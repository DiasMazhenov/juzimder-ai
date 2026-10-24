<?php
declare(strict_types=1);
namespace Juzimder;
/** Exact trusted images only; arbitrary SVG/data URLs remain forbidden. */
final class IconCatalogue {
    public static function all(): array {
        static $icons;
        return $icons ??= json_decode(file_get_contents(__DIR__.'/../resources/tabler-outline/catalogue.json'), true, 32, JSON_THROW_ON_ERROR);
    }
    public static function permits(mixed $src): bool {
        static $placeholder;
        $placeholder ??= json_decode(file_get_contents(__DIR__.'/../resources/image-placeholder.json'),true,4,JSON_THROW_ON_ERROR)['src'];
        return is_string($src) && ($src === $placeholder || in_array($src, self::all(), true));
    }
    public static function markup(string $key): string {
        $src = self::all()[$key];
        return '<img src="'.$src.'" alt="" aria-hidden="true" width="32" height="32">';
    }
}
