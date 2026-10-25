<?php
declare(strict_types=1);
namespace Juzimder;
require_once __DIR__.'/ZeroBlock.php';
final class ZeroBlockRenderer {
    private static function e(string $s): string{return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5,'UTF-8');}
    private static function n(int|float $v): string{return (string)(round($v,4)+0);}
    private static function vectorIcon(string $name): string {
        if(!in_array($name,ZeroBlock::VECTOR_ICONS,true))return '';
        static $catalogue=null;
        if($catalogue===null){$json=file_get_contents(__DIR__.'/../resources/tabler-editor/catalogue.json');$catalogue=is_string($json)?json_decode($json,true):null;if(!is_array($catalogue))throw new \RuntimeException('Tabler icon catalogue is unavailable.');}
        $svg=$catalogue[$name]??'';
        $svgStart=is_string($svg)?strpos($svg,'<svg'):false;
        return $svgStart!==false&&str_ends_with(trim($svg),'</svg>')?$svg:'';
    }
    private static function length(int|float $v,\stdClass $board,bool $autoScale): string{return self::n($autoScale?$v*100/$board->gridWidth:$v).($autoScale?'vw':'px');}
    public static function resolved(\stdClass $element,string $mode): array {
        $e=get_object_vars($element);$o=get_object_vars($element->overrides->$mode??new \stdClass());
        $e=array_replace($e,$o);foreach(['geometry','style'] as $key)$e[$key]=array_replace(get_object_vars($element->$key),get_object_vars($o[$key]??new \stdClass()));return $e;
    }
    public static function geometry(array $g,\stdClass $board,bool $nested=false,bool $autoScale=false): string {
        $ax=$g['anchorX'];$ay=$g['anchorY'];$grid=!$nested&&$g['container']==='grid';$scale=$autoScale&&($nested||$grid);
        $offset=self::length($g['x'],$board,$scale);
        if($grid&&$scale)$left=$ax==0.5?'calc(50% + '.$offset.')':'calc(50% '.($ax==0?'-':'+').' 50vw + '.$offset.')';
        elseif($grid)$left=$ax==0.5?'calc(50% + '.$offset.')':'calc(50% '.($ax==0?'-':'+').' min(50%, '.self::n($board->gridWidth/2).'px) + '.$offset.')';
        else $left='calc('.self::n($ax*100).'% + '.$offset.')';
        return 'position:absolute;box-sizing:border-box;left:'.$left.';top:calc('.self::n($ay*100).'% + '.self::length($g['y'],$board,$scale).');width:'.self::length($g['width'],$board,$scale).';height:'.self::length($g['height'],$board,$scale).';transform:translate('.self::n(-$g['originX']*100).'%,'.self::n(-$g['originY']*100).'%) rotate('.self::n($g['rotation']).'deg);transform-origin:'.self::n($g['originX']*100).'% '.self::n($g['originY']*100).'%;';
    }
    public static function appearance(array $s,?\stdClass $board=null,bool $autoScale=false): string {
        $map=['fontSize'=>'font-size','fontWeight'=>'font-weight','lineHeight'=>'line-height','letterSpacing'=>'letter-spacing','radius'=>'border-radius','opacity'=>'opacity','color'=>'color','fill'=>'background-color','fontFamily'=>'font-family','textAlign'=>'text-align','textDecoration'=>'text-decoration','objectFit'=>'object-fit','objectPosition'=>'object-position'];$css='';
        $lengths=['fontSize','letterSpacing','radius','strokeWidth','shadowX','shadowY','shadowBlur'];
        foreach($map as $key=>$property)if(isset($s[$key]))$css.=$property.':'.(in_array($key,$lengths,true)&&$autoScale&&$board?self::length($s[$key],$board,true):$s[$key].(in_array($key,['fontSize','letterSpacing','radius'],true)?'px':'')).';';
        $css.='border:'.($autoScale&&$board?self::length($s['strokeWidth']??0,$board,true):($s['strokeWidth']??0).'px').' solid '.($s['stroke']??'transparent').';';
        if(isset($s['shadowBlur']))$css.='box-shadow:'.($autoScale&&$board?self::length($s['shadowX']??0,$board,true):($s['shadowX']??0).'px').' '.($autoScale&&$board?self::length($s['shadowY']??0,$board,true):($s['shadowY']??0).'px').' '.($autoScale&&$board?self::length($s['shadowBlur'],$board,true):$s['shadowBlur'].'px').' '.($s['shadowColor']??'#000000').';';
        if($s['autoHeight']??false)$css.='height:auto;';return $css;
    }
    public static function render(\stdClass $data,string $blockId,?string $nonce=null): string {
        ZeroBlock::validate($data);
        $containsCode=static function(array $elements)use(&$containsCode):bool{foreach($elements as $element){if(($element->type??null)==='html')return true;if(($element->type??null)==='group'&&$containsCode($element->children??[]))return true;}return false;};
        if($containsCode($data->elements)&&$nonce===null)$nonce=bin2hex(random_bytes(16));
        $scope='jz-zero-'.substr(hash('sha256',$blockId),0,20);$css='';$html='';
        $boards=get_object_vars($data->artboards);uasort($boards,static fn($a,$b)=>$b->width<=>$a->width);$modes=array_keys($boards);
        $mediaMax=static function(string $mode)use($modes,$boards):?int{$index=array_search($mode,$modes,true);return $index===false||$index===0?null:$boards[$modes[$index-1]]->width-1;};
        $hasTooltip=false;$hasVector=false;
        $walk=function($elements,$nested=false,$inheritedGrid=[])use(&$walk,&$css,&$hasTooltip,&$hasVector,$data,$scope,$modes,$boards,$mediaMax,$nonce){$html='';foreach($elements as $index=>$el){$button=$el->type==='button';$tooltip=$el->type==='tooltip';$vector=$el->type==='vector';if($tooltip)$hasTooltip=true;if($vector)$hasVector=true;$selector=$button?'.'.$scope.' a.jz-zero-button[data-ze="'.$el->id.'"]':($tooltip?'.'.$scope.' .jz-zero-tooltip[data-ze="'.$el->id.'"]':'.'.$scope.' [data-ze="'.$el->id.'"]');$childGrid=[];foreach($modes as $mode){$e=self::resolved($el,$mode);$s=$e['style'];if($button)$s['textAlign']='center';$board=$data->artboards->$mode;$attached=$nested?($inheritedGrid[$mode]??false):($e['geometry']['container']==='grid');$autoScale=($board->scaleGrid??'fixed')==='autoscale'&&$attached;$childGrid[$mode]=$nested?($inheritedGrid[$mode]??false):($e['geometry']['container']==='grid');$body=self::geometry($e['geometry'],$board,$nested,$autoScale).self::appearance($s,$board,$autoScale);if($autoScale&&$button)$body.='padding:'.self::length(12,$board,true).' '.self::length(16,$board,true).';';$body.='z-index:'.($index+1).';display:'.($e['visible']?($button?'flex':($tooltip?'grid':($vector?'flex':'block'))):'none').';';if($tooltip)$body.='overflow:visible;';if($el->type==='ellipse')$body.='border-radius:50%;';if($el->type==='image'&&!isset($s['objectFit']))$body.='object-fit:cover;';if($el->type==='html')$body.='border:0;padding:0;overflow:hidden;';$rule=$selector.'{'.$body.'}';if(isset($s['hoverFill'])||isset($s['hoverColor']))$rule.=$selector.':hover{'.(isset($s['hoverFill'])?'background-color:'.$s['hoverFill'].';':'').(isset($s['hoverColor'])?'color:'.$s['hoverColor'].';':'').'}';if($mode!=='desktop')$rule='@media(max-width:'.$mediaMax($mode).'px){'.$rule.'}';$css.=$rule;}
            $attrs=' class="jz-zero-element'.($button?' jz-zero-button':'').($tooltip?' jz-zero-tooltip':'').($vector?' jz-zero-vector':'').'" data-ze="'.self::e($el->id).'"';$content=self::e($el->content??'');if(isset($el->runs)){$content='';foreach($el->runs as $run){$r=self::e($run->text);if($run->bold??false)$r='<strong>'.$r.'</strong>';if($run->italic??false)$r='<em>'.$r.'</em>';if($run->underline??false)$r='<u>'.$r.'</u>';$content.=$r;}}
            if($tooltip){$tipId='jz-tooltip-'.self::e($el->id);$html.='<div'.$attrs.'><button class="jz-zero-tooltip-trigger" type="button" aria-label="Показать подсказку" aria-describedby="'.$tipId.'">?</button><span class="jz-zero-tooltip-popover" id="'.$tipId.'" role="tooltip">'.$content.'</span></div>';}
            elseif($el->type==='group')$html.='<div'.$attrs.'>'.$walk($el->children??[],true,$childGrid).'</div>';
            elseif($el->type==='html'){$payload=CustomCode::validatePayload($el->code??null);$document=CustomCode::sandboxDocument($payload,$nonce);/* Keep sandbox present in both branches: an empty token set disables scripts. */$sandbox=$payload['js']===''?'sandbox=""':'sandbox="allow-scripts"';$html.='<iframe'.$attrs.' title="'.self::e($el->name??'HTML-элемент').'" '.$sandbox.' referrerpolicy="no-referrer" srcdoc="'.self::e($document).'"></iframe>';}
            elseif($vector)$html.='<div'.$attrs.' role="img" aria-label="'.self::e($el->name??'Вектор').'">'.self::vectorIcon($el->icon).'</div>';
            elseif($el->type==='image'){
                $sources='';foreach(array_reverse(array_slice($modes,1)) as $m){$source=self::resolved($el,$m)['src'];$sources.='<source media="(max-width:'.$mediaMax($m).'px)" srcset="'.self::e(str_replace(',','%2C',$source)).'">';}
                $tag=isset($el->href)?'a':'div';$link=$tag==='a'?' href="'.self::e($el->href).'" target="'.self::e($el->target??'_self').'" rel="noopener noreferrer"':'';
                $html.='<'.$tag.$attrs.' data-zero-image="true"'.$link.'><picture style="display:block;width:100%;height:100%;object-fit:inherit;object-position:inherit;border-radius:inherit">'.$sources.'<img style="width:100%;height:100%;object-fit:inherit;object-position:inherit;border-radius:inherit" src="'.self::e($el->src).'" alt="'.self::e($el->alt??'').'"></picture></'.$tag.'>';
            }
            else{$tag=$el->type==='text'?($el->tag??'p'):($el->type==='button'?'a':'div');$link=$tag==='a'?' href="'.self::e($el->href??'#').'" target="'.self::e($el->target??'_self').'" rel="noopener noreferrer"':'';$html.='<'.$tag.$attrs.$link.'>'.$content.'</'.$tag.'>';}
        }return $html;};$html=$walk($data->elements);
        $base='.'. $scope.'{position:relative!important;display:block!important;width:100%;box-sizing:border-box;isolation:isolate;}.' . $scope.' .jz-zero-element{margin:0;padding:0;white-space:pre-wrap;min-width:0;min-height:0;max-width:none;font-family:inherit;overflow-wrap:anywhere;}.' . $scope.' a.jz-zero-element{padding:12px 16px;text-align:center;text-decoration:none;}.' . $scope.' a.jz-zero-button{align-items:center;justify-content:center;text-align:center;}.' . $scope.' [data-zero-image]{padding:0;}.' . $scope.' iframe.jz-zero-code{display:block;border:0;padding:0;overflow:hidden;}';
        if($hasTooltip)$base.='.'. $scope.' .jz-zero-tooltip{white-space:normal;}.' . $scope.' .jz-zero-tooltip-trigger{display:flex;align-items:center;justify-content:center;box-sizing:border-box;width:28px;height:28px;padding:0;border:1px solid currentColor;border-radius:50%;background:transparent;color:inherit;font:inherit;line-height:1;cursor:help;}.' . $scope.' .jz-zero-tooltip-popover{position:absolute;left:50%;top:calc(100% + 8px);z-index:10000;width:max-content;max-width:280px;box-sizing:border-box;padding:8px 12px;border:0;border-radius:8px;background:#183e31;color:#fff;font:14px/1.4 system-ui,sans-serif;text-align:left;white-space:pre-wrap;overflow-wrap:anywhere;box-shadow:0 8px 24px rgba(0,0,0,.2);opacity:0;visibility:hidden;pointer-events:none;transform:translateX(-50%);transition:opacity .12s ease,visibility .12s ease;}.' . $scope.' .jz-zero-tooltip:hover .jz-zero-tooltip-popover,.' . $scope.' .jz-zero-tooltip:focus-within .jz-zero-tooltip-popover{opacity:1;visibility:visible;}';
        if($hasVector)$base.='.'. $scope.' .jz-zero-vector{align-items:center;justify-content:center;}.' . $scope.' .jz-zero-vector>svg{display:block;width:100%;height:100%;max-width:100%;max-height:100%;}';
        foreach($modes as $mode){$b=$data->artboards->$mode;$autoScale=($b->scaleGrid??'fixed')==='autoscale';$rule='.'. $scope.'{height:'.self::length($b->height,$b,$autoScale).';background:'.$b->background.';overflow:'.$b->overflow.';}';$base.=$mode==='desktop'?$rule:'@media(max-width:'.$mediaMax($mode).'px){'.$rule.'}';}
        return '<section class="jz-zero-block '.$scope.'" data-zero-id="'.self::e($blockId).'"><style>'.$base.$css.'</style>'.$html.'</section>';
    }
}
