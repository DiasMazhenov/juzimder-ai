<?php
declare(strict_types=1);
namespace Juzimder;

interface AiHttpTransport {
    public function post(string $url, array $headers, string $body, int $timeoutSeconds): AiHttpResponse;
}

final class AiHttpResponse {
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly array $headers = [],
    ) {}
}

final class StreamAiHttpTransport implements AiHttpTransport {
    private const MAX_RESPONSE_BYTES = 1000000;

    public function post(string $url, array $headers, string $body, int $timeoutSeconds): AiHttpResponse {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => $timeoutSeconds,
                'ignore_errors' => true,
                'follow_location' => 0,
                'max_redirects' => 0,
                'protocol_version' => 1.1,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $responseBody = @file_get_contents($url, false, $context, 0, self::MAX_RESPONSE_BYTES + 1);
        if ($responseBody === false) throw new \RuntimeException('AI transport failed.');
        if (strlen($responseBody) > self::MAX_RESPONSE_BYTES) throw new \RuntimeException('AI response exceeded its size limit.');

        $responseHeaders = $http_response_header ?? [];
        $status = 0;
        foreach ($responseHeaders as $header) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~i', $header, $matches)) {
                $status = (int)$matches[1];
                break;
            }
        }
        return new AiHttpResponse($status, $responseBody, $responseHeaders);
    }
}

final class AiProviderFailure extends \RuntimeException {
    public function __construct(string $message, public readonly bool $retryable = false) { parent::__construct($message); }
}

final class OpenRouterSettings {
    private function __construct(
        public readonly string $apiKey,
        public readonly string $model,
        public readonly ?string $fallbackModel,
        public readonly int $timeoutSeconds,
    ) {}

    public static function fromCredentials(string $apiKey, string $model, ?string $fallbackModel = null): ?self {
        $apiKey = trim($apiKey);
        $model = trim($model);
        $fallbackModel = trim((string)$fallbackModel);
        if ($fallbackModel === '') $fallbackModel = null;
        if ($apiKey === '' || strlen($apiKey) > 512 || preg_match('/[\x00-\x20\x7F]/', $apiKey) || !self::isValidModel($model)) return null;
        if ($fallbackModel !== null && (!self::isValidModel($fallbackModel) || $fallbackModel === $model)) return null;
        return new self($apiKey, $model, $fallbackModel, 25);
    }

    public static function isValidModel(string $model): bool {
        return $model !== '' && strlen($model) <= 128 && preg_match('~^[A-Za-z0-9._:/-]+$~D', $model) === 1;
    }

    public function publicStatus(): array {
        return ['provider' => 'openrouter', 'configured' => true, 'model' => $this->model, 'fallbackModel' => $this->fallbackModel];
    }
}

interface StructuredAiProvider {
    public function completeJson(string $systemPrompt, string $userPrompt, array $schema, string $schemaName, int $maxTokens = 3200): array;
}

final class OpenRouterProvider implements StructuredAiProvider {
    private const ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

    public function __construct(private readonly OpenRouterSettings $settings, private readonly ?AiHttpTransport $transport = null) {}

    public function completeJson(string $systemPrompt, string $userPrompt, array $schema, string $schemaName, int $maxTokens = 3200): array {
        if (!preg_match('/^[a-z][a-z0-9_]{1,63}$/D', $schemaName)) throw new \InvalidArgumentException('Invalid JSON schema name.');
        if ($maxTokens < 256 || $maxTokens > 12000) throw new \InvalidArgumentException('Invalid completion token limit.');
        $models = [$this->settings->model];
        if ($this->settings->fallbackModel !== null) $models[] = $this->settings->fallbackModel;
        foreach ($models as $index => $model) {
            try {
                return $this->completeWithModel($model, $systemPrompt, $userPrompt, $schema, $schemaName, $maxTokens);
            } catch (AiProviderFailure $failure) {
                if ($index === 0 && isset($models[1]) && $failure->retryable) {
                    error_log('Juzimder AI: OpenRouter primary model failed; using configured fallback.');
                    continue;
                }
                throw $failure;
            }
        }
        throw new AiProviderFailure('AI provider returned an error.');
    }

    private function completeWithModel(string $model, string $systemPrompt, string $userPrompt, array $schema, string $schemaName, int $maxTokens): array {
        try {
            $request = [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                // OpenRouter model metadata commonly advertises max_tokens. With
                // require_parameters enabled, use that shared parameter so models
                // that do not advertise max_completion_tokens remain routable.
                'max_tokens' => $maxTokens,
                'temperature' => 0.2,
                'provider' => ['require_parameters' => true],
                'usage' => ['include' => true],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => ['name' => $schemaName, 'strict' => true, 'schema' => $schema],
                ],
            ];
            if (str_starts_with(strtolower($model), 'nvidia/nemotron-3.5-super-vl')) {
                // This model enables reasoning by default; its reasoning tokens share
                // max_tokens with the visible structured response and can leave content empty.
                $request['reasoning'] = ['enabled' => false];
            }
            $requestBody = json_encode($request, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (\JsonException) {
            throw new \InvalidArgumentException('The structured AI request is invalid.');
        }

        $transport = $this->transport ?? new StreamAiHttpTransport();
        $headers = [
            'Authorization: Bearer ' . $this->settings->apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        $response = null;
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $response = $transport->post(self::ENDPOINT, $headers, $requestBody, $this->settings->timeoutSeconds);
            } catch (\Throwable) {
                error_log('Juzimder AI: OpenRouter transport failure.');
                if ($attempt === 0) {
                    usleep(150000);
                    continue;
                }
                throw new AiProviderFailure('AI provider is unavailable.', true);
            }
            $retryableStatus = $response->status === 408 || $response->status === 429 || ($response->status >= 500 && $response->status <= 599);
            if ($attempt === 0 && $retryableStatus) {
                usleep(150000);
                continue;
            }
            break;
        }

        if (!$response || $response->status < 200 || $response->status >= 300) {
            $status = $response?->status ?? 0;
            error_log('Juzimder AI: OpenRouter returned HTTP ' . $status . '.');
            throw new AiProviderFailure('AI provider returned an error.', $status === 0 || $status === 408 || $status === 429 || ($status >= 500 && $status <= 599));
        }

        try {
            $decoded = json_decode($response->body, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            error_log('Juzimder AI: OpenRouter returned invalid JSON.');
            throw new AiProviderFailure('AI provider returned an invalid response.');
        }
        $content = $decoded['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '' || strlen($content) > 100000) {
            error_log('Juzimder AI: OpenRouter returned no structured content.');
            throw new AiProviderFailure('AI provider returned an invalid response.');
        }

        $usage = is_array($decoded['usage'] ?? null) ? $decoded['usage'] : [];
        $resolvedModel = $decoded['model'] ?? null;
        if (!is_string($resolvedModel) || !preg_match('~^[A-Za-z0-9._:/-]{1,128}$~D', $resolvedModel)) $resolvedModel = $model;
        return [
            'content' => trim($content),
            'model' => $resolvedModel,
            'usage' => [
                'promptTokens' => self::tokenCount($usage['prompt_tokens'] ?? null),
                'completionTokens' => self::tokenCount($usage['completion_tokens'] ?? null),
                'totalTokens' => self::tokenCount($usage['total_tokens'] ?? null),
                'costUsd' => is_numeric($usage['cost'] ?? null) && (float)$usage['cost'] >= 0 ? (float)$usage['cost'] : null,
            ],
        ];
    }

    private static function tokenCount(mixed $value): ?int {
        if (is_int($value) && $value >= 0) return $value;
        return is_string($value) && preg_match('/^\d+$/D', $value) ? (int)$value : null;
    }
}

final class AiDesignPlan {
    private const COLORS = ['ink', 'forest', 'canvas', 'paper', 'body', 'muted', 'line', 'accent'];
    private const SECTIONS = ['navigation', 'hero', 'features', 'process', 'testimonial', 'faq', 'cta', 'text', 'footer'];
    private const TEXT_FIELDS = [
        'navigation' => ['project name', 'About link label', 'Services link label', 'Contact link label', 'header action label'],
        'hero' => ['eyebrow', 'heading', 'description', 'action label'],
        'features' => ['card 1 number', 'card 1 title', 'card 1 description', 'card 2 number', 'card 2 title', 'card 2 description', 'card 3 number', 'card 3 title', 'card 3 description'],
        'process' => ['eyebrow', 'heading', 'step 1 number', 'step 1 title', 'step 1 description', 'step 2 number', 'step 2 title', 'step 2 description', 'step 3 number', 'step 3 title', 'step 3 description'],
        'testimonial' => ['eyebrow', 'quote', 'author', 'author role'],
        'faq' => ['eyebrow', 'heading', 'question 1', 'answer 1', 'question 2', 'answer 2', 'question 3', 'answer 3'],
        'cta' => ['heading', 'description', 'action label'],
        'text' => ['paragraph'],
        'footer' => ['project name and year', 'About link label', 'Contact link label'],
    ];

    public static function schema(): array {
        $colorProperties = [];
        foreach (self::COLORS as $color) $colorProperties[$color] = ['type' => 'string', 'description' => 'Six-digit hexadecimal color, for example #2A8C7A.'];
        return [
            'type' => 'object',
            'properties' => [
                'siteType' => ['type' => 'string', 'description' => 'Website type, up to 120 characters.'],
                'audience' => ['type' => 'string', 'description' => 'Target audience, up to 240 characters.'],
                'goal' => ['type' => 'string', 'description' => 'Primary website goal, up to 200 characters.'],
                'style' => ['type' => 'string', 'description' => 'Visual style, up to 200 characters.'],
                'colors' => ['type' => 'object', 'properties' => $colorProperties, 'required' => self::COLORS, 'additionalProperties' => false],
                'sections' => ['type' => 'array', 'description' => 'Three to nine unique supported section ids, including hero.', 'items' => ['type' => 'string', 'enum' => self::SECTIONS]],
                'content' => [
                    'type' => 'array',
                    'description' => 'One object per selected section, using the same section ids. Each texts array must contain plain text strings in the documented order for that section.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'section' => ['type' => 'string', 'enum' => self::SECTIONS],
                            'texts' => ['type' => 'array', 'description' => 'Plain text only; no HTML, CSS, JavaScript or URL strings.', 'items' => ['type' => 'string']],
                        ],
                        'required' => ['section', 'texts'],
                        'additionalProperties' => false,
                    ],
                ],
                'responsiveNotes' => ['type' => 'string', 'description' => 'Mobile and tablet layout notes, up to 400 characters.'],
            ],
            'required' => ['siteType', 'audience', 'goal', 'style', 'colors', 'sections', 'content', 'responsiveNotes'],
            'additionalProperties' => false,
        ];
    }

    public static function parseProviderOutput(string $json): array {
        try {
            $plan = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new AiProviderFailure('AI provider returned an invalid plan.');
        }
        if (!is_array($plan) || array_is_list($plan) || array_diff(array_keys($plan), ['siteType','audience','goal','style','colors','sections','content','responsiveNotes'])) throw new AiProviderFailure('AI provider returned an invalid plan.');
        foreach (['siteType'=>120,'audience'=>240,'goal'=>200,'style'=>200,'responsiveNotes'=>400] as $field => $maxLength) {
            if (!is_string($plan[$field] ?? null) || trim($plan[$field]) === '' || mb_strlen(trim($plan[$field])) > $maxLength) throw new AiProviderFailure('AI provider returned an invalid plan.');
            $plan[$field] = trim($plan[$field]);
        }
        $colors = $plan['colors'] ?? null;
        if (!is_array($colors) || array_is_list($colors) || array_diff(array_keys($colors), self::COLORS)) throw new AiProviderFailure('AI provider returned an invalid plan.');
        foreach (self::COLORS as $color) if (!is_string($colors[$color] ?? null) || !preg_match('/^#[0-9A-Fa-f]{6}$/D', $colors[$color])) throw new AiProviderFailure('AI provider returned an invalid plan.');
        $sections = $plan['sections'] ?? null;
        if (!is_array($sections) || !array_is_list($sections) || count($sections) < 3 || count($sections) > 9 || !in_array('hero', $sections, true)) throw new AiProviderFailure('AI provider returned an invalid plan.');
        foreach ($sections as $section) if (!is_string($section) || !in_array($section, self::SECTIONS, true)) throw new AiProviderFailure('AI provider returned an invalid plan.');
        if (count(array_unique($sections)) !== count($sections)) throw new AiProviderFailure('AI provider returned an invalid plan.');
        if (count($colors) !== count(self::COLORS)) throw new AiProviderFailure('AI provider returned an invalid plan.');
        $content = $plan['content'] ?? null;
        if (!is_array($content) || !array_is_list($content) || count($content) !== count($sections)) throw new AiProviderFailure('AI provider returned an invalid plan.');
        $contentBySection = [];
        foreach ($content as $entry) {
            if (!is_array($entry) || array_is_list($entry) || array_diff(array_keys($entry), ['section','texts']) || count($entry) !== 2) throw new AiProviderFailure('AI provider returned an invalid plan.');
            $section = $entry['section'] ?? null;
            $texts = $entry['texts'] ?? null;
            if (!is_string($section) || !in_array($section, $sections, true) || isset($contentBySection[$section]) || !is_array($texts) || !array_is_list($texts) || count($texts) !== count(self::TEXT_FIELDS[$section])) throw new AiProviderFailure('AI provider returned an invalid plan.');
            foreach ($texts as &$text) {
                if (!is_string($text) || trim($text) === '' || mb_strlen(trim($text)) > 220 || preg_match('/[<>\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|https?:\/\/|www\./iu', $text)) throw new AiProviderFailure('AI provider returned an invalid plan.');
                $text = trim($text);
            }
            unset($text);
            $contentBySection[$section] = ['section' => $section, 'texts' => $texts];
        }
        if (count($contentBySection) !== count($sections)) throw new AiProviderFailure('AI provider returned an invalid plan.');
        $plan['content'] = array_map(static fn(string $section): array => $contentBySection[$section], $sections);
        return $plan;
    }
}

final class AiDesignPlanner {
    private const SYSTEM_PROMPT = 'Create a concise responsive website design plan and original website copy. Answer in the same language as the user. Use only supported section ids. For content, return one object for each selected section and plain text values in this exact order: navigation [project name, About link label, Services link label, Contact link label, header action label]; hero [eyebrow, heading, description, action label]; features [for each of 3 cards: number, title, description]; process [eyebrow, heading, then for each of 3 steps: number, title, description]; testimonial [eyebrow, quote, author, role]; faq [eyebrow, heading, then 3 question and answer pairs]; cta [heading, description, action label]; text [paragraph]; footer [project name and year, About link label, Contact link label]. Keep text strings at most 220 characters. Do not return HTML, CSS, JavaScript, URLs, markup, or instructions that execute code.';

    public static function create(string $prompt, StructuredAiProvider $provider): array {
        $completion = $provider->completeJson(self::SYSTEM_PROMPT, $prompt, AiDesignPlan::schema(), 'juzimder_design_plan');
        return [
            'plan' => AiDesignPlan::parseProviderOutput($completion['content'] ?? ''),
            'metadata' => [
                'provider' => 'openrouter',
                'resolvedModel' => $completion['model'] ?? null,
                'usage' => $completion['usage'] ?? ['promptTokens'=>null,'completionTokens'=>null,'totalTokens'=>null,'costUsd'=>null],
            ],
        ];
    }
}

final class AiGeneratedWebsite {
    private const TAGS = ['main','header','nav','section','footer','div','article','aside','h1','h2','h3','h4','p','span','a','button','ul','ol','li','strong','em','b','i','br','hr'];
    private const STYLE_PROPERTIES = [
        'display','flex-direction','justify-content','align-items','flex-wrap','align-self','grid-template-columns','gap',
        'width','height','min-width','max-width','min-height','max-height','padding','margin','color','background-color',
        'font-family','font-size','font-weight','line-height','letter-spacing','text-align','text-decoration',
        'border','border-top','border-bottom','border-radius','aspect-ratio','opacity','cursor',
    ];

    private const SYSTEM_PROMPT = 'Create a polished, responsive one-page website design and original copy from the user brief. Return only the requested JSON fields. The html field must contain a fragment with 3 to 9 <section> elements and exactly one hero section having class ai-hero. Use only these tags: main, header, nav, section, footer, div, article, aside, h1-h4, p, span, a, button, ul, ol, li, strong, em, b, i, br, hr. Use no images, forms, scripts, SVG, iframe, embedded content, inline style, or comments. Allowed attributes are class, id, href, title, role, aria-label, aria-hidden. Every class token must begin with ai- and contain only letters, digits, underscore, or hyphen. Every id must be unique and use only letters, digits, underscore, or hyphen; IDs are scoped by the application. Use href only for #fragment, https, mailto, or tel links. The css field must use class selectors only, each selector class must exist in html, and selectors must not use pseudo selectors, IDs, tags, attributes, or global selectors. Style only with these properties: display, flex-direction, justify-content, align-items, flex-wrap, align-self, grid-template-columns, gap, width, height, min-width, max-width, min-height, max-height, padding, margin, color, background-color, font-family, font-size, font-weight, line-height, letter-spacing, text-align, text-decoration, border, border-top, border-bottom, border-radius, aspect-ratio, opacity, cursor. Use safe simple values: hex colors, responsive grid repeat(auto-fit, minmax(...)), flex/grid, spacing in px/rem/%/vw, and system fonts. Responsive behavior should use flex-wrap, auto-fit grids, and at most two @media (max-width: Npx) blocks. Never return JavaScript or external asset URLs. Keep the design visually distinctive, with clear section rhythm, strong typography, and a deliberate palette.';

    public static function schema(): array {
        $textField = static fn(string $description): array => ['type'=>'string','description'=>$description];
        return [
            'type'=>'object',
            'properties'=>[
                'siteType'=>$textField('Website type, up to 120 characters.'),
                'audience'=>$textField('Target audience, up to 240 characters.'),
                'goal'=>$textField('Primary website goal, up to 200 characters.'),
                'style'=>$textField('Visual art direction, up to 200 characters.'),
                'html'=>['type'=>'string','description'=>'Safe HTML fragment using only the tags and attributes from the system instructions. Maximum 24000 characters.'],
                'css'=>['type'=>'string','description'=>'Responsive CSS using only the safe class selectors and properties from the system instructions. Maximum 24000 characters.'],
            ],
            'required'=>['siteType','audience','goal','style','html','css'],
            'additionalProperties'=>false,
        ];
    }

    public static function parseProviderOutput(string $json): array {
        try { $design = json_decode($json, true, 32, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new AiProviderFailure('AI provider returned an invalid design.'); }
        $fields = ['siteType','audience','goal','style','html','css'];
        if (!is_array($design) || array_is_list($design) || count($design) !== count($fields) || array_diff(array_keys($design), $fields)) throw new AiProviderFailure('AI provider returned an invalid design.');
        foreach (['siteType'=>120,'audience'=>240,'goal'=>200,'style'=>200] as $field=>$limit) {
            if (!is_string($design[$field] ?? null) || trim($design[$field]) === '' || mb_strlen(trim($design[$field]), 'UTF-8') > $limit) throw new AiProviderFailure('AI provider returned an invalid design.');
            $design[$field] = trim($design[$field]);
        }
        if (!is_string($design['html'] ?? null) || strlen($design['html']) > 24000 || !is_string($design['css'] ?? null) || strlen($design['css']) > 24000) throw new AiProviderFailure('AI provider returned an invalid design.');

        $scope = 'ai-scope-' . bin2hex(random_bytes(8));
        $markup = self::sanitizeMarkup($design['html'], $scope);
        $stylesheet = self::sanitizeStylesheet($design['css'], $scope, $markup['classes']);
        return [
            'siteType'=>$design['siteType'], 'audience'=>$design['audience'], 'goal'=>$design['goal'], 'style'=>$design['style'],
            'html'=>'<div class="' . $scope . '">' . $markup['html'] . '</div>',
            'css'=>$stylesheet,
            'sectionCount'=>$markup['sectionCount'],
        ];
    }

    public static function create(string $prompt, StructuredAiProvider $provider): array {
        $completion = $provider->completeJson(self::SYSTEM_PROMPT, $prompt, self::schema(), 'juzimder_generated_site', 6000);
        return [
            'design'=>self::parseProviderOutput($completion['content'] ?? ''),
            'metadata'=>[
                'provider'=>'openrouter',
                'resolvedModel'=>$completion['model'] ?? null,
                'usage'=>$completion['usage'] ?? ['promptTokens'=>null,'completionTokens'=>null,'totalTokens'=>null,'costUsd'=>null],
            ],
        ];
    }

    private static function sanitizeMarkup(string $source, string $scope): array {
        if (trim($source) === '' || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $source)) throw new AiProviderFailure('AI provider returned an invalid design.');
        $rootId = 'juzimder-safe-root-' . bin2hex(random_bytes(8));
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML('<!doctype html><html><head><meta charset="UTF-8"></head><body><div id="' . $rootId . '">' . $source . '</div></body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) throw new AiProviderFailure('AI provider returned an invalid design.');
        $body = $document->getElementsByTagName('body')->item(0);
        $root = null;
        foreach ($document->getElementsByTagName('*') as $element) if ($element->getAttribute('id') === $rootId) { $root = $element; break; }
        if (!$body || !$root || $root->parentNode !== $body) throw new AiProviderFailure('AI provider returned an invalid design.');
        foreach ($body->childNodes as $child) {
            if ($child === $root || ($child instanceof \DOMText && trim($child->textContent) === '')) continue;
            throw new AiProviderFailure('AI provider returned an invalid design.');
        }

        $state = ['classes'=>[], 'ids'=>[], 'fragments'=>[], 'sections'=>0, 'heroes'=>0, 'nodes'=>0, 'textLength'=>0];
        $markup = '';
        foreach ($root->childNodes as $child) $markup .= self::serializeNode($child, $scope, $state, 0);
        foreach ($state['fragments'] as $fragment) if (!isset($state['ids'][$fragment])) throw new AiProviderFailure('AI provider returned an invalid design.');
        if ($state['sections'] < 3 || $state['sections'] > 9 || $state['heroes'] !== 1) throw new AiProviderFailure('AI provider returned an invalid design.');
        return ['html'=>$markup,'classes'=>$state['classes'],'sectionCount'=>$state['sections']];
    }

    private static function serializeNode(\DOMNode $node, string $scope, array &$state, int $depth): string {
        if ($depth > 16) throw new AiProviderFailure('AI provider returned an invalid design.');
        if ($node instanceof \DOMText) {
            $text = $node->nodeValue ?? '';
            $state['textLength'] += mb_strlen($text, 'UTF-8');
            if ($state['textLength'] > 12000 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $text)) throw new AiProviderFailure('AI provider returned an invalid design.');
            return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        }
        if (!$node instanceof \DOMElement) {
            if ($node instanceof \DOMComment && trim($node->data) === '') return '';
            throw new AiProviderFailure('AI provider returned an invalid design.');
        }
        $tag = strtolower($node->tagName);
        if (!in_array($tag, self::TAGS, true) || ++$state['nodes'] > 300) throw new AiProviderFailure('AI provider returned an invalid design.');
        if ($tag === 'section') $state['sections']++;
        $attributes = [];
        foreach ($node->attributes as $attribute) {
            $name = strtolower($attribute->name);
            $value = trim($attribute->value);
            if (preg_match('/[\x00-\x1F\x7F]/', $value) || mb_strlen($value, 'UTF-8') > 256) throw new AiProviderFailure('AI provider returned an invalid design.');
            if ($name === 'class') {
                $tokens = preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                if (!$tokens || count($tokens) > 8 || count(array_unique($tokens)) !== count($tokens)) throw new AiProviderFailure('AI provider returned an invalid design.');
                foreach ($tokens as $token) {
                    if (!preg_match('/^ai-[A-Za-z0-9_-]{1,56}$/D', $token)) throw new AiProviderFailure('AI provider returned an invalid design.');
                    $state['classes'][$token] = true;
                }
                if ($tag === 'section' && in_array('ai-hero', $tokens, true)) $state['heroes']++;
                $value = implode(' ', $tokens);
            } elseif ($name === 'id') {
                if (!preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/D', $value) || isset($state['ids'][$value])) throw new AiProviderFailure('AI provider returned an invalid design.');
                $state['ids'][$value] = true;
                $value = $scope . '-' . $value;
            } elseif ($name === 'href') {
                if ($tag !== 'a' || !preg_match('~^(https://[^\s<>"\x00-\x20]+|#[A-Za-z][A-Za-z0-9_-]{0,63}|#|mailto:[A-Za-z0-9@._+-]+|tel:[+0-9 -]+)$~D', $value)) throw new AiProviderFailure('AI provider returned an invalid design.');
                if (str_starts_with($value, '#') && strlen($value) > 1) {
                    $state['fragments'][] = substr($value, 1);
                    $value = '#' . $scope . '-' . substr($value, 1);
                }
            } elseif (in_array($name, ['title','role','aria-label','aria-hidden'], true)) {
                if ($name === 'aria-hidden' && !in_array($value, ['true','false'], true)) throw new AiProviderFailure('AI provider returned an invalid design.');
                if ($name === 'role' && !preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,31}$/D', $value)) throw new AiProviderFailure('AI provider returned an invalid design.');
                if ($name === 'aria-label' && $value === '') throw new AiProviderFailure('AI provider returned an invalid design.');
            } else throw new AiProviderFailure('AI provider returned an invalid design.');
            $attributes[$name] = $value;
        }
        if (in_array($tag, ['a','button'], true) && !isset($attributes['class'])) throw new AiProviderFailure('AI provider returned an invalid design.');
        $attributeText = '';
        foreach ($attributes as $name=>$value) $attributeText .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8') . '"';
        if (in_array($tag, ['br','hr'], true)) {
            if ($node->hasChildNodes()) throw new AiProviderFailure('AI provider returned an invalid design.');
            return '<' . $tag . $attributeText . '>';
        }
        $children = '';
        foreach ($node->childNodes as $child) $children .= self::serializeNode($child, $scope, $state, $depth + 1);
        return '<' . $tag . $attributeText . '>' . $children . '</' . $tag . '>';
    }

    private static function sanitizeStylesheet(string $source, string $scope, array $classes): string {
        if (trim($source) === '' || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F<>]|\/\*|\*\//', $source)) throw new AiProviderFailure('AI provider returned an invalid design.');
        $rules = [];
        self::parseCssRules($source, $scope, $classes, $rules, null, 0);
        if (!$rules || count($rules) > 120) throw new AiProviderFailure('AI provider returned an invalid design.');
        $output = '';
        foreach ($rules as $rule) {
            $block = $rule['selector'] . '{' . implode(';', array_map(static fn($property,$value)=>$property . ':' . $value, array_keys($rule['style']), array_values($rule['style']))) . '}';
            $output .= $rule['media'] === null ? $block . "\n" : '@media ' . $rule['media'] . '{' . $block . "}\n";
        }
        return $output;
    }

    private static function parseCssRules(string $css, string $scope, array $classes, array &$rules, ?string $media, int $depth): void {
        if ($depth > 1) throw new AiProviderFailure('AI provider returned an invalid design.');
        $length = strlen($css); $offset = 0;
        while ($offset < $length) {
            while ($offset < $length && ctype_space($css[$offset])) $offset++;
            if ($offset >= $length) break;
            if (preg_match('/\G@media\s*\(\s*max-width:\s*(\d{3,4}(?:\.\d{1,2})?(?:px|em|rem))\s*\)\s*\{/Ai', $css, $match, 0, $offset)) {
                $open = $offset + strlen($match[0]);
                $close = self::matchingBrace($css, $open - 1);
                if ($close === null) throw new AiProviderFailure('AI provider returned an invalid design.');
                $width = (float)preg_replace('/(?:px|em|rem)$/', '', $match[1]);
                if ($width < 320 || $width > 1920 || $media !== null) throw new AiProviderFailure('AI provider returned an invalid design.');
                self::parseCssRules(substr($css, $open, $close - $open), $scope, $classes, $rules, '(max-width: ' . $match[1] . ')', $depth + 1);
                $offset = $close + 1;
                continue;
            }
            $open = strpos($css, '{', $offset);
            $close = $open === false ? false : strpos($css, '}', $open + 1);
            if ($open === false || $close === false || strpos($css, '{', $open + 1) !== false && strpos($css, '{', $open + 1) < $close) throw new AiProviderFailure('AI provider returned an invalid design.');
            $selectorList = trim(substr($css, $offset, $open - $offset));
            if ($selectorList === '' || str_contains($selectorList, '@')) throw new AiProviderFailure('AI provider returned an invalid design.');
            $declarations = self::parseDeclarations(substr($css, $open + 1, $close - $open - 1));
            if (!$declarations) {
                $offset = $close + 1;
                continue;
            }
            foreach (explode(',', $selectorList) as $selector) {
                $selector = trim($selector);
                if (!preg_match('/^\.(?:ai-[A-Za-z0-9_-]{1,56})(?:\s+\.(?:ai-[A-Za-z0-9_-]{1,56}))*$/D', $selector)) continue;
                preg_match_all('/\.(ai-[A-Za-z0-9_-]{1,56})/', $selector, $matches);
                $allClassesExist = true;
                foreach ($matches[1] as $class) if (!isset($classes[$class])) { $allClassesExist = false; break; }
                if (!$allClassesExist) continue;
                $rules[] = ['selector'=>'.' . $scope . ' ' . $selector,'style'=>$declarations,'media'=>$media];
                if (count($rules) > 120) throw new AiProviderFailure('AI provider returned an invalid design.');
            }
            $offset = $close + 1;
        }
    }

    private static function matchingBrace(string $css, int $open): ?int {
        $level = 0;
        for ($index = $open, $length = strlen($css); $index < $length; $index++) {
            if ($css[$index] === '{') $level++;
            elseif ($css[$index] === '}' && --$level === 0) return $index;
        }
        return null;
    }

    private static function parseDeclarations(string $source): array {
        $style = [];
        foreach (explode(';', trim($source)) as $declaration) {
            if (trim($declaration) === '') continue;
            $separator = strpos($declaration, ':');
            if ($separator === false) continue;
            $property = strtolower(trim(substr($declaration, 0, $separator)));
            $value = trim(substr($declaration, $separator + 1));
            if (!in_array($property, self::STYLE_PROPERTIES, true) || isset($style[$property]) || $value === '' || mb_strlen($value, 'UTF-8') > 160 || !self::validCssValue($property, $value)) continue;
            $style[$property] = $value;
        }
        if (count($style) > 24) throw new AiProviderFailure('AI provider returned an invalid design.');
        return $style;
    }

    private static function validCssValue(string $property, string $value): bool {
        if (preg_match('/[\x00-\x1F\x7F{}<>;\\"\']|\/\*|\*\//', $value) || preg_match('/(?:url|expression)\s*\(|@import|javascript\s*:/i', $value)) return false;
        $length = '(?:0|(?:\d{1,4}(?:\.\d{1,2})?(?:px|rem|em|%|vw|vh)))';
        $lengthOrClamp = '(?:' . $length . '|clamp\(\s*' . $length . '\s*,\s*' . $length . '\s*,\s*' . $length . '\s*\))';
        $color = '(?:#[0-9a-fA-F]{6}|transparent|currentColor)';
        return match ($property) {
            'color','background-color' => preg_match('/^' . $color . '$/D', $value) === 1,
            'display' => in_array($value, ['block','flex','grid','inline-block','inline-flex'], true),
            'flex-direction' => in_array($value, ['row','column','row-reverse','column-reverse'], true),
            'justify-content' => in_array($value, ['flex-start','center','flex-end','space-between','space-around','space-evenly'], true),
            'align-items' => in_array($value, ['flex-start','center','flex-end','stretch','baseline'], true),
            'align-self' => in_array($value, ['auto','flex-start','center','flex-end','stretch'], true),
            'flex-wrap' => in_array($value, ['nowrap','wrap','wrap-reverse'], true),
            'grid-template-columns' => preg_match('/^(?:repeat\((?:[1-4]|auto-fit|auto-fill),\s*minmax\(\s*(?:0|\d{1,4}px)\s*,\s*1fr\s*\)\)|(?:1fr|\d{1,4}px)(?:\s+(?:1fr|\d{1,4}px)){0,3})$/D', $value) === 1,
            'gap' => preg_match('/^' . $lengthOrClamp . '(?:\s+' . $lengthOrClamp . ')?$/D', $value) === 1,
            'padding' => preg_match('/^' . $lengthOrClamp . '(?:\s+' . $lengthOrClamp . '){0,3}$/D', $value) === 1,
            'margin' => preg_match('/^(?:' . $lengthOrClamp . '|auto)(?:\s+(?:' . $lengthOrClamp . '|auto)){0,3}$/D', $value) === 1,
            'width','height','min-width','max-width','min-height','max-height' => preg_match('/^(?:' . $lengthOrClamp . '|auto|fit-content|min-content|max-content)$/D', $value) === 1,
            'font-family' => preg_match('/^(?:Arial|Georgia|Inter|system-ui|sans-serif|serif)(?:,\s*(?:Arial|Georgia|Inter|system-ui|sans-serif|serif))*$/D', $value) === 1,
            'font-size' => preg_match('/^' . $lengthOrClamp . '$/D', $value) === 1,
            'font-weight' => preg_match('/^(?:300|400|500|600|700|800|900|normal|bold)$/D', $value) === 1,
            'line-height' => preg_match('/^(?:0?\.\d{1,2}|[1-3](?:\.\d{1,2})?|' . $lengthOrClamp . ')$/D', $value) === 1,
            'letter-spacing' => preg_match('/^(?:normal|' . $lengthOrClamp . ')$/D', $value) === 1,
            'text-align' => in_array($value, ['left','center','right','justify'], true),
            'text-decoration' => in_array($value, ['none','underline','line-through'], true),
            'border','border-top','border-bottom' => $value === 'none' || preg_match('/^(?:0|' . $length . ')\s+solid\s+' . $color . '$/D', $value) === 1,
            'border-radius' => preg_match('/^' . $lengthOrClamp . '$/D', $value) === 1,
            'aspect-ratio' => $value === 'auto' || preg_match('/^\d{1,3}(?:\.\d{1,2})?\s*\/\s*\d{1,3}(?:\.\d{1,2})?$/D', $value) === 1,
            'opacity' => preg_match('/^(?:0|1|0?\.\d{1,2})$/D', $value) === 1,
            'cursor' => in_array($value, ['default','pointer'], true),
            default => false,
        };
    }
}
