<?php
declare(strict_types=1);
require_once __DIR__ . '/Domain.php';
require_once __DIR__ . '/Ai.php';
require_once __DIR__ . '/AiCredentials.php';
require_once __DIR__ . '/Operations.php';
require_once __DIR__ . '/StaticSiteExporter.php';
require_once __DIR__ . '/PublicOrigin.php';
use Juzimder\{Store, Problem};
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff'); header('X-Frame-Options: DENY');
ini_set('session.use_strict_mode','1');
$databasePath = getenv('JUZIMDER_DB') ?: __DIR__.'/../var/app.sqlite';
$databaseIdentity = realpath($databasePath) ?: $databasePath;
$defaultDatabasePath = __DIR__.'/../var/app.sqlite';
$defaultDatabaseIdentity = realpath($defaultDatabasePath) ?: $defaultDatabasePath;
$sessionName = $databaseIdentity === $defaultDatabaseIdentity
    ? 'juzimder_session'
    : 'juzimder_' . substr(hash('sha256', $databaseIdentity), 0, 16);
session_name($sessionName); session_set_cookie_params(['httponly'=>true,'samesite'=>'Strict','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off','path'=>'/']); session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$devAuth = getenv('JUZIMDER_DEV_AUTH') === '1' && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true);
function result(mixed $data,int $status=200): never { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); exit; }
function expireLocalSession(): void { $_SESSION=[]; session_regenerate_id(true); $_SESSION['csrf']=bin2hex(random_bytes(32)); }
try {
    $store = new Store($databasePath);
    $method = $_SERVER['REQUEST_METHOD']; $path = parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
    $assetUpload = $method === 'POST' && is_string($path) && preg_match('~^/api/projects/[a-f0-9]{24}/assets$~D', $path) === 1;
    $body = [];
    if ($method !== 'GET') {
        if (!hash_equals($_SESSION['csrf'],$_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) throw new Problem(403,'Обновите страницу: недействительный CSRF-токен.');
        $bodyLimit = $assetUpload ? 4194304 : 2100000;
        if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > $bodyLimit) throw new Problem(413,'Запрос слишком большой.');
        $raw = file_get_contents('php://input',false,null,0,$bodyLimit+1);
        if (!is_string($raw) || strlen($raw)>$bodyLimit) throw new Problem(413,'Запрос слишком большой.');
        if ($assetUpload) {
            if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/octet-stream')) throw new Problem(415,'Передайте файл изображения как двоичные данные.');
            $body['_assetBinary'] = $raw;
        } else {
            try { $decodedBody = json_decode($raw,false,64,JSON_THROW_ON_ERROR); } catch (JsonException) { throw new Problem(422,'Некорректный JSON.'); }
            if (!$decodedBody instanceof stdClass) throw new Problem(422,'Ожидается JSON-объект.');
            $body = get_object_vars($decodedBody);
        }
    }
    if ($path === '/api/session' && $method === 'GET') {
        $sessionUser = $_SESSION['user'] ?? null;
        $sessionExpired = false;
        if (is_array($sessionUser) && is_string($sessionUser['id'] ?? null) && $store->query('SELECT 1 FROM users WHERE id=?', [$sessionUser['id']])->fetchColumn() === false) {
            expireLocalSession();
            $sessionUser = null;
            $sessionExpired = true;
        }
        result(['user'=>$sessionUser,'csrf'=>$_SESSION['csrf'],'devAuth'=>$devAuth,'sessionExpired'=>$sessionExpired]);
    }
    if (in_array($path,['/api/register','/api/login'],true) && $method === 'POST') {
        $identity = strtolower(trim(is_string($body['email'] ?? null) ? $body['email'] : ''));
        $devAdminLogin = $devAuth && $path === '/api/login' && $identity === 'admin';
        $email = $devAdminLogin ? 'admin@localhost.test' : $identity;
        $password = $body['password'] ?? '';
        $validPassword = is_string($password) && strlen($password)<=72 && ($devAdminLogin ? strlen($password)>=1 : strlen($password)>=12);
        if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>254 || !$validPassword) throw new Problem(422,'Укажите email и пароль от 12 до 72 байт.');
        $ip = $_SERVER['REMOTE_ADDR']; $now = time();
        $store->query('DELETE FROM auth_attempts WHERE started_at < ?',[$now-900]);
        $attempt = $store->query('SELECT attempts FROM auth_attempts WHERE ip=?',[$ip])->fetchColumn();
        if ($attempt !== false && (int)$attempt >= 15) throw new Problem(429,'Слишком много попыток. Повторите через 15 минут.');
        $store->query('INSERT INTO auth_attempts(ip,attempts,started_at) VALUES(?,1,?) ON CONFLICT(ip) DO UPDATE SET attempts=attempts+1',[$ip,$now]);
        $user = $store->query('SELECT * FROM users WHERE email=?',[$email])->fetch();
        if ($path === '/api/register') {
            if ($user) throw new Problem(422,'Не удалось зарегистрироваться с этим email.');
            $id = bin2hex(random_bytes(12)); $store->query('INSERT INTO users(id,email,password_hash) VALUES(?,?,?)',[$id,$email,password_hash($password,PASSWORD_DEFAULT)]); $user = ['id'=>$id,'email'=>$email];
        } else {
            if (!$user && $devAdminLogin && $password === 'admin') {
                $id = bin2hex(random_bytes(12));
                $store->query('INSERT INTO users(id,email,password_hash) VALUES(?,?,?)',[$id,$email,password_hash('admin',PASSWORD_DEFAULT)]);
                $user = $store->query('SELECT * FROM users WHERE email=?',[$email])->fetch();
            }
            if (!$user || !password_verify($password,$user['password_hash'])) throw new Problem(401,'Неверный email или пароль.');
        }
        session_regenerate_id(true); $_SESSION['user']=['id'=>$user['id'],'email'=>$user['email']]; $_SESSION['csrf']=bin2hex(random_bytes(32)); result(['user'=>$_SESSION['user'],'csrf'=>$_SESSION['csrf']]);
    }
    $user = $_SESSION['user']['id'] ?? null; if (!$user) throw new Problem(401,'Войдите в аккаунт.');
    if ($store->query('SELECT 1 FROM users WHERE id=?', [$user])->fetchColumn() === false) {
        expireLocalSession();
        throw new Problem(401,'Локальная сессия устарела. Войдите снова.');
    }
    if (preg_match('~^/api/projects/([a-f0-9]{24})/assets(?:/([a-f0-9]{24})/file)?$~D', $path, $assetMatch)) {
        $projectId = $assetMatch[1]; $assetId = $assetMatch[2] ?? '';
        if ($method === 'GET' && $assetId === '') result($store->listAssets($projectId, $user));
        if ($method === 'GET' && $assetId !== '') {
            $asset = $store->assetFile($projectId, $user, $assetId);
            $extension = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'][$asset['mime']] ?? 'bin';
            header('Content-Type: ' . $asset['mime']); header('Content-Length: ' . strlen($asset['content']));
            header('Content-Disposition: inline; filename="image.' . $extension . '"'); header('Cache-Control: private, no-store');
            header('ETag: "asset-' . hash('sha256', $asset['content']) . '"'); echo $asset['content']; exit;
        }
        if ($method === 'POST' && $assetId === '') {
            if (array_diff(array_keys($body), ['_assetBinary']) || !is_string($body['_assetBinary'] ?? null)) throw new Problem(422, 'Не удалось прочитать загруженное изображение.');
            $nameHeader = $_SERVER['HTTP_X_ASSET_NAME'] ?? '';
            $name = is_string($nameHeader) ? rawurldecode($nameHeader) : '';
            result($store->uploadAsset($projectId, $user, $name, $body['_assetBinary']), 201);
        }
    }
    if (preg_match('~^/api/projects/([a-f0-9]{24})/export/static$~D', $path, $exportMatch) && $method === 'GET') {
        $project = $store->owned($exportMatch[1], $user);
        $assets = $store->referencedAssets($exportMatch[1], $user, $project['document']);
        $archive = Juzimder\StaticSiteExporter::zip($project, $assets);
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="juzimder-site-' . $exportMatch[1] . '.zip"');
        header('Content-Length: ' . strlen($archive));
        echo $archive;
        exit;
    }
    if (preg_match('~^/api/projects/([a-f0-9]{24})/preview/static$~D', $path, $previewMatch) && $method === 'GET') {
        $project = $store->owned($previewMatch[1], $user);
        $assets = $store->referencedAssets($previewMatch[1], $user, $project['document']);
        $files = Juzimder\StaticSiteExporter::files($project, $assets);
        $documentPages = $project['document']->data->pages;
        $homeId = null;
        foreach ($documentPages as $page) {
            $pageId = (string)$page->id;
            if (($project['settings']['pages'][$pageId]['isHome'] ?? false) === true) { $homeId = $pageId; break; }
        }
        $homeId ??= isset($documentPages[0]) ? (string)$documentPages[0]->id : null;
        $previewPages = [];
        foreach ($documentPages as $page) {
            $pageId = (string)$page->id;
            $route = $project['settings']['pages'][$pageId] ?? null;
            if (!is_array($route)) throw new Problem(422, 'Для страницы не найдены настройки маршрута.');
            $isHome = $pageId === $homeId;
            $htmlPath = $isHome ? 'index.html' : (string)$route['slug'] . '/index.html';
            if (!isset($files[$htmlPath])) throw new Problem(422, 'Не удалось подготовить предпросмотр страницы.');
            $html = Juzimder\StaticSiteExporter::inlineAssets($files[$htmlPath], $assets, $previewMatch[1], $isHome);
            $previewPages[] = ['name'=>(string)($page->name ?? 'Страница'),'path'=>$isHome ? '/' : '/' . (string)$route['slug'] . '/', 'html'=>$html];
        }
        $preview = ['version'=>(int)$project['version'],'pages'=>$previewPages,'css'=>$files['assets/site.css'] ?? ''];
        if (strlen(json_encode($preview, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) > 30000000) throw new Problem(413, 'Предпросмотр превышает 30 МБ.');
        result($preview);
    }
    if (preg_match('~^/api/projects/([a-f0-9]{24})/publication$~D', $path, $publicationMatch) && $method === 'GET') {
        $publication = $store->publication($publicationMatch[1], $user);
        result(['publication'=>$publication,'publicUrl'=>$publication ? Juzimder\PublicOrigin::url($publicationMatch[1]) : null]);
    }
    if (preg_match('~^/api/projects/([a-f0-9]{24})/(publish|unpublish)$~D', $path, $publicationActionMatch) && $method === 'POST') {
        if (array_diff(array_keys($body), ['version']) || !is_int($body['version'] ?? null) || $body['version'] < 0) throw new Problem(422, 'Укажите текущую ревизию проекта.');
        $projectId = $publicationActionMatch[1];
        if ($publicationActionMatch[2] === 'publish') {
            $project = $store->owned($projectId, $user);
            if ($project['version'] !== $body['version']) throw new Problem(409, 'Проект изменился. Обновите дашборд и повторите публикацию.');
            $assets = $store->referencedAssets($projectId, $user, $project['document']);
            $files = Juzimder\StaticSiteExporter::files($project, $assets);
            $publication = $store->publish($projectId, $user, $body['version'], $files);
            result(['publication'=>$publication,'publicUrl'=>Juzimder\PublicOrigin::url($projectId)]);
        }
        $publication = $store->unpublish($projectId, $user, $body['version']);
        result(['publication'=>$publication,'publicUrl'=>null]);
    }
    if (preg_match('~^/api/projects/([a-f0-9]{24})/ai/settings$~D', $path, $aiSettingsMatch)) {
        $projectId = $aiSettingsMatch[1];
        $credentials = new Juzimder\AiCredentialVault($store);
        if ($method === 'GET') result($credentials->status($projectId, $user));
        if ($method === 'PUT') {
            if (array_diff(array_keys($body), ['version','apiKey','model','fallbackModel']) || !is_int($body['version'] ?? null) || $body['version'] < 0 || !is_string($body['model'] ?? null) || (array_key_exists('apiKey', $body) && !is_string($body['apiKey'])) || (array_key_exists('fallbackModel', $body) && !is_string($body['fallbackModel']))) throw new Problem(422, 'Укажите версию настроек, модель и API-ключ (ключ можно оставить пустым, если он уже сохранён).');
            result($credentials->save($projectId, $user, $body['version'], $body['apiKey'] ?? null, $body['model'], $body['fallbackModel'] ?? null));
        }
        if ($method === 'DELETE') {
            if (array_diff(array_keys($body), ['version']) || !is_int($body['version'] ?? null) || $body['version'] < 0) throw new Problem(422, 'Укажите текущую версию настроек AI.');
            result($credentials->clear($projectId, $user, $body['version']));
        }
    }
    if (preg_match('~^/api/projects/([a-f0-9]{24})/ai/profiles(?:/([a-f0-9]{24})(?:/(activate))?)?$~D', $path, $aiProfileMatch)) {
        $projectId = $aiProfileMatch[1];
        $profileId = $aiProfileMatch[2] ?? '';
        $action = $aiProfileMatch[3] ?? '';
        $credentials = new Juzimder\AiCredentialVault($store);
        if ($profileId === '' && $method === 'POST') {
            if (array_diff(array_keys($body), ['version','name','apiKey','model','fallbackModel']) || !is_int($body['version'] ?? null) || $body['version'] < 0 || !is_string($body['name'] ?? null) || !is_string($body['apiKey'] ?? null) || !is_string($body['model'] ?? null) || (array_key_exists('fallbackModel', $body) && !is_string($body['fallbackModel']))) {
                throw new Problem(422, 'Укажите версию списка, название профиля, API-ключ и модель.');
            }
            result($credentials->createProfile($projectId, $user, $body['version'], $body['name'], $body['apiKey'], $body['model'], $body['fallbackModel'] ?? null));
        }
        if ($profileId !== '' && $action === '' && $method === 'PUT') {
            if (array_diff(array_keys($body), ['version','name','apiKey','model','fallbackModel']) || !is_int($body['version'] ?? null) || $body['version'] < 0 || !is_string($body['name'] ?? null) || !is_string($body['model'] ?? null) || (array_key_exists('apiKey', $body) && !is_string($body['apiKey'])) || (array_key_exists('fallbackModel', $body) && !is_string($body['fallbackModel']))) {
                throw new Problem(422, 'Укажите версию списка, название профиля и модель; ключ можно оставить пустым, чтобы не менять его.');
            }
            result($credentials->updateProfile($projectId, $user, $body['version'], $profileId, $body['name'], $body['apiKey'] ?? null, $body['model'], $body['fallbackModel'] ?? null));
        }
        if ($profileId !== '' && $action === 'activate' && $method === 'POST') {
            if (array_diff(array_keys($body), ['version']) || !is_int($body['version'] ?? null) || $body['version'] < 0) throw new Problem(422, 'Укажите текущую версию списка профилей AI.');
            result($credentials->activateProfile($projectId, $user, $body['version'], $profileId));
        }
        if ($profileId !== '' && $action === '' && $method === 'DELETE') {
            if (array_diff(array_keys($body), ['version']) || !is_int($body['version'] ?? null) || $body['version'] < 0) throw new Problem(422, 'Укажите текущую версию списка профилей AI.');
            result($credentials->deleteProfile($projectId, $user, $body['version'], $profileId));
        }
    }
    if (preg_match('~^/api/projects/([a-f0-9]{24})/ai/design-plan$~D', $path, $aiPlanMatch) && $method === 'POST') {
        $prompt = $body['prompt'] ?? null;
        if (!is_string($prompt) || trim($prompt) === '' || mb_strlen(trim($prompt), 'UTF-8') > 4000) throw new Problem(422,'Опишите сайт: от 1 до 4000 символов.');
        $credentials = new Juzimder\AiCredentialVault($store);
        $settings = $credentials->settings($aiPlanMatch[1], $user);
        if (!$settings) throw new Problem(503,'AI-провайдер не настроен на сервере.');
        $now = time();
        $storedRequests = $_SESSION['ai_requests'] ?? [];
        if (!is_array($storedRequests)) $storedRequests = [];
        $requests = array_values(array_filter($storedRequests, static fn($at) => is_int($at) && $at > $now - 60));
        if (count($requests) >= 5) throw new Problem(429,'Слишком много AI-запросов. Повторите через минуту.');
        $requests[] = $now;
        $_SESSION['ai_requests'] = $requests;
        // Each provider attempt may wait up to 25 seconds; primary retry and an
        // optional fallback can together exceed PHP's usual 30-second limit.
        set_time_limit(120);
        try {
            result(Juzimder\AiDesignPlanner::create($prompt, new Juzimder\OpenRouterProvider($settings)));
        } catch (Juzimder\AiProviderFailure $failure) {
            throw new Problem(502,$failure->userMessage());
        }
    }
    if (preg_match('~^/api/projects/([a-f0-9]{24})/ai/generate-page$~D', $path, $aiDesignMatch) && $method === 'POST') {
        $prompt = $body['prompt'] ?? null;
        if (array_diff(array_keys($body), ['prompt'])) throw new Problem(422,'Передайте только текстовое описание сайта.');
        if (!is_string($prompt) || trim($prompt) === '' || mb_strlen(trim($prompt), 'UTF-8') > 4000) throw new Problem(422,'Опишите сайт: от 1 до 4000 символов.');
        $credentials = new Juzimder\AiCredentialVault($store);
        $settings = $credentials->settings($aiDesignMatch[1], $user);
        if (!$settings) throw new Problem(503,'AI-провайдер не настроен на сервере.');
        $now = time();
        $storedRequests = $_SESSION['ai_requests'] ?? [];
        if (!is_array($storedRequests)) $storedRequests = [];
        $requests = array_values(array_filter($storedRequests, static fn($at) => is_int($at) && $at > $now - 60));
        if (count($requests) >= 5) throw new Problem(429,'Слишком много AI-запросов. Повторите через минуту.');
        $requests[] = $now;
        $_SESSION['ai_requests'] = $requests;
        set_time_limit(120);
        try {
            result(Juzimder\AiGeneratedWebsite::create($prompt, new Juzimder\OpenRouterProvider($settings)));
        } catch (Juzimder\AiProviderFailure $failure) {
            throw new Problem(502,$failure->userMessage());
        }
    }
    if (preg_match('~^/api/projects/([a-f0-9]{24})/operations$~D', $path, $operationMatch) && $method === 'POST') {
        if (array_diff(array_keys($body), ['version','operations']) || !is_int($body['version'] ?? null) || $body['version'] < 0) throw new Problem(422,'Укажите текущую версию и список операций.');
        $projectId = $operationMatch[1];
        $project = $store->owned($projectId, $user);
        if ($project['version'] !== $body['version']) throw new Problem(409,'Версия проекта изменилась. Загрузите актуальную версию перед применением операций.');
        $updatedDocument = Juzimder\ProjectOperations::apply($project['document'], $body['operations'] ?? null);
        result($store->save($projectId, $user, $body['version'], $updatedDocument));
    }
    if (preg_match('~^/api/projects/([a-f0-9]{24})/settings$~D', $path, $settingsMatch) && $method === 'PUT') {
        if (array_diff(array_keys($body), ['version','designTokens','seo']) || !is_int($body['version'] ?? null) || $body['version'] < 0 || (!array_key_exists('designTokens', $body) && !array_key_exists('seo', $body))) throw new Problem(422,'Укажите текущую версию и параметры настроек проекта.');
        $projectId = $settingsMatch[1];
        $project = $store->owned($projectId, $user);
        $settings = $project['settings'];
        $document = $project['document'];
        if (array_key_exists('designTokens', $body)) {
            $tokens = Juzimder\ProjectSettings::validateDesignTokens($body['designTokens'], $project['settings']['designTokens']);
            $settings['designTokens'] = $tokens;
            $document = Juzimder\ProjectSettings::applyDesignTokensToDocument($document, $tokens);
        }
        if (array_key_exists('seo', $body)) {
            $seoInput = $body['seo'];
            if ($seoInput instanceof stdClass) $seoInput = get_object_vars($seoInput);
            if (!is_array($seoInput) || array_diff(array_keys($seoInput), ['pageId','title','description','robots','socialImage']) || !is_string($seoInput['pageId'] ?? null) || $seoInput['pageId'] === '') throw new Problem(422,'Укажите страницу и разрешённые SEO-поля.');
            $pageId = $seoInput['pageId'];
            unset($seoInput['pageId']);
            if (!array_key_exists($pageId, $settings['pages'])) throw new Problem(422,'Указанная страница не принадлежит проекту.');
            $settings['pages'][$pageId]['seo'] = Juzimder\ProjectSettings::validateSeoMetadata($seoInput, $settings['pages'][$pageId]['seo']);
        }
        result($store->save($projectId, $user, $body['version'], $document, $settings));
    }
    if (preg_match('~^/api/projects/([a-f0-9]{24})/pages(?:/([A-Za-z0-9_-]{1,128}))?$~D',$path,$pageMatch)) {
        $projectId = $pageMatch[1];
        $pageId = $pageMatch[2] ?? null;
        if ($method === 'GET' && $pageId === null) result(['pages'=>$store->pages($projectId,$user),'project'=>$store->owned($projectId,$user)]);
        if ($method === 'POST') {
            $allowed = $pageId === null ? ['version','action','name'] : ['version','action','name','slug'];
            if (array_diff(array_keys($body),$allowed) || !is_int($body['version'] ?? null) || $body['version'] < 0 || !is_string($body['action'] ?? null)) throw new Problem(422,'Укажите действие и текущую версию проекта.');
            if (isset($body['name']) && !is_string($body['name'])) throw new Problem(422,'Название страницы должно быть текстом.');
            if (isset($body['slug']) && !is_string($body['slug'])) throw new Problem(422,'URL страницы должен быть текстом.');
            if ($pageId === null && $body['action'] !== 'create') throw new Problem(422,'Для этого действия укажите страницу.');
            $value = $body['action'] === 'slug' ? ($body['slug'] ?? null) : ($body['name'] ?? null);
            result($store->mutatePage($projectId,$user,$body['version'],$pageId,$body['action'],$value));
        }
    }
    if ($path === '/api/logout' && $method === 'POST') { $_SESSION=[]; session_destroy(); result(['ok'=>true]); }
    if ($path === '/api/projects') {
        if ($method === 'GET') result($store->query('SELECT p.id,p.name,d.version,d.updated_at FROM projects p JOIN documents d ON p.id=d.project_id WHERE owner_id=? ORDER BY p.created_at DESC',[$user])->fetchAll());
        if ($method === 'POST') { if (!is_string($body['name'] ?? null)) throw new Problem(422,'Укажите название.'); result($store->create($user,$body['name']),201); }
    }
    if (preg_match('~^/api/projects/([a-f0-9]{24})(/revisions(?:/restore)?)?$~D',$path,$match)) {
        $id=$match[1]; $store->owned($id,$user);
        if (($match[2] ?? '') === '/revisions' && $method === 'GET') result($store->query('SELECT version,created_at FROM revisions WHERE project_id=? ORDER BY version DESC LIMIT 50',[$id])->fetchAll());
        if (($match[2] ?? '') === '/revisions/restore' && $method === 'POST') {
            if (!is_int($body['currentVersion'] ?? null) || !is_int($body['targetVersion'] ?? null) || $body['currentVersion'] < 0 || $body['targetVersion'] < 1) throw new Problem(422,'Некорректная версия ревизии.');
            result($store->restore($id,$user,$body['currentVersion'],$body['targetVersion']));
        }
        if (empty($match[2]) && $method === 'GET') result($store->owned($id,$user));
        if (empty($match[2]) && $method === 'PUT') {
            if (array_diff(array_keys($body), ['version','document','settings']) || !is_int($body['version'] ?? null) || $body['version'] < 0) throw new Problem(422,'Укажите версию проекта.');
            $settingsOverride = null;
            if (array_key_exists('settings', $body)) {
                if (!$body['settings'] instanceof stdClass) throw new Problem(422,'Настройки проекта должны быть объектом.');
                $settingsInput = get_object_vars($body['settings']);
                if (array_diff(array_keys($settingsInput), ['designTokens']) || !array_key_exists('designTokens', $settingsInput)) throw new Problem(422,'Можно обновить только проверенные design tokens.');
                $current = $store->owned($id, $user);
                $tokens = Juzimder\ProjectSettings::validateDesignTokens($settingsInput['designTokens'], $current['settings']['designTokens']);
                $settingsOverride = $current['settings'];
                $settingsOverride['designTokens'] = $tokens;
                $body['document'] = Juzimder\ProjectSettings::applyDesignTokensToDocument($body['document'] ?? null, $tokens);
            }
            result($store->save($id,$user,$body['version'],$body['document'] ?? null,$settingsOverride));
        }
    }
    throw new Problem(404,'Маршрут не найден.');
} catch (Problem $e) { result(['error'=>$e->getMessage()],$e->status); } catch (Throwable $e) { error_log('Juzimder API: '.get_class($e)); result(['error'=>'Внутренняя ошибка сервера.'],500); }
