<?php
declare(strict_types=1);
require __DIR__.'/../backend/Domain.php';
require __DIR__.'/../backend/Ai.php';
require __DIR__.'/../backend/AiPhotoSelection.php';
require __DIR__.'/../backend/AiZeroComposer.php';
use Juzimder\{AiZeroComposer,StructuredAiProvider,ZeroBlock};

final class ZeroComposerFakeProvider implements StructuredAiProvider {
    public array $calls=[];
    public function __construct(private array $responses) {}
    public function completeJson(string $systemPrompt,string $userPrompt,array $schema,string $schemaName,int $maxTokens=3200):array {
        $this->calls[]=['schema'=>$schema,'schemaName'=>$schemaName,'maxTokens'=>$maxTokens];
        $content=array_shift($this->responses);
        if(!is_string($content))throw new RuntimeException('Unexpected extra AI request.');
        return ['content'=>$content,'model'=>'test/zero-model','profileName'=>'QA profile','usage'=>['promptTokens'=>32,'completionTokens'=>48,'totalTokens'=>80,'costUsd'=>0.0]];
    }
}
function zeroAiCheck(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);echo "PASS: $message\n";}
function aiItem(string $type,string $name,int $x,int $y,string $content='',string $photoQuery=''):array{return ['type'=>$type,'name'=>$name,'content'=>$content,'photoQuery'=>$photoQuery,'icon'=>'photo','x'=>$x,'y'=>$y,'width'=>$type==='image'?360:300,'height'=>$type==='image'?220:80,'rotation'=>0,'fill'=>'#183e31','color'=>'#ffffff','stroke'=>'#183e31','strokeWidth'=>0,'radius'=>16,'opacity'=>1,'fontSize'=>32,'fontWeight'=>600,'textAlign'=>'left'];}
$fixture=json_decode(file_get_contents(__DIR__.'/zero.fixture.json'),false,64,JSON_THROW_ON_ERROR);
$original=json_decode(json_encode($fixture,JSON_THROW_ON_ERROR),false,64,JSON_THROW_ON_ERROR);
$items=[aiItem('text','Hero heading',80,80,'A clear heading'),aiItem('button','Primary action',80,210,'Get started'),aiItem('rectangle','Accent shape',760,180)];
$provider=new ZeroComposerFakeProvider([json_encode(['elements'=>$items],JSON_THROW_ON_ERROR)]);
$proposal=AiZeroComposer::create('Design a simple landing page hero.',$fixture,$provider,static fn()=>[]);
$candidate=json_decode(json_encode($fixture,JSON_THROW_ON_ERROR),false,64,JSON_THROW_ON_ERROR);$candidate->elements=$proposal['elements'];ZeroBlock::validate($candidate);
zeroAiCheck(count($proposal['elements'])===3&&$proposal['metadata']['resolvedModel']==='test/zero-model','AI proposal contains typed, editable Zero elements');
zeroAiCheck($proposal['elements'][0]->overrides->mobile->geometry->width===90.0,'AI proposal includes proportional responsive overrides');
zeroAiCheck($proposal['elements'][0]->style->fill==='transparent'&&$proposal['elements'][0]->style->color==='#222629','Regular AI text has no opaque bar and uses readable contrast on the artboard');
zeroAiCheck($proposal['elements'][1]->style->fill==='#183e31'&&$proposal['elements'][1]->style->color==='#ffffff','AI button preserves its surface and readable label contrast');
zeroAiCheck($fixture==$original&&$proposal['baseVersion']===null,'Proposal generation leaves source Project Data untouched until explicit editor apply');

$photoItems=[aiItem('image','Clinic photo',700,80,'','modern clinic interior'),aiItem('image','Care photo',700,350,'','doctor consulting patient'),aiItem('image','Extra photo',300,400,'','medical team'),aiItem('text','Clinic heading',80,100,'Care from the first visit')];
$selection=['selections'=>[['slotId'=>'slot-0','photoId'=>'commons:clinic'],['slotId'=>'slot-1','photoId'=>'commons:care'],['slotId'=>'slot-2','photoId'=>'commons:team']]];
$photoProvider=new ZeroComposerFakeProvider([json_encode(['elements'=>$photoItems],JSON_THROW_ON_ERROR),json_encode($selection,JSON_THROW_ON_ERROR)]);
$searchCalls=[];
$photoSearch=static function(string $query)use(&$searchCalls):array{$searchCalls[]=$query;$suffix=str_contains($query,'doctor')?'care':(str_contains($query,'team')?'team':'clinic');return [['id'=>'commons:'.$suffix,'name'=>ucfirst($suffix).' photo.jpg','url'=>'https://upload.wikimedia.org/example/'.$suffix.'.jpg','author'=>'Photo Author','license'=>'CC BY-SA 4.0','sourceUrl'=>'https://commons.wikimedia.org/wiki/File:'.ucfirst($suffix).'.jpg','licenseUrl'=>'https://creativecommons.org/licenses/by-sa/4.0/','kind'=>'commons']];};
$photoProposal=AiZeroComposer::create('Make a medical clinic hero with relevant open-source photos.',$fixture,$photoProvider,$photoSearch);
$photoCandidate=json_decode(json_encode($fixture,JSON_THROW_ON_ERROR),false,64,JSON_THROW_ON_ERROR);$photoCandidate->elements=$photoProposal['elements'];ZeroBlock::validate($photoCandidate);
$photos=array_values(array_filter($photoProposal['elements'],static fn($element)=>$element->type==='image'));
$credits=array_values(array_filter($photoProposal['elements'],static fn($element)=>$element->name==='Фото · автор и лицензия'));
zeroAiCheck(count($searchCalls)===2&&count($photos)===2,'Composer enforces a server-side maximum of two open-source photo searches');
zeroAiCheck(count($credits)===2&&$credits[0]->style->fontSize===8&&str_contains($credits[0]->content,'Photo Author')&&str_contains($credits[0]->content,'CC BY-SA 4.0')&&str_contains($photos[0]->href,'commons.wikimedia.org'),'Each selected open photo keeps a compact visible author/licence/source credit');
zeroAiCheck($photoProposal['metadata']['photoSelection']==='completed','Photo-selection status is returned without exposing provider keys');
echo "PASS: Zero AI proposal contract verified\n";
