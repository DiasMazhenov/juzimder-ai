<?php
declare(strict_types=1);
namespace Juzimder;
/** Bounded native CSS background: one image URL and optional uniform overlay. */
final class BackgroundImage {
    public static function source(mixed $value): ?string {
        if (!is_string($value) || strlen($value)>2048) return null;
        if (!preg_match('~^(?:linear-gradient\((rgba\((\d{1,3}), (\d{1,3}), (\d{1,3}), (?:0(?:\.\d{1,3})?|1(?:\.0{1,3})?)\)), \1\), )?url\("([^"\s<>\\\\(){};]+)"\)$~D', $value,$match)) return null;
        foreach ([2,3,4] as $index) if (($match[$index]??'')!=='' && (int)$match[$index]>255) return null;
        $src=$match[5];
        if (preg_match('~^/api/projects/[a-f0-9]{24}/assets/[a-f0-9]{24}/file$~D',$src)) return $src;
        $parts=parse_url($src);
        return is_array($parts) && ($parts['scheme']??'')==='https' && !empty($parts['host']) && !isset($parts['user']) && !isset($parts['pass']) ? $src : null;
    }
    public static function valid(mixed $value): bool { return $value==='none' || self::source($value)!==null; }
}
