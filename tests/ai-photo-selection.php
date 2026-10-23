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
$result=AiPhotoSelection::enrich($base,new PhotoProvider('{"selections":[{"slotId":"hero","photoId":""}]}'),$search);
selectionCheck($result['design']['photoSelection']['assignments']===[],'Model can reject all unsuitable candidates');

selectionCheck($result['design']['photoSelection']['status']==='no_matches','Rejecting all candidates reports no matches');
$result=AiPhotoSelection::enrich($base,new PhotoProvider('{}'),static function($query){throw new RuntimeException('source unavailable');});
selectionCheck($result['design']['photoSelection']['status']==='unavailable','Search outage is distinct from no matches');
