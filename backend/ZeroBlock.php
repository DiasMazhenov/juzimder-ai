<?php
declare(strict_types=1);
namespace Juzimder;
require_once __DIR__.'/CustomCode.php';

/** Canonical, versioned data belonging to one GrapesJS component. No executable markup. */
final class ZeroBlock {
    public const MODES = ['desktop'=>1200,'laptop'=>980,'tablet'=>640,'landscape'=>480,'mobile'=>360];
    public const VECTOR_ICONS = ['arrow-left','arrow-right','arrow-up','arrow-down','chevron-right','plus','minus','x','link','search','photo','eye','settings','download','copy','square','circle','menu','trash','lock','lock-open'];
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
    public static function videoEmbedUrl(mixed $value): ?string {
        if(!is_string($value)||$value===''||strlen($value)>2048||preg_match('/[\s<>"\x00-\x20]/',$value))return null;
        $parts=parse_url($value);
        if(!is_array($parts)||strtolower($parts['scheme']??'')!=='https'||isset($parts['user'])||isset($parts['pass'])||isset($parts['port'])||isset($parts['fragment']))return null;
        $host=strtolower($parts['host']??'');$path=$parts['path']??'';$query=$parts['query']??'';
        if(in_array($host,['youtube.com','www.youtube.com','youtube-nocookie.com','www.youtube-nocookie.com'],true)){
            if(preg_match('~^/(?:embed|shorts|live)/([A-Za-z0-9_-]{11})/?$~D',$path,$m))return 'https://www.youtube-nocookie.com/embed/'.$m[1];
            if($path==='/watch'&&preg_match_all('~(?:^|&)v=([A-Za-z0-9_-]{11})(?:&|$)~',$query,$matches)===1)return 'https://www.youtube-nocookie.com/embed/'.$matches[1][0];
            return null;
        }
        if($host==='youtu.be'&&preg_match('~^/([A-Za-z0-9_-]{11})/?$~D',$path,$m))return 'https://www.youtube-nocookie.com/embed/'.$m[1];
        if(in_array($host,['vimeo.com','www.vimeo.com'],true)&&preg_match('~^/([0-9]{1,12})/?$~D',$path,$m))return 'https://player.vimeo.com/video/'.$m[1];
        if($host==='player.vimeo.com'&&preg_match('~^/video/([0-9]{1,12})/?$~D',$path,$m))return 'https://player.vimeo.com/video/'.$m[1];
        return null;
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
        if(array_key_exists('videoUrl',$fields)&&(!is_string($fields['videoUrl'])||strlen($fields['videoUrl'])>2048||($fields['videoUrl']!==''&&self::videoEmbedUrl($fields['videoUrl'])===null)))throw new Problem(422,'Zero Block: разрешены только ссылки YouTube и Vimeo.');
        if(isset($fields['target'])&&!in_array($fields['target'],['_self','_blank'],true))throw new Problem(422,'Zero Block: неверный target.');
        if(isset($fields['runs'])){
            if(!is_array($fields['runs'])||count($fields['runs'])>200)throw new Problem(422,'Zero Block: слишком много фрагментов текста.');
            foreach($fields['runs'] as $run){$r=self::fields($run,['text','bold','italic','underline'],['text']);self::text($r['text']);foreach(['bold','italic','underline'] as $key)if(isset($r[$key])&&!is_bool($r[$key]))throw new Problem(422,'Zero Block: неверное форматирование.');}
        }
        if(array_key_exists('animation',$fields)){
            $animation=self::fields($fields['animation'],['effect','duration','delay','easing'],['effect','duration','delay','easing']);
            if(!in_array($animation['effect'],['fade','move','scale'],true)||!in_array($animation['easing'],['linear','ease','ease-in','ease-out','ease-in-out'],true))throw new Problem(422,'Zero Block: недопустимая анимация.');
            if(!is_int($animation['duration'])||$animation['duration']<100||$animation['duration']>3000||!is_int($animation['delay'])||$animation['delay']<0||$animation['delay']>5000)throw new Problem(422,'Zero Block: неверная длительность или задержка анимации.');
        }
    }
    private static function formFields(mixed $fields,mixed $submitLabel): void {
        if(!is_string($submitLabel)||trim($submitLabel)==='')throw new Problem(422,'Zero Block: у макета формы должна быть подпись кнопки.');
        self::text($submitLabel,60);
        if(!is_array($fields)||!array_is_list($fields)||count($fields)<1||count($fields)>8)throw new Problem(422,'Zero Block: макет формы должен содержать от 1 до 8 полей.');
        $ids=[];
        foreach($fields as $field){
            $f=self::fields($field,['id','type','label','placeholder','required'],['id','type','label','placeholder','required']);
            if(!is_string($f['id'])||!preg_match('/^[a-z][a-z0-9_-]{0,31}$/D',$f['id'])||isset($ids[$f['id']]))throw new Problem(422,'Zero Block: ID полей формы должны быть уникальными.');
            $ids[$f['id']]=true;
            if(!in_array($f['type'],['text','email','tel','textarea'],true))throw new Problem(422,'Zero Block: неподдерживаемый тип поля формы.');
            self::text($f['label'],80);self::text($f['placeholder'],120);
            if(trim($f['label'])===''||!is_bool($f['required']))throw new Problem(422,'Zero Block: у поля формы должны быть подпись и флаг required.');
        }
    }
    private static function galleryData(mixed $items,mixed $options): void {
        if(!is_array($items)||!array_is_list($items)||count($items)>12)throw new Problem(422,'Zero Block: галерея может содержать не более 12 фото.');
        $ids=[];
        foreach($items as $item){
            $image=self::fields($item,['id','src','alt'],['id','src','alt']);
            if(!is_string($image['id'])||!preg_match('/^[a-z][a-z0-9_-]{0,31}$/D',$image['id'])||isset($ids[$image['id']]))throw new Problem(422,'Zero Block: ID фото в галерее должны быть уникальны.');
            $ids[$image['id']]=true;
            if(!is_string($image['src'])||$image['src']==='')throw new Problem(422,'Zero Block: фото галереи требует источник.');
            self::text($image['alt'],300);
            self::properties($image);
        }
        $layout=self::fields($options,['columns','gap','objectFit'],['columns','gap','objectFit']);
        if(!is_int($layout['columns'])||$layout['columns']<1||$layout['columns']>6||!is_int($layout['gap'])||$layout['gap']<0||$layout['gap']>80||!in_array($layout['objectFit'],['cover','contain'],true))throw new Problem(422,'Zero Block: недопустимые настройки галереи.');
    }
    public static function validate(mixed $data): void {
        $fields=self::fields($data,['version','artboards','elements'],['version','artboards','elements']);
        if($fields['version']!==1)throw new Problem(422,'Zero Block: неподдерживаемая версия.');
        try { $json=json_encode($data,JSON_THROW_ON_ERROR); } catch (\JsonException) { throw new Problem(422,'Zero Block: данные должны быть конечными JSON-значениями.'); }
        if(strlen($json)>262144)throw new Problem(422,'Zero Block: размер больше 256 КБ.');
        if(!$fields['artboards'] instanceof \stdClass)throw new Problem(422,'Zero Block: ожидается список брейкпоинтов.');
        $boards=get_object_vars($fields['artboards']);
        if(count($boards)<1||count($boards)>10||!isset($boards['desktop']))throw new Problem(422,'Zero Block: нужен базовый брейкпоинт и не более 10 режимов.');
        foreach($boards as $mode=>$board)if(!preg_match('/^(?:desktop|laptop|tablet|landscape|mobile|bp-[a-f0-9-]{8,36})$/D',$mode)||!($board instanceof \stdClass)||!isset($board->width)||!is_int($board->width))throw new Problem(422,'Zero Block: неверный ID или ширина брейкпоинта.');
        $ordered=$boards;uasort($ordered,static fn($a,$b)=>($b->width??0)<=>($a->width??0));
        if(array_key_first($ordered)!=='desktop')throw new Problem(422,'Zero Block: Desktop должен быть самым широким брейкпоинтом.');
        $widths=[];$overrideModes=[];
        foreach($ordered as $mode=>$board){
            if(!preg_match('/^(?:desktop|laptop|tablet|landscape|mobile|bp-[a-f0-9-]{8,36})$/D',$mode))throw new Problem(422,'Zero Block: неверный ID брейкпоинта.');
            $b=self::fields($board,['width','height','background','backgroundOpacity','backgroundImage','backgroundImageName','backgroundSize','backgroundPosition','filterStartColor','filterStartOpacity','filterEndColor','filterEndOpacity','gridWidth','margin','gutter','overflow','scaleGrid','guides'],['width','height','background','gridWidth','margin','gutter','overflow','guides']);
            if(!is_int($b['width'])||$b['width']<320||$b['width']>2400||isset($widths[$b['width']]))throw new Problem(422,'Zero Block: ширины брейкпоинтов должны быть уникальны в диапазоне 320–2400 px.');
            $widths[$b['width']]=true;if($mode!=='desktop')$overrideModes[]=$mode;
            self::number($b['height'],40,20000);self::number($b['gridWidth'],1,2400);self::number($b['margin'],0,500);self::number($b['gutter'],0,200);self::color($b['background']);
            if(array_key_exists('backgroundOpacity',$b))self::number($b['backgroundOpacity'],0,1);
            if(array_key_exists('backgroundImage',$b)&&$b['backgroundImage']!==''&&(!is_string($b['backgroundImage'])||!preg_match('~^/api/projects/[a-f0-9]{24}/assets/[a-f0-9]{24}/file$~D',$b['backgroundImage'])))throw new Problem(422,'Zero Block: выберите изображение из библиотеки текущего проекта.');
            if(array_key_exists('backgroundImageName',$b))self::text($b['backgroundImageName'],200);
            if(array_key_exists('backgroundSize',$b)&&!in_array($b['backgroundSize'],['cover','contain','auto','100% 100%'],true))throw new Problem(422,'Zero Block: неверное поведение фонового изображения.');
            if(array_key_exists('backgroundPosition',$b)&&!in_array($b['backgroundPosition'],['center center','left top','center top','right top','left center','right center','left bottom','center bottom','right bottom'],true))throw new Problem(422,'Zero Block: неверная позиция фонового изображения.');
            foreach(['filterStartColor','filterEndColor'] as $key)if(array_key_exists($key,$b)&&(!is_string($b[$key])||!preg_match('/^#[a-f0-9]{6}$/iD',$b[$key])))throw new Problem(422,'Zero Block: фильтру нужен шестизначный HEX-цвет.');
            foreach(['filterStartOpacity','filterEndOpacity'] as $key)if(array_key_exists($key,$b))self::number($b[$key],0,1);
            if(!in_array($b['overflow'],['hidden','visible'],true))throw new Problem(422,'Zero Block: неверный overflow.');
            if(array_key_exists('scaleGrid',$b)&&!in_array($b['scaleGrid'],['fixed','autoscale'],true))throw new Problem(422,'Zero Block: неверный масштаб Grid Container.');
            if(!is_array($b['guides'])||count($b['guides'])>100)throw new Problem(422,'Zero Block: неверные направляющие.');
            foreach($b['guides'] as $guide){$g=self::fields($guide,['axis','position'],['axis','position']);if(!in_array($g['axis'],['x','y'],true))throw new Problem(422,'Zero Block: неверная ось.');self::number($g['position'],-20000,20000);}
        }
        $ids=[];$count=0;
        $walk=function($elements,$depth)use(&$walk,&$ids,&$count,$overrideModes){
            if(!is_array($elements)||!array_is_list($elements)||$depth>8)throw new Problem(422,'Zero Block: неверная вложенность.');
            foreach($elements as $element){if(++$count>300)throw new Problem(422,'Zero Block: не более 300 элементов.');$e=self::fields($element,['id','type','name','geometry','style','overrides','children','content','runs','src','alt','href','target','tag','visible','locked','keepAspect','code','icon','videoUrl','formFields','submitLabel','galleryItems','galleryOptions','animation'],['id','type','name','geometry','style','overrides','visible','locked']);if(!is_string($e['id'])||!preg_match('/^ze-[a-zA-Z0-9_-]{1,80}$/D',$e['id'])||isset($ids[$e['id']]))throw new Problem(422,'Zero Block: ID элементов должны быть уникальны.');$ids[$e['id']]=true;if(!in_array($e['type'],['text','image','rectangle','ellipse','line','button','tooltip','group','html','vector','video','form','gallery'],true))throw new Problem(422,'Zero Block: неизвестный тип элемента.');if($e['type']==='vector'){if(!isset($e['icon'])||!in_array($e['icon'],self::VECTOR_ICONS,true))throw new Problem(422,'Zero Block: выберите разрешённую Tabler-иконку.');}elseif(isset($e['icon']))throw new Problem(422,'Zero Block: иконка допустима только у Vector-элемента.');if($e['type']==='video'){if(!array_key_exists('videoUrl',$e))throw new Problem(422,'Zero Block: у видео должно быть поле videoUrl.');}elseif(isset($e['videoUrl']))throw new Problem(422,'Zero Block: videoUrl допустим только у Video-элемента.');if($e['type']==='form'){self::formFields($e['formFields']??null,$e['submitLabel']??null);}elseif(array_key_exists('formFields',$e)||array_key_exists('submitLabel',$e))throw new Problem(422,'Zero Block: поля формы допустимы только у Form-элемента.');if($e['type']==='gallery'){if(array_intersect(array_keys($e),['children','content','runs','src','alt','href','target','tag','code','icon','videoUrl','formFields','submitLabel']))throw new Problem(422,'Zero Block: галерея содержит несовместимые поля.');self::galleryData($e['galleryItems']??null,$e['galleryOptions']??null);}elseif(array_key_exists('galleryItems',$e)||array_key_exists('galleryOptions',$e))throw new Problem(422,'Zero Block: параметры галереи допустимы только у Gallery-элемента.');self::text($e['name'],120);self::geometry($e['geometry']);self::properties($e);if($e['type']==='tooltip'){if(!isset($e['content']))throw new Problem(422,'Zero Block: подсказка требует текст.');self::text($e['content'],2000);}foreach(self::fields($e['overrides'],$overrideModes) as $override){$o=self::fields($override,['geometry','style','visible','src']);self::properties($o);}if($e['type']==='group')$walk($e['children']??[], $depth+1);elseif(isset($e['children']))throw new Problem(422,'Zero Block: дети допустимы только у группы.');if($e['type']==='image'&&!isset($e['src']))throw new Problem(422,'Zero Block: изображение требует src.');if($e['type']==='html')CustomCode::validatePayload($e['code']??null);elseif(isset($e['code']))throw new Problem(422,'Zero Block: код допустим только у HTML-элемента.');}
        };$walk($fields['elements'],0);
    }
    public static function updated(mixed $document,string $pageId,string $blockId,mixed $zeroData): \stdClass {
        self::validate($zeroData);
        $copy=json_decode(json_encode($document,JSON_THROW_ON_ERROR),false,64,JSON_THROW_ON_ERROR);$found=0;
        foreach($copy->data->pages as $page)if($page->id===$pageId){$walk=function($node)use(&$walk,$blockId,$zeroData,&$found){if(($node->attributes->id??null)===$blockId){if(($node->type??'')!=='zero-block')throw new Problem(422,'Компонент не является Zero Block.');$node->zeroData=$zeroData;$found++;}foreach($node->components??[] as $child)$walk($child);};$walk($page->frames[0]->component);}
        if($found!==1)throw new Problem($found?409:404,'Zero Block не найден однозначно.');return $copy;
    }
}
