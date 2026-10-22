<?php
declare(strict_types=1);
namespace Juzimder;

final class AiGenerationJobs {
    public function __construct(private readonly Store $store) {
        $this->expire();
    }
    private function expire(): void {
        $this->store->query("UPDATE ai_generation_jobs SET status='failed',error=?,updated_at=? WHERE status IN ('queued','running') AND created_at<?",['Время задачи истекло. Создайте новую генерацию.',time(),time()-660]);
        $this->store->query("DELETE FROM ai_generation_jobs WHERE status NOT IN ('queued','running') AND updated_at<?",[time()-86400]);
    }
    public function create(string $projectId,string $owner,string $pageId,int $version,array $payload): array {
        $project=$this->store->owned($projectId,$owner);
        if($project['version']!==$version)throw new Problem(409,'Версия проекта изменилась. Обновите страницу.');
        if(!in_array($pageId,array_column($project['document']->data->pages,'id'),true))throw new Problem(404,'Страница не найдена.');
        $this->store->query('BEGIN IMMEDIATE');
        try{
            if($this->store->query("SELECT 1 FROM ai_generation_jobs WHERE project_id=? AND status IN ('queued','running')",[$projectId])->fetchColumn())throw new Problem(409,'В проекте уже есть задача генерации. Восстановите её или дождитесь завершения.');
            $id=bin2hex(random_bytes(12));$now=time();
            $this->store->query("INSERT INTO ai_generation_jobs(id,project_id,owner_id,page_id,base_version,status,payload,created_at,updated_at) VALUES(?,?,?,?,?,'queued',?,?,?)",[$id,$projectId,$owner,$pageId,$version,json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$now,$now]);
            $this->store->query('COMMIT');
        }catch(\Throwable $error){$this->store->query('ROLLBACK');throw $error;}
        return $this->get($projectId,$owner,$id);
    }
    public function get(string $projectId,string $owner,?string $id=null,?string $pageId=null): ?array {
        $this->store->owned($projectId,$owner);$this->expire();
        $row=$id===null?$this->store->query('SELECT * FROM ai_generation_jobs WHERE project_id=? AND owner_id=? AND page_id=? ORDER BY created_at DESC,rowid DESC LIMIT 1',[$projectId,$owner,$pageId])->fetch():$this->store->query('SELECT * FROM ai_generation_jobs WHERE id=? AND project_id=? AND owner_id=?',[$id,$projectId,$owner])->fetch();
        if(!$row){if($id!==null)throw new Problem(404,'Задача не найдена.');return null;}
        $payload=json_decode($row['payload'],true,32,JSON_THROW_ON_ERROR);
        return ['id'=>$row['id'],'pageId'=>$row['page_id'],'baseVersion'=>(int)$row['base_version'],'status'=>$row['status'],'createdAt'=>(int)$row['created_at'],'request'=>['prompt'=>$payload['prompt'],'skillProfile'=>$payload['skillProfile'],'skillId'=>$payload['userSkill']['id']??null],'result'=>$row['result']===null?null:json_decode($row['result'],true,64,JSON_THROW_ON_ERROR),'error'=>$row['error']];
    }
    public function failLaunch(string $id): void {$this->store->query("UPDATE ai_generation_jobs SET status='failed',error=?,updated_at=? WHERE id=? AND status='queued'",['Не удалось запустить PHP worker. Проверьте конфигурацию сервера.',time(),$id]);}
    public function run(string $id,callable $providerFactory): bool {
        $claim=$this->store->query("UPDATE ai_generation_jobs SET status='running',updated_at=? WHERE id=? AND status='queued'",[time(),$id]);
        if($claim->rowCount()!==1)return false;
        $row=$this->store->query('SELECT * FROM ai_generation_jobs WHERE id=?',[$id])->fetch();
        try{
            $project=$this->store->owned($row['project_id'],$row['owner_id']);
            if($project['version']!==(int)$row['base_version'])throw new Problem(409,'Проект изменился до запуска задачи. Создайте новую генерацию.');
            $payload=json_decode($row['payload'],true,32,JSON_THROW_ON_ERROR);
            $result=AiGeneratedWebsite::create($payload['prompt'],$providerFactory($row['project_id'],$row['owner_id']),$payload['skillProfile'],$payload['userSkill']);
            $this->store->query("UPDATE ai_generation_jobs SET status='succeeded',result=?,updated_at=? WHERE id=? AND status='running'",[json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),time(),$id]);
        }catch(\Throwable $error){
            $message=$error instanceof AiProviderFailure?$error->userMessage():($error instanceof Problem?$error->getMessage():'Не удалось завершить генерацию. Проект не изменён.');
            $this->store->query("UPDATE ai_generation_jobs SET status='failed',error=?,updated_at=? WHERE id=? AND status='running'",[$message,time(),$id]);
        }
        return true;
    }
    public function launch(string $id): void {
        if(!in_array(PHP_SAPI,['cli','cli-server'],true)||!function_exists('exec')){$this->failLaunch($id);return;}
        $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/../scripts/ai-generation-worker.php').' '.escapeshellarg($this->store->path).' '.escapeshellarg($id).' > /dev/null 2>&1 &';
        exec($command,$unused,$code);if($code!==0)$this->failLaunch($id);
    }
}
