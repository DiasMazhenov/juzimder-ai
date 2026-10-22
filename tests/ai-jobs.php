<?php
declare(strict_types=1);
require __DIR__.'/../backend/Domain.php';
require __DIR__.'/../backend/Ai.php';
require __DIR__.'/../backend/AiGenerationJobs.php';
use Juzimder\{Store,AiGenerationJobs,StructuredAiProvider,Problem,AiProviderFailure};
function jobCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);echo "PASS $message\n";}
function jobReject(callable $fn,int $status):void{try{$fn();}catch(Problem $e){jobCheck($e->status===$status,'Job rejects HTTP '.$status);return;}throw new RuntimeException('Expected job rejection');}
final class JobProvider implements StructuredAiProvider{
 public function completeJson(string $s,string $u,array $schema,string $name,int $maxTokens=3200):array{
  return ['model'=>'test/model','content'=>json_encode(['siteType'=>'Workshop','audience'=>'Adults','goal'=>'Book','style'=>'Warm','html'=>'<section class="ai-hero"><h1>Pottery</h1><p>A calm evening with clay.</p><a href="#booking">Book</a><div class="ai-image-slot" data-image-slot="hero"></div></section><section class="ai-formats"><h2>Formats</h2><p>Learn hand building with clay.</p></section><section class="ai-booking" id="booking"><h2>Booking</h2><p>Choose your workshop format.</p></section>','css'=>'.ai-hero{padding:40px;color:#222222}@media (max-width:768px){.ai-hero{padding:20px}}'],JSON_THROW_ON_ERROR)];
 }
}
$path=sys_get_temp_dir().'/juzimder-jobs-'.bin2hex(random_bytes(6)).'.sqlite';
try{
 $store=new Store($path);$store->query('INSERT INTO users(id,email,password_hash) VALUES(?,?,?)',['owner','jobs@example.test','hash']);
 $store->query('INSERT INTO users(id,email,password_hash) VALUES(?,?,?)',['other','other@example.test','hash']);
 $project=$store->create('owner','Jobs QA');$id=$project['id'];$jobs=new AiGenerationJobs($store);$page=$project['document']->data->pages[0]->id;$payload=['prompt'=>'Workshop','skillProfile'=>'standard','userSkill'=>null];
 $job=$jobs->create($id,'owner',$page,0,$payload);jobCheck($job['status']==='queued'&&$job['result']===null,'Job creation returns immediately without generating or saving content');
 jobReject(fn()=>$jobs->create($id,'owner',$page,0,$payload),409);jobReject(fn()=>$jobs->get($id,'other',$job['id']),404);jobReject(fn()=>$jobs->get($id,'owner',str_repeat('a',24)),404);
 jobCheck($jobs->run($job['id'],fn()=>new JobProvider()),'Worker claims queued job');
 $result=(new AiGenerationJobs(new Store($path)))->get($id,'owner',null,$page);
 jobCheck($result['status']==='succeeded'&&$result['result']['design']['sectionCount']===3,'Validated result persists and can be restored after reopening');
 jobCheck(!$jobs->run($job['id'],fn()=>throw new RuntimeException('Duplicate worker')),'Duplicate workers cannot repeat a completed provider call');
 jobCheck($store->owned($id,'owner')['version']===0,'Job completion never modifies Project Data or revisions');
 $failure=$jobs->create($id,'owner',$page,0,$payload);$jobs->run($failure['id'],fn()=>throw new AiProviderFailure('raw-secret',false,'timeout'));
 $failed=$jobs->get($id,'owner',$failure['id']);jobCheck($failed['status']==='failed'&&!str_contains($failed['error'],'raw-secret'),'Provider failure stores only safe guidance');
 $expired=$jobs->create($id,'owner',$page,0,$payload);$store->query('UPDATE ai_generation_jobs SET created_at=? WHERE id=?',[time()-700,$expired['id']]);
 jobCheck($jobs->get($id,'owner',$expired['id'])['status']==='failed','Abandoned queued/running jobs expire and stop blocking the project');
 $queuedCancel=$jobs->create($id,'owner',$page,0,$payload);
 jobReject(fn()=>$jobs->cancel($id,'other',$queuedCancel['id']),404);
 jobCheck($jobs->cancel($id,'owner',$queuedCancel['id'])['status']==='cancelled','Queued cancellation is immediate');
 jobCheck(!$jobs->run($queuedCancel['id'],fn()=>throw new RuntimeException('Must not call provider')),'Cancelled queued job never calls provider');
 $runningCancel=$jobs->create($id,'owner',$page,0,$payload);
 $jobs->run($runningCancel['id'],function()use($jobs,$id,$runningCancel,$page,$payload){
   jobCheck($jobs->cancel($id,'owner',$runningCancel['id'])['status']==='cancelling','Running cancellation waits for worker acknowledgement');
   jobReject(fn()=>$jobs->create($id,'owner',$page,0,$payload),409);
   return new JobProvider();
 });
 $cancelled=$jobs->get($id,'owner',$runningCancel['id']);
 jobCheck($cancelled['status']==='cancelled'&&$cancelled['result']===null,'A result racing with cancellation is discarded and releases the active slot');
 $workerJob=$jobs->create($id,'owner',$page,0,$payload);$jobs->launch($workerJob['id']);
 for($wait=0;$wait<40;$wait++){usleep(100000);$workerState=$jobs->get($id,'owner',$workerJob['id']);if($workerState['status']==='failed')break;}
 jobCheck($workerState['status']==='failed','Detached CLI worker starts and safely reports missing credentials without blocking the API');
 jobReject(fn()=>$jobs->create($id,'owner','missing',0,$payload),404);jobReject(fn()=>$jobs->create($id,'owner',$page,1,$payload),409);
}finally{unset($store,$jobs);@unlink($path);}
