<?php
declare(strict_types=1);
namespace Juzimder;
/** Opt-in, ten-minute entry probe. No request payloads, credentials or identities. */
final class McpIngressProbe {
    private static function path(string $database):string{return $database.'.mcp-ingress-probe';}
    public static function start(string $database):array{
        $state=['id'=>bin2hex(random_bytes(16)),'expiresAt'=>time()+600,'hits'=>[]];
        $handle=fopen(self::path($database),'c+');if(!$handle)throw new \RuntimeException('Probe unavailable');
        try{chmod(self::path($database),0600);flock($handle,LOCK_EX);ftruncate($handle,0);rewind($handle);fwrite($handle,json_encode($state,JSON_THROW_ON_ERROR));}finally{flock($handle,LOCK_UN);fclose($handle);}
        return $state;
    }
    public static function read(string $database):?array{
        $path=self::path($database);if(!is_file($path))return null;
        $handle=fopen($path,'r');if(!$handle)return null;
        try{flock($handle,LOCK_SH);$state=json_decode(stream_get_contents($handle,8192),true);}finally{flock($handle,LOCK_UN);fclose($handle);}
        return is_array($state)&&($state['expiresAt']??0)>time()?$state:null;
    }
    public static function record(string $database,mixed $id,string $method):void{
        if(!is_string($id)||!preg_match('/^[a-f0-9]{32}$/D',$id)||!in_array($method,['GET','POST','DELETE','OPTIONS','HEAD'],true))return;
        $path=self::path($database);if(!is_file($path))return;
        $handle=fopen($path,'r+');if(!$handle)return;
        try{flock($handle,LOCK_EX);$state=json_decode(stream_get_contents($handle,8192),true);
            if(!is_array($state)||($state['expiresAt']??0)<=time()||!hash_equals((string)($state['id']??''),$id))return;
            $state['hits']=array_slice([...$state['hits'],['method'=>$method,'at'=>time()]],-10);
            ftruncate($handle,0);rewind($handle);fwrite($handle,json_encode($state,JSON_THROW_ON_ERROR));
            if(!headers_sent())header('X-Juzimder-MCP-Entry: '.$id);
        }finally{flock($handle,LOCK_UN);fclose($handle);}
    }
}
