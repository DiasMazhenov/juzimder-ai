<?php
declare(strict_types=1);
require_once __DIR__ . '/../backend/AccountRecovery.php';
use Juzimder\AccountRecoveryCodes;

$codes = AccountRecoveryCodes::generate();
if (count($codes) !== AccountRecoveryCodes::COUNT || count(array_unique($codes)) !== AccountRecoveryCodes::COUNT) throw new RuntimeException('Recovery codes must be unique and have the configured count.');
foreach ($codes as $code) {
    if (preg_match('/^[A-F0-9]{6}(?:-[A-F0-9]{6}){3}$/D', $code) !== 1) throw new RuntimeException('Recovery codes must use the visible grouped format.');
    $normalized = AccountRecoveryCodes::normalize(strtolower(str_replace('-', ' ', $code)));
    if ($normalized !== str_replace('-', '', $code)) throw new RuntimeException('Recovery code normalization must ignore case and separators.');
    if (AccountRecoveryCodes::hash((string)$normalized) === $normalized) throw new RuntimeException('Recovery codes must never be stored as plaintext.');
}
if (AccountRecoveryCodes::normalize('not-a-code') !== null) throw new RuntimeException('Malformed recovery codes must be rejected.');
echo "Account recovery code policy assertions passed.\n";
