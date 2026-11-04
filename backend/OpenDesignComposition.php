<?php
declare(strict_types=1);
namespace Juzimder;

/** App-owned semantic adapter. Receives bounded roles, never external markup or CSS. */
final class OpenDesignComposition {
    public static function extract(string $id,string $variant,array $signals,array $visual): ?array {
        if($signals===[])return null;
        $supported=array_column(OpenDesignTemplateSource::catalog(),'id');
        if(!in_array($id,$supported,true))return null;
        $joined=implode('; ',$signals);
        $columns=null;
        if(preg_match('/repeated content geometry: ([2-9]) aligned columns/',$joined,$m))$columns=(int)$m[1];
        $visualText=implode('; ',array_filter($visual,'is_string'));
        $alignment=null;
        foreach(['center','left','right'] as $candidate){
            if(str_contains(strtolower($joined.'; '.$visualText),$candidate.'-aligned hero')
                ||($candidate==='center'&&str_contains(strtolower($joined.'; '.$visualText),'centered hero content group'))){$alignment=$candidate;break;}
        }
        $signalsForGeometry=strtolower($joined.'; '.$visualText);
        $heroLayout='natural';
        if(str_contains($signalsForGeometry,'asymmetric split hero')||str_contains($signalsForGeometry,'asymmetric multi-region hero grid'))$heroLayout='asymmetric_split';
        elseif(str_contains($signalsForGeometry,'split hero with adjacent copy and image regions')
            ||str_contains($signalsForGeometry,'balanced multi-region hero grid')
            ||str_contains($signalsForGeometry,'multi-column hero composition'))$heroLayout='split';
        $heroBalance=null;
        foreach(['copy-dominant split','media-dominant split','balanced split'] as $candidate){
            if(str_contains($signalsForGeometry,'source hero balance: '.$candidate)){$heroBalance=str_replace(' ','_',$candidate);break;}
        }
        $regionOrder=self::sourceRegionOrder($signals);
        if($regionOrder===null)return null;
        return ['version'=>1,'templateId'=>$id,'variant'=>$variant,'columns'=>$columns,'alignment'=>$alignment,'heroLayout'=>$heroLayout,'heroBalance'=>$heroBalance,'regionOrder'=>$regionOrder,
            'spacing'=>str_contains($joined,'generous inter-region')?'generous':(str_contains($joined,'compact inter-region')?'compact':'regular'),
            'surface'=>str_contains($joined,'soft-corner')?'soft':(str_contains($joined,'square-corner')?'square':null),
            'signalCount'=>count($signals),'sourceDigest'=>hash('sha256',json_encode([$signals,$visual],JSON_THROW_ON_ERROR))];
    }
    /** Keep only reviewed semantic role tokens; source markup, CSS and labels never cross this boundary. */
    private static function sourceRegionOrder(array $signals): ?array {
        $roles=[
            'top navigation'=>'navigation','prominent hero'=>'hero','repeatable feature group'=>'features',
            'separate proof region'=>'proof','offer or pricing group'=>'offers','comparison region'=>'comparison',
            'faq region'=>'faq','compact metric group'=>'metrics','persistent navigation rail'=>'navigation_rail',
            'separate contents rail'=>'contents_rail','operational work area'=>'workspace','closing action region'=>'action',
            'primary article'=>'article','secondary side region'=>'aside','closing footer'=>'footer',
        ];
        $firstSeen=[];$edges=[];$inDegree=[];
        foreach($signals as $signal){
            if(!is_string($signal)||!str_starts_with($signal,'source region order:'))continue;
            $ordered=[];
            foreach(explode('→',substr($signal,strlen('source region order:'))) as $label){
                $role=$roles[strtolower(trim($label))]??null;
                if($role!==null&&!in_array($role,$ordered,true))$ordered[]=$role;
            }
            $ordered=array_slice($ordered,0,8);
            foreach($ordered as $role){
                if(!array_key_exists($role,$firstSeen))$firstSeen[$role]=count($firstSeen);
                $edges[$role]??=[];$inDegree[$role]??=0;
            }
            for($index=1;$index<count($ordered);$index++){
                $before=$ordered[$index-1];$after=$ordered[$index];
                if(isset($edges[$before][$after]))continue;
                $edges[$before][$after]=true;$inDegree[$after]++;
            }
        }
        // A pinned reference may provide complementary partial orders in its
        // layout guide and visual example. Merge compatible constraints instead
        // of discarding every shorter sequence after choosing the longest one.
        // A closing footer is terminal whenever the source identifies one.
        if(isset($firstSeen['footer']))foreach(array_keys($firstSeen) as $role){
            if($role==='footer'||isset($edges[$role]['footer']))continue;
            $edges[$role]['footer']=true;$inDegree['footer']++;
        }
        $best=[];$remaining=$inDegree;
        while(count($best)<count($firstSeen)){
            $ready=[];
            foreach($remaining as $role=>$degree)if($degree===0&&!in_array($role,$best,true))$ready[]=$role;
            if($ready===[])return null;
            usort($ready,static fn(string $left,string $right):int=>$firstSeen[$left]<=>$firstSeen[$right]);
            $next=$ready[0];$best[]=$next;$remaining[$next]=-1;
            foreach(array_keys($edges[$next]??[]) as $after)$remaining[$after]--;
        }
        // A source can wrap its Hero and body in one <article>. The extractor's
        // flattened DOM walk then reports that container before its Hero child;
        // the renderer emits page-flow regions as siblings, so normalize the
        // visual sequence to navigation, Hero, then the containing article role.
        if(($heroIndex=array_search('hero',$best,true))!==false&&$heroIndex>0){
            array_splice($best,$heroIndex,1);
            $navigationIndex=array_search('navigation',$best,true);
            array_splice($best,$navigationIndex===false?0:$navigationIndex+1,0,['hero']);
        }
        return $best;
    }
    public static function prompt(array $contract): string {
        $structure=match($contract['templateId']){
            'saas-landing'=>'One Hero followed by a capability/service group: at least two sibling article elements or li items with headings. Keep only supplied capabilities. Compact top navigation is optional when destinations exist.',
            'web-prototype'=>$contract['variant']==='editorial-log-list'?'Exactly two sections, Hero then chronological list; at least two dated open rows.':'A source-shaped Hero followed by at least two real-work records. Preserve a split copy-and-image Hero when source geometry calls for it; keep the record collection after the Hero.',
            'blog-post'=>'Compact Hero followed by one readable article body. No split Hero or marketing card grid.',
            'docs-page'=>'Compact Hero then one ai-doc-layout with exactly three direct children nav, article, aside in that order.',
            'pricing-page'=>'Hero then offer cards; place supported comparison and FAQ regions after offers in the source order. Never invent offers or prices to meet this contract.',
            'waitlist-page'=>'Two or three sections: centered text Hero then restrained status context. No split Hero or invented signup controls.',
            'dashboard'=>'Compact Hero, exactly one ai-rail navigation and a separate operational content area.',
        };
        $copyPlacement=$contract['templateId']==='saas-landing'
            ?'Keep the Hero as a concise statement of the product purpose; do not enumerate facts that belong in the capability group. Assign every owner-supplied detail to exactly one location, and make each capability item distinct from the Hero and from its sibling items. Do not restate or paraphrase an already-used fact elsewhere. If the brief cannot support the required distinct capability items, do not invent details.'
            :'';
        $geometry=match($contract['heroLayout']??'natural'){'asymmetric_split'=>'asymmetric split copy-and-image composition','split'=>'split copy-and-image composition',default=>'source-native Hero geometry'};
        $geometry.='; '.match($contract['heroBalance']??null){'copy-dominant_split'=>'wider copy region beside a narrower image region','media-dominant_split'=>'wider image region beside a narrower copy region','balanced_split'=>'balanced copy and image regions',default=>'preserve source-relative Hero proportions when a split is present'};
        $order=is_array($contract['regionOrder']??null)&&$contract['regionOrder']!==[]?implode(' → ',$contract['regionOrder']):'content-dependent';
        $roles=array_values(array_unique(array_merge(['hero'],$contract['regionOrder']??[],self::adapterRoles($contract['templateId'],$contract['variant']??''))));
        $roleNames=implode(', ',array_map(static fn(string $role):string=>str_replace('_','-',$role),$roles));
        $markers='Mark every non-Hero page-level section with exactly one app-owned ai-region-<role> class from this source-derived allowlist: '.$roleNames.'. Do not add regions that are absent from this allowlist; place any additional supplied details inside the closest allowed source region. Do not put a marker on a card when the containing section is the region. These markers have no visual styling. The server rejects unmarked or out-of-contract page regions.';
        return $structure.' Preserve supported source-region order: '.$order.'. Omit a source region only when the owner brief has no supporting content; never fabricate content to fill it. '.$copyPlacement.' '.$markers.' Hero geometry: '.$geometry.'. The server checks this composition after safe rendering. Source-derived repeated-column upper bound: '.($contract['columns']??'content-dependent').'; Hero alignment: '.($contract['alignment']??'preserve the generated alignment within the selected source layout').'.';
    }
    /** Validate semantic topology before adding only app-owned spatial rules. Never fabricate content. */
    public static function apply(array $design,array $contract): array {
        $dom=new \DOMDocument('1.0','UTF-8');$previous=libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$design['html'],LIBXML_NONET|LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD|LIBXML_NOERROR|LIBXML_NOWARNING);
        libxml_clear_errors();libxml_use_internal_errors($previous);$xp=new \DOMXPath($dom);
        $has=static fn(string $c):string=>'contains(concat(" ",normalize-space(@class)," ")," '.$c.' ")';
        $root=$dom->documentElement;$hero=$xp->query('//section['.$has('ai-hero').']')->item(0);
        $reject=static function(string $rule):never{throw new AiProviderFailure('Open Design composition does not conform.',false,'invalid_markup','composition_'.$rule);};
        $supported=array_column(OpenDesignTemplateSource::catalog(),'id');
        $allowedRoles=['navigation','hero','features','proof','offers','comparison','faq','metrics','navigation_rail','contents_rail','workspace','action','article','aside','footer','records','status'];
        $regionOrder=$contract['regionOrder']??null;
        if(($contract['version']??null)!==1||!in_array($contract['templateId']??null,$supported,true)
            ||!in_array($contract['heroLayout']??null,['natural','split','asymmetric_split'],true)
            ||!in_array($contract['heroBalance']??null,[null,'copy-dominant_split','media-dominant_split','balanced_split'],true)
            ||(($contract['heroBalance']??null)!==null&&!in_array($contract['heroLayout']??null,['split','asymmetric_split'],true))
            ||!in_array($contract['alignment']??null,[null,'left','center','right'],true)
            ||($regionOrder!==null&&(!is_array($regionOrder)||!array_is_list($regionOrder)||count($regionOrder)>8||array_diff($regionOrder,$allowedRoles)!==[])))$reject('blueprint');
        if(!$root instanceof \DOMElement||!$hero instanceof \DOMElement)$reject('hero');
        $sections=iterator_to_array($xp->query('//section'));if(count($sections)<2||$sections[0]!==$hero)$reject('order');
        $rootWalk=iterator_to_array($xp->query('.//*',$root));$positions=[];
        foreach($rootWalk as $position=>$node)if($node instanceof \DOMElement)$positions[$node->getNodePath()]=$position;
        $heroPosition=$positions[$hero->getNodePath()]??null;
        if($heroPosition===null)$reject('hero');
        $id=$contract['templateId'];$group=null;$items=[];
        if(in_array($id,['saas-landing','pricing-page','web-prototype'],true)){
            foreach($xp->query('//section[not('.$has('ai-hero').')]//*[self::article or self::li][.//h2 or .//h3 or .//h4]') as $item){
                $parent=$item->parentNode;$siblings=[];
                foreach($parent->childNodes as $sibling)if($sibling instanceof \DOMElement&&$sibling->tagName===$item->tagName&&$xp->query('.//*[self::h2 or self::h3 or self::h4]',$sibling)->length)$siblings[]=$sibling;
                if(count($siblings)>=2){$group=$parent;$items=$siblings;break;}
            }
            if(!$group instanceof \DOMElement)$reject('collection');
            if($id==='web-prototype'&&$contract['variant']==='editorial-log-list'){
                if(count($sections)!==2)$reject('records');
                foreach($items as $item)if(!preg_match('/(?<![0-9])(?:19|20)[0-9]{2}(?![0-9])/u',$item->textContent))$reject('records');
            }
            $collectionSection=null;for($node=$group;$node instanceof \DOMElement;$node=$node->parentNode)if($node->tagName==='section'){$collectionSection=$node;break;}
            if(!$collectionSection instanceof \DOMElement||($positions[$collectionSection->getNodePath()]??-1)<=$heroPosition)$reject('collection_order');
            $collectionRole=$id==='pricing-page'?'offers':($id==='saas-landing'&&($contract['variant']??null)==='service'?'offers':($id==='web-prototype'&&($contract['variant']??null)==='editorial-log-list'?'records':'features'));
            self::addClass($collectionSection,'ai-region-'.$collectionRole);
        }
        $orderedRoles=is_array($contract['regionOrder']??null)?$contract['regionOrder']:[];
        // A docs navigation rail is part of the post-Hero three-column shell, not top navigation.
        $pageNavigation=$xp->query('//*[self::header or self::nav][not(contains(concat(" ",normalize-space(@class)," ")," ai-rail ")) and not(contains(concat(" ",normalize-space(@class)," ")," ai-region-navigation-rail ")) and not(ancestor::*[contains(concat(" ",normalize-space(@class)," ")," ai-doc-layout ")])]');

        foreach($pageNavigation as $nav){
            $navPosition=$positions[$nav->getNodePath()]??null;
            if(in_array('navigation',$orderedRoles,true)&&$navPosition!==null&&$heroPosition!==null&&$navPosition>$heroPosition)$reject('navigation_order');
        }
        // Check source order among the optional regions the model actually emits. Missing roles stay optional.
        foreach($orderedRoles as $role){
            if($role==='hero')self::addClass($hero,'ai-region-hero');
            elseif($role==='navigation')foreach($pageNavigation as $nav)self::addClass($nav,'ai-region-navigation');
        }
        if($id==='docs-page'){
            $shell=$xp->query('//*['.$has('ai-doc-layout').']')->item(0);$tags=[];
            if($shell)foreach($shell->childNodes as $child)if($child instanceof \DOMElement)$tags[]=$child->tagName;
            if($tags!==['nav','article','aside'])$reject('docs');
            if($shell instanceof \DOMElement){$shellPosition=$positions[$shell->getNodePath()]??null;if($shellPosition!==null&&$heroPosition!==null&&$shellPosition<$heroPosition)$reject('docs');
                self::addClass($shell->childNodes[0],'ai-region-navigation-rail');self::addClass($shell->childNodes[1],'ai-region-article');self::addClass($shell->childNodes[2],'ai-region-contents-rail');}
        }
        if($id==='dashboard'){
            $rail=$xp->query('//nav['.$has('ai-rail').']')->item(0);if(!$rail instanceof \DOMElement||$xp->query('//nav['.$has('ai-rail').']')->length!==1)$reject('rail');
            self::addClass($rail,'ai-region-navigation-rail');
            $workspace=$xp->query('//section[not('.$has('ai-hero').')]')->item(0);if(!$workspace instanceof \DOMElement)$reject('workspace');
            self::addClass($workspace,'ai-region-workspace');
        }
        if($id==='blog-post'){
            $article=$xp->query('//*['.$has('ai-blog-section').']')->item(0);
            if(!$article instanceof \DOMElement||$xp->query('ancestor::section['.$has('ai-hero').']',$article)->length>0)$reject('article');
            self::addClass($article,'ai-region-article');
        }
        if($id==='waitlist-page'){
            if(count($sections)>3)$reject('waitlist');
            foreach($sections as $section)if($section instanceof \DOMElement&&$section!==$hero){self::addClass($section,'ai-region-status');break;}
        }
        $scope=explode(' ',$root->getAttribute('class'))[0];
        if(!preg_match('/^ai-scope-[a-f0-9]+$/D',$scope))$reject('scope');
        foreach($xp->query('//*[self::header or self::nav or self::section or self::article or self::aside or self::footer]') as $region){
            if(!$region instanceof \DOMElement)continue;
            if($region->tagName==='footer')self::addClass($region,'ai-region-footer');
            elseif($region->tagName==='aside'&&$id!=='docs-page')self::addClass($region,'ai-region-aside');
        }
        $hero->setAttribute('class',trim($hero->getAttribute('class').' ai-source-hero ai-region-hero'));
        self::validateSourceRegions($xp,$root,$contract,$reject);
        // Derive hero geometry from the pinned source signals; split layouts fail closed if the output has no separate copy/media regions.
        $heroLayout=$contract['heroLayout']??'natural';$splitHero=in_array($heroLayout,['split','asymmetric_split'],true);
        $heroCopy=null;$heroMedia=null;
        if($splitHero){
            foreach($hero->childNodes as $child){
                if(!$child instanceof \DOMElement)continue;
                if($child->getElementsByTagName('h1')->length>0||$child->tagName==='h1')$heroCopy=$child;
                if(self::hasClass($child,'ai-image-slot')||$xp->query('.//*['.$has('ai-image-slot').']',$child)->length>0)$heroMedia=$child;
            }
            if(!$heroCopy instanceof \DOMElement||!$heroMedia instanceof \DOMElement||$heroCopy===$heroMedia)$reject('hero_geometry');
            self::addClass($hero,'ai-source-split');self::addClass($heroCopy,'ai-source-hero-copy');self::addClass($heroMedia,'ai-source-hero-media');
        }
        $regionPositions=[];
        foreach($orderedRoles as $role){
            $marker='ai-region-'.str_replace('_','-',$role);$node=$xp->query('//*['.$has($marker).']')->item(0);
            if($node instanceof \DOMElement&&isset($positions[$node->getNodePath()]))$regionPositions[$role]=$positions[$node->getNodePath()];
        }
        $previousPosition=null;
        foreach($orderedRoles as $role){
            if(!isset($regionPositions[$role]))continue;
            if($previousPosition!==null&&$regionPositions[$role]<$previousPosition)$reject('region_order');
            $previousPosition=$regionPositions[$role];
        }
        // Existing article/docs/dashboard adapters keep their detailed spatial rules.
        $css="\n.$scope .ai-hero.ai-source-hero{box-sizing:border-box;min-height:0;height:auto;max-height:none}";
        if($id==='waitlist-page')$css="\n.$scope .ai-hero.ai-source-hero{box-sizing:border-box;min-height:0;height:auto}";
        if($splitHero){
            $desktopColumns=match($contract['heroBalance']??null){
                'copy-dominant_split'=>'minmax(0,1.3fr) minmax(0,.7fr)',
                'media-dominant_split'=>'minmax(0,.7fr) minmax(0,1.3fr)',
                default=>$heroLayout==='asymmetric_split'?'minmax(0,1.1fr) minmax(0,.9fr)':'minmax(0,1fr) minmax(0,1fr)',
            };
            $tabletColumns='minmax(0,1fr) minmax(0,1fr)';
            $css.="\n.$scope .ai-hero.ai-source-hero.ai-source-split{display:grid;grid-template-columns:$desktopColumns;gap:clamp(24px,4vw,64px);align-items:center}"
                ."\n.$scope .ai-source-hero.ai-source-split .ai-source-hero-copy,.$scope .ai-source-hero.ai-source-split .ai-source-hero-media{min-width:0;max-width:100%;box-sizing:border-box}"
                ."\n@media(max-width:980px) and (min-width:768px){.$scope .ai-hero.ai-source-hero.ai-source-split{grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:32px}}"
                ."\n@media(max-width:767px){.$scope .ai-hero.ai-source-hero.ai-source-split{grid-template-columns:minmax(0,1fr);gap:24px}}";
        }
        $alignment=$contract['alignment']??null;
        if($alignment==='center'||$id==='waitlist-page')$css.="\n.$scope .ai-hero.ai-source-hero{display:block;text-align:center}\n.$scope .ai-source-hero .ai-hero-copy{width:100%;max-width:900px;margin-left:auto;margin-right:auto;text-align:center}";
        elseif($alignment==='left')$css.="\n.$scope .ai-hero.ai-source-hero,.$scope .ai-source-hero .ai-hero-copy{text-align:left}\n.$scope .ai-source-hero .ai-hero-copy{margin-left:0;margin-right:auto}";
        elseif($alignment==='right')$css.="\n.$scope .ai-hero.ai-source-hero,.$scope .ai-source-hero .ai-hero-copy{text-align:right}\n.$scope .ai-source-hero .ai-hero-copy{margin-left:auto;margin-right:0}";
        if($group instanceof \DOMElement){
            // Keep a section heading and intro outside the grid; only the detected peer records become grid items.
            if(!in_array($group->tagName,['ul','ol'],true)){
                $collection=$dom->createElement('div');$collection->setAttribute('class','ai-source-collection');
                $group->insertBefore($collection,$items[0]);
                foreach($items as $item)$collection->appendChild($item);
                $group=$collection;
            }else $group->setAttribute('class',trim($group->getAttribute('class').' ai-source-collection'));
            foreach($items as $item)$item->setAttribute('class',trim($item->getAttribute('class').' ai-source-item'));
            $columns=min(count($items),$contract['columns']??3);$tabletColumns=min($columns,2);
            $isLog=$id==='web-prototype'&&$contract['variant']==='editorial-log-list';
            $gap=match($contract['spacing']??'regular'){'compact'=>16,'generous'=>40,default=>24};
            $desktopColumns=$isLog?'1fr':'repeat('.$columns.', minmax(0,1fr))';
            $tabletColumnsCss=$isLog?'1fr':'repeat('.$tabletColumns.', minmax(0,1fr))';
            $css.="\n.$scope .ai-source-collection.ai-source-collection{display:grid;grid-template-columns:$desktopColumns;gap:{$gap}px;list-style:none}\n.$scope .ai-source-item{min-width:0;max-width:100%;box-sizing:border-box;overflow-wrap:anywhere}\n@media(max-width:980px) and (min-width:768px){.$scope .ai-source-collection.ai-source-collection{grid-template-columns:$tabletColumnsCss}}\n@media(max-width:767px){.$scope .ai-source-collection.ai-source-collection{grid-template-columns:minmax(0,1fr)}}";
        }
        if(in_array($id,['saas-landing','web-prototype','pricing-page','waitlist-page'],true)){
            $root->setAttribute('class',trim($root->getAttribute('class').' ai-source-frame'));
            $sectionGap=match($contract['spacing']??'regular'){'compact'=>32,'generous'=>64,default=>48};
            $frame=".$scope.ai-source-frame";
            $css.="\n{$frame}>main,{$frame}>div{box-sizing:border-box;min-width:0;max-width:100%}"
                ."\n{$frame}>header,{$frame}>footer{box-sizing:border-box;width:100%;max-width:1120px;min-width:0;margin-left:auto;margin-right:auto;padding:24px 32px}"
                ."\n{$frame}>section,{$frame}>main>section,{$frame}>div>section,{$frame}>main>div>section{box-sizing:border-box;width:100%;max-width:1120px;min-width:0;margin-left:auto;margin-right:auto;padding:{$sectionGap}px 32px}"
                // Keep source/model typography and palette intact; apply only a
                // wrapping guard so long generated copy cannot widen the canvas.
                ."\n{$frame} section p{overflow-wrap:anywhere}"
                ."\n{$frame} .ai-source-collection.ai-source-collection{box-sizing:border-box;width:100%;max-width:100%;margin-left:0;margin-right:0;padding-left:0;padding-right:0}"
                ."\n{$frame} .ai-source-hero .ai-image-slot{max-height:480px;overflow:hidden}"
                ."\n@media(max-width:980px) and (min-width:768px){{$frame}>section,{$frame}>main>section,{$frame}>div>section,{$frame}>main>div>section{padding:40px 24px}}"
                ."\n@media(max-width:767px){{$frame}>header,{$frame}>footer{padding:20px}{$frame}>section,{$frame}>main>section,{$frame}>div>section,{$frame}>main>div>section{padding:32px 20px}{$frame} .ai-source-hero .ai-image-slot{max-height:320px}}";
        }
        if($group instanceof \DOMElement && !($id==='web-prototype'&&$contract['variant']==='editorial-log-list') && ($contract['surface']??null)!==null){
            $radius=$contract['surface']==='soft'?'16px':'0';
            $css.="\n.$scope .ai-source-item.ai-source-item{border-radius:$radius;border:1px solid var(--juzimder-color-line,#dbe6de);padding:24px}";
        }
        $design['html']=$dom->saveHTML($root);$design['css'].=$css;return $design;
    }
    private static function hasClass(\DOMElement $element,string $class):bool {
        return preg_match('/(?:^|\s)'.preg_quote($class,'/').'(?:\s|$)/',$element->getAttribute('class'))===1;
    }
    private static function adapterRoles(string $id,string $variant):array {
        return match($id){
            'saas-landing'=>[$variant==='service'?'offers':'features'],
            'web-prototype'=>[$variant==='editorial-log-list'?'records':'features'],
            'blog-post'=>['article'],
            'docs-page'=>['navigation_rail','article','contents_rail'],
            'pricing-page'=>['offers'],
            'waitlist-page'=>['status'],
            'dashboard'=>['navigation_rail','metrics','workspace'],
            default=>[],
        };
    }
    /** Prevent unmodeled/generated sections from silently diluting the selected source composition. */
    private static function validateSourceRegions(\DOMXPath $xp,\DOMElement $root,array $contract,callable $reject):void {
        $roles=array_values(array_unique(array_merge(['hero'],$contract['regionOrder']??[],self::adapterRoles($contract['templateId']??'',(string)($contract['variant']??'')))));
        $allowed=array_fill_keys($roles,true);
        foreach($xp->query('.//*',$root) as $node){
            if(!$node instanceof \DOMElement)continue;
            foreach(preg_split('/\\s+/',trim($node->getAttribute('class')))?:[] as $class){
                if(!preg_match('/^ai-region-([a-z]+(?:-[a-z]+)*)$/D',$class,$match))continue;
                if(!isset($allowed[str_replace('-','_',$match[1])]))$reject('unmapped_region');
            }
        }
        foreach($xp->query('.//*[self::header or self::nav]',$root) as $navigation){
            if(!$navigation instanceof \DOMElement)continue;
            $insideDocs=$xp->query('ancestor::*[contains(concat(" ",normalize-space(@class)," ")," ai-doc-layout ")]',$navigation)->length>0;
            $insideRail=self::hasClass($navigation,'ai-rail')||self::hasClass($navigation,'ai-region-navigation-rail')
                ||$xp->query('ancestor::*[contains(concat(" ",normalize-space(@class)," ")," ai-rail ") or contains(concat(" ",normalize-space(@class)," ")," ai-region-navigation-rail ")]',$navigation)->length>0;
            if($insideDocs||$insideRail)continue;
            if(!isset($allowed['navigation'])||!self::hasClass($navigation,'ai-region-navigation'))$reject('unmapped_region');
        }
        foreach($xp->query('.//section[not(ancestor::section)]',$root) as $section){
            if(!$section instanceof \DOMElement)continue;
            $hasRegion=false;
            for($node=$section;$node instanceof \DOMElement;$node=$node->parentNode){
                foreach(preg_split('/\\s+/',trim($node->getAttribute('class')))?:[] as $class){
                    if(preg_match('/^ai-region-([a-z]+(?:-[a-z]+)*)$/D',$class,$match)&&isset($allowed[str_replace('-','_',$match[1])])){$hasRegion=true;break 2;}
                }
                if($node===$root)break;
            }
            if(!$hasRegion){
                foreach($section->childNodes as $child){
                    if(!$child instanceof \DOMElement)continue;
                    foreach(preg_split('/\\s+/',trim($child->getAttribute('class')))?:[] as $class){
                        if(preg_match('/^ai-region-([a-z]+(?:-[a-z]+)*)$/D',$class,$match)&&isset($allowed[str_replace('-','_',$match[1])])){$hasRegion=true;break 2;}
                    }
                }
            }
            if(!$hasRegion)$reject('unmapped_region');
        }
    }
    private static function addClass(\DOMElement $element,string $class):void {
        if(!self::hasClass($element,$class))$element->setAttribute('class',trim($element->getAttribute('class').' '.$class));
    }
}
