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
const operationProject=(await a.call('/projects','POST',{name:'Typed operations'})).data;
assert.equal(operationProject.version,0);
assert.equal((await a.call(`/projects/${operationProject.id}/operations`,'POST',{version:0,operations:[{operation:'create_component',parentId:'page:home',component:{tagName:'section',name:'Generated section',components:[{type:'text',tagName:'h2',content:'Original heading'}]}}]})).status,200,'Typed create operation saves through the authenticated project API');
let operationState=(await a.call(`/projects/${operationProject.id}`)).data;
assert.equal(operationState.version,1);
const operationSection=operationState.document.data.pages[0].frames[0].component.components[0];
const generatedHeading=operationSection.components[0];
assert.match(operationSection.attributes.id,/^cmp-[a-f0-9]{24}$/);
assert.match(generatedHeading.attributes.id,/^cmp-[a-f0-9]{24}$/);
assert.equal((await a.call(`/projects/${operationProject.id}/operations`,'POST',{version:1,operations:[{operation:'update_content',componentId:generatedHeading.attributes.id,content:'No CSRF'}]},'bad')).status,403,'Typed operations require CSRF');
assert.equal((await a.call(`/projects/${operationProject.id}/operations`,'POST',{version:1,operations:[{operation:'update_content',componentId:generatedHeading.attributes.id,content:'Updated heading'}]})).status,200,'Typed content operation updates the requested component');
assert.equal((await a.call(`/projects/${operationProject.id}/operations`,'POST',{version:1,operations:[{operation:'update_content',componentId:generatedHeading.attributes.id,content:'Stale write'}]})).status,409,'Typed operations enforce expected project versions');
assert.equal((await a.call(`/projects/${operationProject.id}/operations`,'POST',{version:2,operations:[{operation:'update_styles',componentId:operationSection.attributes.id,styles:{position:'fixed'}}]})).status,422,'Typed operations enforce the style allowlist');
assert.equal((await a.call(`/projects/${operationProject.id}/operations`,'POST',{version:2,operations:[{operation:'update_styles',componentId:operationSection.attributes.id,styles:{color:'red; position:fixed'}}]})).status,422,'Typed operations reject CSS declaration injection');
assert.equal((await a.call(`/projects/${operationProject.id}/operations`,'POST',{version:2,operations:[{operation:'delete_component',componentId:generatedHeading.attributes.id}]})).status,422,'Typed delete requires explicit confirmation');
operationState=(await a.call(`/projects/${operationProject.id}`)).data;
assert.equal(operationState.version,2,'Rejected operations create no revision or project mutation');
assert.equal(operationState.document.data.pages[0].frames[0].component.components[0].components[0].content,'Updated heading');
assert.equal((await a.call(`/projects/${operationProject.id}/operations`,'POST',{version:2,operations:[{operation:'delete_component',componentId:generatedHeading.attributes.id,confirm:true}]})).status,200,'Confirmed typed delete creates a new saved revision');
assert.equal((await a.call(`/projects/${operationProject.id}/revisions`)).data.length,3);
const created=await a.call('/projects','POST',{name:'API workspace'});assert.equal(created.status,201);const p=created.data;
await b.call('/session');await b.call('/register','POST',{email:'two@example.test',password:'another-test-password'});
assert.deepEqual((await b.call('/projects')).data,[]);assert.equal((await b.call('/projects/'+p.id)).status,404);
assert.equal((await b.call(`/projects/${operationProject.id}/operations`,'POST',{version:3,operations:[{operation:'update_content',componentId:generatedHeading.attributes.id,content:'foreign'}]})).status,404,'Typed operations enforce project ownership');
assert.equal((await b.call('/projects/'+p.id,'PUT',{version:0,document:p.document})).status,404);
p.document.data.pages[0].frames[0].component.components.push({type:'text',tagName:'h1',content:'API saved heading',attributes:{id:'stable-heading'},style:{}});
p.document.data.pages[0].frames[0].component.components.push({type:'text',tagName:'span',content:'Empty object round trip',attributes:{},style:{}});
const saved=await a.call('/projects/'+p.id,'PUT',{version:0,document:p.document});assert.equal(saved.status,200);assert.equal(saved.data.version,1);
const savedDocument=saved.data.document;assert.deepEqual((await a.call('/projects/'+p.id)).data.document,savedDocument);assert.equal(savedDocument.data.pages[0].frames[0].component.style['--juzimder-color-ink'],'#183e31');
assert.equal((await a.call('/projects/'+p.id,'PUT',{version:0,document:p.document})).status,409);
assert.equal((await a.call('/projects/'+p.id,'PUT',{version:1,document:{schemaVersion:99}})).status,422);
const malformedDocument={schemaVersion:1,engine:'grapesjs',data:{pages:{home:{id:'home',frames:[{component:{type:'wrapper'}}]}}}};
assert.equal((await a.call('/projects/'+p.id,'PUT',{version:1,document:malformedDocument})).status,422);
const nullContent=structuredClone(savedDocument);nullContent.data.pages[0].frames[0].component.components[0].content=null;
assert.equal((await a.call('/projects/'+p.id,'PUT',{version:1,document:nullContent})).status,422);
assert.equal((await a.call('/projects/'+p.id)).data.version,1,'Rejected documents do not advance the project version');
assert.deepEqual((await a.call('/projects/'+p.id)).data.document,savedDocument,'Rejected documents preserve the last accepted data');
assert.equal((await a.call('/projects/'+p.id+'/revisions')).data.length,1);
const originalDocument=structuredClone(savedDocument),secondDocument=structuredClone(savedDocument);
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

test('Project pages have stable routes, atomic home selection and versioned mutations',async()=>{
const owner=new Client(),other=new Client();await owner.call('/session');await owner.call('/register','POST',{email:'pages@example.test',password:'a-long-pages-password'});await other.call('/session');await other.call('/register','POST',{email:'other-pages@example.test',password:'another-pages-password'});
const createdProject=await owner.call('/projects','POST',{name:'Pages workspace'}),project=createdProject.data;assert.equal(createdProject.status,201);
let listing=await owner.call(`/projects/${project.id}/pages`);assert.equal(listing.status,200);assert.equal(listing.data.pages.length,1);assert.deepEqual({path:listing.data.pages[0].path,home:listing.data.pages[0].isHome},{path:'/',home:true});
let result=await owner.call(`/projects/${project.id}/pages`,'POST',{version:0,action:'create',name:'О компании'});assert.equal(result.status,200);assert.equal(result.data.version,1);const pageId=result.data.document.data.pages[1].id;
listing=await owner.call(`/projects/${project.id}/pages`);assert.equal(listing.data.pages[1].slug,'o-kompanii');assert.equal(listing.data.pages.filter(page=>page.isHome).length,1);
result=await owner.call(`/projects/${project.id}/pages/${pageId}`,'POST',{version:1,action:'rename',name:'О нас'});assert.equal(result.status,200);assert.equal(result.data.version,2);assert.equal(result.data.settings.pages[pageId].slug,'o-kompanii','page name and URL can differ');
result=await owner.call(`/projects/${project.id}/pages/${pageId}`,'POST',{version:2,action:'slug',slug:'/Контакты'});assert.equal(result.status,200);assert.equal(result.data.settings.pages[pageId].slug,'kontakty');
result=await owner.call(`/projects/${project.id}/pages/${pageId}`,'POST',{version:3,action:'duplicate'});assert.equal(result.status,200);assert.equal(result.data.document.data.pages.length,3);const duplicateId=result.data.document.data.pages[2].id;
assert.equal((await owner.call(`/projects/${project.id}/pages/${duplicateId}`,'POST',{version:4,action:'slug',slug:'kontakty'})).status,422,'duplicate routes are rejected');
assert.equal((await owner.call(`/projects/${project.id}/pages/${duplicateId}`,'POST',{version:4,action:'slug',slug:'api'})).status,422,'system routes are reserved');
assert.equal((await owner.call(`/projects/${project.id}/pages/${duplicateId}`,'POST',{version:3,action:'home'})).status,409,'stale page changes are rejected');
result=await owner.call(`/projects/${project.id}/pages/${duplicateId}`,'POST',{version:4,action:'home'});assert.equal(result.status,200);listing=await owner.call(`/projects/${project.id}/pages`);assert.equal(listing.data.pages.filter(page=>page.isHome).length,1);assert.equal(listing.data.pages[2].path,'/');
result=await owner.call(`/projects/${project.id}/pages/${duplicateId}`,'POST',{version:5,action:'delete'});assert.equal(result.status,200);listing=await owner.call(`/projects/${project.id}/pages`);assert.equal(listing.data.pages[0].isHome,true,'deleting home chooses the first remaining page');
assert.equal((await other.call(`/projects/${project.id}/pages`)).status,404,'page metadata follows project ownership');
const restored=await owner.call(`/projects/${project.id}/revisions/restore`,'POST',{currentVersion:6,targetVersion:4});assert.equal(restored.status,200);assert.equal(restored.data.version,7);assert.equal(restored.data.settings.pages.home.isHome,true,'page routes restore with the matching document revision');
});

test('Project design tokens use validated settings, revisions and server-owned route metadata',async()=>{
const owner=new Client(),other=new Client();await owner.call('/session');await owner.call('/register','POST',{email:'tokens@example.test',password:'a-long-token-password'});await other.call('/session');await other.call('/register','POST',{email:'token-outsider@example.test',password:'another-token-password'});
const created=await owner.call('/projects','POST',{name:'Token workspace'}),project=created.data;assert.equal(created.status,201);assert.equal(project.settings.schemaVersion,2);assert.equal(project.settings.designTokens.fontFamily,'Arial, sans-serif');
const settingsPath=`/projects/${project.id}/settings`,changes={ink:'#112233',fontFamily:'Georgia, serif',radius:'16px'};
assert.equal((await owner.call(settingsPath,'PUT',{version:0,designTokens:changes},'bad')).status,403,'Project settings require CSRF');
assert.equal((await owner.call(settingsPath,'PUT',{version:0,designTokens:{...changes,unknown:'#ffffff'}})).status,422,'Unknown design tokens are rejected');
assert.equal((await owner.call(settingsPath,'PUT',{version:0,designTokens:{ink:'red'}})).status,422,'Unsafe token values are rejected');
assert.equal((await owner.call(settingsPath,'PUT',{version:1,designTokens:changes})).status,409,'Project settings use version preconditions');
assert.equal((await other.call(settingsPath,'PUT',{version:0,designTokens:changes})).status,404,'Project settings enforce ownership');
let saved=await owner.call(settingsPath,'PUT',{version:0,designTokens:changes});assert.equal(saved.status,200);assert.equal(saved.data.version,1);assert.equal(saved.data.settings.designTokens.ink,'#112233');assert.equal(saved.data.settings.designTokens.fontFamily,'Georgia, serif');
assert.equal(saved.data.document.data.pages[0].frames[0].component.style['--juzimder-color-ink'],'#112233');assert.equal((await owner.call(`/projects/${project.id}/revisions`)).data[0].version,1,'A settings save creates a revision');
let page=await owner.call(`/projects/${project.id}/pages`,'POST',{version:1,action:'create',name:'About'});assert.equal(page.status,200);assert.equal(page.data.document.data.pages[1].frames[0].component.style['--juzimder-font-family'],'Georgia, serif','New pages inherit persisted global tokens');
let restored=await owner.call(`/projects/${project.id}/revisions/restore`,'POST',{currentVersion:2,targetVersion:1});assert.equal(restored.status,200);assert.equal(restored.data.version,3);assert.equal(restored.data.settings.designTokens.ink,'#112233','Design tokens restore with their matching revision');
const nextTokens={...restored.data.settings.designTokens,accent:'#8899aa'};
saved=await owner.call(`/projects/${project.id}`,'PUT',{version:3,document:restored.data.document,settings:{designTokens:nextTokens}});assert.equal(saved.status,200);assert.equal(saved.data.settings.designTokens.accent,'#8899aa');assert.equal(Object.keys(saved.data.settings.pages).length,1,'Editor settings cannot replace route metadata');
assert.equal((await owner.call(`/projects/${project.id}`,'PUT',{version:4,document:saved.data.document,settings:{pages:{}}})).status,422,'Editor settings accept only design tokens');
});
