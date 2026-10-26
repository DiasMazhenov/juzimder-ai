<?php
declare(strict_types=1);
namespace Juzimder;
require_once __DIR__ . '/ZeroBlockRenderer.php';

final class StaticSiteExporter {
    private const TAGS = ['div','section','header','footer','nav','main','article','aside','h1','h2','h3','h4','p','span','a','img','button','ul','ol','li','strong','em','b','i','br','hr','label','form','input','textarea','select','option','details','summary','figure','figcaption','time'];
    private const VOID_TAGS = ['img','br','hr','input'];
    private const ATTRIBUTES = ['id','class','href','src','alt','title','rel','target','role','aria-label','aria-hidden','aria-describedby','aria-labelledby','aria-controls','aria-selected','type','name','value','placeholder','rows','cols','multiple','required','checked','selected','disabled','for','min','max','step','minlength','maxlength','pattern','autocomplete','datetime','open'];
    private const STYLE_PROPERTIES = [
        'display','flex-direction','justify-content','align-items','flex-wrap','align-self','grid-template-columns','gap','flex','scroll-snap-type','scroll-snap-align','scrollbar-width',
        'width','height','min-width','max-width','min-height','max-height','padding','padding-top','padding-right','padding-bottom','padding-left',
        'background-image','background-size','background-position','background-repeat','position','top','right','bottom','left','justify-self','overflow','z-index','margin','margin-top','margin-right','margin-bottom','margin-left','color','background-color','font-family','font-size','font-weight',
        'font-style','text-transform','list-style-type','box-shadow','row-gap','column-gap','overflow-x','overflow-y','background-position-x','background-position-y','background-attachment','background-origin','background-clip','text-decoration-line','text-decoration-thickness','text-decoration-style','text-decoration-color','line-height','letter-spacing','text-align','text-decoration','border','border-top','border-bottom','border-radius','object-fit','aspect-ratio','opacity','cursor','filter',
        '--juzimder-color-ink','--juzimder-color-forest','--juzimder-color-canvas','--juzimder-color-paper','--juzimder-color-body','--juzimder-color-muted','--juzimder-color-line','--juzimder-color-accent',
        '--juzimder-font-family','--juzimder-font-heading','--juzimder-font-body','--juzimder-font-weight-body','--juzimder-font-weight-heading',
        '--juzimder-font-size-base','--juzimder-font-size-h1','--juzimder-font-size-h2','--juzimder-font-size-h3','--juzimder-font-size-h4','--juzimder-font-size-h5','--juzimder-font-size-h6',
        '--juzimder-line-height-body','--juzimder-line-height-heading','--juzimder-letter-spacing','--juzimder-radius'
    ];
    private const EXPANDED_STYLE_PROPERTIES = ['border-top-left-radius','border-top-right-radius','border-bottom-right-radius','border-bottom-left-radius','border-top-width','border-right-width','border-bottom-width','border-left-width','border-top-style','border-right-style','border-bottom-style','border-left-style','border-top-color','border-right-color','border-bottom-color','border-left-color','border-image-source','border-image-slice','border-image-width','border-image-outset','border-image-repeat'];
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
        $css .= "\n" . ($settings['customCss'] ?? '');
        $pages = $document->data->pages;
        $home = null;
        foreach ($pages as $page) if (($settings['pages'][(string)$page->id]['isHome'] ?? false) === true) { $home = (string)$page->id; break; }
        if ($home === null && isset($pages[0])) $home = (string)$pages[0]->id;
        $sharedComponents = [];
        foreach ($pages as $sourcePage) {
            $sourceRoot = $sourcePage->frames[0]->component ?? null;
            if (!$sourceRoot instanceof \stdClass) continue;
            foreach (($sourceRoot->components ?? []) as $component) {
                $id = $component instanceof \stdClass ? ($component->attributes->id ?? null) : null;
                if (is_string($id) && $id !== '') $sharedComponents[$id] = $component;
            }
        }
        $sharedSettings = $settings['sharedSections'] ?? ProjectSettings::sharedSectionDefaults();
        $headerId = $sharedSettings['headerComponentId'] ?? '';
        $footerId = $sharedSettings['footerComponentId'] ?? '';
        $sharedHeader = $headerId !== '' ? ($sharedComponents[$headerId] ?? null) : null;
        $sharedFooter = $footerId !== '' ? ($sharedComponents[$footerId] ?? null) : null;

        foreach ($assetPaths as $assetId=>$assetPath) $css = str_replace('/api/projects/'.$projectId.'/assets/'.$assetId.'/file', '../'.$assetPath, $css);
        $files = ['assets/site.css' => $css];
        $allForms = [];
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
            $pageShared = $sharedSettings['pages'][$pageId] ?? ['header'=>true,'footer'=>true];
            $pageHeader = ($pageShared['header'] ?? true) ? $sharedHeader : null;
            $pageFooter = ($pageShared['footer'] ?? true) ? $sharedFooter : null;
            $pageForms = [];
            $formComponents = [];
            self::collectForms($root, $pageId, $path, [], $pageForms, $formComponents);
            if ($pageHeader instanceof \stdClass) self::collectForms($pageHeader, $pageId, $path, ['shared-header'], $pageForms, $formComponents);
            if ($pageFooter instanceof \stdClass) self::collectForms($pageFooter, $pageId, $path, ['shared-footer'], $pageForms, $formComponents);
            $relativeAssetPaths = [];
            foreach ($assetPaths as $assetId=>$assetPath) $relativeAssetPaths['/api/projects/' . $projectId . '/assets/' . $assetId . '/file'] = ($isHome ? '' : '../') . $assetPath;
            $html = self::pageHtml($page, $root, $route['seo'] ?? [], $isHome ? 'assets/site.css' : '../assets/site.css', $relativeAssetPaths, $formComponents, $pageHeader, $pageFooter, [$headerId, $footerId]);
            foreach ($relativeAssetPaths as $source=>$target) $html = str_replace($source,$target,$html);
            $files[$path] = $html;
            foreach ($pageForms as $formId=>$schema) $allForms[$formId] = $schema;
        }
        $files['404.html'] = self::notFoundHtml($settings['notFound'] ?? ProjectSettings::notFoundDefaults());
        $files['assets/site.css'] .= '.jz-not-found{min-height:100vh;box-sizing:border-box;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:32px;text-align:center;background-color:var(--jz-not-found-bg,var(--juzimder-color-canvas,#ecf4ef));color:var(--jz-not-found-fg,var(--juzimder-color-ink,#183e31))}.jz-not-found h1{font-size:clamp(2rem,6vw,4rem);margin:0 0 12px}.jz-not-found p{max-width:36rem;margin:0 0 24px}.jz-not-found a{display:inline-flex;align-items:center;justify-content:center;padding:12px 20px;border-radius:var(--juzimder-radius,8px);background:var(--jz-not-found-button-bg,var(--juzimder-color-accent,#b0dcc1));color:var(--jz-not-found-button-fg,var(--juzimder-color-ink,#183e31));text-decoration:none;font-weight:600}';
        if (!empty($allForms)) {
            $files['assets/site.css'] .= '.jz-form-trap{position:absolute!important;width:1px!important;height:1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;white-space:nowrap!important}.jz-form-trap input{position:absolute!important;left:-10000px!important}';
            $files['forms.json'] = json_encode(['version'=>1,'forms'=>$allForms], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $files['README.txt'] = "Juzimder AI static site export\n\nOpen index.html locally or upload the extracted files to a static web host.\nSubpages are stored in their configured route folders; shared styles are in assets/site.css. A project-level 404.html page is included for web hosts configured to use a custom not-found document.\nReferenced project images are copied into media/.\n\nCustom Code, when present, runs only inside an opaque sandboxed frame. Cross-origin requests are restricted by CSP; editor access, cookies and server-side execution are unavailable. Form inbox delivery works only on a Juzimder AI published site; a standalone ZIP has no submission backend. AI, analytics, domains and online publication are not included. No public deployment was performed.\n";
        return $files;
    }

    private static function notFoundHtml(array $config): string {
        $config = ProjectSettings::validateNotFoundSettings($config, ProjectSettings::notFoundDefaults());
        $title = self::escape($config['title']); $message = self::escape($config['message']); $homeLabel = self::escape($config['homeLabel']);
        $colorProperties = ['backgroundColor'=>'--jz-not-found-bg','textColor'=>'--jz-not-found-fg','buttonColor'=>'--jz-not-found-button-bg','buttonTextColor'=>'--jz-not-found-button-fg'];
        $inlineColors = '';
        foreach ($colorProperties as $key=>$property) if (($config[$key] ?? '') !== '') $inlineColors .= $property.':'.self::escape($config[$key]).';';
        return '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta http-equiv="Content-Security-Policy" content="default-src &#39;none&#39;; script-src &#39;none&#39;; connect-src &#39;none&#39;; style-src &#39;self&#39; &#39;unsafe-inline&#39;; img-src &#39;self&#39; https: data:; font-src &#39;self&#39; data:; object-src &#39;none&#39;; base-uri &#39;none&#39;; form-action &#39;none&#39;"><title>'.$title.'</title><meta name="robots" content="noindex,follow"><link rel="stylesheet" href="/assets/site.css"></head><body><main class="jz-not-found"'.($inlineColors !== '' ? ' style="'.$inlineColors.'"' : '').'><h1>'.$title.'</h1><p>'.$message.'</p><a href="/">'.$homeLabel.'</a></main></body></html>';
    }

    public static function inlineBackgroundStyles(string $css,array $assets,string $projectId): string {
        foreach($assets as $assetId=>$asset){$extension=['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'][$asset['mime']??'']??null;if($extension===null)continue;$css=str_replace('../media/'.$projectId.'/'.$assetId.'.'.$extension,'data:'.$asset['mime'].';base64,'.base64_encode($asset['content']),$css);}
        return $css;
    }

    public static function inlineAssets(string $html, array $assets, string $projectId, bool $isHome): string {
        foreach ($assets as $assetId=>$asset) {
            $extension = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'][$asset['mime'] ?? ''] ?? null;
            if ($extension === null || !is_string($asset['content'] ?? null)) continue;
            $path = ($isHome ? '' : '../') . 'media/' . $projectId . '/' . $assetId . '.' . $extension;
            $html = str_replace('url(&quot;'.$path.'&quot;)', 'url(&quot;data:'.$asset['mime'].';base64,'.base64_encode($asset['content']).'&quot;)', $html);
            $html = str_replace('url("'.$path.'")', 'url("data:'.$asset['mime'].';base64,'.base64_encode($asset['content']).'")', $html);
            $html = str_replace('src="' . self::escape($path) . '"', 'src="data:' . $asset['mime'] . ';base64,' . base64_encode($asset['content']) . '"', $html);
            $html = str_replace('srcset="' . self::escape($path) . '"', 'srcset="data:' . $asset['mime'] . ';base64,' . base64_encode($asset['content']) . '"', $html);
        }
        return $html;
    }

    public static function runtimeNonce(string $html): ?string {
        return preg_match('~<meta\s+name="jz-custom-code-nonce"\s+content="([a-f0-9]{32})"~i', $html, $match) ? $match[1] : null;
    }

    public static function pageSecurityPolicy(string $html): string {
        $nonce = self::runtimeNonce($html);
        $scripts = $nonce === null ? "script-src 'none'" : "script-src 'nonce-" . $nonce . "'";
        $frameSources = $nonce === null ? [] : ["'self'", 'data:'];
        $hasForms = preg_match('~<form\b[^>]*\bdata-jz-form-id="[a-f0-9]{24}"~i', $html) === 1;
        if (self::hasTrustedVideoFrame($html)) $frameSources = array_merge($frameSources, ['https://www.youtube-nocookie.com', 'https://player.vimeo.com']);
        $frames = $frameSources ? 'frame-src ' . implode(' ', $frameSources) : "frame-src 'none'";
        return "default-src 'none'; " . $scripts . "; connect-src " . ($hasForms ? "'self'" : "'none'") . "; style-src 'self' 'unsafe-inline'; img-src 'self' https: data:; font-src 'self' data:; " . $frames . "; object-src 'none'; base-uri 'none'; form-action " . ($hasForms ? "'self'" : "'none'") . "; frame-ancestors 'none'";
    }

    private static function hasTrustedVideoFrame(string $html): bool {
        return preg_match('~<iframe\b(?=[^>]*\bsrc="https://(?:www\.youtube-nocookie\.com/embed/[A-Za-z0-9_-]{11}|player\.vimeo\.com/video/[0-9]{1,12})")[^>]*>~i', $html) === 1;
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

    private static function pageHtml(\stdClass $page, \stdClass $root, array $seo, string $stylesheetPath, array $assetPaths = [], array $formComponents = [], ?\stdClass $sharedHeader = null, ?\stdClass $sharedFooter = null, array $sharedIds = []): string {
        $title = trim((string)($seo['title'] ?? '')) ?: trim((string)($page->name ?? '')) ?: 'Страница';
        $description = trim((string)($seo['description'] ?? ''));
        $robots = ($seo['robots'] ?? 'index') === 'noindex' ? 'noindex,follow' : 'index,follow';
        $socialImage = trim((string)($seo['socialImage'] ?? ''));
        $socialTitle = trim((string)($seo['socialTitle'] ?? '')) ?: $title;
        $socialDescription = trim((string)($seo['socialDescription'] ?? '')) ?: $description;
        $metadata = '<title>' . self::escape($title) . '</title>' .
            ($description !== '' ? '<meta name="description" content="' . self::escape($description) . '">' : '') .
            '<meta name="robots" content="' . $robots . '">' .
            ($socialImage !== '' ? '<meta property="og:image" content="' . self::escape($socialImage) . '">' : '') .
            '<meta property="og:title" content="' . self::escape($socialTitle) . '">' .
            ($socialDescription !== '' ? '<meta property="og:description" content="' . self::escape($socialDescription) . '">' : '');
        $metadata .= '<meta property="og:type" content="website"><meta name="twitter:card" content="' . ($socialImage !== '' ? 'summary_large_image' : 'summary') . '">' . '<meta name="twitter:title" content="' . self::escape($socialTitle) . '">' . ($socialDescription !== '' ? '<meta name="twitter:description" content="' . self::escape($socialDescription) . '">' : '') . ($socialImage !== '' ? '<meta name="twitter:image" content="' . self::escape($socialImage) . '">' : '');
        $hasForms = $formComponents !== [];
        $hasRuntime = self::containsCustomCode($root) || ($sharedHeader instanceof \stdClass && self::containsCustomCode($sharedHeader)) || ($sharedFooter instanceof \stdClass && self::containsCustomCode($sharedFooter)) || $hasForms;
        $nonce = $hasRuntime ? bin2hex(random_bytes(16)) : null;
        $nonceMarker = $nonce === null ? '' : '<meta name="jz-custom-code-nonce" content="' . $nonce . '">';
        $bodyAttributes = self::renderAttributes(self::componentAttributes($root), $root->style ?? null, $assetPaths);
        $content = '';
        if ($sharedHeader instanceof \stdClass) $content .= self::renderComponent($sharedHeader, 0, $assetPaths, $nonce, $formComponents);
        foreach (($root->components ?? []) as $component) {
            $componentId = $component instanceof \stdClass ? ($component->attributes->id ?? '') : '';
            if (is_string($componentId) && $componentId !== '' && in_array($componentId, $sharedIds, true)) continue;
            $content .= self::renderComponent($component, 0, $assetPaths, $nonce, $formComponents);
        }
        if ($sharedFooter instanceof \stdClass) $content .= self::renderComponent($sharedFooter, 0, $assetPaths, $nonce, $formComponents);
        $frameSources = $nonce === null ? [] : ["&#39;self&#39;", 'data:'];
        if (self::hasTrustedVideoFrame($content)) $frameSources = array_merge($frameSources, ['https://www.youtube-nocookie.com', 'https://player.vimeo.com']);
        $frames = $frameSources ? 'frame-src ' . implode(' ', $frameSources) : 'frame-src &#39;none&#39;';
        $connect = $hasForms ? '&#39;self&#39;' : '&#39;none&#39;';
        $formAction = $hasForms ? '&#39;self&#39;' : '&#39;none&#39;';
        $contentSecurityPolicy = '<meta http-equiv="Content-Security-Policy" content="default-src &#39;none&#39;; script-src ' . ($nonce === null ? '&#39;none&#39;' : '&#39;nonce-' . $nonce . '&#39;') . '; connect-src ' . $connect . '; style-src &#39;self&#39; &#39;unsafe-inline&#39;; img-src &#39;self&#39; https: data:; font-src &#39;self&#39; data:; ' . $frames . '; object-src &#39;none&#39;; base-uri &#39;none&#39;; form-action ' . $formAction . '">';
        $formRuntime = $hasForms ? '<script nonce="' . $nonce . '">' . self::formRuntime() . '</script>' : '';
        return '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">' . $contentSecurityPolicy . $nonceMarker . $metadata .
            '<link rel="stylesheet" href="' . $stylesheetPath . '"></head><body' . $bodyAttributes . '>' . $content . $formRuntime . '</body></html>';
    }

    private static function collectForms(\stdClass $component, string $pageId, string $pagePath, array $path, array &$schemas, array &$ids): void {
        if (($component->type ?? null) === 'form') {
            $formId = substr(hash('sha256', $pageId . ':' . implode('.', $path)), 0, 24);
            $fields = [];
            $walk = static function(\stdClass $node) use (&$walk, &$fields): void {
                $tag = strtolower((string)($node->tagName ?? ''));
                if (in_array($tag, ['input','textarea','select'], true)) {
                    $attrs = $node->attributes ?? new \stdClass();
                    $name = $attrs->name ?? null;
                    $type = $tag === 'textarea' ? 'textarea' : ($tag === 'select' ? 'select' : strtolower((string)($attrs->type ?? 'text')));
                    if (is_string($name) && preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/D', $name) && in_array($type, ['text','email','tel','number','textarea','select'], true) && !isset($fields[$name])) {
                        $fields[$name] = ['name'=>$name,'type'=>$type,'required'=>($attrs->required ?? false) === true || ($attrs->required ?? null) === 'required'];
                    }
                }
                foreach (($node->components ?? []) as $child) if ($child instanceof \stdClass) $walk($child);
            };
            $walk($component);
            if ($fields) {
                $schemas[$formId] = ['pagePath'=>$pagePath,'fields'=>array_values($fields)];
                $ids[spl_object_id($component)] = $formId;
            }
        }
        foreach (($component->components ?? []) as $index=>$child) if ($child instanceof \stdClass) self::collectForms($child, $pageId, $pagePath, [...$path,$index], $schemas, $ids);
    }

    private static function formRuntime(): string {
        return "document.addEventListener('submit',async event=>{const form=event.target;if(!(form instanceof HTMLFormElement)||!form.dataset.jzFormId)return;event.preventDefault();const status=form.querySelector('[data-jz-form-status]');const button=form.querySelector('[type=submit]');if(button)button.disabled=true;if(status)status.textContent='Отправляем…';try{const values=Object.fromEntries(new FormData(form).entries());const response=await fetch('/api/public/forms',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({formId:form.dataset.jzFormId,values})});const result=await response.json();if(!response.ok)throw new Error(result.error||'Не удалось отправить заявку.');form.reset();if(status)status.textContent='Спасибо! Заявка отправлена.';}catch(error){if(status)status.textContent=error.message||'Не удалось отправить заявку.';}finally{if(button)button.disabled=false;}});";
    }

    private static function containsCustomCode(\stdClass $component): bool {
        if (in_array(($component->type ?? null), ['custom-code','countdown'], true)) return true;
        if (($component->type ?? null) === 'zero-block' && self::zeroContainsCode($component->zeroData->elements ?? [])) return true;
        foreach (($component->components ?? []) as $child) if ($child instanceof \stdClass && self::containsCustomCode($child)) return true;
        return false;
    }

    private static function zeroContainsCode(array $elements): bool {
        foreach ($elements as $element) {
            if (($element->type ?? null) === 'html') return true;
            if (($element->type ?? null) === 'group' && self::zeroContainsCode($element->children ?? [])) return true;
        }
        return false;
    }

    private static function renderComponent(mixed $component, int $depth, array $assetPaths = [], ?string $nonce = null, array $formComponents = []): string {
        if (!$component instanceof \stdClass || $depth > 35) throw new Problem(422, 'Невозможно экспортировать дерево компонентов.');
        $type = is_string($component->type ?? null) ? $component->type : '';
        if ($type === 'zero-block') return ZeroBlockRenderer::render($component->zeroData, $component->attributes->id, $nonce);
        if (in_array($type, ['head'], true)) return '';
        if ($type === 'textnode') return self::escape(is_string($component->content ?? null) ? $component->content : '');
        if (in_array($type, ['custom-code','countdown'], true)) {
            if ($nonce === null) throw new Problem(422, 'Custom Code требует изолированного renderer nonce.');
            $payload = $type === 'custom-code'
                ? CustomCode::decode($component->content ?? null)
                : CustomCode::countdownPayload($component->attributes->{'data-countdown-end'} ?? null);
            $sandbox = CustomCode::sandboxDocument($payload, $nonce);
            $attributes = self::renderAttributes(self::componentAttributes($component), $component->style ?? null, $assetPaths);
            $title = is_string($component->name ?? null) && $component->name !== '' ? $component->name : ($type === 'countdown' ? 'Обратный отсчёт' : 'Пользовательский код в изолированной песочнице');
            return '<div' . $attributes . '><iframe title="' . self::escape($title) . '" sandbox="allow-scripts" referrerpolicy="no-referrer" style="display:block;width:100%;height:360px;border:0" srcdoc="' . self::escape($sandbox) . '"></iframe></div>';
        }
        $tag = is_string($component->tagName ?? null) ? strtolower($component->tagName) : match ($type) {
            'form'=>'form','input','checkbox','radio'=>'input','textarea'=>'textarea','select'=>'select','option'=>'option','button'=>'button','label'=>'label', 'image' => 'img', 'link', 'link-block' => 'a', 'wrapper' => 'div', default => 'div'
        };
        if (!in_array($tag, self::TAGS, true)) throw new Problem(422, 'Статический экспорт встретил неподдерживаемый HTML-тег.');
        $attributes = self::renderAttributes(self::componentAttributes($component), $component->style ?? null, $assetPaths);
        if ($type === 'form' && isset($formComponents[spl_object_id($component)])) {
            $attributes = rtrim($attributes) . ' data-jz-form-id="' . $formComponents[spl_object_id($component)] . '"';
        }
        if (in_array($tag, self::VOID_TAGS, true)) return '<' . $tag . $attributes . '>';
        $content = is_string($component->content ?? null) && $component->content !== ''
            ? self::renderRichText($component->content)
            : '';
        if ($content === '') foreach (($component->components ?? []) as $child) $content .= self::renderComponent($child, $depth + 1, $assetPaths, $nonce, $formComponents);
        if ($type === 'form' && isset($formComponents[spl_object_id($component)])) {
            $content = '<label class="jz-form-trap" aria-hidden="true">Оставьте поле пустым<input type="text" name="website" tabindex="-1" autocomplete="off"></label>' . $content . '<p data-jz-form-status role="status" aria-live="polite"></p>';
        }
        return '<' . $tag . $attributes . '>' . $content . '</' . $tag . '>';
    }

    private static function componentAttributes(\stdClass $component): \stdClass {
        $nativeAttributes = clone ($component->attributes ?? new \stdClass());
        $classes = preg_split('/\s+/', (string)($nativeAttributes->class ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach (($component->classes ?? []) as $class) {
            if ($class instanceof \stdClass) $class = $class->name ?? null;
            if (is_string($class) && preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $class)) $classes[] = $class;
        }
        if ($classes) $nativeAttributes->class = implode(' ', array_unique($classes));
        return $nativeAttributes;
    }

    private static function renderAttributes(mixed $attributes, mixed $styles, array $assetPaths = []): string {
        $output = '';
        $targetBlank = $attributes instanceof \stdClass && ($attributes->target ?? null) === '_blank';
        $hasRel = false;
        if ($attributes instanceof \stdClass) foreach (get_object_vars($attributes) as $name => $value) {
            if(in_array($name,['multiple','required','checked','selected','disabled','open'],true)){if($value===true||$value===''||$value===$name)$output.=' '.$name;continue;}
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
            $selectors = self::ruleSelectors($rule);
            $declarations = self::declarations($rule->style);
            if (!$selectors || !$declarations) continue;
            $media = $rule->mediaText ?? null;
            if ($media !== null && (!is_string($media) || !preg_match('/^\(max-width:\s*\d{1,4}(?:\.\d{1,2})?(?:px|em|rem)\)$/D', $media))) throw new Problem(422, 'Статический экспорт встретил неподдерживаемое медиавыражение.');
            $block = implode(',', array_unique($selectors)) . '{' . implode(';', $declarations) . '}';
            $css .= $media === null || $media === '' ? $block . "\n" : '@media ' . $media . "{" . $block . "}\n";
        }
        return $css;
    }

    /** Native Core selectors are concatenated; selectorsAdd is an additional selector list. */
    private static function ruleSelectors(\stdClass $rule): array {
        $native = '';
        foreach ($rule->selectors ?? [] as $selector) {
            if ($selector instanceof \stdClass) {
                $name = $selector->name ?? null;
                if (!is_string($name) || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $name)) return [];
                $selector = (($selector->type ?? 1) === 2 ? '#' : '.') . $name;
            } elseif (is_string($selector) && preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $selector)) $selector = '.' . $selector;
            if (!is_string($selector)) return [];
            $native .= $selector;
        }
        $state = $rule->state ?? '';
        if ($state !== '') {
            if (!in_array($state, ['hover','focus','focus-visible','focus-within','active','disabled','visited','checked'], true)) return [];
            $native .= ':' . $state;
        }
        $parts = $native !== '' ? [$native] : [];
        if (is_string($rule->selectorsAdd ?? null) && $rule->selectorsAdd !== '') $parts = array_merge($parts, explode(',', $rule->selectorsAdd));
        $safe = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (strlen($part)>2048 || !preg_match('/^(?:[A-Za-z][A-Za-z0-9-]*|[.#][A-Za-z0-9_-]+)(?:[.#][A-Za-z0-9_-]+)*(?::(?:hover|focus|focus-visible|focus-within|active|disabled|visited|checked))?(?:\s+(?:[A-Za-z][A-Za-z0-9-]*|[.#][A-Za-z0-9_-]+)(?:[.#][A-Za-z0-9_-]+)*(?::(?:hover|focus|focus-visible|focus-within|active|disabled|visited|checked))?)*$/D', $part)) continue;
            $safe[] = $part;
        }
        return array_values(array_unique($safe));
    }

    /** @return list<string> */
    private static function declarations(mixed $style): array {
        if ($style instanceof \stdClass) $style = get_object_vars($style);
        if (!is_array($style)) return [];
        $declarations = [];
        foreach ($style as $property => $value) {
            if ($property === 'background-image') { if ($value !== '' && BackgroundImage::valid($value)) $declarations[] = $property . ':' . $value; continue; }
            if (!is_string($property) || (!in_array($property, self::STYLE_PROPERTIES, true) && !in_array($property, self::EXPANDED_STYLE_PROPERTIES, true)) || !is_string($value) || $value === '' || mb_strlen($value, 'UTF-8') > 512) continue;
            if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F;{}<>\\"\']|\/\*|\*\//', $value) || preg_match('/(?:url|expression)\s*\(|@import|javascript\s*:/i', $value)) continue;
            $declarations[] = $property . ':' . $value;
        }
        return $declarations;
    }

    private static function escape(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
