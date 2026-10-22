<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||count($argv)!==3||!preg_match('/^[a-f0-9]{24}$/D',$argv[2]))exit(1);
require __DIR__.'/../backend/Domain.php';
require __DIR__.'/../backend/Ai.php';
require __DIR__.'/../backend/AiCredentials.php';
require __DIR__.'/../backend/AiGenerationJobs.php';
set_time_limit(630);
$store=new Juzimder\Store($argv[1]);
(new Juzimder\AiGenerationJobs($store))->run($argv[2],static function(string $project,string $owner)use($store):Juzimder\StructuredAiProvider{
    $profiles=(new Juzimder\AiCredentialVault($store))->failoverSettings($project,$owner);
    if(!$profiles)throw new Juzimder\AiProviderFailure('No AI profiles.',false,'credentials');
    return new Juzimder\ProfileFailoverProvider($profiles,null,600,180);
});
