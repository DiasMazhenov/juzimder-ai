<?php
declare(strict_types=1);
require __DIR__.'/../backend/Domain.php';
require __DIR__.'/../backend/Ai.php';
require __DIR__.'/../backend/AiGenerationJobs.php';
use Juzimder\{Store,AiGenerationJobs,StructuredAiProvider,Problem,AiProviderFailure};
function jobCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);echo "PASS $message\n";}
function jobReject(callable $fn,int $status):void{try{$fn();}catch(Problem $e){jobCheck($e->status===$status,'Job rejects HTTP '.$status);return;}throw new RuntimeException('Expected job rejection');}
final class JobProvider implements StructuredAiProvider{
 public static string $lastUserPrompt='';
 public function completeJson(string $s,string $u,array $schema,string $name,int $maxTokens=3200):array{
  self::$lastUserPrompt=$u;
  return ['model'=>'test/model','content'=>json_encode(['siteType'=>'Workshop','audience'=>'Adults','goal'=>'Book','style'=>'Warm','html'=>'<section class="ai-hero"><div class="ai-hero-copy"><h1>Pottery</h1><p>A calm evening with clay.</p><a href="#booking">Book</a></div><div class="ai-image-slot" data-image-slot="hero"></div></section><section class="ai-formats"><h2>Formats</h2><p>Learn hand building with clay.</p></section><section class="ai-booking" id="booking"><h2>Booking</h2><p>Choose your workshop format.</p></section>','css'=>'.ai-hero{padding:40px;color:#222222}@media (max-width:768px){.ai-hero{padding:20px}}'],JSON_THROW_ON_ERROR)];
 }
}
$path=sys_get_temp_dir().'/juzimder-jobs-'.bin2hex(random_bytes(6)).'.sqlite';
try{
 $store=new Store($path);$store->query('INSERT INTO users(id,email,password_hash) VALUES(?,?,?)',['owner','jobs@example.test','hash']);
 $store->query('INSERT INTO users(id,email,password_hash) VALUES(?,?,?)',['other','other@example.test','hash']);
 $project=$store->create('owner','Jobs QA');$id=$project['id'];$jobs=new AiGenerationJobs($store);$page=$project['document']->data->pages[0]->id;$payload=['prompt'=>'Workshop','skillProfile'=>'standard','userSkill'=>null,'projectDesignTokens'=>['ink'=>'#123456']];
 $job=$jobs->create($id,'owner',$page,0,$payload);jobCheck($job['status']==='queued'&&$job['result']===null,'Job creation returns immediately without generating or saving content');
 jobReject(fn()=>$jobs->create($id,'owner',$page,0,$payload),409);jobReject(fn()=>$jobs->get($id,'other',$job['id']),404);jobReject(fn()=>$jobs->get($id,'owner',str_repeat('a',24)),404);
 jobCheck($jobs->run($job['id'],fn()=>new JobProvider()),'Worker claims queued job');
 jobCheck(str_contains(JobProvider::$lastUserPrompt,'"ink":"#123456"'),'Generation worker forwards only the queued project design-token context to the existing model request');
 $result=(new AiGenerationJobs(new Store($path)))->get($id,'owner',null,$page);
 jobCheck(!array_key_exists('projectDesignTokens',$result['request']),'Generation-job status does not expose the project-token snapshot or extend its public request shape');
 jobCheck($result['diagnostics']['outcome']==='succeeded','Job diagnostics persist across reopening');
 jobCheck($result['status']==='succeeded'&&$result['result']['design']['sectionCount']===3,'Validated result persists and can be restored after reopening');
 jobCheck(!$jobs->run($job['id'],fn()=>throw new RuntimeException('Duplicate worker')),'Duplicate workers cannot repeat a completed provider call');
 jobCheck($store->owned($id,'owner')['version']===0,'Job completion never modifies Project Data or revisions');
 $failure=$jobs->create($id,'owner',$page,0,$payload);$jobs->run($failure['id'],fn()=>throw new AiProviderFailure('raw-secret',false,'timeout'));
 $failed=$jobs->get($id,'owner',$failure['id']);jobCheck($failed['diagnostics']['outcome']==='timeout'&&$failed['diagnostics']['attempts']===[],'Job persists a safe failure category even before the provider is available');jobCheck($failed['status']==='failed'&&!str_contains($failed['error'],'raw-secret'),'Provider failure stores only safe guidance');
 $markupFailure=$jobs->create($id,'owner',$page,0,$payload);$jobs->run($markupFailure['id'],fn()=>throw new AiProviderFailure('raw-rejected-markup',false,'invalid_markup','tag_allowlist'));
 $markupDiagnostic=$jobs->get($id,'owner',$markupFailure['id']);jobCheck($markupDiagnostic['diagnostics']['validationRule']==='tag_allowlist'&&!str_contains(json_encode($markupDiagnostic),'raw-rejected-markup'),'Job stores a fixed markup rule without rejected provider content');
 $contrastFailure=$jobs->create($id,'owner',$page,0,$payload);$jobs->run($contrastFailure['id'],fn()=>new class implements StructuredAiProvider{public function completeJson(string $system,string $user,array $schema,string $name,int $maxTokens=3200):array{throw new AiProviderFailure('Low contrast.',false,'contrast_quality','<a>.ai-card-link text uses #D9A441 on #FFFFFF (about 2.25:1; required 4.5:1 at 15.0px). Set an explicit readable foreground color for the text on each surface.');}});
 $contrastDiagnostic=$jobs->get($id,'owner',$contrastFailure['id']);jobCheck($contrastDiagnostic['diagnostics']['validationRule']==='contrast_quality'&&!str_contains(json_encode($contrastDiagnostic),'#D9A441'),'Job UI diagnostics disclose the contrast category without generated CSS details');
 $fragmentFailure=$jobs->create($id,'owner',$page,0,$payload);$jobs->run($fragmentFailure['id'],fn()=>new class implements StructuredAiProvider{public function completeJson(string $system,string $user,array $schema,string $name,int $maxTokens=3200):array{throw new AiProviderFailure('Broken fragment.',false,'invalid_markup','fragment_target:private-fragment');}});
 $fragmentDiagnostic=$jobs->get($id,'owner',$fragmentFailure['id']);jobCheck($fragmentDiagnostic['diagnostics']['validationRule']==='fragment_target'&&!str_contains(json_encode($fragmentDiagnostic),'private-fragment'),'Job history reveals the fragment error category without its generated target ID');
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
 $history=$jobs->history($id,'owner',$page);
 jobCheck($history['items'][0]['id']===$workerJob['id']&&in_array($job['id'],array_column($history['items'],'id'),true),'History includes earlier success after a later cancellation or failure, newest first');
 jobCheck(!array_key_exists('result',$history['items'][0])&&!array_key_exists('payload',$history['items'][0]),'History summaries omit full result and Skill source');
 jobReject(fn()=>$jobs->history($id,'other',$page),404);jobReject(fn()=>$jobs->history($id,'owner','missing'),404);
 for($i=0;$i<23;$i++){$entry=$jobs->create($id,'owner',$page,0,$payload);$jobs->failLaunch($entry['id']);}
 jobCheck(count($jobs->history($id,'owner',$page)['items'])===20,'History response is bounded to the newest twenty tasks');
 jobCheck($store->owned($id,'owner')['version']===0,'Reading task history never changes Project Data');
 jobReject(fn()=>$jobs->create($id,'owner','missing',0,$payload),404);jobReject(fn()=>$jobs->create($id,'owner',$page,1,$payload),409);
}finally{unset($store,$jobs);@unlink($path);}
