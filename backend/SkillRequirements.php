<?php
declare(strict_types=1);
namespace Juzimder;

/** Declarative checks only. No selectors, regexes, scripts or model claims are executed. */
final class SkillRequirements {
    public static function parse(string $content): array {
        $markers=preg_match_all('/^```juzimder-rules\b/m',$content);
        if(!$markers)return [];
        if($markers!==1||!preg_match('/^```juzimder-rules\s*\n(.*?)\n```\s*$/ms',$content,$match))throw new \InvalidArgumentException('Добавьте один завершённый блок juzimder-rules.');
        try{$contract=json_decode($match[1],true,16,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new \InvalidArgumentException('Блок juzimder-rules должен содержать корректный JSON.');}
        if(!is_array($contract)||array_diff(array_keys($contract),['schemaVersion','requirements'])||($contract['schemaVersion']??null)!==1||!is_array($contract['requirements']??null)||!array_is_list($contract['requirements'])||count($contract['requirements'])<1||count($contract['requirements'])>20)throw new \InvalidArgumentException('Контракт Skill: schemaVersion 1 и от 1 до 20 requirements.');
        $ids=[];$headingCounts=[];$textRules=[];$sectionMin=3;$sectionMax=9;
        foreach($contract['requirements'] as $rule){
            if(!is_array($rule)||!is_string($rule['id']??null)||!preg_match('/^[a-z][a-z0-9-]{0,39}$/D',$rule['id'])||isset($ids[$rule['id']])||!is_string($rule['label']??null)||trim($rule['label'])===''||mb_strlen($rule['label'])>160)throw new \InvalidArgumentException('Каждому требованию нужны уникальный id и название до 160 символов.');
            $ids[$rule['id']]=true;$type=$rule['type']??null;
            $fields=match($type){'heading_count'=>['tag','count'],'section_count'=>['min','max'],'text_present','text_absent'=>['text'],'responsive_css'=>[],default=>throw new \InvalidArgumentException('Неизвестный тип автоматической проверки Skill.')};
            if(array_diff(array_keys($rule),['id','label','type',...$fields])||array_diff($fields,array_keys($rule)))throw new \InvalidArgumentException('Недопустимые поля автоматической проверки Skill.');
            if($type==='heading_count'&&(!in_array($rule['tag'],['h1','h2','h3','h4','h5','h6'],true)||!is_int($rule['count'])||$rule['count']<0||$rule['count']>50))throw new \InvalidArgumentException('heading_count: tag h1–h6 и count от 0 до 50.');
            if($type==='section_count'&&(!is_int($rule['min'])||!is_int($rule['max'])||$rule['min']<3||$rule['max']>9||$rule['min']>$rule['max']))throw new \InvalidArgumentException('section_count: диапазон от 3 до 9 секций.');
            if(in_array($type,['text_present','text_absent'],true)&&(!is_string($rule['text'])||trim($rule['text'])===''||mb_strlen($rule['text'])>200))throw new \InvalidArgumentException('Текст проверки должен содержать от 1 до 200 символов.');
            if($type==='heading_count'){
                if(isset($headingCounts[$rule['tag']])&&$headingCounts[$rule['tag']]!==$rule['count'])throw new \InvalidArgumentException('Противоречащие требования количества заголовков.');
                $headingCounts[$rule['tag']]=$rule['count'];
            }
            if($type==='section_count'){$sectionMin=max($sectionMin,$rule['min']);$sectionMax=min($sectionMax,$rule['max']);if($sectionMin>$sectionMax)throw new \InvalidArgumentException('Диапазоны количества секций не пересекаются.');}
            if(in_array($type,['text_present','text_absent'],true)){
                $key=mb_strtolower(trim(preg_replace('/\s+/u',' ',$rule['text'])),'UTF-8');
                if(isset($textRules[$key])&&$textRules[$key]!==$type)throw new \InvalidArgumentException('Один текст одновременно требуется и запрещается.');
                $textRules[$key]=$type;
            }
        }
        return $contract['requirements'];
    }

    public static function audit(array $design,array $rules): array {
        if(!$rules)return [];
        $dom=new \DOMDocument('1.0','UTF-8');$previous=libxml_use_internal_errors(true);
        try{$loaded=$dom->loadHTML('<!doctype html><html><head><meta charset="UTF-8"></head><body>'.$design['html'].'</body></html>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);}finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
        if(!$loaded)throw new \RuntimeException('Cannot inspect sanitized design.');
        $normalize=static fn(string $text): string=>mb_strtolower(trim(preg_replace('/\s+/u',' ',$text)),'UTF-8');
        $text=$normalize($dom->getElementsByTagName('body')->item(0)?->textContent??'');$checks=[];
        foreach($rules as $rule){
            switch($rule['type']){
                case 'heading_count':$actual=$dom->getElementsByTagName($rule['tag'])->length;$passed=$actual===$rule['count'];$evidence=['tag'=>$rule['tag'],'actual'=>$actual,'expected'=>$rule['count']];break;
                case 'section_count':$actual=$dom->getElementsByTagName('section')->length;$passed=$actual>=$rule['min']&&$actual<=$rule['max'];$evidence=['tag'=>'section','actual'=>$actual,'min'=>$rule['min'],'max'=>$rule['max']];break;
                case 'text_present':case 'text_absent':$found=str_contains($text,$normalize($rule['text']));$passed=$rule['type']==='text_present'?$found:!$found;$evidence=['scope'=>'body.textContent','found'=>$found];break;
                case 'responsive_css':$found=(bool)preg_match('/@media\s*\(\s*max-width\s*:/i',$design['css']);$passed=$found;$evidence=['scope'=>'sanitized CSS','maxWidthMediaRule'=>$found];break;
                default:throw new \InvalidArgumentException('Unsupported requirement.');
            }
            $checks[]=['id'=>$rule['id'],'label'=>$rule['label'],'status'=>$passed?'passed':'failed','evidence'=>$evidence];
        }
        return $checks;
    }
}
