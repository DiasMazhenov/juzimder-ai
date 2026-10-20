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
    settingsCheck((int)$store->db->query('PRAGMA user_version')->fetchColumn()===2,'Upgrade existing database to schema 2');
    $project=$store->owned('0123456789abcdef01234567','legacy');
    settingsCheck($project['settings']['schemaVersion']===2,'Project settings migrate to schema version 2');
    settingsCheck($project['settings']['designTokens']['fontFamily']==='Arial, sans-serif','Legacy project receives safe default design tokens');
    settingsCheck(count($project['document']->data->pages)===2,'Legacy page content preserved');
    settingsCheck($project['settings']['pages']['home']['isHome']===true,'First legacy page becomes home');
    settingsCheck($project['settings']['pages']['about-page']['slug']==='o-kompanii','Cyrillic legacy title gets stable slug');
    $revisionSettings=$store->query('SELECT settings_payload FROM revisions WHERE project_id=? AND version=1',['0123456789abcdef01234567'])->fetchColumn();
    settingsCheck(is_string($revisionSettings)&&str_contains($revisionSettings,'about-page'),'Historical revisions receive route metadata');
    settingsCheck(ProjectSettings::validateSlug('/О нас')==='o-nas','Cyrillic and leading slash normalize');
    settingsReject(fn()=>ProjectSettings::validateSlug('api'),422);
    settingsReject(fn()=>ProjectSettings::validateSlug('nested/path'),422);

    $legacyDocument=Document::empty();
    $legacyDocument['data']['pages'][0]['frames'][0]['component']['style']=['--juzimder-color-forest'=>'#123456'];
    $legacyDocument['data']['pages'][0]['frames'][0]['component']['components']=[['tagName'=>'a','style'=>['color'=>'#245b46','background-color'=>'#123456']]];
    $secondPage=$legacyDocument['data']['pages'][0];$secondPage['id']='about';$secondPage['name']='About';$legacyDocument['data']['pages'][]=$secondPage;
    $legacyObject=json_decode(json_encode($legacyDocument,JSON_THROW_ON_ERROR),false,64,JSON_THROW_ON_ERROR);
    $inferred=ProjectSettings::initial($legacyObject);
    settingsCheck($inferred['designTokens']['forest']==='#123456','Legacy root style seeds project design tokens');
    $tokens=ProjectSettings::validateDesignTokens(['forest'=>'#654321','radius'=>'12px'],$inferred['designTokens']);
    $styled=ProjectSettings::applyDesignTokensToDocument($legacyObject,$tokens);
    settingsCheck($styled->data->pages[0]->frames[0]->component->style->{'--juzimder-color-forest'}==='#654321'&&$styled->data->pages[1]->frames[0]->component->style->{'--juzimder-color-forest'}==='#654321','Project tokens reach every page root');
    settingsCheck($styled->data->pages[0]->frames[0]->component->components[0]->style->color==='var(--juzimder-color-forest)'&&$styled->data->pages[0]->frames[0]->component->components[0]->style->{'background-color'}==='#123456','Preset colors follow tokens while custom local colors remain overrides');
    settingsReject(fn()=>ProjectSettings::validateDesignTokens(['ink'=>'red']),422);
    settingsReject(fn()=>ProjectSettings::validateDesignTokens(['unknown'=>'#123456']),422);
} finally { unset($store); @unlink($path); }
