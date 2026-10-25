<?php
declare(strict_types=1);
require __DIR__.'/../backend/Domain.php';
require __DIR__.'/../backend/Ai.php';
require __DIR__.'/../backend/AiPhotoSelection.php';
use Juzimder\{AiPhotoSelection,StructuredAiProvider};
final class PhotoProvider implements StructuredAiProvider {
 public function __construct(private string $content){}
 public function completeJson(string $systemPrompt,string $userPrompt,array $schema,string $schemaName,int $maxTokens=3200):array{return ['content'=>$this->content];}
}
function selectionCheck(bool $ok,string $name):void{if(!$ok)throw new RuntimeException($name);echo "PASS $name\n";}
$base=['design'=>['imageSlots'=>[['id'=>'hero','query'=>'bread loaf'],['id'=>'section-1','query'=>'rye bread']]]];
$photo=['id'=>'commons:1','name'=>'Bread loaf.jpg','url'=>'https://upload.wikimedia.org/a.jpg','author'=>'Author','license'=>'CC0','sourceUrl'=>'https://commons.wikimedia.org/wiki/File:Bread.jpg','licenseUrl'=>'https://creativecommons.org/publicdomain/zero/1.0/','kind'=>'commons'];
$search=static fn($query)=>$query==='bread loaf'?[$photo]:[];
$result=AiPhotoSelection::enrich($base,new PhotoProvider('{"selections":[{"slotId":"hero","photoId":"commons:1"},{"slotId":"section-1","photoId":"commons:1"},{"slotId":"evil","photoId":"https://evil.test"}]}'),$search);
selectionCheck($result['design']['photoSelection']['assignments']===['hero'=>'commons:1'],'Only IDs from the server catalogue for the matching slot can be selected');
selectionCheck($result['design']['photoSelection']['photos']===[$photo],'Persist selected photo source author and licence');
$result=AiPhotoSelection::enrich($base,new PhotoProvider('invalid'),$search);
selectionCheck($result['design']['photoSelection']['status']==='unavailable'&&$result['design']['photoSelection']['photos']===[],'Selection failure preserves design without random photos');
$automatic=['design'=>['imageSlots'=>[['id'=>'hero-auto','query'=>'dental clinic','automatic'=>true]]]];
$result=AiPhotoSelection::enrich($automatic,new PhotoProvider('invalid'),static fn()=>[$photo]);
selectionCheck($result['design']['photoSelection']['status']==='completed'&&$result['design']['photoSelection']['assignments']===['hero-auto'=>'commons:1']&&$result['design']['photoSelection']['photos']===[$photo],'Automatic Hero photography uses the top verified Commons result when model ranking fails');
$result=AiPhotoSelection::enrich($base,new PhotoProvider('{"selections":[{"slotId":"hero","photoId":""}]}'),$search);
selectionCheck($result['design']['photoSelection']['assignments']===[],'Model can reject all unsuitable candidates');

selectionCheck($result['design']['photoSelection']['status']==='no_matches','Rejecting all candidates reports no matches');
$result=AiPhotoSelection::enrich($base,new PhotoProvider('{}'),static function($query){throw new RuntimeException('source unavailable');});
selectionCheck($result['design']['photoSelection']['status']==='unavailable','Search outage is distinct from no matches');
$restaurant=['design'=>['imageSlots'=>[['id'=>'hero','query'=>'restaurant food dishes dining room','fallbackAllowed'=>true]]]];
$vegas=['id'=>'commons:vegas','name'=>'Las Vegas (Nevada, USA), The Venetian -- 2012 -- 6339.jpg','description'=>'Hotel and casino exterior architecture','url'=>'https://upload.wikimedia.org/vegas.jpg','author'=>'Author','license'=>'CC BY-SA 4.0','sourceUrl'=>'https://commons.wikimedia.org/wiki/File:Vegas.jpg','licenseUrl'=>'https://creativecommons.org/licenses/by-sa/4.0/','kind'=>'commons'];
$diningRoom=['id'=>'commons:dining-room','name'=>'Austria-00565 - Our Dining Room.jpg','description'=>'Where we ate dinner at a famous restaurant','url'=>'https://upload.wikimedia.org/dining-room.jpg','author'=>'Author','license'=>'CC BY-SA 4.0','sourceUrl'=>'https://commons.wikimedia.org/wiki/File:Dining_room.jpg','licenseUrl'=>'https://creativecommons.org/licenses/by-sa/4.0/','kind'=>'commons'];
$result=AiPhotoSelection::enrich($restaurant,new PhotoProvider('{"selections":[{"slotId":"hero","photoId":"commons:vegas"}]}'),static fn()=>[$vegas,$diningRoom]);
selectionCheck($result['design']['photoSelection']['assignments']===['hero'=>'commons:dining-room']&&$result['design']['photoSelection']['photos']===[$diningRoom],'Restaurant photo selection rejects an unrelated Las Vegas casino and keeps a relevant dining-room image');
$restaurantRetry=['design'=>['imageSlots'=>[['id'=>'hero','query'=>'family restaurant Almaty exterior','fallbackAllowed'=>true]]]];$fallbackSearchCalls=[];$result=AiPhotoSelection::enrich($restaurantRetry,new PhotoProvider('{"selections":[{"slotId":"hero","photoId":"commons:dining-room"}]}'),static function(string $query)use(&$fallbackSearchCalls,$vegas,$diningRoom):array{$fallbackSearchCalls[]=$query;return $query==='restaurant food dishes dining room'?[$diningRoom]:[$vegas];});
selectionCheck($fallbackSearchCalls===['family restaurant Almaty exterior','restaurant food dishes dining room']&&$result['design']['photoSelection']['assignments']===['hero'=>'commons:dining-room'],'An irrelevant restaurant query triggers one bounded subject-specific Commons retry before photo ranking');
$result=AiPhotoSelection::enrich($restaurant,new PhotoProvider('{"selections":[{"slotId":"hero","photoId":"commons:vegas"}]}'),static fn()=>[$vegas]);
selectionCheck($result['design']['photoSelection']['status']==='no_matches'&&$result['design']['photoSelection']['photos']===[],'Restaurant photo fallback never returns a hotel, casino or unrelated building');
