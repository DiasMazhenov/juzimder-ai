<?php
declare(strict_types=1);
require __DIR__.'/../backend/Domain.php';require __DIR__.'/../backend/ZeroBlockRenderer.php';require __DIR__.'/../backend/StaticSiteExporter.php';
use Juzimder\{ZeroBlock,ZeroBlockRenderer,Document,Problem,StaticSiteExporter};
function zeroCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);echo "PASS: $message\n";}
$fixture=json_decode(file_get_contents(__DIR__.'/zero.fixture.json'),false,64,JSON_THROW_ON_ERROR);
ZeroBlock::validate($fixture);
$reject=function(callable $mutate,string $message)use($fixture){$data=unserialize(serialize($fixture));$mutate($data);try{ZeroBlock::validate($data);}catch(Problem $p){zeroCheck($p->status===422,$message);return;}throw new RuntimeException('Accepted invalid Zero Block: '.$message);};
$reject(fn($d)=>$d->version=2,'unsupported version rejected');
$reject(fn($d)=>$d->elements[1]->id=$d->elements[0]->id,'duplicate element IDs rejected');
$reject(fn($d)=>$d->elements[0]->geometry->x=NAN,'NaN rejected');
$reject(fn($d)=>$d->elements[0]->geometry->y=INF,'Infinity rejected');
$reject(fn($d)=>$d->elements[0]->geometry->width=-1,'negative size rejected');
$reject(fn($d)=>$d->elements[0]->style->fontFamily='Arial;position:fixed','CSS injection rejected');
$reject(fn($d)=>$d->elements[0]->content='<script>alert(1)</script>','HTML rejected in typed text');
$reject(fn($d)=>$d->elements[1]->src='https://user:secret@example.test/a','credential URLs rejected');
$reject(fn($d)=>$d->elements[1]->src='javascript:alert(1)','script URLs rejected');
$reject(fn($d)=>$d->elements[0]->overrides->mobile->geometry->position='fixed','unknown geometry rejected');
$reject(fn($d)=>$d->elements[0]->children=[],'children require a group');
$reject(function($d){$el=$d->elements[2];for($i=0;$i<10;$i++){$child=unserialize(serialize($el));$child->id='ze-nest-'.$i;$child->children=[];$el->children=[$child];$el=$child;}},'group nesting bounded');
$reject(function($d)use($fixture){$d->elements=[];for($i=0;$i<301;$i++){$el=unserialize(serialize($fixture->elements[0]));$el->id='ze-'.$i;$d->elements[]=$el;}},'element quota enforced');
$html=ZeroBlockRenderer::render($fixture,'zero-test');zeroCheck(str_contains($html,'position:absolute')&&!str_contains($html,'display:flex')&&!str_contains($html,'display:grid'),'renderer uses absolute independent elements');zeroCheck(str_contains($html,'Hello &amp; Zero')&&str_contains($html,'srcset="https://example.test/mobile.jpg"'),'text escaped and responsive images exported');zeroCheck(str_contains($html,'z-index:3;')&&str_contains($html,'min(50%, 600px)'),'deterministic layers and centered grid coordinates exported');
$document=json_decode(json_encode(Document::empty()));$block=(object)['type'=>'zero-block','attributes'=>(object)['id'=>'zero-test'],'zeroData'=>$fixture,'components'=>[]];$document->data->pages[0]->frames[0]->component->components=[$block,(object)['type'=>'text','content'=>'Unchanged sibling']];Document::validate($document);$change=unserialize(serialize($fixture));$change->elements[0]->geometry->x=-50;$updated=ZeroBlock::updated($document,'home','zero-test',$change);zeroCheck($document->data->pages[0]->frames[0]->component->components[0]->zeroData->elements[0]->geometry->x===100,'scoped update preserves original document');zeroCheck($updated->data->pages[0]->frames[0]->component->components[1]->content==='Unchanged sibling','scoped update preserves siblings');
$files=StaticSiteExporter::files(['name'=>'Zero export','document'=>$updated]);zeroCheck(str_contains($files['index.html'],'data-zero-id="zero-test"')&&str_contains($files['index.html'],'+ -50px'),'static export integrates Zero Block without a second runtime');zeroCheck(!str_contains($files['index.html'],'<script'),'Zero export has no executable runtime');
$asset='/api/projects/'.str_repeat('a',24).'/assets/'.str_repeat('b',24).'/file';$change->elements[1]->overrides->mobile->src=$asset;$updated=ZeroBlock::updated($document,'home','zero-test',$change);$refs=Document::assetReferences($updated);zeroCheck(count($refs)===1&&$refs[0]['assetId']===str_repeat('b',24),'responsive managed assets participate in existing owner validation');
require __DIR__.'/../backend/Operations.php';
$typed=\Juzimder\ProjectOperations::apply($document,[(object)['operation'=>'update_zero_block','pageId'=>'home','componentId'=>'zero-test','zeroData'=>$change]]);
zeroCheck($typed->data->pages[0]->frames[0]->component->components[0]->zeroData->elements[0]->geometry->x===-50,'typed operation uses the same zeroData authority');
$duplicate=unserialize(serialize($document));$duplicate->data->pages[0]->frames[0]->component->components[]=$duplicate->data->pages[0]->frames[0]->component->components[0];
try{Document::validate($duplicate);throw new RuntimeException('Duplicate block accepted');}catch(Problem $e){zeroCheck($e->status===422,'duplicate component IDs rejected');}
