import test from 'node:test';
import assert from 'node:assert/strict';
import http from 'node:http';
import {execFile} from 'node:child_process';
import {promisify} from 'node:util';
const exec=promisify(execFile);
test('AI transport bounds drip responses, body size and redirects', async()=>{
  const server=http.createServer((req,res)=>{
    if(req.url==='/drip'){
      res.writeHead(200);res.write(' ');
      const timer=setInterval(()=>res.write(' '),100);
      res.on('close',()=>clearInterval(timer));
    }else if(req.url==='/large'){res.end('x'.repeat(1000001));}
    else if(req.url==='/redirect'){res.writeHead(302,{Location:'/ok'});res.end();}
    else {res.end('{"ok":true}');}
  });
  await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
  const url=`http://127.0.0.1:${server.address().port}`;
  try{
    const code=String.raw`require 'backend/Ai.php'; $t=new Juzimder\StreamAiHttpTransport(); try{$r=$t->post($argv[1],['Content-Type: application/json'],'{}',1); echo json_encode(['status'=>$r->status,'body'=>$r->body]);}catch(RuntimeException){echo 'bounded-failure';}`;
    const request=async(path)=>(await exec('php',['-r',code,url+path],{timeout:5000})).stdout;
    assert.deepEqual(JSON.parse(await request('/ok')),{status:200,body:'{"ok":true}'});
    const started=Date.now();
    assert.equal(await request('/drip'),'bounded-failure');
    assert.ok(Date.now()-started<2500,'total timeout must survive continuously arriving bytes');
    assert.equal(await request('/large'),'bounded-failure');
    assert.equal(JSON.parse(await request('/redirect')).status,302);
  }finally{server.closeAllConnections();await new Promise(resolve=>server.close(resolve));}
});
