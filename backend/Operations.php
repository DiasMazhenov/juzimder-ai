<?php
declare(strict_types=1);
namespace Juzimder;

final class ProjectOperations {
    private const MAX_OPERATIONS = 20;
    private const COMPONENT_FIELDS = ['type','tagName','name','content','attributes','style','components'];
    private const ATTRIBUTE_FIELDS = ['id','class','href','src','alt','title','rel','target','role','aria-label','aria-hidden','aria-describedby','aria-labelledby'];
    private const STYLE_FIELDS = [
        'display','flex-direction','justify-content','align-items','flex-wrap','align-self','grid-template-columns','gap',
        'width','height','min-width','max-width','min-height','max-height','padding','padding-top','padding-right','padding-bottom','padding-left',
        'background-image','background-size','background-position','background-repeat','margin','margin-top','margin-right','margin-bottom','margin-left','color','background-color','font-family','font-size','font-weight',
        'line-height','letter-spacing','text-align','text-decoration','border','border-top','border-bottom','border-radius','object-fit','aspect-ratio','opacity'
    ];

    public static function styleFields(): array { return self::STYLE_FIELDS; }
    public static function nodeIdentifier(\stdClass $node): ?string { return self::nodeId($node); }

    public static function componentSubtree(mixed $document, string $pageId, string $componentId): \stdClass {
        if (!$document instanceof \stdClass) throw new Problem(422, 'Некорректный документ проекта.');
        Document::validate($document);
        $pageId = self::identifier($pageId);
        $componentId = self::identifier($componentId);
        $pages = $document->data->pages ?? [];
        $pageIndex = null;
        foreach ($pages as $index => $page) {
            if (($page->id ?? null) === $pageId) { $pageIndex = $index; break; }
        }
        if ($pageIndex === null) throw new Problem(404, 'Страница не найдена.');
        $rootPath = ['data','pages',$pageIndex,'frames',0,'component'];
        $location = self::findComponent($document, $componentId);
        if (!self::isPathPrefix($rootPath, $location['path'])) throw new Problem(404, 'Компонент не найден на выбранной странице.');
        $component =& self::atPath($document, $location['path']);
        if (($component->type ?? null) === 'wrapper') throw new Problem(422, 'Выберите компонент внутри страницы.');
        return json_decode(json_encode($component, JSON_THROW_ON_ERROR), false, 64, JSON_THROW_ON_ERROR);
    }

    public static function componentForAiEdit(mixed $document, string $pageId, mixed $componentPath, string $componentId): array {
        if (!$document instanceof \stdClass) throw new Problem(422, 'Некорректный документ проекта.');
        Document::validate($document);
        $pageId = self::identifier($pageId);
        if (!is_array($componentPath) || !array_is_list($componentPath) || $componentPath === [] || count($componentPath) > 35) throw new Problem(422, 'Некорректный путь выбранного компонента.');
        foreach ($componentPath as $index) if (!is_int($index) || $index < 0) throw new Problem(422, 'Некорректный путь выбранного компонента.');
        if ($componentId !== '') $componentId = self::identifier($componentId);
        $pages = $document->data->pages ?? [];
        $pageIndex = null;
        foreach ($pages as $index => $page) if (($page->id ?? null) === $pageId) { $pageIndex = $index; break; }
        if ($pageIndex === null) throw new Problem(404, 'Страница не найдена.');
        $draft = json_decode(json_encode($document, JSON_THROW_ON_ERROR), false, 64, JSON_THROW_ON_ERROR);
        $path = ['data','pages',$pageIndex,'frames',0,'component'];
        foreach ($componentPath as $index) {
            $parent =& self::atPath($draft, $path);
            if (!is_array($parent->components ?? null) || !array_is_list($parent->components) || !array_key_exists($index, $parent->components)) throw new Problem(404, 'Компонент не найден по выбранному пути.');
            $path[] = 'components';
            $path[] = $index;
            unset($parent);
        }
        $component =& self::atPath($draft, $path);
        if (!$component instanceof \stdClass || ($component->type ?? null) === 'wrapper') throw new Problem(422, 'Выберите компонент внутри страницы.');
        $actualId = self::nodeId($component);
        if ($actualId === '') $actualId = null;
        if ($componentId !== '' && $actualId !== $componentId) throw new Problem(409, 'Выбранный стабильный ID больше не совпадает с компонентом. Выберите его заново.');
        if ($actualId !== null) {
            $location = self::findComponent($draft, $actualId);
            if ($location['path'] !== $path) throw new Problem(409, 'ID компонента неоднозначен. Обновите страницу перед AI-правкой.');
        }
        $needsId = !(($component->attributes ?? null) instanceof \stdClass && is_string($component->attributes->id ?? null) && $component->attributes->id !== '');
        $stableId = $actualId;
        if ($stableId === null) {
            $ids = [];
            self::collectIds($draft, $ids);
            do { $stableId = 'cmp-' . bin2hex(random_bytes(12)); } while (isset($ids[$stableId]));
        }
        if ($needsId) {
            if (!($component->attributes ?? null) instanceof \stdClass) $component->attributes = new \stdClass();
            $component->attributes->id = $stableId;
        }
        $target = json_decode(json_encode($component, JSON_THROW_ON_ERROR), false, 64, JSON_THROW_ON_ERROR);
        return ['document'=>$draft,'component'=>$target,'componentId'=>$stableId,'needsId'=>$needsId];
    }
    private const LAYOUT_FIELDS = [
        'display'=>'display','flexDirection'=>'flex-direction','justifyContent'=>'justify-content','alignItems'=>'align-items',
        'flexWrap'=>'flex-wrap','gridTemplateColumns'=>'grid-template-columns','gap'=>'gap','width'=>'width','height'=>'height',
        'minWidth'=>'min-width','maxWidth'=>'max-width','minHeight'=>'min-height','maxHeight'=>'max-height'
    ];

    public static function apply(mixed $document, mixed $operations): \stdClass {
        if (!$document instanceof \stdClass) throw new Problem(422, 'Некорректный документ проекта.');
        Document::validate($document);
        if (!is_array($operations) || !array_is_list($operations) || count($operations) < 1 || count($operations) > self::MAX_OPERATIONS) throw new Problem(422, 'Передайте от 1 до ' . self::MAX_OPERATIONS . ' операций.');
        try {
            $draft = json_decode(json_encode($document, JSON_THROW_ON_ERROR), false, 64, JSON_THROW_ON_ERROR);
            $batch = json_decode(json_encode($operations, JSON_THROW_ON_ERROR), false, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new Problem(422, 'Операции должны быть корректным JSON.');
        }
        if (!$draft instanceof \stdClass || !is_array($batch)) throw new Problem(422, 'Некорректный документ или список операций.');
        foreach ($batch as $operation) {
            self::applyOne($draft, $operation);
            Document::validate($draft);
        }
        return $draft;
    }

    private static function applyOne(\stdClass $document, mixed $operation): void {
        if (!$operation instanceof \stdClass || !is_string($operation->operation ?? null)) throw new Problem(422, 'Некорректная typed operation.');
        switch ($operation->operation) {
            case 'create_component':
                self::fields($operation, ['operation','parentId','component','index'], ['operation','parentId','component']);
                self::create($document, self::identifier($operation->parentId), $operation->component, $operation->index ?? null);
                return;
            case 'update_content':
                self::fields($operation, ['operation','componentId','content'], ['operation','componentId','content']);
                if (!is_string($operation->content) || mb_strlen($operation->content, 'UTF-8') > 10000) throw new Problem(422, 'Текст компонента не должен превышать 10000 символов.');
                $location = self::findComponent($document, self::identifier($operation->componentId));
                $component =& self::atPath($document, $location['path']);
                $type = $component->type ?? null;
                if (!in_array($type, ['text','textnode','link'], true)) throw new Problem(422, 'Этот компонент не поддерживает изменение текста.');
                $component->content = $operation->content;
                return;
            case 'replace_text':
                self::fields($operation, ['operation','componentId','content'], ['operation','componentId','content']);
                if (!is_string($operation->content) || mb_strlen($operation->content, 'UTF-8') > 1000 || preg_match('/[<>\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|https?:\/\/|www\./iu', $operation->content)) throw new Problem(422, 'Текст должен быть обычным текстом без разметки и URL.');
                $location = self::findComponent($document, self::identifier($operation->componentId));
                $component =& self::atPath($document, $location['path']);
                $type = $component->type ?? null;
                if (!in_array($type, ['text','textnode','link'], true)) throw new Problem(422, 'Этот компонент не поддерживает замену текста.');
                if ($type === 'textnode') $component->content = $operation->content;
                else {
                    unset($component->content);
                    $component->components = [(object)['type'=>'textnode','content'=>$operation->content]];
                }
                return;
            case 'update_styles':
                self::fields($operation, ['operation','componentId','styles'], ['operation','componentId','styles']);
                $location = self::findComponent($document, self::identifier($operation->componentId));
                $component =& self::atPath($document, $location['path']);
                self::applyStyles($component, $operation->styles);
                return;
            case 'update_layout':
                self::fields($operation, ['operation','componentId','layout'], ['operation','componentId','layout']);
                $layout = $operation->layout;
                if (!$layout instanceof \stdClass || get_object_vars($layout) === []) throw new Problem(422, 'Укажите параметры компоновки.');
                $styles = new \stdClass();
                foreach (get_object_vars($layout) as $field => $value) {
                    if (!isset(self::LAYOUT_FIELDS[$field])) throw new Problem(422, 'Неизвестное свойство компоновки.');
                    if (!is_string($value)) throw new Problem(422, 'Значение компоновки должно быть строкой.');
                    if ($field === 'display' && !in_array($value, ['block','flex','grid','inline','inline-block'], true)) throw new Problem(422, 'Недопустимое значение display.');
                    if ($field === 'flexDirection' && !in_array($value, ['row','row-reverse','column','column-reverse'], true)) throw new Problem(422, 'Недопустимое направление Flex.');
                    if ($field === 'justifyContent' && !in_array($value, ['flex-start','flex-end','center','space-between','space-around','space-evenly'], true)) throw new Problem(422, 'Недопустимое выравнивание.');
                    if ($field === 'alignItems' && !in_array($value, ['stretch','flex-start','flex-end','center','baseline'], true)) throw new Problem(422, 'Недопустимое выравнивание.');
                    if ($field === 'flexWrap' && !in_array($value, ['nowrap','wrap','wrap-reverse'], true)) throw new Problem(422, 'Недопустимое значение flex-wrap.');
                    $styles->{self::LAYOUT_FIELDS[$field]} = $value;
                }
                $location = self::findComponent($document, self::identifier($operation->componentId));
                $component =& self::atPath($document, $location['path']);
                self::applyStyles($component, $styles);
                return;
            case 'delete_component':
                self::fields($operation, ['operation','componentId','confirm'], ['operation','componentId','confirm']);
                if ($operation->confirm !== true) throw new Problem(422, 'Удаление требует явного confirm: true.');
                $location = self::findComponent($document, self::identifier($operation->componentId));
                if ($location['parentPath'] === null || $location['index'] === null) throw new Problem(422, 'Корневой компонент страницы нельзя удалить.');
                $parent =& self::atPath($document, $location['parentPath']);
                array_splice($parent->components, $location['index'], 1);
                return;
            case 'move_component':
                self::fields($operation, ['operation','componentId','parentId','index'], ['operation','componentId','parentId']);
                self::move($document, self::identifier($operation->componentId), self::identifier($operation->parentId), $operation->index ?? null);
                return;
            case 'reorder_component':
                self::fields($operation, ['operation','componentId','index'], ['operation','componentId','index']);
                $id = self::identifier($operation->componentId);
                $location = self::findComponent($document, $id);
                if ($location['parentPath'] === null || $location['index'] === null) throw new Problem(422, 'Корневой компонент страницы нельзя переставить.');
                $parent =& self::atPath($document, $location['parentPath']);
                self::relocate($location, $parent, $parent, self::index($operation->index, count($parent->components) - 1));
                return;
            default:
                throw new Problem(422, 'Неподдерживаемый тип операции.');
        }
    }

    private static function create(\stdClass $document, string $parentId, mixed $componentData, mixed $index): void {
        $parentPath = self::findParentPath($document, $parentId);
        $parent =& self::atPath($document, $parentPath);
        self::canContain($parent);
        if (isset($parent->components) && (!is_array($parent->components) || !array_is_list($parent->components))) throw new Problem(422, 'Некорректный список дочерних компонентов.');
        $children = $parent->components ?? [];
        $insertAt = $index === null ? count($children) : self::index($index, count($children));
        $ids = [];
        self::collectIds($document, $ids);
        $count = 0;
        $component = self::normalizeComponent($componentData, $ids, 0, $count);
        array_splice($children, $insertAt, 0, [$component]);
        $parent->components = $children;
    }

    private static function normalizeComponent(mixed $node, array &$ids, int $depth, int &$count): \stdClass {
        if (!$node instanceof \stdClass || $depth > 20 || ++$count > 500) throw new Problem(422, 'Некорректное дерево нового компонента.');
        self::fields($node, self::COMPONENT_FIELDS, []);
        if (isset($node->type) && (!is_string($node->type) || !in_array($node->type, Document::COMPONENT_TYPES, true))) throw new Problem(422, 'Недопустимый тип компонента.');
        if (isset($node->tagName) && !is_string($node->tagName)) throw new Problem(422, 'Некорректный HTML-тег.');
        if (!isset($node->type) && !isset($node->tagName)) throw new Problem(422, 'Укажите разрешённый тип или HTML-тег компонента.');
        if (isset($node->name) && (!is_string($node->name) || mb_strlen($node->name, 'UTF-8') > 120)) throw new Problem(422, 'Некорректное название компонента.');
        if (isset($node->content) && (!is_string($node->content) || mb_strlen($node->content, 'UTF-8') > 10000)) throw new Problem(422, 'Текст компонента слишком длинный.');
        $attributes = $node->attributes ?? new \stdClass();
        if (!$attributes instanceof \stdClass) throw new Problem(422, 'Атрибуты компонента должны быть объектом.');
        self::fields($attributes, self::ATTRIBUTE_FIELDS, []);
        foreach (get_object_vars($attributes) as $name => $value) if (!is_string($value) || mb_strlen($value, 'UTF-8') > 1000) throw new Problem(422, 'Значения атрибутов должны быть строками до 1000 символов.');
        $id = $attributes->id ?? ('cmp-' . bin2hex(random_bytes(12)));
        if (!is_string($id) || $id === '' || mb_strlen($id, 'UTF-8') > 128 || preg_match('/[\x00-\x1F\x7F]/', $id)) throw new Problem(422, 'Некорректный стабильный ID компонента.');
        if (isset($ids[$id])) throw new Problem(422, 'ID нового компонента уже используется.');
        $attributes->id = $id;
        $ids[$id] = true;
        $node->attributes = $attributes;

        if (isset($node->style)) self::validateStyles($node->style);
        if (isset($node->components)) {
            if (!is_array($node->components) || !array_is_list($node->components) || count($node->components) > 500) throw new Problem(422, 'Некорректный список дочерних компонентов.');
            $children = [];
            foreach ($node->components as $child) $children[] = self::normalizeComponent($child, $ids, $depth + 1, $count);
            $node->components = $children;
        }
        return $node;
    }

    private static function applyStyles(\stdClass $component, mixed $styles): void {
        self::validateStyles($styles);
        if (!isset($component->style)) $component->style = new \stdClass();
        if (!$component->style instanceof \stdClass) throw new Problem(422, 'Стили компонента должны быть объектом.');
        foreach (get_object_vars($styles) as $property => $value) $component->style->{$property} = $value;
    }

    private static function validateStyles(mixed $styles): void {
        if (!$styles instanceof \stdClass || get_object_vars($styles) === []) throw new Problem(422, 'Укажите CSS-свойства объектом.');
        foreach (get_object_vars($styles) as $property => $value) {
            if (!in_array($property, self::STYLE_FIELDS, true)) throw new Problem(422, 'CSS-свойство не входит в разрешённый список.');
            if (!is_string($value) || $value === '' || mb_strlen($value, 'UTF-8') > 512 || preg_match('/[\x00-\x1F\x7F;{}<>\\\\]/', $value)) throw new Problem(422, 'CSS-значение содержит недопустимые символы или превышает 512 символов.');
        }
    }

    private static function move(\stdClass $document, string $componentId, string $parentId, mixed $index): void {
        $location = self::findComponent($document, $componentId);
        if ($location['parentPath'] === null || $location['index'] === null) throw new Problem(422, 'Корневой компонент страницы нельзя переместить.');
        $parentPath = self::findParentPath($document, $parentId);
        if (self::isPathPrefix($location['path'], $parentPath)) throw new Problem(422, 'Компонент нельзя переместить внутрь самого себя.');
        $oldParent =& self::atPath($document, $location['parentPath']);
        $newParent =& self::atPath($document, $parentPath);
        self::canContain($newParent);
        if (!is_array($oldParent->components ?? null) || !array_is_list($oldParent->components)) throw new Problem(422, 'Некорректный родительский компонент.');
        if (isset($newParent->components) && (!is_array($newParent->components) || !array_is_list($newParent->components))) throw new Problem(422, 'Некорректный список дочерних компонентов.');
        $children = $newParent->components ?? [];
        $maximum = count($children) - ($oldParent === $newParent ? 1 : 0);
        $insertAt = $index === null ? $maximum : self::index($index, $maximum);
        self::relocate($location, $oldParent, $newParent, $insertAt);
    }

    private static function relocate(array $location, \stdClass $oldParent, \stdClass $newParent, int $insertAt): void {
        if (!is_array($oldParent->components ?? null) || !array_is_list($oldParent->components)) throw new Problem(422, 'Некорректный родительский компонент.');
        $node = $oldParent->components[$location['index']] ?? null;
        if (!$node instanceof \stdClass) throw new Problem(422, 'Компонент нельзя переместить.');
        $oldChildren = $oldParent->components;
        array_splice($oldChildren, $location['index'], 1);
        $oldParent->components = $oldChildren;
        $newChildren = $newParent->components ?? [];
        if (!is_array($newChildren) || !array_is_list($newChildren)) throw new Problem(422, 'Некорректный список дочерних компонентов.');
        if ($insertAt > count($newChildren)) throw new Problem(422, 'Индекс компонента находится за пределами списка.');
        array_splice($newChildren, $insertAt, 0, [$node]);
        $newParent->components = $newChildren;
    }

    private static function canContain(\stdClass $parent): void {
        $type = $parent->type ?? null;
        $tag = strtolower((string)($parent->tagName ?? ''));
        if (in_array($type, ['image','text','textnode','link','link-block'], true) || is_string($parent->content ?? null) || in_array($tag, ['img','br','hr'], true)) throw new Problem(422, 'Этот компонент не может содержать дочерние элементы.');
    }

    private static function findComponent(\stdClass $document, string $id): array {
        $matches = self::findLocations($document, $id);
        if ($matches === []) throw new Problem(404, 'Компонент не найден.');
        if (count($matches) !== 1) throw new Problem(422, 'ID компонента неоднозначен.');
        return $matches[0];
    }

    private static function findParentPath(\stdClass $document, string $id): array {
        if (str_starts_with($id, 'page:')) {
            $pageId = substr($id, 5);
            if ($pageId === '') throw new Problem(422, 'Некорректный page ID.');
            foreach ($document->data->pages as $pageIndex => $page) {
                if (($page->id ?? null) !== $pageId) continue;
                $frame = $page->frames[0] ?? null;
                if (!$frame instanceof \stdClass || !isset($frame->component) || !$frame->component instanceof \stdClass) throw new Problem(422, 'У страницы нет корневого компонента.');
                return ['data','pages',$pageIndex,'frames',0,'component'];
            }
            throw new Problem(404, 'Страница не найдена.');
        }
        return self::findComponent($document, $id)['path'];
    }

    private static function findLocations(\stdClass $document, string $id): array {
        $matches = [];
        foreach ($document->data->pages as $pageIndex => $page) foreach ($page->frames as $frameIndex => $frame) {
            if ($frame instanceof \stdClass && isset($frame->component)) self::walk($frame->component, ['data','pages',$pageIndex,'frames',$frameIndex,'component'], null, null, $id, $matches, 0);
        }
        return $matches;
    }

    private static function walk(mixed $node, array $path, ?array $parentPath, ?int $index, string $id, array &$matches, int $depth): void {
        if (!$node instanceof \stdClass || $depth > 35) return;
        if (self::nodeId($node) === $id) $matches[] = ['path'=>$path,'parentPath'=>$parentPath,'index'=>$index];
        if (!is_array($node->components ?? null) || !array_is_list($node->components)) return;
        foreach ($node->components as $childIndex => $child) self::walk($child, [...$path,'components',$childIndex], $path, $childIndex, $id, $matches, $depth + 1);
    }

    private static function nodeId(\stdClass $node): ?string {
        if (($node->attributes ?? null) instanceof \stdClass && is_string($node->attributes->id ?? null)) return $node->attributes->id;
        return is_string($node->id ?? null) ? $node->id : null;
    }

    private static function collectIds(mixed $document, array &$ids): void {
        if (!$document instanceof \stdClass) return;
        foreach ($document->data->pages as $page) foreach ($page->frames as $frame) if ($frame instanceof \stdClass && isset($frame->component)) self::collectNodeIds($frame->component, $ids);
    }

    private static function collectNodeIds(mixed $node, array &$ids): void {
        if (!$node instanceof \stdClass) return;
        $id = self::nodeId($node);
        if ($id !== null) $ids[$id] = true;
        if (is_array($node->components ?? null)) foreach ($node->components as $child) self::collectNodeIds($child, $ids);
    }

    private static function isPathPrefix(array $prefix, array $path): bool {
        return count($prefix) <= count($path) && array_slice($path, 0, count($prefix)) === $prefix;
    }

    private static function index(mixed $value, int $maximum): int {
        if (!is_int($value) || $value < 0 || $value > $maximum) throw new Problem(422, 'Некорректная позиция компонента.');
        return $value;
    }

    private static function identifier(mixed $value): string {
        if (!is_string($value) || $value === '' || mb_strlen($value, 'UTF-8') > 128 || preg_match('/[\x00-\x1F\x7F]/', $value)) throw new Problem(422, 'Некорректный ID компонента или страницы.');
        return $value;
    }

    private static function fields(\stdClass $object, array $allowed, array $required): void {
        $fields = array_keys(get_object_vars($object));
        if (array_diff($fields, $allowed) || array_diff($required, $fields)) throw new Problem(422, 'Операция содержит неизвестные или отсутствующие поля.');
    }

    private static function &atPath(mixed &$root, array $path): mixed {
        foreach ($path as $segment) {
            if (is_int($segment)) $root =& $root[$segment];
            else $root =& $root->{$segment};
        }
        return $root;
    }
}
