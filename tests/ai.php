<?php
declare(strict_types=1);
require __DIR__.'/../backend/Ai.php';
require __DIR__.'/../backend/Domain.php';
require __DIR__.'/../backend/AiCredentials.php';
use Juzimder\{AiHttpResponse, AiHttpTransport, AiProviderFailure, AiDesignPlan, AiDesignPlanner, AiGeneratedWebsite, AiCredentialVault, OpenRouterProvider, OpenRouterSettings, Store};

function aiCheck(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); echo "PASS $message\n"; }
function aiRejects(callable $fn, string $message): void { try { $fn(); } catch (AiProviderFailure) { aiCheck(true, $message); return; } throw new RuntimeException('Expected provider failure: '.$message); }
final class FakeAiTransport implements AiHttpTransport {
    public array $calls = [];
    public function __construct(private array $responses) {}
    public function post(string $url, array $headers, string $body, int $timeoutSeconds): AiHttpResponse {
        $this->calls[] = compact('url','headers','body','timeoutSeconds');
        $next = array_shift($this->responses);
        if ($next instanceof \Throwable) throw $next;
        return $next;
    }
}

$vaultDb = sys_get_temp_dir().'/juzimder-ai-vault-'.bin2hex(random_bytes(6)).'.sqlite';
try {
    $vaultStore = new Store($vaultDb);
    $vaultStore->query('INSERT INTO users(id,email,password_hash) VALUES(?,?,?)',['vault-owner','vault@example.test','hash']);
    $vaultProject = $vaultStore->create('vault-owner','AI vault test');
    $vault = new AiCredentialVault($vaultStore);
    $savedStatus = $vault->save($vaultProject['id'],'vault-owner',0,'sk-vault-round-trip-test','openai/test-model');
    $restoredSettings = $vault->settings($vaultProject['id'],'vault-owner');
    aiCheck($savedStatus['configured'] && $savedStatus['version']===1 && count($savedStatus['profiles'])===1 && $restoredSettings?->apiKey==='sk-vault-round-trip-test', 'Encrypted project AI credential decrypts for backend provider use');
    $primaryProfileId=$savedStatus['profiles'][0]['id'];
    $secondStatus=$vault->createProfile($vaultProject['id'],'vault-owner',1,'Рабочий','sk-vault-second-test','anthropic/test-model','openai/fallback-model');
    $secondProfileId=$secondStatus['activeProfileId'];
    $secondPublicProfile=array_values(array_filter($secondStatus['profiles'],static fn($profile)=>$profile['id']===$secondProfileId))[0]??null;
    aiCheck(count($secondStatus['profiles'])===2&&$secondStatus['version']===2&&$secondPublicProfile!==null&&$secondPublicProfile['fallbackModel']==='openai/fallback-model'&&$secondStatus['fallbackModel']==='openai/fallback-model','Project stores the optional fallback model with its profile');
    $secondStatus=$vault->updateProfile($vaultProject['id'],'vault-owner',2,$secondProfileId,'Рабочая модель','', 'anthropic/updated-model');
    $secondPublicProfile=array_values(array_filter($secondStatus['profiles'],static fn($profile)=>$profile['id']===$secondProfileId))[0]??null;
    aiCheck($secondPublicProfile['name']==='Рабочая модель'&&$secondPublicProfile['model']==='anthropic/updated-model'&&$secondPublicProfile['fallbackModel']==='openai/fallback-model'&&$secondStatus['version']===3,'Profile edits preserve an existing fallback model when older clients omit it');
    $secondStatus=$vault->updateProfile($vaultProject['id'],'vault-owner',3,$secondProfileId,'Рабочая модель','sk-vault-second-replacement','anthropic/updated-model','openai/alternate-fallback');
    $secondSettings=$vault->settings($vaultProject['id'],'vault-owner');
    aiCheck($secondSettings?->apiKey==='sk-vault-second-replacement'&&$secondSettings?->fallbackModel==='openai/alternate-fallback'&&$secondStatus['version']===4,'Editing a profile can replace its API key and fallback model');
    $activeStatus=$vault->activateProfile($vaultProject['id'],'vault-owner',4,$primaryProfileId);
    $activeSettings=$vault->settings($vaultProject['id'],'vault-owner');
    aiCheck($activeStatus['activeProfileId']===$primaryProfileId&&$activeSettings?->apiKey==='sk-vault-round-trip-test','AI provider uses the selected active profile');
    $deletedProfileStatus=$vault->deleteProfile($vaultProject['id'],'vault-owner',5,$primaryProfileId);
    aiCheck($deletedProfileStatus['configured']&&$deletedProfileStatus['activeProfileId']===$secondProfileId&&$deletedProfileStatus['version']===6,'Deleting the active profile selects a remaining profile');
    $ciphertext = $vaultStore->query('SELECT api_key_ciphertext FROM project_ai_profiles WHERE project_id=? AND id=?',[$vaultProject['id'],$secondProfileId])->fetchColumn();
    aiCheck(is_string($ciphertext) && !str_contains($ciphertext,'sk-vault-second-test') && !str_contains($ciphertext,'sk-vault-second-replacement'), 'Project AI key is encrypted in its dedicated profile row');
    $keyMode = fileperms($vaultDb.'.ai-key') & 0777;
    aiCheck($keyMode===0600, 'AI encryption key sidecar uses owner-only file permissions');
    $cleared = $vault->clear($vaultProject['id'],'vault-owner',6);
    aiCheck(!$cleared['configured'] && $cleared['version']===7, 'AI profile deletion advances its version without resetting stale-write protection');
} finally {
    @unlink($vaultDb);
    @unlink($vaultDb.'.ai-key');
    @unlink($vaultDb.'-journal');
}

    aiCheck(OpenRouterSettings::fromCredentials('', 'openai/test-model') === null, 'Provider stays unavailable until project credentials are configured');
    $settings = OpenRouterSettings::fromCredentials('sk-test-only', 'openai/test-model');
    aiCheck($settings !== null && $settings->publicStatus() === ['provider'=>'openrouter','configured'=>true,'model'=>'openai/test-model','fallbackModel'=>null], 'Public provider status never contains the API key');
    aiCheck(OpenRouterSettings::fromCredentials('sk-test-only','openai/test-model','bad fallback model')===null&&OpenRouterSettings::fromCredentials('sk-test-only','openai/test-model','openai/test-model')===null,'Provider configuration rejects invalid or duplicate fallback model IDs');

    $plan = [
        'siteType'=>'Клиника', 'audience'=>'Семьи и взрослые', 'goal'=>'Запись на приём', 'style'=>'Светлый современный стиль',
        'colors'=>['ink'=>'#1A2824','forest'=>'#1F5D4B','canvas'=>'#F4F8F6','paper'=>'#FFFFFF','body'=>'#34433E','muted'=>'#70817A','line'=>'#DDE5E0','accent'=>'#2A8C7A'],
        'sections'=>['navigation','hero','features','testimonial','faq','cta','footer'], 'responsiveNotes'=>'На телефоне карточки идут в один столбец.',
    ];
    $textCounts=['navigation'=>5,'hero'=>4,'features'=>9,'process'=>11,'testimonial'=>4,'faq'=>8,'cta'=>3,'text'=>1,'footer'=>3];
    $plan['content']=array_map(static fn(string $section): array=>['section'=>$section,'texts'=>array_map(static fn(int $index): string=>$section.' copy '.($index+1),range(0,$textCounts[$section]-1))],$plan['sections']);
    $successBody = json_encode(['model'=>'openai/resolved-model','choices'=>[['message'=>['content'=>json_encode($plan,JSON_UNESCAPED_UNICODE)]]],'usage'=>['prompt_tokens'=>82,'completion_tokens'=>166,'total_tokens'=>248,'cost'=>0.00125]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    $transport = new FakeAiTransport([new AiHttpResponse(200,$successBody)]);
    $provider = new OpenRouterProvider($settings, $transport);
    $result = AiDesignPlanner::create('Создай сайт клиники', $provider);
    $request = $transport->calls[0]; $payload = json_decode($request['body'],true,32,JSON_THROW_ON_ERROR);
    aiCheck($request['url']==='https://openrouter.ai/api/v1/chat/completions' && in_array('Authorization: Bearer sk-test-only',$request['headers'],true), 'Provider sends the server-only bearer key to the fixed OpenRouter endpoint');
    aiCheck($payload['max_tokens']===3200 && !array_key_exists('max_completion_tokens',$payload) && $payload['usage']['include']===true && $payload['provider']['require_parameters']===true && $payload['response_format']['json_schema']['strict']===true && !array_key_exists('reasoning',$payload), 'Provider uses broadly supported token cap with strict structured output and parameter-aware routing');
    aiCheck($payload['response_format']['json_schema']['schema']['properties']['colors']['required']===['ink','forest','canvas','paper','body','muted','line','accent'], 'Structured schema covers all eight project design tokens');
    aiCheck(in_array('content',$payload['response_format']['json_schema']['schema']['required'],true)&&$result['plan']['content'][1]['texts'][1]==='hero copy 2','Structured schema returns validated text for every planned section');
    $allSectionsPlan=$plan;
    $allSectionsPlan['sections']=array_keys($textCounts);
    $allSectionsPlan['content']=array_map(static fn(string $section): array=>['section'=>$section,'texts'=>array_map(static fn(int $index): string=>$section.' copy '.($index+1),range(0,$textCounts[$section]-1))],$allSectionsPlan['sections']);
    aiCheck(count(AiDesignPlan::parseProviderOutput(json_encode($allSectionsPlan,JSON_THROW_ON_ERROR))['content'])===9,'Server validation supports generated text slots in every built-in section');
    aiCheck($request['timeoutSeconds']===25 && $result['metadata']['resolvedModel']==='openai/resolved-model' && $result['metadata']['usage']['costUsd']===0.00125, 'Provider reports the fixed timeout, resolved model, token usage and upstream cost');
    aiCheck($result['plan']['sections'][1]==='hero' && $result['plan']['colors']['accent']==='#2A8C7A', 'Design plan is normalized into supported section and color values');

    $nemotronSettings=OpenRouterSettings::fromCredentials('sk-test-only','nvidia/nemotron-3.5-super-vl:free');
    $nemotronTransport=new FakeAiTransport([new AiHttpResponse(200,$successBody)]);
    (new OpenRouterProvider($nemotronSettings,$nemotronTransport))->completeJson('system','user',AiDesignPlan::schema(),'juzimder_design_plan');
    $nemotronRequest=json_decode($nemotronTransport->calls[0]['body'],true,32,JSON_THROW_ON_ERROR);
    aiCheck($nemotronRequest['reasoning']===['enabled'=>false], 'Nemotron structured requests disable default reasoning so visible JSON retains its completion-token budget');

    $generatedDesign=[
        'siteType'=>'Клиника', 'audience'=>'Семьи и взрослые', 'goal'=>'Запись на приём', 'style'=>'Светлый современный стиль',
        'html'=>'<header class="ai-nav"><nav><a class="ai-link" href="#contact">Записаться</a></nav></header><section class="ai-hero"><div><h1 class="ai-title">Забота о здоровье</h1><p class="ai-copy">Современная клиника рядом с вами.</p></div></section><section class="ai-features"><article class="ai-card"><h2>Опытные врачи</h2><p>Внимание к каждому пациенту.</p></article></section><section class="ai-contact" id="contact"><h2>Мы рядом</h2><p>Запишитесь на консультацию.</p></section>',
        'css'=>'.ai-hero{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px;padding:64px 32px;background-color:#f4f8f6;color:#1a2824}.ai-title{font-size:clamp(32px,5vw,64px);line-height:1.1}.ai-features{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px}.ai-contact{padding:48px 24px}@media (max-width: 768px){.ai-hero{grid-template-columns:1fr;padding:40px 20px}}',
    ];
    $generatedBody=json_encode(['model'=>'openai/resolved-model','choices'=>[['message'=>['content'=>json_encode($generatedDesign,JSON_UNESCAPED_UNICODE)]]],'usage'=>['prompt_tokens'=>420,'completion_tokens'=>1800,'total_tokens'=>2220,'cost'=>0.002]],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
    $generatedTransport=new FakeAiTransport([new AiHttpResponse(200,$generatedBody)]);
    $generated=AiGeneratedWebsite::create('Сайт клиники в современном стиле',new OpenRouterProvider($settings,$generatedTransport));
    $generatedRequest=json_decode($generatedTransport->calls[0]['body'],true,32,JSON_THROW_ON_ERROR);
    $generatedOutput=$generated['design'];
    preg_match('/class="(ai-scope-[a-f0-9]+)"/',$generatedOutput['html'],$scopeMatch);
    aiCheck($generatedRequest['max_tokens']===9000&&$generatedRequest['response_format']['json_schema']['schema']['required']===['siteType','audience','goal','style','html','css'],'Full-page generation reserves enough output tokens for a strict structured response');
    aiCheck($generatedOutput['sectionCount']===3&&isset($scopeMatch[1])&&str_contains($generatedOutput['html'],'href="#'.$scopeMatch[1].'-contact"')&&str_contains($generatedOutput['html'],'id="'.$scopeMatch[1].'-contact"'),'Generated page keeps its section structure and scopes in-page links');
    aiCheck(str_contains($generatedOutput['css'],'.'.$scopeMatch[1].' .ai-hero')&&str_contains($generatedOutput['css'],'@media (max-width: 768px)')&&$generated['metadata']['usage']['completionTokens']===1800,'Generated responsive CSS is scoped and provider usage remains available for preview');
    $plainActions=$generatedDesign;$plainActions['html']=str_replace('<a class="ai-link" href="#contact">Записаться</a>','<a href="#contact">Записаться</a><button type="button">Записаться</button>', $plainActions['html']);
    $plainActionOutput=AiGeneratedWebsite::parseProviderOutput(json_encode($plainActions,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
    aiCheck(str_contains($plainActionOutput['html'],'<a href="#')&&str_contains($plainActionOutput['html'],'<button type="button">Записаться</button>'),'Generated links and buttons without classes remain safe');
    $badGenerated=$plainActions;$badGenerated['html']=str_replace('type="button"','type="submit"',$badGenerated['html']);
    aiRejects(fn()=>AiGeneratedWebsite::parseProviderOutput(json_encode($badGenerated,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)),'Generated button types stay restricted to inert buttons');
    $richGenerated=$generatedDesign;$richGenerated['css'].='.ai-card{background:linear-gradient(135deg, #1A2824 0%, #F4F8F6 100%);box-shadow:0 16px 32px #1A2824;position:relative;text-transform:uppercase;font-style:italic}';
    $richOutput=AiGeneratedWebsite::parseProviderOutput(json_encode($richGenerated,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
    aiCheck(str_contains($richOutput['css'],'linear-gradient(135deg, #1A2824 0%, #F4F8F6 100%)')&&str_contains($richOutput['css'],'box-shadow:0 16px 32px #1A2824')&&str_contains($richOutput['css'],'text-transform:uppercase')&&str_contains($richOutput['css'],'font-style:italic'),'Generated CSS preserves safe expressive gradients, shadows and typography');
    $fencedOutput=AiGeneratedWebsite::parseProviderOutput("```json\n".json_encode($generatedDesign,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)."\n```");
    aiCheck($fencedOutput['sectionCount']===3,'Generated page safely accepts a JSON-only Markdown code fence');
    $tagStyles=$generatedDesign;$tagStyles['css'].='ul{list-style-type:none}.ai-hero h1{text-transform:uppercase}.ai-nav a{color:#1A2824;text-decoration:none}iframe{color:#000000}*{color:#000000}';
    $tagStylesOutput=AiGeneratedWebsite::parseProviderOutput(json_encode($tagStyles,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
    preg_match('/class="(ai-scope-[a-f0-9]+)"/',$tagStylesOutput['html'],$tagScopeMatch);
    aiCheck(str_contains($tagStylesOutput['css'],'.'.$tagScopeMatch[1].' ul{list-style-type:none}')&&str_contains($tagStylesOutput['css'],'.'.$tagScopeMatch[1].' .ai-hero h1{text-transform:uppercase}')&&str_contains($tagStylesOutput['css'],'.'.$tagScopeMatch[1].' .ai-nav a{color:#1A2824;text-decoration:none}')&&!str_contains($tagStylesOutput['css'],'iframe')&&!str_contains($tagStylesOutput['css'],'*{color:'),'Generated page safely scopes allowlisted tag and descendant selectors');
    $badGenerated=$generatedDesign;$badGenerated['html']=str_replace('<header','<script>alert(1)</script><header',$badGenerated['html']);
    aiRejects(fn()=>AiGeneratedWebsite::parseProviderOutput(json_encode($badGenerated,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)),'Generated markup rejects executable tags');
    $badGenerated=$generatedDesign;$badGenerated['html']=str_replace('class="ai-link"','class="ai-link" onclick="alert(1)"',$badGenerated['html']);
    aiRejects(fn()=>AiGeneratedWebsite::parseProviderOutput(json_encode($badGenerated,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)),'Generated markup rejects event-handler attributes');
    $badGenerated=$generatedDesign;$badGenerated['css'].='.ai-title:hover{color:#000000}';
    $sanitizedPseudo=AiGeneratedWebsite::parseProviderOutput(json_encode($badGenerated,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
    aiCheck(!str_contains($sanitizedPseudo['css'],':hover')&&str_contains($sanitizedPseudo['css'],'.ai-title{font-size:'),'Generated CSS drops unsupported selectors while preserving valid styles');
    $badGenerated=$generatedDesign;$badGenerated['css'].='.ai-title{filter:blur(4px);font-family:"Playfair Display",serif;font-size:40px}';
    $sanitizedDeclarations=AiGeneratedWebsite::parseProviderOutput(json_encode($badGenerated,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
    aiCheck(!str_contains($sanitizedDeclarations['css'],'filter')&&!str_contains($sanitizedDeclarations['css'],'Playfair Display')&&str_contains($sanitizedDeclarations['css'],'font-size:40px'),'Generated CSS drops unsafe or unsupported declarations but keeps allowlisted declarations');
    $badGenerated=$generatedDesign;$badGenerated['css'].='.ai-card{background:linear-gradient(135deg, #1A2824 0%, url(https://example.test/image.png) 100%)}';
    $sanitizedGradient=AiGeneratedWebsite::parseProviderOutput(json_encode($badGenerated,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
    aiCheck(!str_contains($sanitizedGradient['css'],'url(')&&!str_contains($sanitizedGradient['css'],'example.test'),'Generated CSS rejects external resources hidden inside gradients');
    $badGenerated=$generatedDesign;$badGenerated['css']=str_replace('background-color:#f4f8f6','background-color:url(https://example.test/a.png)',$badGenerated['css']);
    $sanitizedUrl=AiGeneratedWebsite::parseProviderOutput(json_encode($badGenerated,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
    aiCheck(!str_contains($sanitizedUrl['css'],'url(')&&!str_contains($sanitizedUrl['css'],'example.test'),'Generated CSS strips external-resource declarations');
    $badGenerated=$generatedDesign;$badGenerated['html']=str_replace('href="#contact"','href="#missing"',$badGenerated['html']);
    aiRejects(fn()=>AiGeneratedWebsite::parseProviderOutput(json_encode($badGenerated,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)),'Generated page rejects unresolved in-page links');

    $retryTransport = new FakeAiTransport([new AiHttpResponse(429,'{}'),new AiHttpResponse(200,$successBody)]);
    (new OpenRouterProvider($settings,$retryTransport))->completeJson('system','user',AiDesignPlan::schema(),'juzimder_design_plan');
    aiCheck(count($retryTransport->calls)===2, 'Provider retries one explicit transient rate-limit response');

    $fallbackSettings=OpenRouterSettings::fromCredentials('sk-test-only','openai/primary-model','openai/fallback-model');
    $fallbackTransport=new FakeAiTransport([new AiHttpResponse(503,'{}'),new AiHttpResponse(503,'{}'),new AiHttpResponse(200,$successBody)]);
    $fallbackResult=AiDesignPlanner::create('Создай сайт клиники',new OpenRouterProvider($fallbackSettings,$fallbackTransport));
    $fallbackModels=array_map(static fn($call)=>json_decode($call['body'],true,32,JSON_THROW_ON_ERROR)['model'],$fallbackTransport->calls);
    aiCheck($fallbackModels===['openai/primary-model','openai/primary-model','openai/fallback-model']&&$fallbackResult['metadata']['resolvedModel']==='openai/resolved-model','Configured fallback runs after the primary model exhausts its transient retry');
    $networkFallbackTransport=new FakeAiTransport([new RuntimeException('network interruption'),new RuntimeException('network interruption'),new AiHttpResponse(200,$successBody)]);
    (new OpenRouterProvider($fallbackSettings,$networkFallbackTransport))->completeJson('system','user',AiDesignPlan::schema(),'juzimder_design_plan');
    $networkModels=array_map(static fn($call)=>json_decode($call['body'],true,32,JSON_THROW_ON_ERROR)['model'],$networkFallbackTransport->calls);
    aiCheck($networkModels===['openai/primary-model','openai/primary-model','openai/fallback-model'],'Transport failures get one primary retry before switching to fallback');

    $failedTransport = new FakeAiTransport([new AiHttpResponse(401,'sk-secret-must-not-leak')]);
    try { (new OpenRouterProvider($fallbackSettings,$failedTransport))->completeJson('system','user',AiDesignPlan::schema(),'juzimder_design_plan'); throw new RuntimeException('Expected upstream failure.'); }
    catch (AiProviderFailure $error) { aiCheck(!str_contains($error->getMessage(),'sk-secret') && count($failedTransport->calls)===1, 'Provider errors are redacted and non-retryable authorization failures are not retried'); }

    $badPlan = $plan; $badPlan['sections'] = ['hero','unknown','faq'];
    aiRejects(fn()=>AiDesignPlan::parseProviderOutput(json_encode($badPlan,JSON_THROW_ON_ERROR)), 'Provider output rejects unsupported section types');
    $badPlan = $plan; $badPlan['sections'] = ['features','faq','cta'];
    aiRejects(fn()=>AiDesignPlan::parseProviderOutput(json_encode($badPlan,JSON_THROW_ON_ERROR)), 'Provider output requires a Hero section');
    $badPlan = $plan; $badPlan['colors']['accent'] = 'red';
    aiRejects(fn()=>AiDesignPlan::parseProviderOutput(json_encode($badPlan,JSON_THROW_ON_ERROR)), 'Provider output rejects invalid token colors');
    $badPlan = $plan; $badPlan['sections'] = ['hero','features','hero'];
    aiRejects(fn()=>AiDesignPlan::parseProviderOutput(json_encode($badPlan,JSON_THROW_ON_ERROR)), 'Provider output rejects duplicate sections');
    $badPlan = $plan; $badPlan['content'][1]['texts'] = array_slice($badPlan['content'][1]['texts'],1);
    aiRejects(fn()=>AiDesignPlan::parseProviderOutput(json_encode($badPlan,JSON_THROW_ON_ERROR)), 'Provider output rejects missing section text fields');
    $badPlan = $plan; $badPlan['content'][1]['texts'][1] = '<h1>Markup</h1>';
    aiRejects(fn()=>AiDesignPlan::parseProviderOutput(json_encode($badPlan,JSON_THROW_ON_ERROR)), 'Provider output rejects HTML in generated text');
    $badPlan = $plan; $badPlan['content'][1]['texts'][1] = 'https://example.test';
    aiRejects(fn()=>AiDesignPlan::parseProviderOutput(json_encode($badPlan,JSON_THROW_ON_ERROR)), 'Provider output rejects generated URL strings');
    $badPlan = $plan; $badPlan['content'][0]['section'] = 'faq';
    aiRejects(fn()=>AiDesignPlan::parseProviderOutput(json_encode($badPlan,JSON_THROW_ON_ERROR)), 'Provider output rejects duplicate or unselected section copy');
    aiRejects(fn()=>AiDesignPlan::parseProviderOutput('{"siteType":"unclosed"'), 'Provider output rejects malformed JSON');
