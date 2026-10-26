<?php
declare(strict_types=1);
namespace Juzimder;
require_once __DIR__ . '/ProjectSettings.php';
require_once __DIR__ . '/IconCatalogue.php';
require_once __DIR__ . '/BackgroundImage.php';
require_once __DIR__ . '/CustomCode.php';
require_once __DIR__ . '/ZeroBlock.php';
require_once __DIR__ . '/MigrationRunner.php';
require_once __DIR__ . '/SkillRequirements.php';

final class Problem extends \RuntimeException {
    public function __construct(public int $status, string $message) { parent::__construct($message); }
}
final class Document {
    public const MAX_PAGES = 30;
    public const MAX_PAGE_ID_LENGTH = 128;
    public const FRAMES_PER_PAGE = 1;
    public const COMPONENT_TYPES = ['', 'default','wrapper','text','textnode','image','link','link-block','head','form','input','textarea','select','option','checkbox','radio','button','label','custom-code','countdown','zero-block'];
    public const TAG_NAMES = ['html','head','body','div','section','header','footer','nav','main','article','aside','h1','h2','h3','h4','p','span','a','img','button','ul','ol','li','strong','em','b','i','br','hr','label','form','input','textarea','select','option','details','summary','figure','figcaption','time'];
    public static function empty(): array {
        return ['schemaVersion' => 1, 'engine' => 'grapesjs', 'data' => ['assets' => [], 'styles' => [], 'pages' => [['id' => 'home', 'name' => 'Главная', 'frames' => [['component' => ['type' => 'wrapper', 'style' => ['display' => 'flex', 'flex-direction' => 'column'], 'components' => []]]]]]]];
    }
    public static function validate(mixed $doc): void {
        $document = self::objectFields($doc);
        if ($document === null || ($document['schemaVersion'] ?? null) !== 1 || ($document['engine'] ?? null) !== 'grapesjs') throw new Problem(422, 'Неподдерживаемая схема проекта.');
        $data = $document['data'] ?? null;
        $dataFields = self::objectFields($data);
        if ($dataFields === null) throw new Problem(422, 'Неподдерживаемая схема проекта.');
        foreach (['styles','assets'] as $field) if (array_key_exists($field, $dataFields) && (!is_array($dataFields[$field]) || !array_is_list($dataFields[$field]))) throw new Problem(422, 'Некорректный список ' . $field);

        $pages = $dataFields['pages'] ?? null;
        if (!is_array($pages) || !array_is_list($pages) || count($pages) < 1 || count($pages) > self::MAX_PAGES) throw new Problem(422, 'Нужно от 1 до ' . self::MAX_PAGES . ' страниц.');
        $ids = [];
        foreach ($pages as $page) {
            $pageFields = self::objectFields($page);
            $pageId = $pageFields['id'] ?? null;
            $frames = $pageFields['frames'] ?? null;
            if ($pageFields === null || !is_string($pageId) || $pageId === '' || mb_strlen($pageId, 'UTF-8') > self::MAX_PAGE_ID_LENGTH || isset($ids[$pageId]) || !is_array($frames) || !array_is_list($frames) || count($frames) !== self::FRAMES_PER_PAGE) throw new Problem(422, 'Некорректная страница.');
            if (array_key_exists('name', $pageFields) && !is_string($pageFields['name'])) throw new Problem(422, 'Некорректное название страницы.');
            $frame = self::objectFields($frames[0]);
            if ($frame === null || !array_key_exists('component', $frame)) throw new Problem(422, 'Некорректный frame страницы.');
            $ids[$pageId] = true;
            self::component($frame['component'], 0);
        }
        $zeroIds = [];
        $checkZeroIds = function($node) use (&$checkZeroIds, &$zeroIds): void {
            if (is_string($node->attributes->id ?? null)) { $id=$node->attributes->id;$isZero=($node->type??null)==='zero-block';if(isset($zeroIds[$id])&&($zeroIds[$id]||$isZero))throw new Problem(422,'Стабильные ID Zero Block должны быть уникальны.');$zeroIds[$id]=$isZero; }
            foreach ($node->components ?? [] as $child) $checkZeroIds($child);
        };
        foreach ($pages as $page) { $p=self::objectFields($page);$f=self::objectFields($p['frames'][0]);if($f['component'] instanceof \stdClass)$checkZeroIds($f['component']); }
        $count = 0;
        self::walk($data, 0, $count);
    }
    private static function component(mixed $node, int $depth): void {
        $fields = self::objectFields($node);
        if ($fields === null || $depth > 35) throw new Problem(422,'Некорректное дерево компонентов.');
        if (($fields['style'] ?? null) === '' && $node instanceof \stdClass) {
            $node->style = new \stdClass();
            $fields['style'] = $node->style;
        }
        foreach (['attributes','style'] as $field) if (array_key_exists($field, $fields) && self::objectFields($fields[$field]) === null) throw new Problem(422,'Некорректное свойство компонента: ' . $field . '.');
        if (array_key_exists('content', $fields) && !is_string($fields['content'])) throw new Problem(422,'Некорректный текст компонента.');
        if (($fields['tagName'] ?? null) === 'figcaption' && (($fields['type'] ?? null) !== 'text' || !is_string($fields['content'] ?? null) || mb_strlen($fields['content'], 'UTF-8') > 500 || !empty($fields['components']))) throw new Problem(422,'Подпись фото должна быть простым текстом длиной до 500 символов.');
        if (array_key_exists('type', $fields) && (!is_string($fields['type']) || !in_array($fields['type'], self::COMPONENT_TYPES, true))) throw new Problem(422,'Недопустимый тип компонента.');
        if (($fields['type'] ?? null) === 'zero-block') {
            $attributes = self::objectFields($fields['attributes'] ?? null) ?? [];
            if (!is_string($attributes['id'] ?? null) || !preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,127}$/D', $attributes['id']) || !empty($fields['components']) || !empty($fields['content'])) throw new Problem(422, 'Zero Block должен иметь стабильный ID и единственный zeroData.');
            ZeroBlock::validate($fields['zeroData'] ?? null);
        } elseif (array_key_exists('zeroData', $fields)) throw new Problem(422, 'zeroData допустим только у Zero Block.');
        if (($fields['type'] ?? null) === 'custom-code') {
            if (!is_string($fields['content'] ?? null) || (isset($fields['components']) && $fields['components'] !== [])) throw new Problem(422, 'Custom Code должен хранить только изолированный блок HTML, CSS и JavaScript.');
            CustomCode::decode($fields['content']);
        }
        if (($fields['type'] ?? null) === 'countdown') {
            $attributes = self::objectFields($fields['attributes'] ?? null) ?? [];
            CustomCode::countdownPayload($attributes['data-countdown-end'] ?? null);
        }
        if (array_key_exists('components', $fields)) {
            if (!is_array($fields['components']) || !array_is_list($fields['components'])) throw new Problem(422,'Дочерние компоненты должны быть списком.');
            foreach ($fields['components'] as $child) self::component($child, $depth+1);
        }
        foreach (['head','docEl'] as $field) if (array_key_exists($field, $fields)) self::component($fields[$field],$depth+1);
    }
    private static function walk(mixed $value, int $depth, int &$count): void {
        if (++$count > 50000 || $depth > 45) throw new Problem(422, 'Проект слишком сложный.');
        if (is_array($value) || $value instanceof \stdClass) {
            foreach (self::objectFields($value) ?? $value as $key => $item) {
                // zeroData has its own closed typed schema and HTML payload validator.
                if ($key === 'zeroData' && $value instanceof \stdClass && ($value->type ?? null) === 'zero-block') continue;
                if (is_string($key) && (preg_match('/^on/i', $key) || in_array(strtolower($key), ['script', 'script-export', 'srcdoc', 'srcset', 'formaction', 'action'], true))) throw new Problem(422, 'Исполняемый код запрещён.');
                if ($key === 'tagName' && (!is_string($item) || !in_array($item, self::TAG_NAMES, true))) throw new Problem(422, 'Недопустимый HTML-тег.');
                if (in_array($key, ['href','src'], true)) {
                    $validUri = is_string($item) && preg_match('~^(https://[^\s<>"\x00-\x20]+|\#[a-zA-Z0-9_-]*|mailto:[a-zA-Z0-9@._+-]+|tel:[+0-9 -]+)$~D', $item);
                    $validAsset = $key === 'src' && is_string($item) && preg_match('~^/api/projects/[a-f0-9]{24}/assets/[a-f0-9]{24}/file$~D', $item);
                    if (!$validUri && !$validAsset && !($key === 'src' && IconCatalogue::permits($item))) throw new Problem(422, 'Недопустимый адрес ресурса.');
                }
                if ($key === 'background-image') { if (!BackgroundImage::valid($item)) throw new Problem(422, 'Недопустимое фоновое изображение.'); continue; }
                self::walk($item, $depth + 1, $count);
            }
        } elseif (is_string($value)) {
            $policyValue = self::normalizePolicyString($value);
            if (preg_match('/(?:javascript\s*:|vbscript\s*:|expression\s*\(|url\s*\(|@import|<\?)/i', $policyValue)) throw new Problem(422, 'Небезопасное содержимое.');
            if (str_contains($value, '<')) self::html($value);
        }
    }
    private static function normalizePolicyString(string $value): string {
        $value = preg_replace('~/\*.*?\*/~s', '', $value) ?? $value;
        return preg_replace_callback('~\\\\(?:([0-9a-f]{1,6})(?:[ \t\r\n\f])?|([\r\n\f])|([^\r\n\f]))~i', static function (array $match): string {
            if (($match[1] ?? '') !== '') {
                $codepoint = hexdec($match[1]);
                return $codepoint <= 0x7f ? chr($codepoint) : "\u{FFFD}";
            }
            return $match[3] ?? '';
        }, $value) ?? $value;
    }
    private static function objectFields(mixed $value): ?array {
        if ($value instanceof \stdClass) return get_object_vars($value);
        if (is_array($value) && !array_is_list($value)) return $value;
        return null;
    }
    /** @return list<array{projectId:string,assetId:string}> */
    public static function assetReferences(mixed $document): array {
        $references = [];
        $walk = static function (mixed $value) use (&$walk, &$references): void {
            if ($value instanceof \stdClass) $fields = get_object_vars($value);
            elseif (is_array($value)) $fields = $value;
            else return;
            foreach ($fields as $key => $item) {
                if ($key === 'background-image') $item = BackgroundImage::source($item);
                if (in_array($key,['src','background-image','backgroundImage'],true) && is_string($item) && preg_match('~^/api/projects/([a-f0-9]{24})/assets/([a-f0-9]{24})/file$~D', $item, $match)) {
                    $references[$match[1] . ':' . $match[2]] = ['projectId'=>$match[1], 'assetId'=>$match[2]];
                    if (count($references) > 200) throw new Problem(422, 'В одном проекте можно использовать не более 200 изображений.');
                }
                $walk($item);
            }
        };
        $walk($document);
        return array_values($references);
    }
    private static function html(string $html): void {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<html><body>' . $html . '</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors(); libxml_use_internal_errors($previous);
        foreach ($dom->getElementsByTagName('*') as $el) {
            if (!in_array(strtolower($el->tagName), ['html','body','p','span','br','strong','em','b','i','u','s'], true)) throw new Problem(422, 'В тексте разрешено только базовое форматирование.');
            foreach ($el->attributes as $attr) {
                $policyValue = self::normalizePolicyString($attr->value);
                if (!in_array($attr->name, ['style','class'], true) || preg_match('/[<>]/', $policyValue) || preg_match('/(?:javascript\s*:|vbscript\s*:|expression\s*\(|url\s*\(|@import)/i', $policyValue)) throw new Problem(422, 'Небезопасный атрибут текста.');
            }
        }
    }
}
final class Store {
    public \PDO $db;
    public readonly string $path;
    public function __construct(string $path) {
        $this->path = $path;
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
        $this->db = new \PDO('sqlite:' . $path, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $this->db->exec('PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000;');
        (new MigrationRunner($this->db))->run();
        $this->backfillProjectSlugs();
    }
    public function query(string $sql, array $args = []): \PDOStatement { $stmt = $this->db->prepare($sql); $stmt->execute($args); return $stmt; }
    private function projectSlugBase(string $name): string {
        $name = mb_strtolower(trim($name), 'UTF-8');
        $map = ['а'=>'a','ә'=>'a','б'=>'b','в'=>'v','г'=>'g','ғ'=>'gh','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh','з'=>'z','и'=>'i','й'=>'i','к'=>'k','қ'=>'q','л'=>'l','м'=>'m','н'=>'n','ң'=>'ng','о'=>'o','ө'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ұ'=>'u','ү'=>'u','ф'=>'f','х'=>'kh','һ'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'shch','ъ'=>'','ы'=>'y','і'=>'i','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya'];
        $name = strtr($name, $map);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $name) ?? '';
        $slug = trim($slug, '-');
        if ($slug === '') $slug = 'project';
        return substr($slug, 0, 80);
    }
    private function uniqueProjectSlug(string $name): string {
        $base = $this->projectSlugBase($name);
        $slug = $base; $suffix = 2;
        while ($this->query('SELECT 1 FROM projects WHERE slug=?', [$slug])->fetchColumn()) {
            $tail = '-'.$suffix++;
            $slug = rtrim(substr($base, 0, 80 - strlen($tail)), '-').$tail;
        }
        return $slug;
    }
    private function backfillProjectSlugs(): void {
        if (!$this->query("SELECT 1 FROM projects WHERE slug IS NULL OR slug='' LIMIT 1")->fetchColumn()) return;
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            foreach ($this->query("SELECT id,name FROM projects WHERE slug IS NULL OR slug='' ORDER BY created_at,id")->fetchAll() as $row) {
                $this->query('UPDATE projects SET slug=? WHERE id=?', [$this->uniqueProjectSlug((string)$row['name']), $row['id']]);
            }
            $this->db->commit();
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }
    public function resolveProjectSlug(string $slug, string $user): array {
        $row = $this->query('SELECT id,slug FROM projects WHERE slug=? AND owner_id=?', [$slug,$user])->fetch();
        if (!$row) throw new Problem(404, 'Проект не найден.');
        return $row;
    }
    public function owned(string $id, string $user): array {
        $row = $this->query('SELECT p.id,p.slug,p.name,p.settings_payload,d.version,d.payload,d.updated_at FROM projects p JOIN documents d ON p.id=d.project_id WHERE p.id=? AND p.owner_id=?', [$id,$user])->fetch();
        if (!$row) throw new Problem(404, 'Проект не найден.');
        $row['document'] = json_decode($row['payload'], false, 64, JSON_THROW_ON_ERROR);
        $settings = json_decode($row['settings_payload'] ?: '{}', true, 64, JSON_THROW_ON_ERROR);
        $row['settings'] = ProjectSettings::reconcile($settings, $row['document']);
        unset($row['payload'], $row['settings_payload']); return $row;
    }
    public function publication(string $id, string $user): ?array {
        $this->owned($id, $user);
        $row = $this->query('SELECT v.id,v.version,v.source_project_version,v.created_at FROM project_publications p JOIN publication_versions v ON v.project_id=p.project_id AND v.id=p.publication_id WHERE p.project_id=?', [$id])->fetch();
        if (!$row) return null;
        return ['id'=>$row['id'],'version'=>(int)$row['version'],'sourceProjectVersion'=>(int)$row['source_project_version'],'createdAt'=>$row['created_at']];
    }
    public function publish(string $id, string $user, int $expectedVersion, array $files): array {
        $validatedFiles = [];
        foreach ($files as $path => $contents) {
            $isMedia = is_string($path) && preg_match('~^media/([a-f0-9]{24})/([a-f0-9]{24})\.(jpg|png|gif|webp)$~D', $path, $mediaMatch);
            if (!is_string($path) || (!preg_match('~^(?:index\.html|404\.html|assets/site\.css|forms\.json|README\.txt|[a-z0-9]+(?:-[a-z0-9]+)*/index\.html)$~D', $path) && !$isMedia) || !is_string($contents)) {
                throw new Problem(422, 'Статический снимок содержит недопустимый файл.');
            }
            if ($isMedia) {
                if ($mediaMatch[1] !== $id) throw new Problem(422, 'Публикация не может включать изображения другого проекта.');
                $mime = ['jpg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp'][$mediaMatch[3]];
                if (strlen($contents) > 4194304) throw new Problem(413, 'Изображение в публикации превышает 4 МБ.');
                $validatedFiles[$path] = ['encoding'=>'base64','mime'=>$mime,'content'=>base64_encode($contents)];
            } else {
                if ($path === 'forms.json') {
                    try { $formManifest = json_decode($contents, true, 16, JSON_THROW_ON_ERROR); } catch (\Throwable) { throw new Problem(422, 'Некорректный манифест форм.'); }
                    if (!is_array($formManifest) || ($formManifest['version'] ?? null) !== 1 || !is_array($formManifest['forms'] ?? null) || count($formManifest['forms']) > 100) throw new Problem(422, 'Некорректный манифест форм.');
                    foreach ($formManifest['forms'] as $formId=>$form) {
                        if (!is_string($formId) || !preg_match('/^[a-f0-9]{24}$/D',$formId) || !is_array($form) || !is_string($form['pagePath'] ?? null) || !preg_match('~^(?:index\.html|[a-z0-9]+(?:-[a-z0-9]+)*/index\.html)$~D',$form['pagePath']) || !is_array($form['fields'] ?? null) || count($form['fields']) < 1 || count($form['fields']) > 20) throw new Problem(422, 'Некорректная форма в снимке публикации.');
                        $names=[]; foreach ($form['fields'] as $field) if (!is_array($field) || !is_string($field['name'] ?? null) || !preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/D',$field['name']) || isset($names[$field['name']]) || !in_array($field['type'] ?? null,['text','email','tel','number','textarea','select'],true) || !is_bool($field['required'] ?? null)) throw new Problem(422, 'Некорректное поле формы.'); else $names[$field['name']]=true;
                    }
                }
                $validatedFiles[$path] = $contents;
            }
        }
        if (!isset($validatedFiles['index.html'], $validatedFiles['assets/site.css'])) throw new Problem(422, 'Статический снимок неполон.');
        $filesJson = json_encode($validatedFiles, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (strlen($filesJson) > 32000000) throw new Problem(413, 'Публикация превышает 32 МБ.');
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $project = $this->owned($id, $user);
            if ($project['version'] !== $expectedVersion) throw new Problem(409, 'Проект изменился. Обновите дашборд и повторите публикацию.');
            $version = (int)$this->query('SELECT COALESCE(MAX(version),0)+1 FROM publication_versions WHERE project_id=?', [$id])->fetchColumn();
            $publicationId = bin2hex(random_bytes(12));
            $this->query('INSERT INTO publication_versions(id,project_id,version,source_project_version,files_json) VALUES(?,?,?,?,?)', [$publicationId,$id,$version,$expectedVersion,$filesJson]);
            $this->query('INSERT INTO project_publications(project_id,publication_id) VALUES(?,?) ON CONFLICT(project_id) DO UPDATE SET publication_id=excluded.publication_id', [$id,$publicationId]);
            $this->db->commit();
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
        return $this->publication($id, $user) ?? throw new Problem(500, 'Не удалось сохранить публикацию.');
    }
    public function unpublish(string $id, string $user, int $expectedVersion): ?array {
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $project = $this->owned($id, $user);
            if ($project['version'] !== $expectedVersion) throw new Problem(409, 'Проект изменился. Обновите дашборд и повторите действие.');
            $this->query('DELETE FROM project_publications WHERE project_id=?', [$id]);
            $this->db->commit();
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
        return $this->publication($id, $user);
    }
    public function publicFile(string $id, string $path): ?array {
        if (!preg_match('/^[a-f0-9]{24}$/D', $id)) return null;
        $row = $this->query('SELECT v.files_json,v.version FROM project_publications p JOIN publication_versions v ON v.project_id=p.project_id AND v.id=p.publication_id WHERE p.project_id=?', [$id])->fetch();
        if (!$row) return null;
        try { $files = json_decode($row['files_json'], true, 32, JSON_THROW_ON_ERROR); } catch (\Throwable) { return null; }
        if (!is_array($files) || !array_key_exists($path, $files)) return null;
        if (preg_match('~^media/([a-f0-9]{24})/([a-f0-9]{24})\.(jpg|png|gif|webp)$~D', $path, $match)) {
            if ($match[1] !== $id) return null;
            $entry = $files[$path];
            if (!is_array($entry) || ($entry['encoding'] ?? null) !== 'base64' || !is_string($entry['content'] ?? null)) return null;
            $content = base64_decode($entry['content'], true);
            $mime = ['jpg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp'][$match[3]];
            if (!is_string($content) || strlen($content) > 4194304 || ($entry['mime'] ?? null) !== $mime) return null;
            return ['content'=>$content,'mime'=>$mime,'version'=>(int)$row['version']];
        }
        if (!is_string($files[$path])) return null;
        return ['content'=>$files[$path],'mime'=>$path === 'assets/site.css' ? 'text/css; charset=utf-8' : 'text/html; charset=utf-8','version'=>(int)$row['version']];
    }
    public function acceptFormSubmission(string $projectId, string $formId, array $values, string $sourceIp, ?callable $afterAccept = null): array {
        if (!preg_match('/^[a-f0-9]{24}$/D', $projectId) || !preg_match('/^[a-f0-9]{24}$/D', $formId)) throw new Problem(422, 'Форма не найдена.');
        $publication = $this->query('SELECT v.files_json FROM project_publications p JOIN publication_versions v ON v.project_id=p.project_id AND v.id=p.publication_id WHERE p.project_id=?', [$projectId])->fetchColumn();
        if (!is_string($publication)) throw new Problem(404, 'Форма больше не опубликована.');
        try { $files = json_decode($publication, true, 32, JSON_THROW_ON_ERROR); $manifest = json_decode((string)($files['forms.json'] ?? ''), true, 16, JSON_THROW_ON_ERROR); }
        catch (\Throwable) { throw new Problem(404, 'Форма больше не опубликована.'); }
        $schema = $manifest['forms'][$formId] ?? null;
        if (!is_array($schema) || !is_array($schema['fields'] ?? null) || !is_string($schema['pagePath'] ?? null)) throw new Problem(404, 'Форма больше не опубликована.');
        if (array_diff(array_keys($values), array_merge(array_column($schema['fields'], 'name'), ['website']))) throw new Problem(422, 'Форма содержит неизвестные поля.');
        if (isset($values['website']) && (!is_string($values['website']) || $values['website'] !== '')) return ['accepted'=>true];
        unset($values['website']);
        $clean = [];
        foreach ($schema['fields'] as $field) {
            $name = $field['name'] ?? null;
            if (!is_string($name) || !preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/D', $name)) throw new Problem(422, 'Определение формы повреждено.');
            $value = $values[$name] ?? '';
            if (!is_string($value) || strlen($value) > 4000 || !mb_check_encoding($value, 'UTF-8') || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value)) throw new Problem(422, 'Проверьте значения полей формы.');
            $value = trim($value);
            if (($field['required'] ?? false) && $value === '') throw new Problem(422, 'Заполните обязательные поля.');
            if ($value !== '' && ($field['type'] ?? '') === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) throw new Problem(422, 'Укажите корректный email.');
            $clean[$name] = $value;
        }
        $ipHash = hash('sha256', $projectId . ':' . $sourceIp);
        $now = time();
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $limit = $this->query('SELECT window_started_at,requests FROM project_form_rate_limits WHERE project_id=? AND source_ip_hash=?', [$projectId,$ipHash])->fetch();
            if ($limit && (int)$limit['window_started_at'] > $now - 60 && (int)$limit['requests'] >= 5) throw new Problem(429, 'Слишком много отправок. Повторите через минуту.');
            if ($limit && (int)$limit['window_started_at'] > $now - 60) $this->query('UPDATE project_form_rate_limits SET requests=requests+1 WHERE project_id=? AND source_ip_hash=?', [$projectId,$ipHash]);
            else $this->query('INSERT INTO project_form_rate_limits(project_id,source_ip_hash,window_started_at,requests) VALUES(?,?,?,1) ON CONFLICT(project_id,source_ip_hash) DO UPDATE SET window_started_at=excluded.window_started_at,requests=1', [$projectId,$ipHash,$now]);
            $id = bin2hex(random_bytes(12));
            $this->query('INSERT INTO project_form_submissions(id,project_id,form_id,page_path,fields_json,source_ip_hash) VALUES(?,?,?,?,?,?)', [$id,$projectId,$formId,$schema['pagePath'],json_encode($clean, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),$ipHash]);
            $this->query('DELETE FROM project_form_submissions WHERE project_id=? AND id NOT IN (SELECT id FROM project_form_submissions WHERE project_id=? ORDER BY created_at DESC,id DESC LIMIT 1000)', [$projectId,$projectId]);
            $this->db->commit();
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
        if ($afterAccept !== null) {
            try { $afterAccept($clean, $schema['pagePath']); }
            catch (\Throwable) { /* Persisted submissions remain accepted if optional delivery fails. */ }
        }
        return ['accepted'=>true];
    }
    public function consumeFormMailTestAllowance(string $projectId, string $userId, ?int $now = null): void {
        $this->owned($projectId, $userId);
        $now ??= time();
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $row = $this->query('SELECT window_started_at,requests FROM project_form_mail_test_limits WHERE project_id=? AND user_id=?', [$projectId,$userId])->fetch();
            if ($row && (int)$row['window_started_at'] > $now - 600 && (int)$row['requests'] >= 3) throw new Problem(429, 'Слишком много проверок SMTP. Повторите через 10 минут.');
            if ($row && (int)$row['window_started_at'] > $now - 600) {
                $this->query('UPDATE project_form_mail_test_limits SET requests=requests+1 WHERE project_id=? AND user_id=?', [$projectId,$userId]);
            } else {
                $this->query('INSERT INTO project_form_mail_test_limits(project_id,user_id,window_started_at,requests) VALUES(?,?,?,1) ON CONFLICT(project_id,user_id) DO UPDATE SET window_started_at=excluded.window_started_at,requests=1', [$projectId,$userId,$now]);
            }
            $this->db->commit();
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }
    public function listFormSubmissions(string $projectId, string $user): array {
        $this->owned($projectId,$user);
        $rows = $this->query('SELECT id,form_id,page_path,fields_json,created_at FROM project_form_submissions WHERE project_id=? ORDER BY created_at DESC,id DESC LIMIT 100', [$projectId])->fetchAll();
        return array_map(static function(array $row): array { $fields = json_decode($row['fields_json'], true, 16, JSON_THROW_ON_ERROR); return ['id'=>$row['id'],'formId'=>$row['form_id'],'pagePath'=>$row['page_path'],'fields'=>$fields,'createdAt'=>$row['created_at']]; }, $rows);
    }
    public function deleteFormSubmission(string $projectId, string $user, string $submissionId): void {
        $this->owned($projectId,$user);
        if (!preg_match('/^[a-f0-9]{24}$/D',$submissionId)) throw new Problem(422,'Некорректная заявка.');
        $this->query('DELETE FROM project_form_submissions WHERE project_id=? AND id=?', [$projectId,$submissionId]);
    }
    public function listAssets(string $id, string $user): array {
        $this->owned($id, $user);
        $rows = $this->query('SELECT id,name,mime,byte_length,width,height,created_at FROM project_assets WHERE project_id=? ORDER BY created_at DESC,id DESC', [$id])->fetchAll();
        return array_map(static fn(array $row): array => ['id'=>$row['id'],'name'=>$row['name'],'mime'=>$row['mime'],'size'=>(int)$row['byte_length'],'width'=>(int)$row['width'],'height'=>(int)$row['height'],'createdAt'=>$row['created_at'],'url'=>'/api/projects/' . $id . '/assets/' . $row['id'] . '/file'], $rows);
    }
    public function backup(string $id, string $user): array {
        $project = $this->owned($id, $user);
        $assets = $this->query('SELECT id,name,mime,byte_length,width,height,sha256,content FROM project_assets WHERE project_id=? ORDER BY id', [$id])->fetchAll();
        $packedAssets = [];
        foreach ($assets as $asset) {
            $content = $asset['content'];
            if (!is_string($content) || strlen($content) !== (int)$asset['byte_length'] || !hash_equals((string)$asset['sha256'], hash('sha256', $content))) throw new Problem(500, 'Не удалось проверить изображение для резервной копии.');
            $packedAssets[] = ['id'=>$asset['id'],'name'=>$asset['name'],'mime'=>$asset['mime'],'width'=>(int)$asset['width'],'height'=>(int)$asset['height'],'sha256'=>$asset['sha256'],'content'=>base64_encode($content)];
        }
        $skillState = $this->query('SELECT active_skill_id,version FROM project_ai_skill_state WHERE project_id=?', [$id])->fetch() ?: ['active_skill_id'=>null,'version'=>0];
        $skills = $this->query('SELECT id,name,content,manual_checks_json FROM project_ai_skills WHERE project_id=? ORDER BY created_at,id', [$id])->fetchAll();
        $packedSkills = array_map(static fn(array $skill): array => ['id'=>$skill['id'],'name'=>$skill['name'],'content'=>$skill['content'],'manualChecks'=>json_decode($skill['manual_checks_json'],true,16,JSON_THROW_ON_ERROR)], $skills);
        return ['format'=>'juzimder-project-backup','backupVersion'=>1,'createdAt'=>gmdate('c'),'sourceProjectId'=>$id,'project'=>['name'=>$project['name'],'slug'=>$project['slug'],'document'=>$project['document'],'settings'=>$project['settings'],'assets'=>$packedAssets,'aiSkills'=>['version'=>(int)$skillState['version'],'activeSkillId'=>$skillState['active_skill_id'],'skills'=>$packedSkills]]];
    }
    public function restoreBackup(string $id, string $user, int $version, mixed $backup): array {
        $this->owned($id, $user);
        $fields = $backup instanceof \stdClass ? get_object_vars($backup) : (is_array($backup) && !array_is_list($backup) ? $backup : null);
        $projectValue = $fields['project'] ?? null;
        if ($fields === null || ($fields['format'] ?? null) !== 'juzimder-project-backup' || ($fields['backupVersion'] ?? null) !== 1 || ($fields['sourceProjectId'] ?? null) !== $id || !($projectValue instanceof \stdClass || (is_array($projectValue) && !array_is_list($projectValue)))) throw new Problem(422, 'Файл не является резервной копией этого проекта или имеет неподдерживаемую версию.');
        $project = $projectValue instanceof \stdClass ? get_object_vars($projectValue) : $projectValue;
        if (array_diff(array_keys($project), ['name','slug','document','settings','assets','aiSkills']) || !is_string($project['name'] ?? null) || trim($project['name']) === '' || mb_strlen(trim($project['name']), 'UTF-8') > 100 || !is_string($project['slug'] ?? null) || !is_array($project['assets'] ?? null) || !array_is_list($project['assets']) || count($project['assets']) > 200) throw new Problem(422, 'Резервная копия содержит некорректные данные проекта.');
        $slug = ProjectSettings::validateSlug($project['slug']);
        $document = $project['document'] ?? null;
        Document::validate($document);
        $backupSettings = $project['settings'] ?? null;
        if ($backupSettings instanceof \stdClass) $backupSettings = json_decode(json_encode($backupSettings, JSON_THROW_ON_ERROR), true, 64, JSON_THROW_ON_ERROR);
        $settings = ProjectSettings::reconcile($backupSettings, $document);
        $payload = json_encode($document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $settingsPayload = json_encode($settings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        if (strlen($payload) > 2000000) throw new Problem(413, 'Данные проекта в резервной копии превышают лимит 2 МБ.');
        $assets = []; $totalBytes = 0;
        foreach ($project['assets'] as $asset) {
            if (!$asset instanceof \stdClass && (!is_array($asset) || array_is_list($asset))) throw new Problem(422, 'Некорректная запись изображения в резервной копии.');
            $item = $asset instanceof \stdClass ? get_object_vars($asset) : $asset; $assetId = $item['id'] ?? null; $name = $item['name'] ?? null; $mime = $item['mime'] ?? null; $encoded = $item['content'] ?? null;
            if (array_diff(array_keys($item), ['id','name','mime','width','height','sha256','content']) || !is_string($assetId) || !preg_match('/^[a-f0-9]{24}$/D', $assetId) || isset($assets[$assetId]) || !is_string($name) || trim($name) === '' || mb_strlen($name, 'UTF-8') > 160 || !is_string($mime) || !in_array($mime, ['image/jpeg','image/png','image/gif','image/webp'], true) || !is_string($encoded) || strlen($encoded) > 5592408) throw new Problem(422, 'Некорректные сведения об изображении в резервной копии.');
            $content = base64_decode($encoded, true); if (!is_string($content) || $content === '' || strlen($content) > 4194304) throw new Problem(422, 'Повреждено изображение в резервной копии.');
            $detectedMime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content); $image = @getimagesizefromstring($content); $expected = ['image/jpeg'=>IMAGETYPE_JPEG,'image/png'=>IMAGETYPE_PNG,'image/gif'=>IMAGETYPE_GIF,'image/webp'=>IMAGETYPE_WEBP];
            if ($detectedMime !== $mime || !is_array($image) || ($image[2] ?? null) !== $expected[$mime] || $image[0] < 1 || $image[1] < 1 || $image[0] > 8192 || $image[1] > 8192 || $image[0] * $image[1] > 40000000 || (int)($item['width'] ?? 0) !== $image[0] || (int)($item['height'] ?? 0) !== $image[1] || !is_string($item['sha256'] ?? null) || !hash_equals($item['sha256'], hash('sha256', $content))) throw new Problem(422, 'Изображение резервной копии не прошло проверку целостности.');
            $totalBytes += strlen($content); if ($totalBytes > 20000000) throw new Problem(413, 'Общий размер изображений превышает лимит проекта 20 МБ.');
            $assets[$assetId] = ['name'=>$name,'mime'=>$mime,'width'=>$image[0],'height'=>$image[1],'content'=>$content];
        }
        foreach (Document::assetReferences($document) as $reference) if ($reference['projectId'] !== $id || !isset($assets[$reference['assetId']])) throw new Problem(422, 'В резервной копии отсутствует изображение страницы или оно принадлежит другому проекту.');
        $skillsValue = $project['aiSkills'] ?? null;
        $skillState = $skillsValue instanceof \stdClass ? get_object_vars($skillsValue) : (is_array($skillsValue) && !array_is_list($skillsValue) ? $skillsValue : null);
        if ($skillState === null || !is_int($skillState['version'] ?? null) || $skillState['version'] < 0 || !is_array($skillState['skills'] ?? null) || !array_is_list($skillState['skills']) || count($skillState['skills']) > 10 || !(($skillState['activeSkillId'] ?? null) === null || is_string($skillState['activeSkillId']))) throw new Problem(422, 'Некорректная библиотека AI Skills в резервной копии.');
        $packedSkills=[];
        foreach ($skillState['skills'] as $skillValue) {
            $skill=$skillValue instanceof \stdClass ? get_object_vars($skillValue) : (is_array($skillValue) && !array_is_list($skillValue) ? $skillValue : null);
            if ($skill===null || array_diff(array_keys($skill),['id','name','content','manualChecks']) || !is_string($skill['id']??null) || !preg_match('/^[a-f0-9]{24}$/D',$skill['id']) || isset($packedSkills[$skill['id']]) || !is_string($skill['name']??null) || trim($skill['name'])==='' || mb_strlen($skill['name'],'UTF-8')>64 || !is_string($skill['content']??null) || trim($skill['content'])==='' || mb_strlen($skill['content'],'UTF-8')>16000 || !is_array($skill['manualChecks']??null) || !array_is_list($skill['manualChecks']) || count($skill['manualChecks'])<1 || count($skill['manualChecks'])>12) throw new Problem(422,'Некорректная запись AI Skill в резервной копии.');
            foreach($skill['manualChecks'] as $check) if(!is_string($check)||trim($check)===''||mb_strlen($check,'UTF-8')>240) throw new Problem(422,'Некорректный пункт проверки AI Skill в резервной копии.');
            try { SkillRequirements::parse($skill['content']); } catch (\InvalidArgumentException $error) { throw new Problem(422,$error->getMessage()); }
            $packedSkills[$skill['id']]=['name'=>trim($skill['name']),'content'=>$skill['content'],'checks'=>$skill['manualChecks']];
        }
        $activeSkillId=$skillState['activeSkillId'];if($activeSkillId!==null&&!isset($packedSkills[$activeSkillId])) throw new Problem(422,'Активный AI Skill отсутствует в резервной копии.');
        $current = $this->owned($id, $user); if ($current['version'] !== $version) throw new Problem(409, 'Проект изменился. Обновите страницу и повторите восстановление.');
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $currentRow = $this->query('SELECT d.version FROM documents d JOIN projects p ON p.id=d.project_id WHERE d.project_id=? AND p.owner_id=?', [$id,$user])->fetchColumn();
            if ($currentRow === false || (int)$currentRow !== $version) throw new Problem(409, 'Проект изменился. Обновите страницу и повторите восстановление.');
            $slugOwner = $this->query('SELECT id FROM projects WHERE slug=? AND id<>?', [$slug,$id])->fetchColumn();
            if ($slugOwner !== false) throw new Problem(409, 'Адрес проекта из бэкапа уже занят. Измените адрес проекта и повторите восстановление.');
            $stmt = $this->query('UPDATE documents SET payload=?,version=version+1,updated_at=CURRENT_TIMESTAMP WHERE project_id=? AND version=?', [$payload,$id,$version]);
            if ($stmt->rowCount() !== 1) throw new Problem(409, 'Проект изменился. Обновите страницу и повторите восстановление.');
            $this->query('UPDATE projects SET name=?,slug=?,settings_payload=? WHERE id=? AND owner_id=?', [trim($project['name']),$slug,$settingsPayload,$id,$user]);
            $this->query('DELETE FROM project_assets WHERE project_id=?', [$id]);
            $insert = $this->db->prepare('INSERT INTO project_assets(id,project_id,name,mime,byte_length,width,height,sha256,content) VALUES(?,?,?,?,?,?,?,?,?)');
            foreach ($assets as $assetId=>$asset) { $values=[$assetId,$id,$asset['name'],$asset['mime'],strlen($asset['content']),$asset['width'],$asset['height'],hash('sha256',$asset['content']),$asset['content']]; foreach ($values as $index=>$value) $insert->bindValue($index+1,$value,$index===8 ? \PDO::PARAM_LOB : (is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR)); $insert->execute(); }
            $currentSkillVersion=$this->query('SELECT version FROM project_ai_skill_state WHERE project_id=?',[$id])->fetchColumn();$nextSkillVersion=$currentSkillVersion===false?1:(int)$currentSkillVersion+1;
            $this->query('DELETE FROM project_ai_skills WHERE project_id=?',[$id]);
            foreach($packedSkills as $skillId=>$skill){$this->query('INSERT INTO project_ai_skills(id,project_id,name,content,manual_checks_json) VALUES(?,?,?,?,?)',[$skillId,$id,$skill['name'],$skill['content'],json_encode($skill['checks'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);}
            $this->query('INSERT INTO project_ai_skill_state(project_id,active_skill_id,version) VALUES(?,?,?) ON CONFLICT(project_id) DO UPDATE SET active_skill_id=excluded.active_skill_id,version=excluded.version,updated_at=CURRENT_TIMESTAMP',[$id,$activeSkillId,$nextSkillVersion]);
            $this->query('INSERT INTO revisions(project_id,version,payload,settings_payload) VALUES(?,?,?,?)', [$id,$version+1,$payload,$settingsPayload]);
            $this->query('DELETE FROM revisions WHERE project_id=? AND version NOT IN (SELECT version FROM revisions WHERE project_id=? ORDER BY version DESC LIMIT 200)', [$id,$id]);
            $this->db->commit();
        } catch (\Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
        return $this->owned($id,$user);
    }
    public function uploadAsset(string $id, string $user, string $name, string $content): array {
        $this->owned($id, $user);
        if ($content === '' || strlen($content) > 4194304) throw new Problem(413, 'Изображение должно занимать не более 4 МБ.');
        $name = basename(str_replace('\\', '/', trim($name)));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        if ($name === '' || !mb_check_encoding($name, 'UTF-8') || mb_strlen($name, 'UTF-8') > 160) throw new Problem(422, 'Укажите корректное название файла длиной до 160 символов.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content);
        $image = @getimagesizefromstring($content);
        $supported = ['image/jpeg'=>IMAGETYPE_JPEG,'image/png'=>IMAGETYPE_PNG,'image/gif'=>IMAGETYPE_GIF,'image/webp'=>IMAGETYPE_WEBP];
        if (!is_string($mime) || !isset($supported[$mime]) || !is_array($image) || ($image[2] ?? null) !== $supported[$mime]) throw new Problem(422, 'Поддерживаются только корректные PNG, JPEG, GIF и WebP изображения.');
        $width = (int)$image[0]; $height = (int)$image[1];
        if ($width < 1 || $height < 1 || $width > 8192 || $height > 8192 || $width * $height > 40000000) throw new Problem(422, 'Размер изображения должен быть не больше 8192×8192 пикселей и 40 Мп.');
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $this->owned($id, $user);
            $usage = $this->query('SELECT COALESCE(SUM(byte_length),0) AS bytes,COUNT(*) AS count FROM project_assets WHERE project_id=?', [$id])->fetch();
            if ((int)$usage['count'] >= 200) throw new Problem(413, 'В проекте уже сохранено максимальное число изображений (200).');
            if ((int)$usage['bytes'] + strlen($content) > 20000000) throw new Problem(413, 'Хранилище проекта заполнено (лимит 20 МБ).');
            $assetId = bin2hex(random_bytes(12));
            $stmt = $this->db->prepare('INSERT INTO project_assets(id,project_id,name,mime,byte_length,width,height,sha256,content) VALUES(?,?,?,?,?,?,?,?,?)');
            foreach ([$assetId,$id,$name,$mime,strlen($content),$width,$height,hash('sha256',$content),$content] as $index=>$value) $stmt->bindValue($index+1,$value,$index===8 ? \PDO::PARAM_LOB : (is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR));
            $stmt->execute();
            $this->db->commit();
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
        return $this->assetSummary($id,$assetId);
    }
    private function assetSummary(string $projectId, string $assetId): array {
        $row = $this->query('SELECT id,name,mime,byte_length,width,height,created_at FROM project_assets WHERE project_id=? AND id=?', [$projectId,$assetId])->fetch();
        if (!$row) throw new Problem(404, 'Изображение не найдено.');
        return ['id'=>$row['id'],'name'=>$row['name'],'mime'=>$row['mime'],'size'=>(int)$row['byte_length'],'width'=>(int)$row['width'],'height'=>(int)$row['height'],'createdAt'=>$row['created_at'],'url'=>'/api/projects/' . $projectId . '/assets/' . $row['id'] . '/file'];
    }
    public function assetFile(string $id, string $user, string $assetId): array {
        $this->owned($id, $user);
        $row = $this->query('SELECT name,mime,byte_length,content,sha256 FROM project_assets WHERE project_id=? AND id=?', [$id,$assetId])->fetch();
        if (!$row) throw new Problem(404, 'Изображение не найдено.');
        if (!is_string($row['content']) || strlen($row['content']) !== (int)$row['byte_length'] || !hash_equals($row['sha256'], hash('sha256',$row['content']))) throw new Problem(500, 'Не удалось прочитать изображение.');
        return ['name'=>$row['name'],'mime'=>$row['mime'],'content'=>$row['content']];
    }
    /** @return array<string,array{id:string,name:string,mime:string,size:int,width:int,height:int,content:string}> */
    public function referencedAssets(string $id, string $user, mixed $document): array {
        $this->owned($id, $user);
        $assets = [];
        foreach (Document::assetReferences($document) as $reference) {
            if ($reference['projectId'] !== $id) throw new Problem(422, 'Изображение должно принадлежать текущему проекту.');
            $asset = $this->assetFile($id, $user, $reference['assetId']);
            $assets[$reference['assetId']] = $this->assetSummary($id, $reference['assetId']) + ['content'=>$asset['content']];
        }
        return $assets;
    }
    private function assertDocumentAssets(string $id, mixed $document): void {
        foreach (Document::assetReferences($document) as $reference) {
            if ($reference['projectId'] !== $id || !$this->query('SELECT 1 FROM project_assets WHERE project_id=? AND id=?', [$id,$reference['assetId']])->fetchColumn()) throw new Problem(422, 'Изображение не найдено в текущем проекте.');
        }
    }
    public function create(string $user, string $name): array {
        $name = trim($name); if ($name === '' || mb_strlen($name) > 100) throw new Problem(422,'Название: от 1 до 100 символов.');
        $id = bin2hex(random_bytes(12)); $document = Document::empty(); $payload = json_encode($document, JSON_THROW_ON_ERROR);
        $settings = json_encode(ProjectSettings::initial(json_decode($payload, false, 64, JSON_THROW_ON_ERROR)), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $this->db->beginTransaction();
        try { $slug=$this->uniqueProjectSlug($name); $this->query('INSERT INTO projects(id,owner_id,name,settings_payload,slug) VALUES(?,?,?,?,?)',[$id,$user,$name,$settings,$slug]); $this->query('INSERT INTO documents(project_id,payload) VALUES(?,?)',[$id,$payload]); $this->db->commit(); } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
        return $this->owned($id,$user);
    }
    public function renameProject(string $id, string $user, int $version, string $expectedName, string $name): array {
        $expectedName = trim($expectedName);
        $name = trim($name);
        if ($expectedName === '' || mb_strlen($expectedName, 'UTF-8') > 100 || $name === '' || mb_strlen($name, 'UTF-8') > 100) throw new Problem(422, 'Название: от 1 до 100 символов.');
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $row = $this->query('SELECT p.name,d.version FROM projects p JOIN documents d ON p.id=d.project_id WHERE p.id=? AND p.owner_id=?', [$id,$user])->fetch();
            if (!$row) throw new Problem(404, 'Проект не найден.');
            if ((int)$row['version'] !== $version || !hash_equals((string)$row['name'], $expectedName)) throw new Problem(409, 'Проект изменился в другой вкладке. Обновите страницу перед переименованием.');
            if ($name !== $row['name']) {
                $stmt = $this->query('UPDATE projects SET name=? WHERE id=? AND owner_id=? AND name=?', [$name,$id,$user,$expectedName]);
                if ($stmt->rowCount() !== 1) throw new Problem(409, 'Название проекта изменилось в другой вкладке. Обновите страницу и повторите попытку.');
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
        return $this->owned($id,$user);
    }
    public function save(string $id,string $user,int $version,mixed $doc,?array $settingsOverride=null): array {
        $current = $this->owned($id,$user); Document::validate($doc); $this->assertDocumentAssets($id,$doc);
        if ($current['version'] !== $version) throw new Problem(409,'На сервере есть новая версия. Экспортируйте свои изменения и откройте проект заново.');
        $settings = ProjectSettings::reconcile($settingsOverride ?? $current['settings'], $doc);
        $doc = ProjectSettings::applyDesignTokensToDocument($doc, $settings['designTokens']);
        Document::validate($doc);
        $payload = json_encode($doc, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $settingsPayload = json_encode($settings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        if (strlen($payload) > 2000000) throw new Problem(413,'Проект превышает 2 МБ.');
        $this->db->beginTransaction();
        try {
            $stmt = $this->query('UPDATE documents SET payload=?,version=version+1,updated_at=CURRENT_TIMESTAMP WHERE project_id=? AND version=?',[$payload,$id,$version]);
            if ($stmt->rowCount() !== 1) throw new Problem(409,'На сервере есть новая версия. Экспортируйте свои изменения и откройте проект заново.');
            $this->query('UPDATE projects SET settings_payload=? WHERE id=?',[$settingsPayload,$id]);
            $this->query('INSERT INTO revisions(project_id,version,payload,settings_payload) VALUES(?,?,?,?)',[$id,$version+1,$payload,$settingsPayload]);
            $this->query('DELETE FROM revisions WHERE project_id=? AND version NOT IN (SELECT version FROM revisions WHERE project_id=? ORDER BY version DESC LIMIT 200)',[$id,$id]);
            $this->db->commit();
        } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
        return $this->owned($id,$user);
    }
    public function restore(string $id,string $user,int $version,int $targetVersion): array {
        $this->owned($id,$user);
        $revision = $this->query('SELECT payload,settings_payload FROM revisions WHERE project_id=? AND version=?',[$id,$targetVersion])->fetch();
        if (!$revision) throw new Problem(404,'Ревизия не найдена.');
        $payload = $revision['payload'];
        $document = json_decode($payload,false,64,JSON_THROW_ON_ERROR);
        Document::validate($document);
        $this->assertDocumentAssets($id,$document);
        $settings = ProjectSettings::reconcile(json_decode($revision['settings_payload'] ?: '{}',true,64,JSON_THROW_ON_ERROR),$document);
        $settingsPayload = json_encode($settings,JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $this->db->beginTransaction();
        try {
            $stmt = $this->query('UPDATE documents SET payload=?,version=version+1,updated_at=CURRENT_TIMESTAMP WHERE project_id=? AND version=?',[$payload,$id,$version]);
            if ($stmt->rowCount() !== 1) throw new Problem(409,'Проект изменился в другой вкладке. Обновите его перед восстановлением.');
            $this->query('UPDATE projects SET settings_payload=? WHERE id=?',[$settingsPayload,$id]);
            $this->query('INSERT INTO revisions(project_id,version,payload,settings_payload) VALUES(?,?,?,?)',[$id,$version+1,$payload,$settingsPayload]);
            $this->query('DELETE FROM revisions WHERE project_id=? AND version NOT IN (SELECT version FROM revisions WHERE project_id=? ORDER BY version DESC LIMIT 200)',[$id,$id]);
            $this->db->commit();
        } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
        return $this->owned($id,$user);
    }
    public function pages(string $id,string $user): array {
        $project = $this->owned($id,$user);
        $result = [];
        foreach ($project['document']->data->pages as $page) {
            $route = $project['settings']['pages'][(string)$page->id];
            $result[] = ['id'=>(string)$page->id,'name'=>(string)($page->name ?? 'Страница'),'slug'=>$route['slug'],'isHome'=>$route['isHome'],'path'=>$route['isHome']?'/':'/'.$route['slug']];
        }
        return $result;
    }
    public function mutatePage(string $id,string $user,int $version,?string $pageId,string $action,?string $value=null): array {
        $project = $this->owned($id,$user);
        if ($project['version'] !== $version) throw new Problem(409,'Версия проекта изменилась. Обновите страницу проекта и повторите действие.');
        $document = $project['document'];
        $pages = $document->data->pages;
        $index = null;
        if ($pageId !== null) foreach ($pages as $i=>$page) if ((string)$page->id === $pageId) { $index=$i; break; }
        if ($pageId !== null && $index === null) throw new Problem(404,'Страница не найдена.');
        $settings = $project['settings'];
        switch ($action) {
            case 'create':
                if (count($pages) >= Document::MAX_PAGES) throw new Problem(422,'Достигнут лимит страниц проекта.');
                $name = self::pageName($value);
                $newId = bin2hex(random_bytes(12));
                $pages[] = (object)['id'=>$newId,'name'=>$name,'frames'=>[(object)['component'=>(object)['type'=>'wrapper','components'=>[]]]]];
                $document->data->pages = $pages;
                $settings['pages'][$newId] = ['slug'=>ProjectSettings::defaultSlug($name),'isHome'=>false,'seo'=>ProjectSettings::seoDefaults()];
                break;
            case 'rename':
                $name = self::pageName($value);
                $pages[$index]->name = $name;
                break;
            case 'slug':
                $slug = ProjectSettings::validateSlug($value ?? '');
                foreach ($settings['pages'] as $otherId=>$route) if ($otherId !== $pageId && $route['slug'] === $slug) throw new Problem(422,'Такая URL уже используется в этом проекте.');
                $settings['pages'][$pageId]['slug'] = $slug;
                break;
            case 'home':
                foreach ($settings['pages'] as &$route) $route['isHome'] = false;
                unset($route);
                $settings['pages'][$pageId]['isHome'] = true;
                break;
            case 'duplicate':
                if (count($pages) >= Document::MAX_PAGES) throw new Problem(422,'Достигнут лимит страниц проекта.');
                $copy = json_decode(json_encode($pages[$index],JSON_THROW_ON_ERROR),false,64,JSON_THROW_ON_ERROR);
                $copy->id = bin2hex(random_bytes(12));
                $copy->name = self::pageName((string)($pages[$index]->name ?? 'Страница') . ' — копия');
                self::remapElementIds($copy);
                array_splice($pages,$index+1,0,[$copy]);
                $document->data->pages = $pages;
                $settings['pages'][(string)$copy->id] = ['slug'=>ProjectSettings::defaultSlug($copy->name),'isHome'=>false,'seo'=>ProjectSettings::seoDefaults()];
                break;
            case 'delete':
                if (count($pages) <= 1) throw new Problem(422,'В проекте должна остаться хотя бы одна страница.');
                $wasHome = $settings['pages'][$pageId]['isHome'] === true;
                array_splice($pages,$index,1);
                unset($settings['pages'][$pageId]);
                if ($wasHome) $settings['pages'][(string)$pages[0]->id]['isHome'] = true;
                $document->data->pages = $pages;
                break;
            default:
                throw new Problem(422,'Неизвестное действие со страницей.');
        }
        $settings = ProjectSettings::reconcile($settings,$document);
        return $this->save($id,$user,$version,$document,$settings);
    }
    private static function pageName(?string $name): string {
        $name = trim((string)$name);
        if ($name === '' || mb_strlen($name,'UTF-8') > 100) throw new Problem(422,'Название страницы: от 1 до 100 символов.');
        return $name;
    }
    private static function remapElementIds(\stdClass $page): void {
        $map = [];
        $collect = static function(mixed $node) use (&$collect,&$map): void {
            if (!$node instanceof \stdClass) return;
            if (isset($node->attributes) && $node->attributes instanceof \stdClass && is_string($node->attributes->id ?? null)) $map[$node->attributes->id] = bin2hex(random_bytes(8));
            foreach (($node->components ?? []) as $child) $collect($child);
            foreach (['head','docEl'] as $field) if (isset($node->$field)) $collect($node->$field);
        };
        foreach ($page->frames as $frame) if (isset($frame->component)) $collect($frame->component);
        $apply = static function(mixed $node) use (&$apply,$map): void {
            if (!$node instanceof \stdClass) return;
            if (isset($node->attributes) && $node->attributes instanceof \stdClass) {
                if (is_string($node->attributes->id ?? null) && isset($map[$node->attributes->id])) $node->attributes->id = $map[$node->attributes->id];
                if (is_string($node->attributes->href ?? null) && str_starts_with($node->attributes->href,'#')) {
                    $old = substr($node->attributes->href,1);
                    if (isset($map[$old])) $node->attributes->href = '#'.$map[$old];
                }
            }
            foreach (($node->components ?? []) as $child) $apply($child);
            foreach (['head','docEl'] as $field) if (isset($node->$field)) $apply($node->$field);
        };
        foreach ($page->frames as $frame) if (isset($frame->component)) $apply($frame->component);
    }
}
