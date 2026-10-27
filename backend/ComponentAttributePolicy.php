<?php
declare(strict_types=1);
namespace Juzimder;

/** Shared safe attribute contract for typed operations and static HTML export. */
final class ComponentAttributePolicy {
    public const ATTRIBUTES = ['id','class','href','src','alt','title','rel','target','role','aria-label','aria-hidden','aria-describedby','aria-labelledby','aria-controls','aria-selected','aria-expanded','aria-pressed','datetime','for','name','type','value','placeholder','min','max','step','rows','cols','accept','autocomplete','inputmode','pattern','maxlength','minlength','size','hidden','open','required','disabled','checked','multiple','readonly','selected'];
    private const BOOLEAN_ATTRIBUTES = ['hidden','open','required','disabled','checked','multiple','readonly','selected'];
    private const ENUM_VALUES = [
        'aria-selected'=>['true','false','undefined'],
        'aria-expanded'=>['true','false'],
        'aria-pressed'=>['true','false','mixed'],
        'aria-hidden'=>['true','false'],
    ];
    private const DATA_ATTRIBUTE_PATTERN = '/^data-(?!jz-)[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/D';

    /** @return list<string> */
    public static function attributes(): array { return self::ATTRIBUTES; }
    /** @return list<string> */
    public static function booleanAttributes(): array { return self::BOOLEAN_ATTRIBUTES; }
    /** @return array<string,list<string>> */
    public static function enumValues(): array { return self::ENUM_VALUES; }
    public static function isBoolean(string $name): bool { return in_array($name, self::BOOLEAN_ATTRIBUTES, true); }
    public static function isDataAttribute(string $name): bool { return preg_match(self::DATA_ATTRIBUTE_PATTERN, $name) === 1; }
    public static function isSafeName(string $name): bool {
        return in_array($name, self::attributes(), true) || self::isDataAttribute($name);
    }
    public static function isValidEnumValue(string $name, string $value): bool {
        return !isset(self::ENUM_VALUES[$name]) || in_array($value, self::ENUM_VALUES[$name], true);
    }
}
