import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
let server, dir;
const base='http://127.0.0.1:18081';
class Client {
  cookie=''; csrf='';
  async call(path,method='GET',body,token=this.csrf){const r=await fetch(base+'/api'+path,{method,headers:{Cookie:this.cookie,'Content-Type':'application/json','X-CSRF-Token':token},...(body?{body:JSON.stringify(body)}:{})});const cookie=r.headers.get('set-cookie');if(cookie)this.cookie=cookie.split(';')[0];const data=await r.json();if(data.csrf)this.csrf=data.csrf;return {status:r.status,data};}
}
before(async()=>{dir=await mkdtemp(join(tmpdir(),'juzimder-api-'));server=spawn('php',['-S','127.0.0.1:18081','-t','public','public/router.php'],{env:{...process.env,JUZIMDER_DB:join(dir,'test.sqlite')},stdio:'ignore'});for(let i=0;i<50;i++){try{await fetch(base+'/api/session');return;}catch{await new Promise(r=>setTimeout(r,100));}}throw Error('API startup failed');});
after(async()=>{if(server){server.kill();await new Promise(r=>server.once('exit',r));}if(dir)await rm(dir,{recursive:true,force:true});});
test('Authenticated persistence, tenant isolation, CSRF, validation, revisions and login',async()=>{
const a=new Client(),b=new Client(),anon=new Client();
assert.equal((await anon.call('/projects')).status,401);
await a.call('/session');assert.equal((await a.call('/register','POST',{email:'one@example.test',password:'a-long-test-password'} ,'bad')).status,403);
assert.equal((await a.call('/register','POST',{email:'one@example.test',password:'a-long-test-password'})).status,200);
const created=await a.call('/projects','POST',{name:'API workspace'});assert.equal(created.status,201);const p=created.data;
await b.call('/session');await b.call('/register','POST',{email:'two@example.test',password:'another-test-password'});
assert.deepEqual((await b.call('/projects')).data,[]);assert.equal((await b.call('/projects/'+p.id)).status,404);
assert.equal((await b.call('/projects/'+p.id,'PUT',{version:0,document:p.document})).status,404);
p.document.data.pages[0].frames[0].component.components.push({type:'text',tagName:'h1',content:'API saved heading',attributes:{id:'stable-heading'}});
const saved=await a.call('/projects/'+p.id,'PUT',{version:0,document:p.document});assert.equal(saved.status,200);assert.equal(saved.data.version,1);
assert.deepEqual((await a.call('/projects/'+p.id)).data.document,p.document);
assert.equal((await a.call('/projects/'+p.id,'PUT',{version:0,document:p.document})).status,409);
assert.equal((await a.call('/projects/'+p.id,'PUT',{version:1,document:{schemaVersion:99}})).status,422);
assert.equal((await a.call('/projects/'+p.id+'/revisions')).data.length,1);
const originalDocument=structuredClone(p.document),secondDocument=structuredClone(p.document);
secondDocument.data.pages[0].frames[0].component.components[0].content='Second revision';
assert.equal((await a.call('/projects/'+p.id,'PUT',{version:1,document:secondDocument})).data.version,2);
const restored=await a.call('/projects/'+p.id+'/revisions/restore','POST',{currentVersion:2,targetVersion:1});
assert.equal(restored.status,200);assert.equal(restored.data.version,3);assert.deepEqual(restored.data.document,originalDocument);
assert.equal((await a.call('/projects/'+p.id+'/revisions/restore','POST',{currentVersion:2,targetVersion:2})).status,409);
assert.equal((await b.call('/projects/'+p.id+'/revisions/restore','POST',{currentVersion:3,targetVersion:1})).status,404);
assert.equal((await a.call('/projects/'+p.id+'/revisions/restore','POST',{currentVersion:3,targetVersion:999})).status,404);
assert.equal((await a.call('/projects/'+p.id+'/revisions/restore','POST',{currentVersion:3,targetVersion:0})).status,422);
assert.equal((await a.call('/projects/'+p.id+'/revisions')).data.length,3);
assert.equal((await a.call('/logout','POST',{})).status,200);assert.equal((await a.call('/projects')).status,401);
await a.call('/session');assert.equal((await a.call('/login','POST',{email:'one@example.test',password:'wrong-test-password'})).status,401);
assert.equal((await a.call('/login','POST',{email:'one@example.test',password:'a-long-test-password'})).status,200);
assert.equal((await a.call('/projects/'+p.id)).data.version,3);
});
