<?php
declare(strict_types=1);
require __DIR__.'/../backend/Domain.php';
require __DIR__.'/../backend/Ai.php';
require __DIR__.'/../backend/AiGenerationJobs.php';
use Juzimder\{Store,AiGenerationJobs,StructuredAiProvider,Problem,AiProviderFailure};
function jobCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);echo "PASS $message\n";}
function jobReject(callable $fn,int $status):void{try{$fn();}catch(Problem $e){jobCheck($e->status===$status,'Job rejects HTTP '.$status);return;}throw new RuntimeException('Expected job rejection');}
class JobProvider implements StructuredAiProvider{
 public static string $lastUserPrompt='';
 public function completeJson(string $s,string $u,array $schema,string $name,int $maxTokens=3200):array{
  self::$lastUserPrompt=$u;
  return ['model'=>'test/model','content'=>json_encode(['siteType'=>'Workshop','audience'=>'Adults','goal'=>'Book','style'=>'Warm','html'=>'<section class="ai-hero"><div class="ai-hero-copy"><h1>Pottery</h1><p>A calm evening with clay.</p><a href="#booking">Book</a></div><div class="ai-image-slot" data-image-slot="hero"></div></section><section class="ai-formats"><h2>Formats</h2><p>Learn hand building with clay.</p></section><section class="ai-booking" id="booking"><h2>Booking</h2><p>Choose your workshop format.</p></section>','css'=>'.ai-hero{padding:40px;color:#222222}@media (max-width:768px){.ai-hero{padding:20px}}'],JSON_THROW_ON_ERROR)];
 }
}
final class InvalidMarkupJobProvider extends JobProvider{
 public function completeJson(string $s,string $u,array $schema,string $name,int $maxTokens=3200):array{
  $result=parent::completeJson($s,$u,$schema,$name,$maxTokens);$content=json_decode($result['content'],true,16,JSON_THROW_ON_ERROR);$content['html']=str_replace('<h1>','<script>private-output</script><h1>',$content['html']);$result['content']=json_encode($content,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);return $result;
 }
}
$path=sys_get_temp_dir().'/juzimder-jobs-'.bin2hex(random_bytes(6)).'.sqlite';
try{
 $store=new Store($path);$store->query('INSERT INTO users(id,email,password_hash) VALUES(?,?,?)',['owner','jobs@example.test','hash']);
 $store->query('INSERT INTO users(id,email,password_hash) VALUES(?,?,?)',['other','other@example.test','hash']);
 $project=$store->create('owner','Jobs QA');$id=$project['id'];$jobs=new AiGenerationJobs($store);$page=$project['document']->data->pages[0]->id;$payload=['prompt'=>'Workshop','skillProfile'=>'standard','userSkill'=>null,'projectDesignTokens'=>['ink'=>'#123456']];
 foreach(Juzimder\OpenDesignTemplateSource::catalog() as $template)jobCheck(AiGenerationJobs::safeTemplateId($template['id'])===$template['id'],'Template diagnostics accept catalog ID '.$template['id']);
 jobCheck(AiGenerationJobs::safeTemplateId('design-templates/blog-post/example.html')===null&&AiGenerationJobs::safeTemplateId(['blog-post'])===null,'Template diagnostics reject paths and non-string values');
 $job=$jobs->create($id,'owner',$page,0,$payload);jobCheck($job['status']==='queued'&&$job['result']===null,'Job creation returns immediately without generating or saving content');
 jobReject(fn()=>$jobs->create($id,'owner',$page,0,$payload),409);jobReject(fn()=>$jobs->get($id,'other',$job['id']),404);jobReject(fn()=>$jobs->get($id,'owner',str_repeat('a',24)),404);
 jobCheck($jobs->run($job['id'],fn()=>new JobProvider()),'Worker claims queued job');
 jobCheck(str_contains(JobProvider::$lastUserPrompt,'"ink":"#123456"'),'Generation worker forwards only the queued project design-token context to the existing model request');
 $result=(new AiGenerationJobs(new Store($path)))->get($id,'owner',null,$page);
 jobCheck(!array_key_exists('projectDesignTokens',$result['request']),'Generation-job status does not expose the project-token snapshot or extend its public request shape');
 jobCheck($result['diagnostics']['outcome']==='succeeded','Job diagnostics persist across reopening');
 $stages=array_column($result['diagnostics']['events'],'stage');foreach(['project_check','payload_load','provider_setup','design_context','open_design_reference','design_model_request','markup_validation','copy_validation','hero_validation','contrast_validation','contact_validation','skill_validation','result_persistence'] as $stage)jobCheck(in_array($stage,$stages,true),'Generation diagnostics include '.$stage.' stage');
 jobCheck(!str_contains(json_encode($result['diagnostics']),'Pottery')&&!str_contains(json_encode($result['diagnostics']),'Workshop'),'Generation diagnostics never persist the owner brief or page copy');
 jobCheck($result['status']==='succeeded'&&$result['result']['design']['sectionCount']===3,'Validated result persists and can be restored after reopening');
 $store->query('UPDATE ai_generation_jobs SET diagnostics=? WHERE id=?',[json_encode(['events'=>[['stage'=>'open_design_reference','state'=>'completed','templateId'=>'docs-page','referenceStatus'=>'loaded','source'=>'must not leak']]],JSON_THROW_ON_ERROR),$job['id']]);
 $historyReference=$jobs->history($id,'owner',$page)['items'][0]['templateReference']??null;
 jobCheck($historyReference===['templateId'=>'docs-page','referenceStatus'=>'loaded'],'History exposes only the allowlisted external reference ID and load state');
 jobCheck(!str_contains(json_encode($jobs->history($id,'owner',$page)),'must not leak'),'History never exposes raw source diagnostic metadata');
 jobCheck(!$jobs->run($job['id'],fn()=>throw new RuntimeException('Duplicate worker')),'Duplicate workers cannot repeat a completed provider call');
 jobCheck($store->owned($id,'owner')['version']===0,'Job completion never modifies Project Data or revisions');
 $failure=$jobs->create($id,'owner',$page,0,$payload);$jobs->run($failure['id'],fn()=>throw new AiProviderFailure('raw-secret',false,'timeout'));
 $failed=$jobs->get($id,'owner',$failure['id']);jobCheck($failed['diagnostics']['outcome']==='timeout'&&$failed['diagnostics']['attempts']===[],'Job persists a safe failure category even before the provider is available');jobCheck($failed['status']==='failed'&&!str_contains($failed['error'],'raw-secret'),'Provider failure stores only safe guidance');
 $markupFailure=$jobs->create($id,'owner',$page,0,$payload);$jobs->run($markupFailure['id'],fn()=>throw new AiProviderFailure('raw-rejected-markup',false,'invalid_markup','tag_allowlist'));
 $markupDiagnostic=$jobs->get($id,'owner',$markupFailure['id']);jobCheck($markupDiagnostic['diagnostics']['validationRule']==='tag_allowlist'&&!str_contains(json_encode($markupDiagnostic),'raw-rejected-markup'),'Job stores a fixed markup rule without rejected provider content');
 $cssFailure=$jobs->create($id,'owner',$page,0,$payload);$jobs->run($cssFailure['id'],fn()=>throw new AiProviderFailure('raw-rejected-css',false,'invalid_css','css_rule_syntax'));
 $cssDiagnostic=$jobs->get($id,'owner',$cssFailure['id']);jobCheck($cssDiagnostic['diagnostics']['validationRule']==='css_rule_syntax'&&!str_contains(json_encode($cssDiagnostic),'raw-rejected-css'),'Job diagnostics preserve a fixed CSS rule and omit rejected CSS content');
 $preciseCssFailure=$jobs->create($id,'owner',$page,0,$payload);$jobs->run($preciseCssFailure['id'],fn()=>throw new AiProviderFailure('raw-rejected-css .ai-private{secret:yes',false,'invalid_css','css_rule_syntax:unclosed_rule'));
 $preciseCssDiagnostic=$jobs->get($id,'owner',$preciseCssFailure['id']);jobCheck($preciseCssDiagnostic['diagnostics']['validationRule']==='css_rule_syntax:unclosed_rule'&&!str_contains(json_encode($preciseCssDiagnostic),'raw-rejected-css')&&!str_contains(json_encode($preciseCssDiagnostic),'.ai-private'),'Generation diagnostics retain only the allowlisted CSS parser reason and never rejected CSS text');
 $invalidMarkup=$jobs->create($id,'owner',$page,0,$payload);$jobs->run($invalidMarkup['id'],fn()=>new InvalidMarkupJobProvider());$invalidMarkupDiagnostic=$jobs->get($id,'owner',$invalidMarkup['id']);
 jobCheck($invalidMarkupDiagnostic['diagnostics']['failureStage']==='markup_validation'&&$invalidMarkupDiagnostic['diagnostics']['validationRule']==='tag_allowlist:script','A validator rejection records the exact failed stage and offending safe tag name');
 $tagFailure=$jobs->create($id,'owner',$page,0,$payload);$jobs->run($tagFailure['id'],fn()=>throw new AiProviderFailure('raw-rejected-markup',false,'invalid_markup','tag_allowlist:figure'));
 $tagDiagnostic=$jobs->get($id,'owner',$tagFailure['id']);jobCheck($tagDiagnostic['diagnostics']['validationRule']==='tag_allowlist:figure'&&!str_contains(json_encode($tagDiagnostic),'raw-rejected-markup'),'Markup diagnostics retain only a validated tag name for useful diagnosis');
 $tasteFailure=$jobs->create($id,'owner',$page,0,$payload);$jobs->run($tasteFailure['id'],fn()=>throw new AiProviderFailure('raw-rejected-design',false,'skill_requirements','taste:hero_action,section_copy'));
 $tasteDiagnostic=$jobs->get($id,'owner',$tasteFailure['id']);jobCheck($tasteDiagnostic['diagnostics']['validationRule']==='taste:hero_action,section_copy'&&!str_contains(json_encode($tasteDiagnostic),'raw-rejected-design'),'Taste diagnostics retain only bounded rule IDs without rejected model output');
 jobCheck(!str_contains(json_encode($invalidMarkupDiagnostic['diagnostics']),'private-output')&&str_contains(json_encode($invalidMarkupDiagnostic['diagnostics']),'tag_allowlist:script'),'Generation timeline omits rejected markup while retaining the safe offending tag name');
 $contrastFailure=$jobs->create($id,'owner',$page,0,$payload);$jobs->run($contrastFailure['id'],fn()=>new class implements StructuredAiProvider{public function completeJson(string $system,string $user,array $schema,string $name,int $maxTokens=3200):array{throw new AiProviderFailure('Low contrast.',false,'contrast_quality','<a>.ai-card-link text uses #D9A441 on #FFFFFF (about 2.25:1; required 4.5:1 at 15.0px). Set an explicit readable foreground color for the text on each surface.');}});
 $contrastDiagnostic=$jobs->get($id,'owner',$contrastFailure['id']);jobCheck($contrastDiagnostic['diagnostics']['validationRule']==='contrast_quality'&&!str_contains(json_encode($contrastDiagnostic),'#D9A441'),'Job UI diagnostics disclose the contrast category without generated CSS details');
 $fragmentFailure=$jobs->create($id,'owner',$page,0,$payload);$jobs->run($fragmentFailure['id'],fn()=>new class implements StructuredAiProvider{public function completeJson(string $system,string $user,array $schema,string $name,int $maxTokens=3200):array{throw new AiProviderFailure('Broken fragment.',false,'invalid_markup','fragment_target:private-fragment');}});
 $fragmentDiagnostic=$jobs->get($id,'owner',$fragmentFailure['id']);jobCheck($fragmentDiagnostic['diagnostics']['validationRule']==='fragment_target'&&!str_contains(json_encode($fragmentDiagnostic),'private-fragment'),'Job history reveals the fragment error category without its generated target ID');
 $repeatedFactsFailure=$jobs->create($id,'owner',$page,0,$payload);$jobs->run($repeatedFactsFailure['id'],fn()=>new class implements StructuredAiProvider{public function completeJson(string $system,string $user,array $schema,string $name,int $maxTokens=3200):array{throw new AiProviderFailure('Rejected copy stays private.',false,'copy_quality','copy_repeated_facts',null,['sectionIndexes'=>[1,3],'paragraphIndexes'=>[2,1],'sharedTokenCount'=>7,'smallerParagraphTokenCount'=>8,'overlapType'=>'ordered_run','longestOrderedRun'=>4,'rejectedCopy'=>'private rejected paragraph']);}});
 $repeatedFactsDiagnostic=$jobs->get($id,'owner',$repeatedFactsFailure['id']);$repeatedFactEvent=array_values(array_filter($repeatedFactsDiagnostic['diagnostics']['events'],static fn(array $event):bool=>($event['validationRule']??null)==='copy_repeated_facts'))[0]??[];
 jobCheck(($repeatedFactEvent['validationEvidence']??null)===['sectionIndexes'=>[1,3],'paragraphIndexes'=>[2,1],'overlapType'=>'ordered_run','orderedRunLength'=>4]&&!str_contains(json_encode($repeatedFactsDiagnostic),'sharedTokenCount')&&!str_contains(json_encode($repeatedFactsDiagnostic),'smallerParagraphTokenCount')&&!str_contains(json_encode($repeatedFactsDiagnostic),'private rejected paragraph')&&($repeatedFactEvent['attempt']??1)===1,'Job history stores only repeat-fact coordinates, overlap type and ordered-run length; attempt is separate and rejected copy or overlap counts stay private');
 $copyDuplicateFailure=$jobs->create($id,'owner',$page,0,$payload);$jobs->run($copyDuplicateFailure['id'],fn()=>throw new AiProviderFailure('Private duplicate copy.',false,'copy_quality','copy_duplicate',null,['kind'=>'exact','copyType'=>'heading','firstIndex'=>2,'duplicateIndex'=>4,'rejectedCopy'=>'private text']));
 $copyDuplicateDiagnostic=$jobs->get($id,'owner',$copyDuplicateFailure['id']);$copyDuplicateEvent=array_values(array_filter($copyDuplicateDiagnostic['diagnostics']['events'],static fn(array $event):bool=>($event['validationRule']??null)==='copy_duplicate'))[0]??[];
 jobCheck(($copyDuplicateEvent['validationEvidence']??null)===['kind'=>'exact','copyType'=>'heading','firstIndex'=>2,'duplicateIndex'=>4]&&!str_contains(json_encode($copyDuplicateDiagnostic),'Private duplicate copy')&&!str_contains(json_encode($copyDuplicateDiagnostic),'private text'),'Job history stores only duplicate-copy coordinates without rejected model text');
 $copyOverlapFailure=$jobs->create($id,'owner',$page,0,$payload);$jobs->run($copyOverlapFailure['id'],fn()=>throw new AiProviderFailure('Private overlap copy.',false,'copy_quality','copy_duplicate',null,['kind'=>'overlap','copyType'=>'heading_paragraph','sectionIndex'=>2,'headingIndex'=>3,'paragraphIndex'=>2,'sharedTokenCount'=>3,'smallerTokenCount'=>3,'rejectedCopy'=>'private overlapping text']));
 $copyOverlapDiagnostic=$jobs->get($id,'owner',$copyOverlapFailure['id']);$copyOverlapEvent=array_values(array_filter($copyOverlapDiagnostic['diagnostics']['events'],static fn(array $event):bool=>($event['validationRule']??null)==='copy_duplicate'))[0]??[];
 jobCheck(($copyOverlapEvent['validationEvidence']??null)===['kind'=>'overlap','copyType'=>'heading_paragraph','sectionIndex'=>2,'headingIndex'=>3,'paragraphIndex'=>2,'overlapType'=>'near_duplicate']&&!str_contains(json_encode($copyOverlapDiagnostic),'sharedTokenCount')&&!str_contains(json_encode($copyOverlapDiagnostic),'smallerTokenCount')&&!str_contains(json_encode($copyOverlapDiagnostic),'private overlapping text'),'Job history stores overlap coordinates and type without token counts or rejected copy');
 $pricingHeroFailure=$jobs->create($id,'owner',$page,0,$payload);$jobs->run($pricingHeroFailure['id'],fn()=>throw new AiProviderFailure('Rejected pricing copy stays private.',false,'copy_quality','copy_pricing_hero_fact'));
 $pricingHeroDiagnostic=$jobs->get($id,'owner',$pricingHeroFailure['id']);jobCheck($pricingHeroDiagnostic['diagnostics']['validationRule']==='copy_pricing_hero_fact'&&str_contains(json_encode($pricingHeroDiagnostic),'copy_pricing_hero_fact')&&!str_contains(json_encode($pricingHeroDiagnostic),'Rejected pricing copy stays private'),'Job history retains the fixed pricing-copy rule without rejected model copy');
 $missingCopyRule=$jobs->create($id,'owner',$page,0,$payload);$jobs->run($missingCopyRule['id'],fn()=>throw new AiProviderFailure('Private copy failure.',false,'copy_quality'));
 $missingCopyDiagnostic=$jobs->get($id,'owner',$missingCopyRule['id']);$missingCopyEvent=array_values(array_filter($missingCopyDiagnostic['diagnostics']['events'],static fn(array $event):bool=>($event['state']??null)==='failed'))[0]??[];
 jobCheck($missingCopyDiagnostic['diagnostics']['validationRule']==='copy_rule_missing'&&($missingCopyEvent['validationRule']??null)==='copy_rule_missing'&&!str_contains(json_encode($missingCopyDiagnostic),'Private copy failure'),'Missing copy audit codes receive a safe diagnostic marker without rejected content');
 $unknownCopyRule=$jobs->create($id,'owner',$page,0,$payload);$jobs->run($unknownCopyRule['id'],fn()=>throw new AiProviderFailure('Private copy failure.',false,'copy_quality','copy_internal_detector_42'));
 $unknownCopyDiagnostic=$jobs->get($id,'owner',$unknownCopyRule['id']);jobCheck($unknownCopyDiagnostic['diagnostics']['validationRule']==='copy_rule_unrecognized'&&str_contains(json_encode($unknownCopyDiagnostic),'copy_rule_unrecognized')&&!str_contains(json_encode($unknownCopyDiagnostic),'copy_internal_detector_42'),'Unknown copy audit codes are classified without persisting unreviewed identifiers');
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
 $unavailableLaunch=$jobs->create($id,'owner',$page,0,$payload);$jobs->failLaunch($unavailableLaunch['id'],'exec_unavailable');$launchDiagnostic=$jobs->get($id,'owner',$unavailableLaunch['id']);
 jobCheck($launchDiagnostic['diagnostics']['outcome']==='launcher'&&$launchDiagnostic['diagnostics']['launchCode']==='exec_unavailable'&&$launchDiagnostic['error']==='Хостинг отключил запуск фоновых процессов PHP.','Worker launch failures expose only a fixed hosting-capability code and safe explanation');
 jobReject(fn()=>$jobs->history($id,'other',$page),404);jobReject(fn()=>$jobs->history($id,'owner','missing'),404);
 for($i=0;$i<23;$i++){$entry=$jobs->create($id,'owner',$page,0,$payload);$jobs->failLaunch($entry['id']);}
 jobCheck(count($jobs->history($id,'owner',$page)['items'])===20,'History response is bounded to the newest twenty tasks');
 jobCheck($store->owned($id,'owner')['version']===0,'Reading task history never changes Project Data');
 jobReject(fn()=>$jobs->create($id,'owner','missing',0,$payload),404);jobReject(fn()=>$jobs->create($id,'owner',$page,1,$payload),409);
}finally{unset($store,$jobs);@unlink($path);}
