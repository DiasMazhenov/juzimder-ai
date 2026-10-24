<?php
declare(strict_types=1);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
require_once __DIR__ . '/../backend/PublicOrigin.php';
$publicProjectId = Juzimder\PublicOrigin::projectIdForHost((string)($_SERVER['HTTP_HOST'] ?? ''));
if ($publicProjectId !== null) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'none'; script-src 'none'; connect-src 'none'; style-src 'self' 'unsafe-inline'; img-src 'self' https: data:; font-src 'self' data:; object-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'");
    header('Cache-Control: no-store');
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, ['GET','HEAD'], true)) {
        http_response_code(405); header('Allow: GET, HEAD'); echo 'Method not allowed'; return true;
    }
    $path = is_string($path) ? $path : '';
    if ($path === '/') $filePath = 'index.html';
    elseif ($path === '/assets/site.css') $filePath = 'assets/site.css';
    elseif (preg_match('~^/media/([a-f0-9]{24})/([a-f0-9]{24}\.(?:jpg|png|gif|webp))$~D', $path, $mediaMatch) && hash_equals($publicProjectId, $mediaMatch[1])) $filePath = 'media/' . $mediaMatch[1] . '/' . $mediaMatch[2];
    elseif (preg_match('~^/([a-z0-9]+(?:-[a-z0-9]+)*)$~D', $path, $routeMatch)) {
        header('Location: ' . Juzimder\PublicOrigin::url($publicProjectId) . $routeMatch[1] . '/', true, 308);
        return true;
    } elseif (preg_match('~^/([a-z0-9]+(?:-[a-z0-9]+)*)/$~D', $path, $routeMatch)) $filePath = $routeMatch[1] . '/index.html';
    else { http_response_code(404); echo 'Not found'; return true; }
    try {
        require_once __DIR__ . '/../backend/Domain.php';
        $databasePath = getenv('JUZIMDER_DB') ?: __DIR__ . '/../var/app.sqlite';
        $store = new Juzimder\Store($databasePath);
        $publishedFile = $store->publicFile($publicProjectId, $filePath);
    } catch (Throwable) {
        http_response_code(503); echo 'Publication unavailable'; return true;
    }
    if ($publishedFile === null) { http_response_code(404); echo 'Not found'; return true; }
    header('Content-Type: ' . ($publishedFile['mime'] ?? 'text/html; charset=utf-8'));
    header('Content-Length: ' . strlen($publishedFile['content']));
    header('ETag: "pub-' . $publishedFile['version'] . '-' . hash('sha256', $publishedFile['content']) . '"');
    if (str_starts_with((string)($publishedFile['mime'] ?? ''), 'text/html')) {
        require_once __DIR__ . '/../backend/StaticSiteExporter.php';
        header('Content-Security-Policy: ' . Juzimder\StaticSiteExporter::pageSecurityPolicy($publishedFile['content']));
    }
    if ($method !== 'HEAD') echo $publishedFile['content'];
    return true;
}
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' https: data:; font-src 'self' data:; frame-src 'self' blob:; object-src 'none'; base-uri 'none'; frame-ancestors 'none'");
if (in_array(strtolower((string)($_SERVER['HTTP_HOST'] ?? '')), ['127.0.0.1:8080', 'localhost:8080'], true) && !str_starts_with($path, '/api/')) {
    header('Location: http://juzimder-ai.localhost:8080' . ($_SERVER['REQUEST_URI'] ?? '/'), true, 308);
    return true;
}
if (str_starts_with($path, '/api/')) { require __DIR__ . '/../backend/api.php'; return true; }
if ($path === '/' || $path === '/index.html' || preg_match('~^/projects/[a-f0-9]{24}(?:/settings|/pages/[A-Za-z0-9_-]{1,128}/edit)?$~D',$path)) { if (is_file(__DIR__ . '/index.html')) { header('Content-Type: text/html; charset=utf-8'); readfile(__DIR__.'/index.html'); } else { http_response_code(503); echo 'Run npm run build'; } return true; }
if (preg_match('~^/assets/[a-zA-Z0-9_.-]+$~D', $path) && is_file(__DIR__.$path)) return false;
http_response_code(404); echo 'Not found';
