<?php
declare(strict_types=1);
namespace Juzimder;

/** Public catalogue metadata only. Never fetch an owner/AI-provided URL. */
final class StockPhotos {
    public static function search(string $query): array {
        $query=trim($query);
        if($query===''||mb_strlen($query)>100||preg_match('/[\x00-\x1f<>]/u',$query))throw new Problem(422,'Тема фото должна содержать от 1 до 100 символов.');
        $photos=self::request($query);
        if($photos)return $photos;
        $fallback=self::broaderQuery($query);
        return $fallback!==$query?array_map(static fn($photo)=>[...$photo,'searchBroadened'=>true],self::request($fallback)):[];
    }
    /** Relax all-word matching only after an empty result; candidates still require owner review. */
    public static function broaderQuery(string $query): string {
        preg_match_all('/[\p{L}\p{N}]+/u',$query,$matches);
        $words=array_values(array_unique(array_filter($matches[0],static fn($word)=>mb_strlen($word)>2&&!in_array(mb_strtolower($word),['the','and','with','from','photo','image','photograph','close','shot','view','background'],true))));
        return count($words)>1?implode(' OR ',array_map(static fn($word)=>'"'.$word.'"',array_slice($words,0,8))):$query;
    }
    private static function request(string $query): array {
        $params=['action'=>'query','format'=>'json','formatversion'=>2,'generator'=>'search','gsrnamespace'=>6,'gsrlimit'=>6,'gsrsearch'=>$query,'prop'=>'imageinfo','iiprop'=>'url|mime|size|extmetadata','iiurlwidth'=>960,'iiextmetadatafilter'=>'Artist|LicenseShortName|LicenseUrl|ImageDescription','iiextmetadatalanguage'=>'en'];
        $curl=curl_init('https://commons.wikimedia.org/w/api.php?'.http_build_query($params));$body='';
        curl_setopt_array($curl,[CURLOPT_TIMEOUT=>10,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_USERAGENT=>'JuzimderAI/0.1 (https://github.com/DiasMazhenov/juzimder-ai)',CURLOPT_WRITEFUNCTION=>static function($handle,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>500000)return 0;$body.=$chunk;return strlen($chunk);}]);
        $ok=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
        if($ok===false||$status!==200)throw new Problem(503,'Поиск фото временно недоступен. Можно продолжить без изображения.');
        try{$data=json_decode($body,true,32,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new Problem(503,'Источник фото вернул неполный ответ.');}
        if(isset($data['error']))throw new Problem(503,'Источник фото не выполнил поиск.');
        return self::candidates($data);
    }
    public static function candidates(array $data): array {
        $photos=[];
        $pages=$data['query']['pages']??[];if(!is_array($pages))return [];
        foreach($pages as $page){
            if(!is_array($page))continue;
            $info=$page['imageinfo'][0]??[];if(!is_array($info))continue;$meta=$info['extmetadata']??[];if(!is_array($meta))continue;
            $url=$info['thumburl']??null;$title=$page['title']??null;
            if(!is_string($url)||!is_string($title)||!str_starts_with($title,'File:')||strlen($title)>300||!in_array($info['mime']??'',['image/jpeg','image/png','image/webp'],true))continue;
            $parts=parse_url($url);
            if(($parts['scheme']??'')!=='https'||!in_array($parts['host']??'',['upload.wikimedia.org','thumb.wikimedia.org'],true)||isset($parts['user'])||isset($parts['pass'])||isset($parts['port'])||preg_match('/[\x00-\x20<>"\\\\]/',$url))continue;
            $license=self::plain($meta['LicenseShortName']['value']??'');
            $licenseUrl=match($license){'CC0','CC0 1.0'=>'https://creativecommons.org/publicdomain/zero/1.0/','Public domain'=>'https://creativecommons.org/publicdomain/mark/1.0/',default=>null};
            if(preg_match('/^CC BY(-SA)? (2\.0|2\.5|3\.0|4\.0)$/D',$license,$m))$licenseUrl='https://creativecommons.org/licenses/'.($m[1]?'by-sa':'by').'/'.$m[2].'/';
            $author=self::plain($meta['Artist']['value']??'');$authorKey=mb_strtolower(preg_replace('/[\s\p{P}]+/u','',$author)??$author,'UTF-8');$unknownAuthor=preg_match('/^(?:(?:unknownauthor|unknown|anonymous|artistunknown|notprovided|notspecified))+$/D',$authorKey)===1;if(!$licenseUrl||$author===''||$unknownAuthor)continue;
            $photo=['id'=>'commons:'.(int)($page['pageid']??0),'kind'=>'commons','url'=>$url,'name'=>self::plain(substr($title,5)),'description'=>self::plain($meta['ImageDescription']['value']??''),'author'=>$author,'license'=>$license,'licenseUrl'=>$licenseUrl,'sourceUrl'=>'https://commons.wikimedia.org/wiki/'.rawurlencode(str_replace(' ','_',$title))];
            $width=$info['width']??null;$height=$info['height']??null;
            if(is_int($width)&&$width>0&&$width<=100000&&is_int($height)&&$height>0&&$height<=100000){$photo['width']=$width;$photo['height']=$height;}
            $photos[]=$photo;
        }
        return array_slice($photos,0,4);
    }
    private static function plain(mixed $value):string {
        if(!is_string($value))return '';
        $value=preg_replace('/<\/(?:li|p|div|br)\s*>/iu',' ',$value)??$value;
        return mb_substr(trim(preg_replace('/\s+/u',' ',html_entity_decode(strip_tags($value),ENT_QUOTES|ENT_HTML5,'UTF-8'))??''),0,180);
    }
}
