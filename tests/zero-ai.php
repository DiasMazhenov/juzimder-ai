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
        $this->calls[]=['schema'=>$schema,'schemaName'=>$schemaName,'maxTokens'=>$maxTokens,'systemPrompt'=>$systemPrompt,'userPrompt'=>$userPrompt];
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
zeroAiCheck(AiZeroComposer::schema(1200,700)['properties']['elements']['maxItems']===16&&$provider->calls[0]['maxTokens']===3000&&str_contains($provider->calls[0]['systemPrompt'],'keep each card title followed by its description'),'Complex Zero layouts have room for requested cards and preserve their responsive reading order');

$ctaItems=[aiItem('text','Hero heading',80,80,'Здоровье рядом'),aiItem('text','Supporting copy',80,180,'Забота о всей семье каждый день.'),aiItem('text','Primary action',80,320,'Записаться на приём')];
$ctaProvider=new ZeroComposerFakeProvider([json_encode(['elements'=>$ctaItems],JSON_THROW_ON_ERROR)]);
$ctaProposal=AiZeroComposer::create('Создай первый экран клиники с кнопкой записи.',$fixture,$ctaProvider,static fn()=>[]);
$ctaByName=[];foreach($ctaProposal['elements'] as $element)$ctaByName[$element->name]=$element;
zeroAiCheck($ctaByName['Primary action']->type==='button'&&$ctaByName['Primary action']->content==='Записаться на приём'&&$ctaByName['Primary action']->href==='#','Explicit action copy returned as text becomes a native editable Button');
zeroAiCheck($ctaByName['Hero heading']->type==='text'&&$ctaByName['Primary action']->style->fill==='#183e31'&&$ctaByName['Primary action']->style->color==='#ffffff','CTA conversion preserves ordinary text and applies button surface contrast');
$labelRepairItems=[aiItem('text','Section heading',80,80,'Service directions'),aiItem('text','Therapy',80,180,'Therapy'),aiItem('text','Diagnostics',380,180,'Diagnostics'),aiItem('text','Pediatrics',680,180,'Pediatrics'),aiItem('text','Choose a service',80,300,'Choose a service')];
$labelRepairProvider=new ZeroComposerFakeProvider([json_encode(['elements'=>$items],JSON_THROW_ON_ERROR),json_encode(['elements'=>$labelRepairItems],JSON_THROW_ON_ERROR)]);
$labelRepair=AiZeroComposer::create('Create three cards titled «Therapy», «Diagnostics», and «Pediatrics», and a button «Choose a service».',$fixture,$labelRepairProvider,static fn()=>[]);
$labelRepairByContent=[];foreach($labelRepair['elements'] as $element)$labelRepairByContent[$element->content??$element->name]=$element;
zeroAiCheck(count($labelRepairProvider->calls)===2&&$labelRepairProvider->calls[1]['schemaName']==='juzimder_zero_brief_repair','Omitted quoted brief labels trigger one bounded structured repair');
zeroAiCheck(isset($labelRepairByContent['Therapy'],$labelRepairByContent['Diagnostics'],$labelRepairByContent['Pediatrics'])&&$labelRepairByContent['Choose a service']->type==='button','Repair restores every explicitly named card and preserves the requested native button');
$authorProfileItems=[aiItem('text','Profile heading',80,80,'Личный подход'),aiItem('text','Author biography',80,190,'Врач подробно рассказывает о своём подходе к лечению.'),aiItem('button','Read profile',80,320,'Читать профиль')];$authorProfileItems[0]['fontSize']=64;$authorProfileProvider=new ZeroComposerFakeProvider([json_encode(['elements'=>$authorProfileItems],JSON_THROW_ON_ERROR)]);$authorProfile=AiZeroComposer::create('Create a profile section with a short author biography.',$fixture,$authorProfileProvider,static fn()=>[]);
zeroAiCheck(count(array_filter($authorProfile['elements'],static fn($element)=>$element->name==='Author biography'))===1,'Photo-credit filtering preserves legitimate author biography copy');

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

$mobileHeroItems=[aiItem('text','Hero badge',120,40,'Family care'),aiItem('text','Hero heading',120,100,'Care from the first visit'),aiItem('text','Supporting copy',120,210,'Qualified care, modern equipment, and a welcoming team.'),aiItem('button','Book an appointment',120,340,'Book an appointment'),aiItem('image','Clinic photo',720,80,'','welcoming modern clinic interior'),aiItem('text','Photo source',700,620,'Фото: Unsplash')];$mobileHeroItems[0]['fontSize']=14;$mobileHeroItems[0]['width']=220;$mobileHeroItems[0]['height']=30;$mobileHeroItems[0]['radius']=0;$mobileHeroItems[0]['color']=$mobileHeroItems[0]['fill'];$mobileHeroItems[1]['fontSize']=64;$mobileHeroItems[1]['width']=560;$mobileHeroItems[2]['fontSize']=22;$mobileHeroItems[2]['width']=560;$mobileHeroItems[3]['width']=280;$mobileHeroItems[3]['radius']=12;$mobileHeroItems[4]['x']=720;$mobileHeroItems[4]['y']=80;$mobileHeroItems[4]['width']=480;$mobileHeroItems[4]['height']=500;$mobileHeroItems[4]['radius']=50;$mobileHeroItems[5]['width']=200;$mobileHeroItems[5]['height']=24;$mobileHeroItems[5]['fontSize']=12;
$mobileHeroProvider=new ZeroComposerFakeProvider([json_encode(['elements'=>$mobileHeroItems],JSON_THROW_ON_ERROR),json_encode(['selections'=>[['slotId'=>'slot-4','photoId'=>'commons:clinic']]],JSON_THROW_ON_ERROR)]);
$mobileHeroPhotoSearch=static fn()=>[['id'=>'commons:clinic','name'=>'Clinic photo.jpg','url'=>'https://upload.wikimedia.org/example/clinic.jpg','author'=>'Photo Author','license'=>'CC BY-SA 4.0','sourceUrl'=>'https://commons.wikimedia.org/wiki/File:Clinic.jpg','licenseUrl'=>'https://creativecommons.org/licenses/by-sa/4.0/','kind'=>'commons']];
$mobileHeroProposal=AiZeroComposer::create('Create a clinic hero with a photo.',$fixture,$mobileHeroProvider,$mobileHeroPhotoSearch);$mobileByName=[];foreach($mobileHeroProposal['elements'] as $element)$mobileByName[$element->name]=$element;
$mobileBadge=$mobileByName['Hero badge'];$mobileBadgeGeometry=$mobileBadge->overrides->mobile->geometry;
$mobileHeading=$mobileByName['Hero heading']->overrides->mobile->geometry;$mobileCopy=$mobileByName['Supporting copy']->overrides->mobile->geometry;$mobileButton=$mobileByName['Book an appointment']->overrides->mobile->geometry;$mobilePhoto=$mobileByName['Clinic photo']->overrides->mobile->geometry;$mobileCredit=$mobileByName['Фото · автор и лицензия']->overrides->mobile->geometry;
zeroAiCheck($mobileBadgeGeometry->width<320&&$mobileBadgeGeometry->height===32&&$mobileBadgeGeometry->y<$mobileHeading->y&&$mobileBadge->style->radius>=14&&$mobileBadge->style->color!==$mobileBadge->style->fill,'AI creates a compact, rounded, readable badge before the mobile hero heading');
zeroAiCheck($mobileByName['Clinic photo']->style->radius===$mobileByName['Book an appointment']->style->radius&&$mobileByName['Clinic photo']->overrides->mobile->style->radius===$mobileByName['Book an appointment']->overrides->mobile->style->radius,'Photo, CTA, and their responsive overrides share one corner radius');
zeroAiCheck($mobileHeading->x===20&&$mobileHeading->width===320&&$mobileHeading->y<$mobileCopy->y&&$mobileCopy->y<$mobileButton->y&&$mobileButton->y<$mobilePhoto->y,'A side-by-side photo hero becomes a text-first, photo-last mobile stack without overlapping layers');
zeroAiCheck((float)$mobileByName['Hero heading']->geometry->x===120.0&&(float)$mobileByName['Clinic photo']->geometry->x===720.0&&$mobilePhoto->width===320&&$mobileCredit->y===$mobilePhoto->y+$mobilePhoto->height-16,'Mobile stacking detects tight desktop columns and preserves their geometry plus photo credit alignment');
zeroAiCheck(!isset($mobileByName['Photo source'])&&count(array_filter($mobileHeroProposal['elements'],static fn($element)=>$element->name==='Фото · автор и лицензия'))===1,'Model-invented stock-photo credits are removed in favor of one server-verified source credit');
$nearGapItems=[aiItem('text','Hero badge',120,60,'Care options'),aiItem('text','Section heading',120,120,'Directions for your family'),aiItem('text','Section summary',120,220,'Qualified care for every stage of life.'),aiItem('text','Last card title',450,360,'Pediatrics'),aiItem('image','Clinic photo',760,120,'','bright modern clinic room')];$nearGapItems[0]['fontSize']=14;$nearGapItems[0]['height']=32;$nearGapItems[0]['radius']=0;$nearGapItems[1]['fontSize']=48;$nearGapItems[1]['width']=560;$nearGapItems[2]['fontSize']=18;$nearGapItems[2]['width']=560;$nearGapItems[3]['width']=300;$nearGapItems[3]['height']=40;$nearGapItems[4]['width']=320;$nearGapItems[4]['height']=320;
$nearGapProvider=new ZeroComposerFakeProvider([json_encode(['elements'=>$nearGapItems],JSON_THROW_ON_ERROR),json_encode(['selections'=>[['slotId'=>'slot-4','photoId'=>'commons:clinic']]],JSON_THROW_ON_ERROR)]);
$nearGapProposal=AiZeroComposer::create('Create a clinic section with an open-source photo.',$fixture,$nearGapProvider,$mobileHeroPhotoSearch);$nearGapByName=[];foreach($nearGapProposal['elements'] as $element)$nearGapByName[$element->name]=$element;
zeroAiCheck((float)$nearGapByName['Clinic photo']->overrides->mobile->geometry->x===20.0&&(float)$nearGapByName['Clinic photo']->overrides->mobile->geometry->width===320.0,'Mobile photo stacking remains active when adjacent card content leaves a narrow desktop column gap');
$cardStackItems=[aiItem('text','Section badge',120,40,'Care options'),aiItem('text','Section heading',120,100,'Directions for your family'),aiItem('text','Section summary',120,180,'Choose the care that fits your family.'),aiItem('text','Therapy title',120,300,'Therapy'),aiItem('text','Therapy description',120,350,'Consultations and treatment for common conditions.'),aiItem('text','Diagnostics title',450,300,'Diagnostics'),aiItem('text','Diagnostics description',450,350,'Laboratory and imaging examinations.'),aiItem('text','Pediatrics title',780,300,'Pediatrics'),aiItem('text','Pediatrics description',780,350,'Care for children and advice for parents.'),aiItem('button','Choose a service',120,500,'Choose a service'),aiItem('image','Clinic photo',780,100,'','bright modern clinic room')];$cardStackItems[0]['fontSize']=14;$cardStackItems[0]['height']=32;$cardStackItems[1]['fontSize']=48;$cardStackItems[1]['width']=560;$cardStackItems[2]['fontSize']=18;$cardStackItems[2]['width']=560;foreach([3,5,7] as $i){$cardStackItems[$i]['fontSize']=20;$cardStackItems[$i]['height']=32;}foreach([4,6,8] as $i){$cardStackItems[$i]['fontSize']=14;$cardStackItems[$i]['height']=70;} $cardStackItems[9]['fontSize']=18;$cardStackItems[9]['height']=48;$cardStackItems[10]['width']=300;$cardStackItems[10]['height']=200;
$cardStackProvider=new ZeroComposerFakeProvider([json_encode(['elements'=>$cardStackItems],JSON_THROW_ON_ERROR),json_encode(['selections'=>[['slotId'=>'slot-10','photoId'=>'commons:clinic']]],JSON_THROW_ON_ERROR)]);
$cardStackProposal=AiZeroComposer::create('Create a clinic services section with three cards and an open-source photo.',$fixture,$cardStackProvider,$mobileHeroPhotoSearch);$cardStackByName=[];foreach($cardStackProposal['elements'] as $element)$cardStackByName[$element->name]=$element;$therapyTitleY=$cardStackByName['Therapy title']->overrides->mobile->geometry->y;$therapyDescriptionY=$cardStackByName['Therapy description']->overrides->mobile->geometry->y;$diagnosticsTitleY=$cardStackByName['Diagnostics title']->overrides->mobile->geometry->y;$diagnosticsDescriptionY=$cardStackByName['Diagnostics description']->overrides->mobile->geometry->y;$pediatricsTitleY=$cardStackByName['Pediatrics title']->overrides->mobile->geometry->y;$pediatricsDescriptionY=$cardStackByName['Pediatrics description']->overrides->mobile->geometry->y;
zeroAiCheck($therapyTitleY<$therapyDescriptionY&&$therapyDescriptionY<$diagnosticsTitleY&&$diagnosticsTitleY<$diagnosticsDescriptionY&&$diagnosticsDescriptionY<$pediatricsTitleY&&$pediatricsTitleY<$pediatricsDescriptionY,'Mobile card stacking preserves each title with its description in reading order');
$overlapItems=[aiItem('text','Hero badge',120,120,'Care options'),aiItem('text','Section heading',120,180,'Directions for your family'),aiItem('text','Section summary',120,250,'Choose the care that fits your family.'),aiItem('text','Pediatrics',780,340,'Pediatrics'),aiItem('image','Clinic photo',780,120,'','bright clinic room')];$overlapItems[0]['fontSize']=14;$overlapItems[0]['height']=32;$overlapItems[1]['fontSize']=48;$overlapItems[1]['width']=560;$overlapItems[2]['fontSize']=18;$overlapItems[2]['width']=560;$overlapItems[3]['width']=300;$overlapItems[3]['height']=40;$overlapItems[4]['width']=300;$overlapItems[4]['height']=300;
$fixedOverlapItems=$overlapItems;$fixedOverlapItems[4]['height']=200;
$overlapProvider=new ZeroComposerFakeProvider([json_encode(['elements'=>$overlapItems],JSON_THROW_ON_ERROR),json_encode(['elements'=>$fixedOverlapItems],JSON_THROW_ON_ERROR),json_encode(['selections'=>[['slotId'=>'slot-4','photoId'=>'commons:clinic']]],JSON_THROW_ON_ERROR)]);
$overlapProposal=AiZeroComposer::create('Create a family clinic section with one open-source photo.',$fixture,$overlapProvider,$mobileHeroPhotoSearch);$overlapByName=[];foreach($overlapProposal['elements'] as $element)$overlapByName[$element->name]=$element;
zeroAiCheck($overlapProvider->calls[1]['schemaName']==='juzimder_zero_brief_repair'&&str_contains($overlapProvider->calls[1]['userPrompt'],'image cover text'),'Overlapping AI photo placement triggers one bounded geometry repair');
zeroAiCheck($overlapByName['Clinic photo']->geometry->height===200.0&&$overlapByName['Clinic photo']->overrides->mobile->geometry->width===320,'Repaired image clears the third card on desktop and stacks after mobile copy');

$requestedImageRepairProvider=new ZeroComposerFakeProvider([
    json_encode(['elements'=>$items],JSON_THROW_ON_ERROR),
    json_encode(['elements'=>[$photoItems[0],$items[0],$items[1]]],JSON_THROW_ON_ERROR),
    json_encode(['selections'=>[['slotId'=>'slot-0','photoId'=>'commons:clinic']]],JSON_THROW_ON_ERROR),
]);
$repairSearch=static fn()=>[['id'=>'commons:clinic','name'=>'Clinic photo.jpg','url'=>'https://upload.wikimedia.org/example/clinic.jpg','author'=>'Photo Author','license'=>'CC BY-SA 4.0','sourceUrl'=>'https://commons.wikimedia.org/wiki/File:Clinic.jpg','licenseUrl'=>'https://creativecommons.org/licenses/by-sa/4.0/','kind'=>'commons']];
$repairedPhotoProposal=AiZeroComposer::create('Create a hero with one photo of a modern clinic.',$fixture,$requestedImageRepairProvider,$repairSearch);
$repairedImages=array_values(array_filter($repairedPhotoProposal['elements'],static fn($element)=>$element->type==='image'));
zeroAiCheck(count($requestedImageRepairProvider->calls)===3&&$requestedImageRepairProvider->calls[1]['schemaName']==='juzimder_zero_photo_repair'&&str_contains($requestedImageRepairProvider->calls[1]['userPrompt'],'STRICT REQUIREMENT')&&str_contains($requestedImageRepairProvider->calls[0]['systemPrompt'],'include exactly one image element'),'An explicitly requested photo triggers one bounded structured repair when the first draft omits it');
zeroAiCheck(count($repairedImages)===1&&$repairedPhotoProposal['metadata']['usage']['totalTokens']===160,'The repaired proposal includes a Commons image and combines usage from both composition calls');

$missingPhotoProvider=new ZeroComposerFakeProvider([json_encode(['elements'=>$items],JSON_THROW_ON_ERROR),json_encode(['elements'=>$items],JSON_THROW_ON_ERROR)]);
$missingPhotoRejected=false;
try{AiZeroComposer::create('Create a hero with one photo of a modern clinic.',$fixture,$missingPhotoProvider,static fn()=>[]);}catch(\Juzimder\AiProviderFailure){$missingPhotoRejected=true;}
zeroAiCheck($missingPhotoRejected&&count($missingPhotoProvider->calls)===2,'An explicit photo request is rejected after one repair if the model still omits imagery');
$noOpenPhotoProvider=new ZeroComposerFakeProvider([
    json_encode(['elements'=>$items],JSON_THROW_ON_ERROR),
    json_encode(['elements'=>[$photoItems[0],$items[0],$items[1]]],JSON_THROW_ON_ERROR),
]);
$noOpenPhotoFailure=null;
try{AiZeroComposer::create('Create a hero with one photo of a modern clinic.',$fixture,$noOpenPhotoProvider,static fn()=>[]);}catch(\Juzimder\AiProviderFailure $failure){$noOpenPhotoFailure=$failure;}
zeroAiCheck($noOpenPhotoFailure?->category==='photo_unavailable','An explicit photo request fails closed when no suitable open-licence photo is found');
echo "PASS: Zero AI proposal contract verified\n";
