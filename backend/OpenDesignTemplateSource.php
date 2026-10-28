<?php
declare(strict_types=1);
namespace Juzimder;

/**
 * Bounded, read-only access to reviewed Open Design reference files.
 * Returned bytes are untrusted source material; this class never executes or renders them.
 */
final class OpenDesignTemplateSource {
    public const REPOSITORY='nexu-io/open-design';
    public const REVISION='d297055b83b6226c2c5e40800bbb4287156c4025';
    public const MAX_FILE_BYTES=32768;
    public const MAX_TEMPLATE_BYTES=65536;
    public const REQUEST_TIMEOUT_SECONDS=5;
    private const MAX_CONNECT_TIMEOUT_SECONDS=2;

    private readonly string $cacheRoot;
    private readonly ?\Closure $fetcher;
    private readonly array $sources;

    public function __construct(?string $cacheRoot=null, ?callable $fetcher=null, ?array $sources=null) {
        $resolvedCacheRoot=$cacheRoot??rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'juzimder-open-design-cache';
        if($resolvedCacheRoot===''||$resolvedCacheRoot===DIRECTORY_SEPARATOR||!str_starts_with($resolvedCacheRoot,DIRECTORY_SEPARATOR))throw new \InvalidArgumentException('Open Design cache root must be an absolute directory path.');
        $this->cacheRoot=rtrim($resolvedCacheRoot,DIRECTORY_SEPARATOR);
        $this->fetcher=$fetcher===null?null:\Closure::fromCallable($fetcher);
        $this->sources=$sources??self::approvedSources();
        self::validateSources($this->sources);
    }

    /** Public metadata only; file bytes are fetched separately and only by stable IDs. */
    public static function catalog(): array {
        return [
            ['id'=>'saas-landing','name'=>'SaaS Landing','mode'=>'prototype','category'=>'marketing','sourcePath'=>'design-templates/saas-landing/example.html','usage'=>'composition-reference'],
            ['id'=>'web-prototype','name'=>'Web Prototype','mode'=>'prototype','category'=>'design','sourcePath'=>'design-templates/web-prototype/references/layouts.md','usage'=>'layout-reference'],
        ];
    }

    /**
     * Load only the allowlisted files for a reviewed template. Cached entries are immutable per revision.
     * @return array{id:string,revision:string,files:array<string,string>}
     */
    public function load(string $templateId, ?string $onlyPath=null): array {
        $source=$this->sources[$templateId]??null;
        if(!is_array($source))throw new \InvalidArgumentException('Unknown Open Design template.');
        $approvedFiles=$source['files'];
        if($onlyPath!==null){
            if(!array_key_exists($onlyPath,$approvedFiles))throw new \InvalidArgumentException('Open Design path is not approved for this template.');
            $approvedFiles=[$onlyPath=>$approvedFiles[$onlyPath]];
        }
        $files=[];$totalBytes=0;
        foreach($approvedFiles as $path=>$expectedBlobSha){
            $content=$this->readCache($templateId,$path,$expectedBlobSha);
            if($content===null){
                try{$content=$this->fetchVerified($path,$expectedBlobSha);}
                catch(\Throwable $error){
                    throw new \RuntimeException('Open Design source is unavailable and no valid cached copy exists.',0,$error);
                }
                $this->writeCache($templateId,$path,$expectedBlobSha,$content);
            }
            $totalBytes+=strlen($content);
            if($totalBytes>self::MAX_TEMPLATE_BYTES)throw new \RuntimeException('Open Design template exceeds its approved size limit.');
            $files[$path]=$content;
        }
        return ['id'=>$templateId,'revision'=>self::REVISION,'files'=>$files];
    }

    /** Git's canonical SHA-1 for a blob, used to verify bytes against the reviewed commit tree. */
    public static function gitBlobSha(string $content): string {
        return sha1('blob '.strlen($content)."\0".$content);
    }

    private static function approvedSources(): array {
        return [
            'saas-landing'=>['files'=>[
                'design-templates/saas-landing/example.html'=>'57704cd5b01a96e338f5de2f404a12269b5a14d1',
            ]],
            'web-prototype'=>['files'=>[
                'design-templates/web-prototype/references/layouts.md'=>'4e7be706db8c40abf3ef2de859f30e1ff3403520',
                'design-templates/web-prototype/references/checklist.md'=>'a70db7578fff980c7105db31d3155baf82dad995',
            ]],
        ];
    }

    private static function validateSources(array $sources): void {
        if(!$sources||count($sources)>8)throw new \InvalidArgumentException('Invalid Open Design source manifest.');
        $totalFiles=0;
        foreach($sources as $id=>$source){
            if(!is_string($id)||!preg_match('/^[a-z0-9-]{1,48}$/D',$id)||!is_array($source)||!is_array($source['files']??null)||!$source['files'])throw new \InvalidArgumentException('Invalid Open Design source manifest.');
            foreach($source['files'] as $path=>$blobSha){
                $totalFiles++;
                if(!is_string($path)||strlen($path)>240||!preg_match('~^design-templates/[a-z0-9-]+/(?:example\.html|references/[a-z0-9-]+\.md)$~D',$path)||!is_string($blobSha)||!preg_match('/^[a-f0-9]{40}$/D',$blobSha))throw new \InvalidArgumentException('Invalid Open Design source manifest.');
            }
        }
        if($totalFiles>16)throw new \InvalidArgumentException('Open Design source manifest is too large.');
    }

    private function fetchVerified(string $path,string $expectedBlobSha): string {
        $url='https://raw.githubusercontent.com/'.self::REPOSITORY.'/'.self::REVISION.'/'.$path;
        $result=$this->fetcher?($this->fetcher)($url,self::REQUEST_TIMEOUT_SECONDS,self::MAX_FILE_BYTES):$this->fetchRemote($url);
        if(!is_array($result)||($result['status']??null)!==200||!is_string($result['body']??null))throw new \RuntimeException('Open Design source request failed.');
        $content=$result['body'];
        if($content===''||strlen($content)>self::MAX_FILE_BYTES||!mb_check_encoding($content,'UTF-8')||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$content))throw new \RuntimeException('Open Design source content failed validation.');
        if(!hash_equals($expectedBlobSha,self::gitBlobSha($content)))throw new \RuntimeException('Open Design source integrity check failed.');
        return $content;
    }

    /** HTTPS only, no redirects/cookies/auth, bounded connect/transfer time and response bytes. */
    private function fetchRemote(string $url): array {
        if(!function_exists('curl_init'))throw new \RuntimeException('Open Design loading requires PHP cURL.');
        $parts=parse_url($url);
        if(($parts['scheme']??null)!=='https'||($parts['host']??null)!=='raw.githubusercontent.com'||isset($parts['user'])||isset($parts['pass'])||isset($parts['port']))throw new \RuntimeException('Open Design source URL is not allowed.');
        $curl=curl_init($url);$body='';
        curl_setopt_array($curl,[
            CURLOPT_HTTPGET=>true,
            CURLOPT_CONNECTTIMEOUT=>self::MAX_CONNECT_TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT=>self::REQUEST_TIMEOUT_SECONDS,
            CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_MAXREDIRS=>0,
            CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER=>true,
            CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_USERAGENT=>'JuzimderAI/0.1 OpenDesignReferenceLoader',
            CURLOPT_WRITEFUNCTION=>static function($handle,string $chunk)use(&$body):int{
                if(strlen($body)+strlen($chunk)>self::MAX_FILE_BYTES)return 0;
                $body.=$chunk;return strlen($chunk);
            },
        ]);
        $completed=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$error=$completed===false?curl_error($curl):null;curl_close($curl);
        if($completed===false)throw new \RuntimeException('Open Design source request failed: '.(is_string($error)?mb_substr($error,0,160):'transport error'));
        return ['status'=>$status,'body'=>$body];
    }

    private function cacheDirectory(string $templateId): string {
        return rtrim($this->cacheRoot,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.self::REVISION.DIRECTORY_SEPARATOR.$templateId;
    }

    private function cachePath(string $templateId,string $path): string {
        return $this->cacheDirectory($templateId).DIRECTORY_SEPARATOR.hash('sha256',$path).'.json';
    }

    private function readCache(string $templateId,string $path,string $expectedBlobSha): ?string {
        $cachePath=$this->cachePath($templateId,$path);
        if(!is_file($cachePath)||is_link($cachePath))return null;
        $raw=@file_get_contents($cachePath);
        if(!is_string($raw)||strlen($raw)>self::MAX_FILE_BYTES+1024)return null;
        try{$entry=json_decode($raw,true,8,JSON_THROW_ON_ERROR);}catch(\JsonException){return null;}
        $content=$entry['content']??null;
        if(($entry['revision']??null)!==self::REVISION||($entry['path']??null)!==$path||($entry['blobSha']??null)!==$expectedBlobSha||!is_string($content)||strlen($content)>self::MAX_FILE_BYTES||!mb_check_encoding($content,'UTF-8')||!hash_equals($expectedBlobSha,self::gitBlobSha($content)))return null;
        return $content;
    }

    private function writeCache(string $templateId,string $path,string $blobSha,string $content): void {
        $directory=$this->cacheDirectory($templateId);
        $revisionDirectory=dirname($directory);
        foreach([$this->cacheRoot,$revisionDirectory,$directory] as $cacheDirectory){
            if(is_link($cacheDirectory))throw new \RuntimeException('Open Design cache path is unsafe.');
            if(!is_dir($cacheDirectory)&&!@mkdir($cacheDirectory,0700)&&!is_dir($cacheDirectory))throw new \RuntimeException('Open Design cache could not be written.');
            if(is_link($cacheDirectory)||!is_writable($cacheDirectory))throw new \RuntimeException('Open Design cache path is unsafe.');
            @chmod($cacheDirectory,0700);
        }
        $temporary=tempnam($directory,'.open-design-');
        if($temporary===false)throw new \RuntimeException('Open Design cache could not be written.');
        $payload=json_encode(['revision'=>self::REVISION,'path'=>$path,'blobSha'=>$blobSha,'content'=>$content],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $written=@file_put_contents($temporary,$payload,LOCK_EX);
        if($written===false||$written!==strlen($payload)){@unlink($temporary);throw new \RuntimeException('Open Design cache could not be written.');}
        @chmod($temporary,0600);
        if(!@rename($temporary,$this->cachePath($templateId,$path))){@unlink($temporary);throw new \RuntimeException('Open Design cache could not be written.');}
    }
}
