<?php
declare(strict_types=1);
require __DIR__.'/../backend/Domain.php';
require __DIR__.'/../backend/AiSkills.php';
use Juzimder\{Problem,ProjectAiSkills,Store};

function skillCheck(bool $condition,string $message): void { if(!$condition)throw new RuntimeException($message);echo "PASS $message\n"; }
function skillReject(callable $fn,int $status,string $message): void { try{$fn();}catch(Problem $problem){skillCheck($problem->status===$status,$message);return;}throw new RuntimeException('Expected HTTP '.$status.': '.$message); }

$path=sys_get_temp_dir().'/juzimder-ai-skills-'.bin2hex(random_bytes(6)).'.sqlite';
try {
    $store=new Store($path);
    $store->query('INSERT INTO users(id,email,password_hash) VALUES(?,?,?)',['owner','owner@example.test','hash']);
    $store->query('INSERT INTO users(id,email,password_hash) VALUES(?,?,?)',['outsider','outsider@example.test','hash']);
    $project=$store->create('owner','Skills test');$skills=new ProjectAiSkills($store);$id=$project['id'];
    $initial=$skills->list($id,'owner');
    skillCheck($initial['version']===0&&$initial['activeSkillId']===null&&$initial['skills']===[],'New projects start with an empty user Skill library');
    $created=$skills->create($id,'owner',0,'Brand voice',"# Brand voice\nUse warm, direct language.",['Use the requested brand colors.','Check all mobile sections.']);
    $skillId=$created['skills'][0]['id'];
    skillCheck($created['version']===1&&!$created['skills'][0]['active']&&!array_key_exists('content',$created['skills'][0]),'Skill summaries omit full instructions and keep new Skills inactive');
    $activated=$skills->activate($id,'owner',1,$skillId);
    skillCheck($activated['version']===2&&$activated['activeSkillId']===$skillId&&$activated['skills'][0]['active'],'Owner can activate one saved Skill');
    $createdSecond=$skills->create($id,'owner',2,'Accessibility',"# Accessibility\nUse semantic headings.",['Check heading order.']);
    skillCheck($createdSecond['version']===3&&$createdSecond['activeSkillId']===$skillId,'Adding another Skill preserves the current active selection');
    skillReject(fn()=>$skills->update($id,'owner',2,$skillId,'Stale edit','Updated instructions',['Check readability.']),409,'Skill mutations require the latest library version');
    $updated=$skills->update($id,'owner',3,$skillId,'Updated voice',"# Updated voice\nUse concise, warm language.",['Check heading order.']);
    skillCheck($updated['version']===4&&$updated['activeSkillId']===$skillId,'Editing an active Skill retains activation and advances the library version');
    $detail=$skills->forGeneration($id,'owner',$skillId);
    skillCheck($detail['content']==="# Updated voice\nUse concise, warm language."&&$detail['manualChecks']===['Check heading order.'],'Generation receives only the selected Skill text and checklist');
    skillReject(fn()=>$skills->get($id,'outsider',$skillId),404,'Skill contents are restricted to the project owner');
    skillReject(fn()=>$skills->create($id,'owner',4,'Invalid','', ['Check it.']),422,'Empty Skill text is rejected');
    skillReject(fn()=>$skills->create($id,'owner',4,'Invalid','Text', []),422,'A Skill requires at least one human acceptance check');
    skillReject(fn()=>$skills->create($id,'owner',4,'Invalid',str_repeat('x',ProjectAiSkills::MAX_CONTENT_CHARS+1), ['Check it.']),422,'Skill text has a fixed storage bound');
    skillReject(fn()=>$skills->update($id,'owner',4,$skillId,'Invalid rules',"```juzimder-rules\n{bad}\n```",['Review it.']),422,'Malformed automatic rules are rejected before saving a Skill');
    $deleted=$skills->delete($id,'owner',4,$skillId);
    skillCheck($deleted['version']===5&&$deleted['activeSkillId']===null&&count($deleted['skills'])===1,'Deleting the active Skill clears activation and preserves other Skills');
} finally { unset($store);@unlink($path);@unlink($path.'-journal'); }
