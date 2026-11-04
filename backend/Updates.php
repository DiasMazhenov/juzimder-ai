<?php
declare(strict_types=1);
namespace Juzimder;

final class AppUpdates {
    private const OWNER = 'DiasMazhenov';
    private const REPOSITORY = 'juzimder-ai';
    private const ZIP_NAME = 'juzimder-ai-shared-hosting.zip';
    private const BUILD_WORKFLOW = 'build-shared-hosting.yml';
    private const BUILD_BRANCH = 'shared-hosting-build';
    private const BUILD_MANIFEST = 'repository-build.json';
    private const MAX_METADATA_BYTES = 1048576;
    private const MAX_DOWNLOAD_BYTES = 52428800;
    private const MAX_ARCHIVE_BYTES = 209715200;
    private const MAX_ARCHIVE_FILES = 4000;
    private const MANAGED_DIRECTORIES = ['assets','backend','resources','schemas','vendor'];
    private const MANAGED_FILES = ['.htaccess','htaccess.txt','index.html','favicon.png','router.php','INSTALL.txt','release.json'];

    private readonly ?\Closure $requestOverride;

    public function __construct(private readonly Store $store, private readonly string $appRoot, ?callable $requestOverride = null) {
        $this->requestOverride = $requestOverride === null ? null : \Closure::fromCallable($requestOverride);
    }

    public static function isInstallationManager(Store $store, string $userId): bool {
        $first = $store->query('SELECT id FROM users ORDER BY created_at ASC,id ASC LIMIT 1')->fetch();
        return is_array($first) && is_string($first['id'] ?? null) && hash_equals($first['id'], $userId);
    }

    public function check(): array {
        $package = $this->latestPackage();
        $current = $this->installedVersion();
        return [
            'currentVersion'=>$current,
            'latestVersion'=>$package['version'],
            'updateAvailable'=>$current === null || self::compareVersions($package['version'], $current) > 0,
            'source'=>$package['source'],
            'sourceLabel'=>$package['sourceLabel'],
            'sourceUrl'=>$package['url'],
            'sourceCommit'=>$package['sourceCommit'],
            'publishedAt'=>$package['publishedAt'],
        ];
    }

    public function install(string $expectedVersion, string $expectedSource, ?string $expectedCommit = null): array {
        if (!self::validVersion($expectedVersion)) throw new Problem(422, 'Некорректная версия обновления.');
        if (!in_array($expectedSource, ['release','repository'], true)) throw new Problem(422, 'Некорректный источник обновления.');
        if (($expectedSource === 'repository' && (!is_string($expectedCommit) || !preg_match('/^[a-f0-9]{40}$/iD', $expectedCommit))) || ($expectedSource === 'release' && $expectedCommit !== null)) throw new Problem(422, 'Источник обновления изменился. Проверьте обновления ещё раз.');
        $package = $this->latestPackage();
        $current = $this->installedVersion();
        if (!hash_equals($package['version'], $expectedVersion) || !hash_equals($package['source'], $expectedSource) || ($expectedSource === 'repository' && !hash_equals($package['sourceCommit'], strtolower((string)$expectedCommit)))) throw new Problem(409, 'Сборка в репозитории изменилась. Проверьте обновления ещё раз.');
        if ($current !== null && self::compareVersions($package['version'], $current) <= 0) throw new Problem(409, 'Для этой установки нет более новой версии.');
        if (!class_exists(\ZipArchive::class)) throw new Problem(503, 'На хостинге не включено PHP-расширение ZIP.');

        $varDir = $this->appRoot . '/var';
        if (is_link($varDir) || (!is_dir($varDir) && !@mkdir($varDir, 0700, true) && !is_dir($varDir))) throw new Problem(503, 'Каталог var должен быть обычным закрытым каталогом с правом записи для PHP.');
        @chmod($varDir, 0700);
        $updateRoot = $varDir . '/updates';
        if (is_link($updateRoot) || (!is_dir($updateRoot) && !@mkdir($updateRoot, 0700, true) && !is_dir($updateRoot))) throw new Problem(503, 'Не удалось создать закрытый каталог обновлений в var.');
        $lock = @fopen($updateRoot . '/install.lock', 'c');
        if (!is_resource($lock)) throw new Problem(503, 'Не удалось создать блокировку обновления.');
        if (!flock($lock, LOCK_EX | LOCK_NB)) { fclose($lock); throw new Problem(409, 'Обновление уже выполняется. Подождите и проверьте версию снова.'); }

        $workspace = $updateRoot . '/run-' . bin2hex(random_bytes(12));
        $maintenance = $varDir . '/update-maintenance';
        $changes = [];
        $maintenanceEnabled = false;
        try {
            if (!@mkdir($workspace, 0700) || !@mkdir($workspace . '/extract', 0700) || !@mkdir($workspace . '/rollback', 0700)) throw new Problem(503, 'Не удалось подготовить временное место для обновления.');

            $archivePath = $package['source'] === 'release'
                ? $this->downloadReleaseArchive($package, $workspace)
                : $this->downloadRepositoryArchive($package, $workspace);
            $this->extractArchive($archivePath, $workspace . '/extract', $expectedVersion);
            $dataBackup = $this->backupDatabase($updateRoot, $expectedVersion);

            if (file_put_contents($maintenance, (string)time(), LOCK_EX) === false) throw new Problem(503, 'Не удалось включить режим обслуживания. Обновление отменено.');
            @chmod($maintenance, 0600);
            $maintenanceEnabled = true;
            $this->replaceApplicationFiles($workspace . '/extract', $workspace . '/rollback', $changes);
            if (!@unlink($maintenance)) throw new Problem(503, 'Не удалось завершить обновление. Предыдущие файлы приложения будут восстановлены.');
            $maintenanceEnabled = false;
            $this->pruneDataBackups($updateRoot, $dataBackup);
            return ['ok'=>true,'version'=>$expectedVersion,'source'=>$package['source'],'sourceCommit'=>$package['sourceCommit'],'message'=>'Обновление установлено. Резервная копия базы и ключей сохранена в закрытом каталоге var/updates. Перезагрузите страницу.'];
        } catch (\Throwable $error) {
            $rollbackFailed = $changes !== [] && !$this->rollback($changes);
            if ($maintenanceEnabled) @unlink($maintenance);
            if ($error instanceof Problem && !$rollbackFailed) throw $error;
            $message = $rollbackFailed
                ? 'Обновление прервалось, и автоматический откат не завершился. Не удаляйте var/; восстановите файлы приложения из резервной копии.'
                : 'Обновление не удалось. Предыдущие файлы приложения восстановлены; проверьте права записи и наличие свободного места.';
            throw new Problem(503, $message);
        } finally {
            self::removeTree($workspace);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function latestPackage(): array {
        $release = null;
        $repository = null;
        $releaseError = null;
        $repositoryError = null;
        try { $release = $this->latestRelease(); } catch (Problem $error) { $releaseError = $error; }
        try { $repository = $this->latestRepositoryBuild(); } catch (Problem $error) { $repositoryError = $error; }
        if ($release === null && $repository === null) {
            foreach ([$repositoryError, $releaseError] as $error) if ($error instanceof Problem && $error->status !== 404) throw $error;
            throw new Problem(404, 'В GitHub пока нет стабильного выпуска или готовой сборки последнего коммита main.');
        }
        if ($repository === null) return $release;
        if ($release === null) return $repository;
        // Keep stable releases preferred when both channels carry the same product version.
        return self::compareVersions($repository['version'], $release['version']) > 0 ? $repository : $release;
    }

    private function latestRelease(): ?array {
        $url = 'https://api.github.com/repos/' . self::OWNER . '/' . self::REPOSITORY . '/releases/latest';
        $response = $this->request($url, self::MAX_METADATA_BYTES, 'application/vnd.github+json', 0, true);
        if ($response['status'] === 404) return null;
        try { $release = json_decode($response['body'], true, 32, JSON_THROW_ON_ERROR); }
        catch (\Throwable) { throw new Problem(502, 'GitHub вернул некорректные данные о выпуске.'); }
        if (!is_array($release) || ($release['draft'] ?? true) !== false || ($release['prerelease'] ?? true) !== false || !is_string($release['tag_name'] ?? null)) throw new Problem(503, 'В репозитории пока нет опубликованного стабильного выпуска.');
        $version = self::versionFromTag($release['tag_name']);
        $expectedUrl = 'https://github.com/' . self::OWNER . '/' . self::REPOSITORY . '/releases/tag/' . rawurlencode($release['tag_name']);
        if ($version === null || !is_string($release['html_url'] ?? null) || !hash_equals($expectedUrl, $release['html_url']) || !is_array($release['assets'] ?? null)) throw new Problem(502, 'Выпуск GitHub не соответствует формату обновлений Juzimder.');
        $assets = [];
        foreach ($release['assets'] as $asset) {
            if (!is_array($asset) || !is_string($asset['name'] ?? null) || !is_string($asset['browser_download_url'] ?? null)) continue;
            $assets[$asset['name']] = $asset;
        }
        $zip = $assets[self::ZIP_NAME] ?? null;
        $checksum = $assets[self::ZIP_NAME . '.sha256'] ?? null;
        if (!is_array($zip) || !is_array($checksum) || ($zip['state'] ?? null) !== 'uploaded' || ($checksum['state'] ?? null) !== 'uploaded') throw new Problem(503, 'У выпуска нет готового hosting ZIP с контрольной суммой.');
        $expectedPrefix = 'https://github.com/' . self::OWNER . '/' . self::REPOSITORY . '/releases/download/' . rawurlencode($release['tag_name']) . '/';
        foreach ([$zip['browser_download_url'], $checksum['browser_download_url']] as $assetUrl) {
            if (!str_starts_with($assetUrl, $expectedPrefix) || parse_url($assetUrl, PHP_URL_SCHEME) !== 'https') throw new Problem(502, 'У выпуска указан недоверенный адрес загрузки.');
        }
        $digest = is_string($zip['digest'] ?? null) && preg_match('/^sha256:([a-f0-9]{64})$/iD', $zip['digest'], $match) ? strtolower($match[1]) : null;
        if (isset($zip['size']) && (!is_int($zip['size']) || $zip['size'] < 1 || $zip['size'] > self::MAX_DOWNLOAD_BYTES)) throw new Problem(413, 'Архив обновления превышает допустимый размер.');
        return [
            'version'=>$version,
            'source'=>'release',
            'sourceLabel'=>'стабильного выпуска GitHub',
            'sourceCommit'=>null,
            'url'=>$expectedUrl,
            'publishedAt'=>is_string($release['published_at'] ?? null) ? $release['published_at'] : null,
            'zipUrl'=>$zip['browser_download_url'],
            'checksumUrl'=>$checksum['browser_download_url'],
            'zipDigest'=>$digest,
        ];
    }

    private function latestRepositoryBuild(): ?array {
        $base = 'https://api.github.com/repos/' . self::OWNER . '/' . self::REPOSITORY;
        $commitResponse = $this->request($base . '/commits/main', self::MAX_METADATA_BYTES, 'application/vnd.github+json', 0, true);
        if ($commitResponse['status'] === 404) return null;
        try { $commit = json_decode($commitResponse['body'], true, 16, JSON_THROW_ON_ERROR); }
        catch (\Throwable) { throw new Problem(502, 'GitHub вернул некорректные данные о последнем коммите.'); }
        $latestSha = is_array($commit) && is_string($commit['sha'] ?? null) ? strtolower($commit['sha']) : '';
        if (!preg_match('/^[a-f0-9]{40}$/D', $latestSha)) throw new Problem(502, 'GitHub вернул некорректный идентификатор коммита.');

        $runsResponse = $this->request($base . '/actions/workflows/' . self::BUILD_WORKFLOW . '/runs?branch=main&event=push&per_page=10', self::MAX_METADATA_BYTES, 'application/vnd.github+json', 0, true);
        if ($runsResponse['status'] === 404) return null;
        try { $runs = json_decode($runsResponse['body'], true, 32, JSON_THROW_ON_ERROR); }
        catch (\Throwable) { throw new Problem(502, 'GitHub вернул некорректные данные о сборках репозитория.'); }
        if (!is_array($runs) || !is_array($runs['workflow_runs'] ?? null)) throw new Problem(502, 'GitHub вернул некорректный список сборок репозитория.');

        $matchingRuns = [];
        foreach ($runs['workflow_runs'] as $run) {
            if (!is_array($run) || ($run['event'] ?? null) !== 'push' || ($run['status'] ?? null) !== 'completed' || ($run['conclusion'] ?? null) !== 'success' || ($run['head_branch'] ?? null) !== 'main' || ($run['path'] ?? null) !== '.github/workflows/' . self::BUILD_WORKFLOW || !is_string($run['head_sha'] ?? null) || !hash_equals($latestSha, strtolower($run['head_sha'])) || !is_array($run['head_repository'] ?? null) || strtolower((string)($run['head_repository']['full_name'] ?? '')) !== strtolower(self::OWNER . '/' . self::REPOSITORY) || !is_int($run['id'] ?? null) || $run['id'] < 1) continue;
            $matchingRuns[] = $run;
        }
        usort($matchingRuns, static fn(array $a,array $b): int => ((int)($b['run_attempt'] ?? 1) <=> (int)($a['run_attempt'] ?? 1)) ?: ((int)($b['run_number'] ?? 0) <=> (int)($a['run_number'] ?? 0)));

        if ($matchingRuns === []) return null;

        $manifestUrl = $base . '/contents/' . self::BUILD_MANIFEST . '?ref=' . rawurlencode(self::BUILD_BRANCH);
        $manifestResponse = $this->request($manifestUrl, 16384, 'application/vnd.github+json', 0, true);
        if ($manifestResponse['status'] === 404) return null;
        try { $manifestEnvelope = json_decode($manifestResponse['body'], true, 16, JSON_THROW_ON_ERROR); }
        catch (\Throwable) { throw new Problem(502, 'GitHub вернул некорректные сведения о repository build.'); }
        if (!is_array($manifestEnvelope) || ($manifestEnvelope['type'] ?? null) !== 'file' || ($manifestEnvelope['encoding'] ?? null) !== 'base64' || !is_string($manifestEnvelope['content'] ?? null) || strlen($manifestEnvelope['content']) > 16384) throw new Problem(502, 'Repository build manifest имеет неожиданный формат.');
        $manifestJson = base64_decode(preg_replace('/\s+/', '', $manifestEnvelope['content']) ?? '', true);
        if (!is_string($manifestJson) || strlen($manifestJson) > 8192) throw new Problem(502, 'Repository build manifest повреждён.');
        try { $manifest = json_decode($manifestJson, true, 16, JSON_THROW_ON_ERROR); }
        catch (\Throwable) { throw new Problem(502, 'Repository build manifest содержит некорректный JSON.'); }
        if (!is_array($manifest) || !is_string($manifest['productVersion'] ?? null) || !self::validVersion($manifest['productVersion']) || !is_string($manifest['sourceCommit'] ?? null) || !preg_match('/^[a-f0-9]{40}$/iD', $manifest['sourceCommit']) || !is_int($manifest['workflowRunId'] ?? null) || $manifest['workflowRunId'] < 1 || !is_int($manifest['workflowRunAttempt'] ?? null) || $manifest['workflowRunAttempt'] < 1 || !is_string($manifest['zipSha256'] ?? null) || !preg_match('/^[a-f0-9]{64}$/iD', $manifest['zipSha256'])) throw new Problem(502, 'Repository build manifest не прошёл проверку.');
        if (!hash_equals($latestSha, strtolower($manifest['sourceCommit']))) return null;
        $matchedRun = null;
        foreach ($matchingRuns as $run) {
            if ($run['id'] === $manifest['workflowRunId'] && (int)($run['run_attempt'] ?? 1) === $manifest['workflowRunAttempt']) { $matchedRun = $run; break; }
        }
        if ($matchedRun === null) return null;

        $rawBase = 'https://raw.githubusercontent.com/' . self::OWNER . '/' . self::REPOSITORY . '/' . rawurlencode(self::BUILD_BRANCH) . '/';
        return [
            'version'=>$manifest['productVersion'],
            'source'=>'repository',
            'sourceLabel'=>'последней успешной сборки репозитория main',
            'sourceCommit'=>$latestSha,
            'url'=>'https://github.com/' . self::OWNER . '/' . self::REPOSITORY . '/commit/' . $latestSha,
            'publishedAt'=>is_string($matchedRun['updated_at'] ?? null) ? $matchedRun['updated_at'] : null,
            'zipUrl'=>$rawBase . self::ZIP_NAME,
            'checksumUrl'=>$rawBase . self::ZIP_NAME . '.sha256',
            'zipDigest'=>strtolower($manifest['zipSha256']),
            'runId'=>$manifest['workflowRunId'],
            'runAttempt'=>$manifest['workflowRunAttempt'],
        ];
    }

    private function downloadReleaseArchive(array $release, string $workspace): string {
        $archive = $this->request($release['zipUrl'], self::MAX_DOWNLOAD_BYTES);
        $checksumFile = $this->request($release['checksumUrl'], 4096);
        $checksum = self::parseChecksum($checksumFile['body']);
        if ($checksum === null || !hash_equals($checksum, hash('sha256', $archive['body']))) throw new Problem(502, 'Контрольная сумма обновления не совпала. Файлы приложения не менялись.');
        if ($release['zipDigest'] !== null && !hash_equals($release['zipDigest'], $checksum)) throw new Problem(502, 'GitHub сообщил другую контрольную сумму файла обновления.');
        $path = $workspace . '/update.zip';
        if (file_put_contents($path, $archive['body'], LOCK_EX) !== strlen($archive['body'])) throw new Problem(503, 'Не удалось сохранить скачанный архив во временный каталог.');
        @chmod($path, 0600);
        return $path;
    }

    private function downloadRepositoryArchive(array $build, string $workspace): string {
        $archive = $this->request($build['zipUrl'], self::MAX_DOWNLOAD_BYTES);
        $checksumFile = $this->request($build['checksumUrl'], 4096);
        $checksum = self::parseChecksum($checksumFile['body']);
        $actualDigest = hash('sha256', $archive['body']);
        if ($checksum === null || !hash_equals($checksum, $actualDigest) || !hash_equals($build['zipDigest'], $actualDigest)) throw new Problem(502, 'Контрольная сумма repository build не совпала. Файлы приложения не менялись.');
        $path = $workspace . '/update.zip';
        if (file_put_contents($path, $archive['body'], LOCK_EX) !== strlen($archive['body'])) throw new Problem(503, 'Не удалось сохранить hosting ZIP во временный каталог.');
        @chmod($path, 0600);
        return $path;
    }

    private function installedVersion(): ?string {
        $path = $this->appRoot . '/release.json';
        if (!is_file($path) || is_link($path)) return null;
        $raw = @file_get_contents($path, false, null, 0, 4097);
        if (!is_string($raw) || strlen($raw) > 4096) return null;
        $manifest = json_decode($raw, true);
        $version = is_array($manifest) && is_string($manifest['productVersion'] ?? null) ? $manifest['productVersion'] : null;
        return $version !== null && self::validVersion($version) ? $version : null;
    }

    private function backupDatabase(string $updateRoot, string $version): string {
        $source = $this->store->path;
        if (!is_file($source) || is_link($source)) throw new Problem(503, 'Не удалось определить файл базы для безопасного обновления.');
        $directory = $updateRoot . '/data-backup-' . str_replace('.', '-', $version) . '-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));
        if (!@mkdir($directory, 0700)) throw new Problem(503, 'Не удалось создать закрытое место для резервной копии перед обновлением.');
        try {
            $target = $directory . '/app.sqlite';
            $quotedTarget = $this->store->db->quote($target);
            if (!is_string($quotedTarget) || $quotedTarget === '') throw new Problem(503, 'Не удалось подготовить резервную копию базы.');
            $this->store->db->exec('VACUUM INTO ' . $quotedTarget);
            if (!is_file($target) || filesize($target) < 100) throw new Problem(503, 'SQLite не создал резервную копию перед обновлением.');
            @chmod($target, 0600);
            $copiedKeys = [];
            foreach (['.ai-key','.forms-key'] as $suffix) {
                $keyPath = $source . $suffix;
                if (!file_exists($keyPath)) continue;
                if (!is_file($keyPath) || is_link($keyPath) || !@copy($keyPath, $directory . '/app.sqlite' . $suffix)) throw new Problem(503, 'Не удалось сохранить ключи шифрования рядом с резервной копией.');
                @chmod($directory . '/app.sqlite' . $suffix, 0600);
                $copiedKeys[] = basename($keyPath);
            }
            $manifest = json_encode(['productVersion'=>$this->installedVersion(),'database'=>'app.sqlite','encryptionKeyFiles'=>$copiedKeys,'createdAt'=>gmdate(DATE_ATOM)], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            if (file_put_contents($directory . '/backup.json', $manifest, LOCK_EX) !== strlen($manifest)) throw new Problem(503, 'Не удалось записать сведения о резервной копии.');
            @chmod($directory . '/backup.json', 0600);
            return $directory;
        } catch (\Throwable $error) {
            self::removeTree($directory);
            if ($error instanceof Problem) throw $error;
            throw new Problem(503, 'Не удалось сохранить SQLite-базу перед обновлением. Файлы приложения не менялись.');
        }
    }

    private function pruneDataBackups(string $updateRoot, string $keep): void {
        $backups = [];
        foreach (new \FilesystemIterator($updateRoot, \FilesystemIterator::SKIP_DOTS) as $entry) {
            if ($entry->isDir() && str_starts_with($entry->getFilename(), 'data-backup-')) $backups[] = $entry->getPathname();
        }
        usort($backups, static fn(string $a,string $b): int => filemtime($b) <=> filemtime($a));
        $kept = 0;
        foreach ($backups as $backup) {
            if ($backup === $keep) { $kept++; continue; }
            if ($kept < 2) { $kept++; continue; }
            self::removeTree($backup);
        }
    }

    private function extractArchive(string $archivePath, string $destination, string $expectedVersion): void {
        $zip = new \ZipArchive();
        if ($zip->open($archivePath) !== true) throw new Problem(422, 'GitHub вернул повреждённый hosting ZIP.');
        try {
            if ($zip->numFiles < 6 || $zip->numFiles > self::MAX_ARCHIVE_FILES) throw new Problem(422, 'В архиве обновления некорректное число файлов.');
            $total = 0;
            $seen = [];
            $required = ['index.html'=>false,'favicon.png'=>false,'router.php'=>false,'.htaccess'=>false,'backend/api.php'=>false,'release.json'=>false];
            $assetsFound = false;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->getNameIndex($index);
                if (!is_string($entry) || str_contains($entry, "\0") || str_contains($entry, '\\') || str_starts_with($entry, '/')) throw new Problem(422, 'Архив обновления содержит недопустимый путь.');
                while (str_starts_with($entry, './')) $entry = substr($entry, 2);
                $directory = str_ends_with($entry, '/');
                $relative = rtrim($entry, '/');
                if ($relative === '' || preg_match('~(^|/)\.{1,2}(/|$)|^[A-Za-z]:|[\x00-\x1F]~', $relative)) throw new Problem(422, 'Архив обновления содержит недопустимый путь.');
                if (isset($seen[$relative])) throw new Problem(422, 'Архив обновления содержит повторяющиеся пути.');
                $seen[$relative] = true;
                $segments = explode('/', $relative);
                if (count($segments) > 20) throw new Problem(422, 'Путь внутри архива слишком глубокий.');
                $top = $segments[0];
                if ($top === 'var') {
                    if ($relative !== 'var' && $relative !== 'var/.htaccess') throw new Problem(422, 'Архив не должен содержать данные из var/.');
                    continue;
                }
                if (!in_array($top, self::MANAGED_DIRECTORIES, true) && !(count($segments) === 1 && in_array($top, self::MANAGED_FILES, true))) throw new Problem(422, 'Архив содержит файл вне разрешённых каталогов приложения.');
                if ($top === 'assets' && !$directory) $assetsFound = true;
                if (isset($required[$relative]) && !$directory) $required[$relative] = true;
                if ($directory) continue;
                $stat = $zip->statIndex($index);
                if (!is_array($stat) || !is_int($stat['size'] ?? null) || $stat['size'] < 0 || $stat['size'] > 41943040) throw new Problem(413, 'Файл в архиве обновления слишком большой.');
                $total += $stat['size'];
                if ($total > self::MAX_ARCHIVE_BYTES) throw new Problem(413, 'Распакованный архив обновления слишком большой.');
                if (method_exists($zip, 'getExternalAttributesIndex')) {
                    $opsys = 0; $attributes = 0;
                    if ($zip->getExternalAttributesIndex($index, $opsys, $attributes)) {
                        $mode = ($attributes >> 16) & 0170000;
                        if ($mode !== 0 && !in_array($mode, [0100000,0040000], true)) throw new Problem(422, 'Архив обновления содержит ссылку или специальный файл.');
                    }
                }
                $target = $destination . '/' . $relative;
                $parent = dirname($target);
                if (!is_dir($parent) && !@mkdir($parent, 0755, true) && !is_dir($parent)) throw new Problem(503, 'Не удалось распаковать обновление во временный каталог.');
                $input = $zip->getStream($zip->getNameIndex($index));
                $output = @fopen($target, 'xb');
                if (!is_resource($input) || !is_resource($output)) {
                    if (is_resource($input)) fclose($input);
                    if (is_resource($output)) fclose($output);
                    throw new Problem(503, 'Не удалось распаковать файл обновления.');
                }
                $copied = stream_copy_to_stream($input, $output, $stat['size'] + 1);
                fclose($input); fclose($output); @chmod($target, 0644);
                if (!is_int($copied) || $copied !== $stat['size']) throw new Problem(422, 'Размер распакованного файла не совпал с архивом.');
            }
            if (!$assetsFound || in_array(false, $required, true)) throw new Problem(422, 'В hosting ZIP не хватает обязательных файлов.');
            $manifestRaw = @file_get_contents($destination . '/release.json', false, null, 0, 4097);
            $manifest = is_string($manifestRaw) ? json_decode($manifestRaw, true) : null;
            if (!is_array($manifest) || !is_string($manifest['productVersion'] ?? null) || !hash_equals($expectedVersion, $manifest['productVersion'])) throw new Problem(422, 'Версия внутри ZIP не совпадает с выбранным выпуском.');
        } finally {
            $zip->close();
        }
    }

    private function replaceApplicationFiles(string $stagedRoot, string $rollbackRoot, array &$changes): void {
        foreach (self::MANAGED_DIRECTORIES as $name) {
            $source = $stagedRoot . '/' . $name;
            if (is_dir($source)) $this->replacePath($source, $this->appRoot . '/' . $name, $rollbackRoot . '/' . $name, $changes);
        }
        foreach (self::MANAGED_FILES as $name) {
            $source = $stagedRoot . '/' . $name;
            if (is_file($source)) $this->replacePath($source, $this->appRoot . '/' . $name, $rollbackRoot . '/' . $name, $changes);
        }
    }

    private function replacePath(string $source, string $target, string $backup, array &$changes): void {
        $hadPrevious = file_exists($target) || is_link($target);
        if (is_link($target)) throw new Problem(503, 'Обновление остановлено: целевой путь приложения является ссылкой.');
        if ($hadPrevious) {
            $backupParent = dirname($backup);
            if (!is_dir($backupParent) && !@mkdir($backupParent, 0700, true) && !is_dir($backupParent)) throw new Problem(503, 'Не удалось подготовить откат обновления.');
            if (!@rename($target, $backup)) throw new Problem(503, 'PHP не может заменить файлы приложения. Проверьте права записи.');
        }
        $changes[] = ['target'=>$target,'backup'=>$backup,'hadPrevious'=>$hadPrevious,'installed'=>false];
        $index = array_key_last($changes);
        if (!@rename($source, $target)) throw new Problem(503, 'Не удалось установить новый файл приложения.');
        $changes[$index]['installed'] = true;
    }

    private function rollback(array $changes): bool {
        $ok = true;
        foreach (array_reverse($changes) as $change) {
            $target = $change['target']; $backup = $change['backup'];
            if (($change['installed'] ?? false) && (file_exists($target) || is_link($target))) $ok = self::removeTree($target) && $ok;
            if (($change['hadPrevious'] ?? false) && (file_exists($backup) || is_link($backup))) {
                if (!@rename($backup, $target)) $ok = false;
            }
        }
        return $ok;
    }

    private function request(string $url, int $maxBytes, string $accept = 'application/octet-stream', int $redirects = 0, bool $allowNotFound = false): array {
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        $port = parse_url($url, PHP_URL_PORT);
        $allowedHosts = ['api.github.com','github.com','release-assets.githubusercontent.com','objects.githubusercontent.com','raw.githubusercontent.com'];
        if ($scheme !== 'https' || !in_array($host, $allowedHosts, true) || ($port !== null && $port !== 443) || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null) throw new Problem(502, 'Источник обновления не прошёл проверку адреса.');
        if ($redirects > 3) throw new Problem(502, 'Слишком много перенаправлений при загрузке обновления.');
        if ($this->requestOverride !== null) {
            $response = ($this->requestOverride)($url);
            if (!is_array($response) || !is_string($response['body'] ?? null) || !is_int($response['status'] ?? null) || strlen($response['body']) > $maxBytes) throw new Problem(502, 'Тестовый источник обновлений вернул некорректный ответ.');
            if ($allowNotFound && $response['status'] === 404) return $response;
            if ($response['status'] < 200 || $response['status'] >= 300) throw new Problem($response['status'] === 404 ? 404 : 503, 'GitHub временно не ответил на запрос обновления. Повторите позже.');
            return $response;
        }
        if (function_exists('curl_init')) {
            $body = ''; $headers = []; $tooLarge = false;
            $handle = curl_init($url);
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER=>false,
                CURLOPT_FOLLOWLOCATION=>false,
                CURLOPT_CONNECTTIMEOUT=>8,
                CURLOPT_TIMEOUT=>30,
                CURLOPT_SSL_VERIFYPEER=>true,
                CURLOPT_SSL_VERIFYHOST=>2,
                CURLOPT_HTTPHEADER=>['Accept: '.$accept,'User-Agent: Juzimder-AI-Updater','X-GitHub-Api-Version: 2026-03-10'],
                CURLOPT_HEADERFUNCTION=>static function($curl, string $line) use (&$headers): int { $parts=explode(':',$line,2);if(count($parts)===2)$headers[strtolower(trim($parts[0]))]=trim($parts[1]);return strlen($line); },
                CURLOPT_WRITEFUNCTION=>static function($curl, string $chunk) use (&$body,$maxBytes,&$tooLarge): int { if(strlen($body)+strlen($chunk)>$maxBytes){$tooLarge=true;return 0;}$body.=$chunk;return strlen($chunk); },
            ]);
            $ok = curl_exec($handle); $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE); $error = curl_error($handle); curl_close($handle);
            if ($tooLarge) throw new Problem(413, 'Ответ GitHub превышает допустимый размер.');
            if ($ok === false) throw new Problem(503, 'Хостинг не смог подключиться к GitHub: ' . (preg_match('/timeout|resolve|connect/i', $error) ? 'проверьте исходящее соединение и DNS.' : 'проверьте поддержку HTTPS и cURL.'));
        } elseif (filter_var((string)ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            $context = stream_context_create([
                'http'=>['method'=>'GET','header'=>"Accept: $accept\r\nUser-Agent: Juzimder-AI-Updater\r\nX-GitHub-Api-Version: 2026-03-10\r\n",'timeout'=>30,'ignore_errors'=>true,'follow_location'=>0,'max_redirects'=>0],
                'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false],
            ]);
            $stream = @fopen($url, 'rb', false, $context);
            if (!is_resource($stream)) throw new Problem(503, 'Хостинг не смог подключиться к GitHub. Проверьте исходящие HTTPS-запросы и cURL либо allow_url_fopen.');
            $meta = stream_get_meta_data($stream); $headers = []; $status = 0;
            foreach ($meta['wrapper_data'] ?? [] as $line) { if (preg_match('~^HTTP/\S+\s+(\d{3})~', $line, $match)) $status=(int)$match[1]; elseif (count($parts=explode(':',$line,2))===2) $headers[strtolower(trim($parts[0]))]=trim($parts[1]); }
            $body = stream_get_contents($stream, $maxBytes + 1); fclose($stream);
            if (!is_string($body)) throw new Problem(503, 'Не удалось прочитать ответ GitHub.');
            if (strlen($body) > $maxBytes) throw new Problem(413, 'Ответ GitHub превышает допустимый размер.');
        } else throw new Problem(503, 'Для проверки обновлений включите PHP cURL или allow_url_fopen.');

        if ($status >= 300 && $status < 400 && is_string($headers['location'] ?? null)) {
            $next = self::resolveUrl($url, $headers['location']);
            return $this->request($next, $maxBytes, $accept, $redirects + 1, $allowNotFound);
        }
        if ($allowNotFound && $status === 404) return ['body'=>$body,'status'=>$status];
        if ($status < 200 || $status >= 300) throw new Problem($status === 404 ? 404 : 503, $status === 404 ? 'GitHub не нашёл запрошенный выпуск или артефакт сборки.' : 'GitHub временно не ответил на запрос обновления. Повторите позже.');
        return ['body'=>$body,'status'=>$status];
    }

    private static function resolveUrl(string $base, string $location): string {
        if (str_starts_with($location, 'https://')) return $location;
        if (str_starts_with($location, '/')) return 'https://' . parse_url($base, PHP_URL_HOST) . $location;
        throw new Problem(502, 'GitHub вернул неподдерживаемое перенаправление.');
    }

    private static function parseChecksum(string $contents): ?string {
        if (!preg_match('/\A\s*([a-f0-9]{64})(?:\s+\*?juzimder-ai-shared-hosting\.zip)?\s*\z/iD', $contents, $match)) return null;
        return strtolower($match[1]);
    }

    private static function versionFromTag(string $tag): ?string {
        if (!preg_match('/^v?(\d{2}\.\d{2}\.\d{3})$/D', $tag, $match)) return null;
        return $match[1];
    }

    private static function validVersion(string $version): bool { return preg_match('/^\d{2}\.\d{2}\.\d{3}$/D', $version) === 1; }

    private static function compareVersions(string $left, string $right): int {
        $a = array_map('intval', explode('.', $left)); $b = array_map('intval', explode('.', $right));
        for ($index=0; $index<3; $index++) if ($a[$index] !== $b[$index]) return $a[$index] <=> $b[$index];
        return 0;
    }

    private static function removeTree(string $path): bool {
        if (is_link($path) || is_file($path)) return @unlink($path);
        if (!is_dir($path)) return true;
        $ok = true;
        foreach (new \FilesystemIterator($path, \FilesystemIterator::SKIP_DOTS) as $entry) $ok = self::removeTree($entry->getPathname()) && $ok;
        return @rmdir($path) && $ok;
    }
}
