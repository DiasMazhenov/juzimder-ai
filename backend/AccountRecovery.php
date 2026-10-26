<?php
declare(strict_types=1);
namespace Juzimder;

final class AccountRecoveryCodes {
    public const COUNT = 8;

    /** @return list<string> */
    public static function generate(): array {
        $codes = [];
        for ($i = 0; $i < self::COUNT; $i++) {
            $hex = strtoupper(bin2hex(random_bytes(12)));
            $codes[] = implode('-', str_split($hex, 6));
        }
        return $codes;
    }

    public static function normalize(string $code): ?string {
        $normalized = strtoupper(preg_replace('/[\s-]+/', '', trim($code)) ?? '');
        return preg_match('/^[A-F0-9]{24}$/D', $normalized) === 1 ? $normalized : null;
    }

    public static function hash(string $normalizedCode): string {
        return hash('sha256', $normalizedCode);
    }
}
