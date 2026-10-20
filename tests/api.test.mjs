import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
let server, devServer, dir, devDir;
const base='http://127.0.0.1:18081';
const devBase='http://127.0.0.1:18082';
class Client {
  constructor(url=base){this.url=url;}
  cookie=''; csrf='';
  async call(path,method='GET',body,token=this.csrf){const r=await fetch(this.url+'/api'+path,{method,headers:{Cookie:this.cookie,'Content-Type':'application/json','X-CSRF-Token':token},...(body?{body:JSON.stringify(body)}:{})});const cookie=r.headers.get('set-cookie');if(cookie)this.cookie=cookie.split(';')[0];const data=await r.json();if(data.csrf)this.csrf=data.csrf;return {status:r.status,data};}
}
async function waitFor(url){for(let i=0;i<50;i++){try{await fetch(url+'/api/session');return;}catch{await new Promise(r=>setTimeout(r,100));}}throw Error(`API startup failed at ${url}`);}
before(async()=>{dir=await mkdtemp(join(tmpdir(),'juzimder-api-'));devDir=await mkdtemp(join(tmpdir(),'juzimder-dev-api-'));const testEnv={...process.env,JUZIMDER_DB:join(dir,'test.sqlite'),OPENROUTER_API_KEY:'',OPENROUTER_MODEL:''};server=spawn('php',['-S','127.0.0.1:18081','-t','public','public/router.php'],{env:testEnv,stdio:'ignore'});await waitFor(base);devServer=spawn('php',['-S','127.0.0.1:18082','-t','public','public/router.php'],{env:{...testEnv,JUZIMDER_DB:join(devDir,'test.sqlite'),JUZIMDER_DEV_AUTH:'1'},stdio:'ignore'});await waitFor(devBase);});
after(async()=>{for(const child of [server,devServer])if(child){child.kill();await new Promise(r=>child.once('exit',r));}for(const path of [dir,devDir])if(path)await rm(path,{recursive:true,force:true});});
test('Authenticated persistence, tenant isolation, CSRF, validation, revisions and login',async()=>{
const a=new Client(),b=new Client(),anon=new Client();
assert.equal((await anon.call('/projects')).status,401);
await anon.call('/session');assert.equal((await anon.call('/ai/status')).status,401,'AI status requires an authenticated user');
await a.call('/session');assert.equal((await a.call('/register','POST',{email:'one@example.test',password:'a-long-test-password'} ,'bad')).status,403);
assert.equal((await a.call('/register','POST',{email:'one@example.test',password:'a-long-test-password'})).status,200);
assert.deepEqual((await a.call('/ai/status')).data,{provider:'openrouter',configured:false,model:null});
assert.equal((await a.call('/ai/design-plan','POST',{prompt:'Создай сайт клиники'},'bad')).status,403,'AI planning requires a valid CSRF token');
assert.equal((await anon.call('/ai/design-plan','POST',{prompt:'Создай сайт клиники'})).status,401,'AI planning requires an authenticated user');
assert.equal((await a.call('/ai/design-plan','POST',{prompt:'   '})).status,422,'AI planning rejects empty prompts');
assert.equal((await a.call('/ai/design-plan','POST',{prompt:'x'.repeat(4001)})).status,422,'AI planning enforces its prompt limit');
assert.equal((await a.call('/ai/design-plan','POST',{prompt:'Создай сайт клиники'})).status,503,'AI planning stays disabled without server credentials');
assert.deepEqual((await a.call('/projects')).data,[],'AI planning does not create or mutate projects');
const created=await a.call('/projects','POST',{name:'API workspace'});assert.equal(created.status,201);const p=created.data;
await b.call('/session');await b.call('/register','POST',{email:'two@example.test',password:'another-test-password'});
assert.deepEqual((await b.call('/projects')).data,[]);assert.equal((await b.call('/projects/'+p.id)).status,404);
assert.equal((await b.call('/projects/'+p.id,'PUT',{version:0,document:p.document})).status,404);
p.document.data.pages[0].frames[0].component.components.push({type:'text',tagName:'h1',content:'API saved heading',attributes:{id:'stable-heading'},style:{}});
p.document.data.pages[0].frames[0].component.components.push({type:'text',tagName:'span',content:'Empty object round trip',attributes:{},style:{}});
const saved=await a.call('/projects/'+p.id,'PUT',{version:0,document:p.document});assert.equal(saved.status,200);assert.equal(saved.data.version,1);
assert.deepEqual((await a.call('/projects/'+p.id)).data.document,p.document);
assert.equal((await a.call('/projects/'+p.id,'PUT',{version:0,document:p.document})).status,409);
assert.equal((await a.call('/projects/'+p.id,'PUT',{version:1,document:{schemaVersion:99}})).status,422);
const malformedDocument={schemaVersion:1,engine:'grapesjs',data:{pages:{home:{id:'home',frames:[{component:{type:'wrapper'}}]}}}};
assert.equal((await a.call('/projects/'+p.id,'PUT',{version:1,document:malformedDocument})).status,422);
const nullContent=structuredClone(p.document);nullContent.data.pages[0].frames[0].component.components[0].content=null;
assert.equal((await a.call('/projects/'+p.id,'PUT',{version:1,document:nullContent})).status,422);
assert.equal((await a.call('/projects/'+p.id)).data.version,1,'Rejected documents do not advance the project version');
assert.deepEqual((await a.call('/projects/'+p.id)).data.document,p.document,'Rejected documents preserve the last accepted data');
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

test('admin/admin is available only with loopback development auth enabled',async()=>{
const regular=new Client(),dev=new Client(devBase),secondDev=new Client(devBase);
await regular.call('/session');assert.equal((await regular.call('/login','POST',{email:'admin',password:'admin'})).status,422);
const session=await dev.call('/session');assert.equal(session.data.devAuth,true);
const login=await dev.call('/login','POST',{email:'admin',password:'admin'});assert.equal(login.status,200);assert.equal(login.data.user.email,'admin@localhost.test');
assert.equal((await dev.call('/projects')).status,200);assert.deepEqual((await dev.call('/projects')).data,[]);
await secondDev.call('/session');assert.equal((await secondDev.call('/login','POST',{email:'admin',password:'admin'})).status,200,'the seeded local account persists in SQLite');
});
