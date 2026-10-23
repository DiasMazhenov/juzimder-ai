<?php
declare(strict_types=1);
namespace Juzimder;

final class StaticSiteExporter {
    private const TAGS = ['div','section','header','footer','nav','main','article','aside','h1','h2','h3','h4','p','span','a','img','button','ul','ol','li','strong','em','b','i','br','hr','label'];
    private const VOID_TAGS = ['img','br','hr'];
    private const ATTRIBUTES = ['id','class','href','src','alt','title','rel','target','role','aria-label','aria-hidden','aria-describedby','aria-labelledby'];
    private const STYLE_PROPERTIES = [
        'display','flex-direction','justify-content','align-items','flex-wrap','align-self','grid-template-columns','gap',
        'width','height','min-width','max-width','min-height','max-height','padding','padding-top','padding-right','padding-bottom','padding-left',
        'margin','margin-top','margin-right','margin-bottom','margin-left','color','background-color','font-family','font-size','font-weight',
        'line-height','letter-spacing','text-align','text-decoration','border','border-top','border-bottom','border-radius','object-fit','aspect-ratio','opacity','cursor',
        '--juzimder-color-ink','--juzimder-color-forest','--juzimder-color-canvas','--juzimder-color-paper','--juzimder-color-body','--juzimder-color-muted','--juzimder-color-line','--juzimder-color-accent',
        '--juzimder-font-family','--juzimder-font-heading','--juzimder-font-body','--juzimder-font-weight-body','--juzimder-font-weight-heading',
        '--juzimder-font-size-base','--juzimder-font-size-h1','--juzimder-font-size-h2','--juzimder-font-size-h3','--juzimder-font-size-h4','--juzimder-font-size-h5','--juzimder-font-size-h6',
        '--juzimder-line-height-body','--juzimder-line-height-heading','--juzimder-letter-spacing','--juzimder-radius'
    ];
    private const RICH_TEXT_TAGS = ['p','span','br','strong','em','b','i','u','s'];

    /** @return array<string,string> Paths and file contents for a deployable static ZIP. */
    public static function files(array $project, array $assets = []): array {
        $document = $project['document'] ?? null;
        Document::validate($document);
        if (!$document instanceof \stdClass) throw new Problem(422, 'Некорректный документ проекта.');
        $projectId = is_string($project['id'] ?? null) ? $project['id'] : '';
        $assetPaths = [];
        if ($assets || Document::assetReferences($document)) {
            if (!preg_match('/^[a-f0-9]{24}$/D', $projectId)) throw new Problem(422, 'Не указан проект для экспорта изображений.');
            foreach (Document::assetReferences($document) as $reference) {
                if ($reference['projectId'] !== $projectId || !isset($assets[$reference['assetId']])) throw new Problem(422, 'Изображение не найдено в текущем проекте.');
            }
            foreach ($assets as $assetId=>$asset) {
                if (!is_array($asset) || !preg_match('/^[a-f0-9]{24}$/D', (string)$assetId) || !is_string($asset['mime'] ?? null) || !is_string($asset['content'] ?? null)) throw new Problem(422, 'Некорректное изображение для экспорта.');
                $extension = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'][$asset['mime']] ?? null;
                if ($extension === null || strlen($asset['content']) > 4194304) throw new Problem(422, 'Формат или размер изображения для экспорта недопустим.');
                $assetPaths[$assetId] = 'media/' . $projectId . '/' . $assetId . '.' . $extension;
            }
        }
        $settings = ProjectSettings::reconcile($project['settings'] ?? null, $document);
        $document = ProjectSettings::applyDesignTokensToDocument($document, $settings['designTokens']);
        Document::validate($document);
        $css = self::stylesheet($document->data->styles ?? []);
        $pages = $document->data->pages;
        $home = null;
        foreach ($pages as $page) if (($settings['pages'][(string)$page->id]['isHome'] ?? false) === true) { $home = (string)$page->id; break; }
        if ($home === null && isset($pages[0])) $home = (string)$pages[0]->id;

        $files = ['assets/site.css' => $css];
        foreach ($assets as $assetId=>$asset) $files[$assetPaths[$assetId]] = $asset['content'];
        foreach ($pages as $page) {
            $pageId = (string)$page->id;
            $route = $settings['pages'][$pageId] ?? null;
            if (!is_array($route)) throw new Problem(422, 'Для страницы не найдены настройки маршрута.');
            $isHome = $pageId === $home;
            $slug = $isHome ? '' : (string)$route['slug'];
            if (!$isHome && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)) throw new Problem(422, 'У страницы недопустимый URL.');
            $path = $isHome ? 'index.html' : $slug . '/index.html';
            $root = $page->frames[0]->component ?? null;
            if (!$root instanceof \stdClass) throw new Problem(422, 'У страницы отсутствует корневой компонент.');
            $relativeAssetPaths = [];
            foreach ($assetPaths as $assetId=>$assetPath) $relativeAssetPaths['/api/projects/' . $projectId . '/assets/' . $assetId . '/file'] = ($isHome ? '' : '../') . $assetPath;
            $html = self::pageHtml($page, $root, $route['seo'] ?? [], $isHome ? 'assets/site.css' : '../assets/site.css', $relativeAssetPaths);
            $files[$path] = $html;
        }
        $files['README.txt'] = "Juzimder AI static site export\n\nOpen index.html locally or upload the extracted files to a static web host.\nSubpages are stored in their configured route folders; shared styles are in assets/site.css.\nReferenced project images are copied into media/.\n\nThis archive contains only static HTML, CSS and referenced images. Forms, AI, analytics, custom code, domains and online publication are not included. No public deployment was performed.\n";
        return $files;
    }

    public static function inlineAssets(string $html, array $assets, string $projectId, bool $isHome): string {
        foreach ($assets as $assetId=>$asset) {
            $extension = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'][$asset['mime'] ?? ''] ?? null;
            if ($extension === null || !is_string($asset['content'] ?? null)) continue;
            $path = ($isHome ? '' : '../') . 'media/' . $projectId . '/' . $assetId . '.' . $extension;
            $html = str_replace('src="' . self::escape($path) . '"', 'src="data:' . $asset['mime'] . ';base64,' . base64_encode($asset['content']) . '"', $html);
        }
        return $html;
    }

    public static function zip(array $project, array $assets = []): string {
        if (!class_exists(\ZipArchive::class)) throw new Problem(503, 'Для ZIP-экспорта нужна PHP Zip extension.');
        $files = self::files($project, $assets);
        $temporaryPath = tempnam(sys_get_temp_dir(), 'juzimder-static-');
        if (!is_string($temporaryPath)) throw new Problem(503, 'Не удалось подготовить архив экспорта.');
        try {
            $archive = new \ZipArchive();
            if ($archive->open($temporaryPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) throw new Problem(503, 'Не удалось создать ZIP-архив.');
            foreach ($files as $path => $contents) {
                if (!$archive->addFromString($path, $contents)) {
                    $archive->close();
                    throw new Problem(503, 'Не удалось записать файл в ZIP-архив.');
                }
            }
            if (!$archive->close()) throw new Problem(503, 'Не удалось завершить ZIP-архив.');
            $zip = file_get_contents($temporaryPath);
            if (!is_string($zip)) throw new Problem(503, 'Не удалось прочитать ZIP-архив.');
            return $zip;
        } finally {
            @unlink($temporaryPath);
        }
    }

    private static function pageHtml(\stdClass $page, \stdClass $root, array $seo, string $stylesheetPath, array $assetPaths = []): string {
        $title = trim((string)($seo['title'] ?? '')) ?: trim((string)($page->name ?? '')) ?: 'Страница';
        $description = trim((string)($seo['description'] ?? ''));
        $robots = ($seo['robots'] ?? 'index') === 'noindex' ? 'noindex,follow' : 'index,follow';
        $socialImage = trim((string)($seo['socialImage'] ?? ''));
        $metadata = '<title>' . self::escape($title) . '</title>' .
            ($description !== '' ? '<meta name="description" content="' . self::escape($description) . '">' : '') .
            '<meta name="robots" content="' . $robots . '">' .
            ($socialImage !== '' ? '<meta property="og:image" content="' . self::escape($socialImage) . '">' : '') .
            '<meta property="og:title" content="' . self::escape($title) . '">' .
            ($description !== '' ? '<meta property="og:description" content="' . self::escape($description) . '">' : '');
        $contentSecurityPolicy = '<meta http-equiv="Content-Security-Policy" content="default-src &#39;none&#39;; script-src &#39;none&#39;; connect-src &#39;none&#39;; style-src &#39;self&#39; &#39;unsafe-inline&#39;; img-src &#39;self&#39; https: data:; font-src &#39;self&#39; data:; object-src &#39;none&#39;; base-uri &#39;none&#39;; form-action &#39;none&#39;">';
        $bodyAttributes = self::renderAttributes($root->attributes ?? null, $root->style ?? null, $assetPaths);
        $content = '';
        foreach (($root->components ?? []) as $component) $content .= self::renderComponent($component, 0, $assetPaths);
        return '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">' . $contentSecurityPolicy . $metadata .
            '<link rel="stylesheet" href="' . $stylesheetPath . '"></head><body' . $bodyAttributes . '>' . $content . '</body></html>';
    }

    private static function renderComponent(mixed $component, int $depth, array $assetPaths = []): string {
        if (!$component instanceof \stdClass || $depth > 35) throw new Problem(422, 'Невозможно экспортировать дерево компонентов.');
        $type = is_string($component->type ?? null) ? $component->type : '';
        if (in_array($type, ['head'], true)) return '';
        if ($type === 'textnode') return self::escape(is_string($component->content ?? null) ? $component->content : '');
        $tag = is_string($component->tagName ?? null) ? strtolower($component->tagName) : match ($type) {
            'image' => 'img', 'link', 'link-block' => 'a', 'wrapper' => 'div', default => 'div'
        };
        if (!in_array($tag, self::TAGS, true)) throw new Problem(422, 'Статический экспорт встретил неподдерживаемый HTML-тег.');
        $attributes = self::renderAttributes($component->attributes ?? null, $component->style ?? null, $assetPaths);
        if (in_array($tag, self::VOID_TAGS, true)) return '<' . $tag . $attributes . '>';
        $content = is_string($component->content ?? null) && $component->content !== ''
            ? self::renderRichText($component->content)
            : '';
        if ($content === '') foreach (($component->components ?? []) as $child) $content .= self::renderComponent($child, $depth + 1, $assetPaths);
        return '<' . $tag . $attributes . '>' . $content . '</' . $tag . '>';
    }

    private static function renderAttributes(mixed $attributes, mixed $styles, array $assetPaths = []): string {
        $output = '';
        $targetBlank = $attributes instanceof \stdClass && ($attributes->target ?? null) === '_blank';
        $hasRel = false;
        if ($attributes instanceof \stdClass) foreach (get_object_vars($attributes) as $name => $value) {
            if (!in_array($name, self::ATTRIBUTES, true) || !is_string($value) || $value === '' || mb_strlen($value, 'UTF-8') > 2048 || preg_match('/[\x00-\x1F\x7F]/', $value)) continue;
            if ($name === 'id' && !preg_match('/^[A-Za-z][A-Za-z0-9_:-]{0,127}$/D', $value)) continue;
            if ($name === 'class' && !preg_match('/^[A-Za-z0-9_-]+(?:\s+[A-Za-z0-9_-]+)*$/D', $value)) continue;
            if ($name === 'target' && !in_array($value, ['_blank','_self'], true)) continue;
            if ($name === 'rel') {
                if (!preg_match('/^(?:noopener|noreferrer|nofollow|ugc|sponsored)(?:\s+(?:noopener|noreferrer|nofollow|ugc|sponsored))*$/D', $value)) continue;
                if ($targetBlank) $value = trim($value . ' noopener noreferrer');
                $hasRel = true;
            }
            if (($name === 'href' || $name === 'src') && $name === 'src' && isset($assetPaths[$value])) $value = $assetPaths[$value];
            elseif (($name === 'href' || $name === 'src') && !($name === 'src' && IconCatalogue::permits($value)) && !preg_match('~^(?:https://[^\s<>"\x00-\x20]+|#[A-Za-z0-9_-]*|mailto:[A-Za-z0-9@._+-]+|tel:[+0-9 -]+)$~D', $value)) continue;
            if (str_starts_with($name, 'aria-') && mb_strlen($value, 'UTF-8') > 256) continue;
            $output .= ' ' . $name . '="' . self::escape($value) . '"';
        }
        if ($targetBlank && !$hasRel) $output .= ' rel="noopener noreferrer"';
        $declarations = self::declarations($styles);
        if ($declarations) $output .= ' style="' . self::escape(implode(';', $declarations)) . '"';
        return $output;
    }

    private static function renderRichText(string $html): string {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<!doctype html><html><head><meta charset="UTF-8"></head><body><div id="juzimder-export-root">' . $html . '</div></body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) return self::escape($html);
        $root = $dom->getElementById('juzimder-export-root');
        if (!$root) return self::escape($html);
        $render = static function (\DOMNode $node) use (&$render): string {
            if ($node instanceof \DOMText) return self::escape($node->nodeValue ?? '');
            if (!$node instanceof \DOMElement) return '';
            $tag = strtolower($node->tagName);
            if (!in_array($tag, self::RICH_TEXT_TAGS, true)) return '';
            $attributes = '';
            if ($node->hasAttribute('class')) {
                $class = $node->getAttribute('class');
                if (preg_match('/^[A-Za-z0-9_-]+(?:\s+[A-Za-z0-9_-]+)*$/D', $class)) $attributes = ' class="' . self::escape($class) . '"';
            }
            if ($node->hasAttribute('style')) {
                $style = [];
                foreach (explode(';', $node->getAttribute('style')) as $declaration) {
                    $separator = strpos($declaration, ':');
                    if ($separator === false) continue;
                    $style[trim(substr($declaration, 0, $separator))] = trim(substr($declaration, $separator + 1));
                }
                $declarations = self::declarations($style);
                if ($declarations) $attributes .= ' style="' . self::escape(implode(';', $declarations)) . '"';
            }
            if ($tag === 'br') return '<br>';
            $children = '';
            foreach ($node->childNodes as $child) $children .= $render($child);
            return '<' . $tag . $attributes . '>' . $children . '</' . $tag . '>';
        };
        $output = '';
        foreach ($root->childNodes as $child) $output .= $render($child);
        return $output;
    }

    private static function stylesheet(mixed $rules): string {
        if (!is_array($rules) || !array_is_list($rules)) throw new Problem(422, 'Невозможно экспортировать список стилей.');
        $css = "*,*::before,*::after{box-sizing:border-box}html{font-family:Arial,sans-serif}body{margin:0}img{display:block;max-width:100%;height:auto}\n";
        foreach ($rules as $rule) {
            if (!$rule instanceof \stdClass || !($rule->style ?? null) instanceof \stdClass || !is_array($rule->selectors ?? null) || !array_is_list($rule->selectors)) continue;
            $selectors = [];
            foreach ($rule->selectors as $selector) {
                if ($selector instanceof \stdClass) $selector = $selector->name ?? null;
                if (!is_string($selector) || !preg_match('/^(?:#[A-Za-z][A-Za-z0-9_:-]{0,127}|\.[A-Za-z0-9_-]{1,128})(?:\s+(?:#[A-Za-z][A-Za-z0-9_:-]{0,127}|\.[A-Za-z0-9_-]{1,128}))*$/D', $selector)) continue;
                $selectors[] = $selector;
            }
            $declarations = self::declarations($rule->style);
            if (!$selectors || !$declarations) continue;
            $media = $rule->mediaText ?? null;
            if ($media !== null && (!is_string($media) || !preg_match('/^\(max-width:\s*\d{1,4}(?:\.\d{1,2})?(?:px|em|rem)\)$/D', $media))) throw new Problem(422, 'Статический экспорт встретил неподдерживаемое медиавыражение.');
            $block = implode(',', array_unique($selectors)) . '{' . implode(';', $declarations) . '}';
            $css .= $media === null || $media === '' ? $block . "\n" : '@media ' . $media . "{" . $block . "}\n";
        }
        return $css;
    }

    /** @return list<string> */
    private static function declarations(mixed $style): array {
        if ($style instanceof \stdClass) $style = get_object_vars($style);
        if (!is_array($style)) return [];
        $declarations = [];
        foreach ($style as $property => $value) {
            if (!is_string($property) || !in_array($property, self::STYLE_PROPERTIES, true) || !is_string($value) || $value === '' || mb_strlen($value, 'UTF-8') > 512) continue;
            if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F;{}<>\\"\']|\/\*|\*\//', $value) || preg_match('/(?:url|expression)\s*\(|@import|javascript\s*:/i', $value)) continue;
            $declarations[] = $property . ':' . $value;
        }
        return $declarations;
    }

    private static function escape(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
