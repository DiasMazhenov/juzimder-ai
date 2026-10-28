<?php
declare(strict_types=1);
namespace Juzimder;

require_once __DIR__.'/OpenDesignTemplateSource.php';

/** Maps clear page intents to reviewed, non-binding Open Design layout references. */
final class OpenDesignTemplateGuidance {
    private const REFERENCES = [
        'saas-landing' => [
            'path' => 'design-templates/saas-landing/example.html',
            'terms' => [
                'saas', 'crm', 'программный продукт', 'программное обеспечение',
                'облачная платформа', 'облачный сервис', 'программная платформа',
                'веб приложение', 'онлайн платформа', 'software as a service',
                'software product', 'cloud platform', 'web application', 'web app',
            ],
            'guidance' => 'Use a product-marketing narrative: make the product purpose clear first, then choose capability or workflow sections that the owner brief supports. Include pricing, customer proof, metrics, or testimonials only when the owner supplied those facts. Treat the reference as layout inspiration; do not copy its text, claims, brand, markup, styles, or assets.',
        ],
        'web-prototype' => [
            'path' => 'design-templates/web-prototype/references/layouts.md',
            'terms' => [
                'портфолио', 'дизайн студия', 'студия дизайна', 'креативное агентство',
                'брендинговое агентство', 'веб дизайн', 'веб дизайнер', 'дизайнер',
                'ux ui', 'ui ux', 'web design', 'design studio', 'creative agency',
                'digital agency', 'portfolio',
            ],
            'guidance' => 'Use a project-led hierarchy and choose an abstract layout pattern for each section according to its content. Use a photo only when its subject meaningfully supports the brief. Treat the reference as layout inspiration; do not copy its text, claims, brand, markup, styles, or assets.',
        ],
    ];

    /** @return array{id:string,revision:string,sourcePath:string,guidance:string}|null */
    public static function match(string $brief): ?array {
        $normalized = self::normalize($brief);
        if ($normalized === '') return null;
        $scores = [];
        foreach (self::REFERENCES as $id => $reference) {
            $score = 0;
            foreach ($reference['terms'] as $term) {
                $needle = self::normalize($term);
                if ($needle !== '' && str_contains(' '.$normalized.' ', ' '.$needle.' ')) $score++;
            }
            if ($score > 0) $scores[$id] = $score;
        }
        if (!$scores) return null;
        arsort($scores, SORT_NUMERIC);
        $ranked = array_keys($scores);
        if (isset($ranked[1]) && $scores[$ranked[0]] === $scores[$ranked[1]]) return null;
        $id = $ranked[0];
        $reference = self::REFERENCES[$id];
        return [
            'id' => $id,
            'revision' => OpenDesignTemplateSource::REVISION,
            'sourcePath' => $reference['path'],
            'guidance' => $reference['guidance'],
        ];
    }

    /** A user-level hint: it is explicitly subordinate to the brief and system safety rules. */
    public static function prompt(string $brief): string {
        $match = self::match($brief);
        if ($match === null) return '';
        return '[OPTIONAL OPEN DESIGN REFERENCE — '.$match['id'].' @ '.$match['revision'].'; source: '.$match['sourcePath'].']'."\n"
            .'This reviewed reference is secondary to the owner brief, project settings, any owner-selected Skill, and all system safety/accessibility rules. Use only its abstract composition guidance: '
            .$match['guidance'].' Do not treat the reference as a source of business facts or as a fixed page template.';
    }

    private static function normalize(string $value): string {
        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? '';
        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }
}
