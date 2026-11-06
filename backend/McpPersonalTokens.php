<?php
declare(strict_types=1);
namespace Juzimder;
final class McpPersonalTokens {
    public function __construct(private readonly Store $store) {}
    public function listing(string $user): array {
        $rows=$this->store->query('SELECT id,name,project_ids,can_write,expires_at,revoked_at FROM mcp_personal_tokens WHERE user_id=? ORDER BY created_at DESC',[$user])->fetchAll();
        return array_map(static function(array $row):array{$row['projectIds']=json_decode($row['project_ids'],true);unset($row['project_ids']);return $row;},$rows);
    }
    public function create(string $user,array $input): array {
        if(array_diff(array_keys($input),['name','projectIds','write','days'])||!is_string($input['name']??null)||trim($input['name'])===''||mb_strlen($input['name'])>80||!is_array($input['projectIds']??null)||!array_is_list($input['projectIds'])||count($input['projectIds'])<1||count($input['projectIds'])>50||!is_bool($input['write']??null)||!in_array($input['days']??null,[7,30,90],true))throw new Problem(422,'Укажите название, проекты, права и срок доступа.');
        foreach($input['projectIds'] as $id){if(!is_string($id))throw new Problem(422,'Некорректный проект.');$this->store->owned($id,$user);}
        if((int)$this->store->query('SELECT COUNT(*) FROM mcp_personal_tokens WHERE user_id=? AND revoked_at IS NULL AND expires_at>?',[$user,time()])->fetchColumn()>=20)throw new Problem(422,'Отзовите одно из действующих подключений.');
        $token='jzm_'.bin2hex(random_bytes(32));$id=bin2hex(random_bytes(12));$expires=time()+$input['days']*86400;
        $version=$this->store->query('SELECT auth_version FROM users WHERE id=?',[$user])->fetchColumn();
        $this->store->query('INSERT INTO mcp_personal_tokens(id,user_id,name,token_hash,project_ids,can_write,auth_version,expires_at,created_at) VALUES(?,?,?,?,?,?,?,?,?)',[$id,$user,trim($input['name']),hash('sha256',$token),json_encode(array_values(array_unique($input['projectIds']))),$input['write']?1:0,$version,$expires,time()]);
        return ['id'=>$id,'token'=>$token,'expiresAt'=>$expires];
    }
    public function revoke(string $user,string $id):void {$this->store->query('UPDATE mcp_personal_tokens SET revoked_at=? WHERE id=? AND user_id=?',[time(),$id,$user]);}
    public function validate(string $token):?array {
        if(!preg_match('/^jzm_[a-f0-9]{64}$/D',$token))return null;
        $row=$this->store->query('SELECT t.*,u.auth_version AS current_auth_version FROM mcp_personal_tokens t JOIN users u ON u.id=t.user_id WHERE token_hash=?',[hash('sha256',$token)])->fetch();
        if(!$row||$row['revoked_at']!==null||(int)$row['expires_at']<=time()||(int)$row['auth_version']!==(int)$row['current_auth_version'])return null;
        return ['userId'=>$row['user_id'],'id'=>$row['id'],'projectIds'=>json_decode($row['project_ids'],true),'scopes'=>$row['can_write']?['projects:read','projects:write']:['projects:read']];
    }
}
