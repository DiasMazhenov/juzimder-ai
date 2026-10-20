<?php
declare(strict_types=1);
namespace Juzimder;

final class ProjectSettings {
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
        return ['schemaVersion' => 1, 'pages' => $routes];
    }

    public static function reconcile(mixed $settings, mixed $document): array {
        $pages = self::documentPages($document);
        $validIds = [];
        foreach ($pages as $page) $validIds[(string)$page->id] = true;
        if (!is_array($settings) || ($settings['schemaVersion'] ?? null) !== 1 || !is_array($settings['pages'] ?? null)) {
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
        $settings['schemaVersion'] = 1;
        $settings['pages'] = $routes;
        return $settings;
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
