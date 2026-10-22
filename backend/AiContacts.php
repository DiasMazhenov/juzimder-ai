<?php
declare(strict_types=1);
namespace Juzimder;

/** Presence in the owner's brief is permission to reuse, not proof of factual accuracy. */
final class AiContacts {
    private static function emails(string $text): array {
        preg_match_all('~[A-Za-z0-9.!#$%&\x27*+/=?^_`{|}\~-]+@[A-Za-z0-9](?:[A-Za-z0-9.-]*[A-Za-z0-9])?\.[A-Za-z]{2,63}~',$text,$matches);
        return array_map('strtolower',$matches[0]);
    }
    private static function urls(string $text): array {
        preg_match_all('~https?://[^\s<>"\x27]+~i',$text,$matches);
        return array_map(static fn($url)=>rtrim($url,'.,;!?)'),$matches[0]);
    }
    private static function phone(string $text): string {return preg_replace('/[^0-9]/','',$text);}
    private static function phones(string $text,bool $internationalOnly=false): array {
        preg_match_all($internationalOnly?'~(?<![\pL\d])\+\d[\d ()-]{5,}\d(?!\d)~u':'~(?<![\pL\d])\+?\d[\d ()-]{1,}\d(?!\d)~u',$text,$matches);
        return array_values(array_filter(array_map(self::phone(...),$matches[0]),static fn($digits)=>strlen($digits)>=($internationalOnly?7:3)&&strlen($digits)<=15));
    }
    public static function audit(array $design,string $brief): array {
        $emails=self::emails($brief);$urls=self::urls($brief);$phones=self::phones($brief);
        $document=new \DOMDocument('1.0','UTF-8');$previous=libxml_use_internal_errors(true);
        try{$loaded=$document->loadHTML('<!doctype html><html><head><meta charset="UTF-8"></head><body>'.$design['html'].'</body></html>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);}finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
        if(!$loaded)throw new AiProviderFailure('Contact audit could not parse HTML.',false,'contact_requirements');
        $checked=0;
        $check=static function(string $value,array $allowed)use(&$checked):void{
            $checked++;
            if(!in_array($value,$allowed,true))throw new AiProviderFailure('Generated contact is absent from the owner brief.',false,'contact_requirements');
        };
        foreach($document->getElementsByTagName('a') as $link){
            $href=$link->getAttribute('href');if($href===''||str_starts_with($href,'#'))continue;
            if(str_starts_with($href,'mailto:'))$check(strtolower(substr($href,7)),$emails);
            elseif(str_starts_with($href,'tel:'))$check(self::phone(substr($href,4)),$phones);
            else $check($href,$urls);
        }
        // Ignore script/style data; audit only body copy from already sanitized markup.
        $bodyText=static function(\DOMNode $node)use(&$bodyText):string{
            if($node instanceof \DOMText)return $node->textContent;
            $text='';foreach($node->childNodes as $child)$text.=$bodyText($child);
            return $node instanceof \DOMElement&&in_array($node->tagName,['div','section','header','footer','nav','p','ul','ol','li','h1','h2','h3','h4','h5','h6','br'],true)?' '.$text.' ':$text;
        };
        $text=$bodyText($document->getElementsByTagName('body')->item(0));
        foreach(self::emails($text) as $email)$check($email,$emails);
        foreach(self::urls($text) as $url)$check($url,$urls);
        foreach(self::phones($text,true) as $phone)$check($phone,$phones);
        return ['status'=>'passed','policy'=>'brief-contacts-v1','checkedOccurrences'=>$checked,'scope'=>'External hrefs, email addresses, HTTP URLs and international phone numbers in body text; owner brief only.'];
    }
}
