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
defaultsCheck(str_contains($out['css'],'.ai-scope-test nav{display:flex;flex-wrap:wrap')&&str_contains($out['css'],'.ai-scope-test nav ul{display:flex;flex-wrap:wrap'),'Generated navigation and its list wrap at narrow viewport widths');
$navSpacing=['html'=>'<div class="ai-scope-test"><nav><a href="#about">О нас</a></nav><section class="ai-hero"><h1>Главная</h1></section></div>','css'=>'.ai-scope-test nav{display:flex}'];
$navSpacingOut=GeneratedDesignDefaults::apply($navSpacing);
defaultsCheck(str_contains($navSpacingOut['html'],'ai-nav-default-spacing')&&str_contains($navSpacingOut['css'],'box-sizing:border-box;padding:12px 20px'),'Generated navigation gets safe outer spacing when the model provides none');
$authoredNav=$navSpacing;$authoredNav['css'].='.ai-scope-test nav{padding:8px 32px}';$authoredNav=GeneratedDesignDefaults::apply($authoredNav);
defaultsCheck(!str_contains($authoredNav['html'],'ai-nav-default-spacing'),'Generated navigation preserves model-authored padding');

$split=['html'=>'<div class="ai-scope-test"><section class="ai-hero ai-hero-split"><div class="ai-image-slot" data-image-slot="hero"></div><div class="ai-copy"><h1>Clinic</h1><p>Care</p><a href="#contact">Book</a></div></section></div>','css'=>'.ai-scope-test .ai-hero{background-color:#123456}'];
$composed=GeneratedDesignDefaults::apply($split);
defaultsCheck(strpos($composed['html'],'ai-split-copy') < strpos($composed['html'],'data-image-slot="hero"'),'Split Hero orders text before photo in native DOM');
defaultsCheck(str_contains($composed['css'],'@media(max-width:767px)')&&str_contains($composed['css'],'grid-template-columns:minmax(0,1fr)'),'Split Hero has single-column mobile layout');
defaultsCheck(str_contains($composed['css'],'background-color:#123456'),'Split composition retains model palette');
$split['html']=str_replace('ai-hero-split','ai-hero-background ai-hero-split',$split['html']);
defaultsCheck(!str_contains(GeneratedDesignDefaults::apply($split)['html'],'ai-split-ready'),'Background Hero is never rewritten as split');
$split['html']=str_replace('ai-hero-background ai-hero-split','ai-hero',$split['html']);
defaultsCheck(!str_contains(GeneratedDesignDefaults::apply($split)['html'],'ai-split-ready'),'Unmarked Hero stays free-form');

$rounded=$input;$rounded['css'].='.ai-scope-test .ai-hero{border-radius:24px}';$rounded=GeneratedDesignDefaults::apply($rounded);
defaultsCheck(str_contains($rounded['html'],'ai-hero-rounded')&&str_contains($rounded['css'],'overflow:hidden'),'Rounded Hero retains its radius and clips contents');

$separate=$input;$separate['css'].='.ai-scope-test .ai-hero{border-radius:24px}.ai-scope-test .ai-image-slot{border-radius:8px}';
defaultsCheck(str_contains(GeneratedDesignDefaults::apply($separate)['css'],'ai-photo-radius-1 img{border-radius:8px}'),'Explicit photo radius is independent of Hero container radius');
