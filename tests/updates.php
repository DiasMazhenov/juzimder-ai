<?php
declare(strict_types=1);
require __DIR__ . '/../backend/Domain.php';
require_once __DIR__ . '/../backend/MigrationRunner.php';
require_once __DIR__ . '/../backend/Updates.php';

use Juzimder\AppUpdates;
use Juzimder\Problem;
use Juzimder\Store;

function updatesCheck(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS $message\n";
}

function updatesResponse(int $status, array|string $body): array {
    return ['status'=>$status,'body'=>is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)];
}

$root = sys_get_temp_dir() . '/juzimder-updates-' . bin2hex(random_bytes(8));
if (!mkdir($root, 0700, true)) throw new RuntimeException('Unable to create isolated updater fixture.');
$db = new Store($root . '/var/app.sqlite');
file_put_contents($root . '/release.json', '{"productVersion":"00.00.362","packageVersion":"0.1.362"}');
$sourceSha = str_repeat('a', 40);
$packagePayload = 'fixture ready-to-install shared hosting package';
$packageDigest = hash('sha256', $packagePayload);
$runId = 7812345;
$run = [
    'id'=>$runId,
    'run_number'=>42,
    'run_attempt'=>1,
    'event'=>'push',
    'status'=>'completed',
    'conclusion'=>'success',
    'head_branch'=>'main',
    'head_sha'=>$sourceSha,
    'path'=>'.github/workflows/build-shared-hosting.yml',
    'head_repository'=>['full_name'=>'DiasMazhenov/juzimder-ai'],
    'updated_at'=>'2026-11-05T10:00:00Z',
];
$latestUrl = 'https://api.github.com/repos/DiasMazhenov/juzimder-ai/releases/latest';
$commitUrl = 'https://api.github.com/repos/DiasMazhenov/juzimder-ai/commits/main';
$runsUrl = 'https://api.github.com/repos/DiasMazhenov/juzimder-ai/actions/workflows/build-shared-hosting.yml/runs?branch=main&event=push&per_page=10';
$manifestUrl = 'https://api.github.com/repos/DiasMazhenov/juzimder-ai/contents/repository-build.json?ref=shared-hosting-build';
$zipUrl = 'https://raw.githubusercontent.com/DiasMazhenov/juzimder-ai/shared-hosting-build/juzimder-ai-shared-hosting.zip';
$checksumUrl = 'https://raw.githubusercontent.com/DiasMazhenov/juzimder-ai/shared-hosting-build/juzimder-ai-shared-hosting.zip.sha256';
$manifest = json_encode([
    'productVersion'=>'00.00.363',
    'packageVersion'=>'0.1.363',
    'sourceCommit'=>$sourceSha,
    'workflowRunId'=>$runId,
    'workflowRunAttempt'=>1,
    'zipSha256'=>$packageDigest,
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
$manifestEnvelope = ['type'=>'file','encoding'=>'base64','content'=>base64_encode($manifest)];

$makeUpdater = static function(array $extraResponses = []) use ($db, $root, $sourceSha, $run, $latestUrl, $commitUrl, $runsUrl, $manifestUrl, $manifestEnvelope): AppUpdates {
    $responses = array_merge([
        $latestUrl=>updatesResponse(404, ''),
        $commitUrl=>updatesResponse(200, ['sha'=>$sourceSha]),
        $runsUrl=>updatesResponse(200, ['workflow_runs'=>[$run]]),
        $manifestUrl=>updatesResponse(200, $manifestEnvelope),
    ], $extraResponses);
    return new AppUpdates($db, $root, static function(string $url) use ($responses): array {
        if (!array_key_exists($url, $responses)) throw new RuntimeException('Unexpected updater URL: ' . $url);
        return $responses[$url];
    });
};

$result = $makeUpdater()->check();
updatesCheck(
    $result['latestVersion'] === '00.00.363'
    && $result['source'] === 'repository'
    && $result['sourceCommit'] === $sourceSha
    && $result['updateAvailable'] === true
    && str_contains($result['sourceUrl'], $sourceSha),
    'A ready package on the public build branch is offered when it matches a successful workflow for the current main commit'
);

$decoyRuns = [
    array_merge($run, ['id'=>7812346,'event'=>'pull_request']),
    array_merge($run, ['id'=>7812347,'head_repository'=>['full_name'=>'fork-user/juzimder-ai']]),
    array_merge($run, ['id'=>7812348,'conclusion'=>'failure']),
    array_merge($run, ['id'=>7812349,'head_sha'=>str_repeat('b', 40)]),
];
$decoyUpdater = $makeUpdater([$runsUrl=>updatesResponse(200, ['workflow_runs'=>$decoyRuns])]);
try { $decoyUpdater->check(); throw new RuntimeException('A PR, fork, failed, or stale workflow was accepted.'); }
catch (Problem $error) { updatesCheck($error->status === 404, 'Pull request, fork, failed, and stale workflow runs are not offered'); }

$badManifest = ['type'=>'file','encoding'=>'base64','content'=>base64_encode(json_encode([
    'productVersion'=>'00.00.363','packageVersion'=>'0.1.363','sourceCommit'=>str_repeat('b',40),
    'workflowRunId'=>$runId,'workflowRunAttempt'=>1,'zipSha256'=>$packageDigest,
], JSON_THROW_ON_ERROR))];
try { $makeUpdater([$manifestUrl=>updatesResponse(200, $badManifest)])->check(); throw new RuntimeException('A build manifest from an older source commit was offered.'); }
catch (Problem $error) { updatesCheck($error->status === 404, 'A build manifest from an older source commit is ignored'); }

$release = [
    'draft'=>false,
    'prerelease'=>false,
    'tag_name'=>'v00.00.363',
    'html_url'=>'https://github.com/DiasMazhenov/juzimder-ai/releases/tag/v00.00.363',
    'published_at'=>'2026-11-05T09:00:00Z',
    'assets'=>[
        ['name'=>'juzimder-ai-shared-hosting.zip','state'=>'uploaded','browser_download_url'=>'https://github.com/DiasMazhenov/juzimder-ai/releases/download/v00.00.363/juzimder-ai-shared-hosting.zip','size'=>1000,'digest'=>'sha256:' . str_repeat('c',64)],
        ['name'=>'juzimder-ai-shared-hosting.zip.sha256','state'=>'uploaded','browser_download_url'=>'https://github.com/DiasMazhenov/juzimder-ai/releases/download/v00.00.363/juzimder-ai-shared-hosting.zip.sha256','size'=>80],
    ],
];
$releaseUpdater = $makeUpdater([$latestUrl=>updatesResponse(200, $release)]);
updatesCheck($releaseUpdater->check()['source'] === 'release', 'Stable Release remains the preferred source when versions are equal');

try { $makeUpdater()->install('00.00.363', 'repository', str_repeat('b', 40)); throw new RuntimeException('A stale source commit was accepted.'); }
catch (Problem $error) { updatesCheck($error->status === 409, 'Install requires the exact source commit shown by the check step'); }

$innerChecksum = hash('sha256', $packagePayload) . "  juzimder-ai-shared-hosting.zip\n";
$updater = $makeUpdater([
    $zipUrl=>updatesResponse(200, $packagePayload),
    $checksumUrl=>updatesResponse(200, $innerChecksum),
]);
$workspace = $root . '/workspace';
mkdir($workspace, 0700);
$archivePath = $workspace . '/bootstrap-compatible.zip';
$zipFixture = new ZipArchive();
if ($zipFixture->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Unable to create archive validation fixture.');
foreach ([
    'index.html'=>'<link rel="icon" href="/assets/favicon.png">',
    'assets/app.js'=>'fixture',
    'assets/favicon.png'=>'png-fixture',
    'router.php'=>'<?php',
    '.htaccess'=>'RewriteEngine On',
    'backend/api.php'=>'<?php',
    'release.json'=>'{"productVersion":"00.00.365"}',
] as $name=>$contents) $zipFixture->addFromString($name, $contents);
$zipFixture->close();
$extractMethod = new ReflectionMethod(AppUpdates::class, 'extractArchive');
$extractedPath = $workspace . '/extracted-compatible';
mkdir($extractedPath, 0700);
$extractMethod->invoke($updater, $archivePath, $extractedPath, '00.00.365');
updatesCheck(is_file($extractedPath . '/assets/favicon.png') && !is_file($extractedPath . '/favicon.png'), 'A hosting ZIP can carry the favicon under the legacy updater allowlisted assets directory');

$legacyRejectedArchive = $workspace . '/root-favicon.zip';
$zipFixture = new ZipArchive();
if ($zipFixture->open($legacyRejectedArchive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Unable to create legacy path fixture.');
foreach ([
    'index.html'=>'<link rel="icon" href="/favicon.png">',
    'assets/app.js'=>'fixture',
    'assets/favicon.png'=>'png-fixture',
    'favicon.png'=>'legacy-root-icon',
    'router.php'=>'<?php',
    '.htaccess'=>'RewriteEngine On',
    'backend/api.php'=>'<?php',
    'release.json'=>'{"productVersion":"00.00.365"}',
] as $name=>$contents) $zipFixture->addFromString($name, $contents);
$zipFixture->close();
$rejectedPath = $workspace . '/extracted-root-favicon';
mkdir($rejectedPath, 0700);
$rejectedRootFavicon = false;
try { $extractMethod->invoke($updater, $legacyRejectedArchive, $rejectedPath, '00.00.365'); }
catch (Problem $error) { $rejectedRootFavicon = $error->status === 422; }
updatesCheck($rejectedRootFavicon, 'A root-level favicon outside the legacy updater allowlist is rejected');

$method = new ReflectionMethod(AppUpdates::class, 'downloadRepositoryArchive');
$build = [
    'version'=>'00.00.363',
    'sourceCommit'=>$sourceSha,
    'runId'=>$runId,
    'runAttempt'=>1,
    'zipUrl'=>$zipUrl,
    'checksumUrl'=>$checksumUrl,
    'zipDigest'=>$packageDigest,
];
$path = $method->invoke($updater, $build, $workspace);
updatesCheck(file_get_contents($path) === $packagePayload, 'The hosting package is downloaded from the repository branch and checked against its manifest and checksum file');

$badChecksumUpdater = $makeUpdater([
    $zipUrl=>updatesResponse(200, $packagePayload),
    $checksumUrl=>updatesResponse(200, str_repeat('0', 64)),
]);
try { $method->invoke($badChecksumUpdater, $build, $workspace); throw new RuntimeException('A package with a mismatched repository checksum was accepted.'); }
catch (Problem $error) { updatesCheck($error->status === 502, 'A repository package with a mismatched checksum is rejected'); }

$requestMethod = new ReflectionMethod(AppUpdates::class, 'request');
try { $requestMethod->invoke($updater, 'https://attacker.example/package.zip', 1024, 'application/octet-stream', 0, false); throw new RuntimeException('An untrusted package host was accepted.'); }
catch (Problem $error) { updatesCheck($error->status === 502, 'Repository downloads are restricted to the explicit GitHub host allowlist'); }

echo "PASS updater repository source checks completed\n";
