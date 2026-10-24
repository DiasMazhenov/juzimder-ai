<?php
declare(strict_types=1);
namespace Juzimder;

/** Canonical, versioned data belonging to one GrapesJS component. No executable markup. */
final class ZeroBlock {
    public const MODES = ['desktop'=>1200,'laptop'=>980,'tablet'=>640,'landscape'=>480,'mobile'=>360];
    public const STYLE_RANGES = ['fontSize'=>[1,500],'fontWeight'=>[100,900],'lineHeight'=>[0.5,5],'letterSpacing'=>[-30,100],'radius'=>[0,10000],'strokeWidth'=>[0,100],'opacity'=>[0,1],'shadowBlur'=>[0,200],'shadowX'=>[-200,200],'shadowY'=>[-200,200]];
    public static function fields(mixed $value, array $allowed, array $required=[]): array {
        if (!$value instanceof \stdClass) throw new Problem(422,'Zero Block: ожидается объект.');
        $fields=get_object_vars($value);
        if (array_diff(array_keys($fields),$allowed)||array_diff($required,array_keys($fields))) throw new Problem(422,'Zero Block: неизвестные или отсутствующие поля.');
        return $fields;
    }
    public static function number(mixed $n,float $min,float $max): void {
        if ((!is_int($n)&&!is_float($n))||!is_finite((float)$n)||$n<$min||$n>$max) throw new Problem(422,'Zero Block: число вне допустимого диапазона.');
    }
    private static function text(mixed $s,int $max=10000): void {
        if (!is_string($s)||mb_strlen($s,'UTF-8')>$max||preg_match('/[<>\x00-\x08\x0b\x0c\x0e-\x1f]/u',$s)) throw new Problem(422,'Zero Block: недопустимый текст.');
    }
    public static function color(mixed $s): void {
        if (!is_string($s)||!preg_match('/^(?:#[a-f0-9]{6}|transparent)$/iD',$s)) throw new Problem(422,'Zero Block: недопустимый цвет.');
    }
    private static function geometry(mixed $value,bool $partial=false): void {
        $keys=['x','y','width','height','rotation','originX','originY','anchorX','anchorY','container'];
        foreach(self::fields($value,$keys,$partial?[]:$keys) as $key=>$v){
            if($key==='container'){if(!in_array($v,['grid','window'],true))throw new Problem(422,'Zero Block: неизвестный контейнер.');}
            elseif(in_array($key,['originX','originY','anchorX','anchorY'],true)){if(!in_array($v,[0,0.5,1],true))throw new Problem(422,'Zero Block: неверная привязка.');}
            else self::number($v,in_array($key,['width','height'],true)?1:-20000,20000);
        }
    }
    private static function style(mixed $value): void {
        foreach(self::fields($value,array_merge(array_keys(self::STYLE_RANGES),['color','fill','stroke','shadowColor','fontFamily','textAlign','textDecoration','objectFit','objectPosition','autoHeight','hoverFill','hoverColor'])) as $key=>$v){
            if(isset(self::STYLE_RANGES[$key]))self::number($v,...self::STYLE_RANGES[$key]);
            elseif(in_array($key,['color','fill','stroke','shadowColor','hoverFill','hoverColor'],true))self::color($v);
            elseif($key==='fontFamily'){if(!is_string($v)||strlen($v)>160||!preg_match('/^[a-zA-Z0-9 ,\x27-]+$/D',$v))throw new Problem(422,'Zero Block: недопустимый шрифт.');}
            elseif($key==='autoHeight'){if(!is_bool($v))throw new Problem(422,'Zero Block: неверный autoHeight.');}
            else { $values=match($key){'textAlign'=>['left','center','right','justify'],'textDecoration'=>['none','underline','line-through'],'objectFit'=>['cover','contain','fill','none'],'objectPosition'=>['center','top','bottom','left','right']};if(!in_array($v,$values,true))throw new Problem(422,'Zero Block: недопустимый стиль.'); }
        }
    }
    private static function properties(array $fields): void {
        if(isset($fields['geometry']))self::geometry($fields['geometry'],true);
        if(isset($fields['style']))self::style($fields['style']);
        foreach(['visible','locked','keepAspect'] as $key)if(array_key_exists($key,$fields)&&!is_bool($fields[$key]))throw new Problem(422,'Zero Block: неверный флаг.');
        foreach(['content','alt'] as $key)if(isset($fields[$key]))self::text($fields[$key]);
        if(isset($fields['tag'])&&!in_array($fields['tag'],['p','h1','h2','h3','h4','h5','h6'],true))throw new Problem(422,'Zero Block: недопустимый заголовок.');
        foreach(['src','href'] as $key)if(isset($fields[$key])){
            $url=$fields[$key];
            $asset=$key==='src'&&is_string($url)&&preg_match('~^/api/projects/[a-f0-9]{24}/assets/[a-f0-9]{24}/file$~D',$url);
            $https=is_string($url)&&filter_var($url,FILTER_VALIDATE_URL)&&str_starts_with($url,'https://')&&!parse_url($url,PHP_URL_USER)&&!parse_url($url,PHP_URL_PASS)&&!preg_match('/[\s<>"\x00-\x20]/',$url);
            $link=$key==='href'&&is_string($url)&&preg_match('~^(?:#[a-zA-Z0-9_-]*|mailto:[a-zA-Z0-9@._+-]+|tel:[+0-9 -]+)$~D',$url);
            if((!$asset&&!$https&&!$link)||strlen((string)$url)>2048)throw new Problem(422,'Zero Block: недопустимый адрес.');
        }
        if(isset($fields['target'])&&!in_array($fields['target'],['_self','_blank'],true))throw new Problem(422,'Zero Block: неверный target.');
        if(isset($fields['runs'])){
            if(!is_array($fields['runs'])||count($fields['runs'])>200)throw new Problem(422,'Zero Block: слишком много фрагментов текста.');
            foreach($fields['runs'] as $run){$r=self::fields($run,['text','bold','italic','underline'],['text']);self::text($r['text']);foreach(['bold','italic','underline'] as $key)if(isset($r[$key])&&!is_bool($r[$key]))throw new Problem(422,'Zero Block: неверное форматирование.');}
        }
    }
    public static function validate(mixed $data): void {
        $fields=self::fields($data,['version','artboards','elements'],['version','artboards','elements']);
        if($fields['version']!==1)throw new Problem(422,'Zero Block: неподдерживаемая версия.');
        try { $json=json_encode($data,JSON_THROW_ON_ERROR); } catch (\JsonException) { throw new Problem(422,'Zero Block: данные должны быть конечными JSON-значениями.'); }
        if(strlen($json)>262144)throw new Problem(422,'Zero Block: размер больше 256 КБ.');
        $boards=self::fields($fields['artboards'],array_keys(self::MODES),array_keys(self::MODES));
        foreach($boards as $mode=>$board){$b=self::fields($board,['width','height','background','gridWidth','margin','gutter','overflow','guides'],['width','height','background','gridWidth','margin','gutter','overflow','guides']);if($b['width']!==self::MODES[$mode])throw new Problem(422,'Zero Block: неверная опорная ширина.');self::number($b['height'],40,20000);self::number($b['gridWidth'],1,2400);self::number($b['margin'],0,500);self::number($b['gutter'],0,200);self::color($b['background']);if(!in_array($b['overflow'],['hidden','visible'],true))throw new Problem(422,'Zero Block: неверный overflow.');if(!is_array($b['guides'])||count($b['guides'])>100)throw new Problem(422,'Zero Block: неверные направляющие.');foreach($b['guides'] as $guide){$g=self::fields($guide,['axis','position'],['axis','position']);if(!in_array($g['axis'],['x','y'],true))throw new Problem(422,'Zero Block: неверная ось.');self::number($g['position'],-20000,20000);}}
        $ids=[];$count=0;
        $walk=function($elements,$depth)use(&$walk,&$ids,&$count){
            if(!is_array($elements)||!array_is_list($elements)||$depth>8)throw new Problem(422,'Zero Block: неверная вложенность.');
            foreach($elements as $element){if(++$count>300)throw new Problem(422,'Zero Block: не более 300 элементов.');$e=self::fields($element,['id','type','name','geometry','style','overrides','children','content','runs','src','alt','href','target','tag','visible','locked','keepAspect'],['id','type','name','geometry','style','overrides','visible','locked']);if(!is_string($e['id'])||!preg_match('/^ze-[a-zA-Z0-9_-]{1,80}$/D',$e['id'])||isset($ids[$e['id']]))throw new Problem(422,'Zero Block: ID элементов должны быть уникальны.');$ids[$e['id']]=true;if(!in_array($e['type'],['text','image','rectangle','ellipse','line','button','group'],true))throw new Problem(422,'Zero Block: неизвестный тип элемента.');self::text($e['name'],120);self::geometry($e['geometry']);self::properties($e);foreach(self::fields($e['overrides'],array_slice(array_keys(self::MODES),1)) as $override){$o=self::fields($override,['geometry','style','visible','src']);self::properties($o);}if($e['type']==='group')$walk($e['children']??[], $depth+1);elseif(isset($e['children']))throw new Problem(422,'Zero Block: дети допустимы только у группы.');if($e['type']==='image'&&!isset($e['src']))throw new Problem(422,'Zero Block: изображение требует src.');}
        };$walk($fields['elements'],0);
    }
    public static function updated(mixed $document,string $pageId,string $blockId,mixed $zeroData): \stdClass {
        self::validate($zeroData);
        $copy=json_decode(json_encode($document,JSON_THROW_ON_ERROR),false,64,JSON_THROW_ON_ERROR);$found=0;
        foreach($copy->data->pages as $page)if($page->id===$pageId){$walk=function($node)use(&$walk,$blockId,$zeroData,&$found){if(($node->attributes->id??null)===$blockId){if(($node->type??'')!=='zero-block')throw new Problem(422,'Компонент не является Zero Block.');$node->zeroData=$zeroData;$found++;}foreach($node->components??[] as $child)$walk($child);};$walk($page->frames[0]->component);}
        if($found!==1)throw new Problem($found?409:404,'Zero Block не найден однозначно.');return $copy;
    }
}
