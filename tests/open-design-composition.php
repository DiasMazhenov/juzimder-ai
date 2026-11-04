<?php
declare(strict_types=1);
require_once __DIR__.'/../backend/Ai.php';
use Juzimder\{OpenDesignComposition,OpenDesignTemplateGuidance,OpenDesignTemplateSource,AiProviderFailure,AiGeneratedWebsite};
function compositionCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
$cases=[
 'saas-landing'=>'<section><div><article><h3>Tasks</h3></article><article><h3>Records</h3></article></div></section>',
 'web-prototype'=>'<section><ul><li><h3>Work A</h3></li><li><h3>Work B</h3></li></ul></section>',
 'blog-post'=>'<section class="ai-blog-section"><p>Article body</p></section>',
 'docs-page'=>'<div class="ai-doc-layout"><nav></nav><article><section><p>Guide</p></section></article><aside></aside></div>',
 'pricing-page'=>'<section><div><article><h3>Offer A</h3></article><article><h3>Offer B</h3></article></div></section>',
 'waitlist-page'=>'<section><p>Status context</p></section>',
 'dashboard'=>'<nav class="ai-rail"></nav><section><p>Operational records</p></section>',
];
foreach($cases as $id=>$body){
 $contract=OpenDesignComposition::extract($id,'product',['source region order: prominent Hero','repeated content geometry: 3 aligned columns where the viewport allows'],[]);
 $html='<div class="ai-scope-abcdef"><section class="ai-hero"><div class="ai-hero-copy"><h1>QA</h1></div></section>'.$body.'</div>';
 $result=OpenDesignComposition::apply(['html'=>$html,'css'=>''],$contract);
 compositionCheck(str_contains($result['html'],'ai-source-hero')&&str_contains($result['css'],'@media')===in_array($id,['saas-landing','web-prototype','pricing-page','waitlist-page'],true),$id.' has a native semantic contract and bounded adapter');
 if(in_array($id,['saas-landing','web-prototype','pricing-page'],true)){
  compositionCheck(str_contains($result['css'],'ai-source-frame')&&str_contains($result['css'],'padding:32px 20px')&&str_contains($result['css'],'max-height:480px'),'Source collections share a bounded frame and mobile inset instead of default browser section edges');
  compositionCheck(str_contains($result['css'],'repeat(2, minmax(0,1fr))'),'Source column bound adapts to two supplied records without fabricating a third');
  try{OpenDesignComposition::apply(['html'=>str_replace($body,'<section><h2>Generic</h2><p>Plain stacked text</p></section>',$html),'css'=>''],$contract);throw new RuntimeException('Generic output accepted');}
  catch(AiProviderFailure $e){compositionCheck($e->validationRule==='composition_collection',$id.' rejects generic output rather than labeling it Open Design');}
 }
}
$auditedSignals=[
 'saas-landing'=>['source region order: top navigation → prominent Hero → repeatable feature group → separate proof region → offer or pricing group → closing action region → closing footer','repeated content geometry: 3 aligned columns where the viewport allows','source spacing rhythm: regular inter-region spacing','repeated surface treatment: soft-corner repeated surfaces'],
 'web-prototype'=>['source region order: prominent Hero → repeatable feature group → compact metric group → primary article','source region order: prominent Hero → closing footer','source Hero geometry: balanced multi-region Hero grid','source Hero alignment: center-aligned Hero text','source spacing rhythm: compact inter-region spacing','repeated surface treatment: soft-corner repeated surfaces'],
 'blog-post'=>['source region order: top navigation → primary article → prominent Hero → compact metric group','source spacing rhythm: regular inter-region spacing'],
 'docs-page'=>['source region order: top navigation → persistent navigation rail → primary article → separate contents rail','source spacing rhythm: compact inter-region spacing'],
 'pricing-page'=>['source region order: top navigation → offer or pricing group → comparison region → FAQ region','repeated content geometry: 3 aligned columns where the viewport allows','source spacing rhythm: compact inter-region spacing','repeated surface treatment: soft-corner repeated surfaces'],
 'waitlist-page'=>['source region order: prominent Hero','source Hero alignment: center-aligned Hero text','source Hero placement: centered Hero content group','source spacing rhythm: compact inter-region spacing'],
 'dashboard'=>['source region order: top navigation → persistent navigation rail → compact metric group → operational work area','source spacing rhythm: regular inter-region spacing','repeated surface treatment: soft-corner repeated surfaces'],
];
$expectedSignatures=[
 'saas-landing'=>[['navigation','hero','features','proof','offers','action','footer'],3,null,'natural','regular','soft'],
 'web-prototype'=>[['hero','features','metrics','article','footer'],null,'center','split','compact','soft'],
 'blog-post'=>[['navigation','hero','article','metrics'],null,null,'natural','regular',null],
 'docs-page'=>[['navigation','navigation_rail','article','contents_rail'],null,null,'natural','compact',null],
 'pricing-page'=>[['navigation','offers','comparison','faq'],3,null,'natural','compact','soft'],
 'waitlist-page'=>[['hero'],null,'center','natural','compact',null],
 'dashboard'=>[['navigation','navigation_rail','metrics','workspace'],null,null,'natural','regular','soft'],
];
$auditedContracts=[];
foreach($auditedSignals as $id=>$signals){
 $visual=$id==='web-prototype'?['split Hero with adjacent copy and image regions','flat, outlined card surfaces without heavy shadows','a deliberate narrow-screen reflow for the same content hierarchy']:
  ($id==='blog-post'?['serif typography scoped to the article reading column','italic editorial treatment limited to a lead or quotation','an optional typographic drop cap at the start of the article']:[]);
 $auditedContracts[$id]=OpenDesignComposition::extract($id,'product',$signals,$visual);
 [$order,$columns,$alignment,$heroLayout,$spacing,$surface]=$expectedSignatures[$id];
 compositionCheck($auditedContracts[$id]['regionOrder']===$order&&$auditedContracts[$id]['columns']===$columns&&$auditedContracts[$id]['alignment']===$alignment&&$auditedContracts[$id]['heroLayout']===$heroLayout&&$auditedContracts[$id]['spacing']===$spacing&&$auditedContracts[$id]['surface']===$surface,$id.' preserves its pinned abstract signature instead of collapsing into a shared archetype');
}
$nestedBlogHtml='<div class="ai-scope-abcdef"><header><nav>Home</nav></header><section class="ai-hero"><div class="ai-hero-copy"><h1>Field notes</h1></div></section><section class="ai-blog-section"><p>Article body</p><div class="ai-region-metrics"><span>14 sites</span></div></section></div>';
$nestedBlogResult=OpenDesignComposition::apply(['html'=>$nestedBlogHtml,'css'=>''],$auditedContracts['blog-post']);
$nestedBlogNavigation=strpos($nestedBlogResult['html'],'ai-region-navigation');$nestedBlogHero=strpos($nestedBlogResult['html'],'ai-source-hero');$nestedBlogArticle=strpos($nestedBlogResult['html'],'ai-region-article');$nestedBlogMetrics=strpos($nestedBlogResult['html'],'ai-region-metrics');
compositionCheck($nestedBlogNavigation!==false&&$nestedBlogHero!==false&&$nestedBlogArticle!==false&&$nestedBlogMetrics!==false&&$nestedBlogNavigation<$nestedBlogHero&&$nestedBlogHero<$nestedBlogArticle&&$nestedBlogArticle<$nestedBlogMetrics,'Nested Blog source containers normalize to page flow and retain navigation, Hero, article, and metrics order');
$saasAudit=$auditedContracts['saas-landing'];
$sourceScopedPrompt=OpenDesignComposition::prompt($saasAudit);
compositionCheck(!str_contains($sourceScopedPrompt,'ai-region-metrics')&&str_contains($sourceScopedPrompt,'unmarked or out-of-contract page regions'),'SaaS generation receives a source-scoped role allowlist and explicit fail-closed rule');
$saasWithExtraMetrics='<div class="ai-scope-abcdef"><section class="ai-hero"><div class="ai-hero-copy"><h1>Title</h1></div></section><section><div><article><h3>One</h3></article><article><h3>Two</h3></article></div></section><section class="ai-region-metrics"><h2>Metrics</h2><p>42</p></section></div>';
try{OpenDesignComposition::apply(['html'=>$saasWithExtraMetrics,'css'=>''],$saasAudit);throw new RuntimeException('Unpinned SaaS metric region should be rejected');}
catch(AiProviderFailure $e){compositionCheck($e->validationRule==='composition_unmapped_region','SaaS composition rejects an extra region absent from its pinned source');}
$saasWithUnmarkedSection=str_replace(' class="ai-region-metrics"','',$saasWithExtraMetrics);
try{OpenDesignComposition::apply(['html'=>$saasWithUnmarkedSection,'css'=>''],$saasAudit);throw new RuntimeException('Unmarked SaaS region should be rejected');}
catch(AiProviderFailure $e){compositionCheck($e->validationRule==='composition_unmapped_region','SaaS composition rejects an unmarked extra section instead of passing generic structure');}
$webWithoutNavigation=OpenDesignComposition::extract('web-prototype','project',['source region order: prominent Hero → repeatable feature group'],[]);
$webWithAddedNavigation='<div class="ai-scope-abcdef"><header><nav>Home</nav></header><section class="ai-hero"><div class="ai-hero-copy"><h1>Title</h1></div></section><section><div><article><h3>One</h3></article><article><h3>Two</h3></article></div></section></div>';
try{OpenDesignComposition::apply(['html'=>$webWithAddedNavigation,'css'=>''],$webWithoutNavigation);throw new RuntimeException('Unpinned navigation should be rejected');}
catch(AiProviderFailure $e){compositionCheck($e->validationRule==='composition_unmapped_region','Composition rejects a generic navigation region absent from the selected source');}
$webWithFooter=OpenDesignComposition::extract('web-prototype','project',$auditedSignals['web-prototype'],['split Hero with adjacent copy and image regions']);
$webFooterHtml='<div class="ai-scope-abcdef"><section class="ai-hero"><div class="ai-hero-copy"><h1>Title</h1></div><div class="ai-image-slot"></div></section><section><div><article><h3>One</h3></article><article><h3>Two</h3></article></div></section><section class="ai-region-metrics"><h2>Metrics</h2><p>42</p></section><section class="ai-region-article"><h2>Notes</h2><p>Details</p></section><footer><p>Contact</p></footer></div>';
try{$footerResult=OpenDesignComposition::apply(['html'=>$webFooterHtml,'css'=>''],$webWithFooter);compositionCheck(str_contains($footerResult['html'],'ai-region-footer'),'A complementary pinned footer role is permitted and normalized');}
catch(Throwable){compositionCheck(false,'A complementary pinned footer role is permitted and normalized');}
$webBadFooterHtml='<div class="ai-scope-abcdef"><section class="ai-hero"><div class="ai-hero-copy"><h1>Title</h1></div><div class="ai-image-slot"></div></section><footer><p>Contact</p></footer><section><div><article><h3>One</h3></article><article><h3>Two</h3></article></div></section><section class="ai-region-metrics"><h2>Metrics</h2><p>42</p></section><section class="ai-region-article"><h2>Notes</h2><p>Details</p></section></div>';
try{OpenDesignComposition::apply(['html'=>$webBadFooterHtml,'css'=>''],$webWithFooter);compositionCheck(false,'A source footer before later content is rejected');}
catch(AiProviderFailure $e){compositionCheck($e->validationRule==='composition_region_order','A source footer before later content is rejected');}
$webContradictory=OpenDesignComposition::extract('web-prototype','project',['source region order: prominent Hero → primary article','source region order: primary article → prominent Hero'],[]);
compositionCheck($webContradictory===null,'Contradictory pinned partial orders fail closed instead of selecting one silently');
compositionCheck(OpenDesignComposition::extract('saas-landing','product',[],[])===null,'No extracted source structure cannot produce a source contract');

$ordered=OpenDesignComposition::extract('saas-landing','product',['source region order: top navigation → prominent hero → repeatable feature group → separate proof region'],[]);
compositionCheck($ordered['regionOrder']===['navigation','hero','features','proof'],'Pinned source region order is reduced to bounded app-owned roles');
$saasPrompt=OpenDesignComposition::prompt($ordered);
compositionCheck(str_contains($saasPrompt,'Assign every owner-supplied detail to exactly one location')&&str_contains($saasPrompt,'Do not restate or paraphrase an already-used fact elsewhere'),'SaaS source contract assigns facts once across Hero and capability items');
$orderedHtml='<div class="ai-scope-abcdef"><header><nav>Home</nav></header><section class="ai-hero"><div class="ai-hero-copy"><h1>Title</h1></div></section><section><div><article><h3>One</h3></article><article><h3>Two</h3></article></div></section><section><div class="ai-region-proof"><h2>Proof</h2></div></section></div>';
$orderedResult=OpenDesignComposition::apply(['html'=>$orderedHtml,'css'=>''],$ordered);
compositionCheck(strpos($orderedResult['html'],'ai-region-features')<strpos($orderedResult['html'],'ai-region-proof'),'Matching ordered regions pass with server-owned role markers');
$reversedHtml='<div class="ai-scope-abcdef"><header><nav>Home</nav></header><section class="ai-hero"><div class="ai-hero-copy"><h1>Title</h1></div></section><section><div class="ai-region-proof"><h2>Proof</h2></div></section><section><div><article><h3>One</h3></article><article><h3>Two</h3></article></div></section></div>';
try{OpenDesignComposition::apply(['html'=>$reversedHtml,'css'=>''],$ordered);throw new RuntimeException('Reversed source regions should be rejected');}
catch(AiProviderFailure $e){compositionCheck($e->validationRule==='composition_region_order','Reversed source region order is rejected with a safe validation code');}
$navigationAfterHero='<div class="ai-scope-abcdef"><section class="ai-hero"><div class="ai-hero-copy"><h1>Title</h1></div></section><header><nav>Home</nav></header><section><div><article><h3>One</h3></article><article><h3>Two</h3></article></div></section></div>';
try{OpenDesignComposition::apply(['html'=>$navigationAfterHero,'css'=>''],$ordered);throw new RuntimeException('Late top navigation should be rejected');}
catch(AiProviderFailure $e){compositionCheck($e->validationRule==='composition_navigation_order','Top navigation after the Hero is rejected');}
$split=OpenDesignComposition::extract('saas-landing','product',['split hero with adjacent copy and image regions'],[]);
$splitHtml='<div class="ai-scope-abcdef"><section class="ai-hero"><div class="ai-hero-copy"><h1>Title</h1><p>Intro</p></div><div class="ai-image-slot"></div></section><section><div><article><h3>One</h3></article><article><h3>Two</h3></article></div></section></div>';
$splitResult=OpenDesignComposition::apply(['html'=>$splitHtml,'css'=>''],$split);
compositionCheck(str_contains($splitResult['html'],'ai-source-split')&&str_contains($splitResult['css'],'grid-template-columns:minmax(0,1fr) minmax(0,1fr)'),'Split Hero keeps independent copy and media regions with responsive grid rules');
$copyWide=OpenDesignComposition::extract('saas-landing','product',['asymmetric split Hero with adjacent copy and image regions','source Hero balance: copy-dominant split'],[]);
$copyWidePrompt=OpenDesignComposition::prompt($copyWide);
$copyWideResult=OpenDesignComposition::apply(['html'=>$splitHtml,'css'=>''],$copyWide);
compositionCheck($copyWide['heroBalance']==='copy-dominant_split'&&str_contains($copyWidePrompt,'wider copy region beside a narrower image region')&&str_contains($copyWideResult['css'],'grid-template-columns:minmax(0,1.3fr) minmax(0,.7fr)'),'Copy-dominant source balance survives blueprint, model prompt, and desktop adapter');
$invalidBalance=$copyWide;$invalidBalance['heroLayout']='natural';
try{OpenDesignComposition::apply(['html'=>$splitHtml,'css'=>''],$invalidBalance);throw new RuntimeException('Unsplit Hero should not accept split balance');}
catch(AiProviderFailure $e){compositionCheck($e->validationRule==='composition_blueprint','Hero balance without a split layout is rejected as an invalid contract');}
$mediaWide=OpenDesignComposition::extract('saas-landing','product',['asymmetric split Hero with adjacent copy and image regions','source Hero balance: media-dominant split'],[]);
$mediaWideResult=OpenDesignComposition::apply(['html'=>$splitHtml,'css'=>''],$mediaWide);
compositionCheck($mediaWide['heroBalance']==='media-dominant_split'&&str_contains(OpenDesignComposition::prompt($mediaWide),'wider image region beside a narrower copy region')&&str_contains($mediaWideResult['css'],'grid-template-columns:minmax(0,.7fr) minmax(0,1.3fr)'),'Media-dominant source balance survives blueprint, model prompt, and desktop adapter');
compositionCheck(str_contains($copyWideResult['css'],'max-width:980px')&&str_contains($copyWideResult['css'],'grid-template-columns:minmax(0,1fr) minmax(0,1fr)')&&str_contains($copyWideResult['css'],'max-width:767px'),'Semantic Hero balance reflows to equal Tablet columns and one Mobile column');
$invalidSplit=str_replace('<div class="ai-image-slot"></div>','<div class="ai-placeholder"></div>',$splitHtml);
try{OpenDesignComposition::apply(['html'=>$invalidSplit,'css'=>''],$split);throw new RuntimeException('Split Hero without media should be rejected');}
catch(AiProviderFailure $e){compositionCheck($e->validationRule==='composition_hero_geometry','Split geometry without distinct media is rejected');}
compositionCheck(str_contains($orderedResult['css'],'max-width:1120px')&&str_contains($orderedResult['css'],'padding:48px 32px')&&!str_contains($orderedResult['css'],'.ai-source-frame section h2{')&&str_contains($orderedResult['css'],'.ai-source-frame section p{overflow-wrap:anywhere}'),'App-owned frame keeps shared geometry and overflow protection without overriding generated typography or palette');
$authoredTypography='.ai-scope-abcdef.ai-source-frame section h2{font-size:72px;color:#123456;line-height:0.95}.ai-scope-abcdef.ai-source-frame section p{line-height:1.4}';
$authoredTypographyResult=OpenDesignComposition::apply(['html'=>$orderedHtml,'css'=>$authoredTypography],$ordered);
compositionCheck(str_contains($authoredTypographyResult['css'],$authoredTypography),'Source adapter preserves authored section typography and color verbatim');

$badTopologies=[
 'web-prototype'=>['<section><p>Unstructured content</p></section>','composition_collection'],
 'blog-post'=>['<section><p>Generic stacked section</p></section>','composition_article'],
 'docs-page'=>['<div class="ai-doc-layout"><article><section><p>Guide</p></section></article><nav></nav><aside></aside></div>','composition_docs'],
 'dashboard'=>['<section><p>Operational records</p></section>','composition_rail'],
 'waitlist-page'=>['<section><p>Status</p></section><section></section><section></section>','composition_waitlist'],
];
foreach($badTopologies as $id=>[$body,$rule]){
 $contract=OpenDesignComposition::extract($id,'product',['source region order: prominent Hero'],[]);
 $html='<div class="ai-scope-abcdef"><section class="ai-hero"><div class="ai-hero-copy"><h1>QA</h1></div></section>'.$body.'</div>';
 try{OpenDesignComposition::apply(['html'=>$html,'css'=>''],$contract);throw new RuntimeException($id.' generic or malformed topology should be rejected');}
 catch(AiProviderFailure $e){compositionCheck($e->validationRule===$rule,$id.' malformed or generic topology is rejected explicitly');}
}
$logContract=OpenDesignComposition::extract('web-prototype','editorial-log-list',['source region order: prominent Hero'],[]);
$logHtml='<div class="ai-scope-abcdef"><section class="ai-hero"><div class="ai-hero-copy"><h1>Field notes</h1></div></section><section><ul><li><time>2024</time><h3>Observation A</h3></li><li><time>2025</time><h3>Observation B</h3></li></ul></section></div>';
compositionCheck(str_contains(OpenDesignComposition::apply(['html'=>$logHtml,'css'=>''],$logContract)['html'],'ai-source-hero'),'Editorial log-list variant accepts two dated records without landing-page sections');


$fallbackSource=new OpenDesignTemplateSource(sys_get_temp_dir().'/juzimder-app-fallback-'.bin2hex(random_bytes(5)));
$serviceFallback=OpenDesignTemplateGuidance::resolve('Мастерская проводит занятия: расписание, адрес, стоимость, запись, телефон.',$fallbackSource);
compositionCheck(($serviceFallback['selection']??null)==='no_match'&&($serviceFallback['status']??null)==='not_matched'&&array_key_exists('templateId',$serviceFallback)&&$serviceFallback['templateId']===null&&($serviceFallback['fallbackArchetype']??null)==='service'&&!str_contains($serviceFallback['prompt'],'matched product or service landing'),'App service archetype fallback stays separate from the loaded external SaaS template');

$selection=null;OpenDesignTemplateGuidance::match('Документация и лист ожидания',$selection);
compositionCheck($selection==='ambiguous','Equal explicit archetypes remain ambiguous');
OpenDesignTemplateGuidance::match('Небольшой сайт без уточнений',$selection);compositionCheck($selection==='no_match','No-match remains distinct');
compositionCheck((OpenDesignTemplateGuidance::match('Геологический дневник экспедиции')['id']??null)==='blog-post','Geological diary matches an editorial source without design instructions');
$offline=new OpenDesignTemplateSource(sys_get_temp_dir().'/juzimder-contract-offline-'.bin2hex(random_bytes(5)),static fn()=>throw new RuntimeException('offline'));
compositionCheck(OpenDesignTemplateGuidance::resolve('CRM для команды',$offline)['status']==='unavailable','Network failure cannot claim loaded blueprint');
$method=new ReflectionMethod(AiGeneratedWebsite::class,'auditCrossSectionRepetition');
foreach([
 ['Альфа бета гамма дельта эпсилон зета.','Альфа бета гамма дельта эпсилон зета.','both'],
 ['Альфа бета гамма дельта эпсилон зета эта тета.','Альфа бета гамма каппа лямбда мю дельта ню кси омикрон.','ordered_run'],
] as [$a,$b,$type]){
 try{$method->invoke(null,'<section><p>'.$a.'</p></section><section><p>'.$b.'</p></section>');throw new RuntimeException('Expected repeated facts');}
 catch(AiProviderFailure $e){$evidence=$e->safeValidationEvidence();compositionCheck($e->validationRule==='copy_repeated_facts'&&$evidence['overlapType']===$type&&$evidence['sectionIndexes']===[1,2]&&!str_contains(json_encode($evidence),'Альфа'),'Minimal repeated-copy example reports '.$type.' and coordinates without copy');}
}
$method->invoke(null,'<section><p>Альфа бета гамма дельта эпсилон зета.</p></section><section><p>Каппа лямбда сигма омега тета.</p></section>');
compositionCheck(true,'Distinct factual paragraphs are accepted by unchanged repetition rule');

$signalMethod=new ReflectionMethod(OpenDesignTemplateGuidance::class,'visualSignals');
$blueprintMethod=new ReflectionMethod(OpenDesignTemplateGuidance::class,'compositionBlueprint');
$copyWideSignals=$blueprintMethod->invoke(null,'<style>.hero{display:grid;grid-template-columns:1.4fr .6fr}</style><section class="hero"><h1>Title</h1><img src="https://example.invalid/a.jpg"></section>');
$mediaWideSignals=$blueprintMethod->invoke(null,'<style>.hero{display:grid;grid-template-columns:1.4fr .6fr}</style><section class="hero"><img src="https://example.invalid/a.jpg"><h1>Title</h1></section>');
compositionCheck(in_array('source Hero balance: copy-dominant split',$copyWideSignals,true)&&in_array('source Hero balance: media-dominant split',$mediaWideSignals,true),'Source extractor associates the wider fraction with the text or image role by reading order');
$shorthand=$signalMethod->invoke(null,'<style>body{font:18px/1.6 Georgia,serif}nav{font-family:system-ui,sans-serif}</style>','blog-post');
compositionCheck(in_array('serif typography scoped to the article reading column',$shorthand,true),'Inherited serif role survives source font shorthand');
$sans=$signalMethod->invoke(null,'<style>article{font-family:system-ui,sans-serif}</style>','blog-post');
compositionCheck(!in_array('serif typography scoped to the article reading column',$sans,true),'Sans-serif is not mistaken for serif through a hyphen boundary');
