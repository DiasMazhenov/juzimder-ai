<?php
declare(strict_types=1);
require __DIR__.'/../backend/GeneratedDesignDefaults.php';
use Juzimder\GeneratedDesignDefaults;
function defaultsCheck(bool $ok,string $name):void{if(!$ok)throw new RuntimeException($name);echo "PASS $name\n";}
$input=['html'=>'<div class="ai-scope-test"><section class="ai-hero"><h1>Главная</h1><div class="ai-image-slot"></div></section><section class="ai-menu"><h2>Меню</h2><article class="ai-card"><div class="ai-image-slot"></div></article></section></div>','css'=>'.ai-scope-test .ai-hero{text-align:left}.ai-scope-test .ai-menu{text-align:center}.ai-scope-test .ai-card{border-radius:16px}.ai-scope-test button{border-radius:8px}'];
$out=GeneratedDesignDefaults::apply($input);
defaultsCheck(substr_count($out['html'],'ai-pill-badge')===2,'Each content section gets one pill badge');
defaultsCheck(substr_count($out['html'],'ai-default-left')===2,'Left aligned Hero sets consistent section alignment');
defaultsCheck(str_contains($out['css'],'ai-photo-radius-2.ai-photo-radius-2')&&str_contains($out['css'],'border-radius:16px'),'Photo uses containing card radius');
$center=$input;$center['css']=str_replace('text-align:left','text-align:center',$center['css']);$center=GeneratedDesignDefaults::apply($center);
defaultsCheck(!str_contains($center['html'],'ai-default-left'),'Centered Hero does not force left alignment');
$again=GeneratedDesignDefaults::apply($out);
defaultsCheck(substr_count($again['html'],'ai-pill-badge')===2,'Existing badges are retained without duplicates');

defaultsCheck(str_contains($out['css'],'width:fit-content;align-self:flex-start;justify-self:start'),'Pill badge remains compact inside flex and grid containers');
