<?php
declare(strict_types=1);

namespace Juzimder\McpAuth;

use DateInterval;
use DateTimeImmutable;
use Juzimder\Problem;
use Juzimder\Store;
use Juzimder\TrustedProxyPolicy;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\CryptKeyInterface;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\AuthCodeTrait;
use League\OAuth2\Server\Entities\Traits\ClientTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Token\UnencryptedToken;

final class OAuthConfig
{
    public const SCOPE = 'projects:read';
    public const WRITE_SCOPE = 'projects:write';
    public const ACCESS_TTL = 900;

    public static function allowedScopes(): array { return [self::SCOPE, self::WRITE_SCOPE]; }

    public static function normalizeScopes(?string $scope = null): ?array
    {
        $scope = trim((string)($scope ?? ''));
        $scopes = $scope === '' ? [self::SCOPE] : (preg_split('/\s+/', $scope, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        if (!in_array(self::SCOPE, $scopes, true) || count($scopes) !== count(array_unique($scopes))) return null;
        foreach ($scopes as $item) if (!in_array($item, self::allowedScopes(), true)) return null;
        return array_values($scopes);
    }

    private string $issuer;
    private string $resource;
    private string $privateKeyPath;
    private string $publicKeyPath;
    private string $encryptionKey;
    private AuthorizationServer $server;
    private OAuthRepositories $repositories;

    public function __construct(public readonly Store $store)
    {
        $this->issuer = self::issuerUrl();
        $this->resource = $this->issuer . '/mcp';
        $keyDirectory = dirname($store->path) . '/mcp-oauth';
        if (!is_dir($keyDirectory) && !mkdir($keyDirectory, 0700, true) && !is_dir($keyDirectory)) {
            throw new \RuntimeException('OAuth key storage is unavailable.');
        }
        @chmod($keyDirectory, 0700);
        $this->privateKeyPath = $keyDirectory . '/private.pem';
        $this->publicKeyPath = $keyDirectory . '/public.pem';
        $encryptionPath = $keyDirectory . '/encryption.key';
        $this->ensureKeys($encryptionPath);
        $this->encryptionKey = trim((string)file_get_contents($encryptionPath));
        $this->repositories = new OAuthRepositories($store, $this->issuer, $this->resource);
        $this->server = new AuthorizationServer(
            $this->repositories,
            $this->repositories,
            $this->repositories,
            new CryptKey($this->privateKeyPath, null, false),
            $this->encryptionKey,
        );
        $this->server->setDefaultScope(self::SCOPE);
        $this->server->enableGrantType(
            new AuthCodeGrant($this->repositories, $this->repositories, new DateInterval('PT5M')),
            new DateInterval('PT15M'),
        );
        $refreshGrant = new RefreshTokenGrant($this->repositories);
        $refreshGrant->setRefreshTokenTTL(new DateInterval('P30D'));
        $this->server->enableGrantType($refreshGrant, new DateInterval('PT15M'));
    }

    public function server(): AuthorizationServer { return $this->server; }
    public function repositories(): OAuthRepositories { return $this->repositories; }
    public function issuer(): string { return $this->issuer; }
    public function resource(): string { return $this->resource; }
    public function privateKeyPath(): string { return $this->privateKeyPath; }
    public function publicKeyPath(): string { return $this->publicKeyPath; }

    public function decodeRefreshToken(string $token): ?array
    {
        try {
            $payload = \Defuse\Crypto\Crypto::decryptWithPassword($token, $this->encryptionKey);
            $decoded = json_decode($payload, true, 16, JSON_THROW_ON_ERROR);
            if (!is_array($decoded) || !is_string($decoded['client_id'] ?? null)
                || !is_string($decoded['refresh_token_id'] ?? null) || !is_string($decoded['access_token_id'] ?? null)
                || !is_string($decoded['user_id'] ?? null) || !is_int($decoded['expire_time'] ?? null)
                || !is_array($decoded['scopes'] ?? null)) return null;
            return $decoded;
        } catch (\Throwable) {
            return null;
        }
    }

    public function metadata(): array
    {
        return [
            'issuer' => $this->issuer,
            'authorization_endpoint' => $this->issuer . '/oauth/authorize',
            'token_endpoint' => $this->issuer . '/oauth/token',
            'registration_endpoint' => $this->issuer . '/oauth/register',
            'revocation_endpoint' => $this->issuer . '/oauth/revoke',
            'response_types_supported' => ['code'],
            'response_modes_supported' => ['query'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'authorization_response_iss_parameter_supported' => true,
            'token_endpoint_auth_methods_supported' => ['none'],
            'revocation_endpoint_auth_methods_supported' => ['none'],
            'scopes_supported' => self::allowedScopes(),
            'service_documentation' => 'https://modelcontextprotocol.io/specification/2025-11-25/basic/authorization',
        ];
    }

    public function resourceMetadata(): array
    {
        return [
            'resource' => $this->resource,
            'authorization_servers' => [$this->issuer],
            'scopes_supported' => self::allowedScopes(),
            'bearer_methods_supported' => ['header'],
        ];
    }

    public static function issuerUrl(): string
    {
        $configured = getenv('JUZIMDER_PUBLIC_BASE_URL');
        if (is_string($configured) && trim($configured) !== '') {
            $parts = parse_url(trim($configured));
            if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['https', 'http'], true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || rtrim((string)($parts['path'] ?? ''), '/') !== '') {
                throw new \RuntimeException('JUZIMDER_PUBLIC_BASE_URL must be an origin without a path.');
            }
            if (($parts['scheme'] ?? '') !== 'https' && !self::isLoopbackHost((string)$parts['host'])) {
                throw new \RuntimeException('The MCP OAuth issuer requires HTTPS.');
            }
            return rtrim(trim($configured), '/');
        }
        // Shared hosts commonly do not let an application set environment variables.
        // In that case use the HTTPS origin that served this request, after strict host
        // validation. Explicit configuration remains preferred for unusual proxy setups.
        $origin = self::requestOrigin($_SERVER);
        if ($origin !== null) return $origin;

        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        $hostWithoutPort = preg_replace('/:\\d+$/', '', $host) ?? $host;
        if (getenv('JUZIMDER_DEV_AUTH') === '1' && self::isLoopbackHost($hostWithoutPort)) {
            return 'http://' . $host;
        }
        throw new \RuntimeException('MCP OAuth requires a valid HTTPS origin. Configure JUZIMDER_PUBLIC_BASE_URL when the hosting proxy does not expose HTTPS to PHP.');
    }

    private static function requestOrigin(array $server): ?string
    {
        if (!TrustedProxyPolicy::isHttps($server)) return null;
        $authority = $server['HTTP_HOST'] ?? null;
        if (!is_string($authority) || $authority === '' || strlen($authority) > 260
            || preg_match('/[\\s\\/\\\\@?#,\\x00-\\x1F\\x7F]/', $authority)) return null;
        $parts = parse_url('https://' . $authority);
        if (!is_array($parts) || ($parts['scheme'] ?? null) !== 'https'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['path']) || isset($parts['query']) || isset($parts['fragment'])) return null;

        $host = strtolower(trim((string)($parts['host'] ?? ''), '[]'));
        if ($host === '' || (filter_var($host, FILTER_VALIDATE_IP) === false
            && !preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)(?:\\.(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?))*$/D', $host))) return null;
        $port = $parts['port'] ?? null;
        if ($port !== null && ($port < 1 || $port > 65535)) return null;
        $hostForUrl = str_contains($host, ':') ? '[' . $host . ']' : $host;
        $portSuffix = $port !== null && $port !== 443 ? ':' . $port : '';
        return 'https://' . $hostForUrl . $portSuffix;
    }

    private static function isLoopbackHost(string $host): bool
    {
        return in_array(strtolower(trim($host, '[]')), ['localhost', '127.0.0.1', '::1', 'juzimder-ai.localhost'], true);
    }

    private function ensureKeys(string $encryptionPath): void
    {
        if (is_file($this->privateKeyPath) && is_file($this->publicKeyPath) && is_file($encryptionPath)) {
            @chmod($this->privateKeyPath, 0600);
            @chmod($this->publicKeyPath, 0600);
            @chmod($encryptionPath, 0600);
            return;
        }
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 3072]);
        if ($key === false || !openssl_pkey_export($key, $privatePem)) throw new \RuntimeException('Unable to initialize OAuth signing keys.');
        $details = openssl_pkey_get_details($key);
        if (!is_array($details) || !is_string($details['key'] ?? null)) throw new \RuntimeException('Unable to initialize OAuth signing keys.');
        // Defuse Crypto expects a 32-byte key encoded in its safe ASCII format.
        $encryption = \Defuse\Crypto\Key::createNewRandomKey()->saveToAsciiSafeString();
        foreach ([[$this->privateKeyPath, $privatePem], [$this->publicKeyPath, $details['key']], [$encryptionPath, $encryption]] as [$path, $contents]) {
            if (file_put_contents($path, $contents . "\n", LOCK_EX) === false) throw new \RuntimeException('Unable to store OAuth signing keys.');
            @chmod($path, 0600);
        }
    }
}

final class OAuthRepositories implements ClientRepositoryInterface, AccessTokenRepositoryInterface, AuthCodeRepositoryInterface, RefreshTokenRepositoryInterface, ScopeRepositoryInterface
{
    public function __construct(private readonly Store $store, private readonly string $issuer, private readonly string $resource) {}

    public function registerClient(string $name, array $redirectUris, ?array $scopes = null, array $grantTypes = ['authorization_code']): array
    {
        $scopes ??= OAuthConfig::allowedScopes();
        $grantTypes = array_values(array_unique($grantTypes));
        if (!in_array('authorization_code', $grantTypes, true)
            || array_diff($grantTypes, ['authorization_code', 'refresh_token']) !== []) {
            throw new \InvalidArgumentException('Unsupported OAuth grant types.');
        }
        $clientId = 'jz_' . bin2hex(random_bytes(16));
        $this->store->query('INSERT INTO mcp_oauth_clients(client_id,client_name,redirect_uris,created_at,grant_types) VALUES(?,?,?,?,?)', [$clientId, $name, json_encode($redirectUris, JSON_THROW_ON_ERROR), time(), json_encode($grantTypes, JSON_THROW_ON_ERROR)]);
        return ['client_id'=>$clientId, 'client_id_issued_at'=>time(), 'client_name'=>$name, 'redirect_uris'=>$redirectUris, 'grant_types'=>$grantTypes, 'response_types'=>['code'], 'token_endpoint_auth_method'=>'none', 'scope'=>implode(' ', $scopes)];
    }

    public function getClientEntity(string $clientIdentifier): ?ClientEntityInterface
    {
        $row = $this->store->query('SELECT client_id,client_name,redirect_uris,grant_types FROM mcp_oauth_clients WHERE client_id=?', [$clientIdentifier])->fetch();
        if (!$row) return null;
        $uris = json_decode((string)$row['redirect_uris'], true);
        $grants = json_decode((string)$row['grant_types'], true);
        if (!is_array($uris) || !$uris || !is_array($grants)) return null;
        return new McpClient((string)$row['client_id'], (string)$row['client_name'], $uris, $grants);
    }

    public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType): bool
    {
        $client = $this->getClientEntity($clientIdentifier);
        return $clientSecret === null && $grantType !== null && $client instanceof McpClient && $client->supportsGrantType($grantType);
    }

    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, string|null $userIdentifier = null): AccessTokenEntityInterface
    {
        $token = new McpAccessToken($this->issuer, $this->resource);
        $token->setClient($clientEntity);
        if ($userIdentifier !== null) $token->setUserIdentifier($userIdentifier);
        foreach ($scopes as $scope) $token->addScope($scope);
        return $token;
    }

    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
    {
        $user = $accessTokenEntity->getUserIdentifier();
        if (!is_string($user) || $user === '') throw new \RuntimeException('OAuth user subject is missing.');
        $authVersion = $this->store->query('SELECT auth_version FROM users WHERE id=?', [$user])->fetchColumn();
        if ($authVersion === false) throw new \RuntimeException('OAuth user no longer exists.');
        $scopes = array_map(static fn(ScopeEntityInterface $scope): string => $scope->getIdentifier(), $accessTokenEntity->getScopes());
        $this->store->query('INSERT INTO mcp_oauth_access_tokens(jti,client_id,user_id,auth_version,scopes,expires_at) VALUES(?,?,?,?,?,?)', [
            $accessTokenEntity->getIdentifier(), $accessTokenEntity->getClient()->getIdentifier(), $user, (int)$authVersion,
            json_encode($scopes, JSON_THROW_ON_ERROR), $accessTokenEntity->getExpiryDateTime()->getTimestamp(),
        ]);
    }

    public function revokeAccessToken(string $tokenId): void
    {
        $this->store->query('UPDATE mcp_oauth_access_tokens SET revoked_at=? WHERE jti=? AND revoked_at IS NULL', [time(), $tokenId]);
        $this->store->query('UPDATE mcp_oauth_refresh_tokens SET revoked_at=? WHERE access_token_jti=? AND revoked_at IS NULL', [time(), $tokenId]);
    }

    public function isAccessTokenRevoked(string $tokenId): bool
    {
        $row = $this->store->query('SELECT revoked_at,expires_at FROM mcp_oauth_access_tokens WHERE jti=?', [$tokenId])->fetch();
        return !$row || $row['revoked_at'] !== null || (int)$row['expires_at'] <= time();
    }

    public function accessToken(string $tokenId): ?array
    {
        $row = $this->store->query('SELECT t.jti,t.client_id,t.user_id,t.auth_version,t.scopes,t.expires_at,t.revoked_at,u.auth_version AS current_auth_version FROM mcp_oauth_access_tokens t JOIN users u ON u.id=t.user_id WHERE t.jti=?', [$tokenId])->fetch();
        return $row ?: null;
    }

    public function getNewAuthCode(): AuthCodeEntityInterface { return new McpAuthCode(); }

    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
    {
        $user = $authCodeEntity->getUserIdentifier();
        if (!is_string($user) || $user === '') throw new \RuntimeException('OAuth user subject is missing.');
        $authVersion = $this->store->query('SELECT auth_version FROM users WHERE id=?', [$user])->fetchColumn();
        if ($authVersion === false) throw new \RuntimeException('OAuth user no longer exists.');
        $this->store->query('INSERT INTO mcp_oauth_auth_codes(id,client_id,user_id,auth_version,expires_at) VALUES(?,?,?,?,?)', [
            $authCodeEntity->getIdentifier(), $authCodeEntity->getClient()->getIdentifier(), $user, (int)$authVersion, $authCodeEntity->getExpiryDateTime()->getTimestamp(),
        ]);
    }

    public function revokeAuthCode(string $codeId): void { $this->store->query('UPDATE mcp_oauth_auth_codes SET revoked=1 WHERE id=?', [$codeId]); }

    public function isAuthCodeRevoked(string $codeId): bool
    {
        $row = $this->store->query('SELECT c.revoked,c.expires_at,c.auth_version,u.auth_version AS current_auth_version FROM mcp_oauth_auth_codes c JOIN users u ON u.id=c.user_id WHERE c.id=?', [$codeId])->fetch();
        return !$row || (int)$row['revoked'] === 1 || (int)$row['expires_at'] <= time() || (int)$row['auth_version'] !== (int)$row['current_auth_version'];
    }

    public function getNewRefreshToken(): ?RefreshTokenEntityInterface { return new McpRefreshToken(); }

    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        $access = $refreshTokenEntity->getAccessToken();
        $user = $access->getUserIdentifier();
        if (!is_string($user) || $user === '') throw new \RuntimeException('OAuth user subject is missing.');
        $row = $this->accessToken($access->getIdentifier());
        if (!$row || $row['revoked_at'] !== null || (int)$row['expires_at'] <= time()
            || !hash_equals((string)$row['client_id'], $access->getClient()->getIdentifier())
            || !hash_equals((string)$row['user_id'], $user)) throw new \RuntimeException('Refresh token is not linked to an active access token.');
        $scopes = array_map(static fn(ScopeEntityInterface $scope): string => $scope->getIdentifier(), $access->getScopes());
        $this->store->query('INSERT INTO mcp_oauth_refresh_tokens(id,client_id,user_id,access_token_jti,auth_version,scopes,expires_at) VALUES(?,?,?,?,?,?,?)', [
            $refreshTokenEntity->getIdentifier(), $access->getClient()->getIdentifier(), $user, $access->getIdentifier(),
            (int)$row['auth_version'], json_encode($scopes, JSON_THROW_ON_ERROR), $refreshTokenEntity->getExpiryDateTime()->getTimestamp(),
        ]);
    }

    public function revokeRefreshToken(string $tokenId): void
    {
        $this->store->query('UPDATE mcp_oauth_refresh_tokens SET revoked_at=? WHERE id=? AND revoked_at IS NULL', [time(), $tokenId]);
        $row = $this->store->query('SELECT access_token_jti FROM mcp_oauth_refresh_tokens WHERE id=?', [$tokenId])->fetch();
        if ($row) $this->revokeAccessToken((string)$row['access_token_jti']);
    }

    public function refreshToken(string $tokenId): ?array
    {
        $row = $this->store->query('SELECT r.id,r.client_id,r.user_id,r.access_token_jti,r.auth_version,r.scopes,r.expires_at,r.revoked_at,a.revoked_at AS access_revoked_at,u.auth_version AS current_auth_version FROM mcp_oauth_refresh_tokens r JOIN mcp_oauth_access_tokens a ON a.jti=r.access_token_jti JOIN users u ON u.id=r.user_id WHERE r.id=?', [$tokenId])->fetch();
        return $row ?: null;
    }

    public function isRefreshTokenRevoked(string $tokenId): bool
    {
        $row = $this->refreshToken($tokenId);
        return !$row || $row['revoked_at'] !== null || $row['access_revoked_at'] !== null
            || (int)$row['expires_at'] <= time()
            || (int)$row['auth_version'] !== (int)$row['current_auth_version'];
    }

    public function getScopeEntityByIdentifier(string $identifier): ?ScopeEntityInterface
    {
        return in_array($identifier, OAuthConfig::allowedScopes(), true) ? new McpScope($identifier) : null;
    }

    public function finalizeScopes(array $scopes, string $grantType, ClientEntityInterface $clientEntity, string|null $userIdentifier = null, ?string $authCodeId = null): array
    {
        if (!in_array($grantType, ['authorization_code', 'refresh_token'], true) || !($clientEntity instanceof McpClient)) return [];
        $identifiers = [];
        foreach ($scopes as $scope) {
            if (!$scope instanceof ScopeEntityInterface || !in_array($scope->getIdentifier(), OAuthConfig::allowedScopes(), true)) return [];
            $identifiers[] = $scope->getIdentifier();
        }
        if (!in_array(OAuthConfig::SCOPE, $identifiers, true) || count($identifiers) !== count(array_unique($identifiers))) return [];
        return $scopes;
    }
}

final class McpClient implements ClientEntityInterface
{
    use EntityTrait;
    use ClientTrait;
    public function __construct(string $id, string $name, array $redirectUris, private readonly array $grantTypes) { $this->identifier = $id; $this->name = $name; $this->redirectUri = $redirectUris; $this->isConfidential = false; }
    public function supportsGrantType(string $grantType): bool { return in_array($grantType, $this->grantTypes, true); }
}

final class McpScope implements ScopeEntityInterface, \JsonSerializable
{
    public function __construct(private readonly string $id) {}
    public function getIdentifier(): string { return $this->id; }
    public function jsonSerialize(): string { return $this->id; }
}

final class McpUser implements UserEntityInterface
{
    public function __construct(private readonly string $id) {}
    public function getIdentifier(): string { return $this->id; }
}

final class McpAuthCode implements AuthCodeEntityInterface
{
    use EntityTrait;
    use TokenEntityTrait;
    use AuthCodeTrait;
}

final class McpAccessToken implements AccessTokenEntityInterface
{
    use EntityTrait;
    use TokenEntityTrait;
    private ?CryptKeyInterface $privateKey = null;

    public function __construct(private readonly string $issuer, private readonly string $resource) {}
    public function setPrivateKey(CryptKeyInterface $privateKey): void { $this->privateKey = $privateKey; }

    public function toString(): string
    {
        if ($this->privateKey === null) throw new \LogicException('OAuth signing key is not configured.');
        $private = $this->privateKey->getKeyContents();
        if ($private === '') throw new \LogicException('OAuth signing key is unavailable.');
        $configuration = Configuration::forAsymmetricSigner(new Sha256(), InMemory::plainText($private, $this->privateKey->getPassPhrase() ?? ''), InMemory::plainText('unused'));
        $now = new DateTimeImmutable();
        $scopes = array_map(static fn(ScopeEntityInterface $scope): string => $scope->getIdentifier(), $this->getScopes());
        $builder = $configuration->builder()
            ->issuedBy($this->issuer)
            ->permittedFor($this->resource)
            ->identifiedBy($this->getIdentifier())
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($this->getExpiryDateTime())
            ->relatedTo((string)$this->getUserIdentifier())
            ->withClaim('client_id', $this->getClient()->getIdentifier())
            ->withClaim('scope', implode(' ', $scopes))
            ->withClaim('scopes', $scopes);
        return $builder->getToken($configuration->signer(), $configuration->signingKey())->toString();
    }
}

final class McpRefreshToken implements RefreshTokenEntityInterface
{
    use EntityTrait;
    use \League\OAuth2\Server\Entities\Traits\RefreshTokenTrait;
}

final class BearerPrincipal
{
    public function __construct(public readonly string $userId, public readonly string $clientId, public readonly array $scopes) {}
}

final class BearerValidator
{
    public static function validate(string $token, OAuthConfig $config): ?BearerPrincipal
    {
        try {
            $public = file_get_contents($config->publicKeyPath());
            if (!is_string($public) || $public === '') return null;
            $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($public, 'RS256'));
            $claims = (array)$decoded;
            $audience = $claims['aud'] ?? null;
            $audiences = is_array($audience) ? $audience : [$audience];
            $scopeText = $claims['scope'] ?? null;
            $jti = $claims['jti'] ?? null;
            $userId = $claims['sub'] ?? null;
            $clientId = $claims['client_id'] ?? null;
            if (($claims['iss'] ?? null) !== $config->issuer() || $audiences !== [$config->resource()]
                || !is_string($scopeText) || !is_array($claims['scopes'] ?? null)
                || !is_string($jti) || !is_string($userId) || !is_string($clientId)
                || (int)($claims['exp'] ?? 0) <= time()) return null;
            $tokenScopes = preg_split('/\s+/', trim($scopeText), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $tokenScopes = array_values(array_unique($tokenScopes));
            $claimScopes = array_values(array_unique(array_filter($claims['scopes'], 'is_string')));
            sort($tokenScopes); sort($claimScopes);
            if ($tokenScopes !== $claimScopes) return null;
            $row = $config->repositories()->accessToken($jti);
            if (!$row || $row['revoked_at'] !== null || (int)$row['expires_at'] <= time()
                || !hash_equals((string)$row['user_id'], $userId) || !hash_equals((string)$row['client_id'], $clientId)
                || (int)$row['auth_version'] !== (int)$row['current_auth_version']) return null;
            $storedScopes = json_decode((string)$row['scopes'], true);
            if (!is_array($storedScopes) || !array_is_list($storedScopes)) return null;
            $storedScopes = array_values(array_unique(array_filter($storedScopes, 'is_string')));
            sort($storedScopes);
            if ($storedScopes !== $claimScopes) return null;
            return new BearerPrincipal($userId, $clientId, $claimScopes);
        } catch (\Throwable) {
            return null;
        }
    }
}

final class RedirectUriPolicy
{
    public static function valid(string $uri): bool
    {
        if (strlen($uri) > 2048 || preg_match('/[\x00-\x20\x7f]/', $uri)) return false;
        $parts = parse_url($uri);
        if (!is_array($parts) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || empty($parts['host'])) return false;
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower((string)$parts['host']);
        if ($scheme === 'https') return true;
        if ($scheme !== 'http') return false;
        return in_array(trim($host, '[]'), ['localhost', '127.0.0.1', '::1'], true);
    }
}
