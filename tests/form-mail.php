<?php
declare(strict_types=1);
require __DIR__.'/../backend/Domain.php';
require __DIR__.'/../backend/FormMail.php';
use Juzimder\{Store,FormMailVault,ProjectSettings,Problem,SmtpEgressDenied,SmtpEgressPolicy,SmtpTransport};
function mailCheck(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); echo "PASS $message\n"; }
function mailReject(callable $fn,int $status): void { try { $fn(); } catch (Problem $error) { mailCheck($error->status===$status,'Reject invalid mail settings'); return; } throw new RuntimeException('Expected mail settings rejection.'); }
$path=sys_get_temp_dir().'/juzimder-mail-'.bin2hex(random_bytes(6)).'.sqlite';
try {
    $store=new Store($path);$store->query('INSERT INTO users(id,email,password_hash) VALUES(?,?,?)',['mail-owner','owner@example.test','hash']);$project=$store->create('mail-owner','Mail project');$vault=new FormMailVault($store);
    $status=$vault->status($project['id'],'mail-owner');mailCheck($status['version']===0&&!$status['configured']&&!array_key_exists('password',$status),'SMTP status never returns credentials');
    $saved=$vault->save($project['id'],'mail-owner',0,['host'=>'smtp.example.test','port'=>587,'security'=>'starttls','username'=>'user@example.test','password'=>'smtp-test-secret']);
    mailCheck($saved['configured']&&$saved['version']===1&&$saved['hasPassword']&&!array_key_exists('password',$saved),'SMTP settings save with redacted secret');
    $store->consumeFormMailTestAllowance($project['id'],'mail-owner',1000);
    $store->consumeFormMailTestAllowance($project['id'],'mail-owner',1001);
    $store->consumeFormMailTestAllowance($project['id'],'mail-owner',1002);
    mailReject(fn()=>$store->consumeFormMailTestAllowance($project['id'],'mail-owner',1003),429);
    $store->consumeFormMailTestAllowance($project['id'],'mail-owner',1600);
    mailCheck((int)$store->query('SELECT requests FROM project_form_mail_test_limits WHERE project_id=? AND user_id=?',[$project['id'],'mail-owner'])->fetchColumn()===1,'SMTP test rate limit allows three attempts per ten minutes and resets the next window');
    $cipher=$store->query('SELECT settings_ciphertext FROM project_form_mail_settings WHERE project_id=?',[$project['id']])->fetchColumn();
    mailCheck(is_string($cipher)&&!str_contains($cipher,'smtp-test-secret')&&$vault->deliverySettings($project['id'])['password']==='smtp-test-secret','SMTP password is encrypted at rest and available to server delivery only');
    mailReject(fn()=>$vault->save($project['id'],'mail-owner',0,['host'=>'smtp.example.test','port'=>587,'security'=>'starttls','username'=>'user@example.test','password'=>'new-secret']),409);
    mailReject(fn()=>$vault->save($project['id'],'mail-owner',1,['host'=>'smtp.example.test','port'=>25,'security'=>'none','username'=>'user@example.test','password'=>'new-secret']),422);
    $defaults=ProjectSettings::formDefaults();$settings=ProjectSettings::validateFormsSettings(['notificationsEnabled'=>true,'recipients'=>['owner@example.test','OWNER@example.test'],'senderEmail'=>'forms@example.test','body'=>'{{site}} {{field:email}}']);
    mailCheck($defaults['notificationsEnabled']===false&&$settings['notificationsEnabled']&&$settings['recipients']===['owner@example.test']&&$settings['body']==='{{site}} {{field:email}}','Form delivery settings validate addresses and default to disabled');
    mailReject(fn()=>ProjectSettings::validateFormsSettings(['recipients'=>['not-an-email']]),422);
    mailReject(fn()=>ProjectSettings::validateFormsSettings(['unknown'=>true]),422);
    $publicPolicy=new SmtpEgressPolicy([],static fn(string $host):array=>['8.8.8.8','2001:4860:4860::8888','8.8.8.8']);
    mailCheck($publicPolicy->resolve('smtp.example.test')===['8.8.8.8','2001:4860:4860::8888'],'SMTP DNS answers are deduplicated and public IPv4/IPv6 addresses remain available');
    $blockedPolicy=new SmtpEgressPolicy([],static fn(string $host):array=>['127.0.0.1']);
    try {$blockedPolicy->resolve('smtp.example.test');throw new RuntimeException('Expected loopback SMTP destination to be blocked.');} catch (SmtpEgressDenied) {mailCheck(true,'SMTP egress blocks loopback by default');}
    foreach (['100.64.0.1','198.18.0.1','192.0.2.10','::1','::ffff:127.0.0.1'] as $unsafeIp) {
        try {(new SmtpEgressPolicy())->resolve($unsafeIp);throw new RuntimeException('Expected special SMTP destination to be blocked: '.$unsafeIp);} catch (SmtpEgressDenied) {mailCheck(true,'SMTP egress blocks special-purpose address '.$unsafeIp);}
    }
    try {(new SmtpEgressPolicy([],static fn(string $host):array=>['8.8.8.8','10.0.0.4']))->resolve('smtp.example.test');throw new RuntimeException('Expected mixed public/private DNS answers to be blocked.');} catch (SmtpEgressDenied) {mailCheck(true,'SMTP egress rejects a hostname with any unapproved private DNS answer');}
    $privateRelayPolicy=new SmtpEgressPolicy(['10.20.30.40'],static fn(string $host):array=>['10.20.30.40']);
    mailCheck($privateRelayPolicy->resolve('relay.internal.test')===['10.20.30.40'],'Self-hosted SMTP relays can be explicitly allowed by exact IP');
    $privateRelayPolicy->assertAllowedPort(587);
    try {$privateRelayPolicy->assertAllowedPort(8080);throw new RuntimeException('Expected a non-SMTP port to be blocked.');} catch (SmtpEgressDenied) {mailCheck(true,'SMTP egress blocks non-mail ports by default');}
    $customPortPolicy=new SmtpEgressPolicy([],null,[2526]);$customPortPolicy->assertAllowedPort(2526);
    try {$customPortPolicy->assertAllowedPort(587);throw new RuntimeException('Expected unconfigured SMTP port to be blocked.');} catch (SmtpEgressDenied) {mailCheck(true,'Self-hosted custom port policy uses only its explicit port list');}
    $previousIps=getenv('JUZIMDER_SMTP_EGRESS_ALLOWED_IPS');$previousPorts=getenv('JUZIMDER_SMTP_EGRESS_ALLOWED_PORTS');
    try {
        putenv('JUZIMDER_SMTP_EGRESS_ALLOWED_IPS=10.20.30.40');putenv('JUZIMDER_SMTP_EGRESS_ALLOWED_PORTS=2526');
        $environmentPolicy=SmtpEgressPolicy::fromEnvironment();
        mailCheck($environmentPolicy->resolve('10.20.30.40')===['10.20.30.40'],'Server policy loads exact internal relay IP from process configuration');
        $environmentPolicy->assertAllowedPort(2526);
        try {$environmentPolicy->assertAllowedPort(587);throw new RuntimeException('Expected the process port allowlist to be exact.');} catch (SmtpEgressDenied) {mailCheck(true,'Server policy loads an exact custom SMTP port list');}
    } finally {
        $previousIps===false?putenv('JUZIMDER_SMTP_EGRESS_ALLOWED_IPS'):putenv('JUZIMDER_SMTP_EGRESS_ALLOWED_IPS='.$previousIps);
        $previousPorts===false?putenv('JUZIMDER_SMTP_EGRESS_ALLOWED_PORTS'):putenv('JUZIMDER_SMTP_EGRESS_ALLOWED_PORTS='.$previousPorts);
    }
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
        fclose($listener);(new SmtpTransport(new SmtpEgressPolicy(['127.0.0.1'],null,[$port])))->send(['host'=>'127.0.0.1','port'=>$port,'security'=>'none','username'=>'','password'=>''],'forms@example.test','Juzimder',['owner@example.test'],'Новая заявка','Поля формы: {{email}}');pcntl_waitpid($pid,$childStatus);
        $wireMessage=(string)@file_get_contents($messagePath);@unlink($messagePath);
        mailCheck(pcntl_wifexited($childStatus)&&pcntl_wexitstatus($childStatus)===0&&str_contains($wireMessage,'Subject: =?UTF-8?B?')&&str_contains($wireMessage,'From: =?UTF-8?B?')&&str_contains($wireMessage,base64_encode('Поля формы: {{email}}')),'SMTP transport completes a local handshake and sends an encoded email');
    }
} finally { @unlink($path);@unlink($path.'.forms-key');@unlink($path.'-wal');@unlink($path.'-shm'); }
