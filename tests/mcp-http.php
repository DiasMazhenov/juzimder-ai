<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../backend/Domain.php';
require __DIR__ . '/../backend/TrustedProxyPolicy.php';
require __DIR__ . '/../backend/Operations.php';
require __DIR__ . '/../backend/McpOAuth.php';
require __DIR__ . '/../backend/McpReadTools.php';
require __DIR__ . '/../backend/McpWriteTools.php';
require __DIR__ . '/../backend/McpSessionStore.php';

use Juzimder\CustomCode;
use Juzimder\Document;
use Juzimder\McpAuth\BearerValidator;
use Juzimder\McpAuth\McpScope;
use Juzimder\McpAuth\OAuthConfig;
use Juzimder\Store;
use League\OAuth2\Server\CryptKey;
use Symfony\Component\Uid\Uuid;

function httpCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); echo "PASS $message\n"; }
function httpRequest(string $method, string $url, array $headers = [], ?string $body = null, ?string $cookieJar = null): array
{
    $handle = curl_init($url);
    $responseHeaders = [];
    curl_setopt_array($handle, [
        CURLOPT_CUSTOMREQUEST=>$method,
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HEADERFUNCTION=>static function ($curl, string $line) use (&$responseHeaders): int {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            return strlen($line);
        },
        CURLOPT_HTTPHEADER=>$headers,
        CURLOPT_CONNECTTIMEOUT=>3,
        CURLOPT_TIMEOUT=>8,
    ]);
    if ($body !== null) curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
    if ($cookieJar !== null) { curl_setopt($handle, CURLOPT_COOKIEJAR, $cookieJar); curl_setopt($handle, CURLOPT_COOKIEFILE, $cookieJar); }
    $responseBody = curl_exec($handle);
    $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $error = curl_error($handle);
    if (!is_string($responseBody)) throw new RuntimeException('HTTP request failed: ' . ($error ?: 'no response'));
    return ['status'=>$status,'headers'=>$responseHeaders,'body'=>$responseBody];
}
function httpMcpPayload(array $response): array
{
    $raw = $response['body'];
    if (str_contains((string)($response['headers']['content-type'] ?? ''), 'text/event-stream')) {
        if (preg_match('/^data:\s*(\{.*\})\s*$/m', $raw, $match) !== 1) throw new RuntimeException('MCP response did not contain an SSE JSON message.');
        $raw = $match[1];
    }
    $decoded = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) throw new RuntimeException('MCP response is not a JSON-RPC object.');
    return $decoded;
}
function signedHttpToken(OAuthConfig $config, Store $store, string $clientId, string $jti, array $scopes, int $expiresAt, string $userId = 'owner'): string
{
    $client = $config->repositories()->getClientEntity($clientId);
    $scopeEntities = [];
    foreach ($scopes as $scope) {
        $entity = $config->repositories()->getScopeEntityByIdentifier($scope);
        if ($entity !== null) $scopeEntities[] = $entity;
    }
    $token = $config->repositories()->getNewToken($client, $scopeEntities, $userId);
    $token->setIdentifier($jti);
    $token->setExpiryDateTime((new DateTimeImmutable())->setTimestamp($expiresAt));
    $token->setPrivateKey(new CryptKey($config->privateKeyPath(), null, false));
    $config->repositories()->persistNewAccessToken($token);
    return $token->toString();
}

$root = dirname(__DIR__);
$dir = sys_get_temp_dir() . '/juzimder-mcp-http-' . bin2hex(random_bytes(6));
mkdir($dir, 0700, true);
$dbPath = $dir . '/app.sqlite';
$cookieJar = $dir . '/cookies.txt';
$serverLog = $dir . '/server.log';
$portSocket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if ($portSocket === false) throw new RuntimeException('Could not reserve a local HTTP test port.');
$port = (int)substr(strrchr((string)stream_socket_get_name($portSocket, false), ':'), 1);
fclose($portSocket);
$base = 'http://juzimder-ai.localhost:' . $port;
$endpoint = $base . '/mcp';
$resource = $endpoint;
$password = 'mcp-http-test-password-' . bin2hex(random_bytes(6));
$server = null;
putenv('JUZIMDER_PUBLIC_BASE_URL=' . $base);

try {
    $store = new Store($dbPath);
    $store->query('INSERT INTO users(id,email,password_hash,auth_version) VALUES(?,?,?,1)', ['owner','owner@example.test',password_hash($password, PASSWORD_DEFAULT)]);
    $store->query('INSERT INTO users(id,email,password_hash,auth_version) VALUES(?,?,?,1)', ['other','other@example.test',password_hash('other password', PASSWORD_DEFAULT)]);
    $project = $store->create('owner', 'Endpoint QA');
    $foreign = $store->create('other', 'Private QA');
    $doc = Document::empty();
    $doc['data']['pages'][0]['name'] = 'Home';
    $doc['data']['pages'][0]['frames'][0]['component']['components'] = [
        ['type'=>'text','tagName'=>'h1','attributes'=>['id'=>'editable-title'],'content'=>'Test page'],
        ['type'=>'custom-code','tagName'=>'div','content'=>CustomCode::encode(['html'=>'<p>not exposed</p>','css'=>'','js'=>''])],
    ];
    $doc['data']['pages'][] = ['id'=>'other-page','name'=>'Other Page','frames'=>[['component'=>['type'=>'wrapper','style'=>(object)[],'components'=>[['type'=>'text','tagName'=>'h2','attributes'=>['id'=>'other-page-title'],'content'=>'Other page']]]]]];
    $store->save($project['id'], 'owner', 0, $doc);
    $foreignDoc = Document::empty();
    $foreignDoc['data']['pages'][] = ['id'=>'foreign-page','name'=>'Foreign Page','frames'=>[['component'=>['type'=>'wrapper','style'=>(object)[],'components'=>[]]]]];
    $store->save($foreign['id'], 'other', 0, $foreignDoc);
    $revisionBefore = (int)$store->query('SELECT version FROM documents WHERE project_id=?', [$project['id']])->fetchColumn();
    unset($store);

    $env = getenv();
    $env['JUZIMDER_DB'] = $dbPath;
    $env['JUZIMDER_PUBLIC_BASE_URL'] = $base;
    $server = proc_open([PHP_BINARY,'-S','127.0.0.1:' . $port,'-t',$root . '/public',$root . '/public/router.php'], [0=>['pipe','r'],1=>['file',$serverLog,'a'],2=>['file',$serverLog,'a']], $pipes, $root, $env);
    if (!is_resource($server)) throw new RuntimeException('Could not start the isolated MCP HTTP server.');
    fclose($pipes[0]);
    $ready = false;
    for ($i=0; $i<60; $i++) {
        try { $probe = httpRequest('GET', $base . '/.well-known/oauth-authorization-server', ['Host: juzimder-ai.localhost:' . $port]); if ($probe['status'] === 200) { $ready = true; break; } }
        catch (Throwable) {}
        usleep(50000);
    }
    if (!$ready) throw new RuntimeException('Isolated MCP HTTP server did not become ready.');
    $hostHeader = 'Host: juzimder-ai.localhost:' . $port;
    $metadataResponse = httpRequest('GET', $base . '/.well-known/oauth-authorization-server', [$hostHeader]);
    $metadata = json_decode($metadataResponse['body'], true, 32, JSON_THROW_ON_ERROR);
    httpCheck($metadataResponse['status'] === 200 && ($metadata['authorization_response_iss_parameter_supported'] ?? false) === true && ($metadata['grant_types_supported'] ?? []) === ['authorization_code','refresh_token'], 'Authorization Server metadata advertises PKCE authorization and refresh grants');
    $resourceMetadata = httpRequest('GET', $base . '/.well-known/oauth-protected-resource/mcp', [$hostHeader]);
    httpCheck($resourceMetadata['status'] === 200 && json_decode($resourceMetadata['body'], true)['resource'] === $resource, 'Protected Resource Metadata points to the canonical MCP resource');

    $noToken = httpRequest('POST', $endpoint, [$hostHeader,'Content-Type: application/json'], '{}');
    httpCheck($noToken['status'] === 401 && str_contains($noToken['headers']['www-authenticate'] ?? '', 'resource_metadata='), 'Missing Bearer token receives OAuth challenge and discovery URL');
    $registrationResponse = httpRequest('POST', $base . '/oauth/register', [$hostHeader,'Content-Type: application/json'], json_encode([
        'client_name'=>'MCP HTTP QA','redirect_uris'=>['http://127.0.0.1:' . ($port + 1) . '/callback'],
        'grant_types'=>['authorization_code','refresh_token'],'response_types'=>['code'],'token_endpoint_auth_method'=>'none','scope'=>implode(' ',OAuthConfig::allowedScopes()),'application_type'=>'native',
    ], JSON_THROW_ON_ERROR));
    $registration = json_decode($registrationResponse['body'], true, 32, JSON_THROW_ON_ERROR);
    httpCheck($registrationResponse['status'] === 201 && isset($registration['client_id']) && $registration['grant_types'] === ['authorization_code','refresh_token'], 'Dynamic Client Registration accepts Codex public-client grants including refresh tokens');
    $invalidApplicationType = httpRequest('POST', $base . '/oauth/register', [$hostHeader,'Content-Type: application/json'], json_encode([
        'client_name'=>'Invalid MCP client','redirect_uris'=>['http://127.0.0.1:' . ($port + 1) . '/callback'],'grant_types'=>['authorization_code'],'response_types'=>['code'],'token_endpoint_auth_method'=>'none','application_type'=>'desktop',
    ], JSON_THROW_ON_ERROR));
    httpCheck($invalidApplicationType['status'] === 400 && json_decode($invalidApplicationType['body'], true)['error'] === 'invalid_client_metadata', 'Dynamic Client Registration rejects unsupported application_type metadata');

    $verifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    $state = bin2hex(random_bytes(20));
    $redirect = $registration['redirect_uris'][0];
    $requestedScopes = implode(' ', OAuthConfig::allowedScopes());
    $query = http_build_query(['response_type'=>'code','client_id'=>$registration['client_id'],'redirect_uri'=>$redirect,'scope'=>$requestedScopes,'state'=>$state,'resource'=>$resource,'code_challenge'=>$challenge,'code_challenge_method'=>'S256'], '', '&', PHP_QUERY_RFC3986);
    $authorizationUrl = $base . '/oauth/authorize?' . $query;
    $login = httpRequest('GET', $authorizationUrl, [$hostHeader,'Accept: text/html'], null, $cookieJar);
    preg_match('/name="csrf" value="([^"]+)"/', $login['body'], $csrfMatch);
    httpCheck($login['status'] === 200 && isset($csrfMatch[1]), 'OAuth authorization requires the Juzimder account session');
    $loginResponse = httpRequest('POST', $authorizationUrl, [$hostHeader,'Content-Type: application/x-www-form-urlencoded','Accept: text/html'], http_build_query([
        'csrf'=>$csrfMatch[1],'action'=>'login','email'=>'owner@example.test','password'=>$password,
    ], '', '&', PHP_QUERY_RFC3986), $cookieJar);
    httpCheck($loginResponse['status'] === 303, 'OAuth account login resumes the pending authorization request');
    $consent = httpRequest('GET', $authorizationUrl, [$hostHeader,'Accept: text/html'], null, $cookieJar);
    preg_match('/name="csrf" value="([^"]+)"/', $consent['body'], $consentCsrf);
    preg_match('/name="flow" value="([^"]+)"/', $consent['body'], $flowMatch);
    httpCheck($consent['status'] === 200 && isset($consentCsrf[1],$flowMatch[1]) && str_contains($consent['body'], 'изменение содержимого страниц') && str_contains($consent['body'], 'AI-генерация и публикация не включены'), 'Consent clearly describes bounded read/write access and excludes generation and publishing');
    $consentResponse = httpRequest('POST', $authorizationUrl, [$hostHeader,'Content-Type: application/x-www-form-urlencoded'], http_build_query([
        'csrf'=>$consentCsrf[1],'flow'=>$flowMatch[1],'consent'=>'approve',
    ], '', '&', PHP_QUERY_RFC3986), $cookieJar);
    parse_str((string)parse_url($consentResponse['headers']['location'] ?? '', PHP_URL_QUERY), $callback);
    httpCheck($consentResponse['status'] >= 300 && $consentResponse['status'] < 400 && ($callback['state'] ?? null) === $state && ($callback['iss'] ?? null) === $base && is_string($callback['code'] ?? null), 'Authorization Code callback includes state and RFC 9207 issuer');
    $tokenResponse = httpRequest('POST', $base . '/oauth/token', [$hostHeader,'Content-Type: application/x-www-form-urlencoded'], http_build_query([
        'grant_type'=>'authorization_code','client_id'=>$registration['client_id'],'redirect_uri'=>$redirect,'code'=>$callback['code'],'code_verifier'=>$verifier,'resource'=>$resource,
    ], '', '&', PHP_QUERY_RFC3986));
    $issued = json_decode($tokenResponse['body'], true, 32, JSON_THROW_ON_ERROR);
    $accessToken = $issued['access_token'] ?? '';
    $issuedPrincipal = is_string($accessToken) && $accessToken !== '' ? BearerValidator::validate($accessToken, new OAuthConfig(new Store($dbPath))) : null;
    $grantedScopes = $issuedPrincipal?->scopes ?? [];
    sort($grantedScopes);
    $expectedScopes = OAuthConfig::allowedScopes(); sort($expectedScopes);
    $refreshToken = $issued['refresh_token'] ?? '';
    httpCheck($tokenResponse['status'] === 200 && ($issued['token_type'] ?? '') === 'Bearer' && ($issued['expires_in'] ?? 0) <= OAuthConfig::ACCESS_TTL && is_string($accessToken) && $accessToken !== '' && is_string($refreshToken) && $refreshToken !== '' && $grantedScopes === $expectedScopes, 'Authorization Code + PKCE issues short-lived read/write access and refresh tokens for the MCP resource');

    $call = static function (int $id, string $method, array $params, string $token, ?string $sessionId = null) use ($endpoint,$hostHeader): array {
        $headers = [$hostHeader,'Authorization: Bearer ' . $token,'Content-Type: application/json','Accept: application/json, text/event-stream','Mcp-Protocol-Version: 2025-11-25'];
        if ($sessionId !== null) $headers[] = 'Mcp-Session-Id: ' . $sessionId;
        $response = httpRequest('POST', $endpoint, $headers, json_encode(['jsonrpc'=>'2.0','id'=>$id,'method'=>$method,'params'=>$params], JSON_THROW_ON_ERROR));
        $response['json'] = httpMcpPayload($response);
        return $response;
    };
    $initialize = $call(1,'initialize',['protocolVersion'=>'2025-11-25','capabilities'=>(object)[],'clientInfo'=>['name'=>'isolated-qa','version'=>'1.0']],$accessToken);
    $sessionId = $initialize['headers']['mcp-session-id'] ?? null;
    httpCheck($initialize['status'] === 200 && ($initialize['json']['result']['serverInfo']['name'] ?? '') === 'juzimder-ai' && is_string($sessionId), 'Streamable HTTP initialize handshake succeeds and returns a persistent MCP session');
    $tools = $call(2,'tools/list',[],$accessToken,$sessionId);
    $toolNames = array_column($tools['json']['result']['tools'] ?? [], 'name');
    sort($toolNames);
    httpCheck($tools['status'] === 200 && $toolNames === ['apply_page_operations','get_page','get_project_settings','list_pages','list_projects','update_project_settings'], 'tools/list exposes four read tools and two bounded write tools');

    $listProjects = $call(3,'tools/call',['name'=>'list_projects','arguments'=>['limit'=>20,'offset'=>0]],$accessToken,$sessionId);
    $projectResult = $listProjects['json']['result']['structuredContent'] ?? [];
    httpCheck(($projectResult['items'][0]['id'] ?? null) === $project['id'] && count($projectResult['items'] ?? []) === 1, 'list_projects returns only the authenticated user’s projects');
    $listPages = $call(4,'tools/call',['name'=>'list_pages','arguments'=>['project_id'=>$project['id'],'limit'=>20,'offset'=>0]],$accessToken,$sessionId);
    httpCheck(($listPages['json']['result']['structuredContent']['projectVersion'] ?? null) === 1, 'list_pages returns pages with project version');
    $getPage = $call(5,'tools/call',['name'=>'get_page','arguments'=>['project_id'=>$project['id'],'page_id'=>'home']],$accessToken,$sessionId);
    $pageResult = $getPage['json']['result']['structuredContent'] ?? [];
    $pageJson = json_encode($pageResult, JSON_THROW_ON_ERROR);
    httpCheck(($pageResult['projectData']['schemaVersion'] ?? null) === 1 && ($pageResult['projectVersion'] ?? null) === 1 && ($pageResult['redactedCustomCodeCount'] ?? 0) === 1 && !str_contains($pageJson,'not exposed'), 'get_page returns canonical safe projection and redacts Custom Code');
    $getSettings = $call(6,'tools/call',['name'=>'get_project_settings','arguments'=>['project_id'=>$project['id']]],$accessToken,$sessionId);
    $settings = $getSettings['json']['result']['structuredContent']['settings'] ?? [];
    httpCheck(isset($settings['designTokens'],$settings['pages']) && !isset($settings['forms'],$settings['customCss']), 'get_project_settings returns the allowlisted subset');

    $readRevision = (int)(new Store($dbPath))->query('SELECT version FROM documents WHERE project_id=?', [$project['id']])->fetchColumn();
    httpCheck($readRevision === 1, 'Read tool calls do not mutate the canonical project revision');
    $settingsWrite = $call(11,'tools/call',['name'=>'update_project_settings','arguments'=>['project_id'=>$project['id'],'expected_version'=>1,'updates'=>['notFound'=>['title'=>'No page']]]],$accessToken,$sessionId);
    $settingsWriteResult = $settingsWrite['json']['result']['structuredContent'] ?? [];
    httpCheck(($settingsWriteResult['projectVersion'] ?? null) === 2 && ($settingsWriteResult['updatedFields'] ?? []) === ['notFound'], 'OAuth-authorized settings write uses server validation and advances the expected revision');
    $pageWrite = $call(12,'tools/call',['name'=>'apply_page_operations','arguments'=>['project_id'=>$project['id'],'page_id'=>'home','expected_version'=>2,'operations'=>[['operation'=>'update_content','componentId'=>'editable-title','content'=>'Updated by MCP']]]],$accessToken,$sessionId);
    $pageWriteResult = $pageWrite['json']['result']['structuredContent'] ?? [];
    httpCheck(($pageWriteResult['projectVersion'] ?? null) === 3 && ($pageWriteResult['appliedOperationCount'] ?? null) === 1, 'OAuth-authorized page operation passes the expected-version precondition and backend validator');
    $staleWrite = $call(13,'tools/call',['name'=>'apply_page_operations','arguments'=>['project_id'=>$project['id'],'page_id'=>'home','expected_version'=>1,'operations'=>[['operation'=>'update_content','componentId'=>'editable-title','content'=>'Stale']]]],$accessToken,$sessionId);
    $crossPageWrite = $call(14,'tools/call',['name'=>'apply_page_operations','arguments'=>['project_id'=>$project['id'],'page_id'=>'home','expected_version'=>3,'operations'=>[['operation'=>'update_content','componentId'=>'other-page-title','content'=>'Cross-page']]]],$accessToken,$sessionId);
    $customCodeWrite = $call(15,'tools/call',['name'=>'apply_page_operations','arguments'=>['project_id'=>$project['id'],'page_id'=>'home','expected_version'=>3,'operations'=>[['operation'=>'create_component','parentId'=>'page:home','component'=>['type'=>'custom-code','tagName'=>'div','content'=>'run me']]]]],$accessToken,$sessionId);
    $privateSettingsWrite = $call(16,'tools/call',['name'=>'update_project_settings','arguments'=>['project_id'=>$project['id'],'expected_version'=>3,'updates'=>['forms'=>['recipients'=>['private@example.test']]]]],$accessToken,$sessionId);
    $writeDenialFlags = array_map(static fn(array $response): array => ['isError'=>$response['json']['result']['isError'] ?? false,'rpcErrorCode'=>$response['json']['error']['code'] ?? null], [$staleWrite,$crossPageWrite,$customCodeWrite,$privateSettingsWrite]);
    $isRejected = static fn(array $response): bool => ($response['json']['result']['isError'] ?? false) === true || isset($response['json']['error']);
    httpCheck($isRejected($staleWrite) && $isRejected($crossPageWrite) && $isRejected($customCodeWrite) && $isRejected($privateSettingsWrite), 'Stale, cross-page, executable-code, and private-setting writes are rejected: ' . json_encode($writeDenialFlags, JSON_THROW_ON_ERROR));
    $readOnlyToken = signedHttpToken(new OAuthConfig(new Store($dbPath)), new Store($dbPath), $registration['client_id'], 'http-read-only-write-denied', [OAuthConfig::SCOPE], time()+600);
    $readOnlyWrite = $call(17,'tools/call',['name'=>'update_project_settings','arguments'=>['project_id'=>$project['id'],'expected_version'=>3,'updates'=>['notFound'=>['title'=>'Forbidden']]]],$readOnlyToken,$sessionId);
    httpCheck(($readOnlyWrite['json']['result']['isError'] ?? false) === true, 'A token without projects:write cannot invoke write tools');
    $storeAfterWrites = new Store($dbPath);
    $writeRevision = (int)$storeAfterWrites->query('SELECT version FROM documents WHERE project_id=?', [$project['id']])->fetchColumn();
    $writtenDocument = $storeAfterWrites->owned($project['id'], 'owner')['document'];
    httpCheck($writeRevision === 3 && ($writtenDocument->data->pages[0]->frames[0]->component->components[0]->content ?? '') === 'Updated by MCP', 'Successful writes persist once; all rejected writes leave the revision unchanged');

    $toolPayloads = json_encode([$projectResult,$listPages['json']['result']['structuredContent'] ?? [],$pageResult,$settings], JSON_THROW_ON_ERROR);
    $sensitiveMarkers = ['owner@example.test','password_hash','auth_version','csrf','mcp_oauth','cookie','access_token','refresh_token'];
    $containsSensitiveMarker = false;
    foreach ($sensitiveMarkers as $marker) if (stripos($toolPayloads,$marker) !== false) $containsSensitiveMarker = true;
    httpCheck(!$containsSensitiveMarker, 'Tool results contain no account, session, OAuth, or credential fields');

    $foreignProjectCall = $call(7,'tools/call',['name'=>'list_pages','arguments'=>['project_id'=>$foreign['id']]],$accessToken,$sessionId);
    $crossPageCall = $call(8,'tools/call',['name'=>'get_page','arguments'=>['project_id'=>$project['id'],'page_id'=>'foreign-page']],$accessToken,$sessionId);
    httpCheck(($foreignProjectCall['json']['result']['isError'] ?? false) && ($crossPageCall['json']['result']['isError'] ?? false), 'Foreign project and cross-project page calls are denied by tool handlers');
    $otherUserToken = signedHttpToken(new OAuthConfig(new Store($dbPath)), new Store($dbPath), $registration['client_id'], 'http-other-user', [OAuthConfig::SCOPE], time()+600, 'other');
    $otherUserCall = $call(9,'tools/call',['name'=>'list_pages','arguments'=>['project_id'=>$project['id']]],$otherUserToken,$sessionId);
    httpCheck(($otherUserCall['json']['result']['isError'] ?? false), 'A valid token for another account cannot read the owner’s project');

    $noScopeToken = signedHttpToken(new OAuthConfig(new Store($dbPath)), new Store($dbPath), $registration['client_id'], 'http-insufficient-scope', [], time()+600);
    $noScopeResponse = httpRequest('POST',$endpoint,[$hostHeader,'Authorization: Bearer ' . $noScopeToken,'Content-Type: application/json'],json_encode(['jsonrpc'=>'2.0','id'=>10,'method'=>'tools/list','params'=>[]],JSON_THROW_ON_ERROR));
    httpCheck($noScopeResponse['status'] === 403 && (json_decode($noScopeResponse['body'],true)['error'] ?? '') === 'insufficient_scope', 'Valid token without projects:read receives HTTP 403');
    $wrongAudience = \Firebase\JWT\JWT::encode(['iss'=>$base,'aud'=>'https://wrong.example/mcp','sub'=>'owner','client_id'=>$registration['client_id'],'jti'=>'http-wrong-audience','iat'=>time(),'nbf'=>time(),'exp'=>time()+600,'scope'=>OAuthConfig::SCOPE,'scopes'=>[OAuthConfig::SCOPE]], file_get_contents((new OAuthConfig(new Store($dbPath)))->privateKeyPath()), 'RS256');
    $wrongAudienceResponse = httpRequest('POST',$endpoint,[$hostHeader,'Authorization: Bearer ' . $wrongAudience,'Content-Type: application/json'],'{}');
    httpCheck($wrongAudienceResponse['status'] === 401, 'Wrong audience/resource token receives HTTP 401');
    $wrongIssuer = \Firebase\JWT\JWT::encode(['iss'=>'https://wrong.example','aud'=>[$resource],'sub'=>'owner','client_id'=>$registration['client_id'],'jti'=>'http-wrong-issuer','iat'=>time(),'nbf'=>time(),'exp'=>time()+600,'scope'=>OAuthConfig::SCOPE,'scopes'=>[OAuthConfig::SCOPE]], file_get_contents((new OAuthConfig(new Store($dbPath)))->privateKeyPath()), 'RS256');
    $wrongIssuerResponse = httpRequest('POST',$endpoint,[$hostHeader,'Authorization: Bearer ' . $wrongIssuer,'Content-Type: application/json'],'{}');
    httpCheck($wrongIssuerResponse['status'] === 401, 'Wrong issuer token receives HTTP 401');
    $expired = \Firebase\JWT\JWT::encode(['iss'=>$base,'aud'=>[$resource],'sub'=>'owner','client_id'=>$registration['client_id'],'jti'=>'http-expired','iat'=>time()-1200,'nbf'=>time()-1200,'exp'=>time()-600,'scope'=>OAuthConfig::SCOPE,'scopes'=>[OAuthConfig::SCOPE]], file_get_contents((new OAuthConfig(new Store($dbPath)))->privateKeyPath()), 'RS256');
    $expiredResponse = httpRequest('POST',$endpoint,[$hostHeader,'Authorization: Bearer ' . $expired,'Content-Type: application/json'],'{}');
    httpCheck($expiredResponse['status'] === 401, 'Expired token receives HTTP 401');
    $wrongResourceRefresh = httpRequest('POST',$base . '/oauth/token',[$hostHeader,'Content-Type: application/x-www-form-urlencoded'],http_build_query(['grant_type'=>'refresh_token','client_id'=>$registration['client_id'],'refresh_token'=>$refreshToken,'resource'=>'https://wrong.example/mcp'], '', '&', PHP_QUERY_RFC3986));
    httpCheck($wrongResourceRefresh['status'] === 400, 'Refresh token exchange rejects a different resource audience');
    $issuedClaims = (array)\Firebase\JWT\JWT::decode($accessToken, new \Firebase\JWT\Key((string)file_get_contents((new OAuthConfig(new Store($dbPath)))->publicKeyPath()), 'RS256'));
    (new Store($dbPath))->query('UPDATE mcp_oauth_access_tokens SET expires_at=? WHERE jti=?', [time()-1, $issuedClaims['jti']]);
    $expiredAccessResponse = httpRequest('POST',$endpoint,[$hostHeader,'Authorization: Bearer ' . $accessToken,'Content-Type: application/json'],'{}');
    httpCheck($expiredAccessResponse['status'] === 401, 'Access token expiry does not extend when a refresh token exists');
    $refreshResponse = httpRequest('POST',$base . '/oauth/token',[$hostHeader,'Content-Type: application/x-www-form-urlencoded'],http_build_query(['grant_type'=>'refresh_token','client_id'=>$registration['client_id'],'refresh_token'=>$refreshToken,'resource'=>$resource], '', '&', PHP_QUERY_RFC3986));
    $refreshed = json_decode($refreshResponse['body'], true, 32, JSON_THROW_ON_ERROR);
    $rotatedAccessToken = $refreshed['access_token'] ?? '';
    $rotatedRefreshToken = $refreshed['refresh_token'] ?? '';
    $rotatedPrincipal = is_string($rotatedAccessToken) && $rotatedAccessToken !== '' ? BearerValidator::validate($rotatedAccessToken, new OAuthConfig(new Store($dbPath))) : null;
    httpCheck($refreshResponse['status'] === 200 && is_string($rotatedRefreshToken) && $rotatedRefreshToken !== '' && $rotatedPrincipal?->scopes === $expectedScopes, 'Refresh grant remains usable after the linked access token expires, preserves approved scopes, and rotates both tokens');
    $oldAccessResponse = httpRequest('POST',$endpoint,[$hostHeader,'Authorization: Bearer ' . $accessToken,'Content-Type: application/json'],'{}');
    $reuseRefreshResponse = httpRequest('POST',$base . '/oauth/token',[$hostHeader,'Content-Type: application/x-www-form-urlencoded'],http_build_query(['grant_type'=>'refresh_token','client_id'=>$registration['client_id'],'refresh_token'=>$refreshToken,'resource'=>$resource], '', '&', PHP_QUERY_RFC3986));
    httpCheck($oldAccessResponse['status'] === 401 && $reuseRefreshResponse['status'] === 400, 'Rotated access and refresh tokens are rejected on reuse');
    $revokeResponse = httpRequest('POST',$base . '/oauth/revoke',[$hostHeader,'Content-Type: application/x-www-form-urlencoded'],http_build_query(['token'=>$rotatedRefreshToken,'token_type_hint'=>'refresh_token','client_id'=>$registration['client_id']], '', '&', PHP_QUERY_RFC3986));
    $revokedResponse = httpRequest('POST',$endpoint,[$hostHeader,'Authorization: Bearer ' . $rotatedAccessToken,'Content-Type: application/json'],'{}');
    httpCheck($revokeResponse['status'] === 200 && $revokedResponse['status'] === 401, 'Revoking a refresh token invalidates its linked access token');
    $revokeAccessToken = signedHttpToken(new OAuthConfig(new Store($dbPath)), new Store($dbPath), $registration['client_id'], 'http-revoke-access', [OAuthConfig::SCOPE], time()+600);
    $revokeAccessResponse = httpRequest('POST',$base . '/oauth/revoke',[$hostHeader,'Content-Type: application/x-www-form-urlencoded'],http_build_query(['token'=>$revokeAccessToken,'token_type_hint'=>'access_token','client_id'=>$registration['client_id']], '', '&', PHP_QUERY_RFC3986));
    $revokedAccessResponse = httpRequest('POST',$endpoint,[$hostHeader,'Authorization: Bearer ' . $revokeAccessToken,'Content-Type: application/json'],'{}');
    httpCheck($revokeAccessResponse['status'] === 200 && $revokedAccessResponse['status'] === 401, 'OAuth access-token revocation invalidates an issued token on the next request');
    $storeAfter = new Store($dbPath);
    $revisionAfter = (int)$storeAfter->query('SELECT version FROM documents WHERE project_id=?', [$project['id']])->fetchColumn();
    httpCheck($revisionAfter === $revisionBefore + 2, 'Only the two explicitly authorized write calls create project revisions');
    $logs = is_file($serverLog) ? (string)file_get_contents($serverLog) : '';
    httpCheck(!str_contains($logs,$password) && !str_contains($logs,$accessToken) && !str_contains($logs,$refreshToken) && !str_contains($logs,$rotatedAccessToken) && !str_contains($logs,$rotatedRefreshToken) && !str_contains($logs,$wrongAudience), 'HTTP logs do not contain test passwords or issued/revoked bearer tokens');
    unset($storeAfter);
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    $remove = static function (string $path) use (&$remove): void {
        if (is_dir($path)) { foreach (array_diff(scandir($path) ?: [], ['.','..']) as $child) $remove($path . '/' . $child); @rmdir($path); }
        elseif (is_file($path)) @unlink($path);
    };
    $remove($dir);
    putenv('JUZIMDER_PUBLIC_BASE_URL');
}
