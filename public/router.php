<?php
declare(strict_types=1);
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' https: data:; font-src 'self' data:; frame-src 'self' blob:; object-src 'none'; base-uri 'none'; frame-ancestors 'none'");
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($path, '/api/')) { require __DIR__ . '/../backend/api.php'; return true; }
if ($path === '/' || $path === '/index.html') { if (is_file(__DIR__ . '/index.html')) { header('Content-Type: text/html; charset=utf-8'); readfile(__DIR__.'/index.html'); } else { http_response_code(503); echo 'Run npm run build'; } return true; }
if (preg_match('~^/assets/[a-zA-Z0-9_.-]+$~D', $path) && is_file(__DIR__.$path)) return false;
http_response_code(404); echo 'Not found';
