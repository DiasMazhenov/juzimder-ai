<?php
declare(strict_types=1);
namespace Juzimder;

final class ProjectSettings {
    private const DESIGN_TOKEN_DEFAULTS = [
        'ink' => '#183e31',
        'forest' => '#245b46',
        'canvas' => '#ecf4ef',
        'paper' => '#f6faf7',
        'body' => '#527063',
        'muted' => '#68736c',
        'line' => '#dce4de',
        'accent' => '#b0dcc1',
        'fontFamily' => 'Arial, sans-serif',
        'radius' => '8px',
    ];
    private const DESIGN_TOKEN_VARIABLES = [
        'ink' => '--juzimder-color-ink',
        'forest' => '--juzimder-color-forest',
        'canvas' => '--juzimder-color-canvas',
        'paper' => '--juzimder-color-paper',
        'body' => '--juzimder-color-body',
        'muted' => '--juzimder-color-muted',
        'line' => '--juzimder-color-line',
        'accent' => '--juzimder-color-accent',
        'fontFamily' => '--juzimder-font-family',
        'radius' => '--juzimder-radius',
    ];
    private const FONT_FAMILIES = ['Arial, sans-serif', 'system-ui, sans-serif', 'Georgia, serif'];

    public static function initial(mixed $document): array {
        $pages = self::documentPages($document);
        $routes = [];
        $used = [];
        foreach ($pages as $index => $page) {
            $id = (string)$page->id;
            $base = self::defaultSlug((string)($page->name ?? ''));
            $slug = self::uniqueSlug($base, $used);
            $used[$slug] = true;
            $routes[$id] = ['slug' => $slug, 'isHome' => $index === 0];
        }
        return ['schemaVersion' => 2, 'pages' => $routes, 'designTokens' => self::designTokensFromDocument($document)];
    }

    public static function reconcile(mixed $settings, mixed $document): array {
        $pages = self::documentPages($document);
        if (!is_array($settings) || !in_array($settings['schemaVersion'] ?? null, [1, 2], true) || !is_array($settings['pages'] ?? null)) {
            $settings = self::initial($document);
        }
        $routes = [];
        $used = [];
        $homeId = null;
        foreach ($pages as $index => $page) {
            $id = (string)$page->id;
            $route = $settings['pages'][$id] ?? null;
            $slug = is_array($route) && is_string($route['slug'] ?? null) ? self::normalizeSlug($route['slug']) : self::defaultSlug((string)($page->name ?? ''));
            if ($slug === null || isset($used[$slug])) $slug = self::uniqueSlug(self::defaultSlug((string)($page->name ?? '')), $used);
            $used[$slug] = true;
            $isHome = is_array($route) && ($route['isHome'] ?? false) === true;
            if ($isHome && $homeId === null) $homeId = $id;
            $routes[$id] = ['slug' => $slug, 'isHome' => $isHome];
        }
        if ($homeId === null && $routes) $routes[(string)$pages[0]->id]['isHome'] = true;
        $settings['schemaVersion'] = 2;
        $settings['pages'] = $routes;
        $settings['designTokens'] = self::storedDesignTokens($settings['designTokens'] ?? self::designTokensFromDocument($document));
        return $settings;
    }

    public static function designTokenDefaults(): array { return self::DESIGN_TOKEN_DEFAULTS; }

    public static function validateDesignTokens(mixed $value, array $fallback = []): array {
        if ($value instanceof \stdClass) $value = get_object_vars($value);
        if (!is_array($value)) throw new Problem(422, 'Настройки цветов и шрифтов должны быть объектом.');
        if (array_diff(array_keys($value), array_keys(self::DESIGN_TOKEN_DEFAULTS))) throw new Problem(422, 'В настройках есть неизвестный design token.');
        $tokens = self::storedDesignTokens($fallback);
        foreach ($value as $name => $item) {
            if (in_array($name, ['ink','forest','canvas','paper','body','muted','line','accent'], true)) {
                if (!is_string($item) || !preg_match('/^#[0-9a-f]{6}$/i', $item)) throw new Problem(422, 'Укажите цвет в формате #RRGGBB.');
                $tokens[$name] = strtolower($item);
            } elseif ($name === 'fontFamily') {
                if (!is_string($item) || !in_array($item, self::FONT_FAMILIES, true)) throw new Problem(422, 'Выберите шрифт из списка.');
                $tokens[$name] = $item;
            } elseif ($name === 'radius') {
                if (!is_string($item) || !preg_match('/^(?:0|[1-9]|[12][0-9]|3[0-2])px$/', $item)) throw new Problem(422, 'Скругление должно быть от 0 до 32 px.');
                $tokens[$name] = $item;
            }
        }
        return $tokens;
    }

    public static function applyDesignTokensToDocument(mixed $document, array $tokens): \stdClass {
        $tokens = self::validateDesignTokens($tokens);
        $copy = json_decode(json_encode($document, JSON_THROW_ON_ERROR), false, 64, JSON_THROW_ON_ERROR);
        foreach (self::documentPages($copy) as $page) {
            foreach (($page->frames ?? []) as $frame) {
                if (!isset($frame->component) || !$frame->component instanceof \stdClass) continue;
                self::migrateLegacyStyles($frame->component);
                if (!isset($frame->component->style) || !$frame->component->style instanceof \stdClass) $frame->component->style = new \stdClass();
                foreach (self::designTokenStyles($tokens) as $property => $value) $frame->component->style->{$property} = $value;
            }
        }
        return $copy;
    }

    public static function designTokenStyles(array $tokens): array {
        $tokens = self::validateDesignTokens($tokens);
        $styles = [];
        foreach (self::DESIGN_TOKEN_VARIABLES as $name => $variable) $styles[$variable] = $tokens[$name];
        return $styles;
    }

    private static function designTokensFromDocument(mixed $document): array {
        $tokens = self::DESIGN_TOKEN_DEFAULTS;
        foreach (self::documentPages($document) as $page) {
            foreach (($page->frames ?? []) as $frame) {
                $style = $frame->component->style ?? null;
                if (!$style instanceof \stdClass) continue;
                foreach (self::DESIGN_TOKEN_VARIABLES as $name => $variable) {
                    if (isset($style->{$variable})) $tokens[$name] = $style->{$variable};
                }
                return self::storedDesignTokens($tokens);
            }
        }
        return $tokens;
    }

    private static function storedDesignTokens(mixed $value): array {
        if ($value instanceof \stdClass) $value = get_object_vars($value);
        $tokens = self::DESIGN_TOKEN_DEFAULTS;
        if (!is_array($value)) return $tokens;
        foreach ($value as $name => $item) {
            if (!array_key_exists($name, self::DESIGN_TOKEN_DEFAULTS)) continue;
            try { $tokens[$name] = self::validateDesignTokens([$name => $item])[$name]; }
            catch (Problem) { /* Ignore invalid historical values and keep the documented default. */ }
        }
        return $tokens;
    }

    private static function migrateLegacyStyles(\stdClass $component): void {
        if (isset($component->style) && $component->style instanceof \stdClass) {
            $legacy = [
                '#183e31' => 'ink', '#245b46' => 'forest', '#ecf4ef' => 'canvas', '#f6faf7' => 'paper',
                '#527063' => 'body', '#68736c' => 'muted', '#dce4de' => 'line', '#b0dcc1' => 'accent',
            ];
            foreach ($component->style as $property => $value) {
                if (!is_string($value)) continue;
                if ($property === 'font-family' && strtolower(trim($value)) === strtolower(self::DESIGN_TOKEN_DEFAULTS['fontFamily'])) {
                    $component->style->{$property} = 'var(--juzimder-font-family)';
                } elseif ($property === 'border-radius' && in_array(trim($value), ['6px','8px'], true)) {
                    $component->style->{$property} = 'var(--juzimder-radius)';
                } else {
                    $component->style->{$property} = preg_replace_callback('/#[0-9a-f]{6}\b/i', static function (array $match) use ($legacy): string {
                        $name = $legacy[strtolower($match[0])] ?? null;
                        return $name ? 'var(--juzimder-color-' . $name . ')' : $match[0];
                    }, $value) ?? $value;
                }
            }
        }
        foreach (($component->components ?? []) as $child) if ($child instanceof \stdClass) self::migrateLegacyStyles($child);
    }

    public static function validateSlug(string $raw): string {
        $slug = self::normalizeSlug($raw);
        if ($slug === null) throw new Problem(422, 'Введите URL из одной части, например about-us.');
        if (in_array($slug, ['api','assets','projects','login','register','logout','settings','editor','preview','robots-txt','sitemap-xml'], true)) {
            throw new Problem(422, 'Этот URL зарезервирован системой.');
        }
        return $slug;
    }

    public static function normalizeSlug(string $raw): ?string {
        $raw = trim($raw);
        if ($raw === '') return null;
        if (str_starts_with($raw, '/')) $raw = substr($raw, 1);
        if ($raw === '' || str_contains($raw, '/')) return null;
        $slug = self::slugify($raw);
        return $slug !== '' ? $slug : null;
    }

    public static function slugify(string $value): string {
        $map = [
            'А'=>'a','Б'=>'b','В'=>'v','Г'=>'g','Д'=>'d','Е'=>'e','Ё'=>'yo','Ж'=>'zh','З'=>'z','И'=>'i','Й'=>'y','К'=>'k','Л'=>'l','М'=>'m','Н'=>'n','О'=>'o','П'=>'p','Р'=>'r','С'=>'s','Т'=>'t','У'=>'u','Ф'=>'f','Х'=>'kh','Ц'=>'ts','Ч'=>'ch','Ш'=>'sh','Щ'=>'shch','Ъ'=>'','Ы'=>'y','Ь'=>'','Э'=>'e','Ю'=>'yu','Я'=>'ya',
            'Ә'=>'a','Ғ'=>'g','Қ'=>'q','Ң'=>'n','Ө'=>'o','Ұ'=>'u','Ү'=>'u','Һ'=>'h','І'=>'i',
        ];
        $value = strtr(mb_strtolower(trim($value), 'UTF-8'), array_combine(array_map(static fn($key) => mb_strtolower($key, 'UTF-8'), array_keys($map)), array_values($map)));
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $ascii = strtolower($ascii === false ? $value : $ascii);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $ascii) ?? '';
        return trim($slug, '-');
    }

    public static function defaultSlug(string $value): string {
        $slug = self::slugify($value);
        if ($slug === '') $slug = 'page';
        if (in_array($slug, ['api','assets','projects','login','register','logout','settings','editor','preview','robots-txt','sitemap-xml'], true)) $slug = 'page-' . $slug;
        return $slug;
    }

    private static function uniqueSlug(string $base, array $used): string {
        $base = $base !== '' ? $base : 'page';
        $candidate = $base;
        for ($suffix = 2; isset($used[$candidate]); $suffix++) $candidate = $base . '-' . $suffix;
        return $candidate;
    }

    private static function documentPages(mixed $document): array {
        if (is_array($document) && is_array($document['data']['pages'] ?? null)) {
            $pages = json_decode(json_encode($document['data']['pages'], JSON_THROW_ON_ERROR), false, 64, JSON_THROW_ON_ERROR);
            if (is_array($pages)) return $pages;
        }
        if (!$document instanceof \stdClass || !isset($document->data->pages) || !is_array($document->data->pages)) throw new Problem(422, 'Некорректный список страниц проекта.');
        return $document->data->pages;
    }
}
