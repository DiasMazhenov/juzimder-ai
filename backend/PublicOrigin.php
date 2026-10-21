<?php
declare(strict_types=1);
namespace Juzimder;

final class PublicOrigin {
    public static function hostSuffix(): string {
        $suffix = strtolower(trim(getenv('JUZIMDER_PUBLIC_HOST_SUFFIX') ?: 'juzimder-ai.localhost:8080'));
        if (strlen($suffix) > 253 || !preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?::[0-9]{1,5})?$/D', $suffix)) {
            return 'juzimder-ai.localhost:8080';
        }
        if (preg_match('/:(\d+)$/D', $suffix, $port) && ((int)$port[1] < 1 || (int)$port[1] > 65535)) return 'juzimder-ai.localhost:8080';
        return $suffix;
    }

    public static function projectIdForHost(string $host): ?string {
        $host = strtolower(trim($host));
        $suffix = self::hostSuffix();
        $ending = '.' . $suffix;
        if (!str_ends_with($host, $ending)) return null;
        $projectId = substr($host, 0, -strlen($ending));
        return preg_match('/^[a-f0-9]{24}$/D', $projectId) ? $projectId : null;
    }

    public static function url(string $projectId): string {
        if (!preg_match('/^[a-f0-9]{24}$/D', $projectId)) throw new Problem(422, 'Некорректный адрес публикации.');
        $configuredScheme = strtolower(trim(getenv('JUZIMDER_PUBLIC_SCHEME') ?: ''));
        $localHost = str_contains(self::hostSuffix(), 'localhost') || str_contains(self::hostSuffix(), '127.0.0.1');
        $scheme = in_array($configuredScheme, ['http', 'https'], true) ? $configuredScheme : ($localHost ? 'http' : 'https');
        return $scheme . '://' . $projectId . '.' . self::hostSuffix() . '/';
    }
}
