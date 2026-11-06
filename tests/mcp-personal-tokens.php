<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../backend/Domain.php';
require __DIR__.'/../backend/McpPersonalTokens.php';
require __DIR__.'/../backend/McpReadTools.php';
function checkToken(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
$path=sys_get_temp_dir().'/juzimder-personal-'.bin2hex(random_bytes(8)).'.sqlite';
try{
 $store=new Juzimder\Store($path);
 foreach(['owner','other'] as $user)$store->query('INSERT INTO users(id,email,password_hash) VALUES(?,?,?)',[$user,$user.'@example.test','hash']);
 $a=$store->create('owner','Allowed');$b=$store->create('owner','Excluded');$foreign=$store->create('other','Foreign');
 $tokens=new Juzimder\McpPersonalTokens($store);
 $input=['name'=>'Codex','projectIds'=>[$a['id']],'write'=>false,'days'=>7];
 $created=$tokens->create('owner',$input);$principal=$tokens->validate($created['token']);
 checkToken($principal['scopes']===['projects:read'],'Read scope defaults remain bounded');
 checkToken(!str_contains(json_encode($tokens->listing('owner')),$created['token']),'Listing never returns the token');
 checkToken($store->query('SELECT token_hash FROM mcp_personal_tokens')->fetchColumn()===hash('sha256',$created['token']),'Only token hash is stored');
 $tools=new Juzimder\McpAuth\McpReadTools($store,'owner',$principal['projectIds']);
 checkToken(array_column($tools->list_projects()['items'],'id')===[$a['id']],'Project listing is filtered before pagination');
 try{$tools->get_project_settings($b['id']);throw new RuntimeException('Expected project denial');}catch(Mcp\Exception\ToolCallException){echo "PASS Excluded project denied\n";}
 try{$tokens->create('owner',array_replace($input,['projectIds'=>[$foreign['id']]]));throw new RuntimeException('Expected ownership denial');}catch(Juzimder\Problem){echo "PASS Foreign project grant denied\n";}
 $tokens->revoke('other',$created['id']);checkToken($tokens->validate($created['token'])!==null,'Another account cannot revoke the token');
 $tokens->revoke('owner',$created['id']);checkToken($tokens->validate($created['token'])===null,'Revoked token rejected');
 $created=$tokens->create('owner',array_replace($input,['write'=>true]));checkToken(in_array('projects:write',$tokens->validate($created['token'])['scopes'],true),'Write scope requires explicit grant');
 $store->query('UPDATE mcp_personal_tokens SET expires_at=0 WHERE id=?',[$created['id']]);checkToken($tokens->validate($created['token'])===null,'Expired token rejected');
 $created=$tokens->create('owner',$input);$store->query('UPDATE users SET auth_version=auth_version+1 WHERE id=?',['owner']);checkToken($tokens->validate($created['token'])===null,'Changed account auth version invalidates token');
}finally{foreach(glob($path.'*') as $file)@unlink($file);}
