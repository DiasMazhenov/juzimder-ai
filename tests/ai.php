<?php
declare(strict_types=1);
require __DIR__.'/../backend/Ai.php';
require __DIR__.'/../backend/Domain.php';
require __DIR__.'/../backend/AiCredentials.php';
use Juzimder\{AiHttpResponse, AiHttpTransport, AiProviderFailure, AiDesignPlan, AiDesignPlanner, AiCredentialVault, OpenRouterProvider, OpenRouterSettings, Store};

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
    $secondStatus=$vault->createProfile($vaultProject['id'],'vault-owner',1,'Рабочий','sk-vault-second-test','anthropic/test-model');
    $secondProfileId=$secondStatus['activeProfileId'];
    $secondPublicProfile=array_values(array_filter($secondStatus['profiles'],static fn($profile)=>$profile['id']===$secondProfileId))[0]??null;
    aiCheck(count($secondStatus['profiles'])===2&&$secondStatus['version']===2&&$secondPublicProfile!==null,'Project can store and activate multiple independent AI profiles');
    $secondStatus=$vault->updateProfile($vaultProject['id'],'vault-owner',2,$secondProfileId,'Рабочая модель','', 'anthropic/updated-model');
    $secondPublicProfile=array_values(array_filter($secondStatus['profiles'],static fn($profile)=>$profile['id']===$secondProfileId))[0]??null;
    aiCheck($secondPublicProfile['name']==='Рабочая модель'&&$secondPublicProfile['model']==='anthropic/updated-model'&&$secondStatus['version']===3,'Profile metadata and model can be edited while keeping its encrypted key');
    $secondStatus=$vault->updateProfile($vaultProject['id'],'vault-owner',3,$secondProfileId,'Рабочая модель','sk-vault-second-replacement','anthropic/updated-model');
    $secondSettings=$vault->settings($vaultProject['id'],'vault-owner');
    aiCheck($secondSettings?->apiKey==='sk-vault-second-replacement'&&$secondStatus['version']===4,'Editing a profile can replace its API key');
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
    aiCheck($settings !== null && $settings->publicStatus() === ['provider'=>'openrouter','configured'=>true,'model'=>'openai/test-model'], 'Public provider status never contains the API key');

    $plan = [
        'siteType'=>'Клиника', 'audience'=>'Семьи и взрослые', 'goal'=>'Запись на приём', 'style'=>'Светлый современный стиль',
        'colors'=>['ink'=>'#1A2824','forest'=>'#1F5D4B','canvas'=>'#F4F8F6','paper'=>'#FFFFFF','body'=>'#34433E','muted'=>'#70817A','line'=>'#DDE5E0','accent'=>'#2A8C7A'],
        'sections'=>['navigation','hero','features','testimonial','faq','cta','footer'], 'responsiveNotes'=>'На телефоне карточки идут в один столбец.',
    ];
    $successBody = json_encode(['model'=>'openai/resolved-model','choices'=>[['message'=>['content'=>json_encode($plan,JSON_UNESCAPED_UNICODE)]]],'usage'=>['prompt_tokens'=>82,'completion_tokens'=>166,'total_tokens'=>248,'cost'=>0.00125]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    $transport = new FakeAiTransport([new AiHttpResponse(200,$successBody)]);
    $provider = new OpenRouterProvider($settings, $transport);
    $result = AiDesignPlanner::create('Создай сайт клиники', $provider);
    $request = $transport->calls[0]; $payload = json_decode($request['body'],true,32,JSON_THROW_ON_ERROR);
    aiCheck($request['url']==='https://openrouter.ai/api/v1/chat/completions' && in_array('Authorization: Bearer sk-test-only',$request['headers'],true), 'Provider sends the server-only bearer key to the fixed OpenRouter endpoint');
    aiCheck($payload['max_completion_tokens']===1200 && $payload['usage']['include']===true && $payload['provider']['require_parameters']===true && $payload['response_format']['json_schema']['strict']===true, 'Provider caps output, requests usage metadata and strict structured output');
    aiCheck($payload['response_format']['json_schema']['schema']['properties']['colors']['required']===['ink','forest','canvas','paper','body','muted','line','accent'], 'Structured schema covers all eight project design tokens');
    aiCheck($request['timeoutSeconds']===25 && $result['metadata']['resolvedModel']==='openai/resolved-model' && $result['metadata']['usage']['costUsd']===0.00125, 'Provider reports the fixed timeout, resolved model, token usage and upstream cost');
    aiCheck($result['plan']['sections'][1]==='hero' && $result['plan']['colors']['accent']==='#2A8C7A', 'Design plan is normalized into supported section and color values');

    $retryTransport = new FakeAiTransport([new AiHttpResponse(429,'{}'),new AiHttpResponse(200,$successBody)]);
    (new OpenRouterProvider($settings,$retryTransport))->completeJson('system','user',AiDesignPlan::schema(),'juzimder_design_plan');
    aiCheck(count($retryTransport->calls)===2, 'Provider retries one explicit transient rate-limit response');

    $failedTransport = new FakeAiTransport([new AiHttpResponse(401,'sk-secret-must-not-leak')]);
    try { (new OpenRouterProvider($settings,$failedTransport))->completeJson('system','user',AiDesignPlan::schema(),'juzimder_design_plan'); throw new RuntimeException('Expected upstream failure.'); }
    catch (AiProviderFailure $error) { aiCheck(!str_contains($error->getMessage(),'sk-secret') && count($failedTransport->calls)===1, 'Provider errors are redacted and non-retryable authorization failures are not retried'); }

    $badPlan = $plan; $badPlan['sections'] = ['hero','unknown','faq'];
    aiRejects(fn()=>AiDesignPlan::parseProviderOutput(json_encode($badPlan,JSON_THROW_ON_ERROR)), 'Provider output rejects unsupported section types');
    $badPlan = $plan; $badPlan['sections'] = ['features','faq','cta'];
    aiRejects(fn()=>AiDesignPlan::parseProviderOutput(json_encode($badPlan,JSON_THROW_ON_ERROR)), 'Provider output requires a Hero section');
    $badPlan = $plan; $badPlan['colors']['accent'] = 'red';
    aiRejects(fn()=>AiDesignPlan::parseProviderOutput(json_encode($badPlan,JSON_THROW_ON_ERROR)), 'Provider output rejects invalid token colors');
    $badPlan = $plan; $badPlan['sections'] = ['hero','features','hero'];
    aiRejects(fn()=>AiDesignPlan::parseProviderOutput(json_encode($badPlan,JSON_THROW_ON_ERROR)), 'Provider output rejects duplicate sections');
    aiRejects(fn()=>AiDesignPlan::parseProviderOutput('{"siteType":"unclosed"'), 'Provider output rejects malformed JSON');
