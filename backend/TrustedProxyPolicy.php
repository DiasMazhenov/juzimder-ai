<?php
declare(strict_types=1);
namespace Juzimder;

final class TrustedProxyPolicy {
    /** @return list<string> */
    private static function trustedProxyIps(): array {
        $configured = getenv('JUZIMDER_TRUSTED_PROXY_IPS');
        if (!is_string($configured) || trim($configured) === '' || strlen($configured) > 2048) return [];
        $values = array_map('trim', explode(',', $configured));
        if (count($values) > 32 || in_array('', $values, true)) return [];
        foreach ($values as $value) if (filter_var($value, FILTER_VALIDATE_IP) === false) return [];
        return array_values(array_unique($values));
    }

    private static function sameIp(string $first, string $second): bool {
        $firstPacked = @inet_pton($first);
        $secondPacked = @inet_pton($second);
        return is_string($firstPacked) && is_string($secondPacked) && hash_equals($firstPacked, $secondPacked);
    }

    private static function isTrusted(string $ip, array $trusted): bool {
        foreach ($trusted as $candidate) if (self::sameIp($ip, $candidate)) return true;
        return false;
    }

    public static function clientIp(array $server): ?string {
        $peer = $server['REMOTE_ADDR'] ?? null;
        if (!is_string($peer) || filter_var($peer, FILTER_VALIDATE_IP) === false) return null;
        $trusted = self::trustedProxyIps();
        if (!self::isTrusted($peer, $trusted)) return $peer;

        $forwarded = $server['HTTP_X_FORWARDED_FOR'] ?? null;
        if (!is_string($forwarded) || $forwarded === '' || strlen($forwarded) > 1024) return $peer;
        $hops = array_map('trim', explode(',', $forwarded));
        if (count($hops) > 20) return $peer;
        foreach ($hops as $hop) if ($hop === '' || filter_var($hop, FILTER_VALIDATE_IP) === false) return $peer;

        for ($index = count($hops) - 1; $index >= 0; $index--) {
            if (self::isTrusted($hops[$index], $trusted)) continue;
            return $hops[$index];
        }
        return $peer;
    }

    public static function isHttps(array $server): bool {
        $https = $server['HTTPS'] ?? null;
        if (is_string($https) && !empty($https) && strtolower($https) !== 'off') return true;

        $peer = $server['REMOTE_ADDR'] ?? null;
        if (!is_string($peer) || filter_var($peer, FILTER_VALIDATE_IP) === false || !self::isTrusted($peer, self::trustedProxyIps())) return false;
        $protocol = $server['HTTP_X_FORWARDED_PROTO'] ?? null;
        if (!is_string($protocol) || strlen($protocol) > 16 || str_contains($protocol, ',')) return false;
        return strtolower(trim($protocol)) === 'https';
    }
}
