import 'grapesjs/dist/css/grapes.min.css';
import './style.css';
import { blocks, registerBlocks } from './blocks.js';
const $ = (selector) => document.querySelector(selector);
const app = $('#app');
const esc = (s) => String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const mark = '<span class="brand-mark"><i></i><i></i><i></i><i></i></span>';
let session, editor, current, dirty=false, loading=false, saving=false, saveAgain=false, conflicted=false, saveTimer, savePromise;
async function api(path, method='GET',body) {
  const response = await fetch(`/api${path}`,{method,headers:{'Content-Type':'application/json',...(session?{'X-CSRF-Token':session.csrf}:{})},...(body?{body:JSON.stringify(body)}:{})});
  const data = await response.json(); if (!response.ok) { const error=new Error(data.error || `HTTP ${response.status}`);error.status=response.status;throw error; } return data;
}
function notify(message,error=false) { const el=$('#notice'); if(el){el.textContent=message;el.classList.toggle('error',error);} }
function download(name,content,type='application/json') { const url=URL.createObjectURL(new Blob([content],{type}));const a=document.createElement('a');a.href=url;a.download=name;a.click();setTimeout(()=>URL.revokeObjectURL(url),1000); }
function askConfirmation(message,confirmLabel='Продолжить') {
  const dialog=$('#confirm-dialog');dialog.returnValue='';$('#confirm-message').textContent=message;$('#confirm-submit').textContent=confirmLabel;
  return new Promise(resolve=>{dialog.addEventListener('close',()=>resolve(dialog.returnValue==='confirm'),{once:true});dialog.showModal();});
}
function scheduleAutosave(delay=1400) { clearTimeout(saveTimer);saveTimer=setTimeout(()=>{saveTimer=undefined;saveDocument(true);},delay); }
async function saveDocument(automatic=false) {
  clearTimeout(saveTimer);saveTimer=undefined;
  if(!dirty||conflicted)return;
  if(saving){saveAgain=true;if(!automatic){const pending=savePromise;await pending;if(dirty&&!conflicted)return saveDocument(false);}return;}
  saving=true;const button=$('#save');if(button)button.disabled=true;
  const data=editor.getProjectData(),snapshot=JSON.stringify(data),version=current.version;
  let retryDelay=null;
  $('#save-status').textContent=automatic?'Автосохранение…':'Сохранение…';
  const operation=(async()=>{
    try {
      current=await api(`/projects/${current.id}`,'PUT',{version,document:{schemaVersion:1,engine:'grapesjs',data}});
      dirty=JSON.stringify(editor.getProjectData())!==snapshot;
      $('#save-status').textContent=dirty?'Изменения продолжаются':`${automatic?'Автосохранено':'Сохранено'} · ревизия ${current.version}`;
      notify('');
    } catch(error) {
      if(error.status===409){conflicted=true;$('#save-status').textContent='Версия изменилась в другой вкладке';$('#reload-server').hidden=false;notify('Экспортируйте локальные изменения или загрузите серверную версию.',true);}
      else {const retryable=!error.status||error.status>=500;$('#save-status').textContent=retryable?'Не удалось сохранить · повторим автоматически':'Не удалось сохранить · проверьте изменения';notify(error.message,true);if(retryable)retryDelay=5000;}
    } finally {
      saving=false;if(button)button.disabled=conflicted;
      const again=saveAgain;saveAgain=false;
      if(again&&dirty&&!conflicted)await saveDocument(true);
      else if(dirty&&!conflicted&&!saveTimer&&retryDelay!==null)scheduleAutosave(retryDelay);
    }
  })();
  savePromise=operation;return operation;
}
function auth(register=false) {
  app.innerHTML=`<main class="auth"><div class="auth-story"><a class="brand" href="/">${mark} Juzimder <span>AI</span></a><div><p class="eyebrow">ВАШ СЛЕДУЮЩИЙ ПРОЕКТ НАЧИНАЕТСЯ ЗДЕСЬ</p><h1>Из идеи —<br>в живой сайт.</h1><p class="intro">Пространство, где дизайн складывается.<br>Одна секция, одна страница, одна идея за раз.</p></div><p class="muted">На базе GrapesJS Core · Открытая платформа</p></div><section class="auth-form"><p class="eyebrow">JUZIMDER WORKSPACE / 01</p><h2>${register?'Создайте своё пространство':'С возвращением'}</h2><p class="muted">${register?'Начните с первой страницы.':'Войдите, чтобы продолжить работу над сайтом.'}</p><form id="auth-form"><label>Email<input name="email" type="email" autocomplete="email" required placeholder="you@studio.kz"></label><label>Пароль<input name="password" type="password" autocomplete="${register?'new-password':'current-password'}" required minlength="12" maxlength="72" placeholder="Не менее 12 символов"></label><button class="primary" type="submit">${register?'Создать аккаунт':'Войти в пространство'} <span>→</span></button><p id="notice" role="status"></p></form><button class="text-button" id="toggle-auth">${register?'Уже есть аккаунт? Войти':'Первый раз здесь? Создать аккаунт'}</button><p class="auth-foot">Ранняя версия · Визуальное редактирование и сохранение</p></section></main>`;
  $('#toggle-auth').onclick=()=>auth(!register);
  $('#auth-form').onsubmit=async e=>{e.preventDefault();const b=e.target.querySelector('button');b.disabled=true;try{session=await api(register?'/register':'/login','POST',Object.fromEntries(new FormData(e.target)));await dashboard();}catch(error){notify(error.message,true);}finally{b.disabled=false;}};
}
async function dashboard() {
  const projects=await api('/projects');
  app.innerHTML=`<div class="dashboard"><aside class="dash-sidebar"><a class="brand" href="/">${mark} Juzimder <span>AI</span></a><div class="workspace-label">ЛИЧНОЕ ПРОСТРАНСТВО</div><div class="nav-active">▦ <span>Мои проекты</span><small>${projects.length}</small></div><div class="sidebar-note"><span class="status-dot"></span> Foundation 0.1<br><p>От первой идеи<br>до первой страницы.</p></div><button id="logout" class="text-button">Выйти из аккаунта ↗</button></aside><main class="dash-main"><header class="dash-top"><span>Workspace <span class="slash">/</span> Мои проекты</span><span>${esc(session.user.email)}</span></header><section class="project-intro"><p class="eyebrow">СОЗДАВАЙТЕ В СВОЁМ РИТМЕ</p><div class="title-row"><div><h1>Мои проекты<span class="accent">.</span></h1><p class="muted">Хорошие сайты начинаются с чистого листа.</p></div><button id="new-project" class="primary">+ Новый проект</button></div></section><section class="project-list">${projects.map(p=>`<button class="project-card" data-project="${p.id}"><div class="project-art"><div class="mini-window"><div></div><i></i><i></i><span></span></div><span class="project-arrow">↗</span></div><div class="project-info"><h3>${esc(p.name)}</h3><p>${p.version?'Ревизия '+p.version:'Новый проект'} <span>Открыть редактор →</span></p></div></button>`).join('')}<button class="project-card new-card" id="empty-create"><span class="plus">+</span><strong>Новая идея</strong><span>Создайте сайт с нуля</span></button></section><p id="notice" role="status"></p><footer class="dash-footer"><span>YOUR IDEAS, TAKING SHAPE.</span><span>GrapesJS Core · PHP · SQLite</span></footer></main></div><dialog id="create-dialog"><form id="create-form"><p class="eyebrow">НОВОЕ ПРОСТРАНСТВО</p><h2>Как назовём проект?</h2><label>Название проекта<input name="name" required maxlength="100" placeholder="Например, Сайт студии"></label><p class="muted">Начнём с пустой главной страницы.</p><p id="create-error" role="alert"></p><div class="dialog-actions"><button type="button" id="cancel-create">Отмена</button><button class="primary">Создать проект →</button></div></form></dialog>`;
  const open=()=>$('#create-dialog').showModal(); $('#new-project').onclick=open;$('#empty-create').onclick=open;$('#cancel-create').onclick=()=>$('#create-dialog').close();
  $('#create-form').onsubmit=async e=>{e.preventDefault();const b=e.target.querySelector('.primary');b.disabled=true;try{const project=await api('/projects','POST',{name:new FormData(e.target).get('name')});$('#create-dialog').close();await openEditor(project.id);}catch(error){$('#create-error').textContent=error.message;b.disabled=false;}};
  for(const btn of document.querySelectorAll('[data-project]')) btn.onclick=()=>openEditor(btn.dataset.project).catch(e=>notify(e.message,true));
  $('#logout').onclick=async()=>{await api('/logout','POST',{});session=await api('/session');auth();};
}
async function openEditor(id) {
  current=await api(`/projects/${id}`);dirty=false; loading=true;
  const { default: grapesjs } = await import('grapesjs');
  saving=false;saveAgain=false;conflicted=false;savePromise=undefined;clearTimeout(saveTimer);saveTimer=undefined;
  app.innerHTML=`<main class="editor-shell"><header class="editor-top"><button id="back" class="brand brand-compact" aria-label="К проектам">${mark}</button><span class="project-name">${esc(current.name)}</span><span class="divider"></span><select id="pages" aria-label="Страница"></select><button id="add-page" class="icon-button" title="Добавить страницу">+</button><div class="device-switch" role="group" aria-label="Размер экрана"><button data-device="Desktop" class="active">Desktop</button><button data-device="Tablet">Tablet</button><button data-device="Mobile">Mobile</button></div><div class="top-actions"><button id="undo" title="Отменить">↶</button><button id="redo" title="Повторить">↷</button><button id="history">История</button><button id="export">Экспорт JSON</button><button id="save" class="primary">Сохранить</button></div></header><aside class="editor-left"><div class="panel-tabs"><button data-panel="library" class="active">Секции</button><button data-panel="layers">Слои</button></div><section id="library"><div class="panel-heading"><h2>Библиотека</h2><p>Добавьте секцию на страницу</p></div><div class="block-list">${blocks.map((b,i)=>`<button data-block="${b.id}" class="block-card"><div class="block-thumbnail thumb-${i}"><div></div><i></i><span></span></div><strong>${b.label}</strong><small>${b.description}</small><b>+</b></button>`).join('')}</div><div class="library-foot">Структурные компоненты<br>Каждый элемент можно изменить</div></section><section id="layers" hidden><div id="layer-manager"></div></section></aside><section class="editor-center"><div class="canvas-toolbar"><span id="page-label">ГЛАВНАЯ</span><span id="canvas-size">Адаптивный холст</span><button id="preview">Предпросмотр ↗</button></div><div class="canvas-wrap"><div id="gjs"></div><div class="canvas-empty" id="canvas-empty"><div class="empty-shapes"><i></i><i></i><i></i></div><p>Здесь начинается ваш сайт</p><span>Добавьте первый экран из библиотеки слева</span><button id="start-hero">+ Добавить Hero</button></div></div></section><aside class="editor-right"><div class="panel-heading"><p class="eyebrow">ИНСПЕКТОР</p><h2 id="selected-name">Выберите элемент</h2></div><div class="inspector-hint" id="selection-hint">Нажмите на элемент в холсте, чтобы изменить его оформление.</div><section id="quick-edit" hidden><label>Текст<textarea id="component-text" rows="3"></textarea></label><button id="apply-text">Применить текст</button><label>Режим компоновки<select id="layout"><option value="block">Обычный поток</option><option value="flex">Flex</option><option value="grid">Grid</option></select></label><p class="muted small">Меняется только display. Остальные настройки сохраняются; доступна отмена.</p></section><div id="traits"></div><div id="styles"></div><section class="ai-later"><span class="eyebrow">СЛЕДУЮЩИЙ ЭТАП</span><h3>От слов — к дизайну</h3><p>AI-генерация появится после стабилизации редактора.</p></section></aside><footer class="editor-status"><span><i class="status-dot"></i> <span id="save-status">Ревизия ${current.version} · Сохранено на сервере</span></span><span id="notice" role="status"></span><span class="status-actions"><button id="reload-server" hidden>Загрузить версию сервера</button><span>JUZIMDER / CORE EDITOR</span></span></footer></main><dialog id="confirm-dialog"><form method="dialog"><p id="confirm-message"></p><div class="dialog-actions"><button value="cancel">Отмена</button><button id="confirm-submit" class="primary" value="confirm">Продолжить</button></div></form></dialog><dialog id="history-dialog"><div class="history-head"><div><p class="eyebrow">СОХРАНЁННЫЕ СОСТОЯНИЯ</p><h2>История проекта</h2><p class="muted">Восстановление создаст новую ревизию. Текущая история останется доступной.</p></div><button id="close-history" aria-label="Закрыть">×</button></div><div id="history-list" class="history-list"></div><p id="history-error" role="status"></p></dialog><dialog id="image-dialog"><form id="image-form"><h2>Добавить изображение</h2><label>HTTPS-адрес изображения<input name="src" type="url" pattern="https://.*" required placeholder="https://example.com/image.jpg"></label><label>Описание<input name="alt" maxlength="200"></label><div class="dialog-actions"><button type="button" id="cancel-image">Отмена</button><button class="primary">Добавить</button></div></form></dialog><dialog id="page-dialog"><form id="page-form"><h2>Новая страница</h2><label>Название<input name="name" required maxlength="80"></label><div class="dialog-actions"><button type="button" id="cancel-page">Отмена</button><button class="primary">Добавить</button></div></form></dialog><dialog id="preview-dialog"><div class="preview-head"><strong>Предпросмотр страницы</strong><button id="close-preview">Закрыть</button></div><iframe id="preview-frame" sandbox="" title="Предпросмотр сайта"></iframe></dialog>`;
  editor=grapesjs.init({container:'#gjs',height:'100%',width:'auto',storageManager:false,noticeOnUnload:false,panels:{defaults:[]},allowScripts:false,canvas:{scripts:[],styles:[]},layerManager:{appendTo:'#layer-manager'},traitManager:{appendTo:'#traits'},selectorManager:{componentFirst:true},styleManager:{appendTo:'#styles',sectors:[{name:'Компоновка',open:true,properties:['display','flex-direction','justify-content','align-items','flex-wrap','gap','grid-template-columns']},{name:'Размеры и отступы',open:false,properties:['width','max-width','min-height','padding','margin']},{name:'Типографика',open:false,properties:['font-family','font-size','font-weight','line-height','color','text-align']},{name:'Оформление',open:false,properties:['background-color','border','border-radius','opacity']}]},deviceManager:{devices:[{id:'Desktop',name:'Desktop',width:''},{id:'Tablet',name:'Tablet',width:'768px',widthMedia:'992px'},{id:'Mobile',name:'Mobile',width:'375px',widthMedia:'576px'}]}});
  editor.Components.addType('image',{model:{defaults:{traits:['alt','src']}}});
  registerBlocks(editor);
  const refreshEmpty=()=>{const page=editor.Pages.getSelected();$('#canvas-empty').hidden=!!page?.getMainComponent()?.components().length;};
  const refreshPages=()=>{const selected=editor.Pages.getSelected();$('#pages').innerHTML=editor.Pages.getAll().map(p=>`<option value="${esc(p.id)}" ${p===selected?'selected':''}>${esc(p.get('name')||p.id)}</option>`).join('');$('#page-label').textContent=selected?.get('name')||'Страница';refreshEmpty();};
  editor.on('load',()=>{editor.loadProjectData(current.document.data);loading=false;editor.clearDirtyCount();refreshPages();});
  editor.on('update',()=>{
    if(!loading){dirty=JSON.stringify(editor.getProjectData())!==JSON.stringify(current.document.data);if(dirty){$('#save-status').textContent=conflicted?'Версия изменилась в другой вкладке':'Есть несохранённые изменения';if(!conflicted)scheduleAutosave();}}
    refreshEmpty();
  });
  editor.on('page:select page:add page:remove',refreshPages);
  editor.on('component:selected',component=>{ $('#selected-name').textContent=component.getName();$('#selection-hint').hidden=true;$('#quick-edit').hidden=false; const isText=['text','link','textnode'].includes(component.get('type'));$('#component-text').disabled=!isText;$('#apply-text').disabled=!isText;$('#component-text').value=isText?(component.getEl()?.textContent||component.get('content')||''):'';$('#layout').value=component.getStyle().display||'block'; });
  const addBlock=id=>{if(id==='image'){$('#image-dialog').showModal();return;}const block=blocks.find(b=>b.id===id);const added=editor.addComponents(block.content());editor.select(added[0]);refreshEmpty();};
  for(const b of document.querySelectorAll('[data-block]'))b.onclick=()=>addBlock(b.dataset.block);
  $('#cancel-image').onclick=()=>$('#image-dialog').close();
  $('#image-form').onsubmit=e=>{e.preventDefault();const form=new FormData(e.target);const node=blocks.find(b=>b.id==='image').content();node.src=form.get('src');node.attributes={alt:form.get('alt')};const added=editor.addComponents(node);editor.select(added[0]);$('#image-dialog').close();e.target.reset();};
  $('#start-hero').onclick=()=>addBlock('hero');
  for(const b of document.querySelectorAll('[data-panel]'))b.onclick=()=>{for(const btn of document.querySelectorAll('[data-panel]'))btn.classList.toggle('active',btn===b);$('#library').hidden=b.dataset.panel!=='library';$('#layers').hidden=b.dataset.panel!=='layers';};
  for(const b of document.querySelectorAll('[data-device]'))b.onclick=()=>{editor.setDevice(b.dataset.device);for(const btn of document.querySelectorAll('[data-device]'))btn.classList.toggle('active',btn===b);$('#canvas-size').textContent=b.dataset.device==='Mobile'?'375 px':b.dataset.device==='Tablet'?'768 px':'Адаптивный холст';};
  $('#pages').onchange=e=>editor.Pages.select(e.target.value);
  $('#add-page').onclick=()=>$('#page-dialog').showModal();$('#cancel-page').onclick=()=>$('#page-dialog').close();
  $('#page-form').onsubmit=e=>{e.preventDefault();const page=editor.Pages.add({id:crypto.randomUUID(),name:new FormData(e.target).get('name'),component:{type:'wrapper',components:[]}});editor.Pages.select(page);$('#page-dialog').close();e.target.reset();};
  $('#undo').onclick=()=>editor.UndoManager.undo();$('#redo').onclick=()=>editor.UndoManager.redo();
  $('#layout').onchange=e=>editor.getSelected()?.addStyle({display:e.target.value});
  $('#apply-text').onclick=()=>{const selected=editor.getSelected();if(selected){selected.components(esc($('#component-text').value));notify('Текст изменён');}};
  $('#save').onclick=()=>saveDocument(false);
  $('#reload-server').onclick=async()=>{
    if(!await askConfirmation('Локальные несохранённые изменения будут заменены серверной версией. Сначала можно скачать их через «Экспорт JSON». Продолжить?','Загрузить версию'))return;
    try{const latest=await api(`/projects/${current.id}`);current=latest;loading=true;editor.loadProjectData(latest.document.data);editor.clearDirtyCount();dirty=false;loading=false;conflicted=false;$('#reload-server').hidden=true;$('#save').disabled=false;refreshPages();$('#save-status').textContent=`Ревизия ${current.version} · Загружено с сервера`;notify('Серверная версия загружена');}
    catch(error){loading=false;notify(error.message,true);}
  };
  const renderHistory=async()=>{
    $('#history-dialog').showModal();$('#history-list').textContent='Загрузка истории…';$('#history-error').textContent='';
    try {
      const revisions=await api(`/projects/${current.id}/revisions`);const list=$('#history-list');list.replaceChildren();
      if(!revisions.length){list.textContent='Сохранённых ревизий пока нет.';return;}
      for(const revision of revisions){
        const row=document.createElement('div');row.className='history-row';
        const details=document.createElement('div');const title=document.createElement('strong');title.textContent=`Ревизия ${revision.version}${revision.version===current.version?' · текущая':''}`;const date=document.createElement('time');date.textContent=new Date(`${revision.created_at.replace(' ','T')}Z`).toLocaleString('ru-KZ',{dateStyle:'medium',timeStyle:'short'});details.append(title,date);row.append(details);
        if(revision.version!==current.version){const restore=document.createElement('button');restore.className='restore-revision';restore.textContent='Восстановить';restore.onclick=async()=>{
          if(!await askConfirmation(`Восстановить ревизию ${revision.version}? Это действие создаст новую ревизию, сохранив текущее состояние в истории.`,'Восстановить'))return;
          try {
            if(dirty){await saveDocument(false);if(dirty||conflicted)throw new Error('Текущие изменения не сохранены. Разрешите конфликт и попробуйте снова.');}
            const restored=await api(`/projects/${current.id}/revisions/restore`,'POST',{currentVersion:current.version,targetVersion:revision.version});
            current=restored;loading=true;editor.loadProjectData(restored.document.data);editor.clearDirtyCount();loading=false;dirty=false;conflicted=false;$('#reload-server').hidden=true;$('#save').disabled=false;refreshPages();$('#save-status').textContent=`Восстановлена ревизия ${revision.version} · новая ревизия ${current.version}`;$('#history-dialog').close();notify('Ревизия восстановлена');
          }catch(error){loading=false;if(error.status===409){conflicted=true;$('#reload-server').hidden=false;$('#save').disabled=true;$('#save-status').textContent='Версия изменилась в другой вкладке';}notify(error.message,true);}
        };row.append(restore);}list.append(row);
      }
    } catch(error){$('#history-list').textContent='';$('#history-error').textContent=error.message;}
  };
  $('#history').onclick=renderHistory;$('#close-history').onclick=()=>$('#history-dialog').close();
  $('#export').onclick=()=>download('juzimder-project.json',JSON.stringify({schemaVersion:1,engine:'grapesjs',data:editor.getProjectData()},null,2));
  $('#back').onclick=async()=>{
    if(dirty&&!conflicted)await saveDocument(false);
    if(dirty&&!await askConfirmation(conflicted?'Не удалось объединить изменения с сервером. Можно скачать их через «Экспорт JSON». Всё равно выйти?':'Изменения не сохранены. Всё равно выйти?','Всё равно выйти'))return;
    clearTimeout(saveTimer);saveTimer=undefined;editor.destroy();editor=null;dirty=false;await dashboard();
  };
  $('#preview').onclick=()=>{const html=editor.getHtml(),css=editor.getCss();$('#preview-frame').srcdoc=`<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><style>body{margin:0}${css}</style></head><body>${html}</body></html>`;$('#preview-dialog').showModal();};$('#close-preview').onclick=()=>$('#preview-dialog').close();
}
window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});
(async()=>{try{session=await api('/session');if(session.user)await dashboard();else auth();}catch(e){app.innerHTML='<main class="boot-error"><h1>Не удалось открыть пространство</h1><p></p><button onclick="location.reload()">Повторить</button></main>';$('.boot-error p').textContent=e.message;}})();
