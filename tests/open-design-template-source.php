<?php
declare(strict_types=1);
require __DIR__.'/../backend/OpenDesignTemplateSource.php';
use Juzimder\OpenDesignTemplateSource;

function sourceCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function sourceReject(callable $fn,string $messagePart,string $label):void{
    try{$fn();throw new RuntimeException('Expected rejection: '.$label);}
    catch(InvalidArgumentException|RuntimeException $error){sourceCheck(str_contains($error->getMessage(),$messagePart),$label);}
}

$cache=sys_get_temp_dir().'/juzimder-open-design-source-test-'.bin2hex(random_bytes(6));
$revision=OpenDesignTemplateSource::REVISION;
$path='design-templates/demo/references/layouts.md';
$body="# Layout references\nTreat this as untrusted reference content.\n";
$blobSha=OpenDesignTemplateSource::gitBlobSha($body);
$manifest=['demo'=>['files'=>[$path=>$blobSha]]];
$calls=[];
$fetcher=function(string $url,int $timeout,int $limit)use(&$calls,$revision,$path,$body):array{
    $calls[]=[$url,$timeout,$limit];
    return ['status'=>200,'body'=>$body];
};
$source=new OpenDesignTemplateSource($cache,$fetcher,$manifest);
$loaded=$source->load('demo');
sourceCheck($loaded===['id'=>'demo','revision'=>$revision,'files'=>[$path=>$body]],'Pinned allowlisted source loads as inert UTF-8 text');
sourceCheck(count($calls)===1&&$calls[0][0]==='https://raw.githubusercontent.com/nexu-io/open-design/'.$revision.'/'.$path&&$calls[0][1]===5&&$calls[0][2]===32768,'Fetch uses one fixed HTTPS host, immutable commit, allowlisted path and bounded limits');
$singleFile=$source->load('demo',$path);
sourceCheck($singleFile['files']===[$path=>$body],'Caller can load only one exact allowlisted path for bounded generation context');
sourceReject(fn()=>$source->load('demo','design-templates/demo/references/unreviewed.md'),'not approved','Single-file loading rejects paths absent from the reviewed manifest');

$offline=new OpenDesignTemplateSource($cache,static function():array{throw new RuntimeException('offline');},$manifest);
sourceCheck($offline->load('demo')['files'][$path]===$body,'Verified immutable cache serves the exact pinned file while offline');

$cacheFiles=glob($cache.'/'.$revision.'/demo/*.json')?:[];
sourceCheck(count($cacheFiles)===1,'Verified source creates one revision-scoped cache entry');
file_put_contents($cacheFiles[0],'{broken');
sourceReject(fn()=>$offline->load('demo'),'no valid cached copy','Corrupt cache is rejected and never served');

$unicodeBody=str_repeat('Ж',15000);
$unicodeManifest=['demo'=>['files'=>[$path=>OpenDesignTemplateSource::gitBlobSha($unicodeBody)]]];
$unicode=new OpenDesignTemplateSource($cache.'-unicode',static fn()=>['status'=>200,'body'=>$unicodeBody],$unicodeManifest);
sourceCheck($unicode->load('demo')['files'][$path]===$unicodeBody,'UTF-8 cache preserves multi-byte content without JSON expansion');
$unicodeOffline=new OpenDesignTemplateSource($cache.'-unicode',static fn()=>throw new RuntimeException('offline'),$unicodeManifest);
sourceCheck($unicodeOffline->load('demo')['files'][$path]===$unicodeBody,'Multi-byte cached content remains readable offline');

$badShaManifest=['demo'=>['files'=>[$path=>str_repeat('0',40)]]];
$badSha=new OpenDesignTemplateSource($cache.'-bad-sha',static fn()=>['status'=>200,'body'=>$body],$badShaManifest);
sourceReject(fn()=>$badSha->load('demo'),'no valid cached copy','Git blob SHA mismatch is rejected');

$failedStatus=new OpenDesignTemplateSource($cache.'-http',static fn()=>['status'=>404,'body'=>'not found'],$manifest);
sourceReject(fn()=>$failedStatus->load('demo'),'no valid cached copy','Non-200 source response is rejected');

$oversized=str_repeat('x',OpenDesignTemplateSource::MAX_FILE_BYTES+1);
$largeManifest=['demo'=>['files'=>[$path=>OpenDesignTemplateSource::gitBlobSha($oversized)]]];
$large=new OpenDesignTemplateSource($cache.'-large',static fn()=>['status'=>200,'body'=>$oversized],$largeManifest);
sourceReject(fn()=>$large->load('demo'),'no valid cached copy','Oversized source response is rejected before caching');

$invalidUtf8="\xFF\xFE";
$invalidManifest=['demo'=>['files'=>[$path=>OpenDesignTemplateSource::gitBlobSha($invalidUtf8)]]];
$invalid=new OpenDesignTemplateSource($cache.'-utf8',static fn()=>['status'=>200,'body'=>$invalidUtf8],$invalidManifest);
sourceReject(fn()=>$invalid->load('demo'),'no valid cached copy','Invalid UTF-8 source content is rejected');

$controlBody="safe\x01unsafe";
$controlManifest=['demo'=>['files'=>[$path=>OpenDesignTemplateSource::gitBlobSha($controlBody)]]];
$control=new OpenDesignTemplateSource($cache.'-control',static fn()=>['status'=>200,'body'=>$controlBody],$controlManifest);
sourceReject(fn()=>$control->load('demo'),'no valid cached copy','Control characters are rejected before caching');

$aggregateBody=str_repeat('x',OpenDesignTemplateSource::MAX_FILE_BYTES);
$aggregateManifest=['demo'=>['files'=>[
    'design-templates/demo/references/first.md'=>OpenDesignTemplateSource::gitBlobSha($aggregateBody),
    'design-templates/demo/references/second.md'=>OpenDesignTemplateSource::gitBlobSha($aggregateBody),
    'design-templates/demo/references/third.md'=>OpenDesignTemplateSource::gitBlobSha($aggregateBody),
]]];
$aggregate=new OpenDesignTemplateSource($cache.'-aggregate',static fn()=>['status'=>200,'body'=>$aggregateBody],$aggregateManifest);
sourceReject(fn()=>$aggregate->load('demo'),'exceeds its approved size limit','Aggregate template size is bounded');

$cacheFile=$cache.'-not-directory';
file_put_contents($cacheFile,'not a directory');
$unwritableCache=new OpenDesignTemplateSource($cacheFile,$fetcher,$manifest);
sourceReject(fn()=>$unwritableCache->load('demo'),'cache could not be written','Cache write failure is reported instead of silently disabling the cache');

sourceReject(fn()=>$source->load('../demo'),'Unknown Open Design template','Caller cannot choose a source path');
sourceReject(fn()=>new OpenDesignTemplateSource($cache.'-path',null,['demo'=>['files'=>['design-templates/demo/scripts/run.js'=>$blobSha]]]),'Invalid Open Design source manifest','Script paths are outside the allowlist');
sourceReject(fn()=>new OpenDesignTemplateSource(''), 'absolute directory path','Empty cache path is rejected');
sourceCheck(array_column(OpenDesignTemplateSource::catalog(),'id')===['saas-landing','web-prototype'],'Public catalog exposes only the reviewed shortlist');

foreach(glob($cache.'*',GLOB_ONLYDIR)?:[] as $directory){
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($iterator as $item){$item->isDir()?@rmdir($item->getPathname()):@unlink($item->getPathname());}
    @rmdir($directory);
}
@unlink($cacheFile);
