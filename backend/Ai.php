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
    public function completeJson(string $systemPrompt, string $userPrompt, array $schema, string $schemaName): array;
}

final class OpenRouterProvider implements StructuredAiProvider {
    private const ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';
    private const MAX_COMPLETION_TOKENS = 3200;

    public function __construct(private readonly OpenRouterSettings $settings, private readonly ?AiHttpTransport $transport = null) {}

    public function completeJson(string $systemPrompt, string $userPrompt, array $schema, string $schemaName): array {
        if (!preg_match('/^[a-z][a-z0-9_]{1,63}$/D', $schemaName)) throw new \InvalidArgumentException('Invalid JSON schema name.');
        $models = [$this->settings->model];
        if ($this->settings->fallbackModel !== null) $models[] = $this->settings->fallbackModel;
        foreach ($models as $index => $model) {
            try {
                return $this->completeWithModel($model, $systemPrompt, $userPrompt, $schema, $schemaName);
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

    private function completeWithModel(string $model, string $systemPrompt, string $userPrompt, array $schema, string $schemaName): array {
        try {
            $requestBody = json_encode([
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'max_completion_tokens' => self::MAX_COMPLETION_TOKENS,
                'temperature' => 0.2,
                'provider' => ['require_parameters' => true],
                'usage' => ['include' => true],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => ['name' => $schemaName, 'strict' => true, 'schema' => $schema],
                ],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
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
