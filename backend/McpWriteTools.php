<?php
declare(strict_types=1);

namespace Juzimder\McpAuth;

use Juzimder\Problem;
use Juzimder\ProjectOperations;
use Juzimder\ProjectSettings;
use Juzimder\Store;
use Mcp\Exception\ToolCallException;

/** Write tools that delegate validation and persistence to Juzimder's existing domain services. */
final class McpWriteTools
{
    private const MAX_OPERATIONS = 20;
    private const MAX_OPERATION_BYTES = 1048576;
    private const MAX_SETTINGS_BYTES = 32768;
    private const MAX_INDEXED_NODES = 50000;

    public function __construct(
        private readonly Store $store,
        private readonly string $userId,
        private readonly array $scopes,
    ) {}

    public function apply_page_operations(string $project_id, string $page_id, int $expected_version, mixed $operations): array
    {
        $this->requireWriteScope();
        if ($expected_version < 0 || !is_array($operations) || !array_is_list($operations) || count($operations) < 1 || count($operations) > self::MAX_OPERATIONS) {
            throw new ToolCallException('Укажите актуальную версию проекта и от 1 до 20 операций.');
        }
        try {
            $encoded = json_encode($operations, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (strlen($encoded) > self::MAX_OPERATION_BYTES) throw new ToolCallException('Запрос операций превышает лимит 1 МБ.');
            $batch = json_decode($encoded, false, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ToolCallException('Операции должны быть корректным JSON.');
        }
        if (!is_array($batch) || !array_is_list($batch)) throw new ToolCallException('Операции должны быть списком объектов.');

        $project = $this->owned($project_id);
        $this->requireExpectedVersion($project, $expected_version);
        $draft = $project['document'];
        $this->requirePage($draft, $page_id);

        foreach ($batch as $operation) {
            $this->assertOperationIsPageScoped($draft, $page_id, $operation);
            try {
                $draft = ProjectOperations::apply($draft, [$operation]);
            } catch (Problem $error) {
                throw new ToolCallException($error->getMessage());
            }
        }

        try {
            $saved = $this->store->save($project_id, $this->userId, $expected_version, $draft);
        } catch (Problem $error) {
            throw new ToolCallException($error->getMessage());
        }
        return [
            'projectId'=>(string)$saved['id'],
            'pageId'=>$page_id,
            'projectVersion'=>(int)$saved['version'],
            'appliedOperationCount'=>count($batch),
        ];
    }

    public function update_project_settings(string $project_id, int $expected_version, mixed $updates): array
    {
        $this->requireWriteScope();
        if ($expected_version < 0) throw new ToolCallException('Укажите актуальную версию проекта.');
        $updates = $this->normalizeSettingsInput($updates);
        if ($updates === [] || array_diff(array_keys($updates), ['designTokens','page','seo','notFound','sharedSections'])) {
            throw new ToolCallException('Передайте только разрешённые настройки: designTokens, page, seo, notFound или sharedSections.');
        }
        if (count($updates) > 5) throw new ToolCallException('Слишком много групп настроек в одном запросе.');
        try {
            $encoded = json_encode($updates, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException) {
            throw new ToolCallException('Настройки должны быть корректным JSON.');
        }
        if (strlen($encoded) > self::MAX_SETTINGS_BYTES) throw new ToolCallException('Запрос настроек превышает лимит 32 КБ.');

        $project = $this->owned($project_id);
        $this->requireExpectedVersion($project, $expected_version);
        $settings = $project['settings'];
        $document = $project['document'];

        try {
            if (array_key_exists('designTokens', $updates)) {
                $settings['designTokens'] = ProjectSettings::validateDesignTokens($updates['designTokens'], $settings['designTokens']);
                $document = ProjectSettings::applyDesignTokensToDocument($document, $settings['designTokens']);
            }
            if (array_key_exists('page', $updates)) {
                $page = $updates['page'];
                if (!is_array($page) || array_diff(array_keys($page), ['pageId','name','slug']) || !is_string($page['pageId'] ?? null) || !isset($settings['pages'][$page['pageId']]) || !is_string($page['name'] ?? null) || !is_string($page['slug'] ?? null)) {
                    throw new Problem(422, 'Укажите страницу, название и адрес.');
                }
                $name = trim($page['name']);
                if ($name === '' || mb_strlen($name, 'UTF-8') > 100 || preg_match('/[\x00-\x1F\x7F]/u', $name)) throw new Problem(422, 'Название страницы: от 1 до 100 символов.');
                $slug = ProjectSettings::validateSlug($page['slug']);
                foreach ($settings['pages'] as $otherId=>$route) if ($otherId !== $page['pageId'] && ($route['slug'] ?? null) === $slug) throw new Problem(422, 'Такой URL уже используется в проекте.');
                $found = false;
                foreach ($document->data->pages as $item) {
                    if ((string)$item->id !== $page['pageId']) continue;
                    $item->name = $name;
                    $found = true;
                    break;
                }
                if (!$found) throw new Problem(422, 'Указанная страница не принадлежит проекту.');
                $settings['pages'][$page['pageId']]['slug'] = $slug;
            }
            if (array_key_exists('seo', $updates)) {
                $seo = $updates['seo'];
                if (!is_array($seo) || array_diff(array_keys($seo), ['pageId','title','description','robots','socialImage','socialTitle','socialDescription']) || !is_string($seo['pageId'] ?? null) || $seo['pageId'] === '' || !array_key_exists($seo['pageId'], $settings['pages'])) {
                    throw new Problem(422, 'Укажите страницу и разрешённые SEO-поля.');
                }
                $pageId = $seo['pageId'];
                unset($seo['pageId']);
                $settings['pages'][$pageId]['seo'] = ProjectSettings::validateSeoMetadata($seo, $settings['pages'][$pageId]['seo']);
            }
            if (array_key_exists('notFound', $updates)) $settings['notFound'] = ProjectSettings::validateNotFoundSettings($updates['notFound'], $settings['notFound'] ?? ProjectSettings::notFoundDefaults());
            if (array_key_exists('sharedSections', $updates)) $settings['sharedSections'] = ProjectSettings::validateSharedSections($updates['sharedSections'], array_keys($settings['pages']), ProjectSettings::topLevelSectionIds($document));
            $saved = $this->store->save($project_id, $this->userId, $expected_version, $document, $settings);
        } catch (Problem $error) {
            throw new ToolCallException($error->getMessage());
        }

        return [
            'projectId'=>(string)$saved['id'],
            'projectVersion'=>(int)$saved['version'],
            'updatedFields'=>array_values(array_keys($updates)),
        ];
    }

    private function normalizeSettingsInput(mixed $updates): array
    {
        if ($updates instanceof \stdClass) $updates = get_object_vars($updates);
        if (!is_array($updates) || array_is_list($updates)) throw new ToolCallException('Настройки должны быть объектом полей и значений.');
        try {
            $json = json_encode($updates, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $normalized = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ToolCallException('Настройки должны быть корректным JSON.');
        }
        return is_array($normalized) ? $normalized : [];
    }

    private function assertOperationIsPageScoped(\stdClass $document, string $pageId, mixed $operation): void
    {
        if (!$operation instanceof \stdClass || !is_string($operation->operation ?? null)) throw new ToolCallException('Операция должна быть типизированным объектом.');
        $kind = $operation->operation;
        [$pageNodes, $idCounts] = $this->indexComponents($document, $pageId);

        if ($kind === 'create_component') {
            $this->assertParentInPage($operation->parentId ?? null, $pageId, $pageNodes, $idCounts);
            if ($this->containsCustomCode($operation->component ?? null)) throw new ToolCallException('Добавление исполняемого Custom Code через MCP запрещено.');
            return;
        }
        if ($kind === 'update_zero_block') {
            if (($operation->pageId ?? null) !== $pageId) throw new ToolCallException('Zero Block должен принадлежать выбранной странице.');
            $component = $this->pageComponent($operation->componentId ?? null, $pageNodes, $idCounts);
            if (($component->type ?? null) !== 'zero-block') throw new ToolCallException('Выбранный компонент не является Zero Block на этой странице.');
            return;
        }

        $component = $this->pageComponent($operation->componentId ?? null, $pageNodes, $idCounts);
        if ($kind === 'duplicate_component' && $this->containsCustomCode($component)) throw new ToolCallException('Дублирование компонента с Custom Code запрещено через MCP.');
        if ($kind === 'move_component') $this->assertParentInPage($operation->parentId ?? null, $pageId, $pageNodes, $idCounts);
    }

    private function indexComponents(\stdClass $document, string $pageId): array
    {
        $target = null;
        foreach ($document->data->pages ?? [] as $page) if (($page->id ?? null) === $pageId) { $target = $page; break; }
        if (!$target instanceof \stdClass) throw new ToolCallException('Страница недоступна в этом проекте.');
        $pageNodes = [];
        $idCounts = [];
        $nodeCount = 0;
        $walk = static function (mixed $node, bool $selectedPage, int $depth) use (&$walk, &$pageNodes, &$idCounts, &$nodeCount): void {
            if (!$node instanceof \stdClass) return;
            if ($depth > 48 || ++$nodeCount > self::MAX_INDEXED_NODES) throw new ToolCallException('Страница слишком сложная для безопасного изменения через MCP.');
            $id = $node->attributes->id ?? null;
            if (is_string($id) && $id !== '') {
                $idCounts[$id] = ($idCounts[$id] ?? 0) + 1;
                if ($selectedPage) $pageNodes[$id] = $node;
            }
            foreach ($node->components ?? [] as $child) $walk($child, $selectedPage, $depth + 1);
        };
        foreach ($document->data->pages as $page) {
            $root = $page->frames[0]->component ?? null;
            if (!$root instanceof \stdClass) continue;
            $onSelectedPage = (string)($page->id ?? '') === $pageId;
            $walk($root, $onSelectedPage, 0);
        }
        return [$pageNodes, $idCounts];
    }

    private function pageComponent(mixed $id, array $pageNodes, array $idCounts): \stdClass
    {
        if (!is_string($id) || $id === '' || !isset($pageNodes[$id]) || ($idCounts[$id] ?? 0) !== 1) throw new ToolCallException('Компонент недоступен на выбранной странице.');
        return $pageNodes[$id];
    }

    private function assertParentInPage(mixed $parentId, string $pageId, array $pageNodes, array $idCounts): void
    {
        if (!is_string($parentId) || $parentId === '') throw new ToolCallException('Укажите родительский компонент этой страницы.');
        if ($parentId === 'page:' . $pageId) return;
        if (str_starts_with($parentId, 'page:')) throw new ToolCallException('Нельзя переносить компоненты на другую страницу через MCP.');
        $this->pageComponent($parentId, $pageNodes, $idCounts);
    }

    private function containsCustomCode(mixed $node): bool
    {
        if (!$node instanceof \stdClass) return false;
        if (($node->type ?? null) === 'custom-code') return true;
        foreach ($node->components ?? [] as $child) if ($this->containsCustomCode($child)) return true;
        return false;
    }

    private function requirePage(\stdClass $document, string $pageId): void
    {
        foreach ($document->data->pages ?? [] as $page) if (($page->id ?? null) === $pageId) return;
        throw new ToolCallException('Страница недоступна в этом проекте.');
    }

    private function requireWriteScope(): void
    {
        if (!in_array(OAuthConfig::WRITE_SCOPE, $this->scopes, true)) throw new ToolCallException('Для этой операции нужна OAuth-область projects:write.');
    }

    private function requireExpectedVersion(array $project, int $expectedVersion): void
    {
        if ((int)$project['version'] !== $expectedVersion) throw new ToolCallException('Версия проекта изменилась. Прочитайте актуальную страницу и повторите изменение с новой expected_version.');
    }

    private function owned(string $projectId): array
    {
        try { return $this->store->owned($projectId, $this->userId); }
        catch (Problem) { throw new ToolCallException('Проект недоступен.'); }
    }
}
