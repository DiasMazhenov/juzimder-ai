<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||count($argv)!==3||!preg_match('/^[a-f0-9]{24}$/D',$argv[2]))exit(1);
require __DIR__.'/../backend/Domain.php';
require __DIR__.'/../backend/Ai.php';
require __DIR__.'/../backend/AiCredentials.php';
require __DIR__.'/../backend/AiGenerationJobs.php';
set_time_limit(630);
$store=new Juzimder\Store($argv[1]);
$jobs=new Juzimder\AiGenerationJobs($store);
$jobs->run($argv[2],static function(string $project,string $owner)use($store,$jobs,$argv):Juzimder\StructuredAiProvider{
    $profiles=(new Juzimder\AiCredentialVault($store))->failoverSettings($project,$owner);
    if(!$profiles)throw new Juzimder\AiProviderFailure('No AI profiles.',false,'credentials');
    $lastCheck=0.0;$cancelled=false;
    $transport=new Juzimder\StreamAiHttpTransport(static function()use($jobs,$argv,&$lastCheck,&$cancelled):bool{
        if(microtime(true)-$lastCheck>=0.5){$cancelled=$jobs->shouldCancel($argv[2]);$lastCheck=microtime(true);}
        return $cancelled;
    });
    return new Juzimder\ProfileFailoverProvider($profiles,$transport,600,180);
});
