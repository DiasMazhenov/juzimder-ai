<?php
declare(strict_types=1);
namespace Juzimder;

require_once __DIR__.'/OpenDesignTemplateSource.php';
require_once __DIR__.'/OpenDesignComposition.php';

/** Maps clear page intents to reviewed, external Open Design layout references. */
final class OpenDesignTemplateGuidance {
    private const REFERENCES = [
        'saas-landing' => [
            'priority' => 10,
            'path' => 'design-templates/saas-landing/example.html',
            'terms' => [
                'saas', 'crm', 'программный продукт', 'программное обеспечение',
                'облачная платформа', 'облачная система', 'облачный сервис', 'программная платформа',
                'веб приложение', 'онлайн платформа', 'software as a service',
                'мобильное приложение', 'приложение для', 'веб сервис', 'онлайн сервис',
                'сервис для управления', 'сервис для записи', 'система управления',
                'software product', 'cloud platform', 'web application', 'web app',
                'mobile application', 'mobile app', 'web service', 'online service',
            ],
            'guidance' => 'Use the matched product or service landing composition as the primary layout: compact top navigation, a prominent offer Hero, a repeatable card group for supplied product features or concrete service details, then distinct proof and plan areas only where the owner brief supplies that content. For a non-software service, adapt the reference layout without implying an app or software functionality. Keep these zones visually separate instead of turning the page into generic stacked text sections. Include customer proof, metrics, pricing, or testimonials only when the owner supplied those facts. Do not invent a demo, free trial, account creation, sign-up, contact, or form-submission action. If no working action destination is supplied, omit the CTA or link only to an existing matching section.',
        ],
        'blog-post' => [
            'priority' => 30,
            'path' => 'design-templates/blog-post/example.html',
            'terms' => ['блог', 'статья', 'статья в блоге', 'публикация блога', 'полевой дневник', 'полевые заметки', 'полевой журнал', 'field diary', 'field journal', 'blog', 'blog post', 'article', 'essay'],
            'guidance' => 'Use the matched editorial composition: a slim top navigation, a centered readable article column, a clear title and lead, and a focused article body with deliberate rhythm. Keep the title area compact; this is an article header, not a full-screen marketing Hero. The lead is thematic orientation only; keep names, dates, quantities, observations, results, and record transfers in their single canonical body paragraph instead of repeating them in the lead. Use multiple article sections only when supplied material supports distinct topics; a concise body is complete when the brief has only one topic. Use a byline, date, figure, pull quote, or endnote only when supplied material supports it. Do not turn an article into a generic marketing landing page or invent citations and facts. Do not invent subscribe, comment, feedback, signup, contact, or form-submission actions; omit an action unless the owner supplied a real destination and the renderer supports it.',
        ],
        'docs-page' => [
            'priority' => 30,
            'path' => 'design-templates/docs-page/example.html',
            'terms' => [
                'документация', 'api документация', 'руководство', 'инструкция', 'справочник', 'база знаний',
                'памятка', 'памятка посетителям', 'путеводитель', 'справочник по',
                'информационная страница', 'информационный справочник', 'правила для посетителей',
                'каталог архива', 'правила посещения', 'правила пользования', 'инструкция для',
                'visitor guide', 'user guide', 'handbook', 'documentation', 'api reference', 'tutorial',
            ],
            'guidance' => 'Follow the matched documentation shell as the primary composition: a compact top bar and compact document title area above one shared three-column desktop grid. After the title Hero, use one div.ai-doc-layout with exactly three direct semantic sibling children in this order: nav for grouped section navigation, article for the guide, and aside for the right-side table of contents. Keep both rails vertically arranged beside the article; do not turn them into full-width sections before or after it. Use CSS Grid on ai-doc-layout with supported simple columns of 220px 1fr 180px, a 24–36px gap, and top alignment. Keep the article readable in its middle column, with plain headings and no repeated landing-page badges or marketing cards. Include the required single section.ai-hero for the document title and lead, but style it as a compact document header, not a full-height marketing Hero. Keep the lead to one short orientation sentence with no operational details or numbers; do not repeat it in the article. Do not add decorative or stock photography to a documentation header unless the owner explicitly supplies a relevant image subject. If the owner brief says a specific article section contains photos or requests photos of a subject, place one relevant image-slot marker inside that exact article section, with a concise English data-image-query; never move archive/gallery imagery into the Hero. The document header normally has no action; add one only when the brief supplies a real, supported destination. Never invent sign-up, contact, feedback, form-submission, or other conversion actions. Below 960px, collapse the three regions into a single readable column. Use only owner-provided commands, API behavior, prerequisites, and links; never invent integration steps or technical guarantees. Keep each supplied fact, value, warning, and instruction in one canonical article section; do not repeat detailed values or steps in the lead, navigation, or another section. For product instructions, keep every provided step and safety limitation accurate and do not add unconfirmed device specifications.',
        ],
        'pricing-page' => [
            'priority' => 30,
            'path' => 'design-templates/pricing-page/example.html',
            'terms' => ['страница тарифов', 'страница цен', 'страница сравнения тарифов', 'сравнение тарифов', 'сравнения тарифов', 'цены и тарифы', 'тарифный план', 'абонемент', 'абонементы', 'стоимость занятий', 'стоимость услуг', 'разовое занятие', 'прайс', 'цены на занятия', 'pricing page', 'compare plans', 'subscription tiers'],
            'guidance' => 'Follow the matched pricing composition: a concise page heading above a side-by-side group of offer cards, followed by a separate comparison area only when the supplied offers share comparable attributes. Stack offer cards cleanly on narrow screens. Use only plans, prices, included features, and terms explicitly supplied by the owner. Give every plan detail one canonical location: state each supplied price, feature, and term once in its plan card or one comparison row; never repeat exact amounts or plan facts in the Hero, card copy, footnotes, or another section. Do not invent free tiers, prices, limits, billing periods, FAQs, or conversion actions; omit unsupported plan details. This renderer is read-only and has no checkout, order, booking, quote-request, contact, or submission flow: do not add buttons or visitor-directed action copy for these tasks. Omit the Hero action unless the owner supplied a real supported destination; use an internal link only to a meaningful section present in the page.',
        ],
        'waitlist-page' => [
            'priority' => 30,
            'path' => 'design-templates/waitlist-page/example.html',
            'terms' => ['лист ожидания', 'страница раннего доступа', 'страница предзапуска', 'waitlist', 'waitlist page', 'coming soon page', 'pre-launch page'],
            'guidance' => 'Follow the matched sparse pre-launch composition: keep the brand and one focused status message in a centered, uncluttered first screen, then add only supplied release context. This static renderer has no working form or submission handler: present the waitlist as project status, and omit email capture, sign-up, request, or submission actions unless an explicitly requested supported form is available. Do not invite visitors to leave a name, join the list, or request a place; do not invent a launch date or availability promise.',
        ],
        'dashboard' => [
            'priority' => 30,
            'path' => 'design-templates/dashboard/example.html',
            'terms' => ['дашборд', 'рабочая панель', 'операционная панель', 'панель координатора', 'панель диспетчера', 'панель аналитики', 'аналитическая панель', 'административная панель', 'панель управления', 'панель смены', 'сводка смены', 'оперативная сводка', 'журнал смены', 'dashboard', 'analytics dashboard', 'admin panel', 'control panel'],
            'guidance' => 'Follow the matched application-shell composition: a persistent vertical navigation rail on the left, a compact top bar, a compact page heading, a row of labeled KPI tiles when the brief contains at least two independent numeric facts, and a separate work-panel area for the supplied records. Render an operational application screen, not a public marketing landing page. Do not create a tall promotional Hero, stock-photo split Hero, marketing CTA, feature pitch, testimonial, or decorative image. The required ai-hero section may be a compact in-app page heading. Do not carry demo numbers, chart values, activity, users, controls, or claims from the reference. Unless the owner supplies an explicitly supported destination and action, make the dashboard read-only: show status as text and omit buttons or controls that imply updating, exporting, submitting, or contacting. On narrow screens, stack the rail, summary tiles, and work panels into a readable single column without page-level horizontal overflow.',
        ],
        'web-prototype' => [
            'priority' => 10,
            'path' => 'design-templates/web-prototype/references/layouts.md',
            'terms' => [
                'портфолио', 'дизайн студия', 'студия дизайна', 'креативное агентство',
                'брендинговое агентство', 'веб дизайн', 'веб дизайнер',
                'ux ui', 'ui ux', 'web design', 'design studio', 'creative agency',
                'digital agency', 'portfolio',
            ],
            'guidance' => 'Use the pinned reference as a small composition vocabulary, not as a list of sections to dump onto the page: choose the single layout pattern best supported by the owner content, then use at most one secondary pattern only when the content supports it. Use a photo only when its subject meaningfully supports the brief.',
        ],
    ];

    /** @return array{id:string,revision:string,sourcePath:string,guidance:string}|null */
    public static function match(string $brief, ?string &$selection = null): ?array {
        $selection = 'no_match';
        $normalized = self::normalize($brief);
        if ($normalized === '') return null;
        $scores = [];
        foreach (self::REFERENCES as $id => $reference) {
            $score = 0;
            foreach ($reference['terms'] as $term) {
                $needle = self::normalize($term);
                if ($needle !== '' && str_contains(' '.$normalized.' ', ' '.$needle.' ')) $score++;
            }
            // The opening words identify the requested page archetype. A case
            // study may mention an app or service, but that should not tie a
            // portfolio brief with a SaaS landing reference.
            if ($id === 'web-prototype' && self::hasPrimaryPortfolioIntent($normalized)) $score += 2;
            if ($id === 'saas-landing' && self::hasContentOnlyPlatformProductIntent($normalized)) $score += 2;
            if ($id === 'pricing-page' && self::hasNaturalPricingIntent($brief)) $score++;
            if ($id === 'dashboard' && self::hasOperationalDashboardIntent($normalized)) $score += 2;
            if ($id === 'docs-page' && self::hasStationLogGuideIntent($normalized)) $score += 2;
            if ($id === 'blog-post' && !self::hasPrimarySoftwareProductIntent($normalized)
                && preg_match('/полев\\p{L}*\\s+(?:дневник|журнал|заметк)\\p{L}*/u', $normalized) === 1
                && !self::hasStationLogGuideIntent($normalized)) $score++;
            if($id==='blog-post'&&!self::hasPrimarySoftwareProductIntent($normalized)&&preg_match('/(?:геолог|ботанич|орнитолог|этнограф)\\p{L}*\\s+дневник\\p{L}*/u',$normalized)===1)$score++;
            if ($score > 0) $scores[$id] = ['priority'=>$reference['priority']??10,'score'=>$score];
        }
        // Use the external landing reference for a clearly described service
        // only when no more specific reviewed archetype matched. Require both
        // a real offer and multiple operational details to avoid guessing a
        // template from a business name alone.
        $serviceLandingFallback = false;
        if (!$scores && self::hasServiceLandingIntent($normalized)) {
            $reference=self::REFERENCES['saas-landing'];
            $scores['saas-landing']=['priority'=>$reference['priority']??10,'score'=>1];
            $serviceLandingFallback = true;
        }
        if (!$scores) return null;
        uksort($scores,static function(string $a,string $b)use($scores):int{
            return ($scores[$b]['priority']<=>$scores[$a]['priority'])?:($scores[$b]['score']<=>$scores[$a]['score']);
        });
        $ranked = array_keys($scores);
        if (isset($ranked[1]) && $scores[$ranked[0]] === $scores[$ranked[1]]) { $selection='ambiguous'; return null; }
        $selection = $serviceLandingFallback ? 'archetype_fallback' : 'matched';
        $id = $ranked[0];
        $reference = self::REFERENCES[$id];
        $variant = $serviceLandingFallback ? 'service' : ($id === 'web-prototype' ? 'project' : 'product');
        $guidance = $reference['guidance'];
        if ($id === 'web-prototype' && self::hasMultipleDatedWorkRecords($normalized)) {
            $variant = 'editorial-log-list';
            $guidance .= ' The brief contains multiple dated works: use the external reference rhythm of a centered Hero followed immediately by one chronological editorial log list. Render each work as an open row with its date in a narrow left metadata rail and its supplied title and description in the main column; a right metadata value is optional and may appear only when the brief supplies it. Separate rows with simple horizontal rules at their top edges. Do not wrap rows in cards, turn the works into a feature-card triplet, or add a pill badge to each entry. Keep each supplied work as one canonical entry. On narrow screens, let each row read as a single vertical unit. Do not add a Hero image unless the owner brief requests one or contains a clearly relevant visual subject. Treat each entry as a factual record; do not infer project supervision, construction or implementation stages, schedules, delivery, or other business operations absent from the owner brief.';
        }
        return [
            'id' => $id,
            'revision' => OpenDesignTemplateSource::REVISION,
            'sourcePath' => $reference['path'],
            'guidance' => $guidance,
            'variant' => $variant,
        ];
    }

    /** Recognize an internal operational product described through its work, not the word "dashboard". */
    private static function hasOperationalDashboardIntent(string $normalized): bool {
        $internal = preg_match('/(?:^| )внутренн\\w*/u', $normalized) === 1;
        $monitoringContext = str_contains($normalized,'центр наблюден')
            || str_contains($normalized,'мониторинг')
            || str_contains($normalized,'реестр');
        $dispatchContext = false;
        foreach (['диспетчер','автобаз','автопарк'] as $signal) {
            if (str_contains($normalized, $signal)) $dispatchContext = true;
        }

        $operationalSignals = 0;
        foreach (['задач','статус','готовност','контрол','смен','ответствен','дежур','показ','рейс','маршрут','автобус','пассажир','резерв','обслужив','задержк','неисправ'] as $signal) {
            if (str_contains($normalized, $signal)) $operationalSignals++;
        }

        $monitoringActivitySignals = 0;
        foreach (['провер','осмотр','обход','замер','измер','обновил данн'] as $signal) {
            if (str_contains($normalized, $signal)) $monitoringActivitySignals++;
        }

        // Several distinct fleet/dispatch measures indicate a read-only
        // operations dashboard even when the brief does not call it internal.
        // A historical public transit article does not meet this threshold.
        return ($internal && $operationalSignals > 0)
            || ($dispatchContext && $operationalSignals >= 3)
            || ($monitoringContext && $monitoringActivitySignals >= 2);
    }

    /** Match factual station logs to the Docs Page rather than a generic landing layout. */
    private static function hasStationLogGuideIntent(string $normalized): bool {
        $hasStation = str_contains($normalized,'метеостанц')
            || str_contains($normalized,'гидропост')
            || str_contains($normalized,'гидрологич');
        if (!$hasStation) return false;

        $hasLog = str_contains($normalized,'журнал')
            || str_contains($normalized,'дневник')
            || str_contains($normalized,'сводк');
        if (!$hasLog) return false;

        foreach (['наблюден','измерен','показан','осадк','температур','уровен воды'] as $signal) {
            if (str_contains($normalized,$signal)) return true;
        }
        return false;
    }

    /** A bounded layout direction. Owner content and system safety rules remain authoritative. */
    public static function prompt(string $brief): string {
        $match = self::match($brief);
        if ($match === null) return '';
        return '[MATCHED OPEN DESIGN LAYOUT — '.$match['id'].' @ '.$match['revision'].'; external source: '.$match['sourcePath'].']'."\n"
            .'Use the matched reference as the primary composition pattern for this page archetype while preserving the owner brief, project settings, owner-selected Skill, and system safety/accessibility rules. '
            .$match['guidance'].' Translate the layout into fresh semantic HTML and CSS. Never copy source copy, claims, brands, media, code, class names, markup, or styles.';
    }

    /** Resolves the SHA-pinned reference set and reduces it locally to bounded structural signals. */
    public static function resolve(string $brief, OpenDesignTemplateSource $source): array {
        $match=self::match($brief, $selection);
        if($match===null)return ['selection'=>$selection,'status'=>'not_matched','templateId'=>null,'variant'=>null,'revision'=>null,'sourcePath'=>null,'fallbackArchetype'=>null,'prompt'=>'','visualSignals'=>[]];
        if($selection==='archetype_fallback')return ['selection'=>'no_match','status'=>'not_matched','templateId'=>null,'variant'=>null,'revision'=>null,'sourcePath'=>null,'fallbackArchetype'=>'service','prompt'=>'[APP-OWNED SERVICE ARCHETYPE — no external source selected] Create a concise service page from owner-supplied facts. Use compact navigation only when real destinations exist, a compact Hero with one heading and brief lead, then one substantive service-details region. Use repeatable cards only for independent services or facts supplied by the owner. Keep each concrete service fact in one canonical region. Omit unsupported proof, metrics, software features, pricing, and visitor actions. Preserve the authored compact geometry; do not add a default full-height Hero. This fallback is app-owned and is not an Open Design template.','visualSignals'=>[]];
        $result=[
            'status'=>'unavailable',
            'selection'=>$selection,
            'templateId'=>$match['id'],
            'variant'=>$match['variant']??'product',
            'revision'=>$match['revision'],
            'sourcePath'=>$match['sourcePath'],
            'fallbackArchetype'=>null,
            'prompt'=>self::prompt($brief),
            'visualSignals'=>[],
        ];
        try {
            $loaded=$match['id']==='web-prototype'
                ? $source->load($match['id'])
                : $source->load($match['id'],$match['sourcePath']);
            $file=$loaded['files'][$match['sourcePath']]??null;
            if(!is_string($file))return $result;
            $visualSourcePath=$match['id']==='web-prototype'?'design-templates/web-prototype/assets/template.html':$match['sourcePath'];
            $visualSource=$loaded['files'][$visualSourcePath]??$file;
            if(($match['variant']??null)==='editorial-log-list'){
                $supportedLayouts=self::sourceLayoutOptions($file);
                $signals=array_values(array_intersect($supportedLayouts,['centered editorial Hero','editorial chronological list only for real dated entries']));
            }else{
                $composition=array_merge(self::compositionBlueprint($file),$visualSource!==$file?self::compositionBlueprint($visualSource):[]);
                $signals=array_values(array_unique($composition!==[]?$composition:self::structuralSignals($file)));
            }
            if($signals!==[])$result['prompt'].="\n\n".'[PINNED-SOURCE COMPOSITION BLUEPRINT]'."\n".'The pinned external file was reduced locally to these bounded, non-copy layout signals: '.implode('; ',$signals).'. Preserve the spatial relationships and hierarchy of supported regions as the primary design direction; omit only regions that the owner content or safe renderer cannot support. Never reproduce source copy, claims, brand, assets, code, markup, or styles.';
            $visualSignals=self::visualSignals($visualSource,$match['id'],$match['variant']??'product');
            $result['visualSignals']=$visualSignals;
            if($visualSignals!==[])$result['prompt'].="\n\n".'[PINNED-SOURCE VISUAL BLUEPRINT]'."\n".'Translate these abstract visual traits into fresh styles that use the project design tokens: '.implode('; ',$visualSignals).'. Do not fall back to browser-default typography or unstyled regions. Do not reproduce literal source values or copy source CSS.';
            $result['compositionSignals']=$signals;
            $result['compositionContract']=OpenDesignComposition::extract($match['id'],$match['variant']??'product',$signals,$visualSignals);
            if($result['compositionContract']!==null)$result['prompt'].="\n\n[SERVER-VERIFIED COMPOSITION CONTRACT]\n".OpenDesignComposition::prompt($result['compositionContract']);
            $result['status']='loaded';
        } catch(\Throwable) {
            // Reference loading is optional; generation continues with reviewed fallback guidance.
        }
        return $result;
    }

    /** Reduce source region order and CSS geometry to non-copy semantic roles. */
    private static function compositionBlueprint(string $source): array {
        if($source===''||strlen($source)>OpenDesignTemplateSource::MAX_FILE_BYTES)return [];
        $markup=preg_replace('~<(script|style|noscript)\b[^>]*>.*?</\1\s*>~is',' ',$source)??$source;
        $markup=preg_replace('~<!--.*?-->~s',' ',$markup)??$markup;
        $regions=[];
        $document=new \DOMDocument();$previousErrors=libxml_use_internal_errors(true);
        $loaded=$document->loadHTML($markup,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);
        libxml_clear_errors();libxml_use_internal_errors($previousErrors);
        if($loaded){
            $xpath=new \DOMXPath($document);
            $nodes=$xpath->query('//*[self::header or self::nav or self::section or self::article or self::aside or self::footer or self::div]');
            if($nodes!==false)foreach($nodes as $node){
                if(!$node instanceof \DOMElement)continue;
                $tag=strtolower($node->tagName);
                // BEM and generated source classes often separate role words with
                // underscores; normalize before matching so those files remain legible.
                $classes=strtolower(str_replace('_','-',trim($node->getAttribute('class').' '.$node->getAttribute('id'))));
                $role=null;
                if(preg_match('/\b(?:hero|banner|masthead)\b/',$classes))$role='prominent Hero';
                elseif(preg_match('/\b(?:feature|features|benefit|benefits)\b/',$classes))$role='repeatable feature group';
                elseif(preg_match('/\b(?:proof|testimonial|testimonials|logos|trust)\b/',$classes))$role='separate proof region';
                elseif(preg_match('/\b(?:pricing|tiers|plans|plan-card|tier)\b/',$classes))$role='offer or pricing group';
                elseif(preg_match('/\b(?:compare|comparison)\b/',$classes))$role='comparison region';
                elseif(preg_match('/\b(?:faq|questions)\b/',$classes))$role='FAQ region';
                elseif(preg_match('/\b(?:kpi|metric|metrics|stat|stats)\b/',$classes))$role='compact metric group';
                elseif(preg_match('/\b(?:sidebar|side-nav|rail)\b/',$classes))$role='persistent navigation rail';
                elseif(preg_match('/\b(?:toc|table-of-contents|contents)\b/',$classes))$role='separate contents rail';
                elseif(preg_match('/\b(?:panel|panels|work-area)\b/',$classes))$role='operational work area';
                elseif(preg_match('/\b(?:closing|cta-strip|action-strip)\b/',$classes))$role='closing action region';
                elseif(in_array($tag,['header','nav'],true))$role='top navigation';
                elseif($tag==='article')$role='primary article';
                elseif($tag==='aside')$role='secondary side region';
                elseif($tag==='footer')$role='closing footer';
                // Several pinned archetypes mark their opening composition with a
                // neutral section name (for example, "upper") but still contain
                // exactly one H1. Preserve that source-led Hero structure.
                elseif($tag==='section'&&$node->getElementsByTagName('h1')->length>0)$role='prominent Hero';
                if($role===null||($regions[count($regions)-1]??null)===$role)continue;
                $regions[]=$role;
            }
        }
        // Global navigation is visually a page-level lead even when an external
        // article wrapper happens to contain it in the source DOM.
        if(($navigationIndex=array_search('top navigation',$regions,true))!==false&&$navigationIndex>0){
            array_splice($regions,$navigationIndex,1);array_unshift($regions,'top navigation');
        }
        // The role scan is a flat DOM walk, so it can encounter an <article>
        // wrapper before the Hero section nested inside it. Model the visible
        // sibling flow expected by the app renderer, not parent/child traversal
        // order: page navigation, Hero, then article body.
        if(($heroIndex=array_search('prominent Hero',$regions,true))!==false&&$heroIndex>0){
            array_splice($regions,$heroIndex,1);
            $navigationIndex=array_search('top navigation',$regions,true);
            array_splice($regions,$navigationIndex===false?0:$navigationIndex+1,0,['prominent Hero']);
        }
        $signals=[];
        if($regions!==[])$signals[]='source region order: '.implode(' → ',array_slice($regions,0,8));
        if(preg_match_all('~<style\b[^>]*>(.*?)</style\s*>~is',$source,$styleMatches)){
            $css=preg_replace('~/\*.*?\*/~s',' ',implode("\n",$styleMatches[1]))??'';
            if(preg_match_all('/([^{}]+)\{([^{}]*)\}/',$css,$rules,PREG_SET_ORDER)){
                $heroLayout=null;$heroBalance=null;$heroTextAlignment='';$heroContentAlignment='';$repeatColumns=null;$spacing='';$surface='';
                foreach($rules as $rule){
                    $selector=strtolower(str_replace('_','-',trim($rule[1])));$declarations=[];
                    foreach(explode(';',$rule[2]) as $declaration){$separator=strpos($declaration,':');if($separator===false)continue;$declarations[strtolower(trim(substr($declaration,0,$separator)))]=strtolower(trim(substr($declaration,$separator+1)));}
                    $columns=$declarations['grid-template-columns']??'';
                    if($columns!==''&&preg_match('/(?:hero|banner|masthead|headline|upper|intro)/',$selector)){
                        $fractions=[];if(preg_match_all('/((?:\d+(?:\.\d+)?|\.\d+))fr\b/i',$columns,$values))$fractions=array_map('floatval',$values[1]);
                        if(count($fractions)>=2){
                            $heroLayout=count(array_unique($fractions))>1?'asymmetric multi-region Hero grid':'balanced multi-region Hero grid';
                            $heroNode=null;
                            foreach($xpath->query('//*[self::section or self::header or self::div]') as $candidate){
                                if(!$candidate instanceof \DOMElement)continue;
                                $candidateClasses=strtolower(str_replace('_','-',trim($candidate->getAttribute('class').' '.$candidate->getAttribute('id'))));
                                if(preg_match('/\b(?:hero|banner|masthead|headline|upper|intro)\b/',$candidateClasses)){$heroNode=$candidate;break;}
                            }
                            if($heroNode instanceof \DOMElement){
                                $copyPosition=null;$mediaPosition=null;
                                foreach($xpath->query('.//*',$heroNode) as $position=>$child){
                                    if(!$child instanceof \DOMElement)continue;
                                    if($copyPosition===null&&in_array(strtolower($child->tagName),['h1','h2'],true))$copyPosition=$position;
                                    $childClasses=strtolower(str_replace('_','-',trim($child->getAttribute('class').' '.$child->getAttribute('id'))));
                                    if($mediaPosition===null&&(strtolower($child->tagName)==='img'||preg_match('/\b(?:hero-image|hero-media|visual|media)\b/',$childClasses)))$mediaPosition=$position;
                                }
                                if($copyPosition!==null&&$mediaPosition!==null&&$fractions[0]>0&&$fractions[1]>0){
                                    $sourceFirstIsMedia=$mediaPosition<$copyPosition;
                                    $firstColumnRole=$sourceFirstIsMedia?'media':'copy';
                                    $secondColumnRole=$sourceFirstIsMedia?'copy':'media';
                                    if($fractions[0]>$fractions[1]*1.15)$heroBalance=$firstColumnRole.'-dominant split';
                                    elseif($fractions[1]>$fractions[0]*1.15)$heroBalance=$secondColumnRole.'-dominant split';
                                    else $heroBalance='balanced split';
                                }
                            }
                        }
                        elseif(preg_match('/repeat\(\s*[2-9]\s*,/i',$columns))$heroLayout='multi-column Hero composition';
                    }
                    if(preg_match('/(?:hero|banner|masthead|headline|upper|intro)/',$selector)){
                        if(($declarations['text-align']??'')==='center')$heroTextAlignment='center-aligned Hero text';
                        if(($declarations['display']??'')==='flex'&&(($declarations['justify-content']??'')==='center'||($declarations['align-items']??'')==='center'))$heroContentAlignment='centered Hero content group';
                    }
                    if($columns!==''&&preg_match('/(?:feature|benefit|card|tier|plan)/',$selector)&&preg_match('/repeat\(\s*([2-9])\s*,/i',$columns,$columnMatch))$repeatColumns=(int)$columnMatch[1];
                    foreach(['gap','column-gap','row-gap'] as $property){
                        if(!isset($declarations[$property])||!preg_match('/(\d+(?:\.\d+)?)\s*(px|rem)/',$declarations[$property],$gapMatch))continue;
                        $gap=(float)$gapMatch[1];if($gapMatch[2]==='rem')$gap*=16;
                        $spacing=$gap>=36?'generous inter-region spacing':($gap>=16?'regular inter-region spacing':'compact inter-region spacing');
                    }
                    if(preg_match('/(?:card|feature|plan|tier|panel)/',$selector)&&isset($declarations['border-radius'])){
                        $radius=$declarations['border-radius'];
                        if(preg_match('/^(?:9999px|50%|100vmax)/',$radius))$surface='pill-shaped repeated surfaces';
                        elseif(preg_match('/^0(?:px|rem)?$/',$radius))$surface='square-corner repeated surfaces';
                        else $surface='soft-corner repeated surfaces';
                    }
                }
                if($heroLayout!==null)$signals[]='source Hero geometry: '.$heroLayout;
                if($heroBalance!==null)$signals[]='source Hero balance: '.$heroBalance;
                if($heroTextAlignment!=='')$signals[]='source Hero alignment: '.$heroTextAlignment;
                if($heroContentAlignment!=='')$signals[]='source Hero placement: '.$heroContentAlignment;
                if($repeatColumns!==null)$signals[]='repeated content geometry: '.$repeatColumns.' aligned columns where the viewport allows';
                if($spacing!=='')$signals[]='source spacing rhythm: '.$spacing;
                if($surface!=='')$signals[]='repeated surface treatment: '.$surface;
            }
        }
        return array_slice(array_values(array_unique($signals)),0,8);
    }

    /** @return list<string> */
    private static function structuralSignals(string $source): array {
        if($source===''||strlen($source)>OpenDesignTemplateSource::MAX_FILE_BYTES)return [];
        $source=preg_replace('~<(script|style|noscript)\b[^>]*>.*?</\1\s*>~is',' ',$source)??$source;
        $source=preg_replace('~<!--.*?-->~s',' ',$source)??$source;
        $counts=[];
        preg_match_all('~<\s*(nav|header|main|section|article|aside|footer|h1|h2|h3|button|a|img|ul|li|pre|code|blockquote|figure|table|form)\b~i',$source,$tags);
        foreach($tags[1] as $tag){$key=strtolower($tag);$counts[$key]=($counts[$key]??0)+1;}
        $classTokens=[];
        if(preg_match_all('~\bclass\s*=\s*(["\'])(.*?)\1~is',$source,$classMatches)){
            foreach($classMatches[2] as $classes){
                foreach(preg_split('/[^a-z0-9_-]+/i',$classes,-1,PREG_SPLIT_NO_EMPTY)?:[] as $token){
                    $classTokens[strtolower($token)]=true;
                }
            }
        }
        $hasClass=static function(array $needles)use($classTokens):bool{
            foreach($classTokens as $token=>$_){foreach($needles as $needle){if(str_contains($token,$needle))return true;}}
            return false;
        };
        $signals=[];
        if(($counts['nav']??0)>0)$signals[]='top navigation';
        if(($counts['h1']??0)>0)$signals[]='headline-led opening';
        if(($counts['section']??0)>=2)$signals[]='several distinct content sections';
        if(($counts['article']??0)>=2||($counts['li']??0)>=4)$signals[]='repeatable content group';
        if(($counts['img']??0)>0)$signals[]='image placement';
        if(($counts['button']??0)>=2||($counts['a']??0)>=3)$signals[]='multiple action or navigation points';
        if(($counts['footer']??0)>0)$signals[]='closing footer';
        if(($counts['header']??0)>0||$hasClass(['topbar','navbar']))$signals[]='separate compact page header';
        if(($counts['nav']??0)>0&&($counts['article']??0)>0)$signals[]='section-navigation rail alongside a primary article';
        if(($counts['aside']??0)>0&&($counts['article']??0)>0)$signals[]='secondary rail alongside the primary article';
        if($hasClass(['sidebar','side-nav']))$signals[]='persistent vertical navigation rail';
        if($hasClass(['toc','table-of-contents','contents']))$signals[]='separate table-of-contents region';
        if(($counts['pre']??0)>0||($counts['code']??0)>=2)$signals[]='technical examples embedded beside their explanatory sections';
        if($hasClass(['callout'])||($counts['blockquote']??0)>0)$signals[]='visually distinct note or quotation region';
        if($hasClass(['pager','prev-next']))$signals[]='previous-and-next article navigation';
        if($hasClass(['feature','feature-grid']))$signals[]='repeatable feature-card group';
        if($hasClass(['proof','logos']))$signals[]='distinct customer-proof strip';
        if($hasClass(['tier','plan-card']))$signals[]='repeatable offer-card group';
        if($hasClass(['compare','comparison'])||($counts['table']??0)>0)$signals[]='separate plan-comparison region';
        if($hasClass(['kpi','metric','stat']))$signals[]='compact metric-tile row';
        if($hasClass(['panel','panels']))$signals[]='separate operational work-panel region';
        if($hasClass(['byline','author']))$signals[]='article metadata separated from the lead';
        $layoutTerms=['hero','grid','split','centered','full-width','gallery','stacked','asymmetric','editorial'];
        $found=[];
        foreach($layoutTerms as $term){if(preg_match('/(?<![[:alnum:]])'.preg_quote($term,'/').'(?![[:alnum:]])/iu',$source))$found[]=$term;}
        if($found)$signals[]='layout vocabulary: '.implode(', ',$found);
        $sourceLayouts=self::sourceLayoutOptions($source);
        if($sourceLayouts)$signals[]='reviewed layout archetypes: '.implode('; ',$sourceLayouts);
        return array_slice(array_values(array_unique($signals)),0,16);
    }

    /** Reduce the pinned stylesheet to visual roles, never raw declarations or source values. */
    private static function visualSignals(string $source,string $templateId,string $variant='product'): array {
        if($source===''||strlen($source)>OpenDesignTemplateSource::MAX_FILE_BYTES)return [];
        if(!preg_match_all('~<style\b[^>]*>(.*?)</style\s*>~is',$source,$matches))return [];
        $css=preg_replace('~/\*.*?\*/~s',' ',implode("\n",$matches[1]))??'';
        if($css==='')return [];

        $rules=[];
        if(preg_match_all('/([^{}]+)\{([^{}]*)\}/',$css,$cssRules,PREG_SET_ORDER))foreach($cssRules as $rule){
            $selectors=strtolower(trim($rule[1]));$declarations=[];
            foreach(explode(';',$rule[2]) as $declaration){$separator=strpos($declaration,':');if($separator===false)continue;$declarations[strtolower(trim(substr($declaration,0,$separator)))]=strtolower(trim(substr($declaration,$separator+1)));}
            if($selectors!==''&&$declarations)$rules[]=[$selectors,$declarations];
        }
        $signals=[];
        $heroGrid=false;$unequalHeroColumns=false;$headlineSerif=false;$bodySerif=false;$bodySans=false;$articleSerif=false;$styledCardRadius=false;$styledCardBorder=false;$styledCardShadow=false;$spacingRules=0;
        foreach($rules as [$selector,$declarations]){
            $isHero=preg_match('/(?:hero|banner|masthead)/i',$selector)===1;
            $isHeadline=preg_match('/(?:^|[\s,>])(?:h1|h2|h3)(?:$|[\s.#:,])/i',$selector)===1||preg_match('/(?:headline|display-title)/i',$selector)===1;
            $isBody=preg_match('/(?:^|[\s,>])(?:p|body)(?:$|[\s.#:,])/i',$selector)===1||preg_match('/(?:copy|body-text|reading-column|article-body)/i',$selector)===1;
            $isArticle=preg_match('/(?:article|reading-column|article-body)/i',$selector)===1;
            if($isHero&&isset($declarations['grid-template-columns'])&&preg_match('/\b(?:grid|inline-grid)\b/i',$declarations['display']??'grid')){
                $heroGrid=true;$columns=$declarations['grid-template-columns'];
                if(preg_match_all('/((?:\d+(?:\.\d+)?|\.\d+))fr\b/i',$columns,$fractions)>1&&count(array_unique($fractions[1]))>1)$unequalHeroColumns=true;
                elseif(preg_match_all('/\d+(?:\.\d+)?(?:px|rem|%|vw)\b/i',$columns,$measures)>1&&count(array_unique($measures[0]))>1)$unequalHeroColumns=true;
            }
            $family=$declarations['font-family']??$declarations['font']??'';
            if($isHeadline&&preg_match('/(?<![-\w])serif\b|georgia|baskerville|times\b/i',$family))$headlineSerif=true;
            if($isBody){if(preg_match('/(?<![-\w])serif\b|georgia|baskerville|times\b/i',$family))$bodySerif=true;if(preg_match('/\bsans-serif\b|arial|helvetica|system-ui/i',$family))$bodySans=true;}
            if($isArticle&&preg_match('/(?<![-\w])serif\b|georgia|baskerville|times\b/i',$family))$articleSerif=true;
            if(preg_match('/(?:card|plan|feature|panel)/i',$selector)){
                $styledCardRadius=$styledCardRadius||isset($declarations['border-radius']);
                $styledCardBorder=$styledCardBorder||isset($declarations['border']);
                $styledCardShadow=$styledCardShadow||isset($declarations['box-shadow']);
            }
            foreach(['padding','padding-top','padding-bottom','margin','margin-top','margin-bottom','gap','row-gap','column-gap'] as $property)if(isset($declarations[$property])){$spacingRules++;break;}
        }
        if($heroGrid&&preg_match('/<h1\b/i',$source)&&preg_match('/<img\b|data-image-slot/i',$source))$signals[]=$unequalHeroColumns?'asymmetric split Hero with adjacent copy and image regions':'split Hero with adjacent copy and image regions';
        if($headlineSerif&&$bodySans)$signals[]='serif display headings paired with sans-serif body text';
        elseif($templateId==='blog-post'&&($articleSerif||$bodySerif))$signals[]='serif typography scoped to the article reading column';
        if($templateId==='blog-post'&&preg_match('/font-style\s*:\s*italic/i',$css)&&preg_match('/(?:lead|lede|blockquote)/i',$css))$signals[]='italic editorial treatment limited to a lead or quotation';
        if($templateId==='blog-post'&&preg_match('/::first-letter/i',$css))$signals[]='an optional typographic drop cap at the start of the article';
        if($styledCardRadius)$signals[]='rounded card surfaces';
        if($styledCardBorder&&!$styledCardShadow)$signals[]='flat, outlined card surfaces without heavy shadows';
        elseif($styledCardShadow)$signals[]='card surfaces use restrained depth';
        if($spacingRules>=5)$signals[]='a consistent spacing rhythm across authored layout regions';
        if(preg_match('/@media\s*\([^)]*(?:max-width|min-width)/i',$css))$signals[]='a deliberate narrow-screen reflow for the same content hierarchy';

        if($templateId==='blog-post'&&preg_match('/(?:^|\s)(?:byline|author)(?:\s|$)/i',$source))$signals[]='a distinct author metadata row only when author information is supplied';

        $hexColors=[];
        if(preg_match_all('/#([0-9a-f]{3}|[0-9a-f]{6})(?![0-9a-f])/i',$css,$colorMatches)){
            foreach($colorMatches[1] as $hex){
                if(strlen($hex)===3)$hex=$hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
                $hexColors[]=array_map('hexdec',str_split($hex,2));
            }
        }
        if(count($hexColors)>=3){
            $light=0;$dark=0;$chromatic=0;
            foreach($hexColors as [$red,$green,$blue]){
                $luminance=(299*$red+587*$green+114*$blue)/1000;
                if($luminance>190)$light++;elseif($luminance<72)$dark++;
                if(max($red,$green,$blue)-min($red,$green,$blue)>70)$chromatic++;
            }
            if($light>$dark)$signals[]=$templateId==='blog-post'?'a light article reading surface with dark text contrast':'a light canvas balanced by darker text and surfaces';
            elseif($dark>$light)$signals[]=$templateId==='blog-post'?'a dark article reading surface with clearly separated text contrast':'a dark canvas balanced by lighter text and surfaces';
            if($chromatic<=2)$signals[]='a restrained accent-color role instead of many competing hues';
        }
        if($templateId==='web-prototype'&&$variant==='editorial-log-list'){
            // This variant is selected from owner-provided, dated work records.
            // Keep the externally derived composition specific and prevent the
            // source's unrelated feature-card/split-Hero options from leaking
            // into this page's blueprint.
            $signals=array_values(array_filter($signals,static fn(string $signal):bool=>!str_contains($signal,'split Hero')&&!str_contains($signal,'card surfaces')));
            if(preg_match('/\.hero-center\s*\{[^}]*text-align\s*:\s*center/s',$css))$signals[]='a centered editorial Hero with a controlled reading width';
            if(preg_match('/\.log-row\s*\{[^}]*display\s*:\s*grid[^}]*grid-template-columns\s*:/s',$css))$signals[]='open chronological work rows with a narrow date rail and a flexible title-and-description column';
            if(preg_match('/\.log-row\s*\{[^}]*border-top\s*:/s',$css))$signals[]='plain work rows separated by top rules instead of enclosing cards';
            if(preg_match('/\.log-row\s+\.meta\s*\{[^}]*font-family\s*:\s*var\(\s*--font-mono\s*\)/s',$css))$signals[]='date metadata uses a restrained monospaced text role';
        }
        return array_slice(array_values(array_unique($signals)),0,6);
    }

    /** Map only recognized numbered layout headings from the pinned Open Design guide to safe native-generation choices. */
    private static function sourceLayoutOptions(string $source): array {
        $known=[
            'hero centered'=>'centered editorial Hero',
            'hero split'=>'split Hero with copy beside a contextual visual',
            'feature triplet'=>'three related feature items when the brief confirms three distinct benefits',
            'stat row'=>'compact metrics row using only owner-confirmed numbers',
            'pull quote'=>'single pull quote using only a real owner-supplied quotation',
            'cta strip'=>'closing action strip with only an approved destination',
            'log list'=>'editorial chronological list only for real dated entries',
            'comparison table'=>'comparison table only for owner-confirmed comparable values',
        ];
        if(!preg_match_all('/^##\\s+Layout\\s+\\d+\\s+[—–-]\\s+([^\\r\\n]{1,120})$/mu',$source,$headings))return [];
        $options=[];
        foreach($headings[1] as $heading){
            $label=trim(preg_replace('/\\s*\\([^)]*\\)\\s*$/u','',$heading)??$heading);
            $key=self::normalize($label);
            if(isset($known[$key]))$options[]=$known[$key];
        }
        return array_values(array_unique($options));
    }

    private static function normalize(string $value): string {
        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? '';
        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    private static function hasPrimaryPortfolioIntent(string $normalizedBrief): bool {
        if(preg_match('/^(?:(?:a|an|the)\s+)?portfolio\b|^сайт\s+портфолио\b|^портфолио\b/u',$normalizedBrief)===1)return true;

        // Content-only briefs can describe a portfolio without naming the
        // page type. Require a creative/research entity, multiple dated works,
        // and concrete project activity so an ordinary studio description
        // still abstains instead of receiving an arbitrary portfolio layout.
        return self::hasMultipleDatedWorkRecords($normalizedBrief);
    }

    private static function hasMultipleDatedWorkRecords(string $normalizedBrief): bool {
        $hasStudioEntity=preg_match('/\b(?:студи\p{L}*|бюро|агентств\p{L}*|лаборатори\p{L}*|atelier|studio|agency)\b/u',$normalizedBrief)===1;
        $yearCount=preg_match_all('/(?<!\d)(?:19|20)\d{2}(?!\d)/u',$normalizedBrief);
        if(!$hasStudioEntity||$yearCount<2)return false;
        $projectSignals=0;
        foreach(['проект','работ','фильм','спектакл','выставк','обследова','выполнил','завершил','создал','разработал','составил','реализовал','исследован','выпустил','снял','опубликовал'] as $signal){
            if(str_contains($normalizedBrief,$signal))$projectSignals++;
        }
        return $projectSignals>=2;
    }

    private static function hasPrimarySoftwareProductIntent(string $normalizedBrief): bool {
        return preg_match('/^(?:crm|saas|облачн\\p{L}*\\s+(?:систем\\p{L}*|платформ\\p{L}*|сервис\\p{L}*)|программн\\p{L}*\\s+(?:платформ\\p{L}*|систем\\p{L}*|обеспечен\\p{L}*)|мобильн\\p{L}*\\s+приложен\\p{L}*|веб\\s+приложен\\p{L}*|онлайн\\s+(?:платформ\\p{L}*|сервис\\p{L}*))/u',$normalizedBrief)===1
            || self::hasContentOnlyPlatformProductIntent($normalizedBrief);
    }

    /** Recognize a software product described by concrete workflows without style or page-type hints. */
    private static function hasContentOnlyPlatformProductIntent(string $normalizedBrief): bool {
        if(preg_match('/^платформ\\p{L}*(?: |$)/u',$normalizedBrief)!==1)return false;
        $capabilities=['расписан','билет','возврат','свободн','сеанс','фильм','каталог','инвентар','перемещен','выставк','состояни','осмотр','заказ','заявк','статус','учёт','учет','платеж','оплат','клиент','пользоват','сотрудник','фонд','экспозиц'];
        $actions=['фиксир','отмеч','добавл','видит','открыв','ведет','вести','провер','выбира','сохраня','собира','готов','хранит','запис','перемещ'];
        $capabilityCount=0;$actionCount=0;
        foreach($capabilities as $signal)if(str_contains($normalizedBrief,$signal))$capabilityCount++;
        foreach($actions as $signal)if(str_contains($normalizedBrief,$signal))$actionCount++;
        return $capabilityCount>=3&&$actionCount>=2;
    }

    private static function hasServiceLandingIntent(string $normalizedBrief): bool {
        $offerSignals=['экскурс','заняти','трениров','услуг','аренд','ремонт','прокат','тур','мастер класс','консультац','курс','урок'];
        $detailSignals=['расписан','запис','брониров','заказ','телефон','групп','стоимост','цен','адрес','мест'];
        $hasOffer=false;$detailCount=0;
        foreach($offerSignals as $signal){if(str_contains($normalizedBrief,$signal)){$hasOffer=true;break;}}
        foreach($detailSignals as $signal){if(str_contains($normalizedBrief,$signal))$detailCount++;}
        return $hasOffer&&$detailCount>=2;
    }

    /** Require a real currency amount, an offer noun, and explicit price context. */
    private static function hasNaturalPricingIntent(string $brief): bool {
        $hasCurrencyAmount = preg_match('/(?<![\pL\pN])(?:\d{1,3}(?:[\x{20}\x{A0}\x{202F}]\d{3})+|\d+)(?:[.,]\d+)?\s*(?:₸|тенге\b|тг\b|руб(?:ль|ля|лей)?\b|\$|€)/iu', $brief) === 1;
        if (!$hasCurrencyAmount) return false;

        $normalized = self::normalize($brief);
        $hasOffer = preg_match('/\b(?:заняти\pL*|урок\pL*|встреч\pL*|курс\pL*|абонемент\pL*|тариф\pL*|услуг\pL*|пакет\pL*|сеанс\pL*|сесси\pL*)\b/u', $normalized) === 1;
        $hasPriceContext = preg_match('/\b(?:цен\pL*|стоимост\pL*|стоит|стоят|обойд[её]тся|тариф\pL*|абонемент\pL*|по цене|за)\b/u', $normalized) === 1;
        return $hasOffer && $hasPriceContext;
    }
}
