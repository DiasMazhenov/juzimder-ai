<?php
declare(strict_types=1);
require __DIR__.'/../backend/Domain.php';
require __DIR__.'/../backend/FormMail.php';
use Juzimder\{Store,FormMailVault,ProjectSettings,Problem,SmtpTransport};
function mailCheck(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); echo "PASS $message\n"; }
function mailReject(callable $fn,int $status): void { try { $fn(); } catch (Problem $error) { mailCheck($error->status===$status,'Reject invalid mail settings'); return; } throw new RuntimeException('Expected mail settings rejection.'); }
$path=sys_get_temp_dir().'/juzimder-mail-'.bin2hex(random_bytes(6)).'.sqlite';
try {
    $store=new Store($path);$store->query('INSERT INTO users(id,email,password_hash) VALUES(?,?,?)',['mail-owner','owner@example.test','hash']);$project=$store->create('mail-owner','Mail project');$vault=new FormMailVault($store);
    $status=$vault->status($project['id'],'mail-owner');mailCheck($status['version']===0&&!$status['configured']&&!array_key_exists('password',$status),'SMTP status never returns credentials');
    $saved=$vault->save($project['id'],'mail-owner',0,['host'=>'smtp.example.test','port'=>587,'security'=>'starttls','username'=>'user@example.test','password'=>'smtp-test-secret']);
    mailCheck($saved['configured']&&$saved['version']===1&&$saved['hasPassword']&&!array_key_exists('password',$saved),'SMTP settings save with redacted secret');
    $cipher=$store->query('SELECT settings_ciphertext FROM project_form_mail_settings WHERE project_id=?',[$project['id']])->fetchColumn();
    mailCheck(is_string($cipher)&&!str_contains($cipher,'smtp-test-secret')&&$vault->deliverySettings($project['id'])['password']==='smtp-test-secret','SMTP password is encrypted at rest and available to server delivery only');
    mailReject(fn()=>$vault->save($project['id'],'mail-owner',0,['host'=>'smtp.example.test','port'=>587,'security'=>'starttls','username'=>'user@example.test','password'=>'new-secret']),409);
    mailReject(fn()=>$vault->save($project['id'],'mail-owner',1,['host'=>'smtp.example.test','port'=>25,'security'=>'none','username'=>'user@example.test','password'=>'new-secret']),422);
    $defaults=ProjectSettings::formDefaults();$settings=ProjectSettings::validateFormsSettings(['notificationsEnabled'=>true,'recipients'=>['owner@example.test','OWNER@example.test'],'senderEmail'=>'forms@example.test','body'=>'{{site}} {{field:email}}']);
    mailCheck($defaults['notificationsEnabled']===false&&$settings['notificationsEnabled']&&$settings['recipients']===['owner@example.test']&&$settings['body']==='{{site}} {{field:email}}','Form delivery settings validate addresses and default to disabled');
    mailReject(fn()=>ProjectSettings::validateFormsSettings(['recipients'=>['not-an-email']]),422);
    mailReject(fn()=>ProjectSettings::validateFormsSettings(['unknown'=>true]),422);
    if (function_exists('pcntl_fork')) {
        $listener=stream_socket_server('tcp://127.0.0.1:0',$socketError,$socketMessage);
        if (!is_resource($listener)) throw new RuntimeException('Cannot create local fake SMTP server.');
        $address=stream_socket_get_name($listener,false);$port=(int)substr(strrchr($address,':'),1);$messagePath=$path.'.message';$pid=pcntl_fork();
        if ($pid===-1) throw new RuntimeException('Cannot fork local fake SMTP server.');
        if ($pid===0) {
            $connection=@stream_socket_accept($listener,5);if(!is_resource($connection))exit(2);stream_set_timeout($connection,5);fwrite($connection,"220 local.test ESMTP\r\n");$message='';
            while(($line=fgets($connection,2048))!==false){$verb=strtoupper(strtok(trim($line),' '));if($verb==='EHLO'){fwrite($connection,"250-local.test\r\n250 OK\r\n");continue;}if($verb==='MAIL'||$verb==='RCPT'){fwrite($connection,"250 OK\r\n");continue;}if($verb==='DATA'){fwrite($connection,"354 End data with <CR><LF>.<CR><LF>\r\n");while(($data=fgets($connection,4096))!==false&&$data!==".\r\n")$message.=$data;file_put_contents($messagePath,$message);fwrite($connection,"250 queued\r\n");continue;}if($verb==='QUIT'){fwrite($connection,"221 bye\r\n");break;}fwrite($connection,"500 unsupported\r\n");}
            fclose($connection);fclose($listener);exit(0);
        }
        fclose($listener);(new SmtpTransport())->send(['host'=>'127.0.0.1','port'=>$port,'security'=>'none','username'=>'','password'=>''],'forms@example.test','Juzimder',['owner@example.test'],'Новая заявка','Поля формы: {{email}}');pcntl_waitpid($pid,$childStatus);
        $wireMessage=(string)@file_get_contents($messagePath);@unlink($messagePath);
        mailCheck(pcntl_wifexited($childStatus)&&pcntl_wexitstatus($childStatus)===0&&str_contains($wireMessage,'Subject: =?UTF-8?B?')&&str_contains($wireMessage,'From: =?UTF-8?B?')&&str_contains($wireMessage,base64_encode('Поля формы: {{email}}')),'SMTP transport completes a local handshake and sends an encoded email');
    }
} finally { @unlink($path);@unlink($path.'.forms-key');@unlink($path.'-wal');@unlink($path.'-shm'); }
