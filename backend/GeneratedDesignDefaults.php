<?php
declare(strict_types=1);
namespace Juzimder;

/** Deterministic defaults applied after the HTML/CSS allowlists, before Skill audits. */
final class GeneratedDesignDefaults {
    public static function apply(array $design):array {
        $dom=new \DOMDocument('1.0','UTF-8');$dom->loadHTML('<?xml encoding="UTF-8">'.$design['html'],LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD|LIBXML_NOERROR|LIBXML_NOWARNING);
        $xpath=new \DOMXPath($dom);$root=$xpath->query('//div')->item(0);if(!$root instanceof \DOMElement)return $design;
        $scope=explode(' ',$root->getAttribute('class'))[0];
        $desktop=preg_replace('/@media[^{]+\{((?:[^{}]|\{[^{}]*\})*)\}/s','',$design['css']);
        preg_match_all('/([^{}]+)\{([^{}]*)\}/',$desktop,$matches,PREG_SET_ORDER);$rules=[];
        foreach($matches as $match)foreach(explode(',',$match[1]) as $selector){$props=[];foreach(explode(';',$match[2]) as $decl){$parts=explode(':',$decl,2);if(count($parts)===2)$props[trim($parts[0])]=trim($parts[1]);}$rules[]=[trim($selector),$props];}
        $value=static function(\DOMElement $el,string $property)use($rules):?string{
            $best=-1;$result=null;
            foreach($rules as [$selector,$props]){if(!isset($props[$property])||!self::matches($el,$selector))continue;$score=substr_count($selector,'.')*100+preg_match_all('/(?:^|\s)[a-z]+/',$selector);if($score>=$best){$best=$score;$result=$props[$property];}}
            return $result;
        };
        $hero=$xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," ai-hero ")]')->item(0);
        $alignment='left';$heading=$hero instanceof \DOMElement?$hero->getElementsByTagName('h1')->item(0):null;
        for($node=$heading??$hero;$node instanceof \DOMElement;$node=$node->parentNode){$found=$value($node,'text-align');if($found!==null&&$found!=='inherit'){$alignment=$found;break;}}
        $extra='';$blocks=[];
        if($hero instanceof \DOMElement && ($radius=$value($hero,'border-radius'))!==null && !in_array($radius,['0','0px','none','inherit'],true)) {
            self::addClass($hero,'ai-hero-rounded');
            $extra.="\n.$scope .ai-hero.ai-hero-rounded{overflow:hidden}";
        }
        foreach($xpath->query('//section') as $section)$blocks[]=$section;
        if($hero instanceof \DOMElement&&!in_array($hero,$blocks,true))array_unshift($blocks,$hero);
        foreach($blocks as $block){
            if($alignment==='left'||$alignment==='start')self::addClass($block,'ai-default-left');
            $h=$block->getElementsByTagName('h1')->item(0)??$block->getElementsByTagName('h2')->item(0);
            $badge=$xpath->query('.//*[contains(concat(" ",normalize-space(@class)," ")," ai-pill-badge ")]',$block)->item(0);
            if(!$badge){$badge=$dom->createElement('span');$badge->appendChild($dom->createTextNode(mb_substr(trim($h?->textContent??'Раздел'),0,40)));self::addClass($badge,'ai-pill-badge');if($h)$h->parentNode->insertBefore($badge,$h);else $block->insertBefore($badge,$block->firstChild);}
        }
        $radii=[];$cardRadii=[];foreach($xpath->query('//article|//button|//a|//div') as $element){$radius=$value($element,'border-radius');if($radius!==null&&$radius!=='inherit')$radii[$radius]=($radii[$radius]??0)+1;if($radius!==null&&($element->tagName==='article'||str_contains($element->getAttribute('class'),'card')))$cardRadii[$radius]=($cardRadii[$radius]??0)+1;}arsort($radii);arsort($cardRadii);$fallback=array_key_first($cardRadii)??array_key_first($radii)??'0px';
        $index=0;
        foreach($xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," ai-image-slot ")]') as $slot){
            $radius=$value($slot,'border-radius');if($radius===null||$radius==='inherit')for($node=$slot->parentNode;$node instanceof \DOMElement&&$node!==$root;$node=$node->parentNode){$radius=$value($node,'border-radius');if($radius!==null&&$radius!=='inherit')break;}
            if($radius===null||$radius==='inherit')$radius=$fallback;$name='ai-photo-radius-'.++$index;self::addClass($slot,$name);$extra.="\n.$scope .$name.$name,.$scope .$name img{border-radius:$radius}";
        }
        // Generated navigation must remain usable in narrow preview widths even
        // when the model forgets to wrap its links or gives the menu a fixed width.
        $extra.="\n.$scope nav{display:flex;flex-wrap:wrap;align-items:center;gap:12px;max-width:100%;min-width:0}\n.$scope .ai-nav.ai-nav{display:flex;flex-wrap:wrap;align-items:center;max-width:100%;min-width:0}\n.$scope nav ul{display:flex;flex-wrap:wrap;align-items:center;gap:12px;list-style-type:none;margin:0;padding:0;max-width:100%;min-width:0}\n.$scope nav a{max-width:100%;min-width:0}";
        $extra.="\n.$scope .ai-default-left.ai-default-left,.$scope .ai-default-left h1,.$scope .ai-default-left h2,.$scope .ai-default-left p{text-align:left}\n.$scope .ai-pill-badge.ai-pill-badge{display:inline-block;border-radius:9999px;padding:6px 12px;border:1px solid currentColor;font-size:12px;line-height:1.3;margin:0 0 16px;max-width:100%;width:fit-content;align-self:flex-start;justify-self:start}";
        // One opt-in composition, using the model's existing native elements.
        if ($hero instanceof \DOMElement && self::hasClass($hero, 'ai-hero-split') && !self::hasClass($hero, 'ai-hero-background')) {
            $children = array_values(iterator_to_array($hero->childNodes));
            $elements = array_values(array_filter($children, static fn($node) => $node instanceof \DOMElement));
            $text = null; $media = null;
            foreach ($elements as $element) {
                if ($element->getElementsByTagName('h1')->length) $text = $element;
                if (self::hasClass($element, 'ai-image-slot')) $media = $element;
            }
            // Unknown or richer structures stay untouched; do not guess or discard content.
            if (count($elements) === 2 && $text && $media && $text !== $media) {
                self::addClass($hero, 'ai-split-ready');
                self::addClass($text, 'ai-split-copy');
                $hero->insertBefore($text, $media);
                $base = ".$scope .ai-hero.ai-split-ready";
                $extra .= "\n{$base}{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);align-items:center;gap:48px;padding:64px 32px}\n"
                    . "$base .ai-split-copy{display:flex;flex-direction:column;align-items:flex-start;min-width:0;max-width:100%;gap:20px}\n"
                    . "$base h1,$base p{margin:0;max-width:100%}\n"
                    . "$base .ai-pill-badge{margin:0;width:fit-content}\n"
                    . "$base .ai-split-copy a,$base .ai-split-copy button{width:fit-content;max-width:100%;align-self:flex-start}\n"
                    . "$base .ai-image-slot{width:100%;height:auto;min-width:0;min-height:0;aspect-ratio:5/4;align-self:center}\n"
                    . "$base .ai-image-slot img{width:100%;height:100%;min-height:0;object-fit:cover}\n"
                    . "@media(max-width:767px){{$base}{grid-template-columns:minmax(0,1fr);padding:32px 20px;gap:28px}}\n";
            }
        }
        $design['html']=$dom->saveHTML($root);$design['css'].=$extra;return $design;
    }
    private static function hasClass(\DOMElement $el,string $class):bool{return in_array($class,preg_split('/\s+/',trim($el->getAttribute('class'))),true);}
    private static function addClass(\DOMElement $el,string $class):void{$el->setAttribute('class',trim($el->getAttribute('class').' '.$class));}
    private static function matches(\DOMElement $el,string $selector):bool {
        $tokens=preg_split('/\s+/',trim($selector));$node=$el;
        foreach(array_reverse($tokens) as $index=>$token){$found=false;while($node instanceof \DOMElement){preg_match('/^([a-z][a-z0-9-]*)?/i',$token,$tag);preg_match_all('/\.([a-z0-9_-]+)/i',$token,$classes);$own=preg_split('/\s+/',$node->getAttribute('class'));$ok=(!$tag[0]||strtolower($node->tagName)===strtolower($tag[0]))&&!array_diff($classes[1],$own);if($ok){$found=true;$node=$node->parentNode;break;}if($index===0)return false;$node=$node->parentNode;}if(!$found)return false;}
        return true;
    }
}
