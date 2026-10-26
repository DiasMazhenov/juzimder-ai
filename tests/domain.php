<?php
declare(strict_types=1);
require __DIR__.'/../backend/Domain.php';
use Juzimder\{Document, Store, Problem, CustomCode};
function check(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); echo "PASS $message\n"; }
function rejects(callable $fn,int $status,string $message=''): void { try {$fn();} catch (Problem $e) {check($e->status===$status,'Reject '.$status.($message ? ': '.$message : ''));return;} throw new RuntimeException('Expected rejection'.($message ? ': '.$message : '')); }
function sameJson(mixed $left,mixed $right): bool { return json_encode($left,JSON_THROW_ON_ERROR)===json_encode($right,JSON_THROW_ON_ERROR); }
$path=sys_get_temp_dir().'/juzimder-test-'.bin2hex(random_bytes(6)).'.sqlite';
try {
$s=new Store($path);$s->query('INSERT INTO users(id,email,password_hash) VALUES(?,?,?)',['a','a@example.test','hash']);$s->query('INSERT INTO users(id,email,password_hash) VALUES(?,?,?)',['b','b@example.test','hash']);
check((int)$s->db->query('PRAGMA user_version')->fetchColumn()===15,'Migrations');$p=$s->create('a','Studio');$id=$p['id'];check($p['slug']==='studio','Create project with readable slug');check($p['version']===0,'Create empty project');
$empty=Document::empty()['data']['pages'][0]['frames'][0]['component'];check($empty['style']['display']==='flex'&&$empty['style']['flex-direction']==='column','New project Body defaults to vertical Flex');
rejects(fn()=>$s->owned($id,'b'),404);rejects(fn()=>$s->save($id,'b',0,Document::empty()),404);
Document::validate(json_decode(file_get_contents(__DIR__.'/grapesjs-0.23.8.fixture.json'),false,64,JSON_THROW_ON_ERROR));
check(true,'Real GrapesJS 0.23.8 fixture');
$emptyStyleDocument=json_decode(json_encode(Document::empty(),JSON_THROW_ON_ERROR),false,64,JSON_THROW_ON_ERROR);
$emptyStyleDocument->data->pages[0]->frames[0]->component->components=[(object)['type'=>'custom-code','tagName'=>'div','content'=>CustomCode::encode(['html'=>'<p>Preview</p>','css'=>'','js'=>'' ]),'style'=>'']];
Document::validate($emptyStyleDocument);
check($emptyStyleDocument->data->pages[0]->frames[0]->component->components[0]->style instanceof stdClass&&get_object_vars($emptyStyleDocument->data->pages[0]->frames[0]->component->components[0]->style)===[],'Normalize GrapesJS empty style strings before persistence');
$invalidFixtures=json_decode(file_get_contents(__DIR__.'/fixtures/invalid-documents.json'),false,64,JSON_THROW_ON_ERROR);
foreach($invalidFixtures as $fixture) rejects(fn()=>Document::validate($fixture->document),422,$fixture->case);
$d=Document::empty();$d['data']['pages'][0]['frames'][0]['component']['style']=['--juzimder-color-ink'=>'#102030','--juzimder-radius'=>'12px'];$d['data']['pages'][0]['frames'][0]['component']['components']=[['type'=>'text','tagName'=>'h1','content'=>'Persistent heading','attributes'=>['id'=>'heading-stable'],'ai'=>['origin'=>'manual']]];
$d['data']['styles']=[['selectors'=>['heading-stable'],'style'=>['font-size'=>'32px'],'mediaText'=>'(max-width: 576px)']];
$r=$s->save($id,'a',0,$d);check($r['version']===1,'Version increment');check($r['document']->data->pages[0]->frames[0]->component->style->{'--juzimder-color-ink'}==='#183e31','Project settings add canonical root tokens');check($r['document']->data->pages[0]->frames[0]->component->components[0]->content==='Persistent heading'&&$r['document']->data->styles[0]->style->{'font-size'}==='32px','Central tokens preserve page content and responsive styles');$persistedDocument=$r['document'];rejects(fn()=>$s->save($id,'a',0,$d),409);
check((int)$s->query('SELECT COUNT(*) FROM revisions')->fetchColumn()===1,'Conflict creates no revision');
foreach ([['script'=>'alert(1)'],['tagName'=>'iframe'],['attributes'=>['onclick'=>'alert(1)']],['attributes'=>['href'=>'javascript:alert(1)']],['content'=>'<img src=x onerror=alert(1)>'],['style'=>['background'=>'url(https://example.test)']],['type'=>'custom-code']] as $bad) {$evil=$d;$evil['data']['pages'][0]['frames'][0]['component']['components'][]=$bad;rejects(fn()=>Document::validate($evil),422);}
$photoCaptionDoc=Document::empty();$photoCaptionDoc['data']['pages'][0]['frames'][0]['component']['components']=[['type'=>'wrapper','tagName'=>'figure','style'=>['display'=>'inline-flex'],'components'=>[['type'=>'image','tagName'=>'img','attributes'=>['src'=>'https://example.test/photo.jpg','alt'=>'A photo'],'style'=>['width'=>'100%']],['type'=>'text','tagName'=>'figcaption','content'=>'Photo: Author · CC BY-SA','style'=>['font-size'=>'8px']]]]];Document::validate($photoCaptionDoc);check(true,'Main-editor photo figure with an 8px copyright caption passes Project Data validation');
$longPhotoCaption=$photoCaptionDoc;$longPhotoCaption['data']['pages'][0]['frames'][0]['component']['components'][0]['components'][1]['content']=str_repeat('x',501);rejects(fn()=>Document::validate($longPhotoCaption),422,'Photo caption length is bounded to 500 characters');
check(sameJson($s->owned($id,'a')['document'],$persistedDocument),'Invalid writes preserve data');
$next=$d;$next['data']['pages'][0]['frames'][0]['component']['components'][0]['content']='Second revision';
$second=$s->save($id,'a',1,$next);check($second['version']===2,'Second revision saved');
$restored=$s->restore($id,'a',2,1);check($restored['version']===3&&sameJson($restored['document'],$persistedDocument),'Restore creates a new version with historical data');
check((int)$s->query('SELECT COUNT(*) FROM revisions WHERE project_id=?',[$id])->fetchColumn()===3,'Restore preserves revision history');
rejects(fn()=>$s->restore($id,'a',2,1),409);rejects(fn()=>$s->restore($id,'b',3,1),404);rejects(fn()=>$s->restore($id,'a',3,999),404);
unset($s);$s=new Store($path);check(sameJson($s->owned($id,'a')['document'],$persistedDocument),'Disk persistence after reopen');
$version=$s->owned($id,'a')['version'];$document=$s->owned($id,'a')['document'];
for($i=0;$i<200;$i++){$saved=$s->save($id,'a',$version,$document);$version=$saved['version'];}
$bounds=$s->query('SELECT COUNT(*) AS count,MIN(version) AS first,MAX(version) AS last FROM revisions WHERE project_id=?',[$id])->fetch();
check((int)$bounds['count']===200&&(int)$bounds['first']===$version-199&&(int)$bounds['last']===$version,'Keep only the latest 200 revisions');
rejects(fn()=>$s->restore($id,'a',$version,1),404);
$firstPublication=$s->publish($id,'a',$version,['index.html'=>'published snapshot one','assets/site.css'=>'body{margin:0}','README.txt'=>'export info']);
check($firstPublication['version']===1&&$firstPublication['sourceProjectVersion']===$version,'Publish creates the first immutable snapshot from the expected project revision');
check($s->publicFile($id,'index.html')['content']==='published snapshot one','Public origin reads only the active publication snapshot');
$immutableUpdateRejected=false;try{$s->query('UPDATE publication_versions SET files_json=? WHERE project_id=? AND version=1',['mutated snapshot',$id]);}catch(PDOException){$immutableUpdateRejected=true;}
check($immutableUpdateRejected,'SQLite prevents in-place publication snapshot updates');
rejects(fn()=>$s->publish($id,'a',$version-1,['index.html'=>'stale','assets/site.css'=>'']),409);
rejects(fn()=>$s->publish($id,'b',$version,['index.html'=>'foreign','assets/site.css'=>'']),404);
$savedForPublication=$s->save($id,'a',$version,$document);
check($s->publicFile($id,'index.html')['content']==='published snapshot one','Project edits cannot mutate the active publication');
$secondPublication=$s->publish($id,'a',$savedForPublication['version'],['index.html'=>'published snapshot two','assets/site.css'=>'']);
check($secondPublication['version']===2&&$s->publicFile($id,'index.html')['content']==='published snapshot two','Republishing activates a new version without rewriting history');
$formId=str_repeat('a',24);$formManifest=json_encode(['version'=>1,'forms'=>[$formId=>['pagePath'=>'index.html','fields'=>[['name'=>'email','type'=>'email','required'=>true],['name'=>'message','type'=>'textarea','required'=>false]]]]],JSON_THROW_ON_ERROR);
$formsPublication=$s->publish($id,'a',$savedForPublication['version'],['index.html'=>'published form','assets/site.css'=>'','forms.json'=>$formManifest]);
check($formsPublication['version']===3,'Publish validates and stores the form manifest');
check($s->acceptFormSubmission($id,$formId,['email'=>'visitor@example.test','message'=>'Hello'],'192.0.2.1')['accepted']===true,'Published form accepts a valid submission');
check(count($s->listFormSubmissions($id,'a'))===1&&$s->listFormSubmissions($id,'a')[0]['fields']['email']==='visitor@example.test','Project owner can read private form submissions');
rejects(fn()=>$s->acceptFormSubmission($id,$formId,['email'=>'invalid'],'192.0.2.2'),422,'Reject invalid email');
rejects(fn()=>$s->acceptFormSubmission($id,$formId,['email'=>'ok@example.test','admin'=>'yes'],'192.0.2.2'),422,'Reject fields outside published form schema');
for($i=0;$i<5;$i++)$s->acceptFormSubmission($id,$formId,['email'=>'visitor@example.test'],'192.0.2.3');
rejects(fn()=>$s->acceptFormSubmission($id,$formId,['email'=>'visitor@example.test'],'192.0.2.3'),429,'Rate limit public form submissions');
$submissionId=$s->listFormSubmissions($id,'a')[0]['id'];$s->deleteFormSubmission($id,'a',$submissionId);check(count($s->listFormSubmissions($id,'a'))===5,'Project owner can delete a submission');
rejects(fn()=>$s->unpublish($id,'a',$version),409);
$s->unpublish($id,'a',$savedForPublication['version']);
check($s->publicFile($id,'index.html')===null,'Unpublishing removes the public pointer');
check((int)$s->query('SELECT COUNT(*) FROM publication_versions WHERE project_id=?',[$id])->fetchColumn()===3,'Unpublishing preserves immutable publication history');
} finally {unset($s);@unlink($path);}
require __DIR__.'/backup.php';
