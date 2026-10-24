<?php
declare(strict_types=1);
namespace Juzimder;
require_once __DIR__.'/ZeroBlock.php';
final class ZeroBlockRenderer {
    private static function e(string $s): string{return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5,'UTF-8');}
    private static function n(int|float $v): string{return (string)(round($v,4)+0);}
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
    public static function render(\stdClass $data,string $blockId): string {
        ZeroBlock::validate($data);$scope='jz-zero-'.substr(hash('sha256',$blockId),0,20);$css='';$html='';
        $walk=function($elements,$nested=false,$inheritedGrid=[])use(&$walk,&$css,$data,$scope){$html='';foreach($elements as $index=>$el){$selector='.'. $scope.' [data-ze="'.$el->id.'"]';$childGrid=[];foreach(ZeroBlock::MODES as $mode=>$width){$e=self::resolved($el,$mode);$s=$e['style'];$board=$data->artboards->$mode;$attached=$nested?($inheritedGrid[$mode]??false):($e['geometry']['container']==='grid');$autoScale=($board->scaleGrid??'fixed')==='autoscale'&&$attached;$childGrid[$mode]=$nested?($inheritedGrid[$mode]??false):($e['geometry']['container']==='grid');$body=self::geometry($e['geometry'],$board,$nested,$autoScale).self::appearance($s,$board,$autoScale);if($autoScale&&$el->type==='button')$body.='padding:'.self::length(12,$board,true).' '.self::length(16,$board,true).';';$body.='z-index:'.($index+1).';display:'.($e['visible']?'block':'none').';';if($el->type==='ellipse')$body.='border-radius:50%;';if($el->type==='image'&&!isset($s['objectFit']))$body.='object-fit:cover;';$rule=$selector.'{'.$body.'}';if(isset($s['hoverFill'])||isset($s['hoverColor']))$rule.=$selector.':hover{'.(isset($s['hoverFill'])?'background-color:'.$s['hoverFill'].';':'').(isset($s['hoverColor'])?'color:'.$s['hoverColor'].';':'').'}';if($mode!=='desktop')$rule='@media(max-width:'.match($mode){'laptop'=>1199,'tablet'=>979,'landscape'=>639,'mobile'=>479}.'px){'.$rule.'}';$css.=$rule;}
            $attrs=' class="jz-zero-element" data-ze="'.self::e($el->id).'"';$content=self::e($el->content??'');if(isset($el->runs)){$content='';foreach($el->runs as $run){$r=self::e($run->text);if($run->bold??false)$r='<strong>'.$r.'</strong>';if($run->italic??false)$r='<em>'.$r.'</em>';if($run->underline??false)$r='<u>'.$r.'</u>';$content.=$r;}}
            if($el->type==='group')$html.='<div'.$attrs.'>'.$walk($el->children??[],true,$childGrid).'</div>';
            elseif($el->type==='image'){
                $sources='';foreach(['mobile'=>479,'landscape'=>639,'tablet'=>979,'laptop'=>1199] as $m=>$max){$source=self::resolved($el,$m)['src'];$sources.='<source media="(max-width:'.$max.'px)" srcset="'.self::e(str_replace(',','%2C',$source)).'">';}
                $tag=isset($el->href)?'a':'div';$link=$tag==='a'?' href="'.self::e($el->href).'" target="'.self::e($el->target??'_self').'" rel="noopener noreferrer"':'';
                $html.='<'.$tag.$attrs.' data-zero-image="true"'.$link.'><picture style="display:block;width:100%;height:100%;object-fit:inherit;object-position:inherit;border-radius:inherit">'.$sources.'<img style="width:100%;height:100%;object-fit:inherit;object-position:inherit;border-radius:inherit" src="'.self::e($el->src).'" alt="'.self::e($el->alt??'').'"></picture></'.$tag.'>';
            }
            else{$tag=$el->type==='text'?($el->tag??'p'):($el->type==='button'?'a':'div');$link=$tag==='a'?' href="'.self::e($el->href??'#').'" target="'.self::e($el->target??'_self').'" rel="noopener noreferrer"':'';$html.='<'.$tag.$attrs.$link.'>'.$content.'</'.$tag.'>';}
        }return $html;};$html=$walk($data->elements);
        $base='.'. $scope.'{position:relative!important;display:block!important;width:100%;box-sizing:border-box;isolation:isolate;}.' . $scope.' .jz-zero-element{margin:0;padding:0;white-space:pre-wrap;min-width:0;min-height:0;max-width:none;font-family:inherit;overflow-wrap:anywhere;}.' . $scope.' a.jz-zero-element{padding:12px 16px;text-align:center;text-decoration:none;}.' . $scope.' [data-zero-image]{padding:0;}';
        foreach(ZeroBlock::MODES as $mode=>$width){$b=$data->artboards->$mode;$autoScale=($b->scaleGrid??'fixed')==='autoscale';$rule='.'. $scope.'{height:'.self::length($b->height,$b,$autoScale).';background:'.$b->background.';overflow:'.$b->overflow.';}';$base.=$mode==='desktop'?$rule:'@media(max-width:'.match($mode){'laptop'=>1199,'tablet'=>979,'landscape'=>639,'mobile'=>479}.'px){'.$rule.'}';}
        return '<section class="jz-zero-block '.$scope.'" data-zero-id="'.self::e($blockId).'"><style>'.$base.$css.'</style>'.$html.'</section>';
    }
}
