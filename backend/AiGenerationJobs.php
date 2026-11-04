<?php
declare(strict_types=1);
namespace Juzimder;
require_once __DIR__.'/AiPhotoSelection.php';

/** Records only fixed stage names and safe failure codes; prompts and model output stay private. */
final class AiGenerationTracingProvider implements StructuredAiProvider {
    private array $requestCounts=[];
    public function __construct(private readonly StructuredAiProvider $provider,private readonly \Closure $recordStage) {}
    public function completeJson(string $systemPrompt,string $userPrompt,array $schema,string $schemaName,int $maxTokens=3200):array {
        $count=($this->requestCounts[$schemaName]??0)+1;$this->requestCounts[$schemaName]=$count;
        $stage=match($schemaName){'juzimder_generated_site'=>$count===1?'design_model_request':'repair_model_request','juzimder_photo_selection'=>'photo_model_request',default=>'model_request'};
        ($this->recordStage)($stage,'started',['attempt'=>$count]);
        try{$result=$this->provider->completeJson($systemPrompt,$userPrompt,$schema,$schemaName,$maxTokens);($this->recordStage)($stage,'completed',['attempt'=>$count]);return $result;}
        catch(\Throwable $error){
            $meta=['attempt'=>$count,'reasonCode'=>$error instanceof AiProviderFailure?AiProviderFailure::publicCategory($error->category):'internal'];
            if($error instanceof AiProviderFailure&&$error->validationRule!==null)$meta['validationRule']=$error->validationRule;
            if($error instanceof AiProviderFailure&&($evidence=$error->safeValidationEvidence())!==null)$meta['validationEvidence']=$evidence;
            ($this->recordStage)($stage,'failed',$meta);throw $error;
        }
    }
}

final class AiGenerationJobs {
    private const STAGES=['project_check','payload_load','provider_setup','design_context','open_design_reference','open_design_pipeline','design_model_request','response_parse','markup_validation','documentation_css_fallback','copy_validation','hero_validation','contrast_validation','contact_validation','skill_validation','repair_decision','repair_model_request','contrast_repair','photo_catalog','photo_model_request','result_persistence','model_request'];

    /** Allow only stable catalog IDs in diagnostics; never persist source paths or source text. */
    public static function safeTemplateId(mixed $candidate): ?string {
        if(!is_string($candidate))return null;
        foreach(OpenDesignTemplateSource::catalog() as $template){
            if(($template['id']??null)===$candidate)return $candidate;
        }
        return null;
    }
    private const SAFE_RULES=['composition_hero','composition_order','composition_collection','composition_records','composition_docs','composition_rail','composition_article','composition_waitlist','composition_collection_order','composition_navigation_order','composition_region_order','composition_hero_geometry','composition_workspace','composition_scope','composition_blueprint','tag_allowlist','attribute_allowlist','contrast_quality','section_count_low','section_count_high','hero_count','image_slot_count','fragment_target','aria_labelledby_target','aria_labelledby_value','class_tokens','icon_marker','photo_query','markup_structure','copy_duplicate','copy_mixed_language','copy_grammar','copy_scarcity','copy_inventory_status','copy_business_claim','copy_unconfirmed_product','copy_unconfirmed_credential','copy_lesson_claim','copy_inert_form','copy_empty_contact_section','copy_contact_placeholder','copy_in_person_visit','copy_repeated_facts','copy_pricing_hero_fact','copy_rule_missing','copy_rule_unrecognized','placeholder_link','css_empty','css_unsafe_syntax','css_nesting','css_media_rule','css_rule_syntax','css_selector_syntax','css_rule_limit','css_no_valid_rules','css_declaration_limit'];
    public function __construct(private readonly Store $store) {
        $this->expire();
    }
    private static function safeRule(?string $rule,?string $outcome=null):?string {
        if($rule===null)return $outcome==='copy_quality'?'copy_rule_missing':null;
        if($outcome==='contrast_quality')return 'contrast_quality';
        if(str_starts_with($rule,'fragment_target:'))return 'fragment_target';
        if(preg_match('/^tag_allowlist:([a-z][a-z0-9-]{0,31})$/D',$rule,$tagMatch)===1)return 'tag_allowlist:'.$tagMatch[1];
        if(preg_match('/^attribute_allowlist:[a-z][a-z0-9:_-]{0,31}$/D',$rule)===1)return 'attribute_allowlist';
        if(preg_match('/^css_rule_syntax:(?:unclosed_media|missing_open_brace|unclosed_rule|nested_rule)$/D',$rule)===1)return $rule;
        if(preg_match('/^taste:(?:hero_heading|section_headings|section_copy|hero_action|responsive_css)(?:,(?:hero_heading|section_headings|section_copy|hero_action|responsive_css))*$/D',$rule)===1)return $rule;
        if(str_starts_with($rule,'zero:')){
            $zeroRule=substr($rule,5);
            return in_array($zeroRule,['invalid_json','repair_invalid_json','invalid_element_list','repair_invalid_element_list','too_few_elements','invalid_usable_element_count','missing_required_photo','missing_explicit_copy','text_image_overlap','repair_incomplete'],true)?'zero:'.$zeroRule:null;
        }
        if(in_array($rule,self::SAFE_RULES,true))return $rule;
        return $outcome==='copy_quality'?'copy_rule_unrecognized':null;
    }
    private function expire(): void {
        $this->store->query("UPDATE ai_generation_jobs SET status='failed',diagnostics='{\"outcome\":\"expired\",\"attempts\":[]}',error=?,updated_at=? WHERE status IN ('queued','running','cancelling') AND created_at<?",['Время задачи истекло. Создайте новую генерацию.',time(),time()-660]);
        $this->store->query("DELETE FROM ai_generation_jobs WHERE status NOT IN ('queued','running','cancelling') AND updated_at<?",[time()-86400]);
    }
    public function create(string $projectId,string $owner,string $pageId,int $version,array $payload): array {
        $project=$this->store->owned($projectId,$owner);
        if($project['version']!==$version)throw new Problem(409,'Версия проекта изменилась. Обновите страницу.');
        if(!in_array($pageId,array_column($project['document']->data->pages,'id'),true))throw new Problem(404,'Страница не найдена.');
        $this->store->query('BEGIN IMMEDIATE');
        try{
            if($this->store->query("SELECT 1 FROM ai_generation_jobs WHERE project_id=? AND status IN ('queued','running','cancelling')",[$projectId])->fetchColumn())throw new Problem(409,'В проекте уже есть задача генерации. Восстановите её или дождитесь завершения.');
            $id=bin2hex(random_bytes(12));$now=time();
            $this->store->query("INSERT INTO ai_generation_jobs(id,project_id,owner_id,page_id,base_version,status,payload,created_at,updated_at) VALUES(?,?,?,?,?,'queued',?,?,?)",[$id,$projectId,$owner,$pageId,$version,json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$now,$now]);
            $this->store->query('COMMIT');
        }catch(\Throwable $error){$this->store->query('ROLLBACK');throw $error;}
        return $this->get($projectId,$owner,$id);
    }
    public function get(string $projectId,string $owner,?string $id=null,?string $pageId=null): ?array {
        $this->store->owned($projectId,$owner);$this->expire();
        $row=$id===null?$this->store->query('SELECT * FROM ai_generation_jobs WHERE project_id=? AND owner_id=? AND page_id=? ORDER BY created_at DESC,rowid DESC LIMIT 1',[$projectId,$owner,$pageId])->fetch():$this->store->query('SELECT * FROM ai_generation_jobs WHERE id=? AND project_id=? AND owner_id=?',[$id,$projectId,$owner])->fetch();
        if(!$row){if($id!==null)throw new Problem(404,'Задача не найдена.');return null;}
        $payload=json_decode($row['payload'],true,32,JSON_THROW_ON_ERROR);
        return ['id'=>$row['id'],'pageId'=>$row['page_id'],'baseVersion'=>(int)$row['base_version'],'status'=>$row['status'],'createdAt'=>(int)$row['created_at'],'request'=>['prompt'=>$payload['prompt'],'skillProfile'=>$payload['skillProfile'],'skillId'=>$payload['userSkill']['id']??null],'result'=>$row['result']===null?null:json_decode($row['result'],true,64,JSON_THROW_ON_ERROR),'diagnostics'=>$row['diagnostics']===null?null:json_decode($row['diagnostics'],true,16,JSON_THROW_ON_ERROR),'error'=>$row['error']];
    }
    public function history(string $projectId,string $owner,string $pageId): array {
        $project=$this->store->owned($projectId,$owner);$this->expire();
        if(!in_array($pageId,array_column($project['document']->data->pages,'id'),true))throw new Problem(404,'Страница не найдена.');
        $rows=$this->store->query('SELECT id,page_id,base_version,status,payload,diagnostics,created_at,updated_at,result IS NOT NULL AS has_result FROM ai_generation_jobs WHERE project_id=? AND owner_id=? AND page_id=? ORDER BY created_at DESC,rowid DESC LIMIT 20',[$projectId,$owner,$pageId])->fetchAll();
        return ['limit'=>20,'retentionHours'=>24,'items'=>array_map(static function(array $row):array{
            $payload=json_decode($row['payload'],true,32,JSON_THROW_ON_ERROR);
            $diagnostics=json_decode($row['diagnostics']??'{}',true,16);
            $templateReference=null;
            foreach(array_reverse(is_array($diagnostics['events']??null)?$diagnostics['events']:[]) as $event){
                if(!is_array($event)||($event['stage']??null)!=='open_design_reference')continue;
                $status=$event['referenceStatus']??null;
                $templateId=self::safeTemplateId($event['templateId']??null);
                if($templateId!==null&&in_array($status,['loaded','unavailable'],true))$templateReference=['templateId'=>$templateId,'referenceStatus'=>$status];
                break;
            }
            return ['id'=>$row['id'],'pageId'=>$row['page_id'],'baseVersion'=>(int)$row['base_version'],'status'=>$row['status'],'createdAt'=>(int)$row['created_at'],'updatedAt'=>(int)$row['updated_at'],'promptExcerpt'=>mb_substr($payload['prompt'],0,160),'skillName'=>$payload['userSkill']['name']??($payload['skillProfile']==='taste'?'Taste':'Стандартный'),'templateReference'=>$templateReference,'hasResult'=>(bool)$row['has_result']];
        },$rows)];
    }
    public function cancel(string $projectId,string $owner,string $id): array {
        $job=$this->get($projectId,$owner,$id);
        if(in_array($job['status'],['succeeded','failed'],true))throw new Problem(409,'Задача уже завершена.');
        $change=$this->store->query("UPDATE ai_generation_jobs SET status=CASE WHEN status='queued' THEN 'cancelled' ELSE 'cancelling' END,diagnostics=CASE WHEN status='queued' THEN '{\"outcome\":\"cancelled\",\"attempts\":[]}' ELSE diagnostics END,error=?,updated_at=? WHERE id=? AND project_id=? AND owner_id=? AND status IN ('queued','running')",['Генерация отменена. Проект не изменён. Уже потраченные токены могут быть учтены провайдером.',time(),$id,$projectId,$owner]);
        $updated=$this->get($projectId,$owner,$id);
        if($change->rowCount()===0&&in_array($updated['status'],['succeeded','failed'],true))throw new Problem(409,'Задача уже завершена.');
        return $updated;
    }
    public function shouldCancel(string $id): bool {
        $status=$this->store->query('SELECT status FROM ai_generation_jobs WHERE id=?',[$id])->fetchColumn();
        return $status===false||in_array($status,['cancelling','cancelled','failed'],true);
    }
    public function failLaunch(string $id): void {$this->store->query("UPDATE ai_generation_jobs SET status='failed',diagnostics='{\"outcome\":\"launcher\",\"attempts\":[]}',error=?,updated_at=? WHERE id=? AND status='queued'",['Не удалось запустить PHP worker. Проверьте конфигурацию сервера.',time(),$id]);}
    public function run(string $id,callable $providerFactory): bool {
        $claim=$this->store->query("UPDATE ai_generation_jobs SET status='running',updated_at=? WHERE id=? AND status='queued'",[time(),$id]);
        if($claim->rowCount()!==1)return false;
        $row=$this->store->query('SELECT * FROM ai_generation_jobs WHERE id=?',[$id])->fetch();
        $provider=null;$events=[];$currentStage=null;$failureStage=null;$terminalOutcome=null;$startedAt=microtime(true);$stageStartedAt=[];
        $diagnostics=static function(string $outcome,?string $validationRule=null)use(&$provider,&$events,&$currentStage,&$failureStage):string{
            return json_encode(['version'=>1,'outcome'=>$outcome,'stage'=>$currentStage,'failureStage'=>$failureStage,'validationRule'=>self::safeRule($validationRule,$outcome),'attempts'=>$provider instanceof ProfileFailoverProvider?$provider->diagnostics():[],'events'=>array_slice($events,-50)],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        };
        $recordStage=function(string $stage,string $state,array $meta=[])use(&$events,&$currentStage,&$failureStage,&$terminalOutcome,&$stageStartedAt,$startedAt,$diagnostics,$id):void{
            if(!in_array($stage,self::STAGES,true)||!in_array($state,['started','completed','failed','skipped'],true))return;
            $attempt=isset($meta['attempt'])&&is_int($meta['attempt'])?max(1,min(99,$meta['attempt'])):null;
            $key=$stage.':'.($attempt??0);$duration=null;
            if($state==='started'){$stageStartedAt[$key]=microtime(true);$currentStage=$stage;}
            elseif(isset($stageStartedAt[$key])){$duration=(int)round((microtime(true)-$stageStartedAt[$key])*1000);unset($stageStartedAt[$key]);}
            if($state==='failed'&&$stage!=='open_design_pipeline')$failureStage=$stage;
            $event=['stage'=>$stage,'state'=>$state,'elapsedMs'=>(int)round((microtime(true)-$startedAt)*1000)];
            if($duration!==null)$event['durationMs']=$duration;
            if($attempt!==null)$event['attempt']=$attempt;
            $reasonCode=is_string($meta['reasonCode']??null)?AiProviderFailure::publicCategory($meta['reasonCode']):null;
            if($reasonCode!==null)$event['reasonCode']=$reasonCode;
            if(isset($meta['decision'])&&in_array($meta['decision'],['repair_available','no_repair_rule','safe_fallback'],true))$event['decision']=$meta['decision'];
            $validationRule=is_string($meta['validationRule']??null)?$meta['validationRule']:null;
            if(($safeRule=self::safeRule($validationRule,$reasonCode))!==null)$event['validationRule']=$safeRule;
            if(isset($meta['validationEvidence'])&&is_array($meta['validationEvidence'])){
                $safeEvidence=AiProviderFailure::sanitizePersistedValidationEvidence(
                    is_string($meta['reasonCode']??null)?AiProviderFailure::publicCategory($meta['reasonCode']):'provider',
                    is_string($meta['validationRule']??null)?$meta['validationRule']:null,
                    $meta['validationEvidence']
                );
                if($safeEvidence!==null)$event['validationEvidence']=$safeEvidence;
            }
            if(isset($meta['referenceStatus'])&&in_array($meta['referenceStatus'],['loaded','unavailable','not_matched'],true))$event['referenceStatus']=$meta['referenceStatus'];
            if(($templateId=self::safeTemplateId($meta['templateId']??null))!==null)$event['templateId']=$templateId;
            if(($meta['revision']??null)===OpenDesignTemplateSource::REVISION)$event['revision']=OpenDesignTemplateSource::REVISION;
            if(($meta['fallbackArchetype']??null)==='service')$event['fallbackArchetype']='service';
            if(is_bool($meta['genericDefaults']??null))$event['genericDefaults']=$meta['genericDefaults'];
            if(in_array($meta['pipelineStage']??null,['no_match','ambiguous','unavailable','source_unavailable','loaded','blueprint_extracted','blueprint_in_prompt','output_conforms','validation_rejected','preview_ready'],true))$event['pipelineStage']=$meta['pipelineStage'];
            $events[]= $event;if(count($events)>50)$events=array_slice($events,-50);
            try{$this->store->query("UPDATE ai_generation_jobs SET diagnostics=?,updated_at=? WHERE id=? AND status IN ('running','succeeded')",[$diagnostics($terminalOutcome??'running'),time(),$id]);}catch(\Throwable){error_log('Juzimder AI: generation stage diagnostics could not be persisted.');}
        };
        $runStage=static function(string $stage,callable $operation)use($recordStage):mixed{
            $recordStage($stage,'started');
            try{$result=$operation();$recordStage($stage,'completed');return $result;}
            catch(\Throwable $error){$meta=['reasonCode'=>$error instanceof AiProviderFailure?AiProviderFailure::publicCategory($error->category):'internal'];if($error instanceof AiProviderFailure&&$error->validationRule!==null)$meta['validationRule']=$error->validationRule;if($error instanceof AiProviderFailure&&($evidence=$error->safeValidationEvidence())!==null)$meta['validationEvidence']=$evidence;$recordStage($stage,'failed',$meta);throw $error;}
        };
        try{
            $project=$runStage('project_check',fn()=>$this->store->owned($row['project_id'],$row['owner_id']));
            if($project['version']!==(int)$row['base_version'])throw new Problem(409,'Проект изменился до запуска задачи. Создайте новую генерацию.');
            $payload=$runStage('payload_load',fn()=>json_decode($row['payload'],true,32,JSON_THROW_ON_ERROR));
            $provider=$runStage('provider_setup',fn()=>$providerFactory($row['project_id'],$row['owner_id']));
            $tracedProvider=new AiGenerationTracingProvider($provider,$recordStage);
            $result=AiGeneratedWebsite::create($payload['prompt'],$tracedProvider,$payload['skillProfile'],$payload['userSkill'],$payload['projectDesignTokens']??null,null,$recordStage);
            $result=$runStage('photo_catalog',fn()=>AiPhotoSelection::enrich($result,$tracedProvider,null,$payload['prompt']));
            $recordStage('result_persistence','started');
            $this->store->query("UPDATE ai_generation_jobs SET status='succeeded',result=?,diagnostics=?,updated_at=? WHERE id=? AND status='running'",[json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$diagnostics('succeeded'),time(),$id]);
            $terminalOutcome='succeeded';$recordStage('result_persistence','completed');
            $reference=$result['metadata']['openDesignReference']??[];
            $recordStage('open_design_pipeline','completed',['pipelineStage'=>'preview_ready','templateId'=>$reference['templateId']??null,'revision'=>$reference['revision']??null,'genericDefaults'=>($reference['status']??null)!=='loaded' ,'attempt'=>!empty($result['metadata']['repairAttempted'])?2:1]);
        }catch(\Throwable $error){
            $category=$error instanceof AiProviderFailure?AiProviderFailure::publicCategory($error->category):($error instanceof Problem&&$error->status===409?'project_changed':'internal');
            $message=$error instanceof AiProviderFailure?$error->userMessage():($error instanceof Problem?$error->getMessage():'Не удалось завершить генерацию. Проект не изменён.');
            if($failureStage===null&&$currentStage!==null){$meta=['reasonCode'=>$category];if($error instanceof AiProviderFailure&&$error->validationRule!==null)$meta['validationRule']=$error->validationRule;if($error instanceof AiProviderFailure&&($evidence=$error->safeValidationEvidence())!==null)$meta['validationEvidence']=$evidence;$recordStage($currentStage,'failed',$meta);}
            $this->store->query("UPDATE ai_generation_jobs SET status='failed',error=?,diagnostics=?,updated_at=? WHERE id=? AND status='running'",[$message,$diagnostics($category,$error instanceof AiProviderFailure?$error->validationRule:null),time(),$id]);
        }
        $this->store->query("UPDATE ai_generation_jobs SET status='cancelled',result=NULL,diagnostics=?,updated_at=? WHERE id=? AND status='cancelling'",[$diagnostics('cancelled'),time(),$id]);
        return true;
    }
    public function launch(string $id): void {
        if(!in_array(PHP_SAPI,['cli','cli-server'],true)||!function_exists('exec')){$this->failLaunch($id);return;}
        $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/../scripts/ai-generation-worker.php').' '.escapeshellarg($this->store->path).' '.escapeshellarg($id).' > /dev/null 2>&1 &';
        exec($command,$unused,$code);if($code!==0)$this->failLaunch($id);
    }
}
