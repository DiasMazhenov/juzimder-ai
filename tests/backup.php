<?php
declare(strict_types=1);
require_once __DIR__.'/../backend/Domain.php';
use Juzimder\{Document, Problem, ProjectSettings, Store};
function backupCheck(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); echo "PASS $message\n"; }
function backupReject(callable $fn,int $status): void { try {$fn();} catch (Problem $error) { backupCheck($error->status===$status,'Reject backup operation with '.$status); return; } throw new RuntimeException('Expected backup rejection '.$status); }
$path=sys_get_temp_dir().'/juzimder-backup-'.bin2hex(random_bytes(6)).'.sqlite';
try {
    $store=new Store($path);$store->query('INSERT INTO users(id,email,password_hash) VALUES(?,?,?)',['backup-user','backup@example.test','hash']);
    $project=$store->create('backup-user','Backup QA');$id=$project['id'];
    $store->query('INSERT INTO project_ai_skills(id,project_id,name,content,manual_checks_json) VALUES(?,?,?,?,?)',[str_repeat('a',24),$id,'Backup skill','Use one H1.','["Check the page hierarchy."]']);
    $store->query('INSERT INTO project_ai_skill_state(project_id,active_skill_id,version) VALUES(?,?,?)',[$id,str_repeat('a',24),1]);
    $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j7KcAAAAASUVORK5CYII=',true);
    $asset=$store->uploadAsset($id,'backup-user','pixel.png',$png);
    $document=json_decode(json_encode($project['document'],JSON_THROW_ON_ERROR),false,64,JSON_THROW_ON_ERROR);
    $document->data->pages[0]->frames[0]->component->components=[(object)['type'=>'image','tagName'=>'img','attributes'=>(object)['src'=>'/api/projects/'.$id.'/assets/'.$asset['id'].'/file','alt'=>'Pixel QA']]];
    $settings=$project['settings'];$settings['forms']['notificationsEnabled']=true;$settings['forms']['recipients']=['owner@example.test'];
    $saved=$store->save($id,'backup-user',0,$document,$settings);
    $backup=$store->backup($id,'backup-user');
    backupCheck($backup['format']==='juzimder-project-backup'&&count($backup['project']['document']->data->pages)===1&&count($backup['project']['assets'])===1&&count($backup['project']['aiSkills']['skills'])===1,'Backup captures page data, the full private asset library and AI Skills');
    $changed=json_decode(json_encode($saved['document'],JSON_THROW_ON_ERROR),false,64,JSON_THROW_ON_ERROR);$changed->data->pages[0]->name='Changed after backup';
    $next=$store->save($id,'backup-user',1,$changed);
    $restored=$store->restoreBackup($id,'backup-user',$next['version'],$backup);
    backupCheck($restored['version']===3&&$restored['document']->data->pages[0]->name==='Главная','Restore creates a new revision and restores the saved page tree');
    backupCheck($restored['settings']['forms']['notificationsEnabled']===true&&$restored['settings']['forms']['recipients']===['owner@example.test'],'Restore returns all project settings');
    backupCheck(count($store->listAssets($id,'backup-user'))===1&&$store->assetFile($id,'backup-user',$asset['id'])['content']===$png,'Restore atomically reinstates the media library and preserves stable asset references');
    backupCheck($store->query('SELECT active_skill_id FROM project_ai_skill_state WHERE project_id=?',[$id])->fetchColumn()===str_repeat('a',24)&&$store->query('SELECT name FROM project_ai_skills WHERE project_id=?',[$id])->fetchColumn()==='Backup skill','Restore reinstates the active Skill and its content');
    backupReject(fn()=>$store->restoreBackup($id,'backup-user',2,$backup),409);
    $corrupt=$backup;$corrupt['project']['assets'][0]['sha256']=str_repeat('0',64);
    backupReject(fn()=>$store->restoreBackup($id,'backup-user',3,$corrupt),422);
    backupReject(fn()=>$store->restoreBackup($id,'another-user',3,$backup),404);
    backupReject(fn()=>$store->restoreBackup($id,'backup-user',3,['format'=>'invalid']),422);
} finally { unset($store);@unlink($path);@unlink($path.'-wal');@unlink($path.'-shm'); }
