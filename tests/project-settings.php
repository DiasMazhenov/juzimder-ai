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
    $document['data']['pages'][]=['id'=>'about-page','name'=>'О компании','frames'=>[['component'=>['type'=>'wrapper','components'=>[]]]]];
    $payload=json_encode($document,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
    $legacy->prepare('INSERT INTO projects(id,owner_id,name) VALUES(?,?,?)')->execute(['0123456789abcdef01234567','legacy','Legacy site']);
    $legacy->prepare('INSERT INTO documents(project_id,payload,version) VALUES(?,?,?)')->execute(['0123456789abcdef01234567',$payload,1]);
    $legacy->prepare('INSERT INTO revisions(project_id,version,payload) VALUES(?,?,?)')->execute(['0123456789abcdef01234567',1,$payload]);
    unset($legacy);

    $store=new Store($path);
    settingsCheck((int)$store->db->query('PRAGMA user_version')->fetchColumn()===3,'Upgrade existing database through schema 3');
    $project=$store->owned('0123456789abcdef01234567','legacy');
    settingsCheck($project['settings']['schemaVersion']===3,'Project settings migrate to schema version 3');
    settingsCheck($project['settings']['designTokens']['fontFamily']==='Arial, sans-serif','Legacy project receives safe default design tokens');
    settingsCheck(count($project['document']->data->pages)===2,'Legacy page content preserved');
    settingsCheck($project['settings']['pages']['home']['isHome']===true,'First legacy page becomes home');
    settingsCheck($project['settings']['pages']['about-page']['slug']==='o-kompanii','Cyrillic legacy title gets stable slug');
    $schemaTwo=ProjectSettings::reconcile(['schemaVersion'=>2,'pages'=>['home'=>['slug'=>'home','isHome'=>true]],'designTokens'=>['fontFamily'=>'Georgia, serif']],$project['document']);
    settingsCheck($schemaTwo['schemaVersion']===3&&$schemaTwo['designTokens']['headingFontFamily']==='Georgia, serif'&&$schemaTwo['designTokens']['bodyFontFamily']==='Georgia, serif','Schema version 2 font settings migrate without losing the shared family');
    $revisionSettings=$store->query('SELECT settings_payload FROM revisions WHERE project_id=? AND version=1',['0123456789abcdef01234567'])->fetchColumn();
    settingsCheck(is_string($revisionSettings)&&str_contains($revisionSettings,'about-page'),'Historical revisions receive route metadata');
    settingsCheck(ProjectSettings::validateSlug('/О нас')==='o-nas','Cyrillic and leading slash normalize');
    settingsReject(fn()=>ProjectSettings::validateSlug('api'),422);
    settingsReject(fn()=>ProjectSettings::validateSlug('nested/path'),422);

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
