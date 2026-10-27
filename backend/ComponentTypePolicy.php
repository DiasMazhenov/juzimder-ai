<?php
declare(strict_types=1);
namespace Juzimder;

/** Shared tag contract for typed creation and the static renderer. */
final class ComponentTypePolicy {
    private const TAGS = ['div','section','header','footer','nav','main','article','aside','h1','h2','h3','h4','p','span','a','img','button','ul','ol','li','strong','em','b','i','br','hr','label','form','input','textarea','select','option','details','summary','figure','figcaption','time'];
    private const VOID_TAGS = ['img','br','hr','input'];
    private const SEMANTIC_TAGS_BY_TYPE = [
        'text'=>['h1','h2','h3','h4','p','span','strong','em','b','i','li','summary','figcaption','time'],
        'default'=>['div','section','header','footer','nav','main','article','aside','ul','ol','li','details','figure'],
        ''=>['div','section','header','footer','nav','main','article','aside','ul','ol','li','details','figure'],
    ];
    private const RENDERER_TAG_BY_TYPE = [
        'image'=>'img','link'=>'a','link-block'=>'a','form'=>'form','input'=>'input','checkbox'=>'input','radio'=>'input',
        'textarea'=>'textarea','select'=>'select','option'=>'option','button'=>'button','label'=>'label',
        'custom-code'=>'div','countdown'=>'div',
    ];
    private const DEFAULT_TAG_BY_TYPE = [
        ''=>'div','default'=>'div','wrapper'=>'div','text'=>'div','textnode'=>null,'image'=>'img','link'=>'a','link-block'=>'a','head'=>null,
        'form'=>'form','input'=>'input','checkbox'=>'input','radio'=>'input','textarea'=>'textarea','select'=>'select','option'=>'option','button'=>'button','label'=>'label',
        'custom-code'=>'div','countdown'=>'div','zero-block'=>null,
    ];
    private const TAGS_OWNED_BY_TYPED_RENDERERS = ['img','form','input','textarea','select','option'];

    /** @return list<string> */
    public static function tags(): array { return self::TAGS; }
    /** @return list<string> */
    public static function types(): array { return array_keys(self::DEFAULT_TAG_BY_TYPE); }
    /** @return list<string> */
    public static function voidTags(): array { return self::VOID_TAGS; }
    /** @return list<string> */
    public static function semanticTags(string $type): array { return self::SEMANTIC_TAGS_BY_TYPE[$type] ?? []; }
    public static function tagFor(string $type, ?string $tagName): ?string {
        if ($tagName !== null) return strtolower($tagName);
        return self::DEFAULT_TAG_BY_TYPE[$type] ?? null;
    }

    public static function allowsTypedCreation(?string $type, ?string $tagName): bool {
        if ($tagName !== null && !in_array($tagName, self::TAGS, true)) return false;
        if ($type === 'head') return false;
        if (in_array($type, ['textnode','zero-block'], true)) return $tagName === null;
        if ($type === null) return $tagName === null || !in_array($tagName, self::TAGS_OWNED_BY_TYPED_RENDERERS, true);

        if (isset(self::RENDERER_TAG_BY_TYPE[$type])) {
            return $tagName === null || $tagName === self::RENDERER_TAG_BY_TYPE[$type];
        }
        if (in_array($type, ['','default','wrapper'], true)) {
            return $tagName === null || !in_array($tagName, self::TAGS_OWNED_BY_TYPED_RENDERERS, true);
        }
        if ($type === 'text') {
            return $tagName === null || in_array($tagName, self::semanticTags($type), true);
        }
        return false;
    }
}
