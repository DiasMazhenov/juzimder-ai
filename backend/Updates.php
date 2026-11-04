<?php
declare(strict_types=1);
namespace Juzimder;

final class AppUpdates {
    private const OWNER = 'DiasMazhenov';
    private const REPOSITORY = 'juzimder-ai';
    private const ZIP_NAME = 'juzimder-ai-shared-hosting.zip';
    private const MAX_METADATA_BYTES = 1048576;
    private const MAX_DOWNLOAD_BYTES = 52428800;
    private const MAX_ARCHIVE_BYTES = 209715200;
    private const MAX_ARCHIVE_FILES = 4000;
    private const MANAGED_DIRECTORIES = ['assets','backend','resources','schemas','vendor'];
    private const MANAGED_FILES = ['.htaccess','htaccess.txt','index.html','router.php','INSTALL.txt','release.json'];

    public function __construct(private readonly Store $store, private readonly string $appRoot) {}

    public static function isInstallationManager(Store $store, string $userId): bool {
        $first = $store->query('SELECT id FROM users ORDER BY created_at ASC,id ASC LIMIT 1')->fetch();
        return is_array($first) && is_string($first['id'] ?? null) && hash_equals($first['id'], $userId);
    }

    public function check(): array {
        $release = $this->latestRelease();
        $current = $this->installedVersion();
        return [
            'currentVersion'=>$current,
            'latestVersion'=>$release['version'],
            'updateAvailable'=>$current === null || self::compareVersions($release['version'], $current) > 0,
            'releaseUrl'=>$release['url'],
            'publishedAt'=>$release['publishedAt'],
        ];
    }

    public function install(string $expectedVersion): array {
        if (!self::validVersion($expectedVersion)) throw new Problem(422, 'Некорректная версия обновления.');
        $release = $this->latestRelease();
        $current = $this->installedVersion();
        if (!hash_equals($release['version'], $expectedVersion)) throw new Problem(409, 'В репозитории уже другая версия. Проверьте обновления ещё раз.');
        if ($current !== null && self::compareVersions($release['version'], $current) <= 0) throw new Problem(409, 'Для этой установки нет более новой версии.');
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

            $archive = $this->request($release['zipUrl'], self::MAX_DOWNLOAD_BYTES);
            $checksumFile = $this->request($release['checksumUrl'], 4096);
            $checksum = self::parseChecksum($checksumFile['body']);
            if ($checksum === null || !hash_equals($checksum, hash('sha256', $archive['body']))) throw new Problem(502, 'Контрольная сумма обновления не совпала. Файлы приложения не менялись.');
            if ($release['zipDigest'] !== null && !hash_equals($release['zipDigest'], $checksum)) throw new Problem(502, 'GitHub сообщил другую контрольную сумму файла обновления.');

            $archivePath = $workspace . '/update.zip';
            if (file_put_contents($archivePath, $archive['body'], LOCK_EX) !== strlen($archive['body'])) throw new Problem(503, 'Не удалось сохранить скачанный архив во временный каталог.');
            @chmod($archivePath, 0600);
            unset($archive, $checksumFile);
            $this->extractArchive($archivePath, $workspace . '/extract', $expectedVersion);
            $dataBackup = $this->backupDatabase($updateRoot, $expectedVersion);

            if (file_put_contents($maintenance, (string)time(), LOCK_EX) === false) throw new Problem(503, 'Не удалось включить режим обслуживания. Обновление отменено.');
            @chmod($maintenance, 0600);
            $maintenanceEnabled = true;
            $this->replaceApplicationFiles($workspace . '/extract', $workspace . '/rollback', $changes);
            if (!@unlink($maintenance)) throw new Problem(503, 'Не удалось завершить обновление. Предыдущие файлы приложения будут восстановлены.');
            $maintenanceEnabled = false;
            $this->pruneDataBackups($updateRoot, $dataBackup);
            return ['ok'=>true,'version'=>$expectedVersion,'message'=>'Обновление установлено. Резервная копия базы и ключей сохранена в закрытом каталоге var/updates. Перезагрузите страницу.'];
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

    private function latestRelease(): array {
        $url = 'https://api.github.com/repos/' . self::OWNER . '/' . self::REPOSITORY . '/releases/latest';
        $response = $this->request($url, self::MAX_METADATA_BYTES, 'application/vnd.github+json');
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
            'url'=>$expectedUrl,
            'publishedAt'=>is_string($release['published_at'] ?? null) ? $release['published_at'] : null,
            'zipUrl'=>$zip['browser_download_url'],
            'checksumUrl'=>$checksum['browser_download_url'],
            'zipDigest'=>$digest,
        ];
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
            $required = ['index.html'=>false,'router.php'=>false,'.htaccess'=>false,'backend/api.php'=>false,'release.json'=>false];
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

    private function request(string $url, int $maxBytes, string $accept = 'application/octet-stream', int $redirects = 0): array {
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        $port = parse_url($url, PHP_URL_PORT);
        $allowedHosts = ['api.github.com','github.com','release-assets.githubusercontent.com','objects.githubusercontent.com'];
        if ($scheme !== 'https' || !in_array($host, $allowedHosts, true) || ($port !== null && $port !== 443) || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null) throw new Problem(502, 'Источник обновления не прошёл проверку адреса.');
        if ($redirects > 3) throw new Problem(502, 'Слишком много перенаправлений при загрузке обновления.');
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
            return $this->request($next, $maxBytes, $accept, $redirects + 1);
        }
        if ($status < 200 || $status >= 300) throw new Problem($status === 404 ? 404 : 503, $status === 404 ? 'В репозитории ещё нет опубликованного стабильного выпуска.' : 'GitHub временно не ответил на запрос обновления. Повторите позже.');
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
