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
use Juzimder\McpAuth\McpAccessToken;
use Juzimder\McpAuth\McpReadTools;
use Juzimder\McpAuth\McpWriteTools;
use Juzimder\McpAuth\McpScope;
use Juzimder\McpAuth\McpSessionStore;
use Juzimder\McpAuth\OAuthConfig;
use Juzimder\McpAuth\RedirectUriPolicy;
use Juzimder\Store;
use League\OAuth2\Server\CryptKey;
use Mcp\Exception\ToolCallException;
use Symfony\Component\Uid\Uuid;

function mcpCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); echo "PASS $message\n"; }
function mcpRejects(callable $fn, string $message): void { try { $fn(); } catch (ToolCallException) { mcpCheck(true, $message); return; } throw new RuntimeException('Expected denial: ' . $message); }

$dir = sys_get_temp_dir() . '/juzimder-mcp-' . bin2hex(random_bytes(6));
mkdir($dir, 0700, true);
$dbPath = $dir . '/app.sqlite';
putenv('JUZIMDER_PUBLIC_BASE_URL=https://mcp.example.test');
try {
    $store = new Store($dbPath);
    mcpCheck((int)$store->db->query('PRAGMA user_version')->fetchColumn() === 19 && (bool)$store->db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='mcp_oauth_refresh_tokens'")->fetchColumn(), 'OAuth, rotating refresh-token and MCP session migrations');
    $store->query('INSERT INTO users(id,email,password_hash,auth_version) VALUES(?,?,?,1)', ['owner','owner@example.test',password_hash('test password', PASSWORD_DEFAULT)]);
    $store->query('INSERT INTO users(id,email,password_hash,auth_version) VALUES(?,?,?,1)', ['other','other@example.test',password_hash('test password', PASSWORD_DEFAULT)]);
    $ownerProject = $store->create('owner', 'MCP sample');
    $otherProject = $store->create('other', 'Private project');

    $document = Document::empty();
    $document['data']['pages'][0]['name'] = 'Overview';
    $document['data']['pages'][0]['frames'][0]['component']['components'] = [
        ['type'=>'text','tagName'=>'h1','attributes'=>['id'=>'editable-title'],'content'=>'Visible title'],
        ['type'=>'custom-code','tagName'=>'div','content'=>CustomCode::encode(['html'=>'<p>secret markup</p>','css'=>'/* secret */','js'=>'token=secret'])],
        ['type'=>'input','tagName'=>'input','attributes'=>['type'=>'password','value'=>'private-value']],
    ];
    $document['data']['pages'][] = ['id'=>'second','name'=>'Second','frames'=>[['component'=>['type'=>'wrapper','style'=>(object)[],'components'=>[['type'=>'text','tagName'=>'h2','attributes'=>['id'=>'second-title'],'content'=>'Other page']]]]]];
    $saved = $store->save($ownerProject['id'], 'owner', 0, $document);
    mcpCheck($saved['version'] === 1, 'MCP fixture has a saved project revision');
    $otherDocument = Document::empty();
    $otherDocument['data']['pages'][] = ['id'=>'foreign-only','name'=>'Foreign only','frames'=>[['component'=>['type'=>'wrapper','style'=>(object)[],'components'=>[]]]]];
    $store->save($otherProject['id'], 'other', 0, $otherDocument);

    $settings = $saved['settings'];
    $settings['forms'] = ['recipients'=>['private@example.test']];
    $settings['customCss'] = '/* private custom CSS */';
    $store->query('UPDATE projects SET settings_payload=? WHERE id=?', [json_encode($settings, JSON_THROW_ON_ERROR), $ownerProject['id']]);

    $tools = new McpReadTools($store, 'owner');
    $projects = $tools->list_projects(1, 0);
    mcpCheck(count($projects['items']) === 1 && $projects['items'][0]['id'] === $ownerProject['id'] && $projects['nextOffset'] === null, 'List projects is owner-scoped and paginated');
    $pages = $tools->list_pages($ownerProject['id'], 1, 0);
    mcpCheck($pages['projectVersion'] === 1 && count($pages['items']) === 1 && $pages['nextOffset'] === 1, 'List pages is project-scoped and paginated');
    $page = $tools->get_page($ownerProject['id'], 'home');
    $pageJson = json_encode($page, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    mcpCheck($page['projectVersion'] === 1 && $page['projectData']['schemaVersion'] === 1 && $page['projectData']['engine'] === 'grapesjs', 'Page returns canonical Project Data envelope and concurrency version');
    mcpCheck($page['redactedCustomCodeCount'] === 1 && !str_contains($pageJson, 'secret markup') && !str_contains($pageJson, 'token=secret') && !str_contains($pageJson, 'private-value'), 'Page projection removes executable Custom Code and password value');
    mcpRejects(fn()=>$tools->get_page($ownerProject['id'], 'foreign-only'), 'Reject page from a different project');
    mcpRejects(fn()=>$tools->list_pages($otherProject['id']), 'Reject foreign project on list pages');
    mcpRejects(fn()=>$tools->get_page($otherProject['id'], 'home'), 'Reject foreign project on get page');
    $safeSettings = $tools->get_project_settings($ownerProject['id']);
    $settingsJson = json_encode($safeSettings, JSON_THROW_ON_ERROR);
    mcpCheck(isset($safeSettings['settings']['designTokens'],$safeSettings['settings']['pages']) && !isset($safeSettings['settings']['forms'],$safeSettings['settings']['customCss']) && !str_contains($settingsJson, 'private@example.test') && !str_contains($settingsJson, 'private custom CSS'), 'Settings response applies explicit allowlist');

    $writeTools = new McpWriteTools($store, 'owner', [OAuthConfig::SCOPE, OAuthConfig::WRITE_SCOPE]);
    $settingsWrite = $writeTools->update_project_settings($ownerProject['id'], 1, ['notFound'=>['title'=>'Page unavailable']]);
    mcpCheck($settingsWrite['projectVersion'] === 2 && $settingsWrite['updatedFields'] === ['notFound'], 'MCP settings write reuses validation and commits with expected version');
    mcpRejects(fn()=>$writeTools->update_project_settings($ownerProject['id'], 1, ['notFound'=>['title'=>'Stale write']]), 'Reject stale settings write without changing the project');
    mcpRejects(fn()=>$writeTools->update_project_settings($ownerProject['id'], 2, ['forms'=>['recipients'=>['attacker@example.test']]]), 'Reject form delivery and private operational settings from MCP settings writes');
    mcpRejects(fn()=>$writeTools->update_project_settings($otherProject['id'], 1, ['notFound'=>['title'=>'Foreign']]), 'Reject settings write to a project the user does not own');
    $pageWrite = $writeTools->apply_page_operations($ownerProject['id'], 'home', 2, [['operation'=>'update_content','componentId'=>'editable-title','content'=>'Updated title']]);
    mcpCheck($pageWrite['projectVersion'] === 3 && $pageWrite['appliedOperationCount'] === 1, 'MCP page operation uses ProjectOperations and writes with expected version');
    $updated = $store->owned($ownerProject['id'], 'owner');
    mcpCheck($updated['document']->schemaVersion === 1 && $updated['document']->data->pages[0]->frames[0]->component->components[0]->content === 'Updated title', 'Page write preserves canonical Project Data envelope and updates the requested component');
    mcpRejects(fn()=>$writeTools->apply_page_operations($ownerProject['id'], 'home', 3, [['operation'=>'update_content','componentId'=>'second-title','content'=>'Cross-page edit']]), 'Reject component mutation through a different page identifier');
    mcpRejects(fn()=>$writeTools->apply_page_operations($ownerProject['id'], 'home', 3, [['operation'=>'create_component','parentId'=>'page:home','component'=>['type'=>'custom-code','tagName'=>'div','content'=>'run me']]]), 'Reject executable Custom Code insertion through MCP');
    $readOnlyWriter = new McpWriteTools($store, 'owner', [OAuthConfig::SCOPE]);
    mcpRejects(fn()=>$readOnlyWriter->update_project_settings($ownerProject['id'], 3, ['notFound'=>['title'=>'Forbidden']]), 'Read-only OAuth scope cannot write settings');
    mcpCheck((int)$store->query('SELECT version FROM documents WHERE project_id=?', [$ownerProject['id']])->fetchColumn() === 3, 'Rejected writes do not create extra project revisions');

    $sessions = new McpSessionStore($store, 60);
    $sessionId = Uuid::v4();
    mcpCheck($sessions->write($sessionId, '{"protocol":"state"}') && $sessions->exists($sessionId) && $sessions->read($sessionId) === '{"protocol":"state"}', 'MCP session state survives as SQLite-backed protocol state');
    $sessions->destroy($sessionId);
    mcpCheck(!$sessions->exists($sessionId), 'MCP session teardown removes stored state');

    mcpCheck(RedirectUriPolicy::valid('https://client.example/callback') && RedirectUriPolicy::valid('http://127.0.0.1:3456/oauth/callback') && !RedirectUriPolicy::valid('http://client.example/callback') && !RedirectUriPolicy::valid('javascript:alert(1)'), 'OAuth redirect URI policy permits HTTPS and loopback only');
    $config = new OAuthConfig($store);
    $metadata = $config->metadata();
    mcpCheck($metadata['authorization_response_iss_parameter_supported'] === true && $metadata['code_challenge_methods_supported'] === ['S256'] && $metadata['grant_types_supported'] === ['authorization_code','refresh_token'] && $metadata['scopes_supported'] === OAuthConfig::allowedScopes() && $config->resource() === 'https://mcp.example.test/mcp', 'OAuth metadata advertises issuer identification, PKCE, refresh, resource and read/write scopes');
    $originalServer = $_SERVER;
    putenv('JUZIMDER_PUBLIC_BASE_URL');
    putenv('JUZIMDER_DEV_AUTH');
    $_SERVER['HTTP_HOST'] = 'builder.example.test';
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['REMOTE_ADDR'] = '198.51.100.10';
    mcpCheck(OAuthConfig::issuerUrl() === 'https://builder.example.test', 'Shared HTTPS host can derive OAuth issuer without environment-variable support');
    $_SERVER['HTTP_HOST'] = 'builder.example.test/path';
    $invalidHostRejected = false;
    try { OAuthConfig::issuerUrl(); } catch (RuntimeException $error) { $invalidHostRejected = str_contains($error->getMessage(), 'valid HTTPS origin'); }
    mcpCheck($invalidHostRejected, 'Reject malformed Host values when deriving OAuth issuer');
    $_SERVER['HTTP_HOST'] = 'juzimder-ai.localhost:8080';
    $_SERVER['HTTPS'] = 'off';
    putenv('JUZIMDER_DEV_AUTH=1');
    mcpCheck(OAuthConfig::issuerUrl() === 'http://juzimder-ai.localhost:8080', 'Explicit loopback development mode retains its local HTTP issuer');
    $_SERVER = $originalServer;
    putenv('JUZIMDER_PUBLIC_BASE_URL=https://mcp.example.test');
    putenv('JUZIMDER_DEV_AUTH');
    mcpCheck(OAuthConfig::normalizeScopes(null) === [OAuthConfig::SCOPE], 'OAuth requests default to read only; write must be explicitly requested and approved');
    $store->query('INSERT INTO mcp_oauth_clients(client_id,client_name,redirect_uris,created_at) VALUES(?,?,?,?)', ['legacy-client','Legacy client',json_encode(['http://127.0.0.1:7777/callback'], JSON_THROW_ON_ERROR),time()]);
    $legacyClient = $config->repositories()->getClientEntity('legacy-client');
    mcpCheck($legacyClient instanceof \Juzimder\McpAuth\McpClient && $legacyClient->supportsGrantType('authorization_code') && !$legacyClient->supportsGrantType('refresh_token'), 'Existing client migration does not silently widen supported OAuth grants');
    $registration = $config->repositories()->registerClient('Test Client', ['http://127.0.0.1:4567/callback']);
    $client = $config->repositories()->getClientEntity($registration['client_id']);
    $scope = $config->repositories()->getScopeEntityByIdentifier(OAuthConfig::SCOPE);
    $access = $config->repositories()->getNewToken($client, [$scope], 'owner');
    $access->setIdentifier('test-valid-jti');
    $access->setExpiryDateTime(new DateTimeImmutable('+10 minutes'));
    $access->setPrivateKey(new CryptKey($config->privateKeyPath(), null, false));
    $config->repositories()->persistNewAccessToken($access);
    $jwt = $access->toString();
    $principal = BearerValidator::validate($jwt, $config);
    mcpCheck($principal?->userId === 'owner' && $principal?->scopes === [OAuthConfig::SCOPE], 'Bearer validation checks token signature, issuer, resource, user, scope and database record');
    $config->repositories()->revokeAccessToken('test-valid-jti');
    mcpCheck(BearerValidator::validate($jwt, $config) === null, 'Revoked access token is rejected immediately');

    $emptyScopeAccess = $config->repositories()->getNewToken($client, [], 'owner');
    $emptyScopeAccess->setIdentifier('test-insufficient-jti');
    $emptyScopeAccess->setExpiryDateTime(new DateTimeImmutable('+10 minutes'));
    $emptyScopeAccess->setPrivateKey(new CryptKey($config->privateKeyPath(), null, false));
    $config->repositories()->persistNewAccessToken($emptyScopeAccess);
    $insufficient = BearerValidator::validate($emptyScopeAccess->toString(), $config);
    mcpCheck($insufficient !== null && $insufficient->scopes === [], 'Valid token without read scope is distinguishable for HTTP 403 handling');

    $otherClient = $config->repositories()->registerClient('Second Client', ['http://127.0.0.1:4568/callback']);
    $foreignAudienceToken = \Firebase\JWT\JWT::encode([
        'iss'=>$config->issuer(),'aud'=>'https://wrong.example/mcp','sub'=>'owner','client_id'=>$otherClient['client_id'],
        'jti'=>'test-wrong-audience','iat'=>time(),'nbf'=>time(),'exp'=>time()+600,'scope'=>OAuthConfig::SCOPE,'scopes'=>[OAuthConfig::SCOPE],
    ], file_get_contents($config->privateKeyPath()), 'RS256');
    $store->query('INSERT INTO mcp_oauth_access_tokens(jti,client_id,user_id,auth_version,scopes,expires_at) VALUES(?,?,?,?,?,?)', ['test-wrong-audience',$otherClient['client_id'],'owner',1,json_encode([OAuthConfig::SCOPE]),time()+600]);
    mcpCheck(BearerValidator::validate($foreignAudienceToken, $config) === null, 'Token with wrong audience/resource is rejected');

    $expired = \Firebase\JWT\JWT::encode([
        'iss'=>$config->issuer(),'aud'=>[$config->resource()],'sub'=>'owner','client_id'=>$registration['client_id'],
        'jti'=>'test-expired','iat'=>time()-1200,'nbf'=>time()-1200,'exp'=>time()-600,'scope'=>OAuthConfig::SCOPE,'scopes'=>[OAuthConfig::SCOPE],
    ], file_get_contents($config->privateKeyPath()), 'RS256');
    $store->query('INSERT INTO mcp_oauth_access_tokens(jti,client_id,user_id,auth_version,scopes,expires_at) VALUES(?,?,?,?,?,?)', ['test-expired',$registration['client_id'],'owner',1,json_encode([OAuthConfig::SCOPE]),time()-600]);
    mcpCheck(BearerValidator::validate($expired, $config) === null, 'Expired access token is rejected');
    $before = (int)$store->query('SELECT version FROM documents WHERE project_id=?', [$ownerProject['id']])->fetchColumn();
    mcpCheck($before === 3, 'Only the two successful MCP write calls changed the fixture revision');
} finally {
    unset($store);
    $remove = static function (string $path) use (&$remove): void {
        if (is_dir($path)) { foreach (array_diff(scandir($path) ?: [], ['.','..']) as $child) $remove($path . '/' . $child); @rmdir($path); }
        elseif (is_file($path)) @unlink($path);
    };
    $remove($dir);
    putenv('JUZIMDER_PUBLIC_BASE_URL');
}
