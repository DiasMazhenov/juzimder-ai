<?php
declare(strict_types=1);
namespace Juzimder;

final class Problem extends \RuntimeException {
    public function __construct(public int $status, string $message) { parent::__construct($message); }
}
final class Document {
    public const MAX_PAGES = 30;
    public const MAX_PAGE_ID_LENGTH = 128;
    public const FRAMES_PER_PAGE = 1;
    public const COMPONENT_TYPES = ['', 'default','wrapper','text','textnode','image','link','link-block','head'];
    public const TAG_NAMES = ['html','head','body','div','section','header','footer','nav','main','article','aside','h1','h2','h3','h4','p','span','a','img','button','ul','ol','li','strong','em','b','i','br','hr','label'];
    public static function empty(): array {
        return ['schemaVersion' => 1, 'engine' => 'grapesjs', 'data' => ['assets' => [], 'styles' => [], 'pages' => [['id' => 'home', 'name' => 'Главная', 'frames' => [['component' => ['type' => 'wrapper', 'components' => []]]]]]]];
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
        $count = 0;
        self::walk($data, 0, $count);
    }
    private static function component(mixed $node, int $depth): void {
        $fields = self::objectFields($node);
        if ($fields === null || $depth > 35) throw new Problem(422,'Некорректное дерево компонентов.');
        foreach (['attributes','style'] as $field) if (array_key_exists($field, $fields) && self::objectFields($fields[$field]) === null) throw new Problem(422,'Некорректные свойства компонента.');
        if (array_key_exists('content', $fields) && !is_string($fields['content'])) throw new Problem(422,'Некорректный текст компонента.');
        if (array_key_exists('type', $fields) && (!is_string($fields['type']) || !in_array($fields['type'], self::COMPONENT_TYPES, true))) throw new Problem(422,'Недопустимый тип компонента.');
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
                if (is_string($key) && (preg_match('/^on/i', $key) || in_array(strtolower($key), ['script', 'script-export', 'srcdoc', 'srcset', 'formaction', 'action'], true))) throw new Problem(422, 'Исполняемый код запрещён.');
                if ($key === 'tagName' && (!is_string($item) || !in_array($item, self::TAG_NAMES, true))) throw new Problem(422, 'Недопустимый HTML-тег.');
                if (in_array($key, ['href','src'], true) && (!is_string($item) || !preg_match('~^(https://[^\s<>"\x00-\x20]+|\#[a-zA-Z0-9_-]*|mailto:[a-zA-Z0-9@._+-]+|tel:[+0-9 -]+)$~D', $item))) throw new Problem(422, 'Недопустимый адрес ресурса.');
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
    public function __construct(string $path) {
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
        $this->db = new \PDO('sqlite:' . $path, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $this->db->exec('PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000;');
        if ((int)$this->db->query('PRAGMA user_version')->fetchColumn() === 0) $this->db->exec(file_get_contents(__DIR__ . '/migrations/001_initial.sql'));
    }
    public function query(string $sql, array $args = []): \PDOStatement { $stmt = $this->db->prepare($sql); $stmt->execute($args); return $stmt; }
    public function owned(string $id, string $user): array {
        $row = $this->query('SELECT p.id,p.name,d.version,d.payload,d.updated_at FROM projects p JOIN documents d ON p.id=d.project_id WHERE p.id=? AND p.owner_id=?', [$id,$user])->fetch();
        if (!$row) throw new Problem(404, 'Проект не найден.');
        $row['document'] = json_decode($row['payload'], false, 64, JSON_THROW_ON_ERROR); unset($row['payload']); return $row;
    }
    public function create(string $user, string $name): array {
        $name = trim($name); if ($name === '' || mb_strlen($name) > 100) throw new Problem(422,'Название: от 1 до 100 символов.');
        $id = bin2hex(random_bytes(12)); $payload = json_encode(Document::empty(), JSON_THROW_ON_ERROR);
        $this->db->beginTransaction();
        try { $this->query('INSERT INTO projects(id,owner_id,name) VALUES(?,?,?)',[$id,$user,$name]); $this->query('INSERT INTO documents(project_id,payload) VALUES(?,?)',[$id,$payload]); $this->db->commit(); } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
        return $this->owned($id,$user);
    }
    public function save(string $id,string $user,int $version,mixed $doc): array {
        $this->owned($id,$user); Document::validate($doc); $payload = json_encode($doc, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        if (strlen($payload) > 2000000) throw new Problem(413,'Проект превышает 2 МБ.');
        $this->db->beginTransaction();
        try {
            $stmt = $this->query('UPDATE documents SET payload=?,version=version+1,updated_at=CURRENT_TIMESTAMP WHERE project_id=? AND version=?',[$payload,$id,$version]);
            if ($stmt->rowCount() !== 1) throw new Problem(409,'На сервере есть новая версия. Экспортируйте свои изменения и откройте проект заново.');
            $this->query('INSERT INTO revisions(project_id,version,payload) VALUES(?,?,?)',[$id,$version+1,$payload]);
            $this->query('DELETE FROM revisions WHERE project_id=? AND version NOT IN (SELECT version FROM revisions WHERE project_id=? ORDER BY version DESC LIMIT 200)',[$id,$id]);
            $this->db->commit();
        } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
        return $this->owned($id,$user);
    }
    public function restore(string $id,string $user,int $version,int $targetVersion): array {
        $this->owned($id,$user);
        $revision = $this->query('SELECT payload FROM revisions WHERE project_id=? AND version=?',[$id,$targetVersion])->fetch();
        if (!$revision) throw new Problem(404,'Ревизия не найдена.');
        $payload = $revision['payload'];
        $document = json_decode($payload,false,64,JSON_THROW_ON_ERROR);
        Document::validate($document);
        $this->db->beginTransaction();
        try {
            $stmt = $this->query('UPDATE documents SET payload=?,version=version+1,updated_at=CURRENT_TIMESTAMP WHERE project_id=? AND version=?',[$payload,$id,$version]);
            if ($stmt->rowCount() !== 1) throw new Problem(409,'Проект изменился в другой вкладке. Обновите его перед восстановлением.');
            $this->query('INSERT INTO revisions(project_id,version,payload) VALUES(?,?,?)',[$id,$version+1,$payload]);
            $this->query('DELETE FROM revisions WHERE project_id=? AND version NOT IN (SELECT version FROM revisions WHERE project_id=? ORDER BY version DESC LIMIT 200)',[$id,$id]);
            $this->db->commit();
        } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
        return $this->owned($id,$user);
    }
}
