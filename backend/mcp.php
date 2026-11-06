<?php
declare(strict_types=1);

$mcpAutoload = is_file(__DIR__ . '/vendor/autoload.php')
    ? __DIR__ . '/vendor/autoload.php'
    : __DIR__ . '/../vendor/autoload.php';
require_once $mcpAutoload;
require_once __DIR__ . '/Domain.php';
require_once __DIR__ . '/SessionPolicy.php';
require_once __DIR__ . '/TrustedProxyPolicy.php';
require_once __DIR__ . '/Operations.php';
require_once __DIR__ . '/McpOAuth.php';
require_once __DIR__ . '/McpPersonalTokens.php';
require_once __DIR__ . '/McpReadTools.php';
require_once __DIR__ . '/McpWriteTools.php';
require_once __DIR__ . '/McpSessionStore.php';

use Juzimder\McpAuth\BearerValidator;
use Juzimder\McpAuth\McpReadTools;
use Juzimder\McpAuth\McpWriteTools;
use Juzimder\McpAuth\OAuthConfig;
use Juzimder\McpAuth\RedirectUriPolicy;
use Juzimder\McpAuth\McpUser;
use Juzimder\McpAuth\McpSessionStore;
use Juzimder\Store;
use League\OAuth2\Server\Exception\OAuthServerException;
use Mcp\Server\Transport\Http\Middleware\CorsMiddleware;
use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

$mcpDatabasePath = getenv('JUZIMDER_DB') ?: __DIR__ . '/../var/app.sqlite';
try {
    $mcpStore = new Store($mcpDatabasePath);
    $mcpConfig = new OAuthConfig($mcpStore);
    $mcpPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $mcpMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (str_starts_with($mcpPath, '/.well-known/')) {
        mcpWellKnown($mcpPath, $mcpMethod, $mcpConfig);
    } elseif (str_starts_with($mcpPath, '/oauth/')) {
        mcpOAuthRoute($mcpPath, $mcpMethod, $mcpStore, $mcpConfig);
    } elseif ($mcpPath === '/mcp') {
        mcpEndpoint($mcpMethod, $mcpStore, $mcpConfig);
    } else {
        mcpJson(['error' => 'Not found.'], 404);
    }
} catch (Throwable $error) {
    $trace = array_slice($error->getTrace(), 0, 4);
    $frames = array_map(static fn(array $frame): string => basename((string)($frame['file'] ?? 'internal')) . ':' . (int)($frame['line'] ?? 0) . ' ' . (string)($frame['function'] ?? ''), $trace);
    error_log('MCP request failed: ' . get_class($error) . ' at ' . basename($error->getFile()) . ':' . $error->getLine() . ' [' . implode(' <- ', $frames) . ']');
    if (!headers_sent()) {
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(503);
    }
    echo '{"error":"MCP service unavailable."}';
}

function mcpWellKnown(string $path, string $method, OAuthConfig $config): never
{
    if ($method !== 'GET' && $method !== 'HEAD') mcpJson(['error'=>'Method not allowed.'], 405, ['Allow'=>'GET, HEAD']);
    if ($path === '/.well-known/oauth-authorization-server') {
        mcpJson($config->metadata(), 200, [], $method === 'HEAD');
    }
    if (in_array($path, ['/.well-known/oauth-protected-resource', '/.well-known/oauth-protected-resource/mcp'], true)) {
        mcpJson($config->resourceMetadata(), 200, [], $method === 'HEAD');
    }
    mcpJson(['error'=>'Not found.'], 404);
}

function mcpOAuthRoute(string $path, string $method, Store $store, OAuthConfig $config): never
{
    $psr17 = new Psr17Factory();
    $creator = new ServerRequestCreator($psr17, $psr17, $psr17, $psr17);
    if ($path === '/oauth/register' && $method === 'POST') {
        $rejectMetadata = static function (string $reason): never { mcpJson(['error'=>'invalid_client_metadata'], 400); };
        $ip = Juzimder\TrustedProxyPolicy::clientIp($_SERVER);
        if ($ip !== null) $store->consumeAuthAttemptAllowance($ip);
        $raw = file_get_contents('php://input', false, null, 0, 16385);
        if (!is_string($raw) || strlen($raw) > 16384 || !str_starts_with(strtolower((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) $rejectMetadata('invalid-request-body');
        try { $body = json_decode($raw, true, 16, JSON_THROW_ON_ERROR); } catch (Throwable) { $rejectMetadata('invalid-json'); }
        $allowedKeys = ['client_name','redirect_uris','grant_types','response_types','token_endpoint_auth_method','scope','client_uri','logo_uri','contacts','software_id','software_version','application_type'];
        if (!is_array($body) || array_diff(array_keys($body), $allowedKeys)) $rejectMetadata('unsupported-fields');
        $name = $body['client_name'] ?? 'MCP client';
        $redirectUris = $body['redirect_uris'] ?? null;
        if (!is_string($name) || trim($name) === '' || mb_strlen($name, 'UTF-8') > 80 || !is_array($redirectUris) || !array_is_list($redirectUris) || count($redirectUris) < 1 || count($redirectUris) > 10) $rejectMetadata('invalid-client-name-or-redirect-list');
        foreach ($redirectUris as $uri) if (!is_string($uri) || !RedirectUriPolicy::valid($uri)) mcpJson(['error'=>'invalid_redirect_uri'], 400);
        if (count(array_unique($redirectUris)) !== count($redirectUris)) mcpJson(['error'=>'invalid_redirect_uri'], 400);
        $grantTypes = $body['grant_types'] ?? ['authorization_code'];
        if (!is_array($grantTypes) || !array_is_list($grantTypes) || !$grantTypes
            || count($grantTypes) > 2) $rejectMetadata('unsupported-grant-types');
        foreach ($grantTypes as $grantType) if (!is_string($grantType) || !in_array($grantType, ['authorization_code', 'refresh_token'], true)) $rejectMetadata('unsupported-grant-types');
        if (count($grantTypes) !== count(array_unique($grantTypes)) || !in_array('authorization_code', $grantTypes, true)) $rejectMetadata('unsupported-grant-types');
        if (isset($body['response_types']) && $body['response_types'] !== ['code']) $rejectMetadata('unsupported-response-types');
        if (isset($body['token_endpoint_auth_method']) && $body['token_endpoint_auth_method'] !== 'none') $rejectMetadata('unsupported-token-auth-method');
        if (isset($body['application_type']) && (!is_string($body['application_type']) || !in_array($body['application_type'], ['web','native'], true))) $rejectMetadata('unsupported-application-type');
        if (array_key_exists('scope', $body) && !is_string($body['scope'])) $rejectMetadata('invalid-scope-type');
        $scopes = OAuthConfig::normalizeScopes(isset($body['scope']) ? $body['scope'] : null);
        if ($scopes === null) $rejectMetadata('unsupported-scopes');
        foreach (['client_uri','logo_uri'] as $urlField) if (isset($body[$urlField]) && (!is_string($body[$urlField]) || strlen($body[$urlField]) > 2048 || parse_url($body[$urlField], PHP_URL_SCHEME) !== 'https')) $rejectMetadata('invalid-client-or-logo-uri');
        $result = $config->repositories()->registerClient(trim($name), $redirectUris, $scopes, $grantTypes);
        mcpJson($result, 201, ['Cache-Control'=>'no-store']);
    }

    if ($path === '/oauth/authorize' && ($method === 'GET' || $method === 'POST')) {
        mcpStartBrowserSession($store);
        $request = $creator->fromGlobals();
        $sessionUser = mcpCurrentBrowserUser($store);
        if ($method === 'POST') {
            $post = $request->getParsedBody();
            if (!is_array($post) || !is_string($post['csrf'] ?? null) || !hash_equals((string)($_SESSION['mcp_oauth_csrf'] ?? ''), $post['csrf'])) mcpHtml('Недействительный запрос. Закройте окно и запустите подключение ещё раз.', 403);
            if (($post['action'] ?? '') === 'login') {
                if ($sessionUser !== null) mcpHtml('Сессия уже активна. Обновите запрос авторизации.', 400);
                $email = strtolower(trim(is_string($post['email'] ?? null) ? $post['email'] : ''));
                $password = is_string($post['password'] ?? null) ? $post['password'] : '';
                $ip = Juzimder\TrustedProxyPolicy::clientIp($_SERVER);
                if ($ip !== null) $store->consumeAuthAttemptAllowance($ip);
                $account = filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($password) <= 72
                    ? $store->query('SELECT id,email,password_hash,auth_version FROM users WHERE email=?', [$email])->fetch()
                    : false;
                if (!$account || !password_verify($password, (string)$account['password_hash'])) mcpHtml('Не удалось войти. Проверьте email и пароль.', 401);
                session_regenerate_id(true);
                $_SESSION = ['user'=>['id'=>$account['id'],'email'=>$account['email']], 'auth_version'=>(int)$account['auth_version'], 'created_at'=>time(), 'last_activity_at'=>time(), 'csrf'=>bin2hex(random_bytes(32)), 'mcp_oauth_csrf'=>bin2hex(random_bytes(32))];
                $query = $request->getQueryParams();
                mcpRedirect('/oauth/authorize?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
            }

            $flowId = $post['flow'] ?? null;
            $pending = is_string($flowId) ? ($_SESSION['mcp_oauth_pending'][$flowId] ?? null) : null;
            $query = $request->getQueryParams();
            $queryHash = hash('sha256', json_encode($query, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            if (!is_array($pending) || (int)($pending['created_at'] ?? 0) < time()-600 || !hash_equals((string)($pending['query_hash'] ?? ''), $queryHash)) mcpHtml('Запрос авторизации устарел. Запустите подключение ещё раз.', 400);
            unset($_SESSION['mcp_oauth_pending'][$flowId]);
            if ($sessionUser === null) mcpHtml('Сессия завершилась. Запустите подключение ещё раз.', 401);
            $authorization = mcpValidateAuthorizationRequest($request, $config);
            $authorization->setUser(new McpUser($sessionUser['id']));
            $authorization->setAuthorizationApproved(($post['consent'] ?? '') === 'approve');
            try {
                $response = $config->server()->completeAuthorizationRequest($authorization, $psr17->createResponse());
                mcpSendAuthorizationResponse($response, $config->issuer());
            } catch (OAuthServerException $error) {
                $response = $error->generateHttpResponse($psr17->createResponse());
                mcpSendAuthorizationResponse($response, $config->issuer());
            }
        }

        try { $authorization = mcpValidateAuthorizationRequest($request, $config); }
        catch (OAuthServerException $error) { mcpSendAuthorizationResponse($error->generateHttpResponse($psr17->createResponse()), $config->issuer()); }
        $query = $request->getQueryParams();
        if ($sessionUser === null) {
            $action = '/oauth/authorize?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
            $csrf = htmlspecialchars((string)$_SESSION['mcp_oauth_csrf'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            mcpHtml('<h1>Вход в Juzimder AI</h1><p>Войдите, чтобы подключить MCP-клиент к своему аккаунту.</p><form method="post" action="'.htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'">'.mcpHidden('csrf',$csrf).mcpHidden('action','login').'<label>Email<input name="email" type="email" autocomplete="username" required maxlength="254"></label><label>Пароль<input name="password" type="password" autocomplete="current-password" required maxlength="72"></label><button type="submit">Войти и продолжить</button></form>');
        }
        $flowId = bin2hex(random_bytes(16));
        $_SESSION['mcp_oauth_pending'][$flowId] = ['query_hash'=>hash('sha256', json_encode($query, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)), 'created_at'=>time()];
        foreach ($_SESSION['mcp_oauth_pending'] as $id=>$flow) if ((int)($flow['created_at'] ?? 0) < time()-600) unset($_SESSION['mcp_oauth_pending'][$id]);
        $action = '/oauth/authorize?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $clientName = htmlspecialchars((string)$authorization->getClient()->getName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $csrf = htmlspecialchars((string)$_SESSION['mcp_oauth_csrf'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $scopeNames = implode(', ', array_map(static fn($scope): string => htmlspecialchars($scope->getIdentifier(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $authorization->getScopes()));
        $permissionDescription = in_array(OAuthConfig::WRITE_SCOPE, array_map(static fn($scope): string => $scope->getIdentifier(), $authorization->getScopes()), true)
            ? 'Чтение разрешённых данных и изменение содержимого страниц и безопасных настроек. AI-генерация и публикация не включены.'
            : 'Только чтение разрешённых данных проектов, страниц и настроек.';
        mcpHtml('<h1>Разрешить подключение?</h1><p><strong>'.$clientName.'</strong> запрашивает доступ к Juzimder.</p><p>'.htmlspecialchars($permissionDescription, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</p><p>Запрашиваемый доступ: '.$scopeNames.'</p><form method="post" action="'.htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'">'.mcpHidden('csrf',$csrf).mcpHidden('flow',$flowId).'<button name="consent" value="approve" type="submit">Разрешить</button><button name="consent" value="deny" type="submit">Отказать</button></form>');
    }

    if ($path === '/oauth/token' && $method === 'POST') {
        if (!str_starts_with(strtolower((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/x-www-form-urlencoded')) mcpJson(['error'=>'invalid_request'], 400);
        $request = $creator->fromGlobals();
        $body = $request->getParsedBody();
        if (!is_array($body) || isset($body['client_secret']) || ($body['resource'] ?? null) !== $config->resource()) mcpJson(['error'=>'invalid_target'], 400);
        try { $response = $config->server()->respondToAccessTokenRequest($request, $psr17->createResponse()); }
        catch (OAuthServerException $error) { mcpSendPsrResponse($error->generateHttpResponse($psr17->createResponse())); }
        mcpSendPsrResponse($response);
    }

    if ($path === '/oauth/revoke' && $method === 'POST') {
        if (!str_starts_with(strtolower((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/x-www-form-urlencoded')) mcpJson(['error'=>'invalid_request'], 400);
        $request = $creator->fromGlobals();
        $body = $request->getParsedBody();
        $token = is_array($body) && is_string($body['token'] ?? null) ? $body['token'] : '';
        $clientId = is_array($body) && is_string($body['client_id'] ?? null) ? $body['client_id'] : '';
        $hint = is_array($body) && is_string($body['token_type_hint'] ?? null) ? $body['token_type_hint'] : '';
        if ($token !== '' && strlen($token) <= 8192 && $clientId !== '') {
            if ($hint !== 'refresh_token') {
                $principal = BearerValidator::validate($token, $config);
                if ($principal !== null && hash_equals($principal->clientId, $clientId)) {
                    $claims = (array)\Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key((string)file_get_contents($config->publicKeyPath()), 'RS256'));
                    $config->repositories()->revokeAccessToken((string)$claims['jti']);
                }
            }
            if ($hint !== 'access_token') {
                $payload = $config->decodeRefreshToken($token);
                if ($payload !== null && hash_equals($payload['client_id'], $clientId)) {
                    $stored = $config->repositories()->refreshToken($payload['refresh_token_id']);
                    $storedScopes = $stored ? json_decode((string)$stored['scopes'], true) : null;
                    $payloadScopes = array_values(array_filter($payload['scopes'], 'is_string'));
                    if ($stored !== null && hash_equals((string)$stored['client_id'], $payload['client_id'])
                        && hash_equals((string)$stored['user_id'], $payload['user_id'])
                        && hash_equals((string)$stored['access_token_jti'], $payload['access_token_id'])
                        && is_array($storedScopes) && $storedScopes === $payloadScopes) {
                        $config->repositories()->revokeRefreshToken($payload['refresh_token_id']);
                    }
                }
            }
        }
        mcpJson(new stdClass(), 200);
    }

    mcpJson(['error'=>'Method not allowed.'], 405, ['Allow'=>$path === '/oauth/authorize' ? 'GET, POST' : 'POST']);
}

function mcpValidateAuthorizationRequest(Psr\Http\Message\ServerRequestInterface $request, OAuthConfig $config): \League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface
{
    $query = $request->getQueryParams();
    if (($query['resource'] ?? null) !== $config->resource() || ($query['response_type'] ?? null) !== 'code'
        || !is_string($query['state'] ?? null) || strlen($query['state']) < 1 || strlen($query['state']) > 512
        || !is_string($query['code_challenge'] ?? null) || ($query['code_challenge_method'] ?? null) !== 'S256') {
        mcpHtml('OAuth-запрос должен содержать корректные resource, state и PKCE S256 параметры.', 400);
    }
    if (array_key_exists('scope', $query) && (!is_string($query['scope']) || OAuthConfig::normalizeScopes($query['scope']) === null)) {
        mcpHtml('OAuth-запрос содержит неподдерживаемый набор MCP-разрешений.', 400);
    }
    return $config->server()->validateAuthorizationRequest($request);
}

function mcpEndpoint(string $method, Store $store, OAuthConfig $config): never
{
    if ($method === 'OPTIONS') {
        $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
        if ($origin !== '' && !hash_equals($config->issuer(), $origin)) mcpJson(['error'=>'Invalid origin.'], 403);
        http_response_code(204); header('Cache-Control: no-store');
        if ($origin !== '') {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Accept, Authorization, Content-Type, Mcp-Protocol-Version, Mcp-Session-Id');
            header('Access-Control-Expose-Headers: Mcp-Session-Id');
            header('Vary: Origin');
        }
        exit;
    }
    if (!in_array($method, ['POST','GET','DELETE'], true)) mcpJson(['error'=>'Method not allowed.'], 405, ['Allow'=>'GET, POST, DELETE, OPTIONS']);
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authorization = '';
    foreach ($headers as $name=>$value) if (is_string($name) && strtolower($name) === 'authorization' && is_string($value)) $authorization = $value;
    if ($authorization === '' && isset($_SERVER['HTTP_AUTHORIZATION']) && is_string($_SERVER['HTTP_AUTHORIZATION'])) $authorization = $_SERVER['HTTP_AUTHORIZATION'];
    if (!preg_match('/^Bearer\s+([^\s]+)$/iD', $authorization, $match)) mcpUnauthorized($config);
    $personal=(new Juzimder\McpPersonalTokens($store))->validate($match[1]);
    $principal = $personal===null ? BearerValidator::validate($match[1], $config) : new Juzimder\McpAuth\BearerPrincipal($personal['userId'],'personal:'.$personal['id'],$personal['scopes']);
    if ($principal === null) mcpUnauthorized($config, 'invalid_token');
    if (!in_array(OAuthConfig::SCOPE, $principal->scopes, true)) {
        $metadataUrl = $config->issuer() . '/.well-known/oauth-protected-resource/mcp';
        mcpJson(['error'=>'insufficient_scope'], 403, ['WWW-Authenticate'=>'Bearer error="insufficient_scope", scope="'.implode(' ', OAuthConfig::allowedScopes()).'", resource_metadata="'.$metadataUrl.'"']);
    }

    $psr17 = new Psr17Factory();
    $creator = new ServerRequestCreator($psr17, $psr17, $psr17, $psr17);
    $request = $creator->fromGlobals();
    $requestHost = strtolower((string)$request->getUri()->getHost());
    $issuerHost = strtolower((string)parse_url($config->issuer(), PHP_URL_HOST));
    if ($requestHost === '' || $requestHost !== $issuerHost) mcpJson(['error'=>'Invalid host.'], 400);

    $tools = new McpReadTools($store, $principal->userId, $personal['projectIds']??null);
    $writeTools = new McpWriteTools($store, $principal->userId, $principal->scopes, $personal['projectIds']??null);
    $version = mcpProductVersion();
    $builder = Mcp\Server::builder()->setServerInfo('juzimder-ai', $version, 'Read and write access to the authenticated user’s Juzimder AI projects.');
    $builder->addTool([$tools,'list_projects'], 'list_projects', 'List projects', 'List the authenticated user’s projects. Returns paginated project metadata.', null,
        ['type'=>'object','properties'=>['limit'=>['type'=>'integer','minimum'=>1,'maximum'=>50,'default'=>20], 'offset'=>['type'=>'integer','minimum'=>0,'maximum'=>100000,'default'=>0]],'additionalProperties'=>false], null, null,
        ['type'=>'object','properties'=>['items'=>['type'=>'array','items'=>['type'=>'object','properties'=>['id'=>['type'=>'string'],'slug'=>['type'=>'string'],'name'=>['type'=>'string'],'version'=>['type'=>'integer'],'updatedAt'=>['type'=>'string']],'required'=>['id','slug','name','version','updatedAt'],'additionalProperties'=>false]],'limit'=>['type'=>'integer'],'offset'=>['type'=>'integer'],'nextOffset'=>['type'=>['integer','null']]],'required'=>['items','limit','offset','nextOffset'],'additionalProperties'=>false]);
    $builder->addTool([$tools,'list_pages'], 'list_pages', 'List pages', 'List pages in a project the authenticated user owns. Project access is checked on every call.', null,
        ['type'=>'object','properties'=>['project_id'=>['type'=>'string','pattern'=>'^[a-f0-9]{24}$'],'limit'=>['type'=>'integer','minimum'=>1,'maximum'=>50,'default'=>20],'offset'=>['type'=>'integer','minimum'=>0,'maximum'=>1000,'default'=>0]],'required'=>['project_id'],'additionalProperties'=>false], null, null,
        ['type'=>'object','properties'=>['projectId'=>['type'=>'string'],'projectVersion'=>['type'=>'integer'],'items'=>['type'=>'array','items'=>['type'=>'object','properties'=>['id'=>['type'=>'string'],'name'=>['type'=>'string'],'slug'=>['type'=>'string'],'isHome'=>['type'=>'boolean'],'path'=>['type'=>'string']],'required'=>['id','name','slug','isHome','path'],'additionalProperties'=>false]],'limit'=>['type'=>'integer'],'offset'=>['type'=>'integer'],'nextOffset'=>['type'=>['integer','null']]],'required'=>['projectId','projectVersion','items','limit','offset','nextOffset'],'additionalProperties'=>false]);
    $builder->addTool([$tools,'get_page'], 'get_page', 'Get page', 'Read a safe projection of one page in a project the authenticated user owns. Custom Code payloads are redacted; projectData preserves the canonical schemaVersion envelope.', null,
        ['type'=>'object','properties'=>['project_id'=>['type'=>'string','pattern'=>'^[a-f0-9]{24}$'],'page_id'=>['type'=>'string','minLength'=>1,'maxLength'=>128,'pattern'=>'^[A-Za-z0-9_-]+$']],'required'=>['project_id','page_id'],'additionalProperties'=>false], null, null,
        ['type'=>'object','properties'=>['projectId'=>['type'=>'string'],'projectVersion'=>['type'=>'integer'],'pageId'=>['type'=>'string'],'pageName'=>['type'=>'string'],'redactedCustomCodeCount'=>['type'=>'integer'],'projectData'=>['type'=>'object']],'required'=>['projectId','projectVersion','pageId','pageName','redactedCustomCodeCount','projectData'],'additionalProperties'=>false]);
    $builder->addTool([$tools,'get_project_settings'], 'get_project_settings', 'Get project settings', 'Read an allowlisted subset of a project’s settings. Mail, custom code, AI credentials, and private operational settings are excluded.', null,
        ['type'=>'object','properties'=>['project_id'=>['type'=>'string','pattern'=>'^[a-f0-9]{24}$']],'required'=>['project_id'],'additionalProperties'=>false], null, null,
        ['type'=>'object','properties'=>['projectId'=>['type'=>'string'],'projectVersion'=>['type'=>'integer'],'name'=>['type'=>'string'],'slug'=>['type'=>'string'],'settings'=>['type'=>'object']],'required'=>['projectId','projectVersion','name','slug','settings'],'additionalProperties'=>false]);

    $builder->addTool([$writeTools,'apply_page_operations'], 'apply_page_operations', 'Apply page operations', 'Apply 1–20 typed, server-validated edits to components on one owned page. Requires projects:write and the current expected_version. Cross-page writes and executable Custom Code creation/duplication are rejected.', null,
        ['type'=>'object','properties'=>[
            'project_id'=>['type'=>'string','pattern'=>'^[a-f0-9]{24}$'],
            'page_id'=>['type'=>'string','minLength'=>1,'maxLength'=>128],
            'expected_version'=>['type'=>'integer','minimum'=>0],
            'operations'=>['type'=>'array','minItems'=>1,'maxItems'=>20,'items'=>mcpPageOperationSchema()],
        ],'required'=>['project_id','page_id','expected_version','operations'],'additionalProperties'=>false], null, null,
        ['type'=>'object','properties'=>['projectId'=>['type'=>'string'],'pageId'=>['type'=>'string'],'projectVersion'=>['type'=>'integer'],'appliedOperationCount'=>['type'=>'integer']],'required'=>['projectId','pageId','projectVersion','appliedOperationCount'],'additionalProperties'=>false]);
    $builder->addTool([$writeTools,'update_project_settings'], 'update_project_settings', 'Update project settings', 'Update allowlisted design tokens, page name/slug, SEO, 404 and shared-section settings. Requires projects:write and the current expected_version. Form delivery, custom CSS/code, credentials, AI generation and publishing are excluded.', null,
        ['type'=>'object','properties'=>[
            'project_id'=>['type'=>'string','pattern'=>'^[a-f0-9]{24}$'],
            'expected_version'=>['type'=>'integer','minimum'=>0],
            'updates'=>mcpSettingsUpdateSchema(),
        ],'required'=>['project_id','expected_version','updates'],'additionalProperties'=>false], null, null,
        ['type'=>'object','properties'=>['projectId'=>['type'=>'string'],'projectVersion'=>['type'=>'integer'],'updatedFields'=>['type'=>'array','items'=>['type'=>'string']]],'required'=>['projectId','projectVersion','updatedFields'],'additionalProperties'=>false]);

    $host = (string)$request->getUri()->getHost();
    $middleware = [new CorsMiddleware([$config->issuer()]), new DnsRebindingProtectionMiddleware([$host], $psr17, $psr17)];
    $transport = new StreamableHttpTransport($request, $psr17, $psr17, null, $middleware);
    $builder->setSession(new McpSessionStore($store));
    $response = $builder->build()->run($transport);
    mcpSendPsrResponse($response);
}

function mcpUnauthorized(OAuthConfig $config, string $error = 'invalid_request'): never
{
    $metadataUrl = $config->issuer() . '/.well-known/oauth-protected-resource/mcp';
    $challenge = 'Bearer resource_metadata="'.$metadataUrl.'"';
    if ($error === 'invalid_token') $challenge .= ', error="invalid_token"';
    mcpJson(['error'=>$error], 401, ['WWW-Authenticate'=>$challenge]);
}

function mcpPageOperationSchema(): array
{
    $string = ['type'=>'string'];
    $integer = ['type'=>'integer','minimum'=>0];
    $object = ['type'=>'object'];
    $definitions = [
        'create_component'=>[['parentId'=>$string,'component'=>$object,'index'=>$integer],['parentId','component']],
        'update_zero_block'=>[['pageId'=>$string,'componentId'=>$string,'zeroData'=>$object],['pageId','componentId','zeroData']],
        'update_content'=>[['componentId'=>$string,'content'=>$string],['componentId','content']],
        'rename_component'=>[['componentId'=>$string,'name'=>$string],['componentId','name']],
        'update_tag_name'=>[['componentId'=>$string,'tagName'=>$string],['componentId','tagName']],
        'update_classes'=>[['componentId'=>$string,'classes'=>['type'=>'array','items'=>$string]],['componentId','classes']],
        'update_attributes'=>[['componentId'=>$string,'attributes'=>$object],['componentId','attributes']],
        'replace_text'=>[['componentId'=>$string,'content'=>$string],['componentId','content']],
        'update_styles'=>[['componentId'=>$string,'styles'=>$object],['componentId','styles']],
        'update_layout'=>[['componentId'=>$string,'layout'=>$object],['componentId','layout']],
        'delete_component'=>[['componentId'=>$string,'confirm'=>['const'=>true]],['componentId','confirm']],
        'duplicate_component'=>[['componentId'=>$string,'index'=>$integer],['componentId']],
        'move_component'=>[['componentId'=>$string,'parentId'=>$string,'index'=>$integer],['componentId','parentId']],
        'reorder_component'=>[['componentId'=>$string,'index'=>$integer],['componentId','index']],
    ];
    $variants = [];
    foreach ($definitions as $kind=>$definition) {
        [$properties,$required] = $definition;
        $variants[] = ['type'=>'object','properties'=>array_merge(['operation'=>['const'=>$kind]],$properties),'required'=>array_merge(['operation'],$required),'additionalProperties'=>false];
    }
    return ['oneOf'=>$variants];
}

function mcpSettingsUpdateSchema(): array
{
    $tokenProperties = [];
    foreach (['ink','forest','canvas','paper','body','muted','line','accent'] as $name) $tokenProperties[$name] = ['type'=>'string','pattern'=>'^#[0-9a-fA-F]{6}$'];
    $families = ['Arial, sans-serif','system-ui, sans-serif','Georgia, serif'];
    foreach (['fontFamily','headingFontFamily','bodyFontFamily'] as $name) $tokenProperties[$name] = ['type'=>'string','enum'=>$families];
    foreach (['bodyFontWeight','headingFontWeight'] as $name) $tokenProperties[$name] = ['type'=>'string','enum'=>['300','400','500','600','700','800']];
    foreach (['baseFontSize'=>[12,24],'fontSizeH1'=>[28,80],'fontSizeH2'=>[22,64],'fontSizeH3'=>[18,48],'fontSizeH4'=>[16,40],'fontSizeH5'=>[14,32],'fontSizeH6'=>[12,28]] as $name=>$bounds) {
        $tokenProperties[$name] = ['type'=>'string','enum'=>array_map(static fn(int $size): string => $size . 'px', range($bounds[0], $bounds[1]))];
    }
    $lineHeights = ['1','1.1','1.15','1.2','1.25','1.3','1.4','1.5','1.6','1.7','1.8','1.9','2'];
    foreach (['bodyLineHeight','headingLineHeight'] as $name) $tokenProperties[$name] = ['type'=>'string','enum'=>$lineHeights];
    $tokenProperties['letterSpacing'] = ['type'=>'string','enum'=>['-2px','-1px','-0.5px','0px','0.25px','0.5px','1px','1.5px','2px']];
    $tokenProperties['radius'] = ['type'=>'string','pattern'=>'^(?:0|[1-9]|[12][0-9]|3[0-2])px$'];

    return ['type'=>'object','minProperties'=>1,'properties'=>[
        'designTokens'=>['type'=>'object','minProperties'=>1,'properties'=>$tokenProperties,'additionalProperties'=>false],
        'page'=>['type'=>'object','properties'=>['pageId'=>['type'=>'string','minLength'=>1,'maxLength'=>128],'name'=>['type'=>'string','minLength'=>1,'maxLength'=>100],'slug'=>['type'=>'string','minLength'=>1,'maxLength'=>80]],'required'=>['pageId','name','slug'],'additionalProperties'=>false],
        'seo'=>['type'=>'object','properties'=>['pageId'=>['type'=>'string','minLength'=>1,'maxLength'=>128],'title'=>['type'=>'string','maxLength'=>70],'description'=>['type'=>'string','maxLength'=>160],'robots'=>['type'=>'string','enum'=>['index','noindex']],'socialImage'=>['type'=>'string','maxLength'=>2048],'socialTitle'=>['type'=>'string','maxLength'=>70],'socialDescription'=>['type'=>'string','maxLength'=>160]],'required'=>['pageId'],'additionalProperties'=>false],
        'notFound'=>['type'=>'object','properties'=>['title'=>['type'=>'string','maxLength'=>100],'message'=>['type'=>'string','maxLength'=>400],'homeLabel'=>['type'=>'string','maxLength'=>80],'backgroundColor'=>['type'=>'string','pattern'=>'^(?:#[0-9a-fA-F]{6})?$'],'textColor'=>['type'=>'string','pattern'=>'^(?:#[0-9a-fA-F]{6})?$'],'buttonColor'=>['type'=>'string','pattern'=>'^(?:#[0-9a-fA-F]{6})?$'],'buttonTextColor'=>['type'=>'string','pattern'=>'^(?:#[0-9a-fA-F]{6})?$']],'additionalProperties'=>false],
        'sharedSections'=>['type'=>'object','properties'=>[
            'headerComponentId'=>['type'=>'string','maxLength'=>120],
            'footerComponentId'=>['type'=>'string','maxLength'=>120],
            'pages'=>['type'=>'object','additionalProperties'=>['type'=>'object','properties'=>['header'=>['type'=>'boolean'],'footer'=>['type'=>'boolean']],'additionalProperties'=>false]],
        ],'additionalProperties'=>false],
    ],'additionalProperties'=>false];
}

function mcpStartBrowserSession(Store $store): void
{
    ini_set('session.use_strict_mode', '1');
    $databasePath = getenv('JUZIMDER_DB') ?: __DIR__ . '/../var/app.sqlite';
    $identity = realpath($databasePath) ?: $databasePath;
    $default = __DIR__ . '/../var/app.sqlite';
    $defaultIdentity = realpath($default) ?: $default;
    session_name($identity === $defaultIdentity ? 'juzimder_session' : 'juzimder_' . substr(hash('sha256', $identity), 0, 16));
    session_set_cookie_params(['lifetime'=>Juzimder\SessionPolicy::ABSOLUTE_TIMEOUT_SECONDS,'httponly'=>true,'samesite'=>'Strict','secure'=>Juzimder\TrustedProxyPolicy::isHttps($_SERVER),'path'=>'/']);
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $_SESSION['mcp_oauth_csrf'] ??= bin2hex(random_bytes(32));
    $_SESSION['mcp_oauth_pending'] ??= [];
}

function mcpCurrentBrowserUser(Store $store): ?array
{
    $identity = $_SESSION['user']['id'] ?? null;
    if (!is_string($identity) || $identity === '') return null;
    $account = $store->query('SELECT auth_version FROM users WHERE id=?', [$identity])->fetch();
    $now = time();
    if (!$account || Juzimder\SessionPolicy::isExpired($_SESSION['created_at'] ?? null, $_SESSION['last_activity_at'] ?? null, $now)
        || (int)($_SESSION['auth_version'] ?? -1) !== (int)$account['auth_version']) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['mcp_oauth_csrf'] = bin2hex(random_bytes(32));
        $_SESSION['mcp_oauth_pending'] = [];
        return null;
    }
    $_SESSION['last_activity_at'] = $now;
    return ['id'=>$identity];
}

function mcpHidden(string $name, string $value): string
{
    return '<input type="hidden" name="'.htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" value="'.htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'">';
}

function mcpHtml(string $body, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff');
    echo '<!doctype html><html lang="ru"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Подключение MCP · Juzimder AI</title><style>body{font:16px system-ui,sans-serif;max-width:640px;margin:8vh auto;padding:24px;color:#f2f5f2;background:#222629}main{background:#474b4f;border:1px solid #6b6e70;border-radius:14px;padding:28px}label{display:block;margin:18px 0}input{display:block;width:100%;box-sizing:border-box;margin-top:8px;padding:12px;border-radius:8px;border:1px solid #6b6e70;background:#222629;color:#fff}button{padding:12px 18px;margin:14px 10px 0 0;border:0;border-radius:8px;background:#86c232;color:#222629;font-weight:700;cursor:pointer}button[value="deny"]{background:#222629;color:#fff;border:1px solid #6b6e70}p{line-height:1.55;color:#e1e6e1}</style><main>'.$body.'</main></html>';
    exit;
}

function mcpRedirect(string $location, int $status = 303): never
{
    http_response_code($status); header('Location: ' . $location); header('Cache-Control: no-store'); exit;
}

function mcpJson(mixed $data, int $status = 200, array $headers = [], bool $head = false): never
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff');
    foreach ($headers as $name=>$value) header($name . ': ' . $value);
    http_response_code($status);
    if (!$head) echo $json;
    exit;
}

function mcpSendPsrResponse(Psr\Http\Message\ResponseInterface $response): never
{
    http_response_code($response->getStatusCode());
    foreach ($response->getHeaders() as $name=>$values) foreach ($values as $value) header($name . ': ' . $value, false);
    echo (string)$response->getBody();
    exit;
}

function mcpSendAuthorizationResponse(Psr\Http\Message\ResponseInterface $response, string $issuer): never
{
    $location = $response->getHeaderLine('Location');
    if ($location !== '') {
        $separator = str_contains($location, '?') ? '&' : '?';
        $response = $response->withHeader('Location', $location . $separator . 'iss=' . rawurlencode($issuer));
    }
    mcpSendPsrResponse($response);
}

function mcpProductVersion(): string
{
    $package = json_decode((string)file_get_contents(__DIR__ . '/../package.json'), true);
    return is_string($package['version'] ?? null) ? $package['version'] : 'unknown';
}
