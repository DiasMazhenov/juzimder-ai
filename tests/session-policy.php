<?php
declare(strict_types=1);
require_once __DIR__ . '/../backend/SessionPolicy.php';
use Juzimder\SessionPolicy;

$now = 2_000_000_000;
$checks = [
    !SessionPolicy::isExpired($now - 60, $now - 60, $now),
    SessionPolicy::isExpired($now - 60, $now - SessionPolicy::IDLE_TIMEOUT_SECONDS, $now),
    SessionPolicy::isExpired($now - SessionPolicy::ABSOLUTE_TIMEOUT_SECONDS, $now - 10, $now),
    SessionPolicy::isExpired($now + 1, $now, $now),
    SessionPolicy::isExpired($now - 10, null, $now),
];
if (in_array(false, $checks, true)) throw new RuntimeException('Session expiry policy regression.');
echo "Session expiry policy passed\n";
