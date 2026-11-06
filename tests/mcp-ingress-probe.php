<?php
declare(strict_types=1);
require __DIR__.'/../backend/McpIngressProbe.php';
use Juzimder\McpIngressProbe as Probe;
function probeCheck(bool $value,string $message):void{if(!$value)throw new RuntimeException($message);echo "PASS $message\n";}
$db=sys_get_temp_dir().'/juzimder-probe-'.bin2hex(random_bytes(8));
try{
 Probe::record($db,str_repeat('a',32),'POST');probeCheck(!file_exists($db.'.mcp-ingress-probe'),'Inactive probe writes nothing');
 $state=Probe::start($db);probeCheck(count($state['hits'])===0&&strlen($state['id'])===32,'Probe starts empty with random id');
 Probe::record($db,str_repeat('a',32),'POST');probeCheck(Probe::read($db)['hits']===[],'Wrong id ignored');
 Probe::record($db,$state['id'],'POST');$record=Probe::read($db);probeCheck($record['hits'][0]['method']==='POST','Matching request records method');
 probeCheck(array_keys($record['hits'][0])===['method','at'],'Only method and timestamp persisted');
 for($i=0;$i<12;$i++)Probe::record($db,$state['id'],'GET');probeCheck(count(Probe::read($db)['hits'])===10,'Record count bounded');
 $expired=$state;$expired['expiresAt']=time()-1;file_put_contents($db.'.mcp-ingress-probe',json_encode($expired));Probe::record($db,$state['id'],'POST');probeCheck(Probe::read($db)===null,'Expired probe disabled');
}finally{@unlink($db.'.mcp-ingress-probe');}
