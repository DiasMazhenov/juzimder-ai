<?php
declare(strict_types=1);
namespace Juzimder;

final class SessionPolicy {
    public const IDLE_TIMEOUT_SECONDS = 1800;
    public const ABSOLUTE_TIMEOUT_SECONDS = 43200;

    public static function isExpired(mixed $createdAt, mixed $lastActivityAt, int $now): bool {
        if (!is_int($createdAt) || !is_int($lastActivityAt) || $createdAt > $now || $lastActivityAt > $now) return true;
        return ($now - $lastActivityAt) >= self::IDLE_TIMEOUT_SECONDS
            || ($now - $createdAt) >= self::ABSOLUTE_TIMEOUT_SECONDS;
    }
}
