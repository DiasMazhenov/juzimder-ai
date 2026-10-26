<?php
declare(strict_types=1);
require_once __DIR__ . '/Domain.php';
require_once __DIR__ . '/Ai.php';
require_once __DIR__ . '/StockPhotos.php';
require_once __DIR__ . '/AiCredentials.php';
require_once __DIR__ . '/FormMail.php';
require_once __DIR__ . '/AiSkills.php';
require_once __DIR__ . '/AiGenerationJobs.php';
require_once __DIR__ . '/SkillAnalyzer.php';
require_once __DIR__ . '/AiZeroComposer.php';
require_once __DIR__ . '/Operations.php';
require_once __DIR__ . '/StaticSiteExporter.php';
require_once __DIR__ . '/PublicOrigin.php';
use Juzimder\{Store, Problem, CustomCode};
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
    $sandboxRequest = $method === 'POST' && is_string($path) && preg_match('~^/api/projects/[a-f0-9]{24}/custom-code/sandbox$~D', $path) === 1;
    $sandboxPreviewRequest = $method === 'GET' && is_string($path) && preg_match('~^/api/projects/[a-f0-9]{24}/custom-code/sandbox/[a-f0-9]{64}$~D', $path) === 1;
    $assetUpload = $method === 'POST' && is_string($path) && preg_match('~^/api/projects/[a-f0-9]{24}/assets$~D', $path) === 1;
    $body = [];
    if ($method !== 'GET') {
        if (!$sandboxRequest && !hash_equals($_SESSION['csrf'],$_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) throw new Problem(403,'Обновите страницу: недействительный CSRF-токен.');
        $bodyLimit = $assetUpload ? 4194304 : ($sandboxRequest ? 100000 : 2100000);
        if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > $bodyLimit) throw new Problem(413,'Запрос слишком большой.');
        $raw = file_get_contents('php://input',false,null,0,$bodyLimit+1);
        if (!is_string($raw) || strlen($raw)>$bodyLimit) throw new Problem(413,'Запрос слишком большой.');
        if ($assetUpload) {
            if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/octet-stream')) throw new Problem(415,'Передайте файл изображения как двоичные данные.');
            $body['_assetBinary'] = $raw;
        } elseif ($sandboxRequest) {
            if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/x-www-form-urlencoded')) throw new Problem(415,'Передайте данные Custom Code как форму.');
            parse_str($raw, $body);
            if (!hash_equals($_SESSION['csrf'], is_string($body['csrf'] ?? null) ? $body['csrf'] : '')) throw new Problem(403,'Обновите страницу: недействительный CSRF-токен.');
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
    if (preg_match('~^/api/projects/resolve/([a-z0-9](?:[a-z0-9-]{0,78}[a-z0-9])?)$~D', $path, $slugMatch) && $method === 'GET') {
        result($store->resolveProjectSlug($slugMatch[1], $user));
    }
    if (preg_match('~^/api/projects/([a-f0-9]{24})/form-submissions$~D', $path, $formInboxMatch) && $method === 'GET') {
        result(['submissions'=>$store->listFormSubmissions($formInboxMatch[1],$user)]);
    }
    if (preg_match('~^/api/projects/([a-f0-9]{24})/form-submissions/([a-f0-9]{24})/delete$~D', $path, $formDeleteMatch) && $method === 'POST') {
        if ($body) throw new Problem(422,'Удаление заявки не принимает дополнительные поля.');
        $store->deleteFormSubmission($formDeleteMatch[1],$user,$formDeleteMatch[2]);
        result(['ok'=>true]);
    }
    if ($sandboxRequest && preg_match('~^/api/projects/([a-f0-9]{24})/custom-code/sandbox$~D', $path, $sandboxMatch)) {
        $store->owned($sandboxMatch[1], $user);
        $kind = $body['kind'] ?? '';
        if (array_diff(array_keys($body), ['csrf','kind','html','css','js','deadline']) || !is_string($kind)) throw new Problem(422,'Некорректные параметры песочницы.');
        $payload = match ($kind) {
            'custom-code' => CustomCode::decode(CustomCode::encode(['html'=>$body['html'] ?? null,'css'=>$body['css'] ?? null,'js'=>$body['js'] ?? null])),
            'countdown' => CustomCode::countdownPayload($body['deadline'] ?? null),
            default => throw new Problem(422,'Неизвестный тип песочницы.')
        };
        foreach ($_SESSION['custom_code_previews'] ?? [] as $token=>$preview) if (!is_array($preview) || ($preview['expires'] ?? 0) < time()) unset($_SESSION['custom_code_previews'][$token]);
        $token = bin2hex(random_bytes(32));
        $_SESSION['custom_code_previews'][$token] = ['projectId'=>$sandboxMatch[1],'payload'=>$payload,'expires'=>time()+60];
        result(['url'=>'/api/projects/'.$sandboxMatch[1].'/custom-code/sandbox/'.$token]);
    }
    if ($sandboxPreviewRequest && preg_match('~^/api/projects/([a-f0-9]{24})/custom-code/sandbox/([a-f0-9]{64})$~D', $path, $sandboxPreviewMatch)) {
        $store->owned($sandboxPreviewMatch[1], $user);
        $preview = $_SESSION['custom_code_previews'][$sandboxPreviewMatch[2]] ?? null;
        unset($_SESSION['custom_code_previews'][$sandboxPreviewMatch[2]]);
        if (!is_array($preview) || ($preview['projectId'] ?? null) !== $sandboxPreviewMatch[1] || ($preview['expires'] ?? 0) < time() || !is_array($preview['payload'] ?? null)) throw new Problem(404,'Предпросмотр истёк. Запустите его снова.');
        $nonce = bin2hex(random_bytes(16));
        header_remove('X-Frame-Options'); header('X-Frame-Options: SAMEORIGIN');
        header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-store');
        $sandboxScripts = $preview['payload']['js'] !== '' ? 'sandbox allow-scripts' : 'sandbox';
        header("Content-Security-Policy: " . $sandboxScripts . "; default-src 'none'; script-src 'nonce-" . $nonce . "'; connect-src 'none'; img-src data: blob:; media-src data: blob:; font-src data:; style-src 'unsafe-inline'; frame-src 'none'; object-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'");
        echo CustomCode::sandboxDocument($preview['payload'], $nonce); exit;
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
    if (preg_match('~^/api/projects/([a-f0-9]{24})/pages/([A-Za-z0-9_-]{1,128})/view$~D', $path, $viewMatch) && $method === 'GET') {
        $project=$store->owned($viewMatch[1],$user);
        $assets=$store->referencedAssets($viewMatch[1],$user,$project['document']);
        $files=Juzimder\StaticSiteExporter::files($project,$assets);
        $pages=$project['document']->data->pages; $homeId=null; $target=null;
        foreach($pages as $page) { if(($project['settings']['pages'][(string)$page->id]['isHome']??false)===true)$homeId=(string)$page->id; if((string)$page->id===$viewMatch[2])$target=$page; }
        $homeId??=(string)$pages[0]->id;
        if(!$target)throw new Problem(404,'Страница не найдена.');
        $isHome=(string)$target->id===$homeId;
        $route=$project['settings']['pages'][(string)$target->id];
        $html=$files[$isHome?'index.html':$route['slug'].'/index.html'];
        $css=$files['assets/site.css'];
        foreach($assets as $assetId=>$asset){$ext=['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'][$asset['mime']];$css=str_replace('../media/'.$viewMatch[1].'/'.$assetId.'.'.$ext,'data:'.$asset['mime'].';base64,'.base64_encode($asset['content']),$css);}
        $html=Juzimder\StaticSiteExporter::inlineAssets($html,$assets,$viewMatch[1],$isHome);
        $html=preg_replace_callback('~<link rel="stylesheet"[^>]*>~',static fn()=>'<style>'.$css.'</style>',$html);
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Security-Policy: ' . Juzimder\StaticSiteExporter::pageSecurityPolicy($html));
        header('Cross-Origin-Opener-Policy: same-origin');
        echo $html;exit;
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
        $preview = ['version'=>(int)$project['version'],'pages'=>$previewPages,'css'=>Juzimder\StaticSiteExporter::inlineBackgroundStyles($files['assets/site.css'] ?? '',$assets,$previewMatch[1])];
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
    if(preg_match('~^/api/projects/([a-f0-9]{24})/stock-photos$~D',$path,$photoMatch)&&$method==='GET'){
        $store->owned($photoMatch[1],$user);
        $query=$_GET['q']??null;if(!is_string($query))throw new Problem(422,'Укажите тему фото.');
        $requests=array_values(array_filter($_SESSION['photo_requests']??[],static fn($at)=>is_int($at)&&$at>time()-60));
        if(count($requests)>=24)throw new Problem(429,'Слишком много поисков фото. Повторите через минуту.');
        $requests[]=time();$_SESSION['photo_requests']=$requests;
        session_write_close();
        result(['photos'=>Juzimder\StockPhotos::search($query),'source'=>'Wikimedia Commons']);
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
    if(preg_match('~^/api/projects/([a-f0-9]{24})/ai/profiles/([a-f0-9]{24})/test$~D',$path,$probeMatch)&&$method==='POST'){
        if(array_diff(array_keys($body),['version'])||!is_int($body['version']??null))throw new Problem(422,'Укажите текущую версию профилей.');
        $vault=new Juzimder\AiCredentialVault($store);
        $status=$vault->status($probeMatch[1],$user);
        if($body['version']!==$status['version'])throw new Problem(409,'Профили изменились. Обновите страницу.');
        if(!in_array($probeMatch[2],array_column($status['profiles'],'id'),true))throw new Problem(404,'Профиль AI не найден.');
        $settings=$vault->settings($probeMatch[1],$user,$probeMatch[2]);
        $stored=$_SESSION['ai_requests']??[];$now=time();
        $requests=array_values(array_filter(is_array($stored)?$stored:[],static fn($at)=>is_int($at)&&$at>$now-60));
        if(count($requests)>=5)throw new Problem(429,'Слишком много AI-запросов. Повторите через минуту.');
        $requests[]=$now;$_SESSION['ai_requests']=$requests;set_time_limit(30);
        $probeSettings=Juzimder\OpenRouterSettings::fromCredentials($settings->apiKey,$settings->model);
        try{result(Juzimder\AiProfileProbe::check(new Juzimder\OpenRouterProvider($probeSettings,null,microtime(true)+20,false)));}
        catch(Juzimder\AiProviderFailure $failure){throw new Problem(502,$failure->userMessage());}
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
    if(preg_match('~^/api/projects/([a-f0-9]{24})/ai/skills/analyze$~D',$path,$skillAnalysisMatch)&&$method==='POST'){
        if(array_diff(array_keys($body),['version','content'])||!is_int($body['version']??null)||$body['version']<0||!is_string($body['content']??null))throw new Problem(422,'Передайте текущую версию библиотеки и текст Skill.');
        $library=(new Juzimder\ProjectAiSkills($store))->list($skillAnalysisMatch[1],$user);
        if($library['version']!==$body['version'])throw new Problem(409,'Библиотека Skills изменилась. Обновите страницу перед разбором.');
        try{$content=Juzimder\SkillAnalyzer::validateSource($body['content']);}catch(InvalidArgumentException $error){throw new Problem(422,$error->getMessage());}
        $settings=(new Juzimder\AiCredentialVault($store))->failoverSettings($skillAnalysisMatch[1],$user);
        if(!$settings)throw new Problem(503,'Настройте профиль OpenRouter в AI Engine.');
        $now=time();$stored=$_SESSION['ai_requests']??[];
        $requests=array_values(array_filter(is_array($stored)?$stored:[],static fn($at)=>is_int($at)&&$at>$now-60));
        if(count($requests)>=5)throw new Problem(429,'Слишком много AI-запросов. Повторите через минуту.');
        $requests[]=$now;$_SESSION['ai_requests']=$requests;set_time_limit(120);
        try{result(Juzimder\SkillAnalyzer::create($content,new Juzimder\ProfileFailoverProvider($settings)));}
        catch(Juzimder\AiProviderFailure $error){throw new Problem(502,Juzimder\SkillAnalyzer::failureMessage($error));}
    }
    if (preg_match('~^/api/projects/([a-f0-9]{24})/ai/skills(?:/([a-f0-9]{24})(?:/(activate))?)?$~D', $path, $aiSkillMatch)) {
        $projectId=$aiSkillMatch[1];$skillId=$aiSkillMatch[2]??'';$action=$aiSkillMatch[3]??'';$skills=new Juzimder\ProjectAiSkills($store);
        if($skillId===''&&$method==='GET')result($skills->list($projectId,$user));
        if($skillId!==''&&$action===''&&$method==='GET')result($skills->get($projectId,$user,$skillId));
        if($skillId===''&&$method==='POST'){
            if(array_diff(array_keys($body),['version','name','content','manualChecks'])||!is_int($body['version']??null)||$body['version']<0||!is_string($body['name']??null)||!is_string($body['content']??null)||!is_array($body['manualChecks']??null))throw new Problem(422,'Укажите версию списка, название, Markdown-текст и пункты ручной проверки Skill.');
            result($skills->create($projectId,$user,$body['version'],$body['name'],$body['content'],$body['manualChecks']));
        }
        if($skillId!==''&&$action===''&&$method==='PUT'){
            if(array_diff(array_keys($body),['version','name','content','manualChecks'])||!is_int($body['version']??null)||$body['version']<0||!is_string($body['name']??null)||!is_string($body['content']??null)||!is_array($body['manualChecks']??null))throw new Problem(422,'Укажите версию списка, название, Markdown-текст и пункты ручной проверки Skill.');
            result($skills->update($projectId,$user,$body['version'],$skillId,$body['name'],$body['content'],$body['manualChecks']));
        }
        if($skillId!==''&&$action==='activate'&&$method==='POST'){
            if(array_diff(array_keys($body),['version'])||!is_int($body['version']??null)||$body['version']<0)throw new Problem(422,'Укажите текущую версию списка Skills.');
            result($skills->activate($projectId,$user,$body['version'],$skillId));
        }
        if($skillId!==''&&$action===''&&$method==='DELETE'){
            if(array_diff(array_keys($body),['version'])||!is_int($body['version']??null)||$body['version']<0)throw new Problem(422,'Укажите текущую версию списка Skills.');
            result($skills->delete($projectId,$user,$body['version'],$skillId));
        }
    }
    if (preg_match('~^/api/projects/([a-f0-9]{24})/ai/design-plan$~D', $path, $aiPlanMatch) && $method === 'POST') {
        $prompt = $body['prompt'] ?? null;
        if (!is_string($prompt) || trim($prompt) === '' || mb_strlen(trim($prompt), 'UTF-8') > 4000) throw new Problem(422,'Опишите сайт: от 1 до 4000 символов.');
        $credentials = new Juzimder\AiCredentialVault($store);
        $settings = $credentials->failoverSettings($aiPlanMatch[1], $user);
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
            result(Juzimder\AiDesignPlanner::create($prompt, new Juzimder\ProfileFailoverProvider($settings)));
        } catch (Juzimder\AiProviderFailure $failure) {
            throw new Problem(502,$failure->userMessage());
        }
    }
    if (preg_match('~^/api/projects/([a-f0-9]{24})/ai/zero-proposal$~D', $path, $zeroAiMatch) && $method === 'POST') {
        if(array_diff(array_keys($body),['prompt','pageId','blockId','version'])||count($body)!==4||!is_int($body['version']??null)||$body['version']<0)throw new Problem(422,'Передайте описание, страницу, Zero Block и текущую версию проекта.');
        $prompt=$body['prompt'];$pageId=$body['pageId'];$blockId=$body['blockId'];
        if(!is_string($prompt)||trim($prompt)===''||mb_strlen(trim($prompt),'UTF-8')>2000||!is_string($pageId)||$pageId===''||strlen($pageId)>120||!is_string($blockId)||$blockId===''||strlen($blockId)>120)throw new Problem(422,'Проверьте описание композиции и выбранный Zero Block.');
        $projectId=$zeroAiMatch[1];$project=$store->owned($projectId,$user);
        if($project['version']!==$body['version'])throw new Problem(409,'Версия проекта изменилась. Обновите Zero Editor и запросите композицию заново.');
        $found=[];
        foreach($project['document']->data->pages as $page)if(($page->id??null)===$pageId){$walk=function($node)use(&$walk,&$found,$blockId){if(($node->attributes->id??null)===$blockId){if(($node->type??'')!=='zero-block'||!isset($node->zeroData))throw new Problem(422,'Выбранный компонент не является Zero Block.');$found[]=$node->zeroData;}foreach($node->components??[] as $child)$walk($child);};$walk($page->frames[0]->component);}
        if(count($found)!==1)throw new Problem(count($found)?409:404,'Zero Block не найден однозначно на выбранной странице.');
        $credentials=new Juzimder\AiCredentialVault($store);$settings=$credentials->failoverSettings($projectId,$user);if(!$settings)throw new Problem(503,'AI-провайдер не настроен на сервере.');
        $now=time();$storedRequests=$_SESSION['ai_requests']??[];if(!is_array($storedRequests))$storedRequests=[];$requests=array_values(array_filter($storedRequests,static fn($at)=>is_int($at)&&$at>$now-60));if(count($requests)>=5)throw new Problem(429,'Слишком много AI-запросов. Повторите через минуту.');$requests[]=$now;$_SESSION['ai_requests']=$requests;
        set_time_limit(150);
        $provider=new Juzimder\ProfileFailoverProvider($settings);
        try{$proposal=Juzimder\AiZeroComposer::create($prompt,$found[0],$provider);$latest=$store->owned($projectId,$user);if($latest['version']!==$project['version'])throw new Problem(409,'Пока AI готовил композицию, версия проекта изменилась. Запросите новый вариант.');$proposal['baseVersion']=$project['version'];result($proposal);}
        catch(Juzimder\AiProviderFailure $failure){throw new Problem(502,$failure->userMessage().$provider->userAttemptSummary());}
        catch(InvalidArgumentException $failure){throw new Problem(422,$failure->getMessage());}
    }
    if (preg_match('~^/api/projects/([a-f0-9]{24})/ai/edit-component$~D', $path, $aiEditMatch) && $method === 'POST') {
        if (array_diff(array_keys($body), ['prompt','pageId','componentPath','componentId','version']) || count($body) !== 5 || !is_int($body['version'] ?? null) || $body['version'] < 0) throw new Problem(422,'Передайте описание, страницу, путь компонента и текущую версию проекта.');
        $prompt = $body['prompt'] ?? null;
        $pageId = $body['pageId'] ?? null;
        $componentPath = $body['componentPath'] ?? null;
        $componentId = $body['componentId'] ?? null;
        if (!is_string($prompt) || trim($prompt) === '' || mb_strlen(trim($prompt), 'UTF-8') > 2000) throw new Problem(422,'Опишите правку: от 1 до 2000 символов.');
        if (!is_string($pageId) || !is_string($componentId) || !is_array($componentPath)) throw new Problem(422,'Выберите компонент страницы для AI-правки.');
        $projectId = $aiEditMatch[1];
        $project = $store->owned($projectId, $user);
        if ($project['version'] !== $body['version']) throw new Problem(409,'Версия проекта изменилась. Загрузите актуальную версию и подготовьте правку заново.');
        $target = Juzimder\ProjectOperations::componentForAiEdit($project['document'], $pageId, $componentPath, $componentId);
        $credentials = new Juzimder\AiCredentialVault($store);
        $settings = $credentials->failoverSettings($projectId, $user);
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
            $proposal = Juzimder\AiComponentEditor::create($prompt, $target['component'], new Juzimder\ProfileFailoverProvider($settings));
            Juzimder\ProjectOperations::apply($target['document'], $proposal['operations']);
            result(['pageId'=>$pageId,'componentId'=>$target['componentId'],'requestedComponentId'=>$componentId,'needsId'=>$target['needsId'],'baseVersion'=>$project['version'],...$proposal]);
        } catch (Juzimder\AiProviderFailure $failure) {
            throw new Problem(502,$failure->userMessage());
        } catch (InvalidArgumentException $failure) {
            throw new Problem(422,$failure->getMessage());
        }
    }
    if(preg_match('~^/api/projects/([a-f0-9]{24})/ai/generation-jobs/history$~D',$path,$historyMatch)&&$method==='GET'){
        if(!is_string($_GET['pageId']??null)||$_GET['pageId']==='')throw new Problem(422,'Выберите страницу для истории генераций.');
        result((new Juzimder\AiGenerationJobs($store))->history($historyMatch[1],$user,$_GET['pageId']));
    }
    if(preg_match('~^/api/projects/([a-f0-9]{24})/ai/generation-jobs/([a-f0-9]{24})/cancel$~D',$path,$cancelJob)&&$method==='POST'){
        if($body)throw new Problem(422,'Отмена не принимает дополнительные поля.');
        result((new Juzimder\AiGenerationJobs($store))->cancel($cancelJob[1],$user,$cancelJob[2]));
    }
    if(preg_match('~^/api/projects/([a-f0-9]{24})/ai/generation-jobs(?:/([a-f0-9]{24}))?$~D',$path,$jobMatch)&&$method==='GET'){
        result((new Juzimder\AiGenerationJobs($store))->get($jobMatch[1],$user,$jobMatch[2]??null,is_string($_GET['pageId']??null)?$_GET['pageId']:null));
    }
    if (preg_match('~^/api/projects/([a-f0-9]{24})/ai/(generate-page|generation-jobs)$~D', $path, $aiDesignMatch) && $method === 'POST') {
        $prompt = $body['prompt'] ?? null;
        $skillId = array_key_exists('skillId',$body) ? $body['skillId'] : null;
        $skillProfile = array_key_exists('skillProfile', $body) ? $body['skillProfile'] : ($skillId===null?'taste':'standard');
        if (array_diff(array_keys($body), $aiDesignMatch[2]==='generation-jobs'?['prompt','skillProfile','skillId','pageId','version']:['prompt','skillProfile','skillId'])) throw new Problem(422,'Передайте описание сайта и поддерживаемый профиль генерации.');
        if (!is_string($prompt) || trim($prompt) === '' || mb_strlen(trim($prompt), 'UTF-8') > 4000) throw new Problem(422,'Опишите сайт: от 1 до 4000 символов.');
        if (!is_string($skillProfile) || !in_array($skillProfile, ['standard','taste'], true)) throw new Problem(422,'Выберите поддерживаемый профиль генерации.');
        if ($skillId !== null && (!is_string($skillId) || !preg_match('/^[a-f0-9]{24}$/D',$skillId) || $skillProfile==='taste')) throw new Problem(422,'Выберите либо встроенный профиль, либо один пользовательский Skill.');
        $credentials = new Juzimder\AiCredentialVault($store);
        $settings = $credentials->failoverSettings($aiDesignMatch[1], $user);
        if (!$settings) throw new Problem(503,'AI-провайдер не настроен на сервере.');
        $userSkill=$skillId===null?null:(new Juzimder\ProjectAiSkills($store))->forGeneration($aiDesignMatch[1],$user,$skillId);
        $now = time();
        $storedRequests = $_SESSION['ai_requests'] ?? [];
        if (!is_array($storedRequests)) $storedRequests = [];
        $requests = array_values(array_filter($storedRequests, static fn($at) => is_int($at) && $at > $now - 60));
        if (count($requests) >= 5) throw new Problem(429,'Слишком много AI-запросов. Повторите через минуту.');
        $requests[] = $now;
        $_SESSION['ai_requests'] = $requests;
        if($aiDesignMatch[2]==='generation-jobs'){
            if(!is_string($body['pageId']??null)||!is_int($body['version']??null)||$body['version']<0)throw new Problem(422,'Укажите страницу и текущую версию проекта.');
            $jobs=new Juzimder\AiGenerationJobs($store);
            $job=$jobs->create($aiDesignMatch[1],$user,$body['pageId'],$body['version'],['prompt'=>$prompt,'skillProfile'=>$skillProfile,'userSkill'=>$userSkill]);
            $jobs->launch($job['id']);
            result($jobs->get($aiDesignMatch[1],$user,$job['id']),202);
        }
        set_time_limit(120);
        try {
            result(Juzimder\AiGeneratedWebsite::create($prompt, new Juzimder\ProfileFailoverProvider($settings), $skillProfile, $userSkill));
        } catch (Juzimder\AiProviderFailure $failure) {
            throw new Problem(502,$failure->userMessage());
        }
    }
    if (preg_match('~^/api/projects/([a-f0-9]{24})/pages/([A-Za-z0-9_-]{1,128})/zero/([A-Za-z][A-Za-z0-9_-]{0,127})$~D', $path, $zeroMatch) && $method === 'PUT') {
        if (array_diff(array_keys($body), ['version','zeroData']) || !is_int($body['version'] ?? null) || $body['version'] < 0) throw new Problem(422, 'Укажите текущую версию Zero Block.');
        $project = $store->owned($zeroMatch[1], $user);
        $document = \Juzimder\ZeroBlock::updated($project['document'], $zeroMatch[2], $zeroMatch[3], $body['zeroData'] ?? null);
        $saved = $store->save($zeroMatch[1], $user, $body['version'], $document);
        result(['version'=>$saved['version'], 'zeroData'=>$body['zeroData']]);
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
        if (array_diff(array_keys($body), ['version','designTokens','seo','page','forms']) || !is_int($body['version'] ?? null) || $body['version'] < 0 || (!array_key_exists('designTokens', $body) && !array_key_exists('seo', $body) && !array_key_exists('page', $body) && !array_key_exists('forms', $body))) throw new Problem(422,'Укажите текущую версию и параметры настроек проекта.');
        $projectId = $settingsMatch[1];
        $project = $store->owned($projectId, $user);
        $settings = $project['settings'];
        $document = $project['document'];
        if (array_key_exists('designTokens', $body)) {
            $tokens = Juzimder\ProjectSettings::validateDesignTokens($body['designTokens'], $project['settings']['designTokens']);
            $settings['designTokens'] = $tokens;
            $document = Juzimder\ProjectSettings::applyDesignTokensToDocument($document, $tokens);
        }
        if (array_key_exists('page', $body)) {
            $page = $body['page'];
            if ($page instanceof stdClass) $page = get_object_vars($page);
            if (!is_array($page) || array_diff(array_keys($page), ['pageId','name','slug']) || !is_string($page['pageId'] ?? null) || !isset($settings['pages'][$page['pageId']]) || !is_string($page['name'] ?? null) || !is_string($page['slug'] ?? null)) throw new Problem(422,'Укажите страницу, название и адрес.');
            $name = trim($page['name']);
            if ($name === '' || mb_strlen($name,'UTF-8') > 100 || preg_match('/[\x00-\x1F\x7F]/', $name)) throw new Problem(422,'Название страницы: от 1 до 100 символов.');
            $slug = Juzimder\ProjectSettings::validateSlug($page['slug']);
            foreach ($settings['pages'] as $otherId=>$route) if ($otherId !== $page['pageId'] && $route['slug'] === $slug) throw new Problem(422,'Такой URL уже используется в проекте.');
            foreach ($document->data->pages as $item) if ((string)$item->id === $page['pageId']) $item->name = $name;
            $settings['pages'][$page['pageId']]['slug'] = $slug;
        }
        if (array_key_exists('seo', $body)) {
            $seoInput = $body['seo'];
            if ($seoInput instanceof stdClass) $seoInput = get_object_vars($seoInput);
            if (!is_array($seoInput) || array_diff(array_keys($seoInput), ['pageId','title','description','robots','socialImage','socialTitle','socialDescription']) || !is_string($seoInput['pageId'] ?? null) || $seoInput['pageId'] === '') throw new Problem(422,'Укажите страницу и разрешённые SEO-поля.');
            $pageId = $seoInput['pageId'];
            unset($seoInput['pageId']);
            if (!array_key_exists($pageId, $settings['pages'])) throw new Problem(422,'Указанная страница не принадлежит проекту.');
            $settings['pages'][$pageId]['seo'] = Juzimder\ProjectSettings::validateSeoMetadata($seoInput, $settings['pages'][$pageId]['seo']);
        }
        if (array_key_exists('forms', $body)) $settings['forms'] = Juzimder\ProjectSettings::validateFormsSettings($body['forms'], $settings['forms'] ?? Juzimder\ProjectSettings::formDefaults());
        result($store->save($projectId, $user, $body['version'], $document, $settings));
    }
    if (preg_match('~^/api/projects/([a-f0-9]{24})/form-mail$~D', $path, $formMailMatch)) {
        $vault = new Juzimder\FormMailVault($store);
        if ($method === 'GET') result($vault->status($formMailMatch[1], $user));
        if ($method === 'PUT') {
            if (array_diff(array_keys($body), ['version','host','port','security','username','password']) || !is_int($body['version'] ?? null) || $body['version'] < 0) throw new Problem(422,'Укажите текущую версию и параметры SMTP.');
            result($vault->save($formMailMatch[1], $user, $body['version'], array_diff_key($body, ['version'=>true])));
        }
    }
    if (preg_match('~^/api/projects/([a-f0-9]{24})/form-mail/test$~D', $path, $formMailTestMatch) && $method === 'POST') {
        if (array_diff(array_keys($body), ['version','to']) || !is_int($body['version'] ?? null) || !is_string($body['to'] ?? null) || !filter_var($body['to'], FILTER_VALIDATE_EMAIL)) throw new Problem(422,'Укажите текущую версию SMTP и email для теста.');
        $vault = new Juzimder\FormMailVault($store); $status = $vault->status($formMailTestMatch[1], $user);
        if ($status['version'] !== $body['version'] || !$status['configured']) throw new Problem(409,'Сначала сохраните настройки SMTP и обновите страницу.');
        $project = $store->owned($formMailTestMatch[1], $user); $mailSettings = $project['settings']['forms'] ?? [];
        if (!is_string($mailSettings['senderEmail'] ?? null) || !filter_var($mailSettings['senderEmail'], FILTER_VALIDATE_EMAIL)) throw new Problem(422,'Сначала сохраните корректный email отправителя в настройках писем.');
        $config = $vault->deliverySettings($formMailTestMatch[1]);
        (new Juzimder\SmtpTransport())->send($config, $mailSettings['senderEmail'], (string)($mailSettings['senderName'] ?? ''), [$body['to']], 'Тест почтового транспорта', 'SMTP-подключение настроено и может отправлять письма.');
        result(['sent'=>true]);
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
        if ($method === 'GET') result($store->query('SELECT p.id,p.slug,p.name,d.version,d.updated_at FROM projects p JOIN documents d ON p.id=d.project_id WHERE owner_id=? ORDER BY p.created_at DESC',[$user])->fetchAll());
        if ($method === 'POST') { if (!is_string($body['name'] ?? null)) throw new Problem(422,'Укажите название.'); result($store->create($user,$body['name']),201); }
    }
    if (preg_match('~^/api/projects/([a-f0-9]{24})/name$~D', $path, $renameMatch) && $method === 'PUT') {
        if (array_diff(array_keys($body), ['version','expectedName','name']) || !is_int($body['version'] ?? null) || $body['version'] < 0 || !is_string($body['expectedName'] ?? null) || !is_string($body['name'] ?? null)) throw new Problem(422, 'Укажите актуальное название и версию проекта.');
        result($store->renameProject($renameMatch[1], $user, $body['version'], $body['expectedName'], $body['name']));
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
