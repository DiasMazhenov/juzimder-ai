<?php
declare(strict_types=1);
require __DIR__.'/../backend/Domain.php';
use Juzimder\{Document, Store, Problem};
function check(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); echo "PASS $message\n"; }
function rejects(callable $fn,int $status): void { try {$fn();} catch (Problem $e) {check($e->status===$status,'Reject '.$status);return;} throw new RuntimeException('Expected rejection'); }
$path=sys_get_temp_dir().'/juzimder-test-'.bin2hex(random_bytes(6)).'.sqlite';
try {
$s=new Store($path);$s->query('INSERT INTO users(id,email,password_hash) VALUES(?,?,?)',['a','a@example.test','hash']);$s->query('INSERT INTO users(id,email,password_hash) VALUES(?,?,?)',['b','b@example.test','hash']);
check((int)$s->db->query('PRAGMA user_version')->fetchColumn()===1,'Migration');$p=$s->create('a','Studio');$id=$p['id'];check($p['version']===0,'Create empty project');
rejects(fn()=>$s->owned($id,'b'),404);rejects(fn()=>$s->save($id,'b',0,Document::empty()),404);
Document::validate(json_decode(file_get_contents(__DIR__.'/grapesjs-0.23.8.fixture.json'),true));
check(true,'Real GrapesJS 0.23.8 fixture');
$d=Document::empty();$d['data']['pages'][0]['frames'][0]['component']['components']=[['type'=>'text','tagName'=>'h1','content'=>'Persistent heading','attributes'=>['id'=>'heading-stable'],'ai'=>['origin'=>'manual']]];
$d['data']['styles']=[['selectors'=>['heading-stable'],'style'=>['font-size'=>'32px'],'mediaText'=>'(max-width: 576px)']];
$r=$s->save($id,'a',0,$d);check($r['document']===$d,'Lossless project round trip');check($r['version']===1,'Version increment');rejects(fn()=>$s->save($id,'a',0,$d),409);
check((int)$s->query('SELECT COUNT(*) FROM revisions')->fetchColumn()===1,'Conflict creates no revision');
foreach ([['script'=>'alert(1)'],['tagName'=>'iframe'],['attributes'=>['onclick'=>'alert(1)']],['attributes'=>['href'=>'javascript:alert(1)']],['content'=>'<img src=x onerror=alert(1)>'],['style'=>['background'=>'url(https://example.test)']],['type'=>'custom-code']] as $bad) {$evil=$d;$evil['data']['pages'][0]['frames'][0]['component']['components'][]=$bad;rejects(fn()=>Document::validate($evil),422);}
$evil=$d;$evil['data']['pages'][0]['frames'][0]['component']['components']='bad';rejects(fn()=>Document::validate($evil),422);
$evil=$d;$evil['schemaVersion']=2;rejects(fn()=>Document::validate($evil),422);
check($s->owned($id,'a')['document']===$d,'Invalid writes preserve data');
unset($s);$s=new Store($path);check($s->owned($id,'a')['document']===$d,'Disk persistence after reopen');
} finally {unset($s);@unlink($path);}
