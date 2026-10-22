<?php
declare(strict_types=1);
namespace Juzimder;
require_once __DIR__.'/SkillRequirements.php';

final class ProjectAiSkills {
    public const MAX_SKILLS = 10;
    public const MAX_CONTENT_CHARS = 16000;
    public const MAX_CHECKS = 12;

    public function __construct(private readonly Store $store) {}

    public function list(string $projectId, string $userId): array {
        $this->store->owned($projectId, $userId);
        $state = $this->state($projectId);
        $rows = $this->store->query(
            'SELECT id,name,content,manual_checks_json,created_at,updated_at FROM project_ai_skills WHERE project_id=? ORDER BY created_at,id',
            [$projectId]
        )->fetchAll();
        $skills = [];
        foreach ($rows as $row) {
            $checks = json_decode($row['manual_checks_json'], true, 16, JSON_THROW_ON_ERROR);
            $skills[] = [
                'id'=>$row['id'], 'name'=>$row['name'], 'characterCount'=>mb_strlen($row['content'], 'UTF-8'),
                'manualChecks'=>$checks, 'updatedAt'=>$row['updated_at'],
                'active'=>$row['id'] === ($state['active_skill_id'] ?? null),
            ];
        }
        return ['version'=>(int)($state['version'] ?? 0),'activeSkillId'=>$state['active_skill_id'] ?? null,'skills'=>$skills,'limit'=>self::MAX_SKILLS];
    }

    public function get(string $projectId, string $userId, string $skillId): array {
        $this->store->owned($projectId, $userId);
        $row = $this->store->query(
            'SELECT id,name,content,manual_checks_json,updated_at FROM project_ai_skills WHERE project_id=? AND id=?',
            [$projectId,$skillId]
        )->fetch();
        if (!$row) throw new Problem(404,'Skill не найден.');
        return ['id'=>$row['id'],'name'=>$row['name'],'content'=>$row['content'],'manualChecks'=>json_decode($row['manual_checks_json'],true,16,JSON_THROW_ON_ERROR),'updatedAt'=>$row['updated_at']];
    }

    public function forGeneration(string $projectId, string $userId, string $skillId): array {
        $skill = $this->get($projectId,$userId,$skillId);
        try { SkillRequirements::parse($skill['content']); } catch (\InvalidArgumentException $error) { throw new Problem(422,$error->getMessage()); }
        return ['id'=>$skill['id'],'name'=>$skill['name'],'content'=>$skill['content'],'manualChecks'=>$skill['manualChecks']];
    }

    public function create(string $projectId, string $userId, int $expectedVersion, string $name, string $content, array $manualChecks): array {
        $this->store->owned($projectId,$userId);
        $name=self::validateName($name);$content=self::validateContent($content);$manualChecks=self::validateChecks($manualChecks);
        return $this->mutate($projectId,$userId,$expectedVersion,function(?array $state) use($projectId,$name,$content,$manualChecks): ?string {
            $count=(int)$this->store->query('SELECT COUNT(*) FROM project_ai_skills WHERE project_id=?',[$projectId])->fetchColumn();
            if ($count>=self::MAX_SKILLS) throw new Problem(422,'Для проекта можно сохранить не более '.self::MAX_SKILLS.' Skills.');
            $id=bin2hex(random_bytes(12));
            $this->store->query('INSERT INTO project_ai_skills(id,project_id,name,content,manual_checks_json) VALUES(?,?,?,?,?)',[$id,$projectId,$name,$content,json_encode($manualChecks,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
            return $state['active_skill_id'] ?? null;
        });
    }

    public function update(string $projectId, string $userId, int $expectedVersion, string $skillId, string $name, string $content, array $manualChecks): array {
        $this->store->owned($projectId,$userId);
        $name=self::validateName($name);$content=self::validateContent($content);$manualChecks=self::validateChecks($manualChecks);
        return $this->mutate($projectId,$userId,$expectedVersion,function(?array $state) use($projectId,$skillId,$name,$content,$manualChecks): ?string {
            $updated=$this->store->query('UPDATE project_ai_skills SET name=?,content=?,manual_checks_json=?,updated_at=CURRENT_TIMESTAMP WHERE project_id=? AND id=?',[$name,$content,json_encode($manualChecks,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$projectId,$skillId]);
            if ($updated->rowCount()!==1) throw new Problem(404,'Skill не найден.');
            return $state['active_skill_id'] ?? null;
        });
    }

    public function activate(string $projectId, string $userId, int $expectedVersion, string $skillId): array {
        $this->store->owned($projectId,$userId);
        return $this->mutate($projectId,$userId,$expectedVersion,function() use($projectId,$skillId): ?string {
            if ($this->store->query('SELECT 1 FROM project_ai_skills WHERE project_id=? AND id=?',[$projectId,$skillId])->fetchColumn()===false) throw new Problem(404,'Skill не найден.');
            return $skillId;
        });
    }

    public function delete(string $projectId, string $userId, int $expectedVersion, string $skillId): array {
        $this->store->owned($projectId,$userId);
        return $this->mutate($projectId,$userId,$expectedVersion,function(?array $state) use($projectId,$skillId): ?string {
            $deleted=$this->store->query('DELETE FROM project_ai_skills WHERE project_id=? AND id=?',[$projectId,$skillId]);
            if ($deleted->rowCount()!==1) throw new Problem(404,'Skill не найден.');
            return ($state['active_skill_id'] ?? null)===$skillId ? null : ($state['active_skill_id'] ?? null);
        });
    }

    private function mutate(string $projectId,string $userId,int $expectedVersion,callable $changeActive): array {
        $this->store->owned($projectId,$userId);
        try {
            $this->store->db->beginTransaction();
            $state=$this->state($projectId);$version=(int)($state['version']??0);
            if($version!==$expectedVersion)throw new Problem(409,'Список Skills изменился в другой вкладке. Обновите страницу и повторите действие.');
            if($state===null)$this->store->query('INSERT INTO project_ai_skill_state(project_id,active_skill_id,version) VALUES(?,NULL,0)',[$projectId]);
            $updated=$this->store->query('UPDATE project_ai_skill_state SET version=version+1,updated_at=CURRENT_TIMESTAMP WHERE project_id=? AND version=?',[$projectId,$expectedVersion]);
            if($updated->rowCount()!==1)throw new Problem(409,'Список Skills изменился в другой вкладке. Обновите страницу и повторите действие.');
            $activeId=$changeActive($state);
            $this->store->query('UPDATE project_ai_skill_state SET active_skill_id=?,updated_at=CURRENT_TIMESTAMP WHERE project_id=?',[$activeId,$projectId]);
            $this->store->db->commit();
        } catch(\Throwable $error) {
            if($this->store->db->inTransaction())$this->store->db->rollBack();
            if($error instanceof \PDOException&&preg_match('/locked|busy/i',$error->getMessage()))throw new Problem(409,'Список Skills изменился в другой вкладке. Обновите страницу и повторите действие.');
            throw $error;
        }
        return $this->list($projectId,$userId);
    }

    private function state(string $projectId): ?array {
        $row=$this->store->query('SELECT active_skill_id,version FROM project_ai_skill_state WHERE project_id=?',[$projectId])->fetch();
        return $row?:null;
    }

    private static function validateName(string $name): string {
        $name=trim($name);
        if($name===''||mb_strlen($name,'UTF-8')>64||preg_match('/[\x00-\x1F\x7F]/u',$name))throw new Problem(422,'Название Skill должно содержать от 1 до 64 символов.');
        return $name;
    }

    private static function validateContent(string $content): string {
        $content=str_replace("\r\n","\n",str_replace("\r","\n",$content));
        if(str_starts_with($content,"\xEF\xBB\xBF"))$content=substr($content,3);
        $content=trim($content);
        if($content===''||!mb_check_encoding($content,'UTF-8')||mb_strlen($content,'UTF-8')>self::MAX_CONTENT_CHARS||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',$content))throw new Problem(422,'Текст Skill должен быть UTF-8 и содержать от 1 до '.self::MAX_CONTENT_CHARS.' символов.');
        try { SkillRequirements::parse($content); } catch (\InvalidArgumentException $error) { throw new Problem(422,$error->getMessage()); }
        return $content;
    }

    private static function validateChecks(array $checks): array {
        if(!array_is_list($checks)||count($checks)<1||count($checks)>self::MAX_CHECKS)throw new Problem(422,'Добавьте от 1 до '.self::MAX_CHECKS.' пунктов ручной проверки, по одному в строке.');
        $normalized=[];
        foreach($checks as $check){
            if(!is_string($check))throw new Problem(422,'Пункты ручной проверки должны быть текстом.');
            $check=trim($check);
            if($check===''||mb_strlen($check,'UTF-8')>240||preg_match('/[\x00-\x1F\x7F]/u',$check))throw new Problem(422,'Каждый пункт ручной проверки должен содержать от 1 до 240 символов.');
            $normalized[]=$check;
        }
        if(count(array_unique($normalized))!==count($normalized))throw new Problem(422,'Пункты ручной проверки не должны повторяться.');
        return $normalized;
    }
}
