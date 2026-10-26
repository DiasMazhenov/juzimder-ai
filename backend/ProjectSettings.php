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
        'headingFontFamily' => 'Arial, sans-serif',
        'bodyFontFamily' => 'Arial, sans-serif',
        'bodyFontWeight' => '400',
        'headingFontWeight' => '500',
        'baseFontSize' => '16px',
        'fontSizeH1' => '48px',
        'fontSizeH2' => '36px',
        'fontSizeH3' => '24px',
        'fontSizeH4' => '20px',
        'fontSizeH5' => '18px',
        'fontSizeH6' => '16px',
        'bodyLineHeight' => '1.6',
        'headingLineHeight' => '1.15',
        'letterSpacing' => '0px',
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
        'headingFontFamily' => '--juzimder-font-heading',
        'bodyFontFamily' => '--juzimder-font-body',
        'bodyFontWeight' => '--juzimder-font-weight-body',
        'headingFontWeight' => '--juzimder-font-weight-heading',
        'baseFontSize' => '--juzimder-font-size-base',
        'fontSizeH1' => '--juzimder-font-size-h1',
        'fontSizeH2' => '--juzimder-font-size-h2',
        'fontSizeH3' => '--juzimder-font-size-h3',
        'fontSizeH4' => '--juzimder-font-size-h4',
        'fontSizeH5' => '--juzimder-font-size-h5',
        'fontSizeH6' => '--juzimder-font-size-h6',
        'bodyLineHeight' => '--juzimder-line-height-body',
        'headingLineHeight' => '--juzimder-line-height-heading',
        'letterSpacing' => '--juzimder-letter-spacing',
        'radius' => '--juzimder-radius',
    ];
    private const FONT_FAMILIES = ['Arial, sans-serif', 'system-ui, sans-serif', 'Georgia, serif'];
    private const SEO_DEFAULTS = ['title' => '', 'description' => '', 'robots' => 'index', 'socialImage' => '', 'socialTitle' => '', 'socialDescription' => ''];
    private const FORM_DEFAULTS = [
        'notificationsEnabled' => false, 'recipients' => [], 'senderName' => '', 'senderEmail' => '',
        'subject' => 'Новая заявка с сайта {{site}}', 'body' => "Новая заявка\n\n{{fields}}",
        'autoresponderEnabled' => false, 'autoresponderEmailField' => 'email',
        'autoresponderSubject' => 'Мы получили вашу заявку',
        'autoresponderBody' => "Здравствуйте!\n\nМы получили вашу заявку и свяжемся с вами.\n\n{{site}}",
    ];

    public static function initial(mixed $document): array {
        $pages = self::documentPages($document);
        $routes = [];
        $used = [];
        foreach ($pages as $index => $page) {
            $id = (string)$page->id;
            $base = self::defaultSlug((string)($page->name ?? ''));
            $slug = self::uniqueSlug($base, $used);
            $used[$slug] = true;
            $routes[$id] = ['slug' => $slug, 'isHome' => $index === 0, 'seo' => self::SEO_DEFAULTS];
        }
        return ['schemaVersion' => 6, 'pages' => $routes, 'designTokens' => self::designTokensFromDocument($document), 'forms' => self::FORM_DEFAULTS];
    }

    public static function reconcile(mixed $settings, mixed $document): array {
        $pages = self::documentPages($document);
        if (!is_array($settings) || !in_array($settings['schemaVersion'] ?? null, [1, 2, 3, 4, 5, 6], true) || !is_array($settings['pages'] ?? null)) {
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
            try { $seo = self::validateSeoMetadata(is_array($route) ? ($route['seo'] ?? []) : [], self::SEO_DEFAULTS); }
            catch (Problem) { $seo = self::SEO_DEFAULTS; }
            $routes[$id] = ['slug' => $slug, 'isHome' => $isHome, 'seo' => $seo];
        }
        if ($homeId === null && $routes) $routes[(string)$pages[0]->id]['isHome'] = true;
        $settings['schemaVersion'] = 6;
        $settings['pages'] = $routes;
        $settings['designTokens'] = self::storedDesignTokens($settings['designTokens'] ?? self::designTokensFromDocument($document));
        try { $settings['forms'] = self::validateFormsSettings($settings['forms'] ?? [], self::FORM_DEFAULTS); }
        catch (Problem) { $settings['forms'] = self::FORM_DEFAULTS; }
        return $settings;
    }

    public static function formDefaults(): array { return self::FORM_DEFAULTS; }

    public static function validateFormsSettings(mixed $value, array $fallback = []): array {
        if ($value instanceof \stdClass) $value = get_object_vars($value);
        if (!is_array($value) || array_diff(array_keys($value), array_keys(self::FORM_DEFAULTS))) throw new Problem(422, 'Настройки форм содержат недопустимые поля.');
        $result = array_replace(self::FORM_DEFAULTS, $fallback);
        foreach (['notificationsEnabled','autoresponderEnabled'] as $key) {
            if (array_key_exists($key, $value)) {
                if (!is_bool($value[$key])) throw new Problem(422, 'Проверьте состояние переключателей доставки.');
                $result[$key] = $value[$key];
            }
        }
        if (array_key_exists('recipients', $value)) {
            if (!is_array($value['recipients']) || !array_is_list($value['recipients']) || count($value['recipients']) > 10) throw new Problem(422, 'Укажите не более 10 адресов получателей.');
            $recipients = [];
            foreach ($value['recipients'] as $email) {
                if (!is_string($email) || strlen($email) > 254 || !filter_var(trim($email), FILTER_VALIDATE_EMAIL)) throw new Problem(422, 'Проверьте адреса email для уведомлений.');
                $email = strtolower(trim($email));
                if (!in_array($email, $recipients, true)) $recipients[] = $email;
            }
            $result['recipients'] = $recipients;
        }
        foreach (['senderName'=>100,'senderEmail'=>254,'subject'=>200,'body'=>10000,'autoresponderEmailField'=>64,'autoresponderSubject'=>200,'autoresponderBody'=>10000] as $key=>$limit) {
            if (!array_key_exists($key, $value)) continue;
            $item = $value[$key];
            if (!is_string($item) || mb_strlen($item, 'UTF-8') > $limit || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $item)) throw new Problem(422, 'Проверьте длину и символы в настройках писем.');
            $item = trim($item);
            if ($key === 'senderEmail' && $item !== '' && !filter_var($item, FILTER_VALIDATE_EMAIL)) throw new Problem(422, 'Укажите корректный email отправителя.');
            if ($key === 'autoresponderEmailField' && $item !== '' && !preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/D', $item)) throw new Problem(422, 'Укажите имя поля email из формы.');
            $result[$key] = $item;
        }
        return $result;
    }

    public static function designTokenDefaults(): array { return self::DESIGN_TOKEN_DEFAULTS; }

    public static function seoDefaults(): array { return self::SEO_DEFAULTS; }

    public static function validateSeoMetadata(mixed $value, array $fallback = []): array {
        if ($value instanceof \stdClass) $value = get_object_vars($value);
        if (!is_array($value) || array_diff(array_keys($value), array_keys(self::SEO_DEFAULTS))) throw new Problem(422, 'SEO-настройки должны содержать только разрешённые поля.');
        $seo = self::SEO_DEFAULTS;
        foreach (self::SEO_DEFAULTS as $name => $default) {
            if (array_key_exists($name, $fallback)) $seo[$name] = $fallback[$name];
        }
        foreach ($value as $name => $item) {
            if (in_array($name, ['title', 'description', 'socialTitle', 'socialDescription'], true)) {
                $limit = in_array($name, ['title','socialTitle'], true) ? 70 : 160;
                if (!is_string($item) || mb_strlen(trim($item), 'UTF-8') > $limit || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $item)) throw new Problem(422, $name === 'title' ? 'Заголовок SEO: не более 70 символов.' : 'Описание SEO: не более 160 символов.');
                $seo[$name] = trim($item);
            } elseif ($name === 'robots') {
                if (!is_string($item) || !in_array($item, ['index', 'noindex'], true)) throw new Problem(422, 'Выберите index или noindex.');
                $seo[$name] = $item;
            } elseif ($name === 'socialImage') {
                if (!is_string($item) || mb_strlen($item, 'UTF-8') > 2048) throw new Problem(422, 'Ссылка на изображение должна быть HTTPS-адресом длиной до 2048 символов.');
                $item = trim($item);
                if ($item !== '') {
                    $parts = parse_url($item);
                    if (!filter_var($item, FILTER_VALIDATE_URL) || !is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) throw new Problem(422, 'Изображение для соцсетей должно иметь корректный HTTPS-адрес.');
                }
                $seo[$name] = $item;
            }
        }
        return $seo;
    }

    public static function validateDesignTokens(mixed $value, array $fallback = []): array {
        if ($value instanceof \stdClass) $value = get_object_vars($value);
        if (!is_array($value)) throw new Problem(422, 'Настройки цветов и шрифтов должны быть объектом.');
        if (array_diff(array_keys($value), array_keys(self::DESIGN_TOKEN_DEFAULTS))) throw new Problem(422, 'В настройках есть неизвестный design token.');
        $tokens = self::storedDesignTokens($fallback);
        foreach ($value as $name => $item) {
            if (in_array($name, ['ink','forest','canvas','paper','body','muted','line','accent'], true)) {
                if (!is_string($item) || !preg_match('/^#[0-9a-f]{6}$/i', $item)) throw new Problem(422, 'Укажите цвет в формате #RRGGBB.');
                $tokens[$name] = strtolower($item);
            } elseif (in_array($name, ['fontFamily','headingFontFamily','bodyFontFamily'], true)) {
                if (!is_string($item) || !in_array($item, self::FONT_FAMILIES, true)) throw new Problem(422, 'Выберите шрифт из списка.');
                $tokens[$name] = $item;
            } elseif (in_array($name, ['bodyFontWeight','headingFontWeight'], true)) {
                if (!is_string($item) || !in_array($item, ['300','400','500','600','700','800'], true)) throw new Problem(422, 'Укажите вес шрифта от 300 до 800.');
                $tokens[$name] = $item;
            } elseif (in_array($name, ['baseFontSize','fontSizeH1','fontSizeH2','fontSizeH3','fontSizeH4','fontSizeH5','fontSizeH6'], true)) {
                $bounds = ['baseFontSize'=>[12,24],'fontSizeH1'=>[28,80],'fontSizeH2'=>[22,64],'fontSizeH3'=>[18,48],'fontSizeH4'=>[16,40],'fontSizeH5'=>[14,32],'fontSizeH6'=>[12,28]][$name];
                if (!is_string($item) || !preg_match('/^(\d{1,3})px$/', $item, $size) || (int)$size[1] < $bounds[0] || (int)$size[1] > $bounds[1]) throw new Problem(422, 'Размер типографического токена вне допустимого диапазона.');
                $tokens[$name] = ((int)$size[1]) . 'px';
            } elseif (in_array($name, ['bodyLineHeight','headingLineHeight'], true)) {
                if (!is_string($item) || !in_array($item, ['1','1.1','1.15','1.2','1.25','1.3','1.4','1.5','1.6','1.7','1.8','1.9','2'], true)) throw new Problem(422, 'Межстрочный интервал должен быть от 1 до 2.');
                $tokens[$name] = $item;
            } elseif ($name === 'letterSpacing') {
                if (!is_string($item) || !in_array($item, ['-2px','-1px','-0.5px','0px','0.25px','0.5px','1px','1.5px','2px'], true)) throw new Problem(422, 'Выберите межбуквенный интервал из списка.');
                $tokens[$name] = $item;
            } elseif ($name === 'radius') {
                if (!is_string($item) || !preg_match('/^(?:0|[1-9]|[12][0-9]|3[0-2])px$/', $item)) throw new Problem(422, 'Скругление должно быть от 0 до 32 px.');
                $tokens[$name] = $item;
            }
        }
        $hasSeparateFamilies = array_key_exists('headingFontFamily', $value) || array_key_exists('bodyFontFamily', $value);
        if (array_key_exists('fontFamily', $value) && !$hasSeparateFamilies) {
            $tokens['headingFontFamily'] = $tokens['fontFamily'];
            $tokens['bodyFontFamily'] = $tokens['fontFamily'];
        } else {
            if (array_key_exists('headingFontFamily', $value)) $tokens['headingFontFamily'] = $value['headingFontFamily'];
            if (array_key_exists('bodyFontFamily', $value)) $tokens['bodyFontFamily'] = $value['bodyFontFamily'];
            $tokens['fontFamily'] = $tokens['bodyFontFamily'];
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
                self::applyInheritedTypography($frame->component->style);
                self::applyHeadingDefaults($frame->component);
            }
        }
        return $copy;
    }

    public static function designTokenStyles(array $tokens): array {
        $tokens = self::validateDesignTokens($tokens);
        $styles = [];
        foreach (self::DESIGN_TOKEN_VARIABLES as $name => $variable) $styles[$variable] = self::designTokenCssValue($name, $tokens[$name]);
        return $styles;
    }

    private static function designTokenCssValue(string $name, string $value): string {
        if ($name === 'fontSizeH1') return 'clamp(28px, calc(18px + 2.35vw), ' . $value . ')';
        if ($name === 'fontSizeH2') return 'clamp(22px, calc(17px + 1.5vw), ' . $value . ')';
        if ($name === 'fontSizeH3') return 'clamp(18px, calc(12px + 0.9vw), ' . $value . ')';
        return $value;
    }

    private static function designTokensFromDocument(mixed $document): array {
        $tokens = self::DESIGN_TOKEN_DEFAULTS;
        foreach (self::documentPages($document) as $page) {
            foreach (($page->frames ?? []) as $frame) {
                $style = $frame->component->style ?? null;
                if (!$style instanceof \stdClass) continue;
                foreach (self::DESIGN_TOKEN_VARIABLES as $name => $variable) {
                    if (!isset($style->{$variable})) continue;
                    $value = $style->{$variable};
                    if (in_array($name, ['fontSizeH1','fontSizeH2','fontSizeH3'], true) && is_string($value) && preg_match('/,\s*(\d{1,3}px)\s*\)$/', $value, $size)) $value = $size[1];
                    $tokens[$name] = $value;
                }
                if (isset($style->{'--juzimder-font-family'}) && !isset($style->{'--juzimder-font-heading'}) && !isset($style->{'--juzimder-font-body'})) {
                    $tokens['fontFamily'] = $style->{'--juzimder-font-family'};
                    $tokens['headingFontFamily'] = $style->{'--juzimder-font-family'};
                    $tokens['bodyFontFamily'] = $style->{'--juzimder-font-family'};
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
        if (isset($value['fontFamily']) && !isset($value['headingFontFamily'])) $tokens['headingFontFamily'] = $tokens['fontFamily'];
        if (isset($value['fontFamily']) && !isset($value['bodyFontFamily'])) $tokens['bodyFontFamily'] = $tokens['fontFamily'];
        $tokens['fontFamily'] = $tokens['bodyFontFamily'];
        return $tokens;
    }

    private static function migrateLegacyStyles(\stdClass $component): void {
        if (isset($component->style) && $component->style instanceof \stdClass) {
            $legacy = [
                '#183e31' => 'ink', '#245b46' => 'forest', '#ecf4ef' => 'canvas', '#f6faf7' => 'paper',
                '#527063' => 'body', '#68736c' => 'muted', '#dce4de' => 'line', '#b0dcc1' => 'accent',
            ];
            $tagName = strtolower((string)($component->tagName ?? ''));
            foreach ($component->style as $property => $value) {
                if (!is_string($value)) continue;
                if ($property === 'font-family' && (strtolower(trim($value)) === strtolower(self::DESIGN_TOKEN_DEFAULTS['fontFamily']) || str_contains($value, '--juzimder-font-family'))) {
                    $fontToken = preg_match('/^h[1-6]$/', $tagName) ? '--juzimder-font-heading' : '--juzimder-font-body';
                    $component->style->{$property} = 'var(' . $fontToken . ')';
                } elseif ($property === 'font-size' && preg_match('/^h([1-6])$/', $tagName, $heading)) {
                    $legacySizes = ['1'=>['clamp(36px, 6vw, 76px)'],'2'=>['36px','clamp(28px, 4vw, 42px)','clamp(28px,4vw,42px)'],'3'=>['20px','18px','16px']];
                    if (str_contains($value, '--juzimder-font-size-h' . $heading[1]) || in_array(trim($value), $legacySizes[$heading[1]] ?? [], true)) $component->style->{$property} = 'var(--juzimder-font-size-h' . $heading[1] . ')';
                } elseif ($property === 'font-size' && $tagName === 'p' && trim($value) === '18px') {
                    $component->style->{$property} = 'var(--juzimder-font-size-base)';
                } elseif ($property === 'font-weight' && preg_match('/^h[1-6]$/', $tagName) && in_array(trim($value), ['500','600','700'], true)) {
                    $component->style->{$property} = 'var(--juzimder-font-weight-heading)';
                } elseif ($property === 'line-height' && preg_match('/^h[1-6]$/', $tagName) && in_array(trim($value), ['1.05','1.15','1.2'], true)) {
                    $component->style->{$property} = 'var(--juzimder-line-height-heading)';
                } elseif ($property === 'line-height' && !preg_match('/^h[1-6]$/', $tagName) && in_array(trim($value), ['1.6','1.65','1.7'], true)) {
                    $component->style->{$property} = 'var(--juzimder-line-height-body)';
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

    private static function applyInheritedTypography(\stdClass $style): void {
        $defaults = [
            'font-family' => 'var(--juzimder-font-body, Arial, sans-serif)',
            'font-size' => 'var(--juzimder-font-size-base, 16px)',
            'font-weight' => 'var(--juzimder-font-weight-body, 400)',
            'line-height' => 'var(--juzimder-line-height-body, 1.6)',
            'letter-spacing' => 'var(--juzimder-letter-spacing, 0px)',
        ];
        foreach ($defaults as $property => $value) {
            $current = $style->{$property} ?? null;
            if (!is_string($current) || !str_contains($current, '--juzimder-font') && !str_contains($current, '--juzimder-line-height') && !str_contains($current, '--juzimder-letter-spacing')) {
                if ($current === null) $style->{$property} = $value;
                continue;
            }
            $style->{$property} = $value;
        }
    }

    private static function applyHeadingDefaults(\stdClass $component): void {
        $tagName = strtolower((string)($component->tagName ?? ''));
        if (preg_match('/^h([1-6])$/', $tagName, $match)) {
            if (!isset($component->style) || !$component->style instanceof \stdClass) $component->style = new \stdClass();
            $heading = $match[1];
            $defaults = [
                'font-family' => 'var(--juzimder-font-heading, Arial, sans-serif)',
                'font-size' => 'var(--juzimder-font-size-h' . $heading . ')',
                'font-weight' => 'var(--juzimder-font-weight-heading, 500)',
                'line-height' => 'var(--juzimder-line-height-heading, 1.15)',
            ];
            foreach ($defaults as $property => $value) {
                $current = $component->style->{$property} ?? null;
                if ($current === null || is_string($current) && (str_contains($current, '--juzimder-font') || str_contains($current, '--juzimder-line-height'))) $component->style->{$property} = $value;
            }
        }
        foreach (($component->components ?? []) as $child) if ($child instanceof \stdClass) self::applyHeadingDefaults($child);
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
