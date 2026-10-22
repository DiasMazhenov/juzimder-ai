<?php
 declare(strict_types=1);
require __DIR__.'/../backend/Ai.php';
require __DIR__.'/../backend/SkillAnalyzer.php';
use Juzimder\{SkillAnalyzer,StructuredAiProvider,AiProviderFailure,SkillRequirements};
function analysisCheck(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);echo "PASS $message\n";}
final class AnalysisProvider implements StructuredAiProvider {
    public array $calls=[];
    public function __construct(public array $output){}
    public function completeJson(string $systemPrompt,string $userPrompt,array $schema,string $schemaName,int $maxTokens=3200): array {
        $this->calls[]=compact('systemPrompt','userPrompt','schema','maxTokens');
        return ['content'=>json_encode($this->output,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),'model'=>'test/model'];
    }
}
$source="# Design\nUse exactly one H1.\nMake the layout calm and readable.";
$items=[
    ['id'=>'one-h1','label'=>'Один H1','sourceQuote'=>'Use exactly one H1.','mode'=>'automatic','ruleJson'=>json_encode(['id'=>'one-h1','label'=>'Один H1','type'=>'heading_count','tag'=>'h1','count'=>1],JSON_UNESCAPED_UNICODE)],
    ['id'=>'calm','label'=>'Спокойная читаемая композиция','sourceQuote'=>'Make the layout calm and readable.','mode'=>'manual','ruleJson'=>''],
];
$provider=new AnalysisProvider(['items'=>$items]);$draft=SkillAnalyzer::create($source,$provider);
analysisCheck(count(SkillRequirements::parse($draft['content']))===1&&$draft['manualChecks']===['Спокойная читаемая композиция'],'Analysis builds a valid rules contract and separate human checklist');
analysisCheck($draft['sourceHash']===hash('sha256',$source)&&$draft['coverage']==='owner_review_required'&&$draft['items'][0]['sourceQuote']==='Use exactly one H1.','Analysis preserves exact source evidence and never claims complete coverage');
analysisCheck(count($provider->calls)===1&&$provider->calls[0]['userPrompt']===$source&&$provider->calls[0]['maxTokens']===4500,'Analysis sends only supplied Skill text with bounded structured output');
foreach(['fabricated','unsupported','duplicate','conflicting'] as $case){
    $bad=$items;
    if($case==='fabricated')$bad[0]['sourceQuote']='This quote does not exist.';
    if($case==='unsupported')$bad[0]['ruleJson']=json_encode(['id'=>'one-h1','label'=>'Один H1','type'=>'execute_script']);
    if($case==='duplicate')$bad[]=$bad[0];
    if($case==='conflicting'){$extra=$bad[0];$extra['id']='two-h1';$extra['ruleJson']=json_encode(['id'=>'two-h1','label'=>'Один H1','type'=>'heading_count','tag'=>'h1','count'=>2],JSON_UNESCAPED_UNICODE);$bad[]=$extra;}
    try{SkillAnalyzer::create($source,new AnalysisProvider(['items'=>$bad]));throw new RuntimeException('Expected rejected draft');}catch(AiProviderFailure){analysisCheck(true,'Reject '.$case.' extracted requirements');}
}
$unused=new AnalysisProvider(['items'=>$items]);
foreach(['',str_repeat('x',15001),"```juzimder-rules\n{}\n```"] as $invalid){try{SkillAnalyzer::create($invalid,$unused);throw new RuntimeException('Expected invalid source');}catch(InvalidArgumentException){analysisCheck(true,'Reject unsupported source before provider call');}}
analysisCheck($unused->calls===[],'Invalid sources never reach OpenRouter');
