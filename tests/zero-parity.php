<?php
require __DIR__.'/../backend/Domain.php';require __DIR__.'/../backend/ZeroBlockRenderer.php';
$data=json_decode(file_get_contents(__DIR__.'/zero.fixture.json'));
\Juzimder\ZeroBlock::validate($data);$result=[];
foreach(\Juzimder\ZeroBlock::MODES as $mode=>$width)foreach($data->elements as $index=>$el)$result[$mode][$index]=\Juzimder\ZeroBlockRenderer::geometry(\Juzimder\ZeroBlockRenderer::resolved($el,$mode)['geometry'],$data->artboards->$mode);
$result['nested']=\Juzimder\ZeroBlockRenderer::geometry(get_object_vars($data->elements[2]->children[0]->geometry),(object)['width'=>400,'height'=>300],true);
echo json_encode($result,JSON_THROW_ON_ERROR);
