<?php
declare(strict_types=1);
require __DIR__.'/../backend/Domain.php';
use Juzimder\{Document, ProjectSettings, Problem, Store};
function settingsCheck(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); echo "PASS $message\n"; }
function settingsReject(callable $fn,int $status): void { try {$fn();} catch (Problem $e) { settingsCheck($e->status===$status,'Reject '.$status); return; } throw new RuntimeException('Expected rejection '.$status); }
$path=sys_get_temp_dir().'/juzimder-migration-'.bin2hex(random_bytes(6)).'.sqlite';
try {
    $legacy=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $legacy->exec(file_get_contents(__DIR__.'/../backend/migrations/001_initial.sql'));
    $legacy->exec("INSERT INTO users(id,email,password_hash) VALUES('legacy','legacy@example.test','hash')");
    $document=Document::empty();
    $document['data']['pages'][]=['id'=>'about-page','name'=>'О компании','frames'=>[['component'=>['type'=>'wrapper','components'=>[['tagName'=>'section','attributes'=>['id'=>'legacy-section'],'components'=>[]]]]]]];
    $payload=json_encode($document,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
    $legacy->prepare('INSERT INTO projects(id,owner_id,name) VALUES(?,?,?)')->execute(['0123456789abcdef01234567','legacy','Legacy site']);
    $legacy->prepare('INSERT INTO documents(project_id,payload,version) VALUES(?,?,?)')->execute(['0123456789abcdef01234567',$payload,1]);
    $legacy->prepare('INSERT INTO revisions(project_id,version,payload) VALUES(?,?,?)')->execute(['0123456789abcdef01234567',1,$payload]);
    unset($legacy);

    $store=new Store($path);
    settingsCheck((int)$store->db->query('PRAGMA user_version')->fetchColumn()===15,'Upgrade existing database through schema 15');
    $project=$store->owned('0123456789abcdef01234567','legacy');
    settingsCheck($project['slug']==='legacy-site','Existing project receives a readable slug without changing its internal ID');
    settingsCheck($store->resolveProjectSlug('legacy-site','legacy')['id']===$project['id'],'Legacy slug resolves for the owning account');
    settingsCheck($project['settings']['schemaVersion']===10&&$project['settings']['notFound']===ProjectSettings::notFoundDefaults()&&$project['settings']['sharedSections']===ProjectSettings::sharedSectionDefaults(),'Project settings migrate to schema version 10 with safe not-found and shared-section defaults');
    settingsCheck($project['settings']['forms']===ProjectSettings::formDefaults(),'Form delivery settings migrate with safe disabled defaults');
    settingsCheck($project['settings']['designTokens']['fontFamily']==='Arial, sans-serif','Legacy project receives safe default design tokens');
    settingsCheck(count($project['document']->data->pages)===2,'Legacy page content preserved');
    settingsCheck($project['settings']['pages']['home']['isHome']===true,'First legacy page becomes home');
    settingsCheck($project['settings']['pages']['about-page']['slug']==='o-kompanii','Cyrillic legacy title gets stable slug');
    $schemaTwo=ProjectSettings::reconcile(['schemaVersion'=>2,'pages'=>['home'=>['slug'=>'home','isHome'=>true]],'designTokens'=>['fontFamily'=>'Georgia, serif']],$project['document']);
    settingsCheck($schemaTwo['schemaVersion']===10&&$schemaTwo['designTokens']['headingFontFamily']==='Georgia, serif'&&$schemaTwo['designTokens']['bodyFontFamily']==='Georgia, serif','Schema version 2 settings migrate while preserving the shared font family');
    settingsCheck($schemaTwo['pages']['home']['seo']===ProjectSettings::seoDefaults(),'Legacy pages receive safe default SEO metadata');
    $schemaThree=ProjectSettings::reconcile(['schemaVersion'=>3,'pages'=>['home'=>['slug'=>'home','isHome'=>true]],'designTokens'=>['fontFamily'=>'Georgia, serif']],$project['document']);
    settingsCheck($schemaThree['schemaVersion']===10&&$schemaThree['pages']['home']['seo']===ProjectSettings::seoDefaults(),'Schema version 3 adds SEO metadata defaults without losing existing routes');
    $schemaEight=ProjectSettings::reconcile(['schemaVersion'=>8,'pages'=>['home'=>['slug'=>'home','isHome'=>true]],'notFound'=>['title'=>'Missing','message'=>'Try again','homeLabel'=>'Home']],$project['document']);
    settingsCheck($schemaEight['schemaVersion']===10&&$schemaEight['notFound']['title']==='Missing'&&$schemaEight['notFound']['backgroundColor']===''&&$schemaEight['sharedSections']===ProjectSettings::sharedSectionDefaults(),'Schema version 8 preserves 404 copy and migrates shared-section defaults');
    $shared=ProjectSettings::validateSharedSections(['headerComponentId'=>'legacy-section','footerComponentId'=>'','pages'=>['home'=>['header'=>true,'footer'=>false]]],['home'],ProjectSettings::topLevelSectionIds($project['document']));
    settingsCheck($shared['headerComponentId']==='legacy-section'&&$shared['pages']['home']===['header'=>true,'footer'=>false],'Shared header/footer configuration accepts real top-level component IDs and per-page visibility');
    settingsReject(fn()=>ProjectSettings::validateSharedSections(['headerComponentId'=>'missing'],['home'],ProjectSettings::topLevelSectionIds($project['document'])),422);
    settingsReject(fn()=>ProjectSettings::validateSharedSections(['headerComponentId'=>'legacy-section','footerComponentId'=>'legacy-section'],['home'],ProjectSettings::topLevelSectionIds($project['document'])),422);
    settingsReject(fn()=>ProjectSettings::validateSharedSections(['pages'=>['other'=>['header'=>true]]],['home']),422);
    $notFound=ProjectSettings::validateNotFoundSettings(['title'=>'Не нашли','message'=>'Проверьте адрес','homeLabel'=>'На главную','backgroundColor'=>'#F0F0F0']);
    settingsCheck($notFound['title']==='Не нашли'&&$notFound['backgroundColor']==='#f0f0f0'&&$notFound['textColor']==='','404 settings accept bounded text, HEX colors and inherited site colors');
    settingsReject(fn()=>ProjectSettings::validateNotFoundSettings(['buttonColor'=>'url(javascript:alert(1))']),422);
    settingsReject(fn()=>ProjectSettings::validateNotFoundSettings(['message'=>str_repeat('x',401)]),422);
    settingsReject(fn()=>ProjectSettings::validateNotFoundSettings(['homeLabel'=>'   ']),422);
    $revisionSettings=$store->query('SELECT settings_payload FROM revisions WHERE project_id=? AND version=1',['0123456789abcdef01234567'])->fetchColumn();
    settingsCheck(is_string($revisionSettings)&&str_contains($revisionSettings,'about-page'),'Historical revisions receive route metadata');
    settingsCheck(ProjectSettings::validateSlug('/О нас')==='o-nas','Cyrillic and leading slash normalize');
    settingsReject(fn()=>ProjectSettings::validateSlug('api'),422);
    settingsReject(fn()=>ProjectSettings::validateSlug('nested/path'),422);
    $seo=ProjectSettings::validateSeoMetadata(['title'=>'  О компании  ','description'=>'Описание','robots'=>'noindex','socialImage'=>'https://example.test/social.jpg']);
    settingsCheck($seo['title']==='О компании'&&$seo['robots']==='noindex','SEO text is trimmed and robots directives are retained');
    settingsReject(fn()=>ProjectSettings::validateSeoMetadata(['robots'=>'follow']),422);
    settingsReject(fn()=>ProjectSettings::validateSeoMetadata(['socialImage'=>'http://example.test/social.jpg']),422);
    settingsReject(fn()=>ProjectSettings::validateSeoMetadata(['title'=>str_repeat('x',71)]),422);
    settingsReject(fn()=>ProjectSettings::validateSeoMetadata(['description'=>str_repeat('x',161)]),422);
    settingsCheck(ProjectSettings::validateCustomCss('.hero { color: #fff; margin: 0 auto; }')==='.hero{color: #fff; margin: 0 auto;}','Custom CSS accepts bounded class rules and normalizes them for export');
    settingsCheck(ProjectSettings::validateCustomCss('.card { box-sizing: border-box; min-height: 240px; font-family: Georgia, serif; line-height: 1.4; text-transform: uppercase; } img { object-fit: cover; } .grid { display: grid; grid-template-columns: 1fr 2fr; }')===".card{box-sizing: border-box; min-height: 240px; font-family: Georgia, serif; line-height: 1.4; text-transform: uppercase;}\nimg{object-fit: cover;}\n.grid{display: grid; grid-template-columns: 1fr 2fr;}",'Extended CSS accepts bounded typography, dimensions, image fitting and simple grid tracks');
    settingsReject(fn()=>ProjectSettings::validateCustomCss('.card { font-family: "External Font"; }'),422);
    settingsReject(fn()=>ProjectSettings::validateCustomCss('.grid { grid-template-columns: repeat(2, 1fr); }'),422);
    settingsReject(fn()=>ProjectSettings::validateCustomCss('#admin { color: red; }'),422);
    settingsReject(fn()=>ProjectSettings::validateCustomCss('.hero { background-color: red; }'),422);
    settingsReject(fn()=>ProjectSettings::validateCustomCss('.hero { background-color: #222629; background-image: url(https://example.test/x); }'),422);
    settingsCheck(ProjectSettings::validateCustomCss('.hero { display: flex; } @media (max-width: 600px) { .hero { display: block; } }')===".hero{display: flex;}\n@media (max-width: 600px){.hero{display: block;}}",'Bounded min/max-width media rules preserve safe responsive overrides');
    settingsCheck(ProjectSettings::validateCustomCss('.cta:hover { background-color: #61892f; cursor: pointer; } .field:focus-visible { outline: 2px solid #86c232; outline-offset: 2px; } button:disabled { opacity: 0.5; cursor: not-allowed; }')===".cta:hover{background-color: #61892f; cursor: pointer;}\n.field:focus-visible{outline: 2px solid #86c232; outline-offset: 2px;}\nbutton:disabled{opacity: 0.5; cursor: not-allowed;}",'Interactive and keyboard focus states accept only the bounded pseudo-class and outline rules');
    settingsReject(fn()=>ProjectSettings::validateCustomCss('.link:visited { color: #ffffff; }'),422);
    settingsReject(fn()=>ProjectSettings::validateCustomCss('.cta:hover { cursor: url(cursor.cur); }'),422);
    settingsReject(fn()=>ProjectSettings::validateCustomCss('@media (orientation: portrait) { .hero { display: block; } }'),422);
    settingsReject(fn()=>ProjectSettings::validateCustomCss('@media (max-width: 319px) { .hero { display: block; } }'),422);
    settingsReject(fn()=>ProjectSettings::validateCustomCss('@media (max-width: 640px) { @supports (display: grid) { .hero { display: grid; } } }'),422);
    settingsReject(fn()=>ProjectSettings::validateCustomCss('@media (max-width: 640px) { .hero { display: block; } } .hero { display: flex; }'),422);
    settingsReject(fn()=>ProjectSettings::validateCustomCss('.hero { position: fixed; }'),422);
    settingsReject(fn()=>ProjectSettings::validateCustomCss(str_repeat('x',12001)),422);

    $legacyDocument=Document::empty();
    $legacyDocument['data']['pages'][0]['frames'][0]['component']['style']=['--juzimder-color-forest'=>'#123456','--juzimder-font-family'=>'Georgia, serif'];
    $legacyDocument['data']['pages'][0]['frames'][0]['component']['components']=[['tagName'=>'a','style'=>['color'=>'#245b46','background-color'=>'#123456']]];
    $secondPage=$legacyDocument['data']['pages'][0];$secondPage['id']='about';$secondPage['name']='About';$legacyDocument['data']['pages'][]=$secondPage;
    $legacyObject=json_decode(json_encode($legacyDocument,JSON_THROW_ON_ERROR),false,64,JSON_THROW_ON_ERROR);
    $inferred=ProjectSettings::initial($legacyObject);
    settingsCheck($inferred['designTokens']['forest']==='#123456','Legacy root style seeds project design tokens');
    settingsCheck($inferred['designTokens']['headingFontFamily']==='Georgia, serif'&&$inferred['designTokens']['bodyFontFamily']==='Georgia, serif','Legacy shared font token migrates to both typography families');
    $tokens=ProjectSettings::validateDesignTokens(['forest'=>'#654321','radius'=>'12px'],$inferred['designTokens']);
    $styled=ProjectSettings::applyDesignTokensToDocument($legacyObject,$tokens);
    settingsCheck($styled->data->pages[0]->frames[0]->component->style->{'--juzimder-color-forest'}==='#654321'&&$styled->data->pages[1]->frames[0]->component->style->{'--juzimder-color-forest'}==='#654321','Project tokens reach every page root');
    settingsCheck($styled->data->pages[0]->frames[0]->component->style->{'--juzimder-font-heading'}==='Georgia, serif'&&$styled->data->pages[0]->frames[0]->component->style->{'font-family'}==='var(--juzimder-font-body, Arial, sans-serif)','Typography variables and body defaults reach the page root');
    settingsCheck($styled->data->pages[0]->frames[0]->component->components[0]->style->color==='var(--juzimder-color-forest)'&&$styled->data->pages[0]->frames[0]->component->components[0]->style->{'background-color'}==='#123456','Preset colors follow tokens while custom local colors remain overrides');
    settingsReject(fn()=>ProjectSettings::validateDesignTokens(['ink'=>'red']),422);
    settingsReject(fn()=>ProjectSettings::validateDesignTokens(['unknown'=>'#123456']),422);
    settingsReject(fn()=>ProjectSettings::validateDesignTokens(['fontSizeH1'=>'20px']),422);
    settingsReject(fn()=>ProjectSettings::validateDesignTokens(['headingFontWeight'=>'900']),422);

    $typography=ProjectSettings::validateDesignTokens([
        'headingFontFamily'=>'Georgia, serif','bodyFontFamily'=>'system-ui, sans-serif','headingFontWeight'=>'700','bodyFontWeight'=>'300',
        'baseFontSize'=>'18px','fontSizeH1'=>'72px','fontSizeH2'=>'42px','bodyLineHeight'=>'1.8','headingLineHeight'=>'1.3','letterSpacing'=>'0.5px',
    ]);
    $typographyDocument=Document::empty();
    $typographyDocument['data']['pages'][0]['frames'][0]['component']['components']=[
        ['tagName'=>'h1','style'=>['font-family'=>'Arial, sans-serif','font-size'=>'clamp(36px, 6vw, 76px)','font-weight'=>'700','line-height'=>'1.05']],
        ['tagName'=>'p','style'=>['font-family'=>'Arial, sans-serif','font-size'=>'18px','line-height'=>'1.6']],
        ['tagName'=>'h2','style'=>['font-size'=>'30px','font-weight'=>'800','line-height'=>'1.4']],
    ];
    $typed=ProjectSettings::applyDesignTokensToDocument(json_decode(json_encode($typographyDocument),false),$typography);
    $root=$typed->data->pages[0]->frames[0]->component;
    settingsCheck($root->style->{'--juzimder-font-size-h1'}==='clamp(28px, calc(18px + 2.35vw), 72px)'&&$root->style->{'--juzimder-line-height-body'}==='1.8','Responsive typography tokens are stored on the project page root');
    settingsCheck($root->components[0]->style->{'font-family'}==='var(--juzimder-font-heading, Arial, sans-serif)'&&$root->components[0]->style->{'font-size'}==='var(--juzimder-font-size-h1)'&&$root->components[0]->style->{'font-weight'}==='var(--juzimder-font-weight-heading, 500)'&&$root->components[0]->style->{'line-height'}==='var(--juzimder-line-height-heading, 1.15)','Known preset heading styles migrate to shared typography tokens');
    settingsCheck($root->style->{'font-weight'}==='var(--juzimder-font-weight-body, 400)'&&$root->components[1]->style->{'font-size'}==='var(--juzimder-font-size-base)'&&$root->components[1]->style->{'line-height'}==='var(--juzimder-line-height-body)','Known preset body styles and inherited weight use shared typography tokens');
    settingsCheck($root->components[2]->style->{'font-size'}==='30px'&&$root->components[2]->style->{'font-weight'}==='800','Custom local heading styles remain overrides');
} finally { unset($store); @unlink($path); }

$futureSchemaPath=sys_get_temp_dir().'/juzimder-future-schema-'.bin2hex(random_bytes(6)).'.sqlite';
try {
    $futureSchema=new PDO('sqlite:'.$futureSchemaPath,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $futureSchema->exec('PRAGMA user_version = 16');
    unset($futureSchema);
    $futureSchemaRejected=false;
    try { new Store($futureSchemaPath); } catch (RuntimeException $error) { $futureSchemaRejected=str_contains($error->getMessage(),'newer than this application'); }
    settingsCheck($futureSchemaRejected,'Migration runner refuses to open a database from a newer schema version');
} finally { unset($futureSchema); @unlink($futureSchemaPath); @unlink($futureSchemaPath.'-journal'); }

$profileMigrationPath=sys_get_temp_dir().'/juzimder-ai-profile-migration-'.bin2hex(random_bytes(6)).'.sqlite';
try {
    $legacyAi=new PDO('sqlite:'.$profileMigrationPath,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    foreach (['001_initial.sql','002_project_settings.sql','003_ai_credentials.sql','004_publications.sql'] as $migration) {
        $legacyAi->exec(file_get_contents(__DIR__.'/../backend/migrations/'.$migration));
    }
    $legacyAi->exec("INSERT INTO users(id,email,password_hash) VALUES('ai-legacy','ai-legacy@example.test','hash')");
    $legacyAi->exec("INSERT INTO projects(id,owner_id,name) VALUES('abcdef0123456789abcdef01','ai-legacy','Legacy AI')");
    $legacyAi->exec("INSERT INTO project_ai_credentials(project_id,api_key_ciphertext,model,version) VALUES('abcdef0123456789abcdef01','encrypted-legacy-fixture','openai/legacy-model',7)");
    unset($legacyAi);

    $migratedAi=new Store($profileMigrationPath);
    $profile=$migratedAi->query('SELECT id,name,api_key_ciphertext,model,fallback_model FROM project_ai_profiles WHERE project_id=?',['abcdef0123456789abcdef01'])->fetch();
    $profileState=$migratedAi->query('SELECT active_profile_id,version FROM project_ai_profile_state WHERE project_id=?',['abcdef0123456789abcdef01'])->fetch();
    settingsCheck((int)$migratedAi->db->query('PRAGMA user_version')->fetchColumn()===15,'Upgrade schema 4 through AI credentials, assets, skills, forms, slugs and mail vault');
    settingsCheck($profile!==false&&$profile['name']==='Основной профиль'&&$profile['api_key_ciphertext']==='encrypted-legacy-fixture'&&$profile['model']==='openai/legacy-model'&&$profile['fallback_model']===null,'Migration preserves the existing encrypted key and model and leaves fallback disabled');
    settingsCheck($profileState!==false&&$profileState['active_profile_id']===$profile['id']&&(int)$profileState['version']===7,'Migration preserves active selection and optimistic version');
    settingsCheck($migratedAi->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='project_ai_credentials'")->fetchColumn()===false,'Migration removes the obsolete single-profile credential table');
} finally { unset($migratedAi); @unlink($profileMigrationPath); @unlink($profileMigrationPath.'-journal'); }

settingsReject(fn()=>ProjectSettings::validateSeoMetadata(['socialTitle'=>str_repeat('x',71)]),422);
settingsReject(fn()=>ProjectSettings::validateSeoMetadata(['socialDescription'=>str_repeat('x',161)]),422);
