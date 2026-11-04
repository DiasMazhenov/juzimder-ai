<?php
declare(strict_types=1);

namespace Juzimder\McpAuth;

use Juzimder\Problem;
use Juzimder\Store;
use Mcp\Exception\ToolCallException;

/** Read-only MCP projection over the canonical Juzimder Project Data. */
final class McpReadTools
{
    private const MAX_PAGE_BYTES = 262144;
    private const MAX_PAGE_NODES = 50000;

    public function __construct(private readonly Store $store, private readonly string $userId) {}

    public function list_projects(int $limit = 20, int $offset = 0): array
    {
        $limit = max(1, min(50, $limit));
        $offset = max(0, min(100000, $offset));
        $rows = $this->store->projects($this->userId, $limit + 1, $offset);
        $hasMore = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);
        $items = array_map(static fn(array $row): array => [
            'id' => (string)$row['id'],
            'slug' => (string)$row['slug'],
            'name' => (string)$row['name'],
            'version' => (int)$row['version'],
            'updatedAt' => (string)$row['updated_at'],
        ], $rows);
        return ['items'=>$items, 'limit'=>$limit, 'offset'=>$offset, 'nextOffset'=>$hasMore ? $offset + $limit : null];
    }

    public function list_pages(string $project_id, int $limit = 20, int $offset = 0): array
    {
        $limit = max(1, min(50, $limit));
        $offset = max(0, min(1000, $offset));
        $project = $this->owned($project_id);
        $all = $this->store->pages($project_id, $this->userId);
        $slice = array_slice($all, $offset, $limit + 1);
        $hasMore = count($slice) > $limit;
        return [
            'projectId'=>(string)$project['id'],
            'projectVersion'=>(int)$project['version'],
            'items'=>array_slice($slice, 0, $limit),
            'limit'=>$limit,
            'offset'=>$offset,
            'nextOffset'=>$hasMore ? $offset + $limit : null,
        ];
    }

    public function get_page(string $project_id, string $page_id): array
    {
        $project = $this->owned($project_id);
        $document = $project['document'];
        $pages = $document->data->pages ?? null;
        if (!is_array($pages)) throw new ToolCallException('Страница недоступна.');
        $page = null;
        foreach ($pages as $candidate) {
            if ((string)($candidate->id ?? '') === $page_id) { $page = $candidate; break; }
        }
        if ($page === null) throw new ToolCallException('Страница недоступна в этом проекте.');

        $redactedCount = 0;
        $nodes = 0;
        $safePage = $this->safeValue($page, 0, $nodes, $redactedCount);
        $safeData = [
            'assets'=>$this->safeReferencedAssets($document->data->assets ?? [], $safePage),
            'styles'=>$this->safeValue($document->data->styles ?? [], 0, $nodes, $redactedCount),
            'pages'=>[$safePage],
        ];
        $result = [
            'projectId'=>(string)$project['id'],
            'projectVersion'=>(int)$project['version'],
            'pageId'=>$page_id,
            'pageName'=>(string)($page->name ?? 'Страница'),
            'redactedCustomCodeCount'=>$redactedCount,
            'projectData'=>['schemaVersion'=>(int)$document->schemaVersion, 'engine'=>(string)$document->engine, 'data'=>$safeData],
        ];
        $encoded = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if (!is_string($encoded) || strlen($encoded) > self::MAX_PAGE_BYTES) {
            throw new ToolCallException('Данные страницы превышают лимит MCP-ответа 256 КБ.');
        }
        return $result;
    }

    public function get_project_settings(string $project_id): array
    {
        $project = $this->owned($project_id);
        $settings = $project['settings'];
        $safePages = [];
        foreach ($settings['pages'] ?? [] as $id=>$route) {
            if (!is_array($route)) continue;
            $seo = is_array($route['seo'] ?? null) ? $route['seo'] : [];
            $safePages[(string)$id] = [
                'slug'=>(string)($route['slug'] ?? ''),
                'isHome'=>($route['isHome'] ?? false) === true,
                'seo'=>[
                    'title'=>$this->boundedText($seo['title'] ?? '', 300),
                    'description'=>$this->boundedText($seo['description'] ?? '', 1000),
                    'robots'=>in_array($seo['robots'] ?? '', ['index','noindex'], true) ? $seo['robots'] : 'index',
                    'socialTitle'=>$this->boundedText($seo['socialTitle'] ?? '', 300),
                    'socialDescription'=>$this->boundedText($seo['socialDescription'] ?? '', 1000),
                ],
            ];
        }
        $tokenKeys = ['ink','forest','canvas','paper','body','muted','line','accent','fontFamily','headingFontFamily','bodyFontFamily','bodyFontWeight','headingFontWeight','baseFontSize','fontSizeH1','fontSizeH2','fontSizeH3','fontSizeH4','fontSizeH5','fontSizeH6','bodyLineHeight','headingLineHeight','letterSpacing','radius'];
        $tokens = [];
        foreach ($tokenKeys as $key) if (is_string($settings['designTokens'][$key] ?? null)) $tokens[$key] = substr($settings['designTokens'][$key], 0, 128);
        $notFoundKeys = ['title','message','homeLabel','backgroundColor','textColor','buttonColor','buttonTextColor'];
        $notFound = [];
        foreach ($notFoundKeys as $key) if (is_string($settings['notFound'][$key] ?? null)) $notFound[$key] = $this->boundedText($settings['notFound'][$key], 500);
        $sharedSections = ['headerComponentId'=>'','footerComponentId'=>'','pages'=>[]];
        $shared = is_array($settings['sharedSections'] ?? null) ? $settings['sharedSections'] : [];
        foreach (['headerComponentId','footerComponentId'] as $key) if (is_string($shared[$key] ?? null)) $sharedSections[$key] = substr($shared[$key], 0, 120);
        foreach ($shared['pages'] ?? [] as $pageId=>$flags) {
            if (!isset($safePages[$pageId]) || !is_array($flags)) continue;
            $sharedSections['pages'][$pageId] = ['header'=>($flags['header'] ?? true) === true,'footer'=>($flags['footer'] ?? true) === true];
        }
        $safeSettings = [
            'schemaVersion'=>(int)($settings['schemaVersion'] ?? 0),
            'pages'=>$safePages,
            'designTokens'=>$tokens,
            'notFound'=>$notFound,
            'sharedSections'=>$sharedSections,
        ];
        $encoded = json_encode($safeSettings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if (!is_string($encoded) || strlen($encoded) > 32768) throw new ToolCallException('Настройки превышают лимит MCP-ответа.');
        return ['projectId'=>(string)$project['id'],'projectVersion'=>(int)$project['version'],'name'=>(string)$project['name'],'slug'=>(string)$project['slug'],'settings'=>$safeSettings];
    }

    private function owned(string $projectId): array
    {
        try { return $this->store->owned($projectId, $this->userId); }
        catch (Problem) { throw new ToolCallException('Проект недоступен.'); }
    }

    private function safeValue(mixed $value, int $depth, int &$nodes, int &$redactedCount): mixed
    {
        if (++$nodes > self::MAX_PAGE_NODES || $depth > 48) throw new ToolCallException('Дерево страницы слишком сложное для безопасной MCP-проекции.');
        if ($value instanceof \stdClass) {
            $fields = get_object_vars($value);
            if (($fields['type'] ?? null) === 'custom-code') {
                $redactedCount++;
                unset($fields['content']);
            }
            $type = is_string($fields['attributes']->type ?? null) ? strtolower($fields['attributes']->type) : '';
            if ($type === 'password' && isset($fields['attributes']->value)) $fields['attributes']->value = '';
            $safe = new \stdClass();
            foreach ($fields as $key=>$child) $safe->{$key} = $this->safeValue($child, $depth + 1, $nodes, $redactedCount);
            return $safe;
        }
        if (is_array($value)) {
            $safe = [];
            foreach ($value as $key=>$child) $safe[$key] = $this->safeValue($child, $depth + 1, $nodes, $redactedCount);
            return $safe;
        }
        if (is_string($value) && strlen($value) > self::MAX_PAGE_BYTES) throw new ToolCallException('Одно поле страницы превышает лимит MCP-ответа.');
        return $value;
    }

    private function safeReferencedAssets(mixed $assets, mixed $page): array
    {
        if (!is_array($assets)) return [];
        $sources = [];
        $collect = static function (mixed $value) use (&$collect, &$sources): void {
            if ($value instanceof \stdClass) {
                foreach (get_object_vars($value) as $key=>$child) {
                    if (in_array($key, ['src','background-image'], true) && is_string($child)) $sources[$child] = true;
                    $collect($child);
                }
            } elseif (is_array($value)) foreach ($value as $child) $collect($child);
        };
        $collect($page);
        $result = [];
        foreach ($assets as $asset) {
            if (!($asset instanceof \stdClass)) continue;
            $src = $asset->src ?? null;
            if (is_string($src) && isset($sources[$src])) $result[] = $asset;
            if (count($result) >= 200) break;
        }
        return $result;
    }

    private function boundedText(mixed $value, int $max): string
    {
        return is_string($value) ? mb_substr($value, 0, $max, 'UTF-8') : '';
    }
}
