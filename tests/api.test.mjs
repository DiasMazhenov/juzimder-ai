import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync, spawn } from 'node:child_process';
import { request as httpRequest } from 'node:http';
import { mkdtemp, readFile, rm, stat, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { blocks } from '../src/blocks.js';
import { AI_SECTION_TEXT_FIELDS, buildDesignPlanOperations } from '../src/ai-plan.js';
let server, devServer, dir, devDir, testDbPath;
const base='http://127.0.0.1:18081';
const devBase='http://127.0.0.1:18082';
class Client {
  constructor(url=base){this.url=url;}
  cookie=''; csrf='';
  async call(path,method='GET',body,token=this.csrf){const r=await fetch(this.url+'/api'+path,{method,headers:{Cookie:this.cookie,'Content-Type':'application/json','X-CSRF-Token':token},...(body?{body:JSON.stringify(body)}:{})});const cookie=r.headers.get('set-cookie');if(cookie)this.cookie=cookie.split(';')[0];const data=await r.json();if(data.csrf)this.csrf=data.csrf;return {status:r.status,data};}
  async download(path){const r=await fetch(this.url+'/api'+path,{headers:{Cookie:this.cookie}});const cookie=r.headers.get('set-cookie');if(cookie)this.cookie=cookie.split(';')[0];return {status:r.status,type:r.headers.get('content-type'),body:Buffer.from(await r.arrayBuffer())};}
  async upload(path,bytes,name,token=this.csrf){const r=await fetch(this.url+'/api'+path,{method:'POST',headers:{Cookie:this.cookie,'Content-Type':'application/octet-stream','X-CSRF-Token':token,'X-Asset-Name':encodeURIComponent(name)},body:bytes});const cookie=r.headers.get('set-cookie');if(cookie)this.cookie=cookie.split(';')[0];return {status:r.status,data:await r.json()};}
}
async function publicCall(projectId,path='/',method='GET'){
  const endpoint=new URL(base);
  return new Promise((resolve,reject)=>{
    const request=httpRequest({hostname:endpoint.hostname,port:endpoint.port,path,method,headers:{Host:`${projectId}.juzimder-ai.localhost:18081`}},response=>{
      const chunks=[];response.on('data',chunk=>chunks.push(chunk));response.on('end',()=>resolve({status:response.statusCode,headers:response.headers,body:Buffer.concat(chunks).toString('utf8')}));
    });
    request.on('error',reject);request.end();
  });
}
async function publicBinaryCall(projectId,path,method='GET'){
  const endpoint=new URL(base);
  return new Promise((resolve,reject)=>{
    const request=httpRequest({hostname:endpoint.hostname,port:endpoint.port,path,method,headers:{Host:`${projectId}.juzimder-ai.localhost:18081`}},response=>{
      const chunks=[];response.on('data',chunk=>chunks.push(chunk));response.on('end',()=>resolve({status:response.statusCode,headers:response.headers,body:Buffer.concat(chunks)}));
    });
    request.on('error',reject);request.end();
  });
}
async function waitFor(url){for(let i=0;i<50;i++){try{await fetch(url+'/api/session');return;}catch{await new Promise(r=>setTimeout(r,100));}}throw Error(`API startup failed at ${url}`);}
before(async()=>{dir=await mkdtemp(join(tmpdir(),'juzimder-api-'));devDir=await mkdtemp(join(tmpdir(),'juzimder-dev-api-'));testDbPath=join(dir,'test.sqlite');const testEnv={...Object.fromEntries(Object.entries(process.env).filter(([key])=>!key.startsWith('OPENROUTER_'))),JUZIMDER_DB:testDbPath,JUZIMDER_PUBLIC_HOST_SUFFIX:'juzimder-ai.localhost:18081',JUZIMDER_PUBLIC_SCHEME:'http'};server=spawn('php',['-S','127.0.0.1:18081','-t','public','public/router.php'],{env:testEnv,stdio:'ignore'});await waitFor(base);devServer=spawn('php',['-S','127.0.0.1:18082','-t','public','public/router.php'],{env:{...testEnv,JUZIMDER_DB:join(devDir,'test.sqlite'),JUZIMDER_DEV_AUTH:'1'},stdio:'ignore'});await waitFor(devBase);});
after(async()=>{for(const child of [server,devServer])if(child){child.kill();await new Promise(r=>child.once('exit',r));}for(const path of [dir,devDir])if(path)await rm(path,{recursive:true,force:true});});
test('Authenticated persistence, tenant isolation, CSRF, validation, revisions and login',async()=>{
const a=new Client(),b=new Client(),anon=new Client();
assert.equal((await anon.call('/projects')).status,401);
await anon.call('/session');
await a.call('/session');assert.equal((await a.call('/register','POST',{email:'one@example.test',password:'a-long-test-password'} ,'bad')).status,403);
const isolatedDatabaseClient=new Client(devBase);await isolatedDatabaseClient.call('/session');assert.notEqual(a.cookie.split('=')[0],isolatedDatabaseClient.cookie.split('=')[0],'separate SQLite databases use separate browser session cookie names');
assert.equal((await a.call('/register','POST',{email:'one@example.test',password:'a-long-test-password'})).status,200);
const aiProject=(await a.call('/projects','POST',{name:'AI credentials'})).data,aiSettingsPath=`/projects/${aiProject.id}/ai/settings`,aiPlanPath=`/projects/${aiProject.id}/ai/design-plan`,aiGeneratePath=`/projects/${aiProject.id}/ai/generate-page`;
assert.equal((await a.call('/ai/status')).status,404,'The former global environment-configured AI endpoint is removed');
assert.equal((await anon.call(aiSettingsPath)).status,401,'AI settings require an authenticated user');
assert.deepEqual((await a.call(aiSettingsPath)).data,{provider:'openrouter',configured:false,model:null,fallbackModel:null,activeProfileId:null,profiles:[],version:0});
assert.equal((await a.call(aiSettingsPath,'PUT',{version:0,apiKey:'sk-test-only',model:'openai/test-model'},'bad')).status,403,'Saving an AI key requires CSRF');
assert.equal((await a.call(aiSettingsPath,'PUT',{version:0,apiKey:'sk-test-only',model:'bad model'})).status,422,'AI model ids are validated');
assert.equal((await a.call(aiSettingsPath,'PUT',{version:0,apiKey:'',model:'openai/test-model'})).status,422,'Initial AI configuration requires an API key');
const aiProfilesPath='/projects/'+aiProject.id+'/ai/profiles';
assert.equal((await a.call(aiProfilesPath,'POST',{version:0,name:'CSRF profile',apiKey:'sk-test-only',model:'openai/test-model'},'bad')).status,403,'Creating an AI profile requires CSRF');
assert.equal((await a.call(aiProfilesPath,'POST',{version:0,name:'',apiKey:'sk-test-only',model:'openai/test-model'})).status,422,'Profile creation validates the profile name');
assert.equal((await a.call(aiProfilesPath,'POST',{version:0,name:'Missing key',apiKey:'',model:'openai/test-model'})).status,422,'A new AI profile requires an API key');
assert.equal((await a.call(aiProfilesPath,'POST',{version:0,name:'Duplicate fallback',apiKey:'sk-test-only',model:'openai/test-model',fallbackModel:'openai/test-model'})).status,422,'Fallback model must be valid and differ from primary');
assert.equal((await a.call(aiPlanPath,'POST',{prompt:'Создай сайт клиники'},'bad')).status,403,'AI planning requires a valid CSRF token');
assert.equal((await anon.call(aiPlanPath,'POST',{prompt:'Создай сайт клиники'})).status,401,'AI planning requires an authenticated user');
assert.equal((await a.call(aiPlanPath,'POST',{prompt:'   '})).status,422,'AI planning rejects empty prompts');
assert.equal((await a.call(aiPlanPath,'POST',{prompt:'x'.repeat(4001)})).status,422,'AI planning enforces its prompt limit');
assert.equal((await a.call(aiPlanPath,'POST',{prompt:'Создай сайт клиники'})).status,503,'AI planning stays disabled until a project key is configured');
assert.equal((await anon.call(aiGeneratePath,'POST',{prompt:'Сайт клиники'})).status,401,'Full-page AI generation requires an authenticated user');
assert.equal((await a.call(aiGeneratePath,'POST',{prompt:'Сайт клиники'},'bad')).status,403,'Full-page AI generation requires CSRF');
assert.equal((await a.call(aiGeneratePath,'POST',{prompt:'   '})).status,422,'Full-page AI generation rejects empty prompts');
assert.equal((await a.call(aiGeneratePath,'POST',{prompt:'x'.repeat(4001)})).status,422,'Full-page AI generation enforces its prompt limit');
assert.equal((await a.call(aiGeneratePath,'POST',{prompt:'Сайт клиники',projectData:{secret:'must not be accepted'}})).status,422,'Full-page AI generation accepts only the user prompt and profile');
assert.equal((await a.call(aiGeneratePath,'POST',{prompt:'Сайт клиники',skillProfile:'untrusted-profile'})).status,422,'Full-page AI generation accepts only allowlisted skill profiles');
assert.equal((await a.call(aiGeneratePath,'POST',{prompt:'Сайт клиники',skillProfile:null})).status,422,'Full-page AI generation rejects an explicitly null skill profile');
assert.equal((await a.call(aiGeneratePath,'POST',{prompt:'Сайт клиники'})).status,503,'Full-page AI generation stays disabled until a project profile is configured');
const testApiKey='sk-or-v1-this-is-a-test-secret-only';
let aiSaved=await a.call(aiSettingsPath,'PUT',{version:0,apiKey:testApiKey,model:'openai/test-model',fallbackModel:'openai/fallback-model'});
assert.equal(aiSaved.status,200);assert.equal(aiSaved.data.configured,true);assert.equal(aiSaved.data.model,'openai/test-model');assert.equal(aiSaved.data.fallbackModel,'openai/fallback-model');assert.equal(aiSaved.data.profiles[0].fallbackModel,'openai/fallback-model');assert.equal(aiSaved.data.version,1);
const primaryProfile=aiSaved.data.profiles.find(profile=>profile.active);
assert.ok(primaryProfile);assert.equal(primaryProfile.name,'Основной профиль');
assert.equal(JSON.stringify(aiSaved.data).includes(testApiKey),false,'AI settings never return the API key');
const projectResponse=await a.call(`/projects/${aiProject.id}`);
assert.equal(JSON.stringify(projectResponse.data).includes(testApiKey),false,'AI key is not part of Project Data/settings');
assert.equal((await a.call(`/projects/${aiProject.id}/revisions`)).data.length,0,'AI credentials are not stored in document revisions');
const dbContents=(await readFile(join(dir,'test.sqlite'))).toString('latin1');
assert.equal(dbContents.includes(testApiKey),false,'SQLite stores encrypted API key ciphertext, not plaintext');
const keyFile=await stat(join(dir,'test.sqlite.ai-key'));
assert.equal(keyFile.mode & 0o077,0,'Application encryption key file is private to the service user');
assert.equal((await a.call(aiSettingsPath,'PUT',{version:0,apiKey:'sk-stale',model:'openai/test-model'})).status,409,'AI credential settings enforce version preconditions');
aiSaved=await a.call(aiSettingsPath,'PUT',{version:1,apiKey:'',model:'openai/second-test-model'});
assert.equal(aiSaved.status,200);assert.equal(aiSaved.data.configured,true);assert.equal(aiSaved.data.model,'openai/second-test-model');assert.equal(aiSaved.data.fallbackModel,'openai/fallback-model');assert.equal(aiSaved.data.version,2,'Model changes preserve the saved key and fallback model while advancing credential version');
let profilesSaved=await a.call(aiProfilesPath,'POST',{version:2,name:'Рабочий профиль',apiKey:'sk-or-v1-second-test-secret',model:'anthropic/test-model',fallbackModel:'openai/work-fallback'});
assert.equal(profilesSaved.status,200);assert.equal(profilesSaved.data.profiles.length,2);assert.equal(profilesSaved.data.configured,true);assert.equal(profilesSaved.data.fallbackModel,'openai/work-fallback');assert.equal(profilesSaved.data.version,3);
const secondProfile=profilesSaved.data.profiles.find(profile=>profile.active);
assert.ok(secondProfile);assert.notEqual(secondProfile.id,primaryProfile.id);assert.equal(JSON.stringify(profilesSaved.data).includes('sk-or-v1-second-test-secret'),false,'AI profile listings never return saved keys');
assert.equal((await a.call(aiProfilesPath,'POST',{version:2,name:'Stale profile',apiKey:'sk-stale',model:'openai/test-model'})).status,409,'Profile creation enforces the shared version precondition');
profilesSaved=await a.call(aiProfilesPath+'/'+secondProfile.id,'PUT',{version:3,name:'Обновлённый профиль',apiKey:'',model:'anthropic/updated-model',fallbackModel:'openai/updated-fallback'});
assert.equal(profilesSaved.status,200);assert.equal(profilesSaved.data.version,4);
const editedProfile=profilesSaved.data.profiles.find(profile=>profile.id===secondProfile.id);
assert.equal(editedProfile.name,'Обновлённый профиль');assert.equal(editedProfile.model,'anthropic/updated-model');assert.equal(editedProfile.fallbackModel,'openai/updated-fallback');
profilesSaved=await a.call(aiProfilesPath+'/'+secondProfile.id,'PUT',{version:4,name:'Обновлённый профиль',apiKey:'sk-or-v1-updated-second-secret',model:'anthropic/updated-model'});
assert.equal(profilesSaved.status,200);assert.equal(profilesSaved.data.version,5);
assert.equal(JSON.stringify(profilesSaved.data).includes('sk-or-v1-updated-second-secret'),false,'Replacing a profile key never returns the new secret');
assert.equal((await a.call(aiProfilesPath+'/'+primaryProfile.id+'/activate','POST',{version:5})).data.model,'openai/second-test-model','The owner can select which profile the AI endpoint uses');
const otherAiClient=new Client();await otherAiClient.call('/session');await otherAiClient.call('/register','POST',{email:'ai-outsider@example.test',password:'another-ai-password'});
assert.equal((await otherAiClient.call(aiSettingsPath,'GET')).status,404,'AI credentials enforce project ownership');
assert.equal((await otherAiClient.call(aiSettingsPath,'PUT',{version:6,apiKey:'sk-foreign',model:'openai/test-model'})).status,404,'AI credential writes enforce project ownership');
assert.equal((await otherAiClient.call(aiGeneratePath,'POST',{prompt:'Сайт клиники'})).status,404,'Full-page AI generation enforces project ownership');
assert.equal((await otherAiClient.call(aiProfilesPath,'POST',{version:6,name:'Foreign',apiKey:'sk-foreign',model:'openai/test-model'})).status,404,'AI profile creation enforces project ownership');
assert.equal((await otherAiClient.call(aiProfilesPath+'/'+secondProfile.id,'PUT',{version:6,name:'Foreign',apiKey:'sk-foreign',model:'openai/test-model'})).status,404,'AI profile edits enforce project ownership');
profilesSaved=await a.call(aiProfilesPath+'/'+primaryProfile.id,'DELETE',{version:6});
assert.equal(profilesSaved.status,200);assert.equal(profilesSaved.data.activeProfileId,secondProfile.id);assert.equal(profilesSaved.data.configured,true);assert.equal(profilesSaved.data.version,7,'Deleting the active profile selects another saved profile');
assert.equal((await a.call(aiProfilesPath+'/'+secondProfile.id,'DELETE',{version:6})).status,409,'Profile deletion rejects a stale version');
profilesSaved=await a.call(aiProfilesPath+'/'+secondProfile.id,'DELETE',{version:7});
assert.equal(profilesSaved.status,200);assert.equal(profilesSaved.data.configured,false);assert.equal(profilesSaved.data.version,8);
aiSaved=await a.call(aiSettingsPath,'DELETE',{version:8});
assert.equal(aiSaved.data.configured,false);assert.equal(aiSaved.data.version,9,'Clearing AI profiles does not reset the shared version');
assert.equal((await a.call(aiSettingsPath,'PUT',{version:8,apiKey:testApiKey,model:'openai/test-model'})).status,409,'Clearing credentials does not reset the config version');
assert.equal((await a.call(aiPlanPath,'POST',{prompt:'Создай сайт клиники'})).status,503,'AI planning stays disabled after the project key is removed');
assert.equal((await a.call('/projects')).data.length,1,'AI planning does not create additional projects');
const operationProject=(await a.call('/projects','POST',{name:'Typed operations'})).data;
assert.equal(operationProject.version,0);
const idlessProject=(await a.call('/projects','POST',{name:'ID-less AI target'})).data,idlessDocument=structuredClone(idlessProject.document);
idlessDocument.data.pages[0].frames[0].component.components=[{tagName:'p',type:'text',content:'Текст без сохранённого ID'}];
const idlessSaved=await a.call(`/projects/${idlessProject.id}`,'PUT',{version:0,document:idlessDocument});assert.equal(idlessSaved.status,200);
const idlessEdit={prompt:'Сократи текст',pageId:'home',componentPath:[0],componentId:'',version:1};
assert.equal((await a.call(`/projects/${idlessProject.id}/ai/edit-component`,'POST',idlessEdit)).status,503,'ID-less targets resolve before AI profile availability is checked');
const idlessAfterProposal=(await a.call(`/projects/${idlessProject.id}`)).data;
assert.equal(idlessAfterProposal.version,1);assert.equal(idlessAfterProposal.document.data.pages[0].frames[0].component.components[0].attributes,undefined,'Preparing an AI edit never persists its proposed stable ID');
assert.equal((await a.call(`/projects/${operationProject.id}/operations`,'POST',{version:0,operations:[{operation:'create_component',parentId:'page:home',component:{tagName:'section',name:'Generated section',components:[{type:'text',tagName:'h2',content:'Original heading'}]}}]})).status,200,'Typed create operation saves through the authenticated project API');
let operationState=(await a.call(`/projects/${operationProject.id}`)).data;
assert.equal(operationState.version,1);
const operationSection=operationState.document.data.pages[0].frames[0].component.components[0];
const generatedHeading=operationSection.components[0];
assert.match(operationSection.attributes.id,/^cmp-[a-f0-9]{24}$/);
assert.match(generatedHeading.attributes.id,/^cmp-[a-f0-9]{24}$/);
const aiEditPath=`/projects/${operationProject.id}/ai/edit-component`,aiEditRequest={prompt:'Сделай заголовок выразительнее',pageId:'home',componentPath:[0,0],componentId:generatedHeading.attributes.id,version:1};
assert.equal((await a.call(aiEditPath,'POST',aiEditRequest,'bad')).status,403,'Targeted AI edits require a valid CSRF token');
assert.equal((await anon.call(aiEditPath,'POST',aiEditRequest)).status,401,'Targeted AI edits require an authenticated session');
await b.call('/session');
assert.equal((await otherAiClient.call(aiEditPath,'POST',aiEditRequest)).status,404,'Targeted AI edits enforce project ownership');
assert.equal((await a.call(aiEditPath,'POST',{...aiEditRequest,version:99})).status,409,'Targeted AI edits reject a stale project version before contacting a provider');
assert.equal((await a.call(aiEditPath,'POST',{...aiEditRequest,componentId:'missing-component'})).status,409,'Targeted AI edits reject a stable ID that does not match the selected component path');
assert.equal((await a.call(aiEditPath,'POST',{...aiEditRequest,componentPath:[0,99]})).status,404,'Targeted AI edits resolve only an existing component path on the selected page');
assert.equal((await a.call(aiEditPath,'POST',aiEditRequest)).status,503,'Targeted AI edits remain unavailable until a project provider profile is configured');
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
assert.equal((await anon.download(`/projects/${p.id}/export/static`)).status,401,'Static exports require an authenticated session');
const archive=await a.download(`/projects/${p.id}/export/static`);assert.equal(archive.status,200);assert.equal(archive.type,'application/zip');assert.equal(archive.body.subarray(0,2).toString(),'PK','Authenticated owner receives a ZIP archive');
await b.call('/session');await b.call('/register','POST',{email:'two@example.test',password:'another-test-password'});
assert.deepEqual((await b.call('/projects')).data,[]);assert.equal((await b.call('/projects/'+p.id)).status,404);assert.equal((await b.download(`/projects/${p.id}/export/static`)).status,404,'Static export enforces project ownership');
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

test('stale sessions from a different local database are reset instead of causing project creation errors',async()=>{
const staleDirect=new Client();await staleDirect.call('/session');const directUser=await staleDirect.call('/register','POST',{email:'stale-direct@example.test',password:'a-long-stale-password'});assert.equal(directUser.status,200);
execFileSync('php',['-r',"$db=new PDO('sqlite:'.$argv[1]);$db->prepare('DELETE FROM users WHERE id=?')->execute([$argv[2]]);",testDbPath,directUser.data.user.id]);
const rejected=await staleDirect.call('/projects','POST',{name:'Must not be created'});assert.equal(rejected.status,401);assert.equal(rejected.data.error,'Локальная сессия устарела. Войдите снова.');
const staleBootstrap=new Client();await staleBootstrap.call('/session');const bootstrapUser=await staleBootstrap.call('/register','POST',{email:'stale-bootstrap@example.test',password:'another-stale-password'});assert.equal(bootstrapUser.status,200);
execFileSync('php',['-r',"$db=new PDO('sqlite:'.$argv[1]);$db->prepare('DELETE FROM users WHERE id=?')->execute([$argv[2]]);",testDbPath,bootstrapUser.data.user.id]);
const session=await staleBootstrap.call('/session');assert.equal(session.status,200);assert.equal(session.data.user,null);assert.equal(session.data.sessionExpired,true,'bootstrap reports that the current database invalidated the old login');
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
const created=await owner.call('/projects','POST',{name:'Token workspace'}),project=created.data;assert.equal(created.status,201);assert.equal(project.settings.schemaVersion,4);assert.equal(project.settings.designTokens.fontFamily,'Arial, sans-serif');assert.deepEqual(project.settings.pages.home.seo,{title:'',description:'',robots:'index',socialImage:''});
const settingsPath=`/projects/${project.id}/settings`,changes={ink:'#112233',fontFamily:'Georgia, serif',radius:'16px'};
assert.equal((await owner.call(settingsPath,'PUT',{version:0,designTokens:changes},'bad')).status,403,'Project settings require CSRF');
assert.equal((await owner.call(settingsPath,'PUT',{version:0,designTokens:{...changes,unknown:'#ffffff'}})).status,422,'Unknown design tokens are rejected');
assert.equal((await owner.call(settingsPath,'PUT',{version:0,designTokens:{ink:'red'}})).status,422,'Unsafe token values are rejected');
assert.equal((await owner.call(settingsPath,'PUT',{version:1,designTokens:changes})).status,409,'Project settings use version preconditions');
assert.equal((await other.call(settingsPath,'PUT',{version:0,designTokens:changes})).status,404,'Project settings enforce ownership');
let saved=await owner.call(settingsPath,'PUT',{version:0,designTokens:changes});assert.equal(saved.status,200);assert.equal(saved.data.version,1);assert.equal(saved.data.settings.designTokens.ink,'#112233');assert.equal(saved.data.settings.designTokens.fontFamily,'Georgia, serif');
assert.equal(saved.data.document.data.pages[0].frames[0].component.style['--juzimder-color-ink'],'#112233');assert.equal((await owner.call(`/projects/${project.id}/revisions`)).data[0].version,1,'A settings save creates a revision');
const typography={headingFontFamily:'Georgia, serif',bodyFontFamily:'system-ui, sans-serif',headingFontWeight:'700',bodyFontWeight:'300',baseFontSize:'18px',fontSizeH1:'72px',fontSizeH2:'42px',fontSizeH3:'30px',fontSizeH4:'22px',fontSizeH5:'18px',fontSizeH6:'16px',headingLineHeight:'1.3',bodyLineHeight:'1.8',letterSpacing:'0.5px'};
saved=await owner.call(settingsPath,'PUT',{version:1,designTokens:typography});assert.equal(saved.status,200);assert.equal(saved.data.version,2);assert.equal(saved.data.settings.designTokens.headingFontFamily,'Georgia, serif');assert.equal(saved.data.document.data.pages[0].frames[0].component.style['--juzimder-font-size-h1'],'clamp(28px, calc(18px + 2.35vw), 72px)','Typography update writes fluid CSS variables');
let page=await owner.call(`/projects/${project.id}/pages`,'POST',{version:2,action:'create',name:'About'});assert.equal(page.status,200);assert.equal(page.data.document.data.pages[1].frames[0].component.style['--juzimder-font-family'],'system-ui, sans-serif','New pages inherit persisted global tokens');
let restored=await owner.call(`/projects/${project.id}/revisions/restore`,'POST',{currentVersion:3,targetVersion:2});assert.equal(restored.status,200);assert.equal(restored.data.version,4);assert.equal(restored.data.settings.designTokens.ink,'#112233','Design tokens restore with their matching revision');
const nextTokens={...restored.data.settings.designTokens,accent:'#8899aa'};
saved=await owner.call(`/projects/${project.id}`,'PUT',{version:4,document:restored.data.document,settings:{designTokens:nextTokens}});assert.equal(saved.status,200);assert.equal(saved.data.settings.designTokens.accent,'#8899aa');assert.equal(Object.keys(saved.data.settings.pages).length,1,'Editor settings cannot replace route metadata');
assert.equal((await owner.call(`/projects/${project.id}`,'PUT',{version:5,document:saved.data.document,settings:{pages:{}}})).status,422,'Editor settings accept only design tokens');
const pageId=saved.data.document.data.pages[0].id,seo={title:'Home title',description:'A useful page description',robots:'noindex',socialImage:'https://example.test/social.jpg'};
assert.equal((await owner.call(settingsPath,'PUT',{version:5,seo:{pageId,robots:'follow'}})).status,422,'SEO robots values are validated');
assert.equal((await owner.call(settingsPath,'PUT',{version:5,seo:{pageId,socialImage:'http://example.test/social.jpg'}})).status,422,'SEO images require HTTPS');
assert.equal((await owner.call(settingsPath,'PUT',{version:5,seo:{pageId:'unknown-page',...seo}})).status,422,'SEO metadata can only target a page in this project');
saved=await owner.call(settingsPath,'PUT',{version:5,seo:{pageId,...seo}});assert.equal(saved.status,200);assert.equal(saved.data.version,6);assert.deepEqual(saved.data.settings.pages[pageId].seo,seo,'Per-page SEO metadata is saved by the backend');
assert.equal((await other.call(settingsPath,'PUT',{version:6,seo:{pageId,...seo}})).status,404,'SEO settings enforce project ownership');
saved=await owner.call(settingsPath,'PUT',{version:6,seo:{pageId,title:'Changed title'}});assert.equal(saved.status,200);assert.equal(saved.data.settings.pages[pageId].seo.title,'Changed title');assert.equal(saved.data.settings.pages[pageId].seo.description,seo.description,'Partial SEO updates preserve omitted metadata');
const seoRestored=await owner.call(`/projects/${project.id}/revisions/restore`,'POST',{currentVersion:7,targetVersion:6});assert.equal(seoRestored.status,200);assert.equal(seoRestored.data.settings.pages[pageId].seo.title,seo.title);assert.equal(seoRestored.data.settings.pages[pageId].seo.robots,'noindex','SEO metadata restores with its matching revision');
});


test('Managed project images are private, validated and included in export and immutable publication',async()=>{
const owner=new Client(),outsider=new Client(),anonymous=new Client();await owner.call('/session');await owner.call('/register','POST',{email:'asset-owner@example.test',password:'a-long-asset-password'});await outsider.call('/session');await outsider.call('/register','POST',{email:'asset-outsider@example.test',password:'another-asset-password'});
const project=(await owner.call('/projects','POST',{name:'Asset workspace'})).data,assetsPath=`/projects/${project.id}/assets`,png=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j6J8AAAAASUVORK5CYII=','base64');
assert.equal((await anonymous.call(assetsPath)).status,401,'Asset listing requires an authenticated session');assert.equal((await outsider.call(assetsPath)).status,404,'Asset listing enforces project ownership');
assert.equal((await owner.upload(assetsPath,Buffer.from('<svg xmlns="http://www.w3.org/2000/svg"></svg>'),'unsafe.svg','bad')).status,403,'Asset upload requires CSRF');
assert.equal((await owner.upload(assetsPath,Buffer.from('<svg xmlns="http://www.w3.org/2000/svg"></svg>'),'unsafe.svg')).status,422,'SVG and non-raster payloads are rejected');
const uploaded=await owner.upload(assetsPath,png,'микро.png');assert.equal(uploaded.status,201);assert.equal(uploaded.data.mime,'image/png');assert.equal(uploaded.data.width,1);assert.equal(uploaded.data.height,1);assert.match(uploaded.data.url,new RegExp(`^/api/projects/${project.id}/assets/[a-f0-9]{24}/file$`));
const listed=await owner.call(assetsPath);assert.equal(listed.status,200);assert.equal(listed.data.length,1);assert.equal(listed.data[0].name,'микро.png');
const file=await owner.download(`/projects/${project.id}/assets/${uploaded.data.id}/file`);assert.equal(file.status,200);assert.match(file.type,/image\/png/);assert.deepEqual(file.body,png,'Authorized media delivery preserves the exact uploaded bytes');
assert.equal((await outsider.download(`/projects/${project.id}/assets/${uploaded.data.id}/file`)).status,404,'Asset byte delivery enforces project ownership');
const state=(await owner.call(`/projects/${project.id}`)).data;state.document.data.pages[0].frames[0].component.components=[{type:'image',tagName:'img',attributes:{src:uploaded.data.url,alt:'Тестовое изображение'}}];
const saved=await owner.call(`/projects/${project.id}`,'PUT',{version:0,document:state.document});assert.equal(saved.status,200);assert.equal(saved.data.document.data.pages[0].frames[0].component.components[0].attributes.src,uploaded.data.url,'Project Data stores the scoped managed-asset reference');
const foreignDoc=structuredClone(saved.data.document);foreignDoc.data.pages[0].frames[0].component.components[0].attributes.src=`/api/projects/${'a'.repeat(24)}/assets/${uploaded.data.id}/file`;assert.equal((await owner.call(`/projects/${project.id}`,'PUT',{version:1,document:foreignDoc})).status,422,'Project Data cannot reference an asset from another project');
const preview=await owner.call(`/projects/${project.id}/preview/static`);assert.equal(preview.status,200);assert.match(preview.data.pages[0].html,/src="data:image\/png;base64,/,'Saved candidate preview embeds private asset bytes without exposing an unauthenticated path');
const archive=await owner.download(`/projects/${project.id}/export/static`);assert.equal(archive.status,200);const archivePath=join(dir,'asset-site.zip');await writeFile(archivePath,archive.body);const exportedImage=execFileSync('php',['-r','$zip=new ZipArchive();if($zip->open($argv[1])!==true)exit(2);$contents=$zip->getFromName($argv[2]);$zip->close();echo $contents;',archivePath,`media/${project.id}/${uploaded.data.id}.png`]);assert.deepEqual(exportedImage,png,'Static ZIP includes the referenced image bytes at its managed media path');
const published=await owner.call(`/projects/${project.id}/publish`,'POST',{version:1});assert.equal(published.status,200);assert.match((await publicCall(project.id)).body,new RegExp(`media/${project.id}/${uploaded.data.id}\.png`));
const publicImage=await publicBinaryCall(project.id,`/media/${project.id}/${uploaded.data.id}.png`);assert.equal(publicImage.status,200);assert.match(publicImage.headers['content-type'],/image\/png/);assert.deepEqual(publicImage.body,png,'Public origin serves only the image bytes captured in the active publication snapshot');
assert.equal((await publicBinaryCall(project.id,`/media/${'a'.repeat(24)}/${uploaded.data.id}.png`)).status,404,'Public origin cannot cross project asset namespaces');
});

test('Publications use owner-checked immutable snapshots and a session-free public origin',async()=>{
const owner=new Client(),other=new Client(),anonymous=new Client();
await owner.call('/session');await owner.call('/register','POST',{email:'publisher@example.test',password:'a-long-publisher-password'});
await other.call('/session');await other.call('/register','POST',{email:'publication-outsider@example.test',password:'another-publication-password'});
const project=(await owner.call('/projects','POST',{name:'Publication lifecycle'})).data,publicationPath=`/projects/${project.id}/publication`,previewPath=`/projects/${project.id}/preview/static`;
assert.equal((await anonymous.call(publicationPath)).status,401,'Publication management requires an authenticated session');
assert.equal((await owner.call(publicationPath)).status,200);assert.equal((await owner.call(publicationPath)).data.publication,null);
assert.equal((await other.call(publicationPath)).status,404,'Publication metadata follows project ownership');
assert.equal((await anonymous.call(previewPath)).status,401,'Candidate previews require an authenticated session');
assert.equal((await other.call(previewPath)).status,404,'Candidate previews enforce project ownership');
assert.equal((await owner.call(`/projects/${project.id}/publish`,'POST',{version:0},'bad')).status,403,'Publishing requires CSRF');
assert.equal((await owner.call(`/projects/${project.id}/publish`,'POST',{version:1})).status,409,'Publishing rejects a stale source revision');
let page=await owner.call(`/projects/${project.id}/pages`,'POST',{version:0,action:'create',name:'О нас'});assert.equal(page.status,200);
page.data.document.data.pages[0].frames[0].component.components=[{type:'text',tagName:'h1',content:'Published revision one'}];
page.data.document.data.pages[1].frames[0].component.components=[{type:'text',tagName:'h1',content:'About revision one'}];
let saved=await owner.call(`/projects/${project.id}`,'PUT',{version:1,document:page.data.document,settings:{designTokens:page.data.settings.designTokens}});assert.equal(saved.status,200);assert.equal(saved.data.version,2);
const revisionsBeforePreview=(await owner.call(`/projects/${project.id}/revisions`)).data;
let preview=await owner.call(previewPath);assert.equal(preview.status,200);assert.equal(preview.data.version,2);assert.deepEqual(preview.data.pages.map(item=>item.path),['/','/o-nas/']);assert.match(preview.data.pages[0].html,/Published revision one/);assert.match(preview.data.pages[1].html,/About revision one/);assert.equal(typeof preview.data.css,'string');
assert.equal((await owner.call(`/projects/${project.id}`)).data.version,2,'Previewing does not create a project revision');assert.deepEqual((await owner.call(`/projects/${project.id}/revisions`)).data,revisionsBeforePreview,'Previewing does not change revision history');assert.equal((await owner.call(publicationPath)).data.publication,null,'Previewing does not publish the candidate');
let result=await owner.call(`/projects/${project.id}/publish`,'POST',{version:2});assert.equal(result.status,200);assert.equal(result.data.publication.version,1);assert.equal(result.data.publication.sourceProjectVersion,2);
assert.equal(result.data.publicUrl,`http://${project.id}.juzimder-ai.localhost:18081/`,'The returned public URL uses the configured separate host');
let publicResponse=await publicCall(project.id);assert.equal(publicResponse.status,200);assert.match(publicResponse.body,/Published revision one/);assert.match(publicResponse.headers['content-security-policy'],/script-src 'none'/);assert.equal(publicResponse.headers['x-frame-options'],'DENY');assert.equal(publicResponse.headers['set-cookie'],undefined,'Public requests do not create an admin session');
let about=await publicCall(project.id,'/o-nas/');assert.equal(about.status,200);assert.match(about.body,/About revision one/);
let stylesheet=await publicCall(project.id,'/assets/site.css');assert.equal(stylesheet.status,200);assert.match(stylesheet.headers['content-type'],/text\/css/);
let redirect=await publicCall(project.id,'/o-nas');assert.equal(redirect.status,308);assert.equal(redirect.headers.location,`${result.data.publicUrl}o-nas/`);
assert.equal((await publicCall(project.id,'/README.txt')).status,404,'Only rendered site routes and shared CSS are public');
assert.equal((await publicCall(project.id,'/api/session')).status,404,'The public origin does not expose the editor API');
assert.equal((await publicCall(project.id,'/','POST')).status,405,'The public origin is read-only');
assert.equal((await other.call(`/projects/${project.id}/publish`,'POST',{version:2})).status,404,'Only the project owner can publish');
assert.equal((await owner.call(`/projects/${project.id}/unpublish`,'POST',{version:1})).status,409,'Unpublishing also requires the current source revision');
saved=await owner.call(`/projects/${project.id}`,'PUT',{version:2,document:saved.data.document,settings:{designTokens:saved.data.settings.designTokens}});assert.equal(saved.status,200);assert.equal(saved.data.version,3);
saved.data.document.data.pages[0].frames[0].component.components[0].content='Published revision two';
saved=await owner.call(`/projects/${project.id}`,'PUT',{version:3,document:saved.data.document,settings:{designTokens:saved.data.settings.designTokens}});assert.equal(saved.status,200);assert.equal(saved.data.version,4);
preview=await owner.call(previewPath);assert.match(preview.data.pages[0].html,/Published revision two/,'Candidate preview renders the latest saved source revision');assert.match((await publicCall(project.id)).body,/Published revision one/,'Previewing a newer candidate leaves the active publication unchanged');
assert.match((await publicCall(project.id)).body,/Published revision one/,'Editing the source does not mutate an immutable public snapshot');
result=await owner.call(`/projects/${project.id}/publish`,'POST',{version:4});assert.equal(result.data.publication.version,2);assert.equal(result.data.publication.sourceProjectVersion,4);
assert.match((await publicCall(project.id)).body,/Published revision two/,'Republishing activates a new immutable snapshot');
result=await owner.call(`/projects/${project.id}/unpublish`,'POST',{version:4});assert.equal(result.status,200);assert.equal(result.data.publication,null);
assert.equal((await publicCall(project.id)).status,404,'Unpublishing immediately removes the public route');
assert.equal((await owner.call(publicationPath)).data.publicUrl,null);
await structuredPlanLifecycle(owner);
});

async function structuredPlanLifecycle(owner){
const created=await owner.call('/projects','POST',{name:'Structured plan lifecycle'});
assert.equal(created.status,201,JSON.stringify(created.data));
const project=created.data;
const plan={
siteType:'Сайт студии',audience:'Малый бизнес',goal:'Получать заявки',style:'Спокойный современный стиль',
colors:{ink:'#112233',forest:'#224466',canvas:'#eaf0f5',paper:'#ffffff',body:'#334455',muted:'#556677',line:'#ccddee',accent:'#88bb99'},
sections:['navigation','hero','features','footer'],responsiveNotes:'Карточки складываются в одну колонку на узком экране.',
content:['navigation','hero','features','footer'].map(section=>({section,texts:AI_SECTION_TEXT_FIELDS[section].map((_,index)=>`${section} copy ${index+1}`)}))
};
const operations=buildDesignPlanOperations(plan,blocks,'home');
const applied=await owner.call(`/projects/${project.id}/operations`,'POST',{version:0,operations});
assert.equal(applied.status,200,`The validated structured plan becomes server-authoritative typed operations: ${JSON.stringify(applied.data)}`);
let state=(await owner.call(`/projects/${project.id}`)).data;
assert.equal(state.version,1);assert.equal(state.document.data.pages[0].frames[0].component.components.length,4);
const findHeading=node=>{
if(node?.tagName==='h1')return node;
for(const child of node?.components||[]){const match=findHeading(child);if(match)return match;}
return null;
};
const heading=findHeading(state.document.data.pages[0].frames[0].component);
assert.ok(heading?.attributes?.id,'Typed operations assign stable IDs used by later edits');
assert.equal(heading.content,'hero copy 2','AI-generated plain text is saved in an editable heading component');
const originalDocument=structuredClone(state.document);
const edited=await owner.call(`/projects/${project.id}/operations`,'POST',{version:1,operations:[{operation:'update_content',componentId:heading.attributes.id,content:'Ручная правка до восстановления'}]});
assert.equal(edited.status,200);assert.equal(edited.data.version,2,'A local text edit creates its own revision');
const restored=await owner.call(`/projects/${project.id}/revisions/restore`,'POST',{currentVersion:2,targetVersion:1});
assert.equal(restored.status,200);assert.equal(restored.data.version,3);assert.deepEqual(restored.data.document,originalDocument,'Restore creates a new revision with the earlier complete document');
const revisions=(await owner.call(`/projects/${project.id}/revisions`)).data;
assert.deepEqual(revisions.map(item=>item.version),[3,2,1],'Restore preserves both the edited and original history entries');
const preview=await owner.call(`/projects/${project.id}/preview/static`);
assert.equal(preview.status,200);assert.equal(preview.data.version,3);assert.match(preview.data.pages[0].html,/hero copy 2/);assert.doesNotMatch(preview.data.pages[0].html,/Ручная правка до восстановления/);
const archive=await owner.download(`/projects/${project.id}/export/static`);
assert.equal(archive.status,200);assert.equal(archive.body.subarray(0,2).toString(),'PK','The restored candidate exports as a static ZIP');
const archivePath=join(dir,'structured-plan.zip');await writeFile(archivePath,archive.body);
const exportedIndex=execFileSync('php',['-r','$zip=new ZipArchive();if($zip->open($argv[1])!==true)exit(2);$contents=$zip->getFromName("index.html");$zip->close();echo $contents;',archivePath]).toString('utf8');
assert.match(exportedIndex,/hero copy 2/);assert.doesNotMatch(exportedIndex,/Ручная правка до восстановления/,'ZIP contains the restored source revision');
const published=await owner.call(`/projects/${project.id}/publish`,'POST',{version:3});
assert.equal(published.status,200);assert.equal(published.data.publication.sourceProjectVersion,3);
const publicPage=await publicCall(project.id);
assert.equal(publicPage.status,200);assert.match(publicPage.body,/hero copy 2/);assert.doesNotMatch(publicPage.body,/Ручная правка до восстановления/);
assert.equal((await owner.call(`/projects/${project.id}`)).data.version,3,'Preview, export and publish do not mutate Project Data');
}
