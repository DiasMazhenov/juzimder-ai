<?php
declare(strict_types=1);
require __DIR__ . '/../backend/Domain.php';
require __DIR__ . '/../backend/StaticSiteExporter.php';
use Juzimder\{Document, Problem, ProjectSettings, StaticSiteExporter};

function exportCheck(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS $message\n";
}
function exportRejects(callable $callback, int $status, string $message): void {
    try { $callback(); } catch (Problem $problem) {
        exportCheck($problem->status === $status, $message);
        return;
    }
    throw new RuntimeException('Expected export failure: ' . $message);
}

$document = json_decode(file_get_contents(__DIR__ . '/grapesjs-0.23.8.fixture.json'), false, 64, JSON_THROW_ON_ERROR);
Document::validate($document);
$document->data->styles[] = (object)[
    'selectors' => ['#ij4p'],
    'style' => (object)['color' => '#123456', 'font-size' => 'clamp(28px, calc(18px + 2.35vw), 72px)'],
    'mediaText' => '(max-width: 576px)',
];
$about = json_decode(json_encode($document->data->pages[0], JSON_THROW_ON_ERROR), false, 64, JSON_THROW_ON_ERROR);
$about->id = 'about';
$about->name = 'О нас';
$about->frames[0]->component->components = [(object)[
    'type' => 'text', 'tagName' => 'h1', 'content' => '<span style="color:#123456;font-weight:700">О проекте</span>', 'attributes' => (object)['id' => 'about-title'],
]];
$document->data->pages[] = $about;
$settings = ProjectSettings::initial($document);
$homeId = (string)$document->data->pages[0]->id;
$settings['pages'][$homeId]['seo'] = ['title' => 'Home <Page>', 'description' => 'A useful & safe description', 'robots' => 'index', 'socialImage' => 'https://example.test/social.jpg'];
$settings['pages']['about']['seo'] = ['title' => 'About page', 'description' => '', 'robots' => 'noindex', 'socialImage' => ''];
$settings['designTokens']['ink'] = '#123456';
$project = ['name' => 'Export project', 'version' => 4, 'document' => $document, 'settings' => $settings];
$files = StaticSiteExporter::files($project);
exportCheck(isset($files['index.html'], $files['o-nas/index.html'], $files['assets/site.css'], $files['README.txt']), 'Static export contains home, routed pages, shared CSS and instructions');
exportCheck(str_contains($files['index.html'], '<title>Home &lt;Page&gt;</title>') && str_contains($files['index.html'], 'name="description" content="A useful &amp; safe description"') && str_contains($files['index.html'], 'property="og:image"'), 'SEO metadata is escaped and included in page head');
exportCheck(str_contains($files['o-nas/index.html'], 'name="robots" content="noindex,follow"') && str_contains($files['o-nas/index.html'], 'href="../assets/site.css"'), 'Subpage route, relative stylesheet and robots directive are preserved');
exportCheck(str_contains($files['o-nas/index.html'], '<span style="color:#123456;font-weight:700">О проекте</span>'), 'Safe inline rich-text formatting is retained');
exportCheck(str_contains($files['assets/site.css'], '@media (max-width: 576px)') && str_contains($files['assets/site.css'], '#ij4p{color:#123456'), 'Responsive Project Data styles and design tokens reach static CSS');
exportCheck(str_contains($files['index.html'], 'http-equiv="Content-Security-Policy"') && !preg_match('/<script\b|onerror\s*=|javascript:/i', implode("\n", $files)), 'Static output applies a restrictive CSP and contains no executable markup');

$assetProject = $project;
$assetProject['id'] = str_repeat('a', 24);
$assetId = str_repeat('b', 24);
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j6J8AAAAASUVORK5CYII=', true);
if (!is_string($png)) throw new RuntimeException('Could not prepare PNG fixture.');
$assetProject['document'] = json_decode(json_encode($assetProject['document'], JSON_THROW_ON_ERROR), false, 64, JSON_THROW_ON_ERROR);
$assetProject['document']->data->pages[0]->frames[0]->component->components[] = (object)['type'=>'image','tagName'=>'img','attributes'=>(object)['src'=>'/api/projects/' . $assetProject['id'] . '/assets/' . $assetId . '/file','alt'=>'Managed PNG']];
$assetFiles = StaticSiteExporter::files($assetProject, [$assetId=>['id'=>$assetId,'mime'=>'image/png','content'=>$png]]);
exportCheck($assetFiles['media/' . $assetProject['id'] . '/' . $assetId . '.png'] === $png && str_contains($assetFiles['index.html'], 'src="media/' . $assetProject['id'] . '/' . $assetId . '.png"'), 'Static export copies referenced raster assets and rewrites their scoped source');
$previewHtml = StaticSiteExporter::inlineAssets($assetFiles['index.html'], [$assetId=>['mime'=>'image/png','content'=>$png]], $assetProject['id'], true);
exportCheck(str_contains($previewHtml, 'src="data:image/png;base64,' . base64_encode($png) . '"'), 'Sandboxed saved-candidate preview embeds private managed assets');
$foreignReference = json_decode(json_encode($assetProject, JSON_THROW_ON_ERROR), false, 64, JSON_THROW_ON_ERROR);
$foreignReference->document->data->pages[0]->frames[0]->component->components[0]->attributes->src = '/api/projects/' . str_repeat('c', 24) . '/assets/' . $assetId . '/file';
exportRejects(fn() => StaticSiteExporter::files((array)$foreignReference, [$assetId=>['id'=>$assetId,'mime'=>'image/png','content'=>$png]]), 422, 'Static renderer refuses cross-project managed asset references');

$unsafeStyle = json_decode(json_encode($project, JSON_THROW_ON_ERROR), false, 64, JSON_THROW_ON_ERROR);
$unsafeStyle->document->data->styles[] = (object)['selectors' => ['#ij4p'], 'style' => (object)['color' => 'red } body{display:block']];
$unsafeFiles = StaticSiteExporter::files((array)$unsafeStyle);
exportCheck(!str_contains($unsafeFiles['assets/site.css'], 'red } body'), 'Unsafe CSS declaration text is omitted');
$badMedia = json_decode(json_encode($project, JSON_THROW_ON_ERROR), false, 64, JSON_THROW_ON_ERROR);
$badMedia->document->data->styles[] = (object)['selectors' => ['#ij4p'], 'style' => (object)['color' => '#ffffff'], 'mediaText' => '){}body{display:block'];
exportRejects(fn() => StaticSiteExporter::files((array)$badMedia), 422, 'Untrusted media query syntax is rejected');

$zipPath = tempnam(sys_get_temp_dir(), 'juzimder-export-test-');
if (!is_string($zipPath)) throw new RuntimeException('Could not create ZIP test file.');
try {
    file_put_contents($zipPath, StaticSiteExporter::zip($project));
    $archive = new ZipArchive();
    exportCheck($archive->open($zipPath) === true, 'Static export is a valid ZIP archive');
    exportCheck($archive->locateName('index.html') !== false && $archive->locateName('o-nas/index.html') !== false && $archive->locateName('assets/site.css') !== false, 'ZIP preserves static export paths');
    $archive->close();
} finally { @unlink($zipPath); }
