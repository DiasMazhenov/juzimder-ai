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
$settings['pages'][$homeId]['seo'] = ['title' => 'Home <Page>', 'description' => 'A useful & safe description', 'robots' => 'index', 'socialImage' => 'https://example.test/social.jpg', 'socialTitle'=>'Social <Title>', 'socialDescription'=>'Social & description'];
$settings['pages']['about']['seo'] = ['title' => 'About page', 'description' => '', 'robots' => 'noindex', 'socialImage' => ''];
$settings['designTokens']['ink'] = '#123456';
$settings['customCss'] = ProjectSettings::validateCustomCss('.hero { color: #ffffff; padding: 24px; } .card { box-sizing: border-box; min-height: 240px; font-family: Georgia, serif; line-height: 1.4; } img { object-fit: cover; } .cta:hover { background-color: #61892f; cursor: pointer; } @media (max-width: 640px) { .hero { padding: 16px; } }');
$project = ['name' => 'Export project', 'version' => 4, 'document' => $document, 'settings' => $settings];
$files = StaticSiteExporter::files($project);
exportCheck(str_contains($files['index.html'], 'property="og:title" content="Social &lt;Title&gt;"') && str_contains($files['index.html'], 'name="twitter:description" content="Social &amp; description"') && str_contains($files['index.html'], 'name="twitter:card" content="summary_large_image"'), 'Separate social metadata is escaped and exported as Open Graph and Twitter Card');
exportCheck(str_contains($files['o-nas/index.html'], 'property="og:title" content="About page"') && str_contains($files['o-nas/index.html'], 'name="twitter:card" content="summary"'), 'Older page social metadata falls back to SEO without an image');
exportCheck(isset($files['index.html'], $files['o-nas/index.html'], $files['404.html'], $files['assets/site.css'], $files['README.txt']), 'Static export contains home, routed pages, a not-found page, shared CSS and instructions');
exportCheck(str_contains($files['404.html'], '<title>Страница не найдена</title>') && str_contains($files['404.html'], 'href="/assets/site.css"') && str_contains($files['404.html'], 'href="/">Вернуться на главную</a>') && str_contains($files['assets/site.css'], '.jz-not-found{'), 'Project 404 document uses safe project CSS and a root-relative home link');
exportCheck(str_contains($files['index.html'], '<title>Home &lt;Page&gt;</title>') && str_contains($files['index.html'], 'name="description" content="A useful &amp; safe description"') && str_contains($files['index.html'], 'property="og:image"'), 'SEO metadata is escaped and included in page head');
exportCheck(str_contains($files['o-nas/index.html'], 'name="robots" content="noindex,follow"') && str_contains($files['o-nas/index.html'], 'href="../assets/site.css"'), 'Subpage route, relative stylesheet and robots directive are preserved');
exportCheck(str_contains($files['o-nas/index.html'], '<span style="color:#123456;font-weight:700">О проекте</span>'), 'Safe inline rich-text formatting is retained');
exportCheck(str_contains($files['assets/site.css'], '@media (max-width: 576px)') && str_contains($files['assets/site.css'], '#ij4p{color:#123456'), 'Responsive Project Data styles and design tokens reach static CSS');
exportCheck(str_contains($files['assets/site.css'], '.hero{color: #ffffff; padding: 24px;}'), 'Validated project CSS is appended to the static site stylesheet');
exportCheck(str_contains($files['assets/site.css'], '.card{box-sizing: border-box; min-height: 240px; font-family: Georgia, serif; line-height: 1.4;}') && str_contains($files['assets/site.css'], 'img{object-fit: cover;}'), 'Safe typography, element sizing and image fitting CSS reach the exported site');
exportCheck(str_contains($files['assets/site.css'], '.cta:hover{background-color: #61892f; cursor: pointer;}'), 'Validated interaction states reach static CSS');
exportCheck(str_contains($files['assets/site.css'], '@media (max-width: 640px){.hero{padding: 16px;}}'), 'Validated responsive project CSS is included in the static stylesheet');
exportCheck(str_contains($files['index.html'], 'http-equiv="Content-Security-Policy"') && !preg_match('/<script\b|onerror\s*=|javascript:/i', implode("\n", $files)), 'Static output applies a restrictive CSP and contains no executable markup');

$captionProject = $project;
$captionProject['document'] = json_decode(json_encode($project['document'], JSON_THROW_ON_ERROR), false, 64, JSON_THROW_ON_ERROR);
$captionProject['document']->data->pages[0]->frames[0]->component->components[] = (object)['classes'=>['ai-image-slot'],'style'=>(object)['position'=>'relative'],'components'=>[(object)['tagName'=>'p','classes'=>['ai-photo-credit'],'style'=>(object)['position'=>'absolute','left'=>'0px','right'=>'0px','bottom'=>'0px','font-size'=>'8px'],'components'=>[(object)['type'=>'textnode','content'=>'Photo credit']]]]];
$captionFiles = StaticSiteExporter::files($captionProject);
exportCheck(str_contains($captionFiles['index.html'],'position:absolute;left:0px;right:0px;bottom:0px;font-size:8px'), 'Photo caption offsets and small text survive saved HTML export');

$mainPhotoProject=$project;
$mainPhotoProject['document']=json_decode(json_encode($project['document'],JSON_THROW_ON_ERROR),false,64,JSON_THROW_ON_ERROR);
$mainPhotoProject['document']->data->pages[0]->frames[0]->component->components[]=(object)['type'=>'wrapper','tagName'=>'figure','name'=>'Фото','style'=>(object)['display'=>'inline-flex','flex-direction'=>'column','margin'=>'0'],'components'=>[(object)['type'=>'image','tagName'=>'img','attributes'=>(object)['src'=>'https://example.test/photo.jpg','alt'=>'A portrait'],'style'=>(object)['display'=>'block','width'=>'100%']],(object)['type'=>'text','tagName'=>'figcaption','name'=>'Копирайт','content'=>'Photo & Author · CC BY-SA 4.0','style'=>(object)['font-size'=>'8px','line-height'=>'1.2','margin-top'=>'2px','width'=>'100%','text-align'=>'left','color'=>'inherit']]]];
Document::validate($mainPhotoProject['document']);
$mainPhotoHtml=StaticSiteExporter::files($mainPhotoProject)['index.html'];
exportCheck(str_contains($mainPhotoHtml,'<figure')&&str_contains($mainPhotoHtml,'<figcaption style="font-size:8px;line-height:1.2;margin-top:2px;width:100%;text-align:left;color:inherit">Photo &amp; Author · CC BY-SA 4.0</figcaption>'),'Main-editor photo credit is exported as escaped 8px text below its image');

$filterProject=$project;
$filterProject['document']=json_decode(json_encode($project['document'],JSON_THROW_ON_ERROR),false,64,JSON_THROW_ON_ERROR);
$filterProject['document']->data->pages[0]->frames[0]->component->components[]=(object)['tagName'=>'div','style'=>(object)['filter'=>'blur(2px) grayscale(50%)'],'components'=>[]];
exportCheck(str_contains(StaticSiteExporter::files($filterProject)['index.html'],'filter:blur(2px) grayscale(50%)'),'Native filter style survives saved page export');

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

$iconDoc = Document::empty();
foreach (\Juzimder\IconCatalogue::all() as $key=>$uri) {
    $iconDoc['data']['pages'][0]['frames'][0]['component']['components'][] = ['type'=>'image','tagName'=>'img','attributes'=>['src'=>$uri,'alt'=>'']];
}
Document::validate($iconDoc);
$iconFiles = StaticSiteExporter::files(['id'=>str_repeat('a',24),'name'=>'Icons','document'=>json_decode(json_encode($iconDoc))]);
foreach (\Juzimder\IconCatalogue::all() as $uri) exportCheck(str_contains($iconFiles['index.html'],$uri),'Trusted icon survives static export with embedded MIT notice');
$badIconDoc=$iconDoc;
$badIconDoc['data']['pages'][0]['frames'][0]['component']['components'][0]['attributes']['src'].='x';
exportRejects(fn()=>Document::validate($badIconDoc),422,'Altered icon SVG rejected');

// Native Core serializes imported class rules into selectorsAdd and classes separately.
$nativeDoc = Document::empty();
$nativeDoc['data']['pages'][0]['frames'][0]['component']['components'] = [['tagName'=>'section','classes'=>['ai-scope-native','ai-card'],'attributes'=>['class'=>'existing'],'components'=>[['type'=>'text','tagName'=>'h2','classes'=>[(object)['name'=>'ai-title']],'content'=>'Native class QA']]]];
$nativeDoc['data']['styles'] = [
 ['selectors'=>[],'selectorsAdd'=>'.ai-scope-native.ai-card','style'=>['display'=>'grid','row-gap'=>'24px','border-top-left-radius'=>'18px','box-shadow'=>'0 4px 20px #00000022']],
 ['selectors'=>[],'selectorsAdd'=>'.ai-scope-native h2, .ai-scope-native .ai-title','style'=>['color'=>'#123456','font-size'=>'40px']],
 ['selectors'=>[(object)['name'=>'ai-title','type'=>1],(object)['name'=>'highlight','type'=>1]],'state'=>'hover','style'=>['color'=>'#abcdef']],
 ['selectors'=>[],'selectorsAdd'=>'.ai-scope-native h2','mediaText'=>'(max-width: 768px)','style'=>['font-size'=>'28px']],
 ['selectors'=>[],'selectorsAdd'=>'body{color:red} .evil, .safe','style'=>['color'=>'#ffffff']],
];
$nativeFiles = StaticSiteExporter::files(['name'=>'Native CSS','document'=>json_decode(json_encode($nativeDoc))]);
exportCheck(str_contains($nativeFiles['index.html'],'class="existing ai-scope-native ai-card"') && str_contains($nativeFiles['index.html'],'class="ai-title"'),'Core class arrays and selector objects become HTML classes without mutating Project Data');
exportCheck(str_contains($nativeFiles['assets/site.css'],'.ai-scope-native.ai-card{display:grid;row-gap:24px;border-top-left-radius:18px;box-shadow:'),'selectorsAdd preserves compound selectors and expanded Core properties');
exportCheck(str_contains($nativeFiles['assets/site.css'],'.ai-scope-native h2,.ai-scope-native .ai-title{color:#123456;font-size:40px}'),'Scoped descendant tags and selector lists reach CSS');
exportCheck(str_contains($nativeFiles['assets/site.css'],'.ai-title.highlight:hover{color:#abcdef}'),'Native selector collections concatenate rather than becoming a comma list');
exportCheck(str_contains($nativeFiles['assets/site.css'],'@media (max-width: 768px){.ai-scope-native h2{font-size:28px}}'),'Responsive native selectorsAdd rules survive export');
exportCheck(!str_contains($nativeFiles['assets/site.css'],'body{color:red}') && str_contains($nativeFiles['assets/site.css'],'.safe{color:#ffffff}'),'Invalid selector fragments cannot inject stylesheet text');

$sandboxProject=$project;
$sandboxProject['document']=json_decode(json_encode($project['document'],JSON_THROW_ON_ERROR),false,64,JSON_THROW_ON_ERROR);
$sandboxProject['document']->data->pages[0]->frames[0]->component->components[]=(object)['type'=>'custom-code','tagName'=>'div','name'=>'Sandbox QA','content'=>\Juzimder\CustomCode::encode(['html'=>'<h2>Isolated preview</h2>','css'=>'h2{color:#123456}','js'=>'document.body.dataset.ready="yes";const escaped="</script>";'])];
$sandboxFiles=StaticSiteExporter::files($sandboxProject);
$sandboxHtml=$sandboxFiles['index.html'];
$nonce=StaticSiteExporter::runtimeNonce($sandboxHtml);
exportCheck(is_string($nonce)&&preg_match('~script-src &#39;nonce-' . preg_quote($nonce,'~') . '&#39;~',$sandboxHtml)===1,'Custom Code export grants only a per-document script nonce');
exportCheck(str_contains($sandboxHtml,'sandbox="allow-scripts"')&&str_contains($sandboxHtml,'srcdoc="&lt;!doctype html'),'Exported user code is nested in an opaque sandboxed iframe');
exportCheck(str_contains($sandboxHtml,'connect-src &#39;none&#39;')&&str_contains($sandboxHtml,'frame-src &#39;self&#39; data:'),'Custom Code output keeps the page network policy closed');
exportCheck(!preg_match('~<script\b|</script>~i',$sandboxHtml),'User JavaScript is base64-transported and never becomes a script in the parent document');
exportCheck(!str_contains(StaticSiteExporter::pageSecurityPolicy($sandboxHtml),'sandbox '),'Preview/public headers must not sandbox the complete page');
$countdownProject=$project;
$countdownProject['document']=json_decode(json_encode($project['document'],JSON_THROW_ON_ERROR),false,64,JSON_THROW_ON_ERROR);
$countdownProject['document']->data->pages[0]->frames[0]->component->components[]=(object)['type'=>'countdown','tagName'=>'div','attributes'=>(object)['data-countdown-end'=>'2027-01-01T00:00:00Z']];
$countdownHtml=StaticSiteExporter::files($countdownProject)['index.html'];
exportCheck(str_contains($countdownHtml,'Обратный отсчёт')&&str_contains($countdownHtml,'sandbox="allow-scripts"'),'Countdown exports a live isolated timer instead of a static label');
$zeroCodeProject=$project;
$zeroCodeProject['document']=json_decode(json_encode($project['document'],JSON_THROW_ON_ERROR),false,64,JSON_THROW_ON_ERROR);
$zeroCodeData=json_decode(file_get_contents(__DIR__.'/zero.fixture.json'),false,64,JSON_THROW_ON_ERROR);
$zeroCodeElement=clone $zeroCodeData->elements[0];$zeroCodeElement->id='ze-static-html';$zeroCodeElement->type='html';$zeroCodeElement->name='Isolated Zero HTML';$zeroCodeElement->code=(object)['html'=>'<h2>Isolated Zero HTML</h2>','css'=>'h2{color:#123456}','js'=>'document.body.dataset.ready="yes";'];$zeroCodeData->elements=[$zeroCodeElement];
$zeroBlock=(object)['type'=>'zero-block','attributes'=>(object)['id'=>'zero-html-export'],'zeroData'=>$zeroCodeData,'components'=>[]];
$zeroCodeProject['document']->data->pages[0]->frames[0]->component->components[]=$zeroBlock;
$zeroCodeFiles=StaticSiteExporter::files($zeroCodeProject);$zeroCodePage=$zeroCodeFiles['index.html'];$zeroCodeNonce=StaticSiteExporter::runtimeNonce($zeroCodePage);
exportCheck(is_string($zeroCodeNonce)&&str_contains($zeroCodePage,'sandbox="allow-scripts"')&&str_contains($zeroCodePage,'srcdoc="&lt;!doctype html'),'Nested Zero HTML uses an opaque iframe in static export');
exportCheck(str_contains($zeroCodePage,'script-src &#39;nonce-'.$zeroCodeNonce.'&#39;')&&str_contains(StaticSiteExporter::pageSecurityPolicy($zeroCodePage),"frame-src 'self' data:")&&!preg_match('~<script\b|</script>~i',$zeroCodePage),'Static page CSP grants only the frame nonce and never emits user JS into its own DOM');
$zeroVideoProject=$project;
$zeroVideoProject['document']=json_decode(json_encode($project['document'],JSON_THROW_ON_ERROR),false,64,JSON_THROW_ON_ERROR);
$zeroVideoData=json_decode(file_get_contents(__DIR__.'/zero.fixture.json'),false,64,JSON_THROW_ON_ERROR);
$zeroVideo=clone $zeroVideoData->elements[0];$zeroVideo->id='ze-static-video';$zeroVideo->type='video';$zeroVideo->name='Safe video';$zeroVideo->videoUrl='https://youtu.be/dQw4w9WgXcQ';$zeroVideoData->elements=[$zeroVideo];
$zeroVideoBlock=(object)['type'=>'zero-block','attributes'=>(object)['id'=>'zero-video-export'],'zeroData'=>$zeroVideoData,'components'=>[]];
$zeroVideoProject['document']->data->pages[0]->frames[0]->component->components[]=$zeroVideoBlock;
$zeroVideoPage=StaticSiteExporter::files($zeroVideoProject)['index.html'];$zeroVideoPolicy=StaticSiteExporter::pageSecurityPolicy($zeroVideoPage);
exportCheck(str_contains($zeroVideoPage,'sandbox="allow-scripts allow-same-origin allow-presentation"')&&str_contains($zeroVideoPage,'src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"')&&str_contains($zeroVideoPage,'referrerpolicy="no-referrer"'),'Zero Video is rendered in a sandboxed privacy-enhanced iframe without referrer disclosure in static output');
exportCheck(str_contains($zeroVideoPage,'frame-src https://www.youtube-nocookie.com https://player.vimeo.com'),'Static HTML CSP allows only the two video embed origins');
exportCheck(str_contains($zeroVideoPolicy,'frame-src https://www.youtube-nocookie.com https://player.vimeo.com')&&!preg_match('~frame-src\s+https:($|;)~',$zeroVideoPolicy),'Preview CSP allows only the two video embed origins');
exportCheck(!str_contains(StaticSiteExporter::pageSecurityPolicy($zeroCodePage),'youtube-nocookie.com')&&!str_contains(StaticSiteExporter::pageSecurityPolicy($zeroCodePage),'player.vimeo.com'),'Pages without Zero Video do not receive external frame permissions');
exportRejects(fn()=>\Juzimder\CustomCode::countdownPayload('2027-13-99T99:99:99Z'),422,'Countdown rejects invalid dates');
exportRejects(fn()=>\Juzimder\CustomCode::decode(\Juzimder\CustomCode::encode(['html'=>'','css'=>'</style><script>bad</script>','js'=>''])),422,'Custom Code CSS cannot escape its style boundary');

$zeroFormProject=$project;$zeroFormProject['document']=json_decode(json_encode($project['document'],JSON_THROW_ON_ERROR),false,64,JSON_THROW_ON_ERROR);$zeroFormData=json_decode(file_get_contents(__DIR__.'/zero.fixture.json'),false,64,JSON_THROW_ON_ERROR);$zeroForm=clone $zeroFormData->elements[0];$zeroForm->id='ze-static-form';$zeroForm->type='form';$zeroForm->name='Contact';$zeroForm->submitLabel='Send';$zeroForm->formFields=[(object)['id'=>'email','type'=>'email','label'=>'Email','placeholder'=>'name@example.test','required'=>true]];$zeroFormData->elements=[$zeroForm];$zeroFormBlock=(object)['type'=>'zero-block','attributes'=>(object)['id'=>'zero-form-export'],'zeroData'=>$zeroFormData,'components'=>[]];$zeroFormProject['document']->data->pages[0]->frames[0]->component->components[]=$zeroFormBlock;$zeroFormPage=StaticSiteExporter::files($zeroFormProject)['index.html'];$zeroFormPolicy=StaticSiteExporter::pageSecurityPolicy($zeroFormPage);exportCheck(str_contains($zeroFormPage,'role="group" aria-label="Contact"')&&str_contains($zeroFormPage,'readonly aria-readonly="true"')&&str_contains($zeroFormPage,'type="button" disabled aria-disabled="true"'),'Zero Form exports a typed inert layout without submission');exportCheck(str_contains($zeroFormPolicy,"form-action 'none'")&&!preg_match('~<form\\b|action=~',$zeroFormPage),'Zero Form retains the closed form CSP and emits no form action');
$nativeDoc['data']['pages'][0]['frames'][0]['component']['classes']=['page-theme'];
$rootFiles=StaticSiteExporter::files(['name'=>'Root CSS','document'=>json_decode(json_encode($nativeDoc))]);
exportCheck(str_contains($rootFiles['index.html'],'<body class="page-theme"'),'Native wrapper classes also reach the exported body');

$submittedFormProject=$project;
$submittedFormProject['document']=json_decode(json_encode($project['document'],JSON_THROW_ON_ERROR),false,64,JSON_THROW_ON_ERROR);
$submittedForm=(object)['type'=>'form','tagName'=>'form','attributes'=>(object)['id'=>'contact-form'],'components'=>[
    (object)['type'=>'input','tagName'=>'input','attributes'=>(object)['type'=>'email','name'=>'email','required'=>true]],
    (object)['type'=>'button','tagName'=>'button','attributes'=>(object)['type'=>'submit'],'components'=>[(object)['type'=>'textnode','content'=>'Send']]],
]];
$submittedFormProject['document']->data->pages[0]->frames[0]->component->components[]=$submittedForm;
$submittedFormFiles=StaticSiteExporter::files($submittedFormProject);
$submittedFormHtml=$submittedFormFiles['index.html'];
$submittedFormPolicy=StaticSiteExporter::pageSecurityPolicy($submittedFormHtml);
exportCheck(isset($submittedFormFiles['forms.json'])&&str_contains($submittedFormHtml,'data-jz-form-id=')&&str_contains($submittedFormHtml,'data-jz-form-status')&&str_contains($submittedFormHtml,"fetch('/api/public/forms"),'Main editor form exports with published inbox runtime and a bounded manifest');
exportCheck(str_contains($submittedFormPolicy,"script-src 'nonce-")&&str_contains($submittedFormPolicy,"connect-src 'self'")&&str_contains($submittedFormPolicy,"form-action 'self'"),'Published form CSP allows only its nonce script and same-origin submission endpoint');
exportCheck(!str_contains(StaticSiteExporter::pageSecurityPolicy($rootFiles['index.html']),"connect-src 'self'"),'Pages without forms keep external connection permission closed');
