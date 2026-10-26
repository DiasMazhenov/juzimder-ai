<?php
declare(strict_types=1);
require_once __DIR__ . '/../backend/TrustedProxyPolicy.php';
use Juzimder\TrustedProxyPolicy;

putenv('JUZIMDER_TRUSTED_PROXY_IPS=10.0.0.8,10.0.0.9,2001:db8::8');
$cases = [
    [TrustedProxyPolicy::clientIp(['REMOTE_ADDR'=>'198.51.100.1','HTTP_X_FORWARDED_FOR'=>'203.0.113.5']), '198.51.100.1', 'untrusted peers cannot spoof the client address'],
    [TrustedProxyPolicy::clientIp(['REMOTE_ADDR'=>'10.0.0.8','HTTP_X_FORWARDED_FOR'=>'198.51.100.77']), '198.51.100.77', 'trusted proxies may provide the client address'],
    [TrustedProxyPolicy::clientIp(['REMOTE_ADDR'=>'10.0.0.8','HTTP_X_FORWARDED_FOR'=>'192.0.2.200, 198.51.100.77']), '198.51.100.77', 'the closest untrusted hop wins over a spoofed X-Forwarded-For prefix'],
    [TrustedProxyPolicy::clientIp(['REMOTE_ADDR'=>'10.0.0.8','HTTP_X_FORWARDED_FOR'=>'192.0.2.200, 10.0.0.9']), '192.0.2.200', 'trusted proxy chains are walked from the application edge'],
    [TrustedProxyPolicy::clientIp(['REMOTE_ADDR'=>'10.0.0.8','HTTP_X_FORWARDED_FOR'=>'not-an-ip']), '10.0.0.8', 'malformed forwarded addresses fall back to the proxy peer'],
    [TrustedProxyPolicy::clientIp(['REMOTE_ADDR'=>'10.0.0.8','HTTP_X_FORWARDED_FOR'=>str_repeat('1.2.3.4,', 30)]), '10.0.0.8', 'oversized proxy chains fall back to the peer'],
    [TrustedProxyPolicy::isHttps(['REMOTE_ADDR'=>'198.51.100.1','HTTP_X_FORWARDED_PROTO'=>'https']), false, 'untrusted peers cannot assert HTTPS'],
    [TrustedProxyPolicy::isHttps(['REMOTE_ADDR'=>'10.0.0.8','HTTP_X_FORWARDED_PROTO'=>'https']), true, 'trusted proxy HTTPS is accepted'],
    [TrustedProxyPolicy::isHttps(['REMOTE_ADDR'=>'10.0.0.8','HTTP_X_FORWARDED_PROTO'=>'https, http']), false, 'ambiguous proxy protocol chains are rejected'],
    [TrustedProxyPolicy::isHttps(['HTTPS'=>'on','REMOTE_ADDR'=>'198.51.100.1']), true, 'server-native HTTPS remains authoritative'],
];
foreach ($cases as [$actual, $expected, $name]) {
    if ($actual !== $expected) throw new RuntimeException("Trusted proxy policy regression: $name");
}
putenv('JUZIMDER_TRUSTED_PROXY_IPS=10.0.0.8,not-an-ip');
if (TrustedProxyPolicy::isHttps(['REMOTE_ADDR'=>'10.0.0.8','HTTP_X_FORWARDED_PROTO'=>'https'])) throw new RuntimeException('Invalid trusted proxy config must fail closed.');
echo "Trusted proxy policy passed\n";
