import { serializedZero, sameOutsideZero } from './zero/sync.js';
import { registerZero } from './zero/register.js';
import { blockMedia } from './block-media.js';
import { installImagePicker } from './image-picker.js';
import { installFormInbox } from './form-inbox.js';
import { installColorPickerInputs, syncColorInputs } from './ui/color-picker.js';
import { editorPlugins, exposePluginBlocks } from './editor-plugins.js';
import { appendPhotoCredit as appendAIPhotoCredit, photoCreditCaption, photoCreditText, photoFigureFromImage } from './photo-credit.js';
import { tablerIcon, tablerCoreIcons, installEditorIcons, updateFlexIcons } from './editor-icons.js';
import { backgroundValue, installBackgroundControls } from './editor-background.js';
import { persistedComponentId } from './component-identity.js';
import 'grapesjs/dist/css/grapes.min.css';
import './ui-theme.css';
import './ui/color-picker.css';
import './style.css';
import './ui-overrides.css';
import { blocks, registerBlocks } from './blocks.js';
import { installComponentActions } from './component-actions.js';
import { removeUnassignedImageSlot } from './ai-image-slots.js';
import { createPageRoot } from './page-root.js';
import { decodeCustomCode, encodeCustomCode, submitSandboxFrame, clearSandboxFrame, registerSafeRuntimeComponents } from './custom-code.js';
import { DEFAULT_DESIGN_TOKENS, FONT_FAMILIES, FONT_WEIGHTS, TOKEN_FIELDS, designTokensFromStyle, designTokensToStyle, migrateLegacyTokenValue, normalizeDesignTokens, tokenValue } from './design-tokens.js';
import packageInfo from '../package.json';
const $ = (selector) => document.querySelector(selector);
const app = $('#app');
installColorPickerInputs(document.body);
const productVersion = packageInfo.productVersion;
const esc = (s) => String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const mark = '<span class="brand-mark"><i></i><i></i><i></i><i></i></span>';
const themeColorInputs = TOKEN_FIELDS.filter(field => field.type === 'color').map(field => `<label>${field.label}<input type="color" name="${field.name}" aria-label="${field.label}"></label>`).join('');
const themeFontOptions = FONT_FAMILIES.map(font => `<option value="${esc(font)}">${font.startsWith('Georgia') ? 'Georgia' : font.startsWith('system-ui') ? 'Системный' : 'Arial'}</option>`).join('');
const lineHeightOptions = Object.freeze(['1','1.1','1.15','1.2','1.25','1.3','1.4','1.5','1.6','1.7','1.8','1.9','2']);
const letterSpacingOptions = Object.freeze(['-2px','-1px','-0.5px','0px','0.25px','0.5px','1px','1.5px','2px']);
const fontSizeRanges = Object.freeze({baseFontSize:[12,24,1],fontSizeH1:[28,80,4],fontSizeH2:[22,64,2],fontSizeH3:[18,48,2],fontSizeH4:[16,40,2],fontSizeH5:[14,32,2],fontSizeH6:[12,28,2]});
const flexGroups=[
  {property:'flex-direction',label:'Направление',options:[['row','Строка'],['column','Колонка'],['row-reverse','Строка в обратном порядке'],['column-reverse','Колонка в обратном порядке']]},
  {property:'justify-content',label:'Распределение',options:[['flex-start','По началу'],['center','По центру'],['flex-end','По концу'],['space-between','Между элементами'],['space-around','Вокруг элементов'],['space-evenly','Равномерно']]},
  {property:'align-items',label:'Выравнивание',options:[['flex-start','По началу'],['center','По центру'],['flex-end','По концу'],['stretch','Растянуть'],['baseline','По базовой линии']]},
  {property:'flex-wrap',label:'Перенос',options:[['nowrap','Без переноса'],['wrap','Переносить'],['wrap-reverse','Переносить в обратном порядке']]}
];
const flexControlMarkup=flexGroups.map(group=>`<div class="flex-control-group"><span class="flex-control-label">${group.label}</span><div class="flex-option-row">${group.options.map(([value,label])=>`<button type="button" class="flex-option" data-flex-property="${group.property}" data-flex-value="${value}" title="${group.property}: ${value}" aria-label="${group.label}: ${label}" aria-pressed="false"><span class="flex-glyph" data-property="${group.property}" data-value="${value}" aria-hidden="true"><i></i><i></i><i></i></span></button>`).join('')}</div></div>`).join('');
const blockCategory=id=>blocks.find(block=>block.id===id)?.category||({text:'Элементы',image:'Медиа',button:'Элементы',container:'Компоновка',navigation:'Навигация'}[id]||'Секции');
let savedEditorSnapshot=null;
let session, editor, current, dirty=false, loading=false, saving=false, saveAgain=false, conflicted=false, saveTimer, savePromise, editorPublication=null;
const projectSlugs = new Map();
const projectUrl = (id, suffix='') => `/projects/${encodeURIComponent(projectSlugs.get(id) || id)}${suffix}`;
async function api(path, method='GET',body) {
  const response = await fetch(`/api${path}`,{method,headers:{'Content-Type':'application/json',...(session?{'X-CSRF-Token':session.csrf}:{})},...(body?{body:JSON.stringify(body)}:{})});
  let data;try{data=await response.json();}catch{const error=new Error('Сервер вернул некорректный ответ. Обновите страницу и повторите запрос.');error.status=response.status>=400?response.status:502;throw error;} if (!response.ok) { const error=new Error(data.error || `HTTP ${response.status}`);error.status=response.status;if(response.status===401&&session?.user&&path==='/projects'&&method==='POST'){session=null;location.assign('/?session=expired');}throw error; } return data;
}
function notify(message,error=false) { const el=$('#notice'); if(el){el.textContent=message;el.classList.toggle('error',error);} }
function setEditorPanel(panel) {
  const shell=$('.editor-shell');if(!shell)return;
  shell.dataset.openPanel=panel==='library'||panel==='layers'?panel:'none';
  if(panel==='inspector')shell.dataset.inspectorOpen='true';
  else if(window.matchMedia('(max-width: 800px)').matches)shell.dataset.inspectorOpen='false';
  for(const button of document.querySelectorAll('.mobile-workspace-tabs [data-mobile-panel]'))button.setAttribute('aria-pressed',String(button.dataset.mobilePanel===panel));
  for(const button of document.querySelectorAll('.editor-floating-tools [data-open-panel]'))button.setAttribute('aria-pressed',String(button.dataset.openPanel===panel));
  const library=$('#library'),layers=$('#layers');if(library)library.hidden=panel!=='library';if(layers)layers.hidden=panel!=='layers';
}
function download(name,content,type='application/json') { const url=URL.createObjectURL(new Blob([content],{type}));const a=document.createElement('a');a.href=url;a.download=name;a.click();setTimeout(()=>URL.revokeObjectURL(url),1000); }
function askConfirmation(message,confirmLabel='Продолжить') {
  const dialog=$('#confirm-dialog');dialog.returnValue='';$('#confirm-message').textContent=message;$('#confirm-submit').textContent=confirmLabel;
  return new Promise(resolve=>{dialog.addEventListener('close',()=>resolve(dialog.returnValue==='confirm'),{once:true});dialog.showModal();});
}
function syncSaveButtonState() {
  const button=$('#save');if(!button)return;
  button.dataset.saveState=conflicted?'conflict':saving?'saving':dirty?'unsaved':'saved';
  button.setAttribute('aria-busy',String(saving));
  syncPublishButtonState();
}
function syncPublishButtonState() {
  const button=$('#publish-editor');if(!button)return;
  const published=Boolean(editorPublication&&current&&!dirty&&!saving&&editorPublication.sourceProjectVersion===current.version);
  button.dataset.publishState=published?'published':'unpublished';
  button.title=published?`Опубликована текущая версия · ревизия ${current.version}`:'Есть изменения для публикации';
  button.setAttribute('aria-label',published?'Опубликовано':'Опубликовать');
}
function scheduleAutosave(delay=1400) { clearTimeout(saveTimer);saveTimer=setTimeout(()=>{saveTimer=undefined;saveDocument(true);},delay); }
async function saveDocument(automatic=false) {
  clearTimeout(saveTimer);saveTimer=undefined;
  if(!dirty||conflicted)return;
  if(saving){saveAgain=true;if(!automatic){const pending=savePromise;await pending;if(dirty&&!conflicted)return saveDocument(false);}return;}
  saving=true;const button=$('#save');if(button)button.disabled=true;syncSaveButtonState();
  const data=editor.getProjectData(),snapshot=JSON.stringify(data),version=current.version;
  let retryDelay=null;
  $('#save-status').textContent=automatic?'Автосохранение…':'Сохранение…';
  const operation=(async()=>{
    try {
      current=await api(`/projects/${current.id}`,'PUT',{version,document:{schemaVersion:1,engine:'grapesjs',data},settings:{designTokens:current.settings.designTokens}});
      savedEditorSnapshot=snapshot;dirty=JSON.stringify(editor.getProjectData())!==snapshot;
      $('#save-status').textContent=dirty?'Изменения продолжаются':`${automatic?'Автосохранено':'Сохранено'} · ревизия ${current.version}`;
      notify('');
    } catch(error) {
      if(error.status===409){conflicted=true;$('#save-status').textContent='Версия изменилась в другой вкладке';$('#reload-server').hidden=false;notify('Экспортируйте локальные изменения или загрузите серверную версию.',true);}
      else {const retryable=!error.status||error.status>=500;$('#save-status').textContent=retryable?'Не удалось сохранить · повторим автоматически':'Не удалось сохранить · проверьте изменения';notify(error.message,true);if(retryable)retryDelay=5000;}
    } finally {
      saving=false;if(button)button.disabled=conflicted;syncSaveButtonState();
      const again=saveAgain;saveAgain=false;
      if(again&&dirty&&!conflicted)await saveDocument(true);
      else if(dirty&&!conflicted&&!saveTimer&&retryDelay!==null)scheduleAutosave(retryDelay);
    }
  })();
  savePromise=operation;return operation;
}
function makeProjectTitle() {
  const title=document.createElement('span');title.id='project-name';title.className='project-name';title.setAttribute('role','button');title.tabIndex=0;title.textContent=current.name;
  title.title='Дважды щёлкните, чтобы изменить название проекта';title.setAttribute('aria-label',`Переименовать проект: ${current.name}`);
  return bindProjectTitle(title);
}
function bindProjectTitle(title) {
  title.addEventListener('dblclick',()=>beginProjectRename(title));
  title.addEventListener('keydown',event=>{if(event.key==='Enter'||event.key===' '||event.key==='F2'){event.preventDefault();beginProjectRename(title);}});
  return title;
}
function beginProjectRename(title) {
  if(!title.isConnected)return;
  const projectId=current.id,originalName=current.name,input=document.createElement('input');let savingName=false,finished=false;
  input.className='project-name-input';input.type='text';input.maxLength=100;input.value=originalName;input.setAttribute('aria-label','Новое название проекта');
  const restore=()=>{if(input.isConnected)input.replaceWith(makeProjectTitle());};
  const cancel=()=>{if(finished||savingName)return;finished=true;restore();notify('');};
  const save=async()=>{
    if(finished||savingName)return;
    const name=input.value.trim();
    if(!name){notify('Название проекта не может быть пустым.',true);input.focus();return;}
    if(name===originalName){finished=true;restore();notify('');return;}
    savingName=true;input.disabled=true;
    try{current=await api(`/projects/${projectId}/name`,'PUT',{version:current.version,expectedName:originalName,name});finished=true;restore();notify('Название проекта сохранено');}
    catch(error){savingName=false;input.disabled=false;notify(error.message,true);input.focus();input.select();}
  };
  input.addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();void save();}else if(event.key==='Escape'){event.preventDefault();cancel();}});
  input.addEventListener('blur',()=>{if(!finished&&!savingName)void save();});
  title.replaceWith(input);input.focus();input.select();
}
function auth(register=false) {
  const devAdmin = session?.devAuth === true && !register;
  const expiredSession=session?.sessionExpired||new URLSearchParams(location.search).get('session')==='expired';
  app.innerHTML=`<main class="auth"><div class="auth-story"><a class="brand" href="/">${mark} Juzimder <span>AI</span></a><div><p class="eyebrow">ВАШ СЛЕДУЮЩИЙ ПРОЕКТ НАЧИНАЕТСЯ ЗДЕСЬ</p><h1>Из идеи —<br>в живой сайт.</h1><p class="intro">Пространство, где дизайн складывается.<br>Одна секция, одна страница, одна идея за раз.</p></div><p class="muted">На базе GrapesJS Core · Открытая платформа</p></div><section class="auth-form"><p class="eyebrow">JUZIMDER WORKSPACE / 01</p><h2>${register?'Создайте своё пространство':'С возвращением'}</h2><p class="muted">${register?'Начните с первой страницы.':'Войдите, чтобы продолжить работу над сайтом.'}</p>${expiredSession?'<p class="muted" role="status">Сессия не относится к текущей локальной базе. Войдите снова.</p>':''}<form id="auth-form"><label>${devAdmin?'Email или локальный логин':'Email'}<input name="email" type="${devAdmin?'text':'email'}" autocomplete="${register?'email':'username'}" required ${register||!session?.devAuth?'minlength="12"':''} maxlength="254" placeholder="${devAdmin?'admin или you@studio.kz':'you@studio.kz'}"></label><label>Пароль<input name="password" type="password" autocomplete="${register?'new-password':'current-password'}" required ${register||!session?.devAuth?'minlength="12"':''} maxlength="72" placeholder="${devAdmin?'admin или не менее 12 символов':'Не менее 12 символов'}"></label><button class="primary" type="submit">${register?'Создать аккаунт':'Войти в пространство'} <span>→</span></button><p id="notice" role="status"></p></form>${devAdmin?'<p class="muted small">Локальный тестовый вход: <strong>admin / admin</strong></p>':''}<button class="text-button" id="toggle-auth">${register?'Уже есть аккаунт? Войти':'Первый раз здесь? Создать аккаунт'}</button><p class="auth-foot">Ранняя версия · Визуальное редактирование и сохранение</p></section></main>`;
  $('#toggle-auth').onclick=()=>auth(!register);
  $('#auth-form').onsubmit=async e=>{e.preventDefault();const b=e.target.querySelector('button');b.disabled=true;try{session=await api(register?'/register':'/login','POST',Object.fromEntries(new FormData(e.target)));history.replaceState(null,'','/');await dashboard();}catch(error){notify(error.message,true);}finally{b.disabled=false;}};
}
async function dashboard() {
  const projects=await api('/projects');
  for(const project of projects)if(project.slug)projectSlugs.set(project.id,project.slug);
  app.innerHTML=`<div class="dashboard"><aside class="dash-sidebar"><a class="brand" href="/">${mark} Juzimder <span>AI</span></a><div class="workspace-label">ЛИЧНОЕ ПРОСТРАНСТВО</div><div class="nav-active">▦ <span>Мои проекты</span><small>${projects.length}</small></div><div class="sidebar-note"><span class="status-dot"></span> FOUNDATION<div class="version-label">Версия продукта <strong>${productVersion}</strong></div><p>От первой идеи<br>до первой страницы.</p></div><button id="logout" class="text-button">Выйти из аккаунта ↗</button></aside><main class="dash-main"><header class="dash-top"><span>Workspace <span class="slash">/</span> Мои проекты</span><span class="dash-user"><span>${esc(session.user.email)}</span><span class="version-label">Версия ${productVersion}</span></span></header><section class="project-intro"><p class="eyebrow">СОЗДАВАЙТЕ В СВОЁМ РИТМЕ</p><div class="title-row"><div><h1>Мои проекты<span class="accent">.</span></h1><p class="muted">Хорошие сайты начинаются с чистого листа.</p></div><button id="new-project" class="primary">+ Новый проект</button></div></section><section class="project-list">${projects.map(p=>`<button class="project-card" data-project="${esc(p.id)}"><div class="project-art"><div class="mini-window"><div></div><i></i><i></i><span></span></div><span class="project-arrow">↗</span></div><div class="project-info"><h3>${esc(p.name)}</h3><p>${p.version?'Ревизия '+p.version:'Новый проект'} <span>Страницы →</span></p></div></button>`).join('')}<button class="project-card new-card" id="empty-create"><span class="plus">+</span><strong>Новая идея</strong><span>Создайте сайт с нуля</span></button></section><p id="notice" role="status"></p><footer class="dash-footer"><span>YOUR IDEAS, TAKING SHAPE.</span><span>GrapesJS Core · PHP · SQLite</span></footer></main></div><dialog id="create-dialog"><form id="create-form"><p class="eyebrow">НОВОЕ ПРОСТРАНСТВО</p><h2>Как назовём проект?</h2><label>Название проекта<input name="name" required maxlength="100" placeholder="Например, Сайт студии"></label><p class="muted">Начнём с пустой главной страницы.</p><p id="create-error" role="alert"></p><div class="dialog-actions"><button type="button" id="cancel-create">Отмена</button><button class="primary">Создать проект →</button></div></form></dialog>`;
  const open=()=>$('#create-dialog').showModal(); $('#new-project').onclick=open;$('#empty-create').onclick=open;$('#cancel-create').onclick=()=>$('#create-dialog').close();
  $('#create-form').onsubmit=async e=>{e.preventDefault();const b=e.target.querySelector('.primary');b.disabled=true;try{const project=await api('/projects','POST',{name:new FormData(e.target).get('name')});projectSlugs.set(project.id,project.slug);location.assign(projectUrl(project.id));}catch(error){$('#create-error').textContent=error.message;b.disabled=false;}};
  for(const btn of document.querySelectorAll('[data-project]')) btn.onclick=()=>location.assign(projectUrl(btn.dataset.project));
  $('#logout').onclick=async()=>{await api('/logout','POST',{});session=await api('/session');auth();};
}
async function projectPages(id) {
  const response=await api(`/projects/${id}/pages`),project=response.project,pages=response.pages;
  if(project.slug)projectSlugs.set(id,project.slug);
  const publicationResponse=await api(`/projects/${id}/publication`);
  let publication=publicationResponse.publication,publicUrl=publicationResponse.publicUrl;
  let pageDialogAction=null;
  const rows=pages.map(page=>`<tr><td class="page-select-cell"><input type="checkbox" class="page-select" data-page-select="${esc(page.id)}" aria-label="Выбрать страницу ${esc(page.name)}"></td><td><a class="page-title" href="${projectUrl(id)}/pages/${encodeURIComponent(page.id)}/edit">${esc(page.name)}</a><small class="page-revision-link">Изменить содержимое →</small></td><td><button class="url-value" data-page-action="slug" data-page="${esc(page.id)}" aria-label="Изменить URL ${esc(page.name)}">${esc(page.path)}</button></td><td><span class="home-state ${page.isHome?'is-home':''}">${page.isHome?'⌂ Главная':'Обычная'}</span></td><td><details class="page-menu"><summary aria-label="Действия: ${esc(page.name)}">⋯</summary><div class="page-menu-popover"><a href="${projectUrl(id)}/pages/${encodeURIComponent(page.id)}/edit">Редактировать</a><button data-page-action="rename" data-page="${esc(page.id)}">Переименовать</button><button data-page-action="slug" data-page="${esc(page.id)}">Изменить URL</button><button data-page-action="duplicate" data-page="${esc(page.id)}">Дублировать</button><button data-page-action="home" data-page="${esc(page.id)}" ${page.isHome?'disabled':''}>Сделать главной</button><button data-page-action="page-settings" data-page="${esc(page.id)}">Настройки страницы</button><button class="danger-action" data-page-action="delete" data-page="${esc(page.id)}">Удалить</button></div></details></td></tr>`).join('');
  app.innerHTML=`<div class="dashboard project-dashboard"><aside class="dash-sidebar"><a class="brand" href="/">${mark} Juzimder <span>AI</span></a><div class="workspace-label">ПРОЕКТ</div><a class="nav-active" href="${projectUrl(id)}">▦ <span>Страницы</span><small>${pages.length}</small></a><a class="project-side-link" href="${projectUrl(id)}/settings">⚙ <span>Настройки сайта</span></a><div class="sidebar-note"><span class="status-dot"></span> FOUNDATION<div class="version-label">Версия продукта <strong>${productVersion}</strong></div><p>${esc(project.name)}</p></div><a class="text-button" href="/">← Все проекты</a><button id="logout" class="text-button">Выйти из аккаунта ↗</button></aside><main class="dash-main"><header class="dash-top"><span><a href="/">Мои проекты</a><span class="slash">/</span>${esc(project.name)}</span><span class="version-label">Версия ${productVersion}</span></header><section class="project-intro"><p class="eyebrow">УПРАВЛЕНИЕ САЙТОМ</p><div class="title-row project-title-row"><div><h1>${esc(project.name)}<span class="accent">.</span></h1><p class="muted">Страницы сайта · ревизия ${project.version}</p></div><div class="project-actions"><a class="secondary-link" href="${projectUrl(id)}/settings">Настройки сайта</a><button id="create-page" class="primary">+ Создать страницу</button></div></div></section><section class="project-pages-section publication-panel" aria-labelledby="publication-title"><div class="section-title-row"><div><h2 id="publication-title">Публикация сайта</h2><p class="muted">Публичный адрес обслуживает только сохранённый статический снимок.</p></div><span id="publication-status" class="status-pill" role="status" aria-live="polite">Проверка…</span></div><div class="publication-actions"><button id="preview-site" class="secondary-link">Предпросмотр</button><button id="publish-site" class="primary">Опубликовать сайт</button><button id="unpublish-site" class="secondary-link" hidden>Снять с публикации</button><a id="publication-link" class="secondary-link" target="_blank" rel="noopener noreferrer" hidden>Открыть сайт ↗</a></div></section><section class="project-pages-section"><div class="section-title-row"><div><h2>Страницы</h2><p class="muted">URL-адреса не меняют внутренние идентификаторы страниц.</p></div><span class="page-count">${pages.length} из 30</span></div><div class="bulk-page-actions"><label class="visually-hidden" for="bulk-page-action">Действия с выбранными страницами</label><select id="bulk-page-action" disabled><option value="">Действия с выбранными…</option><option value="duplicate">Дублировать выбранные</option><option value="home">Сделать главной</option><option value="delete">Удалить выбранные</option></select><span id="bulk-page-selection" class="muted small" role="status" aria-live="polite">Страницы не выбраны</span></div>${pages.length?`<div class="pages-table-wrap"><table class="pages-table"><thead><tr><th class="page-select-cell"><input type="checkbox" id="select-all-pages" aria-label="Выбрать все страницы"></th><th>Название страницы</th><th>URL страницы</th><th>Главная</th><th><span class="visually-hidden">Действия</span></th></tr></thead><tbody>${rows}</tbody></table></div>`:`<div class="empty-pages"><h3>Здесь появится первая страница</h3><p>Создайте страницу, затем откройте её в редакторе.</p><button class="primary" id="empty-create-page">Создать первую страницу</button></div>`}</section><section class="project-pages-section system-items-section"><div class="section-title-row"><div><h2>Сквозные блоки и системные страницы</h2><p class="muted">Глобальные элементы проекта будут храниться отдельно от содержимого страниц.</p></div></div><div class="system-items"><div><span class="system-icon">↥</span><span><strong>Шапка сайта</strong><small>Общий Header для выбранных страниц</small></span><span class="status-pill">Не подключено</span></div><div><span class="system-icon">↧</span><span><strong>Подвал сайта</strong><small>Общий Footer для выбранных страниц</small></span><span class="status-pill">Не подключено</span></div><div><span class="system-icon">404</span><span><strong>Страница 404</strong><small>Системная страница для опубликованного сайта</small></span><span class="status-pill">Настраивается выше</span></div></div></section><p id="notice" role="status"></p><footer class="dash-footer"><span>YOUR IDEAS, TAKING SHAPE.</span><span>GrapesJS Core · PHP · SQLite</span></footer></main></div><dialog id="page-dialog"><form id="project-page-form"><p class="eyebrow" id="page-dialog-eyebrow">СТРАНИЦА ПРОЕКТА</p><h2 id="page-dialog-title"></h2><label id="page-dialog-label">Название<input id="page-dialog-input" name="value" required maxlength="100"></label><p class="muted small" id="page-dialog-help"></p><p id="page-dialog-error" role="alert"></p><div class="dialog-actions"><button type="button" id="cancel-page-dialog">Отмена</button><button class="primary" id="submit-page-dialog">Сохранить</button></div></form></dialog><dialog id="confirm-dialog"><form method="dialog"><p id="confirm-message"></p><div class="dialog-actions"><button value="cancel">Отмена</button><button id="confirm-submit" class="primary" value="confirm">Продолжить</button></div></form></dialog><dialog id="site-preview-dialog" class="site-preview-dialog" aria-labelledby="site-preview-title"><div class="site-preview-header"><div><p class="eyebrow">ПРЕДПРОСМОТР</p><h2 id="site-preview-title">Сохранённый сайт</h2></div><button id="close-site-preview" type="button" aria-label="Закрыть предпросмотр">×</button></div><label class="site-preview-selector">Страница<select id="site-preview-page" aria-label="Страница предпросмотра"></select></label><p id="site-preview-status" role="status" aria-live="polite">Подготовка…</p><iframe id="site-preview-frame" title="Предпросмотр страницы сайта" sandbox=""></iframe></dialog>`;
  installFormInbox(id, api);
  const renderPublication=()=>{const status=$('#publication-status'),link=$('#publication-link'),publishButton=$('#publish-site'),unpublishButton=$('#unpublish-site'),stale=publication&&publication.sourceProjectVersion!==project.version,published=Boolean(publication&&!stale);status.textContent=!publication?'Не опубликован':stale?`Устарела · версия ${publication.version}, ревизия ${publication.sourceProjectVersion}`:`Опубликована · версия ${publication.version}, ревизия ${publication.sourceProjectVersion}`;status.classList.toggle('publication-stale',Boolean(stale));publishButton.dataset.publicationState=published?'published':'unpublished';publishButton.textContent=publication?`Опубликовать ревизию ${project.version}`:'Опубликовать сайт';unpublishButton.hidden=!publication;link.hidden=!publication;if(publication)link.href=publicUrl;};
  renderPublication();
  const pageSelection = new Set(), selectAllPages = $('#select-all-pages'), bulkAction = $('#bulk-page-action'), selectionStatus = $('#bulk-page-selection');
  const selectedPageIds = () => [...pageSelection].filter(pageId => pages.some(page => page.id === pageId));
  const syncPageSelection = () => {
    const checkboxes = [...document.querySelectorAll('[data-page-select]')], selected = selectedPageIds();
    for (const checkbox of checkboxes) checkbox.checked = pageSelection.has(checkbox.dataset.pageSelect);
    selectAllPages.checked = checkboxes.length > 0 && selected.length === checkboxes.length;
    selectAllPages.indeterminate = selected.length > 0 && selected.length < checkboxes.length;
    bulkAction.disabled = selected.length === 0;
    selectionStatus.textContent = selected.length ? `Выбрано: ${selected.length}` : 'Страницы не выбраны';
  };
  selectAllPages.onchange = () => {
    pageSelection.clear();
    if (selectAllPages.checked) for (const page of pages) pageSelection.add(page.id);
    syncPageSelection();
  };
  for (const checkbox of document.querySelectorAll('[data-page-select]')) checkbox.onchange = () => {
    if (checkbox.checked) pageSelection.add(checkbox.dataset.pageSelect); else pageSelection.delete(checkbox.dataset.pageSelect);
    syncPageSelection();
  };
  bulkAction.onchange = async () => {
    const action = bulkAction.value, selected = selectedPageIds();
    if (!action || !selected.length) return;
    bulkAction.value = '';
    if (action === 'home' && selected.length !== 1) { notify('Чтобы выбрать главную, отметьте ровно одну страницу.', true); return; }
    if (action === 'delete' && selected.length >= pages.length) { notify('Нельзя удалить все страницы проекта. Оставьте хотя бы одну.', true); return; }
    if (action === 'duplicate' && pages.length + selected.length > 30) { notify('В проекте можно иметь не более 30 страниц.', true); return; }
    if (action === 'delete') {
      const names = selected.map(pageId => pages.find(page => page.id === pageId)?.name).filter(Boolean);
      if (!await askConfirmation(`Удалить ${selected.length} страниц: ${names.map(name => `«${name}»`).join(', ')}? Это действие создаст новые ревизии проекта.`, 'Удалить выбранные')) return;
    }
    bulkAction.disabled = true;
    let completed = 0;
    try {
      for (const pageId of selected) {
        const result = await api(`/projects/${id}/pages/${encodeURIComponent(pageId)}`, 'POST', { version: project.version, action });
        project.version = result.version;
        completed++;
      }
      await projectPages(id);
      notify(action === 'delete' ? `Удалено страниц: ${completed}.` : action === 'duplicate' ? `Создано копий: ${completed}.` : 'Главная страница обновлена.');
    } catch (error) {
      notify(completed ? `Выполнено ${completed} из ${selected.length}. Остальные действия не завершены: ${error.message}` : error.message, true);
      await projectPages(id);
    } finally {
      bulkAction.disabled = false;
    }
  };
  syncPageSelection();
  let previewData=null;
  const previewDialog=$('#site-preview-dialog'),previewFrame=$('#site-preview-frame'),previewSelect=$('#site-preview-page'),previewStatus=$('#site-preview-status');
  const renderSitePreview=()=>{const page=previewData?.pages.find(item=>item.path===previewSelect.value);if(!page)return;const parsed=new DOMParser().parseFromString(page.html,'text/html'),link=parsed.querySelector('link[rel="stylesheet"]');if(link){const style=parsed.createElement('style');style.textContent=previewData.css;link.replaceWith(style);}if(!parsed.body.children.length&&!parsed.body.textContent.trim()){const empty=parsed.createElement('div');empty.textContent='Страница пока пустая. Откройте редактор и добавьте секции, чтобы увидеть сайт.';empty.style.cssText='margin:24px;padding:22px;border:1px dashed #a8b7aa;border-radius:8px;color:#526457;background:#f5f8f5;font:14px/1.6 system-ui,sans-serif;text-align:center';parsed.body.append(empty);}previewFrame.srcdoc='<!doctype html>'+parsed.documentElement.outerHTML;};
  $('#preview-site').onclick=async()=>{const button=$('#preview-site');button.disabled=true;previewStatus.textContent='Подготовка предпросмотра…';previewDialog.showModal();try{previewData=await api(`/projects/${id}/preview/static`);if(!Array.isArray(previewData.pages)||!previewData.pages.length)throw new Error('В проекте пока нет страниц для предпросмотра.');previewSelect.replaceChildren(...previewData.pages.map(page=>{const option=document.createElement('option');option.value=page.path;option.textContent=`${page.name} · ${page.path}`;return option;}));previewSelect.value=previewData.pages[0].path;renderSitePreview();previewStatus.textContent=`Сохранённая ревизия ${previewData.version}. Изменения и публикация не затронуты.`;}catch(error){previewStatus.textContent=error.message;}finally{button.disabled=false;}};
  previewSelect.onchange=renderSitePreview;$('#close-site-preview').onclick=()=>previewDialog.close();previewDialog.addEventListener('close',()=>{previewFrame.srcdoc='';previewData=null;});
  $('#publish-site').onclick=async()=>{if(!await askConfirmation(`Будет опубликована сохранённая ревизия ${project.version}. Сайт станет доступен без входа на отдельном публичном адресе. Продолжить?`,'Опубликовать'))return;const button=$('#publish-site');button.disabled=true;try{const result=await api(`/projects/${id}/publish`,'POST',{version:project.version});publication=result.publication;publicUrl=result.publicUrl;renderPublication();notify('Сохранённая ревизия опубликована.');}catch(error){notify(error.message,true);}finally{button.disabled=false;}};
  $('#unpublish-site').onclick=async()=>{if(!await askConfirmation('Отключить публичный сайт? История публикаций сохранится.','Снять с публикации'))return;const button=$('#unpublish-site');button.disabled=true;try{const result=await api(`/projects/${id}/unpublish`,'POST',{version:project.version});publication=result.publication;publicUrl=null;renderPublication();notify('Публичный сайт отключён. История публикаций сохранена.');}catch(error){notify(error.message,true);}finally{button.disabled=false;}};
  const openDialog=(action,page=null)=>{pageDialogAction={action,pageId:page?.id??null};const slug=action==='slug';$('#page-dialog-eyebrow').textContent=action==='create'?'НОВАЯ СТРАНИЦА':'ИЗМЕНИТЬ СТРАНИЦУ';$('#page-dialog-title').textContent=action==='create'?'Создать страницу':slug?'Изменить URL страницы':'Переименовать страницу';$('#page-dialog-label').firstChild.textContent=slug?'URL страницы':'Название';const input=$('#page-dialog-input');input.value=slug?(page?.slug||''):action==='create'?'':(page?.name||'');input.maxLength=slug?80:100;input.placeholder=slug?'about-us':'Например, О компании';$('#page-dialog-help').textContent=slug?'Введите один адресный сегмент. Кириллица автоматически преобразуется в латиницу. Для главной страницы публичный адрес остаётся /.':'';$('#page-dialog-error').textContent='';$('#page-dialog').showModal();input.focus();input.select();};
  $('#create-page').onclick=()=>openDialog('create');$('#empty-create-page')?.addEventListener('click',()=>openDialog('create'));$('#cancel-page-dialog').onclick=()=>$('#page-dialog').close();
  $('#project-page-form').onsubmit=async event=>{event.preventDefault();if(!pageDialogAction)return;const submit=$('#submit-page-dialog');submit.disabled=true;try{const action=pageDialogAction.action,body={version:project.version,action};if(action==='create'||action==='rename')body.name=$('#page-dialog-input').value;if(action==='slug')body.slug=$('#page-dialog-input').value;const path=pageDialogAction.pageId?`/projects/${id}/pages/${encodeURIComponent(pageDialogAction.pageId)}`:`/projects/${id}/pages`;await api(path,'POST',body);$('#page-dialog').close();await projectPages(id);}catch(error){$('#page-dialog-error').textContent=error.message;}finally{submit.disabled=false;}};
  for(const button of document.querySelectorAll('[data-page-action]'))button.onclick=async()=>{const action=button.dataset.pageAction,page=pages.find(item=>item.id===button.dataset.page);if(action==='rename'||action==='slug'){openDialog(action,page);button.closest('details')?.removeAttribute('open');return;}if(action==='page-settings'){notify('Настройки отдельных страниц появятся вместе с разделом SEO.',true);button.closest('details')?.removeAttribute('open');return;}if(action==='delete'&&!await askConfirmation(`Удалить страницу «${page.name}»?${page.isHome?' Главной станет первая оставшаяся страница.':''} Это действие сохранит новую ревизию.`,'Удалить страницу'))return;try{await api(`/projects/${id}/pages/${encodeURIComponent(page.id)}`,'POST',{version:project.version,action});await projectPages(id);}catch(error){notify(error.message,true);}};
  $('#logout').onclick=async()=>{await api('/logout','POST',{});session=await api('/session');auth();};
}
async function siteSettings(id) {
  let project=await api(`/projects/${id}`);if(project.slug)projectSlugs.set(id,project.slug);const sections=[['domain','Домен'],['fonts','Шрифты'],['design','Цвета и стили'],['seo','SEO'],['analytics','Аналитика'],['forms','Формы'],['code','Вставка кода'],['ai','AI Engine']];
  const active=new URLSearchParams(location.search).get('section')||'domain',selected=sections.find(([key])=>key===active)||sections[0];
  const settingsNav=`<nav class="settings-nav sidebar-settings-nav" aria-label="Разделы настроек">${sections.map(([key,label])=>`<a href="${projectUrl(id)}/settings?section=${key}" class="${selected[0]===key?'active':''}" ${selected[0]===key?'aria-current="page"':''}>${label}</a>`).join('')}</nav>`;
  const tokens=normalizeDesignTokens(project.settings?.designTokens||DEFAULT_DESIGN_TOKENS);
  const tokenSelect=(name,label,choices,choiceLabel=value=>value)=>`<label class="settings-field">${esc(label)}<select name="${name}">${choices.map(value=>`<option value="${esc(value)}" ${tokens[name]===value?'selected':''}>${esc(choiceLabel(value))}</option>`).join('')}</select></label>`;
  const sizeSelect=(name,label)=>{const [min,max,step]=fontSizeRanges[name];const choices=Array.from({length:Math.floor((max-min)/step)+1},(_,index)=>`${min+index*step}px`);return tokenSelect(name,label,choices);};
  let body='',aiSettings=null,aiSkillLibrary=null,formMailSettings=null;
  if(selected[0]==='design')body=`<form id="design-tokens-form" class="settings-form"><p class="muted settings-form-intro">Основные цвета и скругление хранятся в настройках проекта, применяются ко всем страницам и доступны редактору и AI.</p><div class="settings-color-grid">${TOKEN_FIELDS.filter(field=>field.type==='color').map(field=>`<label>${esc(field.label)}<input type="color" name="${field.name}" value="${esc(tokens[field.name])}" aria-label="${esc(field.label)}"></label>`).join('')}</div><label class="settings-field">Скругление карточек<select name="radius">${[0,4,6,8,12,16,24,32].map(px=>`<option value="${px}px" ${tokens.radius===`${px}px`?'selected':''}>${px} px</option>`).join('')}</select></label><p class="muted small">Локальные стили элементов остаются самостоятельными. Токены сохраняются отдельной ревизией и включаются в CSS предпросмотра, ZIP-экспорта и опубликованного сайта.</p><div class="settings-form-actions"><button class="primary" type="submit">Сохранить цвета и стили</button><span id="settings-save-status" class="muted small" role="status"></span></div></form>`;
  else if(selected[0]==='fonts')body=`<form id="font-tokens-form" class="settings-form"><p class="muted settings-form-intro">Задайте шрифты, веса, размеры и интервалы проекта. H1–H3 адаптируются к ширине холста; значения, заданные непосредственно элементу, сохраняют приоритет.</p><div class="typography-grid">${tokenSelect('headingFontFamily','Шрифт заголовков',FONT_FAMILIES,font=>font.startsWith('Georgia')?'Georgia':font.startsWith('system-ui')?'Системный':'Arial')}${tokenSelect('bodyFontFamily','Шрифт основного текста',FONT_FAMILIES,font=>font.startsWith('Georgia')?'Georgia':font.startsWith('system-ui')?'Системный':'Arial')}${tokenSelect('headingFontWeight','Вес заголовков',FONT_WEIGHTS)}${tokenSelect('bodyFontWeight','Вес основного текста',FONT_WEIGHTS)}${sizeSelect('baseFontSize','Базовый размер текста')}${sizeSelect('fontSizeH1','Размер H1')}${sizeSelect('fontSizeH2','Размер H2')}${sizeSelect('fontSizeH3','Размер H3')}${sizeSelect('fontSizeH4','Размер H4')}${sizeSelect('fontSizeH5','Размер H5')}${sizeSelect('fontSizeH6','Размер H6')}${tokenSelect('headingLineHeight','Интервал заголовков',lineHeightOptions)}${tokenSelect('bodyLineHeight','Интервал основного текста',lineHeightOptions)}${tokenSelect('letterSpacing','Межбуквенный интервал',letterSpacingOptions)}</div><p class="muted small">Настройки сохраняются новой ревизией и используются редактором и AI-секциями. Типографические токены входят в предпросмотр, ZIP-экспорт и опубликованный сайт.</p><div class="settings-form-actions"><button class="primary" type="submit">Сохранить типографику</button><span id="settings-save-status" class="muted small" role="status"></span></div></form>`;
  else if(selected[0]==='seo'){
    const pages=project.document.data.pages||[],requestedPage=new URLSearchParams(location.search).get('page'),seoPage=pages.find(page=>page.id===requestedPage)||pages[0],seo=project.settings?.pages?.[seoPage?.id]?.seo||{title:'',description:'',robots:'index',socialImage:''};
    body=`<label class="settings-field">Страница<select id="seo-page-select">${pages.map(page=>`<option value="${esc(page.id)}" ${page.id===seoPage?.id?'selected':''}>${esc(page.name)}</option>`).join('')}</select></label><form id="seo-settings-form" class="settings-form"><input type="hidden" name="pageId" value="${esc(seoPage?.id||'')}"><p class="muted settings-form-intro">SEO-метаданные сохраняются для каждой страницы вместе с ревизиями проекта и используются в предпросмотре, ZIP-экспорте и опубликованном сайте.</p><label class="settings-field">Заголовок страницы<input name="title" maxlength="70" value="${esc(seo.title||'')}" placeholder="Пустое поле использует название страницы"><span class="muted small">До 70 символов.</span></label><label class="settings-field">Описание страницы<textarea name="description" rows="4" maxlength="160" placeholder="Кратко опишите содержание страницы">${esc(seo.description||'')}</textarea><span class="muted small">До 160 символов.</span></label><label class="settings-field">Индексация<select name="robots"><option value="index" ${seo.robots==='index'?'selected':''}>Разрешить индексацию</option><option value="noindex" ${seo.robots==='noindex'?'selected':''}>Запретить индексацию</option></select></label><label class="settings-field">Изображение для соцсетей<input name="socialImage" type="url" value="${esc(seo.socialImage||'')}" maxlength="2048" pattern="https://.*" placeholder="https://example.com/preview.jpg"><span class="muted small">Необязательно. Используется HTTPS-адрес.</span></label><div class="settings-form-actions"><button class="primary" type="submit">Сохранить SEO</button><span id="seo-save-status" class="muted small" role="status"></span></div></form>`;
  }
  else if(selected[0]==='forms'){
    try { formMailSettings=await api(`/projects/${id}/form-mail`); }
    catch(error) { formMailSettings={configured:false,host:'',port:587,security:'starttls',username:'',hasPassword:false,version:0,error:error.message}; }
    const config=project.settings?.forms||{notificationsEnabled:false,recipients:[],senderName:'',senderEmail:'',subject:'Новая заявка с сайта {{site}}',body:'Новая заявка\n\n{{fields}}',autoresponderEnabled:false,autoresponderEmailField:'email',autoresponderSubject:'Мы получили вашу заявку',autoresponderBody:'Здравствуйте!\n\nМы получили вашу заявку и свяжемся с вами.\n\n{{site}}'};
    body=`<section class="form-delivery-settings"><p class="muted settings-form-intro">Заявки продолжают сохраняться отдельно в разделе «Заявки» проекта. Здесь настраивается только доставка писем с опубликованных форм.</p><form id="forms-settings-form" class="settings-form"><h3>Уведомления о заявках</h3><label class="settings-check"><input type="checkbox" name="notificationsEnabled" ${config.notificationsEnabled?'checked':''}> Отправлять уведомление при новой заявке</label><label class="settings-field">Получатели · по одному email в строке<textarea name="recipients" rows="3" placeholder="owner@example.com">${esc((config.recipients||[]).join('\n'))}</textarea></label><div class="typography-grid"><label class="settings-field">Имя отправителя<input name="senderName" maxlength="100" value="${esc(config.senderName||'')}" placeholder="Название компании"></label><label class="settings-field">Email отправителя<input name="senderEmail" type="email" maxlength="254" value="${esc(config.senderEmail||'')}" placeholder="forms@example.com"></label></div><label class="settings-field">Тема письма<input name="subject" maxlength="200" value="${esc(config.subject||'')}"></label><label class="settings-field">Текст письма<textarea name="body" rows="7" maxlength="10000">${esc(config.body||'')}</textarea></label><div class="settings-template-help muted small"><strong>Переменные шаблона:</strong> <code>{{site}}</code>, <code>{{form}}</code>, <code>{{page}}</code>, <code>{{date}}</code>, <code>{{fields}}</code> и поля формы, например <code>{{field:name}}</code> или <code>{{field:email}}</code>.</div><hr><h3>Автоответ посетителю</h3><label class="settings-check"><input type="checkbox" name="autoresponderEnabled" ${config.autoresponderEnabled?'checked':''}> Отправлять автоответ на email из формы</label><label class="settings-field">Имя email-поля<input name="autoresponderEmailField" maxlength="64" value="${esc(config.autoresponderEmailField||'email')}" placeholder="email"></label><label class="settings-field">Тема автоответа<input name="autoresponderSubject" maxlength="200" value="${esc(config.autoresponderSubject||'')}"></label><label class="settings-field">Текст автоответа<textarea name="autoresponderBody" rows="5" maxlength="10000">${esc(config.autoresponderBody||'')}</textarea></label><div class="settings-form-actions"><button class="primary" type="submit">Сохранить настройки писем</button><span id="forms-settings-status" class="muted small" role="status">${esc(formMailSettings.error||'')}</span></div></form><form id="smtp-settings-form" class="settings-form"><h3>Почтовый сервер SMTP</h3><p class="muted small">Пароль шифруется отдельным закрытым ключом на сервере и не попадает в настройки проекта или историю ревизий. TLS и SSL проверяют сертификат сервера.</p><div class="typography-grid"><label class="settings-field">SMTP host<input name="host" required maxlength="253" value="${esc(formMailSettings.host||'')}" placeholder="smtp.example.com"></label><label class="settings-field">Порт<input name="port" type="number" min="1" max="65535" required value="${esc(formMailSettings.port||587)}"></label></div><div class="typography-grid"><label class="settings-field">Защита соединения<select name="security"><option value="starttls" ${formMailSettings.security==='starttls'?'selected':''}>STARTTLS</option><option value="ssl" ${formMailSettings.security==='ssl'?'selected':''}>SSL/TLS</option><option value="none" ${formMailSettings.security==='none'?'selected':''}>Без шифрования</option></select></label><label class="settings-field">Логин<input name="username" autocomplete="username" value="${esc(formMailSettings.username||'')}"></label></div><label class="settings-field">Пароль SMTP<input name="password" type="password" autocomplete="new-password" maxlength="1024" placeholder="${formMailSettings.hasPassword?'Сохранён · пустое поле оставит его без изменений':'Пароль SMTP'}"></label><div class="settings-form-actions"><button class="primary" type="submit">Сохранить SMTP</button><button id="smtp-test" class="secondary-link" type="button" ${formMailSettings.configured?'':'disabled'}>Отправить тестовое письмо</button><span id="smtp-settings-status" class="muted small" role="status">${esc(formMailSettings.error||`SMTP · версия ${formMailSettings.version}`)}</span></div><label class="settings-field">Email для теста<input id="smtp-test-to" type="email" required maxlength="254" placeholder="you@example.com"></label></form></section>`;
  }
  else if(selected[0]==='ai'){
    try { aiSettings=await api('/projects/'+id+'/ai/settings'); }
    catch(error) { aiSettings={configured:false,model:null,activeProfileId:null,profiles:[],version:0,error:error.message}; }
    try { aiSkillLibrary=await api(`/projects/${id}/ai/skills`); }
    catch(error) { aiSkillLibrary={version:0,activeSkillId:null,skills:[],limit:10,error:error.message}; }
    const initialProfileId=aiSettings.activeProfileId||(aiSettings.profiles[0]?.id||'');
    const initialProfile=aiSettings.profiles.find(profile=>profile.id===initialProfileId);
    const profileCards=aiSettings.profiles.map(profile=>'<button type="button" data-ai-profile-id="'+esc(profile.id)+'" class="ai-profile-card'+(profile.id===initialProfileId?' selected':'')+'" aria-pressed="'+(profile.id===initialProfileId?'true':'false')+'"><span><strong>'+esc(profile.name)+'</strong><small>'+esc(profile.model)+(profile.fallbackModel?' · запасная: '+esc(profile.fallbackModel):'')+'</small></span><span class="status-pill">'+(profile.active?'Активен':'Редактировать')+'</span></button>').join('');
    body='<section class="ai-engine-settings"><div class="settings-notice"><span class="settings-notice-icon">✳</span><div><strong id="ai-settings-heading">'+(aiSettings.configured?'Активный профиль: '+esc(aiSettings.profiles.find(profile=>profile.id===aiSettings.activeProfileId)?.name||'OpenRouter'):'Добавьте профиль OpenRouter')+'</strong><p id="ai-settings-intro">'+(aiSettings.configured?'AI-запросы начинают с выбранного профиля и модели «'+esc(aiSettings.model||'')+'»'+(aiSettings.fallbackModel?' · запасная модель «'+esc(aiSettings.fallbackModel)+'».':'.'):'Сохраните несколько профилей с разными API-ключами и моделями. Один профиль будет активным для AI-запросов.')+'</p></div></div><p class="muted small ai-profile-security">При таймауте, лимите, временном сбое, отказе ключа или модели запрос автоматически перейдёт к другим сохранённым профилям. Активный профиль не изменится. Каждая попытка может расходовать токены. «Проверить профиль» отправляет только короткий служебный запрос в сохранённую основную модель (до 256 токенов, 20 секунд), без fallback и без изменения настроек. Ключи шифруются на сервере и не возвращаются в браузер. Чтобы заменить ключ профиля, введите новый; пустое поле сохранит прежний.</p><p class="muted small ai-profile-security"><strong>О модели openrouter/free:</strong> это динамический маршрутизатор, который выбирает бесплатную модель случайным образом. Между запросами могут меняться фактическая модель, задержка и формат ответа. Для более воспроизводимой генерации укажите конкретные ID основной и резервной моделей.</p><div class="ai-profile-manager"><div class="ai-profile-list-column"><div class="ai-profile-list-heading"><h3>Профили OpenRouter</h3><button id="ai-profile-add" class="secondary-link" type="button">+ Новый профиль</button></div><div id="ai-profile-list" class="ai-profile-list">'+(profileCards||'<p class="muted small">Профилей пока нет. Создайте первый профиль справа.</p>')+'</div></div><form id="ai-settings-form" class="settings-form ai-profile-form"><input type="hidden" name="profileId" value="'+esc(initialProfile?.id||'')+'"><label class="settings-field">Название профиля<input name="profileName" required maxlength="64" value="'+esc(initialProfile?.name||'')+'" placeholder="Например, Основной"></label><label class="settings-field">API-ключ OpenRouter<input type="password" name="apiKey" autocomplete="off" maxlength="512" placeholder="'+(initialProfile?'Ключ сохранён · пустое поле оставит его без изменений':'sk-or-v1-…')+'" spellcheck="false" '+(initialProfile?'':'required')+'></label><label class="settings-field">ID основной модели<input type="text" name="model" required maxlength="128" value="'+esc(initialProfile?.model||'')+'" placeholder="provider/model-slug" autocomplete="off" spellcheck="false"></label><label class="settings-field">ID резервной модели · необязательно<input type="text" name="fallbackModel" maxlength="128" value="'+esc(initialProfile?.fallbackModel||'')+'" placeholder="provider/fallback-model" autocomplete="off" spellcheck="false"></label><p class="muted small">При временной ошибке или лимите основной модели запрос повторится через резервную. OpenRouter может начислить стоимость за каждый запрос. Пустое поле отключает резервную модель.</p><p class="muted small">Ключ передаётся серверу вашего проекта (на production — по HTTPS), шифруется и не попадает в Project Data или историю ревизий.</p><div class="settings-form-actions"><button id="ai-profile-save" class="primary" type="submit">'+(initialProfile?'Сохранить изменения':'Создать профиль')+'</button><button id="ai-profile-activate" class="secondary-link" type="button" '+(!initialProfile||initialProfile.active?'hidden':'')+'>Использовать для AI</button><button id="ai-profile-test" class="secondary-link" type="button">Проверить профиль</button><button id="ai-profile-delete" class="secondary-link danger-action" type="button" '+(initialProfile?'':'hidden')+'>Удалить профиль</button><span id="ai-settings-status" class="muted small" role="status">'+esc(aiSettings.error||'Профили AI · версия '+aiSettings.version)+'</span></div></form></div><a class="secondary-link ai-editor-link" href="/projects/'+id+'/pages/'+encodeURIComponent(project.document.data.pages[0].id)+'/edit">Перейти к редактору AI-плана</a></section>';
    body+=`<section class="ai-skill-library" aria-labelledby="ai-skills-title"><div class="section-title-row"><div><h3 id="ai-skills-title">Пользовательские Skills</h3><p class="muted">До ${aiSkillLibrary.limit} Markdown-файлов или вставленных инструкций на проект.</p></div><span id="ai-skills-version" class="version-label">Библиотека · версия ${aiSkillLibrary.version}</span></div><p class="muted small ai-skill-disclosure">Текст хранится только в проекте. Он отправится в OpenRouter после нажатия «Разобрать Skill с AI» либо при генерации дизайна с выбранным Skill. Skill не исполняет команды и не гарантирует безошибочное выполнение: перед импортом вы вручную отметите его пункты проверки.</p><details class="ai-skill-rules-help"><summary>Автоматические правила Skill</summary><p class="muted small">Добавьте этот блок в текст Skill. Сервер проверит правила и выполнит одну исправляющую попытку при нарушении. Остальные требования остаются ручными.</p><pre>\`\`\`juzimder-rules
{"schemaVersion":1,"requirements":[
  {"id":"single-title","label":"Один H1","type":"heading_count","tag":"h1","count":1},
  {"id":"responsive","label":"Адаптивное CSS-правило","type":"responsive_css"}
]}
\`\`\`</pre><p class="muted small">Также доступны section_count (min/max от 3 до 9), text_present и text_absent (text до 200 символов). До 20 правил; каждый id уникален. Текст проверяется в DOM, адаптация — по наличию CSS-правила.</p></details><div class="ai-skill-manager"><div class="ai-profile-list-column"><div class="ai-profile-list-heading"><h4>Сохранённые Skills</h4><button id="ai-skill-add" class="secondary-link" type="button">+ Добавить Skill</button></div><div id="ai-skill-list" class="ai-profile-list"></div></div><form id="ai-skill-form" class="settings-form ai-skill-form"><input type="hidden" name="skillId" value=""><label class="settings-field">Название<input name="skillName" required maxlength="64" placeholder="Например, фирменный стиль"></label><label class="settings-field">Загрузить Markdown-файл<input id="ai-skill-file" type="file" accept=".md,.markdown,text/markdown,text/plain"></label><label class="settings-field">Текст Skill<textarea name="content" rows="12" maxlength="16000" required placeholder="# Название Skill&#10;&#10;Опишите правила и ограничения дизайна"></textarea><small class="muted">UTF-8 · максимум 16 000 символов</small></label><label class="settings-field">Пункты ручной проверки<textarea name="manualChecks" rows="4" maxlength="3000" required placeholder="Проверь адаптивность на телефоне&#10;Убедись, что CTA заметна"></textarea><small class="muted">По одному пункту в строке · от 1 до 12 пунктов</small></label><p class="muted small">«Разобрать Skill» отправит текущий текст (до 15 000 символов) в OpenRouter через активный профиль. При нарушении формата или цитат возможна одна исправляющая попытка с дополнительным расходом токенов. Ответ — черновик: проверьте полноту и точность извлечённых требований.</p><button id="ai-skill-analyze" type="button">Разобрать Skill с AI</button><section id="ai-skill-analysis" hidden aria-label="Черновик требований Skill"></section><div class="settings-form-actions"><button id="ai-skill-save" class="primary" type="submit">Сохранить Skill</button><button id="ai-skill-activate" class="secondary-link" type="button" hidden>Использовать в редакторе</button><button id="ai-skill-delete" class="secondary-link danger-action" type="button" hidden>Удалить Skill</button><span id="ai-skill-status" class="muted small" role="status">${esc(aiSkillLibrary.error||`Библиотека Skills · версия ${aiSkillLibrary.version}`)}</span></div></form></div></section>`;
  }
  else if(selected[0]==='code')body=`<form id="custom-css-form" class="settings-form"><p class="muted settings-form-intro">Дополнительный CSS применяется только к страницам опубликованного сайта и ZIP-экспорта. Он не загружается в редактор и не меняет его интерфейс. JavaScript и произвольный HTML здесь не исполняются.</p><label class="settings-field">CSS сайта<textarea name="customCss" id="custom-css-input" rows="16" maxlength="12000" spellcheck="false" placeholder=".hero { background-color: #222629; color: white; }">${esc(project.settings?.customCss||'')}</textarea><span class="muted small">До 12 000 символов. Простые классы/теги и безопасные свойства; без URL, скриптов, @-правил и позиционирования.</span></label><div class="settings-form-actions"><button class="primary" type="submit">Сохранить CSS</button><button id="custom-css-preview" type="button">Предпросмотр</button><span id="custom-css-status" class="muted small" role="status"></span></div><iframe id="custom-css-preview-frame" sandbox="" title="Изолированный предпросмотр опубликованной страницы" hidden></iframe></form>`;
  else body=`<div class="settings-notice settings-planned"><span class="settings-notice-icon">◷</span><div><strong>${selected[0]==='domain'?'Подключение домена пока недоступно':'Раздел пока не подключён'}</strong><p>${selected[0]==='domain'?'Публичная публикация, DNS-проверка, маршрутизация и выпуск SSL ещё не реализованы. Сайт не будет показан как подключённый без проверки.':'Для этого раздела пока нет сохранения и backend-интеграции. Форма появится вместе с рабочей серверной поддержкой.'}</p><span class="status-pill">Не реализовано</span></div></div>`;
  app.innerHTML=`<div class="dashboard project-dashboard settings-dashboard"><aside class="dash-sidebar"><a class="brand" href="/">${mark} Juzimder <span>AI</span></a><div class="workspace-label">ПРОЕКТ</div><a class="project-side-link" href="${projectUrl(id)}">▦ <span>Страницы</span></a><a class="nav-active" href="${projectUrl(id)}/settings">⚙ <span>Настройки сайта</span></a>${settingsNav}<div class="sidebar-note"><span class="status-dot"></span> FOUNDATION<div class="version-label">Версия продукта <strong>${productVersion}</strong></div><p>${esc(project.name)}</p></div><a class="text-button" href="/">← Все проекты</a><button id="logout" class="text-button">Выйти из аккаунта ↗</button></aside><main class="dash-main settings-main"><header class="dash-top"><span><a href="/">Мои проекты</a><span class="slash">/</span><a href="${projectUrl(id)}">${esc(project.name)}</a><span class="slash">/</span>Настройки сайта</span><span class="version-label">Версия ${productVersion}</span></header><section class="project-intro"><p class="eyebrow">НАСТРОЙКИ ПРОЕКТА</p><h1>Настройки сайта<span class="accent">.</span></h1><p class="muted">Общие параметры проекта и подключённые возможности.</p></section><div class="settings-layout"><section class="settings-content"><div class="section-title-row"><div><p class="eyebrow">${esc(selected[1].toUpperCase())}</p><h2>${esc(selected[1])}</h2></div><span id="settings-revision" class="version-label">Проект · ревизия ${project.version}</span></div>${body}</section></div><footer class="dash-footer"><span>YOUR IDEAS, TAKING SHAPE.</span><span>GrapesJS Core · PHP · SQLite</span></footer></main></div><dialog id="confirm-dialog"><form method="dialog"><p id="confirm-message"></p><div class="dialog-actions"><button value="cancel">Отмена</button><button id="confirm-submit" class="primary" value="confirm">Продолжить</button></div></form></dialog>`;
  const settingsForm=$('#design-tokens-form')||$('#font-tokens-form');
  if(settingsForm)settingsForm.onsubmit=async event=>{event.preventDefault();const button=settingsForm.querySelector('[type="submit"]'),status=$('#settings-save-status');button.disabled=true;status.textContent='Сохранение…';try{const nextTokens=normalizeDesignTokens({...project.settings.designTokens,...Object.fromEntries(new FormData(settingsForm))});project=await api(`/projects/${id}/settings`,'PUT',{version:project.version,designTokens:nextTokens});$('#settings-revision').textContent=`Проект · ревизия ${project.version}`;status.textContent=`Сохранено · ревизия ${project.version}`;}catch(error){status.textContent=error.message;}finally{button.disabled=false;}};
  const seoPageSelect=$('#seo-page-select');if(seoPageSelect)seoPageSelect.onchange=()=>{const url=new URL(location.href);url.searchParams.set('section','seo');url.searchParams.set('page',seoPageSelect.value);location.assign(url);};
  const seoForm=$('#seo-settings-form');if(seoForm)seoForm.onsubmit=async event=>{event.preventDefault();const button=seoForm.querySelector('[type="submit"]'),status=$('#seo-save-status');button.disabled=true;status.textContent='Сохранение SEO…';try{const values=Object.fromEntries(new FormData(seoForm)),pageId=values.pageId;delete values.pageId;project=await api(`/projects/${id}/settings`,'PUT',{version:project.version,seo:{pageId,...values}});$('#settings-revision').textContent=`Проект · ревизия ${project.version}`;status.textContent=`SEO сохранено · ревизия ${project.version}`;}catch(error){status.textContent=error.message;}finally{button.disabled=false;}};
  if(selected[0]==='code'){
    const cssForm=$('#custom-css-form'),cssInput=$('#custom-css-input'),cssStatus=$('#custom-css-status'),previewButton=$('#custom-css-preview'),previewFrame=$('#custom-css-preview-frame');
    const syncPreviewState=()=>{previewButton.disabled=cssInput.value!==(project.settings?.customCss||'');};
    cssInput.addEventListener('input',syncPreviewState);
    cssForm.onsubmit=async event=>{event.preventDefault();const button=cssForm.querySelector('[type="submit"]');button.disabled=true;cssStatus.textContent='Проверяем и сохраняем CSS…';try{project=await api(`/projects/${id}/settings`,'PUT',{version:project.version,customCss:cssInput.value});cssInput.value=project.settings.customCss||'';$('#settings-revision').textContent=`Проект · ревизия ${project.version}`;cssStatus.textContent=`CSS сохранён · ревизия ${project.version}`;syncPreviewState();}catch(error){cssStatus.textContent=error.message;}finally{button.disabled=false;}};
    previewButton.onclick=async()=>{if(cssInput.value!==(project.settings?.customCss||'')){cssStatus.textContent='Сначала сохраните изменения CSS.';return;}previewButton.disabled=true;cssStatus.textContent='Загружаем сохранённую страницу…';try{const data=await api(`/projects/${id}/preview/static`),page=data.pages?.[0];if(!page)throw new Error('В проекте нет страницы для предпросмотра.');const parsed=new DOMParser().parseFromString(page.html,'text/html'),link=parsed.querySelector('link[rel="stylesheet"]');if(link){const style=parsed.createElement('style');style.textContent=data.css;link.replaceWith(style);}previewFrame.srcdoc='<!doctype html>'+parsed.documentElement.outerHTML;previewFrame.hidden=false;cssStatus.textContent=`Изолированный предпросмотр сохранённой ревизии ${data.version}.`;}catch(error){cssStatus.textContent=error.message;}finally{previewButton.disabled=false;}};
    syncPreviewState();
  }
  if(selected[0]==='forms'){
    const formsForm=$('#forms-settings-form'),smtpForm=$('#smtp-settings-form'),formsStatus=$('#forms-settings-status'),smtpStatus=$('#smtp-settings-status');
    formsForm.onsubmit=async event=>{event.preventDefault();const button=formsForm.querySelector('[type="submit"]');button.disabled=true;formsStatus.textContent='Сохраняем настройки писем…';try{const values=Object.fromEntries(new FormData(formsForm));values.notificationsEnabled=formsForm.elements.notificationsEnabled.checked;values.autoresponderEnabled=formsForm.elements.autoresponderEnabled.checked;values.recipients=values.recipients.split(/[\r\n,;]+/).map(value=>value.trim()).filter(Boolean);project=await api(`/projects/${id}/settings`,'PUT',{version:project.version,forms:values});$('#settings-revision').textContent=`Проект · ревизия ${project.version}`;formsStatus.textContent=`Настройки писем сохранены · ревизия ${project.version}`;}catch(error){formsStatus.textContent=error.message;}finally{button.disabled=false;}};
    smtpForm.onsubmit=async event=>{event.preventDefault();const button=smtpForm.querySelector('[type="submit"]');button.disabled=true;smtpStatus.textContent='Сохраняем SMTP…';try{const values=Object.fromEntries(new FormData(smtpForm));values.port=Number(values.port);formMailSettings=await api(`/projects/${id}/form-mail`,'PUT',{version:formMailSettings.version,...values});smtpForm.elements.password.value='';smtpForm.elements.password.placeholder='Сохранён · пустое поле оставит его без изменений';$('#smtp-test').disabled=false;smtpStatus.textContent=`SMTP сохранён · версия ${formMailSettings.version}`;}catch(error){smtpStatus.textContent=error.message;}finally{button.disabled=false;}};
    $('#smtp-test').onclick=async()=>{const button=$('#smtp-test'),to=$('#smtp-test-to').value.trim();if(!to||!$('#smtp-test-to').checkValidity()){$('#smtp-test-to').reportValidity();return;}button.disabled=true;smtpStatus.textContent='Отправляем тестовое письмо…';try{await api(`/projects/${id}/form-mail/test`,'POST',{version:formMailSettings.version,to});smtpStatus.textContent=`Тестовое письмо отправлено на ${to}.`;}catch(error){smtpStatus.textContent=error.message;}finally{button.disabled=false;}};
  }
  const aiSettingsForm=$('#ai-settings-form');
  if(aiSettingsForm){
    const keyInput=aiSettingsForm.elements.apiKey,nameInput=aiSettingsForm.elements.profileName,modelInput=aiSettingsForm.elements.model,fallbackModelInput=aiSettingsForm.elements.fallbackModel,saveButton=$('#ai-profile-save'),status=$('#ai-settings-status'),profileList=$('#ai-profile-list'),activateButton=$('#ai-profile-activate'),deleteButton=$('#ai-profile-delete');
    let selectedAiProfileId=aiSettings.activeProfileId||(aiSettings.profiles[0]?.id||'');
    const selectedProfile=()=>aiSettings.profiles.find(profile=>profile.id===selectedAiProfileId)||null;
    const renderAiProfiles=()=>{
      const profile=selectedProfile();
      profileList.innerHTML=aiSettings.profiles.map(item=>'<button type="button" data-ai-profile-id="'+esc(item.id)+'" class="ai-profile-card'+(item.id===selectedAiProfileId?' selected':'')+'" aria-pressed="'+(item.id===selectedAiProfileId?'true':'false')+'"><span><strong>'+esc(item.name)+'</strong><small>'+esc(item.model)+(item.fallbackModel?' · запасная: '+esc(item.fallbackModel):'')+'</small></span><span class="status-pill">'+(item.active?'Активен':'Редактировать')+'</span></button>').join('')||'<p class="muted small">Профилей пока нет. Создайте первый профиль справа.</p>';
      nameInput.value=profile?.name||'';modelInput.value=profile?.model||'';fallbackModelInput.value=profile?.fallbackModel||'';keyInput.value='';keyInput.required=!profile;
      keyInput.placeholder=profile?'Ключ сохранён · пустое поле оставит его без изменений':'sk-or-v1-…';
      saveButton.textContent=profile?'Сохранить изменения':'Создать профиль';
      activateButton.hidden=!profile||profile.active;deleteButton.hidden=!profile;$('#ai-profile-test').disabled=!profile;
      $('#ai-settings-heading').textContent=aiSettings.configured?'Активный профиль: '+(aiSettings.profiles.find(item=>item.id===aiSettings.activeProfileId)?.name||'OpenRouter'):'Добавьте профиль OpenRouter';
      $('#ai-settings-intro').textContent=aiSettings.configured?'AI-запросы начинают с выбранного профиля и модели «'+aiSettings.model+'»'+(aiSettings.fallbackModel?' · запасная модель «'+aiSettings.fallbackModel+'».':'.'):'Сохраните несколько профилей с разными API-ключами и моделями. Один профиль будет активным для AI-запросов.';
      status.textContent='Профили AI · версия '+aiSettings.version;
    };
    profileList.onclick=event=>{const button=event.target.closest('[data-ai-profile-id]');if(!button)return;selectedAiProfileId=button.dataset.aiProfileId;renderAiProfiles();};
    $('#ai-profile-add').onclick=()=>{selectedAiProfileId='';renderAiProfiles();nameInput.focus();};
    aiSettingsForm.onsubmit=async event=>{
      event.preventDefault();saveButton.disabled=true;status.textContent='Сохраняем профиль…';
      const editing=selectedProfile(),method=editing?'PUT':'POST',path=editing?'/ai/profiles/'+encodeURIComponent(editing.id):'/ai/profiles';
      try{
        aiSettings=await api('/projects/'+id+path,method,{version:aiSettings.version,name:nameInput.value,apiKey:keyInput.value,model:modelInput.value,fallbackModel:fallbackModelInput.value});
        if(!editing)selectedAiProfileId=aiSettings.activeProfileId;
        renderAiProfiles();status.textContent='Профиль сохранён · версия '+aiSettings.version;
      }catch(error){status.textContent=error.message;}finally{saveButton.disabled=false;}
    };
    $('#ai-profile-test').onclick=async()=>{
      const profile=selectedProfile();if(!profile)return;
      if(nameInput.value!==profile.name||modelInput.value!==profile.model||fallbackModelInput.value!==(profile.fallbackModel||'')||keyInput.value){status.textContent='Сначала сохраните изменения профиля.';return;}
      const button=$('#ai-profile-test');button.disabled=true;status.textContent='Проверяем «'+profile.name+'»…';
      try{const result=await api('/projects/'+id+'/ai/profiles/'+encodeURIComponent(profile.id)+'/test','POST',{version:aiSettings.version});if(selectedAiProfileId===profile.id)status.textContent='«'+profile.name+'»: подключение работает · '+result.resolvedModel+' · '+(result.elapsedMs/1000).toFixed(1)+' с. Это проверка короткого ответа, не полной генерации.';}
      catch(error){if(selectedAiProfileId===profile.id)status.textContent='«'+profile.name+'»: '+error.message;}finally{button.disabled=!selectedProfile();}
    };
    activateButton.onclick=async()=>{
      const profile=selectedProfile();if(!profile)return;activateButton.disabled=true;status.textContent='Выбираем профиль…';
      try{aiSettings=await api('/projects/'+id+'/ai/profiles/'+encodeURIComponent(profile.id)+'/activate','POST',{version:aiSettings.version});renderAiProfiles();status.textContent='Активный профиль обновлён · версия '+aiSettings.version;}
      catch(error){status.textContent=error.message;}finally{activateButton.disabled=false;}
    };
    deleteButton.onclick=async()=>{
      const profile=selectedProfile();if(!profile||!await askConfirmation('Удалить профиль «'+profile.name+'»? Его API-ключ будет удалён.','Удалить профиль'))return;
      deleteButton.disabled=true;status.textContent='Удаляем профиль…';
      try{aiSettings=await api('/projects/'+id+'/ai/profiles/'+encodeURIComponent(profile.id),'DELETE',{version:aiSettings.version});selectedAiProfileId=aiSettings.activeProfileId||(aiSettings.profiles[0]?.id||'');renderAiProfiles();status.textContent='Профиль удалён · версия '+aiSettings.version;}
      catch(error){status.textContent=error.message;}finally{deleteButton.disabled=false;}
    };
    renderAiProfiles();
    const skillForm=$('#ai-skill-form'),skillList=$('#ai-skill-list'),skillStatus=$('#ai-skill-status');
    const skillIdInput=skillForm.elements.skillId,skillNameInput=skillForm.elements.skillName,skillContentInput=skillForm.elements.content,skillChecksInput=skillForm.elements.manualChecks;
    const skillSave=$('#ai-skill-save'),skillActivate=$('#ai-skill-activate'),skillDelete=$('#ai-skill-delete');
    const selectedSkill=()=>aiSkillLibrary.skills.find(skill=>skill.id===skillIdInput.value)||null;
    const renderAiSkills=()=>{
      skillList.innerHTML=aiSkillLibrary.skills.map(skill=>`<button type="button" data-ai-skill-id="${esc(skill.id)}" class="ai-profile-card${skill.id===skillIdInput.value?' selected':''}" aria-pressed="${skill.id===skillIdInput.value}"><span><strong>${esc(skill.name)}</strong><small>${skill.characterCount.toLocaleString('ru-RU')} символов · ${skill.manualChecks.length} пунктов проверки</small></span><span class="status-pill">${skill.active?'Активен':'Редактировать'}</span></button>`).join('')||'<p class="muted small">Skills ещё не добавлены. Загрузите Markdown или вставьте текст справа.</p>';
      const skill=selectedSkill();skillActivate.hidden=!skill||skill.active;skillDelete.hidden=!skill;
      $('#ai-skills-version').textContent=`Библиотека · версия ${aiSkillLibrary.version}`;
    };
    const clearAiSkillForm=()=>{invalidateAnalysis();skillForm.reset();skillIdInput.value='';skillActivate.hidden=true;skillDelete.hidden=true;skillSave.textContent='Сохранить Skill';renderAiSkills();};
    skillList.onclick=async event=>{
      const button=event.target.closest('[data-ai-skill-id]');if(!button)return;
      invalidateAnalysis();skillStatus.textContent='Загружаем Skill…';
      try{const skill=await api(`/projects/${id}/ai/skills/${encodeURIComponent(button.dataset.aiSkillId)}`);skillIdInput.value=skill.id;skillNameInput.value=skill.name;skillContentInput.value=skill.content;skillChecksInput.value=skill.manualChecks.join('\n');skillSave.textContent='Сохранить изменения';renderAiSkills();skillStatus.textContent=`Skill загружен · библиотека версии ${aiSkillLibrary.version}`;}
      catch(error){skillStatus.textContent=error.message;}
    };
    $('#ai-skill-add').onclick=()=>{clearAiSkillForm();skillNameInput.focus();};
    $('#ai-skill-file').onchange=async event=>{
      const file=event.target.files?.[0];if(!file)return;invalidateAnalysis();
      try{if(file.size>65536)throw new Error('Размер файла превышает 64 КБ.');const text=await file.text();if([...text].length>16000)throw new Error('Файл длиннее 16 000 символов.');skillContentInput.value=text;if(!skillNameInput.value.trim())skillNameInput.value=file.name.replace(/\.(?:md|markdown|txt)$/i,'');skillStatus.textContent=`Файл «${file.name}» прочитан. Нажмите «Сохранить Skill», чтобы добавить его в проект.`;}
      catch(error){skillStatus.textContent=error.message;event.target.value='';}
    };
    let skillAnalysisDraft=null;
    const analysisPreview=$('#ai-skill-analysis');
    const invalidateAnalysis=()=>{skillAnalysisDraft=null;analysisPreview.replaceChildren();analysisPreview.hidden=true;};
    skillContentInput.addEventListener('input',invalidateAnalysis);
    skillChecksInput.addEventListener('input',invalidateAnalysis);
    $('#ai-skill-analyze').onclick=async()=>{
      const button=$('#ai-skill-analyze'),source=skillContentInput.value,sourceSkillId=skillIdInput.value,sourceChecks=skillChecksInput.value;
      button.disabled=true;invalidateAnalysis();skillStatus.textContent='Разбираем требования Skill через OpenRouter…';
      try{
        const draft=await api(`/projects/${id}/ai/skills/analyze`,'POST',{version:aiSkillLibrary.version,content:source});
        if(skillContentInput.value!==source||skillIdInput.value!==sourceSkillId||skillChecksInput.value!==sourceChecks)throw new Error('Форма изменилась во время разбора. Повторите запрос для текущего текста.');
        skillAnalysisDraft={draft,source,sourceSkillId,sourceChecks};
        const heading=document.createElement('h4');heading.textContent='Проверьте извлечённые требования';analysisPreview.append(heading);
        const warning=document.createElement('p');warning.className='muted small';warning.textContent='AI мог пропустить или неверно истолковать требования. Сверьте каждую цитату и полноту списка с исходным Skill. Автоматические правила измеряют только объявленные свойства HTML/CSS.';analysisPreview.append(warning);
        for(const item of draft.items){const row=document.createElement('article'),title=document.createElement('strong'),quote=document.createElement('blockquote');title.textContent=`${item.id} · ${item.label} · ${item.mode==='automatic'?'автоматически':'вручную'}`;quote.textContent=item.sourceQuote;row.append(title,quote);if(item.mode==='automatic'){const rule=document.createElement('pre');rule.textContent=item.ruleJson;row.append(rule);}analysisPreview.append(row);}
        const review=document.createElement('label'),check=document.createElement('input');check.type='checkbox';review.append(check,document.createTextNode('Я сверил полноту и точность разбора с исходным Skill.'));
        const apply=document.createElement('button');apply.type='button';apply.textContent='Перенести черновик в форму';apply.disabled=true;check.onchange=()=>{apply.disabled=!check.checked;};
        apply.onclick=()=>{if(!check.checked||!skillAnalysisDraft)return;if(skillContentInput.value!==source||skillIdInput.value!==sourceSkillId||skillChecksInput.value!==sourceChecks){invalidateAnalysis();skillStatus.textContent='Форма изменилась. Повторите разбор.';return;}const merged=[...new Set([...sourceChecks.split(/\r?\n/).map(text=>text.trim()).filter(Boolean),...draft.manualChecks])];if(merged.length>12){skillStatus.textContent='Объединённый ручной чек-лист длиннее 12 пунктов. Сократите его и повторите разбор.';return;}skillContentInput.value=draft.content;skillChecksInput.value=merged.join('\n');invalidateAnalysis();skillStatus.textContent='Черновик перенесён в форму. Проверьте текст и нажмите «Сохранить Skill».';};
        analysisPreview.append(review,apply);analysisPreview.hidden=false;skillStatus.textContent=`Разбор готов · ${draft.items.length} требований · ${draft.metadata?.resolvedModel||'модель не указана'}. Ничего не сохранено.`;
      }catch(error){skillStatus.textContent=error.message;}finally{button.disabled=false;}
    };
    skillForm.onsubmit=async event=>{
      event.preventDefault();skillSave.disabled=true;skillStatus.textContent='Сохраняем Skill в проекте…';
      const editing=selectedSkill(),existingSkillIds=new Set(aiSkillLibrary.skills.map(skill=>skill.id)),method=editing?'PUT':'POST',path=editing?`/ai/skills/${encodeURIComponent(editing.id)}`:'/ai/skills';
      const manualChecks=skillChecksInput.value.split(/\r?\n/).map(item=>item.trim()).filter(Boolean);
      try{
        aiSkillLibrary=await api(`/projects/${id}${path}`,method,{version:aiSkillLibrary.version,name:skillNameInput.value,content:skillContentInput.value,manualChecks});
        if(!editing){const created=aiSkillLibrary.skills.find(skill=>!existingSkillIds.has(skill.id));if(created)skillIdInput.value=created.id;}
        if(skillIdInput.value){const saved=await api(`/projects/${id}/ai/skills/${encodeURIComponent(skillIdInput.value)}`);skillNameInput.value=saved.name;skillContentInput.value=saved.content;skillChecksInput.value=saved.manualChecks.join('\n');}
        invalidateAnalysis();skillSave.textContent='Сохранить изменения';renderAiSkills();skillStatus.textContent=`Skill сохранён · версия ${aiSkillLibrary.version}`;
      }catch(error){skillStatus.textContent=error.message;}
      finally{skillSave.disabled=false;}
    };
    skillActivate.onclick=async()=>{
      const skill=selectedSkill();if(!skill)return;skillActivate.disabled=true;skillStatus.textContent='Активируем Skill…';
      try{aiSkillLibrary=await api(`/projects/${id}/ai/skills/${encodeURIComponent(skill.id)}/activate`,'POST',{version:aiSkillLibrary.version});renderAiSkills();skillStatus.textContent=`«${skill.name}» будет выбран по умолчанию в редакторе · версия ${aiSkillLibrary.version}`;}
      catch(error){skillStatus.textContent=error.message;}finally{skillActivate.disabled=false;}
    };
    skillDelete.onclick=async()=>{
      const skill=selectedSkill();if(!skill||!await askConfirmation(`Удалить Skill «${skill.name}»?`,'Удалить Skill'))return;
      skillDelete.disabled=true;skillStatus.textContent='Удаляем Skill…';
      try{aiSkillLibrary=await api(`/projects/${id}/ai/skills/${encodeURIComponent(skill.id)}`,'DELETE',{version:aiSkillLibrary.version});clearAiSkillForm();skillStatus.textContent=`Skill удалён · версия ${aiSkillLibrary.version}`;}
      catch(error){skillStatus.textContent=error.message;}finally{skillDelete.disabled=false;}
    };
    if(aiSkillLibrary.error)skillStatus.textContent=aiSkillLibrary.error;
    renderAiSkills();
  }
  $('#logout').onclick=async()=>{await api('/logout','POST',{});session=await api('/session');auth();};
}
async function openEditor(id,pageId=null) {
  current=await api(`/projects/${id}`);dirty=false;savedEditorSnapshot=null;editorPublication=null;try{editorPublication=(await api(`/projects/${id}/publication`)).publication;}catch{} loading=true;
  if(current.slug)projectSlugs.set(id,current.slug);
  const { default: grapesjs } = await import('grapesjs');
  saving=false;saveAgain=false;conflicted=false;savePromise=undefined;clearTimeout(saveTimer);saveTimer=undefined;
  app.innerHTML=`<main class="editor-shell" data-open-panel="none" data-inspector-open="true"><header class="editor-top"><button id="back" class="brand brand-compact" aria-label="К страницам проекта" title="К страницам проекта">${mark}</button><span id="project-name" class="project-name" role="button" tabindex="0" title="Дважды щёлкните, чтобы изменить название проекта" aria-label="Переименовать проект: ${esc(current.name)}">${esc(current.name)}</span><span class="divider"></span><div id="page-switcher" class="page-switcher"><button id="page-switcher-trigger" type="button" class="page-switcher-trigger" aria-label="Страница" aria-haspopup="listbox" aria-expanded="false" aria-controls="page-menu"><span id="page-switcher-label">Страница</span><span class="page-switcher-chevron" aria-hidden="true">${tablerIcon('chevron-down')}</span></button><div id="page-menu" class="page-switcher-menu" role="listbox" aria-label="Страницы проекта" hidden></div></div><button id="add-page" class="icon-button" title="Добавить страницу">+</button><div class="device-switch" role="group" aria-label="Размер экрана"><button data-device="Desktop" class="active">Desktop</button><button data-device="Tablet">Tablet</button><button data-device="Mobile">Mobile</button></div><div class="top-actions"><button id="undo" title="Отменить (⌘Z / Ctrl+Z)" aria-label="Отменить" aria-keyshortcuts="Control+Z Meta+Z">↶</button><button id="redo" title="Повторить (⌘⇧Z / Ctrl+Shift+Z)" aria-label="Повторить" aria-keyshortcuts="Control+Shift+Z Meta+Shift+Z Control+Y">↷</button><button id="history">История</button><button id="theme" class="icon-button" aria-label="Настройки" title="Настройки страницы"></button><button id="export">Экспорт JSON</button><button id="static-export" title="Скачать все сохранённые страницы, SEO и стили в статическом ZIP">ZIP сайта</button><button id="publish-editor" class="secondary-link" title="Опубликовать сохранённую ревизию проекта">Опубликовать</button><button id="save" class="primary" title="Сохранить (⌘S / Ctrl+S)" aria-keyshortcuts="Control+S Meta+S">Сохранить</button></div></header><nav class="mobile-workspace-tabs" role="group" aria-label="Панели редактора"><button type="button" data-mobile-panel="canvas" aria-pressed="true">Холст</button><button type="button" data-mobile-panel="library" aria-pressed="false">Секции</button><button type="button" data-mobile-panel="inspector" aria-pressed="false">Свойства</button></nav><nav class="editor-floating-tools" aria-label="Инструменты страницы"><button id="toggle-layers" type="button" data-open-panel="layers" aria-label="Слои" title="Слои" aria-pressed="false"><span class="layers-icon" aria-hidden="true">≡</span></button><button id="toggle-library" type="button" data-open-panel="library" aria-label="Добавить секцию" title="Добавить секцию" aria-pressed="false">+</button></nav><aside class="editor-left"><div class="panel-tabs"><button data-panel="library" class="active">Секции</button><button data-panel="layers">Слои</button></div><section id="library"><div class="panel-heading panel-heading-with-close"><div><h2>Библиотека</h2><p>Добавьте секцию на страницу</p></div><button type="button" class="panel-close" data-close-panel aria-label="Закрыть библиотеку">×</button></div><div class="block-list">${blocks.map((b,i)=>`<button data-block="${b.id}" class="block-card" title="${esc(b.label+' — '+b.description)}"><div class="block-media" aria-hidden="true">${blockMedia(b.id)}</div><strong>${b.label}</strong><small>${b.description}</small></button>`).join('')}</div><div class="library-foot">Структурные компоненты<br>Каждый элемент можно изменить</div></section><section id="layers" hidden><div class="panel-heading panel-heading-with-close"><div><h2>Слои</h2><p>Структура выбранной страницы</p></div><button type="button" class="panel-close" data-close-panel aria-label="Закрыть слои">×</button></div><div id="layer-manager"></div></section></aside><section class="editor-center"><div class="canvas-toolbar"><span id="page-label">ГЛАВНАЯ</span><span id="canvas-size">Адаптивный холст</span><button id="preview">Предпросмотр ↗</button></div><div class="canvas-wrap"><div id="gjs"></div><div class="canvas-empty" id="canvas-empty"><div class="empty-shapes"><i></i><i></i><i></i></div><p>Здесь начинается ваш сайт</p><span>Добавьте первый экран из библиотеки слева</span><button id="start-hero">+ Добавить Hero</button></div></div></section><aside class="editor-right"><div class="panel-heading panel-heading-with-close"><div><p class="eyebrow">ИНСПЕКТОР</p><h2 id="selected-name">Выберите элемент</h2></div><button type="button" id="close-inspector" class="panel-close" aria-label="Закрыть инспектор">×</button></div><div class="inspector-hint" id="selection-hint">Нажмите на элемент в холсте, чтобы изменить его оформление.</div><section id="quick-edit" hidden><div id="text-edit"><label>Текст<textarea id="component-text" rows="3"></textarea></label><button id="apply-text">Применить текст</button></div><div id="photo-credit-edit" class="photo-credit-controls" hidden><label>Копирайт<textarea id="component-photo-credit" rows="2" maxlength="500" placeholder="Фото: Автор · лицензия"></textarea></label><p class="muted small">Показывается под фотографией размером 8 px.</p></div><button type="button" id="edit-custom-code" hidden>Изменить HTML, CSS и JavaScript</button><label>Режим компоновки<select id="layout"><option value="block">Обычный поток</option><option value="flex">Flex</option><option value="grid">Grid</option></select></label><p class="muted small">Меняется только display. Остальные настройки сохраняются; доступна отмена.</p><section class="size-controls" aria-label="Адаптивные размеры"><h3>Размер и ограничения</h3><div class="size-control-grid"><label>Ширина<select id="width-mode" aria-label="Режим ширины"><option value="auto">Авто</option><option value="fit-content">Hug</option><option value="100%">Fill</option><option value="custom" disabled>CSS вручную</option></select></label><label>Высота<select id="height-mode" aria-label="Режим высоты"><option value="auto">Hug</option><option value="100%">Fill</option><option value="custom" disabled>CSS вручную</option></select></label></div><div class="size-constraint-grid"><label>Min. ширина<input id="min-width" type="text" inputmode="text" maxlength="32" placeholder="320px" aria-label="Минимальная ширина"></label><label>Max. ширина<input id="max-width" type="text" inputmode="text" maxlength="32" placeholder="80rem" aria-label="Максимальная ширина"></label><label>Min. высота<input id="min-height" type="text" inputmode="text" maxlength="32" placeholder="240px" aria-label="Минимальная высота"></label><label>Max. высота<input id="max-height" type="text" inputmode="text" maxlength="32" placeholder="none" aria-label="Максимальная высота"></label></div><button type="button" id="apply-size-constraints">Применить min/max</button><p class="muted small">Hug следует содержимому; Fill задаёт 100%. Размеры: px, %, rem, em, vw, vh. Пустое поле снимает ограничение.</p></section></section><section id="ai-component-editor" class="ai-component-editor" aria-labelledby="ai-component-title"><p class="eyebrow">AI-ПРАВКА</p><h3 id="ai-component-title">Изменить элемент</h3><p id="ai-component-target" class="muted small">Выберите элемент на холсте.</p><label class="ai-component-prompt-label">Что изменить?<textarea id="ai-component-prompt" rows="3" maxlength="2000" placeholder="Например: сократи текст и сделай заголовок выразительнее"></textarea></label><p class="muted small ai-component-disclosure">В OpenRouter будут отправлены запрос и безопасное описание выбранного элемента с его вложенными элементами. Остальная страница не отправляется. Изменение появится только после вашего подтверждения.</p><button type="button" class="primary" id="ai-component-propose" disabled>Предложить правку</button><p id="ai-component-status" class="muted small" role="status" aria-live="polite">Выберите элемент, чтобы подготовить AI-правку.</p><div id="ai-component-preview" class="ai-component-preview" hidden><h4>Предлагаемые изменения</h4><div id="ai-component-changes" class="ai-component-changes"></div><button type="button" class="primary" id="ai-component-apply">Применить и сохранить</button></div></section><div id="traits"></div><div id="flex-controls" class="flex-controls" hidden>${flexControlMarkup}</div><div id="styles"></div><section class="ai-plan-panel" aria-labelledby="ai-plan-panel-title"><p class="eyebrow">AI-ПЛАНИРОВАНИЕ</p><h3 id="ai-plan-panel-title">Структура сайта</h3><p>Перед открытием AI незаписанные правки сохраняются. Секции не добавляются до вашего подтверждения.</p><button type="button" id="ai-plan-open" class="primary">Составить план</button><p id="ai-panel-status" class="muted small" role="status">Проверим доступность AI-сервера.</p></section></aside><footer class="editor-status"><span><i class="status-dot"></i> <span id="save-status">Ревизия ${current.version} · Сохранено на сервере</span></span><span id="notice" role="status"></span><span class="status-actions"><button id="reload-server" hidden>Загрузить версию сервера</button><span class="version-label editor-version" aria-label="Версия продукта ${productVersion}">Версия ${productVersion}</span></span></footer></main><dialog id="confirm-dialog"><form method="dialog"><p id="confirm-message"></p><div class="dialog-actions"><button value="cancel">Отмена</button><button id="confirm-submit" class="primary" value="confirm">Продолжить</button></div></form></dialog><dialog id="history-dialog"><div class="history-head"><div><p class="eyebrow">СОХРАНЁННЫЕ СОСТОЯНИЯ</p><h2>История проекта</h2><p class="muted">Восстановление создаст новую ревизию. Текущая история останется доступной.</p></div><button id="close-history" aria-label="Закрыть">×</button></div><div id="history-list" class="history-list"></div><p id="history-error" role="status"></p></dialog><dialog id="image-dialog" aria-labelledby="image-dialog-title"></dialog><dialog id="page-dialog"><form id="page-form"><h2>Новая страница</h2><label>Название<input name="name" required maxlength="80"></label><div class="dialog-actions"><button type="button" id="cancel-page">Отмена</button><button class="primary">Добавить</button></div></form></dialog><dialog id="preview-dialog"><div class="preview-head"><strong>Предпросмотр страницы</strong><button id="close-preview">Закрыть</button></div><iframe id="preview-frame" sandbox="" title="Предпросмотр сайта"></iframe></dialog><dialog id="custom-code-dialog" aria-labelledby="custom-code-title"><div class="ai-plan-head"><div><p class="eyebrow">ИЗОЛИРОВАННЫЙ БЛОК</p><h2 id="custom-code-title">Custom Code</h2></div><button type="button" id="close-custom-code" aria-label="Закрыть">×</button></div><p class="muted small">HTML, CSS и JavaScript работают в отдельном sandbox iframe с непрозрачным origin. Код не видит DOM редактора или cookies; CSP ограничивает сетевые запросы.</p><label>HTML<textarea id="custom-code-html" rows="6" spellcheck="false"></textarea></label><label>CSS<textarea id="custom-code-css" rows="5" spellcheck="false"></textarea></label><label>JavaScript<textarea id="custom-code-js" rows="5" spellcheck="false"></textarea></label><div class="dialog-actions"><button type="button" id="custom-code-preview">Проверить в песочнице</button><button type="button" class="primary" id="custom-code-save">Сохранить блок</button></div><iframe id="custom-code-preview-frame" title="Предпросмотр кода в изоляции" style="width:100%;height:260px;border:1px solid var(--line);border-radius:8px;margin-top:18px"></iframe><p id="custom-code-status" role="status" class="muted small"></p></dialog><dialog id="ai-plan-dialog" aria-labelledby="ai-plan-title"><div class="ai-plan-head"><div><p class="eyebrow">AI-ПЛАНИРОВАНИЕ</p><h2 id="ai-plan-title">План сайта</h2></div><button type="button" id="close-ai-plan" aria-label="Закрыть">×</button></div><p class="muted ai-disclosure">На OpenRouter будет отправлено только описание из этого поля. Данные проекта и содержимое страницы не передаются. Незаписанные правки редактора сохраняются перед открытием этой формы; новые AI-секции добавятся только после вашего подтверждения.</p><p id="ai-provider-status" class="muted small" role="status">Проверяем настройки AI проекта…</p><a id="ai-settings-link" class="secondary-link" href="${projectUrl(id)}/settings?section=ai" hidden>Настроить ключ в «Настройки сайта → AI Engine»</a><form id="ai-plan-form"><label for="ai-prompt">Опишите сайт, аудиторию и задачу<textarea id="ai-prompt" name="prompt" rows="4" maxlength="4000" required placeholder="Например: сайт семейной кофейни в Алматы, для жителей района, уютный стиль, цель — рассказать о меню и получить заказы"></textarea></label><div class="dialog-actions"><button type="submit" id="ai-generate" class="primary" disabled>Составить план</button></div></form><p id="ai-plan-status" class="muted small" role="status" aria-live="polite"></p><section id="ai-image-slot-chooser" hidden><div id="ai-image-slot-list"></div></section><button type="button" id="ai-job-cancel" hidden>Отменить генерацию</button><section id="ai-job-diagnostics" hidden aria-label="Диагностика генерации"></section><button type="button" id="ai-job-history">История генераций</button><section id="ai-job-history-list" hidden aria-label="История генераций"></section><button type="button" id="ai-job-resume">Восстановить последнюю задачу</button><section id="ai-plan-preview" class="ai-plan-preview" hidden aria-label="Предпросмотр плана"></section></dialog><dialog id="theme-dialog" aria-labelledby="page-settings-title"><div class="page-settings-heading"><h2 id="page-settings-title">Настройки страницы</h2><button type="button" id="close-page-settings" aria-label="Закрыть настройки">×</button></div><nav class="page-settings-tabs" aria-label="Разделы настроек"><button type="button" data-settings-tab="general">Основные</button><button type="button" data-settings-tab="seo">SEO</button><button type="button" data-settings-tab="social">Соцсети</button><button type="button" data-settings-tab="design">Оформление сайта</button></nav><form id="page-settings-form"><section data-settings-panel="general"><label>Название страницы<input name="name" required maxlength="100"></label><label>Адрес страницы<input name="slug" required maxlength="100" pattern="[a-z0-9]+(?:-[a-z0-9]+)*"></label><p class="muted small">Адрес используется при публикации и в ZIP. Главная страница также открывается в корне сайта.</p></section><section data-settings-panel="seo" hidden><label>Заголовок SEO<input name="title" maxlength="70" placeholder="По умолчанию — название страницы"></label><label>Описание SEO<textarea name="description" maxlength="160" rows="3"></textarea></label><label>Индексация<select name="robots"><option value="index">Разрешить</option><option value="noindex">Запретить</option></select></label></section><section data-settings-panel="social" hidden><p class="muted small">Open Graph и Twitter Card. Пустые заголовок и описание используют SEO.</p><label>Заголовок для соцсетей<input name="socialTitle" maxlength="70"></label><label>Описание для соцсетей<textarea name="socialDescription" maxlength="160" rows="3"></textarea></label><label>Изображение для соцсетей<input name="socialImage" type="url" maxlength="2048" pattern="https://.*" placeholder="https://example.com/photo.jpg"></label></section><div class="dialog-actions"><button class="primary" type="submit">Сохранить настройки страницы</button></div><p id="page-settings-status" role="status" class="muted small"></p></form><form id="theme-form" data-settings-panel="design" hidden><p class="eyebrow">ДИЗАЙН-СИСТЕМА ПРОЕКТА</p><h2 id="theme-title">Глобальные токены</h2><p class="muted">Изменения применятся к готовым секциям на всех страницах.</p><div class="theme-color-grid">${themeColorInputs}</div><div class="theme-options"><label>Шрифт заголовков<select name="headingFontFamily">${themeFontOptions}</select></label><label>Шрифт основного текста<select name="bodyFontFamily">${themeFontOptions}</select></label><label>Скругление карточек<select name="radius"><option value="0px">0 px</option><option value="4px">4 px</option><option value="8px">8 px</option><option value="12px">12 px</option><option value="16px">16 px</option><option value="24px">24 px</option><option value="32px">32 px</option></select></label></div><p class="muted small">Токены сохраняются в проекте и входят в историю версий.</p><div class="dialog-actions"><button type="button" id="cancel-theme">Отмена</button><button class="primary">Применить</button></div></form></dialog>`;
  bindProjectTitle($('#project-name'));
  syncSaveButtonState();
  syncPublishButtonState();
  let imagePicker;
  editor=grapesjs.init({assetManager:{custom:{open:props=>imagePicker?.open(props),close:()=>$('#image-dialog').open&&$('#image-dialog').close()}},plugins:[editorPlugins],icons:tablerCoreIcons,container:'#gjs',height:'100%',width:'auto',storageManager:false,undoManager:{trackSelection:false},noticeOnUnload:false,panels:{defaults:[]},allowScripts:false,canvas:{scripts:[],styles:[]},layerManager:{appendTo:'#layer-manager'},traitManager:{appendTo:'#traits'},selectorManager:{componentFirst:true},styleManager:{appendTo:'#styles',sectors:[{name:'Компоновка',open:true,properties:['display','flex-direction','justify-content','align-items','flex-wrap','gap','grid-template-columns']},{name:'Размеры и отступы',open:false,properties:['padding','margin']},{name:'Типографика',open:false,properties:['font-family','font-size','font-weight','line-height','color','text-align']},{name:'Оформление',open:false,properties:['background-color','border','border-radius','opacity']}]},deviceManager:{devices:[{id:'Desktop',name:'Desktop',width:''},{id:'Tablet',name:'Tablet',width:'768px',widthMedia:'992px'},{id:'Mobile',name:'Mobile',width:'375px',widthMedia:'576px'}]}});
  $('#ai-component-editor .ai-component-disclosure').textContent='В OpenRouter отправляются запрос и безопасное описание только выбранного элемента: его текст и локальные стили. Остальная страница не отправляется. Изменение появится только после вашего подтверждения.';
  const componentActions=installComponentActions({editor,confirmDelete:askConfirmation,notify});
  installEditorIcons(editor);
  installBackgroundControls(editor,id,api,notify);
  const viewLink=document.createElement('a');viewLink.id='page-view';viewLink.className='secondary-link';viewLink.title='Просмотр сохранённой страницы в новом окне';viewLink.setAttribute('aria-label','Просмотр');viewLink.target='_blank';viewLink.rel='noopener noreferrer';viewLink.innerHTML=tablerIcon('eye');$('#save').before(viewLink);
  const syncView=()=>{const page=editor.Pages.getSelected();viewLink.href=`/api/projects/${id}/pages/${encodeURIComponent(page?.get('id')||'home')}/view`;};editor.on('page:select',syncView);editor.on('load',syncView);syncView();
  $('#ai-plan-open').textContent='AI-дизайн';
  $('.ai-plan-panel .eyebrow').textContent='AI-ДИЗАЙН';
  $('#ai-plan-panel-title').textContent='Сгенерировать страницу';
  $('.ai-plan-panel>p:not(.eyebrow)').textContent='Опишите идею — получите HTML и CSS, которые можно редактировать в GrapesJS.';
  $('#ai-plan-title').textContent='Дизайн страницы';
  $('#ai-plan-dialog .eyebrow').textContent='AI-ГЕНЕРАЦИЯ';
  $('#ai-generate').textContent='Создать дизайн';
  $('.ai-disclosure').textContent='В OpenRouter отправятся описание из этого поля и инструкции выбранного встроенного профиля. Project Data и содержимое страницы не передаются. Если ответ нарушит формат или проверки профиля, система выполнит одну исправляющую попытку с тем же описанием; повторный провал остановит генерацию. Изображения подбираются автоматически; применение — отдельной кнопкой.';
  const skillProfileLabel=document.createElement('label');skillProfileLabel.className='ai-skill-profile-field';skillProfileLabel.textContent='Профиль генерации';
  const skillProfileSelect=document.createElement('select');skillProfileSelect.id='ai-skill-profile';skillProfileSelect.setAttribute('aria-label','Профиль генерации');
  for(const [value,label] of [['taste','Taste · выразительный дизайн'],['standard','Стандартный дизайн']]){const option=document.createElement('option');option.value=value;option.textContent=label;skillProfileSelect.append(option);}
  skillProfileLabel.append(skillProfileSelect);$('#ai-prompt').closest('label').before(skillProfileLabel);
  let userSkillLibrary={version:0,activeSkillId:null,skills:[]},userSkillOptionsLoaded=false,userSkillLoadError='';
  const updateAIDisclosure=()=>{
    const value=skillProfileSelect.value;
    if(value.startsWith('user:'))$('.ai-disclosure').textContent='После нажатия «Создать дизайн» в OpenRouter отправятся описание и полный текст выбранного Skill с его ручным чек-листом. Project Data и содержимое страницы не передаются. Модель постарается соблюдать Skill, но это не гарантия: результат доступен в предпросмотре.';
    else if(value==='taste')$('.ai-disclosure').textContent='В OpenRouter отправятся описание и встроенный профиль Taste. Project Data и содержимое страницы не передаются. Изображения подбираются автоматически. Посмотрите результат и нажмите «Добавить дизайн».';
    else $('.ai-disclosure').textContent='В OpenRouter отправятся описание и встроенные инструкции стандартного профиля. Project Data и содержимое страницы не передаются. Изображения подбираются автоматически. Применение — отдельной кнопкой.';
    $('.ai-disclosure').textContent+=' Генерация идёт в фоне до 10 минут. Закрытие окна не отменяет запрос и его расход. Задачу можно восстановить; описание, Skill и результат хранятся приватно до 24 часов. Применение — отдельно.';
  };
  const refreshUserSkillOptions=async()=>{
    const previous=skillProfileSelect.value;
    userSkillLibrary=await api(`/projects/${id}/ai/skills`);
    for(const option of [...skillProfileSelect.options])if(option.dataset.userSkillId)option.remove();
    for(const skill of userSkillLibrary.skills){const option=document.createElement('option');option.value='user:'+skill.id;option.dataset.userSkillId=skill.id;option.textContent=`${skill.name}${skill.active?' · активный':''}`;skillProfileSelect.append(option);}
    const previousStillExists=[...skillProfileSelect.options].some(option=>option.value===previous);
    if(!userSkillOptionsLoaded&&userSkillLibrary.activeSkillId)skillProfileSelect.value='user:'+userSkillLibrary.activeSkillId;
    else if(previousStillExists)skillProfileSelect.value=previous;
    else skillProfileSelect.value='taste';
    userSkillOptionsLoaded=true;updateAIDisclosure();
  };
  skillProfileSelect.onchange=updateAIDisclosure;
  updateAIDisclosure();
  editor.Components.addType('image',{model:{defaults:{traits:['alt','src']}}});
  registerSafeRuntimeComponents(editor,{projectId:id,csrf:()=>session.csrf});
  registerZero(editor,{projectId:id,projectSlug:current.slug,beforeOpen:()=>saveDocument(false),isClean:()=>!dirty&&!saving&&!conflicted,notify,onRemote:async message=>{
    if(message.version<=current.version)return;
    if(dirty||saving||conflicted){conflicted=true;clearTimeout(saveTimer);$('#reload-server').hidden=false;$('#save').disabled=true;syncSaveButtonState();$('#save-status').textContent='Zero Block изменён в другом окне';notify('Локальные изменения сохранены в редакторе. Экспортируйте их или загрузите серверную версию.',true);return;}
    const snapshot=JSON.stringify(editor.getProjectData());
    try{const latest=await api(`/projects/${id}`);if(dirty||saving||JSON.stringify(editor.getProjectData())!==snapshot){conflicted=true;clearTimeout(saveTimer);$('#reload-server').hidden=false;$('#save').disabled=true;syncSaveButtonState();notify('Во время синхронизации появились локальные изменения. Разрешите конфликт.',true);return;}if(!sameOutsideZero(current.document,latest.document,message.pageId,message.blockId)||JSON.stringify(current.settings)!==JSON.stringify(latest.settings)){location.reload();return;}
      let target;const find=model=>{if(model.get('type')==='zero-block'&&model.getAttributes().id===message.blockId)target=model;for(const child of model.components().models)find(child);};const remotePage=editor.Pages.getAll().find(p=>p.id===message.pageId);if(remotePage)find(remotePage.getMainComponent());
      if(!target){location.reload();return;}loading=true;editor.UndoManager.skip(()=>target.set('zeroData',serializedZero(latest.document,message.pageId,message.blockId).zeroData));current=latest;editor.clearDirtyCount();savedEditorSnapshot=JSON.stringify(editor.getProjectData());loading=false;dirty=false;$('#save-status').textContent=`Zero Block обновлён · ревизия ${current.version}`;syncSaveButtonState();}catch(error){loading=false;notify(error.message,true);}
  }});
  registerBlocks(editor);
  exposePluginBlocks(editor,$('.block-list'));
  const libraryFilter=document.createElement('div');libraryFilter.className='library-filter';libraryFilter.innerHTML=`<label><span class="visually-hidden">Поиск секций и элементов</span><input id="block-search" type="search" placeholder="Поиск секций и элементов" autocomplete="off"></label><div class="library-categories" role="group" aria-label="Категории библиотеки">${[['all','Все'],['Секции','Секции'],['Элементы','Элементы'],['Компоновка','Компоновка'],['Медиа','Медиа'],['Навигация','Навигация'],['Обложки','Обложки'],['Карточки','Карточки'],['Статьи','Статьи'],['Галереи','Галереи'],['Цены','Цены'],['Команда','Команда'],['Формы','Формы']].map(([id,label],index)=>`<button type="button" data-library-category="${id}" aria-pressed="${index===0}">${label}</button>`).join('')}</div>`;
  $('#library .panel-heading').after(libraryFilter);
  const libraryEmpty=document.createElement('p');libraryEmpty.className='library-empty';libraryEmpty.textContent='Ничего не найдено. Измените поиск или категорию.';libraryEmpty.hidden=true;$('.block-list').after(libraryEmpty);
  const filterLibrary=()=>{const query=$('#block-search').value.trim().toLocaleLowerCase('ru'),category=$('.library-categories [aria-pressed="true"]')?.dataset.libraryCategory||'all';let visible=0;for(const card of document.querySelectorAll('.block-card')){const matchesCategory=category==='all'||card.dataset.category===category,matchesQuery=!query||card.textContent.toLocaleLowerCase('ru').includes(query);card.hidden=!(matchesCategory&&matchesQuery);if(!card.hidden)visible++;}libraryEmpty.hidden=visible>0;};
  for(const card of document.querySelectorAll('.block-card'))card.dataset.category ||= blockCategory(card.dataset.block);
  $('#block-search').oninput=filterLibrary;
  for(const button of document.querySelectorAll('[data-library-category]'))button.onclick=()=>{for(const sibling of document.querySelectorAll('[data-library-category]'))sibling.setAttribute('aria-pressed',String(sibling===button));filterLibrary();};
  const syncThemeForm=()=>{
    const wrapper=editor.Pages.getSelected()?.getMainComponent();
    const tokens=normalizeDesignTokens(current.settings?.designTokens||designTokensFromStyle(wrapper?.getStyle()||{}));
    for(const field of TOKEN_FIELDS){const control=$(`#theme-form [name="${field.name}"]`);if(control)control.value=tokens[field.name];}
    syncColorInputs($('#theme-form'));
  };
  let aiDesignState=null,aiPreviewRenderId=0,aiComponentProposal=null,aiComponentTargetId=null,aiComponentTargetModel=null,aiComponentTargetPath=null;
  const syncAIApply=()=>{const button=$('#ai-plan-apply');if(button)button.disabled=isAIDesignReadOnly()||Boolean(aiDesignState?.photosPending);};
  const isAIDesignReadOnly=()=>!aiDesignState||aiDesignState.baseVersion!==current.version||aiDesignState.design.contactAudit?.status!=='passed';
  const aiPreviewObjectUrls=new Map();
  const clearAIPreviewUrls=()=>{aiPreviewRenderId++;for(const url of aiPreviewObjectUrls.values())URL.revokeObjectURL(url);aiPreviewObjectUrls.clear();};
  const getAIPreviewUrl=async asset=>{
    if(asset.kind==='commons')return asset.url;
    if(aiPreviewObjectUrls.has(asset.id))return aiPreviewObjectUrls.get(asset.id);
    const response=await fetch(asset.url,{credentials:'same-origin'});if(!response.ok)throw new Error('Не удалось загрузить изображение из библиотеки проекта.');
    const url=URL.createObjectURL(await response.blob());aiPreviewObjectUrls.set(asset.id,url);return url;
  };
  const setHeroBackground=(slot,url)=>{const hero=slot.closest('.ai-hero-background');if(!hero)return false;hero.style.setProperty('background-image',backgroundValue(url,'#000000',0.55));hero.style.setProperty('background-size','cover');hero.style.setProperty('background-position','center');hero.style.setProperty('background-repeat','no-repeat');slot.classList.remove('ai-image-slot');slot.style.display='none';return true;};
  const updateAIDesignFrame=async()=>{
    if(!aiDesignState)return;const frame=$('#ai-design-preview-frame');if(!frame)return;
    const renderId=++aiPreviewRenderId,design=aiDesignState.design,markup=new DOMParser().parseFromString(design.html,'text/html');
    for(const slot of markup.querySelectorAll('[data-image-slot]')){
      const asset=aiDesignState.assets.find(item=>item.id===aiDesignState.imageAssignments[slot.dataset.imageSlot]);if(!asset){removeUnassignedImageSlot(slot);continue;}
      if(slot.dataset.imageSlot==='hero'&&slot.closest('.ai-hero-background')){const source=await getAIPreviewUrl(asset);const hero=slot.closest('.ai-hero-background');hero.style.backgroundImage=`linear-gradient(rgba(0, 0, 0, 0.55), rgba(0, 0, 0, 0.55)), url("${source}")`;hero.style.backgroundSize='cover';hero.style.backgroundPosition='center';slot.classList.remove('ai-image-slot');slot.style.display='none';appendAIPhotoCredit(slot,asset);continue;}
      const image=markup.createElement('img');image.src=await getAIPreviewUrl(asset);image.alt=aiDesignState.imageAlt[slot.dataset.imageSlot]||'';slot.replaceChildren(image);appendAIPhotoCredit(slot,asset);
    }
    if(renderId!==aiPreviewRenderId||!frame.isConnected)return;
    const csp="default-src 'none'; script-src 'none'; connect-src 'none'; img-src data: blob: https://upload.wikimedia.org https://thumb.wikimedia.org; font-src 'none'; style-src 'unsafe-inline'; object-src 'none'; base-uri 'none'; form-action 'none'";
    // Match the inherited typography of the selected native page, not the host UI.
    const rootElement=editor.Pages.getSelected()?.getMainComponent()?.getEl();
    const typography=document.createElement('div');
    if(rootElement){const computed=editor.Canvas.getWindow().getComputedStyle(rootElement);for(const property of ['font-family','font-size','font-weight','font-style','line-height','letter-spacing','color'])typography.style.setProperty(property,computed.getPropertyValue(property));}
    const inheritedTypography=typography.style.cssText.replace(/</g,'\\3c ');
    const theme=currentDesignTokens();
    const headingStyle=document.createElement('div');headingStyle.style.fontFamily=theme.headingFontFamily;headingStyle.style.fontWeight=theme.headingFontWeight;headingStyle.style.lineHeight=theme.headingLineHeight;
    const headingDefaults=Array.from({length:6},(_,index)=>{headingStyle.style.fontSize=theme[`fontSizeH${index+1}`];return `h${index+1}{${headingStyle.style.cssText.replace(/</g,'\\3c ')}}`;}).join('');
    frame.srcdoc='<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="Content-Security-Policy" content="'+csp+'"><style>*,*::before,*::after{box-sizing:border-box}body{margin:0;'+inheritedTypography+'}'+headingDefaults+design.css+'</style></head><body>'+markup.body.innerHTML+'</body></html>';
  };
  const findAIStockPhoto=async(slotId,query)=>{
    const state=aiDesignState;if(!state||isAIDesignReadOnly())return;
    state.photoQueries??={};state.photoQueries[slotId]=query;
    const result=await api(`/projects/${id}/stock-photos?q=${encodeURIComponent(query)}`);
    if(aiDesignState!==state)return;
    const photos=result.photos||[];
    for(const photo of photos)if(!state.assets.some(item=>item.id===photo.id))state.assets.push(photo);
    const needsReview=photos.some(photo=>photo.searchBroadened);
    if(photos.length&&!needsReview){state.imageAssignments[slotId]=photos[0].id;state.imageAlt[slotId]=query;}
    renderAIImageSlots();await updateAIDesignFrame();
    state.photoNotice=needsReview?'Подходящего фото не найдено. Дизайн подготовлен без этого изображения.':photos.length?'Изображения подобраны автоматически.':'Подходящее фото не найдено. Дизайн подготовлен без этого изображения.';$('#ai-plan-status').textContent=state.photoNotice;
  };
  const renderAIImageSlots=()=>{ $('#ai-image-slot-chooser').hidden=true; $('#ai-image-slot-list').replaceChildren(); };
  const renderAIDesign=(design,metadata)=>{
    const preview=$('#ai-plan-preview');preview.replaceChildren();
    const facts=document.createElement('div');facts.className='ai-plan-facts';
    const title=document.createElement('p');title.textContent=design.siteType;facts.append(title);
    for(const [label,value] of [['Аудитория',design.audience],['Цель',design.goal],['Стиль',design.style]]){
      const row=document.createElement('p'),caption=document.createElement('span');caption.textContent=label;row.append(caption,document.createTextNode(value));facts.append(row);
    }
    const isTaste=metadata?.skillProfile==='taste';
    const isUserSkill=metadata?.skillProfile==='user_skill';
    if(isTaste&&design.concept){const row=document.createElement('p'),caption=document.createElement('span');caption.textContent='Идея';row.append(caption,document.createTextNode(design.concept));facts.append(row);}
    if(isUserSkill&&metadata?.userSkillName){const row=document.createElement('p'),caption=document.createElement('span');caption.textContent='Skill';row.append(caption,document.createTextNode(metadata.userSkillName));facts.append(row);}
    preview.append(facts);
    const audit=design.skillAudit;
    if((isTaste&&audit?.profile==='taste')||(isUserSkill&&audit?.checks?.length)){
      const checks=document.createElement('section');checks.className='ai-taste-audit';checks.setAttribute('aria-label','Автоматические проверки Skill');
      const heading=document.createElement('h3');heading.textContent='Проверки структуры';checks.append(heading);
      const list=document.createElement('ul');
      for(const item of audit.checks||[]){const row=document.createElement('li');row.dataset.status=item.status==='passed'?'passed':'failed';row.textContent=(item.status==='passed'?'✓ ':'! ')+(item.id?item.id+' · ':'')+item.label;if(item.evidence){const evidence=document.createElement('small');evidence.textContent=' · '+JSON.stringify(item.evidence);row.append(evidence);}list.append(row);}
      checks.append(list);
      const note=document.createElement('p');note.className='muted small';note.textContent=isUserSkill?'Структурные правила Skill проверены автоматически.':'Структура страницы проверена автоматически.';checks.append(note);preview.append(checks);
    }
    const frame=document.createElement('iframe');frame.id='ai-design-preview-frame';frame.className='ai-design-preview-frame';frame.title='Предпросмотр AI-дизайна';frame.setAttribute('sandbox','');
    const frameViewport=document.createElement('div');frameViewport.className='ai-skill-preview-scroll';frameViewport.append(frame);preview.append(frameViewport);
    {
      const viewNote=document.createElement('p');viewNote.className='muted small ai-taste-view-note';viewNote.textContent='Предпросмотр на компьютере, планшете и телефоне.';preview.append(viewNote);
      const viewportControls=document.createElement('div');viewportControls.className='ai-taste-viewport-controls';viewportControls.setAttribute('role','group');viewportControls.setAttribute('aria-label','Размер предпросмотра');
      const viewportWidths={desktop:1440,tablet:768,mobile:375};
      for(const [name,width] of Object.entries(viewportWidths)){const button=document.createElement('button');button.type='button';button.dataset.viewport=name;button.textContent=(name==='desktop'?'Desktop':name==='tablet'?'Tablet':'Mobile')+' · '+width+' px';button.setAttribute('aria-pressed','false');button.onclick=()=>{frame.style.width=width+'px';frame.style.maxWidth='none';for(const item of viewportControls.querySelectorAll('[data-viewport]'))item.setAttribute('aria-pressed',String(item===button));};viewportControls.append(button);}
      const expandButton=document.createElement('button');expandButton.type='button';expandButton.textContent='Расширить окно';expandButton.setAttribute('aria-pressed','false');expandButton.onclick=()=>{const expanded=$('#ai-plan-dialog').classList.toggle('ai-preview-expanded');expandButton.setAttribute('aria-pressed',String(expanded));expandButton.textContent=expanded?'Обычное окно':'Расширить окно';};viewportControls.append(expandButton);
      preview.insertBefore(viewportControls,frameViewport);
    }
    renderAIImageSlots();updateAIDesignFrame().catch(error=>{$('#ai-plan-status').textContent=error.message;});
    const contactsNote=document.createElement('p');contactsNote.className='muted small';contactsNote.textContent=design.contactAudit?.status==='passed'?'Контакты и внешние ссылки проверены автоматически.':'Контакты этого старого результата автоматически не проверены.';preview.append(contactsNote);
    const note=document.createElement('p');note.className='muted small';note.textContent='Секций: '+design.sectionCount+'. Фото подбираются автоматически с сохранением автора и лицензии. Изображения можно изменить в редакторе.';preview.append(note);
    const usage=metadata?.usage||{},usageParts=[];
    for(const [key,label] of [['promptTokens','входные токены'],['completionTokens','выходные токены'],['totalTokens','всего токенов']])if(Number.isInteger(usage[key])&&usage[key]>=0)usageParts.push(label+': '+usage[key]);
    if(typeof usage.costUsd==='number'&&Number.isFinite(usage.costUsd)&&usage.costUsd>=0)usageParts.push('стоимость: $'+usage.costUsd.toFixed(6));
    const photoUsage=metadata?.photoSelectionUsage;if(photoUsage){if(Number.isInteger(photoUsage.totalTokens))usageParts.push('подбор фото: '+photoUsage.totalTokens+' токенов');if(typeof photoUsage.costUsd==='number'&&Number.isFinite(photoUsage.costUsd)&&photoUsage.costUsd>=0)usageParts.push('подбор фото: $'+photoUsage.costUsd.toFixed(6));}
    const model=typeof metadata?.resolvedModel==='string'?metadata.resolvedModel:'модель не указана';
    const meta=document.createElement('p');meta.className='muted small ai-plan-metadata';meta.textContent=(metadata?.resolvedProfileName?metadata.resolvedProfileName+' · ':'')+model+(usageParts.length?' · '+usageParts.join(' · '):'');preview.append(meta);
    const actions=document.createElement('div');actions.className='dialog-actions';
    const apply=document.createElement('button');apply.type='button';apply.id='ai-plan-apply';apply.className='primary';apply.textContent='Добавить дизайн · '+design.sectionCount+' секций';apply.onclick=applyAIDesign;
    apply.disabled=isAIDesignReadOnly()||Boolean(aiDesignState?.photosPending);
    if(isAIDesignReadOnly())apply.textContent='Только просмотр · требуется новая генерация';
    actions.append(apply);preview.append(actions);preview.hidden=false;
  };
  const buildAIDesignHtml=()=>{
    const markup=new DOMParser().parseFromString(aiDesignState.design.html,'text/html');
    for(const slot of markup.querySelectorAll('[data-image-slot]')){
      const slotId=slot.dataset.imageSlot,asset=aiDesignState.assets.find(item=>item.id===aiDesignState.imageAssignments[slotId]);slot.removeAttribute('data-image-slot');slot.replaceChildren();
      if(!asset){removeUnassignedImageSlot(slot);continue;}
      if(slotId==='hero'&&setHeroBackground(slot,asset.url)){appendAIPhotoCredit(slot,asset);continue;}
      const image=markup.createElement('img');image.src=asset.url;image.alt=aiDesignState.imageAlt[slotId]||'';slot.append(image);appendAIPhotoCredit(slot,asset);
    }
    return markup.body.innerHTML;
  };
  const applyAIDesign=async()=>{
    if(!aiDesignState||aiDesignState.photosPending)return;
    if(isAIDesignReadOnly()){$('#ai-plan-status').textContent='Версия проекта изменилась или отсутствует проверка контактов. Создайте новую генерацию.';return;}
    const page=editor.Pages.getAll().find(item=>item.get('id')===aiDesignState.pageId);
    if(!page){notify('Страница предпросмотра больше не существует.',true);return;}
    if(editor.Pages.getSelected()?.get('id')!==aiDesignState.pageId){$('#ai-plan-status').textContent='Выбрана другая страница. Составьте дизайн заново для текущей страницы.';return;}
    if(!await askConfirmation('Добавить '+aiDesignState.design.sectionCount+' сгенерированных секций в конец страницы «'+(page.get('name')||page.get('id'))+'»? Текущие секции сохранятся. Будет создана новая ревизия.','Добавить дизайн'))return;
    const button=$('#ai-plan-apply'),originalData=editor.getProjectData(),version=current.version;button.disabled=true;$('#ai-plan-status').textContent='Импортируем HTML/CSS в редактируемые компоненты…';
    try{
      loading=true;
      const root=page.getMainComponent(),previousCount=root.components().length;
      root.append(buildAIDesignHtml());
      if(root.components().length!==previousCount+1)throw new Error('Редактор не смог разобрать сгенерированную структуру.');
      editor.addStyle(aiDesignState.design.css);
    }catch(error){
      try{editor.loadProjectData(originalData);editor.clearDirtyCount();dirty=false;refreshPages();refreshEmpty();}
      catch(restoreError){$('#ai-plan-status').textContent='Импорт не завершился, а локальное состояние нужно перезагрузить.';button.disabled=false;return;}
      $('#ai-plan-status').textContent=error.message;button.disabled=false;return;
    }finally{loading=false;}
    dirty=true;refreshEmpty();$('#save-status').textContent='Сохраняем AI-дизайн…';$('#ai-plan-status').textContent='Сохраняем редактируемую страницу…';
    try{
      await saveDocument(false);
      if(!dirty&&current.version>version){
        $('#ai-plan-dialog').close();notify('AI-дизайн добавлен · ревизия '+current.version);
      }else{
        $('#ai-plan-status').textContent='Дизайн импортирован в редактор, но сервер не подтвердил сохранение. Проверьте статус редактора; несохранённые изменения можно отменить.';
        notify('AI-дизайн пока не сохранён на сервере.',true);
      }
    }catch(error){
      $('#ai-plan-status').textContent=error.message;notify(error.message,true);
    }finally{
      if($('#ai-plan-apply'))$('#ai-plan-apply').disabled=false;
    }
  };
  const currentDesignTokens=()=>normalizeDesignTokens(current.settings?.designTokens||designTokensFromStyle(editor.Pages.getSelected()?.getMainComponent()?.getStyle()||{}));
  const applyDesignTokens=value=>{
    const tokens=normalizeDesignTokens({...current.settings.designTokens,...value}),variables=designTokensToStyle(tokens);
    current.settings={...current.settings,designTokens:tokens};
    const migrate=component=>{
      const updates={},tagName=String(component.get?.('tagName')||'').toLowerCase(),style=component.getStyle?.()||{};
      for(const [property,previous] of Object.entries(component.getStyle?.()||{})){
        const next=migrateLegacyTokenValue(property,previous,tagName);
        if(next!==previous)updates[property]=next;
      }
      const heading=tagName.match(/^h([1-6])$/)?.[1];
      if(heading){
        const defaults={'font-family':'var(--juzimder-font-heading, Arial, sans-serif)','font-size':tokenValue(`fontSizeH${heading}`),'font-weight':'var(--juzimder-font-weight-heading, 500)','line-height':'var(--juzimder-line-height-heading, 1.15)'};
        for(const [property,next] of Object.entries(defaults)){
          const previous=style[property],element=component.getEl?.();
          const modelHasStyle=element&&editor.Css.getAll().some(rule=>{const selector=rule.selectorsToString();if(!selector.includes('.ai-scope-')||!Object.hasOwn(rule.getStyle(),property))return false;try{return element.matches(selector);}catch{return false;}});
          const managed=typeof previous==='string'&&(previous.includes('--juzimder-font')||previous.includes('--juzimder-line-height'));
          if(modelHasStyle){if(managed)component.removeStyle(property);continue;}
          if(previous===undefined||managed)updates[property]=next;
        }
      }
      if(Object.keys(updates).length)component.addStyle(updates);
      component.components?.().forEach(migrate);
    };
    for(const page of editor.Pages.getAll()){
      const wrapper=page.getMainComponent();
      const rootStyle=wrapper.getStyle()||{},inherited={'font-family':'var(--juzimder-font-body, Arial, sans-serif)','font-size':'var(--juzimder-font-size-base, 16px)','font-weight':'var(--juzimder-font-weight-body, 400)','line-height':'var(--juzimder-line-height-body, 1.6)','letter-spacing':'var(--juzimder-letter-spacing, 0px)'};
      for(const [property,value] of Object.entries(inherited)){const previous=rootStyle[property];if(previous===undefined||typeof previous==='string'&&/(?:--juzimder-font|--juzimder-line-height|--juzimder-letter-spacing)/.test(previous))variables[property]=value;}
      wrapper.addStyle(variables);
      migrate(wrapper);
    }
    syncThemeForm();
  };
  const refreshEmpty=()=>{const page=editor.Pages.getSelected();$('#canvas-empty').hidden=!!page?.getMainComponent()?.components().length;};
  const pageSwitcher=$('#page-switcher'),pageMenu=$('#page-menu'),pageSwitcherTrigger=$('#page-switcher-trigger');
  let pageSelectionTimer=null;
  const closePageMenu=focusTrigger=>{clearTimeout(pageSelectionTimer);pageSelectionTimer=null;pageMenu.hidden=true;pageSwitcherTrigger.setAttribute('aria-expanded','false');if(focusTrigger)pageSwitcherTrigger.focus();};
  const openPageMenu=focusSelected=>{pageMenu.hidden=false;pageSwitcherTrigger.setAttribute('aria-expanded','true');if(focusSelected)pageMenu.querySelector('[role="option"][aria-selected="true"]')?.focus();};
  const selectPageById=pageId=>{const page=editor.Pages.get(pageId);if(!page)return;closePageMenu();editor.Pages.select(page);};
  const refreshPages=()=>{const selected=editor.Pages.getSelected(),pages=editor.Pages.getAll();$('#page-switcher-label').textContent=selected?.get('name')||'Страница';$('#page-label').textContent=selected?.get('name')||'Страница';pageMenu.replaceChildren();for(const page of pages){const option=document.createElement('div'),name=document.createElement('span');option.className='page-switcher-option';option.setAttribute('role','option');option.setAttribute('aria-selected',String(page===selected));option.tabIndex=page===selected?0:-1;option.dataset.pageId=page.id;name.className='page-switcher-option-name';name.textContent=page.get('name')||page.id;option.append(name);if(page===selected){const currentBadge=document.createElement('span');currentBadge.className='page-switcher-current';currentBadge.textContent='Открыта';option.append(currentBadge);}pageMenu.append(option);}refreshEmpty();};
  const cancelPageRename=(option,page,name)=>{loading=true;editor.Pages.get(page)?.set('name',name,{silent:true});loading=false;refreshPages();pageMenu.querySelector(`[data-page-id="${CSS.escape(page)}"]`)?.focus();};
  const commitPageRename=async(pageId,input,option)=>{if(input.dataset.committing==='true')return;const page=editor.Pages.get(pageId),previousName=page?.get('name')||pageId,name=input.value.trim();if(!page)return;if(!name){input.setCustomValidity('Введите название страницы.');input.reportValidity();return;}input.setCustomValidity('');if(name===previousName){refreshPages();pageMenu.querySelector(`[data-page-id="${CSS.escape(pageId)}"]`)?.focus();return;}input.dataset.committing='true';for(const control of option.querySelectorAll('button,input'))control.disabled=true;try{await saveDocument(false);if(dirty||conflicted)throw new Error('Не удалось сохранить текущие изменения. Разрешите конфликт и повторите переименование.');current=await api(`/projects/${id}/pages/${encodeURIComponent(pageId)}`,'POST',{version:current.version,action:'rename',name});loading=true;editor.Pages.get(pageId)?.set('name',name,{silent:true});loading=false;savedEditorSnapshot=JSON.stringify(editor.getProjectData());dirty=false;refreshPages();$('#save-status').textContent=`Сохранено · ревизия ${current.version}`;notify('Название страницы сохранено');pageMenu.querySelector(`[data-page-id="${CSS.escape(pageId)}"]`)?.focus();}catch(error){input.dataset.committing='false';for(const control of option.querySelectorAll('button,input'))control.disabled=false;let message=option.querySelector('.page-rename-error');if(!message){message=document.createElement('span');message.className='page-rename-error';message.setAttribute('role','alert');option.append(message);}message.textContent=error.message;notify(error.message,true);input.focus();}finally{if(input.dataset.committing==='true')delete input.dataset.committing;}};
  const beginPageRename=(option,pageId)=>{const page=editor.Pages.get(pageId);if(!page)return;const previousName=page.get('name')||pageId;option.replaceChildren();option.classList.add('is-renaming');option.setAttribute('role','group');option.setAttribute('aria-label','Переименование страницы');option.removeAttribute('aria-selected');option.tabIndex=-1;const input=document.createElement('input');input.className='page-switcher-rename-input';input.type='text';input.required=true;input.maxLength=100;input.value=previousName;input.setAttribute('aria-label','Новое название страницы');const save=document.createElement('button'),cancel=document.createElement('button');save.type='button';save.className='page-rename-action';save.setAttribute('aria-label','Сохранить название');save.title='Сохранить';save.innerHTML=`${tablerIcon('check')}`;cancel.type='button';cancel.className='page-rename-action';cancel.setAttribute('aria-label','Отменить переименование');cancel.title='Отмена';cancel.innerHTML=`${tablerIcon('x')}`;save.onpointerdown=cancel.onpointerdown=event=>event.preventDefault();save.onclick=()=>void commitPageRename(pageId,input,option);cancel.onclick=()=>cancelPageRename(option,pageId,previousName);input.onkeydown=event=>{if(event.key==='Enter'){event.preventDefault();void commitPageRename(pageId,input,option);}else if(event.key==='Escape'){event.preventDefault();cancelPageRename(option,pageId,previousName);}};option.append(input,save,cancel);input.focus();input.select();};
  editor.on('load',()=>{editor.loadProjectData(current.document.data);if(pageId&&editor.Pages.getAll().some(page=>page.get('id')===pageId))editor.Pages.select(pageId);applyDesignTokens(currentDesignTokens());loading=false;editor.clearDirtyCount();savedEditorSnapshot=JSON.stringify(editor.getProjectData());syncSaveButtonState();refreshPages();syncThemeForm();});
  editor.on('project:loaded',()=>queueMicrotask(syncSaveButtonState));
  editor.on('update',()=>{
    if(!loading){if(aiComponentProposal){aiComponentProposal=null;$('#ai-component-preview').hidden=true;$('#ai-component-changes').replaceChildren();$('#ai-component-status').textContent='Страница изменилась. Составьте AI-правку заново.';}dirty=savedEditorSnapshot!==null&&JSON.stringify(editor.getProjectData())!==savedEditorSnapshot;if(dirty){$('#save-status').textContent=conflicted?'Версия изменилась в другой вкладке':'Есть несохранённые изменения';if(!conflicted)scheduleAutosave();}}
    syncSaveButtonState();
    refreshEmpty();
  });
  const syncPageRoute=()=>{const page=editor.Pages.getSelected();if(page)history.replaceState({},'',`${projectUrl(id)}/pages/${encodeURIComponent(page.get('id'))}/edit`);};
  editor.on('page:select page:add page:remove',()=>{refreshPages();syncThemeForm();syncPageRoute();});
  const syncSizeControls=component=>{const style=component.getStyle();const width=style.width||'auto';const height=style.height||'auto';$('#width-mode').value=['auto','fit-content','100%'].includes(width)?width:'custom';$('#height-mode').value=['auto','100%'].includes(height)?height:'custom';for(const property of ['min-width','max-width','min-height','max-height'])$(`#${property}`).value=style[property]||'';};
  const flexDefaults=component=>({'flex-direction':component?.get?.('type')==='wrapper'?'column':'row','justify-content':'flex-start','align-items':'stretch','flex-wrap':'nowrap'});
  const syncFlexControls=component=>{const style=component?.getStyle?.()||{},defaults=flexDefaults(component);$('#flex-controls').hidden=!component||component.get('type')==='zero-block';for(const button of document.querySelectorAll('[data-flex-property]')){const property=button.dataset.flexProperty,value=style[property]||defaults[property];const active=value===button.dataset.flexValue;button.classList.toggle('active',active);button.setAttribute('aria-pressed',String(active));}updateFlexIcons(document,{...defaults,...style});};
  const componentIdentifier=persistedComponentId;
  const componentPath=(component,page)=>{const root=page?.getMainComponent?.();const path=[];let cursor=component;while(cursor&&cursor!==root){const parent=cursor.parent?.(),index=parent?.components?.().indexOf(cursor);if(!parent||!Number.isInteger(index)||index<0)return null;path.unshift(index);cursor=parent;}return cursor===root?path:null;};
  const clearAIComponentProposal=status=>{aiComponentProposal=null;$('#ai-component-preview').hidden=true;$('#ai-component-changes').replaceChildren();if(status)$('#ai-component-status').textContent=status;};
  const syncAIComponentTarget=component=>{
    aiComponentTargetModel=component||null;aiComponentTargetId=componentIdentifier(component);clearAIComponentProposal();
    const page=editor.Pages.getSelected(),path=component?componentPath(component,page):null;aiComponentTargetPath=Array.isArray(path)?path:null;
    const supported=!!component&&!['wrapper','zero-block'].includes(component.get('type'))&&Array.isArray(path)&&path.length>0&&!conflicted;
    $('#ai-component-prompt').disabled=!supported;$('#ai-component-propose').disabled=!supported;
    $('#ai-component-target').textContent=supported?`${component.getName()} · ${aiComponentTargetId?`стабильный ID ${aiComponentTargetId}`:'ID будет закреплён при применении'}`:(component?'Для AI-правки выберите компонент внутри страницы.':'Выберите элемент на холсте.');
    $('#ai-component-status').textContent=supported?'Опишите правку. До подтверждения страница не изменится.':'AI-правка доступна для выбранного вложенного компонента.';
  };
  const appendAIChange=(model,field,before,after)=>{
    const row=document.createElement('article');row.className='ai-component-change';
    const title=document.createElement('div');title.className='ai-component-change-title';title.textContent=`${model.getName()} · ${field}`;
    const values=document.createElement('div');values.className='ai-component-change-values';
    for(const [label,value] of [['Было',before],['Будет',after]]){const cell=document.createElement('div');const caption=document.createElement('small');caption.textContent=label;const text=document.createElement('span');text.textContent=value==null||value===''?'—':String(value);cell.append(caption,text);values.append(cell);}
    row.append(title,values);$('#ai-component-changes').append(row);
  };
  const validateAIComponentProposal=(response,component,pageId,requestedComponentId)=>{
    if(response?.baseVersion!==current.version||response?.pageId!==pageId||response?.requestedComponentId!==requestedComponentId||typeof response?.needsId!=='boolean'||typeof response?.componentId!=='string'||!response.componentId||!Array.isArray(response.operations)||response.operations.length<1||response.operations.length>20)throw new Error('Предложение устарело или имеет неверный формат. Составьте его заново.');
    if(response.needsId?(requestedComponentId!==''||!/^cmp-[a-f0-9]{24}$/.test(response.componentId)):(requestedComponentId!==response.componentId))throw new Error('Стабильный ID выбранного элемента изменился. Составьте правку заново.');
    const models=new Map([[response.componentId,component]]),styleFields=new Set(['display','flex-direction','justify-content','align-items','flex-wrap','align-self','grid-template-columns','gap','width','height','min-width','max-width','min-height','max-height','padding','padding-top','padding-right','padding-bottom','padding-left','margin','margin-top','margin-right','margin-bottom','margin-left','color','background-color','font-family','font-size','font-weight','line-height','letter-spacing','text-align','text-decoration','border','border-top','border-bottom','border-radius','object-fit','aspect-ratio','opacity']);
    for(const operation of response.operations){
      const model=models.get(operation?.componentId);if(!model)throw new Error('AI предложил изменить компонент за пределами выбранного элемента.');
      if(operation.operation==='replace_text'){
        if(!['text','textnode','link'].includes(model.get('type'))||typeof operation.content!=='string'||/[<>\u0000-\u0008\u000b\u000c\u000e-\u001f\u007f]|https?:\/\/|www\./iu.test(operation.content))throw new Error('AI предложил недопустимую текстовую правку.');
      }else if(operation.operation==='update_styles'){
        if(!operation.styles||typeof operation.styles!=='object'||Array.isArray(operation.styles)||!Object.keys(operation.styles).length||Object.keys(operation.styles).some(property=>!styleFields.has(property)||typeof operation.styles[property]!=='string'))throw new Error('AI предложил недопустимые стили.');
      }else throw new Error('AI предложил неподдерживаемое действие.');
    }
    return models;
  };
  $('#ai-component-propose').onclick=async()=>{
    const button=$('#ai-component-propose'),component=editor.getSelected(),page=editor.Pages.getSelected(),prompt=$('#ai-component-prompt').value.trim();
    if(!component||!page||!prompt)return;
    if(conflicted){$('#ai-component-status').textContent='Сначала загрузите актуальную версию проекта с сервера.';return;}
    if(component!==aiComponentTargetModel){syncAIComponentTarget(component);$('#ai-component-status').textContent='Выбор элемента обновлён. Проверьте его и повторите запрос.';return;}
    if(componentIdentifier(component)!==aiComponentTargetId)syncAIComponentTarget(component);
    if(dirty){await saveDocument(false);if(dirty||conflicted){$('#ai-component-status').textContent='Не удалось сохранить текущие правки. AI-изменение не запрошено.';return;}}
    const pageId=page.get('id'),componentId=aiComponentTargetId,path=componentPath(component,page),targetPath=aiComponentTargetPath,baseVersion=current.version;
    if(!Array.isArray(path)||path.length===0||JSON.stringify(path)!==JSON.stringify(targetPath)||component.get('type')==='wrapper'){syncAIComponentTarget(component);$('#ai-component-status').textContent='Выбор элемента изменился. Проверьте его и повторите запрос.';return;}
    button.disabled=true;clearAIComponentProposal('Отправляем запрос с данными только выбранного элемента…');
    try{
      const response=await api(`/projects/${id}/ai/edit-component`,'POST',{prompt,pageId,componentPath:path,componentId,version:baseVersion});
      if(editor.getSelected()!==component||editor.Pages.getSelected()?.get('id')!==pageId)throw new Error('Выбран другой элемент. Подготовьте AI-правку заново.');
      const models=validateAIComponentProposal(response,component,pageId,componentId);
      aiComponentProposal={...response,requestedComponentId:componentId,componentModel:component,pageId,baseVersion};
      for(const operation of response.operations){const model=models.get(operation.componentId);if(operation.operation==='replace_text')appendAIChange(model,'Текст',model.getEl()?.textContent||model.get('content')||'',operation.content);else for(const [property,value] of Object.entries(operation.styles))appendAIChange(model,property,model.getStyle()?.[property]||'',value);}
      $('#ai-component-preview').hidden=false;$('#ai-component-status').textContent=`Предложено операций: ${response.changeCount||response.operations.length}. Проверьте каждое изменение перед применением.`;
    }catch(error){clearAIComponentProposal(error.message);notify(error.message,true);}
    finally{const selected=editor.getSelected(),selectedPage=editor.Pages.getSelected();button.disabled=!selected||selected.get('type')==='wrapper'||!Array.isArray(componentPath(selected,selectedPage))||conflicted;}
  };
  $('#ai-component-apply').onclick=async()=>{
    const proposal=aiComponentProposal;if(!proposal)return;
    if(conflicted||current.version!==proposal.baseVersion){clearAIComponentProposal('Версия проекта изменилась. Составьте правку заново.');return;}
    const component=editor.getSelected(),page=editor.Pages.getSelected();
    if(component!==proposal.componentModel||componentIdentifier(component)!==proposal.requestedComponentId||page?.get('id')!==proposal.pageId){clearAIComponentProposal('Выбран другой элемент. Составьте правку заново.');return;}
    if(!await askConfirmation(`Применить ${proposal.operations.length} AI-изменений к «${component.getName()}» и сохранить новую ревизию?`,'Применить AI-правку'))return;
    const button=$('#ai-component-apply'),originalData=editor.getProjectData(),models=new Map([[proposal.componentId,component]]),baseVersion=proposal.baseVersion;button.disabled=true;$('#ai-component-status').textContent='Применяем проверенные изменения и сохраняем ревизию…';
    try{
      loading=true;
      if(proposal.needsId)component.addAttributes({id:proposal.componentId});
      for(const operation of proposal.operations){const model=models.get(operation.componentId);if(!model)throw new Error('Компонент больше не найден.');if(operation.operation==='replace_text'){if(model.get('type')==='textnode')model.set('content',operation.content);else model.components(esc(operation.content));}else model.addStyle(operation.styles);}
    }catch(error){editor.loadProjectData(originalData);editor.clearDirtyCount();refreshPages();refreshEmpty();$('#ai-component-status').textContent=error.message;button.disabled=false;loading=false;return;}
    finally{loading=false;}
    dirty=JSON.stringify(editor.getProjectData())!==JSON.stringify(current.document.data);
    if(!dirty){clearAIComponentProposal('AI не предложил изменений, отличающихся от текущих значений. Ревизия не создана.');button.disabled=false;return;}
    $('#save-status').textContent='Сохраняем AI-правку…';await saveDocument(false);
    if(!dirty&&current.version>baseVersion){syncAIComponentTarget(component);$('#component-text').value=component.getEl()?.textContent||component.get('content')||'';clearAIComponentProposal(`Изменения сохранены · ревизия ${current.version}.`);notify(`AI-правка сохранена · ревизия ${current.version}`);}
    else{$('#ai-component-status').textContent='Редактор подготовил изменение, но сервер не подтвердил сохранение. Проверьте статус проекта; при конфликте загрузите актуальную версию.';notify('AI-правка не подтверждена сервером.',true);}
    button.disabled=false;
  };
  const photoCreditParts=component=>{
    let current=component;
    while(current){
      const children=current.components?.().models||[];
      if(current.get?.('tagName')==='figure'){
        const image=children.find(child=>child.get('type')==='image');
        if(image)return {figure:current,image,caption:children.find(child=>child.get('tagName')==='figcaption')||null};
      }
      if(current.get?.('type')==='image')return {figure:null,image:current,caption:null};
      current=current.parent?.();
    }
    return null;
  };
  const syncPhotoCredit=component=>{
    const field=$('#photo-credit-edit'),textarea=$('#component-photo-credit'),parts=photoCreditParts(component);
    field.hidden=!parts;
    textarea.value=parts?.caption?.get('content')||'';
  };
  $('#component-photo-credit').addEventListener('change',()=>{
    const parts=photoCreditParts(editor.getSelected());if(!parts)return;
    const credit=photoCreditText($('#component-photo-credit').value);
    if(parts.figure){
      if(credit){if(parts.caption)parts.caption.set('content',credit);else parts.figure.components().add(photoCreditCaption(credit));}
      else parts.caption?.remove();
      return;
    }
    if(!credit)return;
    const replaced=parts.image.replaceWith(photoFigureFromImage(parts.image.toJSON(),credit));
    if(replaced?.[0])editor.select(replaced[0]);
  });
  editor.on('component:selected',component=>{ $('#selected-name').textContent=component.get('type')==='wrapper'?'Body · Корень страницы':component.getName();$('#selection-hint').hidden=true;$('#quick-edit').hidden=false;$('#quick-edit').dataset.zero=String(component.get('type')==='zero-block');document.querySelector('.editor-shell').dataset.zeroSelected=String(component.get('type')==='zero-block'); const isText=['text','link','textnode'].includes(component.get('type'));$('#text-edit').hidden=!isText;$('#component-text').disabled=!isText;$('#apply-text').disabled=!isText;$('#component-text').value=isText?(component.getEl()?.textContent||component.get('content')||''):'';$('#edit-custom-code').hidden=component.get('type')!=='custom-code';$('#layout').value=component.getStyle().display||'block';syncSizeControls(component);syncFlexControls(component);syncAIComponentTarget(component);syncPhotoCredit(component);setEditorPanel('inspector'); });
  editor.on('component:deselected',()=>{if(!editor.getSelected()){ document.querySelector('.editor-shell').dataset.zeroSelected='false';$('#selected-name').textContent='Выберите элемент';$('#selection-hint').hidden=false;$('#quick-edit').hidden=true;syncFlexControls(null);syncAIComponentTarget(null);syncPhotoCredit(null);}});
  editor.on('component:update:style',component=>{if(editor.getSelected()===component){syncSizeControls(component);syncFlexControls(component);}});
  let customCodeTarget=null;
  const customCodePayload=()=>({html:$('#custom-code-html').value,css:$('#custom-code-css').value,js:$('#custom-code-js').value});
  const validCodePayload=payload=>{const total=new TextEncoder().encode(payload.html).length+new TextEncoder().encode(payload.css).length+new TextEncoder().encode(payload.js).length;if(total>65536||new TextEncoder().encode(payload.html).length>32768||new TextEncoder().encode(payload.css).length>16384||new TextEncoder().encode(payload.js).length>16384)throw new Error('Размер блока превышен: HTML до 32 КБ, CSS и JavaScript до 16 КБ каждый.');if(/<\/style\s*>/i.test(payload.css))throw new Error('В CSS нельзя закрывать тег style.');};
  const openCustomCodeEditor=component=>{customCodeTarget=component;const payload=decodeCustomCode(component.get('content'));$('#custom-code-html').value=payload.html;$('#custom-code-css').value=payload.css;$('#custom-code-js').value=payload.js;$('#custom-code-status').textContent='Изменения попадут на страницу только после сохранения блока.';$('#custom-code-dialog').showModal();submitSandboxFrame({iframe:$('#custom-code-preview-frame'),projectId:id,csrf:session.csrf,kind:'custom-code',payload});};
  $('#edit-custom-code').onclick=()=>{const component=editor.getSelected();if(component?.get('type')==='custom-code')openCustomCodeEditor(component);};
  $('#custom-code-preview').onclick=()=>{try{const payload=customCodePayload();validCodePayload(payload);$('#custom-code-status').textContent='Проверка выполняется в отдельном sandbox iframe.';submitSandboxFrame({iframe:$('#custom-code-preview-frame'),projectId:id,csrf:session.csrf,kind:'custom-code',payload});}catch(error){$('#custom-code-status').textContent=error.message;}};
  $('#custom-code-save').onclick=()=>{try{if(!customCodeTarget||customCodeTarget.get('type')!=='custom-code')throw new Error('Блок больше не выбран. Выберите его заново.');const payload=customCodePayload();validCodePayload(payload);const encoded=encodeCustomCode(payload);customCodeTarget.set('content',encoded);$('#custom-code-status').textContent='Блок сохранён в Project Data; проект сохранится обычным автосохранением.';$('#custom-code-dialog').close();editor.select(customCodeTarget);refreshEmpty();}catch(error){$('#custom-code-status').textContent=error.message;}};
  $('#close-custom-code').onclick=()=>$('#custom-code-dialog').close();
  $('#custom-code-dialog').addEventListener('close',()=>{customCodeTarget=null;clearSandboxFrame($('#custom-code-preview-frame'));});
  const addBlock=id=>{if(id==='image'){imagePicker.open();return;}const block=blocks.find(b=>b.id===id);const added=editor.addComponents(block.content());editor.select(added[0]);if(id==='custom-code')openCustomCodeEditor(added[0]);refreshEmpty();};
  for(const b of document.querySelectorAll('[data-block]'))b.onclick=()=>addBlock(b.dataset.block);
  imagePicker=installImagePicker({editor,projectId:id,api,csrf:()=>session.csrf,notify,insert:(src,alt)=>{const node=blocks.find(b=>b.id==='image').content();node.src=src;node.attributes={alt};const added=editor.addComponents(node);editor.select(added[0]);refreshEmpty();}});
  $('#start-hero').onclick=()=>addBlock('hero');
  for(const b of document.querySelectorAll('[data-panel]'))b.onclick=()=>setEditorPanel(b.dataset.panel);
  for(const b of document.querySelectorAll('.editor-floating-tools [data-open-panel]'))b.onclick=()=>setEditorPanel($('.editor-shell').dataset.openPanel===b.dataset.openPanel?'none':b.dataset.openPanel);
  for(const b of document.querySelectorAll('[data-close-panel]'))b.onclick=()=>setEditorPanel('none');
  $('#close-inspector').onclick=()=>{$('.editor-shell').dataset.inspectorOpen='false';};
  for(const b of document.querySelectorAll('.mobile-workspace-tabs [data-mobile-panel]'))b.onclick=()=>setEditorPanel(b.dataset.mobilePanel);
  for(const b of document.querySelectorAll('[data-flex-property]'))b.onclick=()=>{const component=editor.getSelected();if(!component)return;component.addStyle({[b.dataset.flexProperty]:b.dataset.flexValue});syncFlexControls(component);};
  for(const b of document.querySelectorAll('[data-device]'))b.onclick=()=>{editor.setDevice(b.dataset.device);for(const btn of document.querySelectorAll('[data-device]'))btn.classList.toggle('active',btn===b);$('#canvas-size').textContent=b.dataset.device==='Mobile'?'375 px':b.dataset.device==='Tablet'?'768 px':'Адаптивный холст';};
  let settingsPageId;
  const selectSettingsTab=tab=>{
    for(const panel of document.querySelectorAll('[data-settings-panel]'))panel.hidden=panel.dataset.settingsPanel!==tab;
    for(const button of document.querySelectorAll('[data-settings-tab]'))button.setAttribute('aria-pressed',String(button.dataset.settingsTab===tab));
    $('#page-settings-form').hidden=tab==='design';
  };
  for(const button of document.querySelectorAll('[data-settings-tab]'))button.onclick=()=>selectSettingsTab(button.dataset.settingsTab);
  $('#close-page-settings').onclick=()=>$('#theme-dialog').close();
  $('#theme').onclick=()=>{
    const page=editor.Pages.getSelected();if(!page)return;
    settingsPageId=page.id;const route=current.settings.pages[page.id],form=$('#page-settings-form');
    for(const [key,value] of Object.entries({name:page.get('name'),slug:route.slug,...route.seo}))if(form.elements.namedItem(key))form.elements.namedItem(key).value=value;
    $('#page-settings-status').textContent='';syncThemeForm();selectSettingsTab('general');$('#theme-dialog').showModal();
  };
  $('#page-settings-form').addEventListener('invalid',event=>{const panel=event.target.closest('[data-settings-panel]');if(panel)selectSettingsTab(panel.dataset.settingsPanel);},true);
  $('#page-settings-form').onsubmit=async e=>{
    e.preventDefault();const button=e.target.querySelector('[type="submit"]');button.disabled=true;
    try{
      await saveDocument(false);if(dirty||conflicted)throw new Error('Сначала сохраните изменения страницы и разрешите конфликт версий.');
      const values=Object.fromEntries(new FormData(e.target)),{name,slug,...seo}=values;
      current=await api(`/projects/${id}/settings`,'PUT',{version:current.version,page:{pageId:settingsPageId,name,slug},seo:{pageId:settingsPageId,...seo}});
      loading=true;editor.Pages.get(settingsPageId)?.set('name',name.trim(),{silent:true});loading=false;refreshPages();
      $('#page-settings-status').textContent=`Сохранено · ревизия ${current.version}`;$('#save-status').textContent=`Сохранено · ревизия ${current.version}`;
    }catch(error){$('#page-settings-status').textContent=error.message;}finally{button.disabled=false;}
  };
  $('#cancel-theme').onclick=()=>$('#theme-dialog').close();
  $('#theme-form').onsubmit=e=>{e.preventDefault();try{applyDesignTokens(Object.fromEntries(new FormData(e.target)));$('#theme-dialog').close();notify('Тема применена ко всем страницам');}catch(error){notify(error.message,true);}};
  $('#close-ai-plan').onclick=()=>$('#ai-plan-dialog').close();
  $('#ai-plan-dialog').addEventListener('close',()=>{$('#ai-plan-dialog').classList.remove('ai-preview-expanded');$('#ai-job-diagnostics').hidden=true;generationEpoch++;aiDesignState=null;clearAIPreviewUrls();$('#ai-image-slot-chooser').hidden=true;$('#ai-image-slot-list').replaceChildren();$('#ai-plan-preview').replaceChildren();});
  $('#ai-plan-open').onclick=async()=>{
    const openButton=$('#ai-plan-open');openButton.disabled=true;
    try{
      if(conflicted)throw new Error('Сначала загрузите актуальную версию проекта с сервера.');
      if(dirty){await saveDocument(false);if(dirty||conflicted)throw new Error('Не удалось сохранить текущие изменения. План не применён.');}
      aiDesignState=null;clearAIPreviewUrls();$('#ai-image-slot-chooser').hidden=true;$('#ai-image-slot-list').replaceChildren();$('#ai-plan-preview').replaceChildren();$('#ai-plan-preview').hidden=true;$('#ai-plan-status').textContent='';$('#ai-provider-status').textContent='Проверяем настройку AI-сервера…';$('#ai-generate').disabled=true;
      $('#ai-plan-dialog').showModal();
      try{await refreshUserSkillOptions();userSkillLoadError='';}catch(error){userSkillLoadError=error.message;}
      const status=await api(`/projects/${id}/ai/settings`);
      if(status.configured===true){$('#ai-generate').disabled=false;$('#ai-settings-link').hidden=true;$('#ai-provider-status').textContent=`OpenRouter настроен для проекта${status.model?` · ${status.model}`:''}. Запрос будет отправлен только после нажатия «Создать дизайн».${userSkillLoadError?' Библиотека Skills недоступна: '+userSkillLoadError:''}`;$('#ai-panel-status').textContent='AI настроен для проекта.';}
      else{$('#ai-settings-link').hidden=false;$('#ai-provider-status').textContent='AI не настроен. Описание останется на этой странице и не будет отправлено.';$('#ai-panel-status').textContent='Задайте ключ в «Настройки сайта → AI Engine».';}
    }catch(error){$('#ai-settings-link').hidden=false;$('#ai-provider-status').textContent=error.message;$('#ai-panel-status').textContent=error.message;notify(error.message,true);}
    finally{openButton.disabled=false;}
  };
  let generationPolling=false,generationEpoch=0,activeGenerationJobId=null;
  const renderAIJobDiagnostics=job=>{
    const section=$('#ai-job-diagnostics');section.replaceChildren();section.hidden=false;
    const heading=document.createElement('h3');heading.textContent='Диагностика задачи';section.append(heading);
    if(!job.diagnostics){const note=document.createElement('p');note.className='muted small';note.textContent='Для этой задачи подробная диагностика не сохранена.';section.append(note);return;}
    const labels={launcher:'Не удалось запустить worker',expired:'Истёк срок выполнения задачи',succeeded:'Дизайн прошёл серверную проверку',response_received:'Ответ модели получен',timeout:'Таймаут ответа',rate_limited:'Ограничение запросов · HTTP 429',unavailable:'Сетевая ошибка или временный отказ',credentials:'Отказ доступа к модели',invalid_output:'Результат не прошёл безопасную проверку',invalid_component_edit:'Правка элемента не прошла проверку',invalid_response:'Модель не вернула структурированный JSON',invalid_schema:'Неверный формат дизайна',invalid_markup:'HTML не прошёл проверку',invalid_css:'CSS не прошёл проверку',contact_requirements:'Контакт отсутствует в описании',skill_requirements:'Не выполнены правила Skill',cancelled:'Задача отменена',project_changed:'Версия проекта изменилась',internal:'Внутренняя ошибка',provider:'Отказ провайдера'};
    const outcome=document.createElement('p');outcome.textContent=labels[job.diagnostics.outcome]||'Запрос не завершён';section.append(outcome);
    const note=document.createElement('p');note.className='muted small';note.textContent='Обращения к профилям, включая исправление дизайна. Внутренние повторы и резервная модель профиля объединены в одно обращение. Исходные ответы и ключи не показываются.';section.append(note);
    for(const attempt of job.diagnostics.attempts||[]){const row=document.createElement('p');row.className='muted small';row.textContent=attempt.profileName+' · '+attempt.model+' · '+(labels[attempt.outcome]||'Отказ провайдера')+' · '+(attempt.elapsedMs/1000).toFixed(1)+' с';section.append(row);}
  };

  $('#ai-job-cancel').onclick=async()=>{
    const jobId=activeGenerationJobId;if(!jobId)return;
    const button=$('#ai-job-cancel');button.disabled=true;
    try{const job=await api(`/projects/${id}/ai/generation-jobs/${jobId}/cancel`,'POST',{});$('#ai-plan-status').textContent=job.status==='cancelling'?'Отменяем запрос. Уже потраченные токены могут быть учтены провайдером.':job.error;}
    catch(error){$('#ai-plan-status').textContent=error.message;}finally{button.disabled=false;}
  };
  $('#ai-job-history').onclick=async()=>{
    if(generationPolling){$('#ai-plan-status').textContent='Закройте окно ожидания и откройте AI-дизайн снова, чтобы просмотреть историю.';return;}
    const button=$('#ai-job-history'),list=$('#ai-job-history-list'),page=editor.Pages.getSelected();if(!page)return;
    button.disabled=true;list.hidden=false;list.replaceChildren();
    try{
      const history=await api(`/projects/${id}/ai/generation-jobs/history?pageId=${encodeURIComponent(page.get('id'))}`);
      const note=document.createElement('p');note.className='muted small';note.textContent=history.items.length?'Последние 20 задач этой страницы за 24 часа. Открытие не отправляет новый запрос в OpenRouter.':'Для этой страницы пока нет задач за последние 24 часа.';list.append(note);
      const statuses={queued:'В очереди',running:'Выполняется',cancelling:'Отменяется',cancelled:'Отменена',succeeded:'Готово',failed:'Ошибка'};
      for(const job of history.items){
        const row=document.createElement('div');row.className='ai-job-history-row';
        const text=document.createElement('p');text.textContent=new Date(job.createdAt*1000).toLocaleString('ru-RU')+' · '+(statuses[job.status]||job.status)+' · '+job.skillName+' · ревизия '+job.baseVersion+' — '+job.promptExcerpt;
        const open=document.createElement('button');open.type='button';open.className='secondary-link';open.textContent=job.hasResult?'Посмотреть результат':'Открыть задачу';open.onclick=()=>$('#ai-plan-form').onsubmit({preventDefault(){},resumeJob:true,jobId:job.id});row.append(text,open);list.append(row);
      }
    }catch(error){list.textContent=error.message;}finally{button.disabled=false;}
  };
  $('#ai-job-resume').onclick=()=>$('#ai-plan-form').onsubmit({preventDefault(){},resumeJob:true});
  $('#ai-plan-form').onsubmit=async e=>{
    e.preventDefault();if(generationPolling||(!e.resumeJob&&$('#ai-generate').disabled))return;
    let prompt=$('#ai-prompt').value.trim();if(!prompt&&!e.resumeJob)return;generationPolling=true;
    const button=$('#ai-generate');button.disabled=true;$('#ai-job-diagnostics').hidden=true;aiDesignState=null;clearAIPreviewUrls();$('#ai-image-slot-chooser').hidden=true;$('#ai-plan-preview').replaceChildren();$('#ai-plan-preview').hidden=true;$('#ai-plan-status').textContent='Создаём дизайн страницы. Проект пока не меняется.';
    const pollEpoch=generationEpoch;
    try{
      const page=editor.Pages.getSelected();
      if(!page)throw new Error('Выберите страницу проекта.');
      const selectedProfile=$('#ai-skill-profile').value;
      let userSkillId=selectedProfile.startsWith('user:')?selectedProfile.slice(5):null,skillProfile=userSkillId?'standard':selectedProfile;
      let job=e.resumeJob?await api(e.jobId?`/projects/${id}/ai/generation-jobs/${encodeURIComponent(e.jobId)}`:`/projects/${id}/ai/generation-jobs?pageId=${encodeURIComponent(page.get('id'))}`):await api(`/projects/${id}/ai/generation-jobs`,'POST',{prompt,skillProfile,...(userSkillId?{skillId:userSkillId}:{}),pageId:page.get('id'),version:current.version});
      if(!job)throw new Error('Для этой страницы пока нет задачи.');
      activeGenerationJobId=job.id;$('#ai-job-cancel').hidden=!['queued','running','cancelling'].includes(job.status);
      userSkillId=job.request.skillId;skillProfile=job.request.skillProfile;$('#ai-prompt').value=job.request.prompt;skillProfileSelect.value=userSkillId?'user:'+userSkillId:skillProfile;updateAIDisclosure();
      while(['queued','running','cancelling'].includes(job.status)){
        $('#ai-plan-status').textContent=(job.status==='cancelling'?'Отменяем генерацию':job.status==='queued'?'Задача в очереди':'Генерация выполняется')+' · '+Math.max(0,Math.floor(Date.now()/1000-job.createdAt))+' с. Можно закрыть окно и восстановить задачу позже. Лимит задачи — 10 минут.';
        await new Promise(resolve=>setTimeout(resolve,2000));
        if(!$('#ai-plan-dialog')?.open||pollEpoch!==generationEpoch)return;
        job=await api(`/projects/${id}/ai/generation-jobs/${job.id}`);
      }
      if(!$('#ai-plan-dialog')?.open||pollEpoch!==generationEpoch)return;
      renderAIJobDiagnostics(job);
      if(job.status!=='succeeded')throw new Error(job.error||'Генерация не завершилась.');
      if(job.pageId!==page.get('id'))throw new Error('Задача относится к другой странице.');
      const response=job.result;
      if(!response.design||typeof response.design.html!=='string'||typeof response.design.css!=='string')throw new Error('AI вернул неполный дизайн. Попробуйте уточнить описание.');
      if(skillProfile==='taste'&&(response.metadata?.skillProfile!=='taste'||response.design.skillAudit?.profile!=='taste'||!Array.isArray(response.design.skillAudit.checks)||response.design.skillAudit.checks.some(item=>item.status!=='passed')))throw new Error('Ответ не подтвердил обязательные проверки Taste. Дизайн не применён.');
      if(userSkillId&&(response.metadata?.skillProfile!=='user_skill'||response.metadata?.userSkillId!==userSkillId||response.design.skillAudit?.profile!=='user_skill'||!Array.isArray(response.design.skillAudit.humanReviewRequired)||!response.design.skillAudit.humanReviewRequired.length))throw new Error('Ответ не содержит чек-лист выбранного Skill. Дизайн не применён.');
      let assets=[],assetWarning='';try{assets=await api(`/projects/${id}/assets`);}catch(error){assetWarning=' Не удалось загрузить библиотеку изображений: '+error.message;}
      const slots=response.design.imageSlots||[];
      aiDesignState={baseVersion:job.baseVersion,design:response.design,metadata:response.metadata,pageId:page.get('id'),photosPending:true,photoNotice:response.design.photoSelection?.status==='unavailable'?'Подбор фото временно недоступен. Дизайн готов без изображений.':response.design.photoSelection?.status==='no_matches'?'Подходящих фото не найдено. Дизайн готов без изображений.':response.design.photoSelection?.photos?.length?'Изображения подобраны автоматически.':'',assets:[...assets,...(response.design.photoSelection?.photos||[])],imageAssignments:response.design.photoSelection?.assignments||{},imageAlt:Object.fromEntries(slots.map(slot=>[slot.id,slot.label]))};
      renderAIDesign(response.design,response.metadata);if(!response.design.photoSelection&&!isAIDesignReadOnly()){for(const slot of response.design.imageSlots||[]){if(slot.query){try{await findAIStockPhoto(slot.id,slot.query);}catch(error){if(aiDesignState){aiDesignState.photoNotice=error.message;$('#ai-plan-status').textContent=error.message;}}}}}if(!$('#ai-plan-dialog')?.open||pollEpoch!==generationEpoch)return;aiDesignState.photosPending=false;syncAIApply();if(isAIDesignReadOnly()){$('#ai-image-slot-chooser').hidden=true;for(const input of $('#ai-plan-preview').querySelectorAll('input'))input.disabled=true;}$('#ai-plan-status').textContent=response.design.contactAudit?.status!=='passed'?'Старый результат без проверки контактов: только просмотр. Создайте новую генерацию.':job.baseVersion!==current.version?'Результат ревизии '+job.baseVersion+' доступен только для просмотра: текущая ревизия '+current.version+'. Для применения создайте новую генерацию.':'Проверьте предпросмотр. Страница изменится только после отдельного подтверждения. '+(aiDesignState.photoNotice||'');
      if(assetWarning)$('#ai-plan-status').textContent+=assetWarning;
    }catch(error){$('#ai-plan-status').textContent=error.message;notify(error.message,true);}
    finally{generationPolling=false;activeGenerationJobId=null;$('#ai-job-cancel').hidden=true;button.disabled=false;}
  };
  pageSwitcherTrigger.onclick=()=>pageMenu.hidden?openPageMenu(true):closePageMenu(false);
  pageSwitcherTrigger.onkeydown=event=>{if(['ArrowDown','Enter',' '].includes(event.key)){event.preventDefault();openPageMenu(true);}};
  pageMenu.addEventListener('click',event=>{const option=event.target.closest('[role="option"]');if(!option||event.target.closest('input,button'))return;option.focus();clearTimeout(pageSelectionTimer);pageSelectionTimer=setTimeout(()=>selectPageById(option.dataset.pageId),500);});
  pageMenu.addEventListener('dblclick',event=>{const option=event.target.closest('[role="option"]');if(!option||event.target.closest('input,button'))return;event.preventDefault();clearTimeout(pageSelectionTimer);pageSelectionTimer=null;beginPageRename(option,option.dataset.pageId);});
  pageMenu.addEventListener('keydown',event=>{if(event.target.closest('input,button'))return;const option=event.target.closest('[role="option"]');if(!option)return;const options=[...pageMenu.querySelectorAll('[role="option"]:not(.is-renaming)')],index=options.indexOf(option);if(event.key==='ArrowDown'||event.key==='ArrowUp'){event.preventDefault();options[(index+(event.key==='ArrowDown'?1:-1)+options.length)%options.length]?.focus();}else if(event.key==='Home'||event.key==='End'){event.preventDefault();options[event.key==='Home'?0:options.length-1]?.focus();}else if(event.key==='Enter'){event.preventDefault();selectPageById(option.dataset.pageId);}else if(event.key==='F2'){event.preventDefault();beginPageRename(option,option.dataset.pageId);}});
  document.addEventListener('pointerdown',event=>{if(!pageSwitcher.contains(event.target)&&!pageMenu.hidden){if(pageMenu.querySelector('.is-renaming'))refreshPages();closePageMenu(false);}});
  document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!event.defaultPrevented&&!pageMenu.hidden){if(pageMenu.querySelector('.is-renaming'))refreshPages();closePageMenu(true);}});
  $('#add-page').onclick=()=>$('#page-dialog').showModal();$('#cancel-page').onclick=()=>$('#page-dialog').close();
  $('#page-form').onsubmit=e=>{e.preventDefault();const page=editor.Pages.add({id:crypto.randomUUID(),name:new FormData(e.target).get('name'),component:createPageRoot(designTokensToStyle(currentDesignTokens()))});editor.Pages.select(page);$('#page-dialog').close();e.target.reset();};
  $('#undo').onclick=()=>editor.UndoManager.undo();$('#redo').onclick=()=>editor.UndoManager.redo();
  $('#layout').onchange=e=>{const component=editor.getSelected();if(!component)return;const style=component.getStyle()||{},updates={display:e.target.value};if(component.get('type')==='wrapper'&&e.target.value==='flex'&&!style['flex-direction'])updates['flex-direction']='column';component.addStyle(updates);syncFlexControls(component);};
  $('#width-mode').onchange=e=>editor.getSelected()?.addStyle({width:e.target.value});$('#height-mode').onchange=e=>editor.getSelected()?.addStyle({height:e.target.value});$('#apply-size-constraints').onclick=()=>{const component=editor.getSelected();if(!component)return;const updates={},removals=[];for(const property of ['min-width','max-width','min-height','max-height']){const input=$(`#${property}`),value=input.value.trim();if(!value){removals.push(property);continue;}const valid=/^(?:\d+(?:\.\d+)?(?:px|%|rem|em|vw|vh)|auto|none)$/i.test(value)&&(property.startsWith('min-')?value.toLowerCase()!=='none':value.toLowerCase()!=='auto');if(!valid){input.focus();notify('Укажите размер с единицей: например 320px, 60% или 40rem.',true);return;}updates[property]=value;}for(const property of removals)component.removeStyle(property);if(Object.keys(updates).length)component.addStyle(updates);syncSizeControls(component);notify('Ограничения размеров применены');};
  $('#apply-text').onclick=()=>{const selected=editor.getSelected();if(selected){selected.components(esc($('#component-text').value));notify('Текст изменён');}};
  $('#save').onclick=()=>saveDocument(false);
  $('#publish-editor').onclick=async()=>{
    const button=$('#publish-editor');button.disabled=true;
    try{
      if(conflicted)throw new Error('Сначала разрешите конфликт версий.');
      if(dirty)await saveDocument(false);
      if(dirty||saving||conflicted)throw new Error('Сначала сохраните изменения и разрешите конфликт версий.');
      const version=current.version;
      if(!await askConfirmation(`Будет опубликована сохранённая ревизия ${version}. Сайт станет доступен без входа на отдельном публичном адресе. Продолжить?`,'Опубликовать'))return;
      const result=await api(`/projects/${id}/publish`,'POST',{version});
      editorPublication=result.publication;current.version=result.publication.sourceProjectVersion;syncPublishButtonState();notify(`Опубликована ревизия ${result.publication.sourceProjectVersion}: ${result.publicUrl}`);
    }catch(error){notify(error.message,true);}
    finally{button.disabled=false;}
  };
  $('#reload-server').onclick=async()=>{
    if(!await askConfirmation('Локальные несохранённые изменения будут заменены серверной версией. Сначала можно скачать их через «Экспорт JSON». Продолжить?','Загрузить версию'))return;
    try{const selectedPageId=editor.Pages.getSelected()?.get('id');const latest=await api(`/projects/${current.id}`);current=latest;loading=true;editor.loadProjectData(latest.document.data);if(selectedPageId&&editor.Pages.getAll().some(page=>page.get('id')===selectedPageId))editor.Pages.select(selectedPageId);editor.clearDirtyCount();savedEditorSnapshot=JSON.stringify(editor.getProjectData());dirty=false;loading=false;conflicted=false;$('#reload-server').hidden=true;$('#save').disabled=false;refreshPages();syncThemeForm();$('#save-status').textContent=`Ревизия ${current.version} · Загружено с сервера`;notify('Серверная версия загружена');}
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
            const selectedPageId=editor.Pages.getSelected()?.get('id');
            const restored=await api(`/projects/${current.id}/revisions/restore`,'POST',{currentVersion:current.version,targetVersion:revision.version});
            current=restored;loading=true;editor.loadProjectData(restored.document.data);if(selectedPageId&&editor.Pages.getAll().some(page=>page.get('id')===selectedPageId))editor.Pages.select(selectedPageId);editor.clearDirtyCount();savedEditorSnapshot=JSON.stringify(editor.getProjectData());loading=false;dirty=false;conflicted=false;$('#reload-server').hidden=true;$('#save').disabled=false;refreshPages();syncThemeForm();$('#save-status').textContent=`Восстановлена ревизия ${revision.version} · новая ревизия ${current.version}`;$('#history-dialog').close();notify('Ревизия восстановлена');
          }catch(error){loading=false;if(error.status===409){conflicted=true;$('#reload-server').hidden=false;$('#save').disabled=true;$('#save-status').textContent='Версия изменилась в другой вкладке';}notify(error.message,true);}
        };row.append(restore);}list.append(row);
      }
    } catch(error){$('#history-list').textContent='';$('#history-error').textContent=error.message;}
  };
  $('#history').onclick=renderHistory;$('#close-history').onclick=()=>$('#history-dialog').close();
  $('#export').onclick=()=>download('juzimder-project.json',JSON.stringify({schemaVersion:1,engine:'grapesjs',data:editor.getProjectData()},null,2));
  $('#static-export').onclick=async()=>{
    const button=$('#static-export');button.disabled=true;
    try{
      if(dirty&&!conflicted)await saveDocument(false);
      if(dirty||conflicted)throw new Error('Сначала сохраните изменения и разрешите конфликт версий.');
      const response=await fetch(`/api/projects/${current.id}/export/static`,{headers:{'X-CSRF-Token':session.csrf}});
      if(!response.ok){let error='Не удалось собрать ZIP сайта.';try{error=(await response.json()).error||error;}catch{}throw new Error(error);}
      const archive=await response.blob();download('juzimder-static-site.zip',archive,'application/zip');notify('Статический ZIP сайта скачан');
    }catch(error){notify(error.message,true);}
    finally{button.disabled=false;}
  };
  $('#back').onclick=async()=>{
    if(dirty&&!conflicted)await saveDocument(false);
    if(dirty&&!await askConfirmation(conflicted?'Не удалось объединить изменения с сервером. Можно скачать их через «Экспорт JSON». Всё равно выйти?':'Изменения не сохранены. Всё равно выйти?','Всё равно выйти'))return;
    clearTimeout(saveTimer);saveTimer=undefined;componentActions.destroy();editor.destroy();editor=null;dirty=false;location.assign(projectUrl(id));
  };
  $('#preview').onclick=()=>{const html=editor.getHtml(),css=editor.getCss();$('#preview-frame').srcdoc=`<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><style>body{margin:0}${css}</style></head><body>${html}</body></html>`;$('#preview-dialog').showModal();};$('#close-preview').onclick=()=>$('#preview-dialog').close();
}
document.addEventListener('keydown',event=>{
  if(!editor||event.defaultPrevented||document.querySelector('dialog[open]'))return;
  if(event.key==='Escape'){const shell=$('.editor-shell');if(shell.dataset.openPanel!=='none'){setEditorPanel('none');return;}if(shell.dataset.inspectorOpen==='true'){shell.dataset.inspectorOpen='false';return;}}
  if(!(event.metaKey||event.ctrlKey)||event.altKey)return;
  if(event.target instanceof Element&&event.target.closest('input,textarea,select,[contenteditable]:not([contenteditable="false"])'))return;
  const key=event.key.toLowerCase();
  if(key==='s'&&!event.shiftKey){event.preventDefault();saveDocument(false);}
  else if(key==='z'){event.preventDefault();if(event.shiftKey)editor.UndoManager.redo();else editor.UndoManager.undo();}
  else if(key==='y'&&event.ctrlKey){event.preventDefault();editor.UndoManager.redo();}
});
window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});
(async()=>{try{session=await api('/session');if(!session.user){auth();return;}let path=location.pathname;const incoming=path.match(/^\/projects\/([^/]+)(.*)$/);if(incoming){const segment=incoming[1],suffix=incoming[2];const resolved=/^[a-f0-9]{24}$/.test(segment)?await api(`/projects/${segment}`):await api(`/projects/resolve/${encodeURIComponent(segment)}`);projectSlugs.set(resolved.id,resolved.slug);path=`/projects/${resolved.id}${suffix}`;if(segment!==resolved.slug)history.replaceState({},'',`${projectUrl(resolved.id,suffix)}${location.search}${location.hash}`);}const zeroMatch=path.match(/^\/projects\/([a-f0-9]{24})\/pages\/([A-Za-z0-9_-]{1,128})\/zero\/([A-Za-z][A-Za-z0-9_-]{0,127})\/edit$/);if(zeroMatch){const {openZeroEditor}=await import('./zero/editor/app.js');await openZeroEditor({projectId:zeroMatch[1],pageId:zeroMatch[2],blockId:zeroMatch[3],api,session,app});return;}let match=path.match(/^\/projects\/([a-f0-9]{24})\/pages\/([A-Za-z0-9_-]{1,128})\/edit$/);if(match){await openEditor(match[1],decodeURIComponent(match[2]));return;}match=path.match(/^\/projects\/([a-f0-9]{24})\/settings$/);if(match){await siteSettings(match[1]);return;}match=path.match(/^\/projects\/([a-f0-9]{24})$/);if(match){await projectPages(match[1]);return;}await dashboard();}catch(e){app.innerHTML='<main class="boot-error"><h1>Не удалось открыть пространство</h1><p></p><button onclick="location.reload()">Повторить</button></main>';$('.boot-error p').textContent=e.message;}})();
