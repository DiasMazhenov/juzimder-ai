import 'grapesjs/dist/css/grapes.min.css';
import './style.css';
import { blocks, registerBlocks } from './blocks.js';
import { DEFAULT_DESIGN_TOKENS, FONT_FAMILIES, FONT_WEIGHTS, TOKEN_FIELDS, designTokensFromStyle, designTokensToStyle, migrateLegacyTokenValue, normalizeDesignTokens, tokenValue } from './design-tokens.js';
import packageInfo from '../package.json';
const $ = (selector) => document.querySelector(selector);
const app = $('#app');
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
const blockCategory=id=>({text:'Элементы',image:'Медиа',button:'Элементы',container:'Компоновка',navigation:'Навигация'}[id]||'Секции');
let session, editor, current, dirty=false, loading=false, saving=false, saveAgain=false, conflicted=false, saveTimer, savePromise;
async function api(path, method='GET',body) {
  const response = await fetch(`/api${path}`,{method,headers:{'Content-Type':'application/json',...(session?{'X-CSRF-Token':session.csrf}:{})},...(body?{body:JSON.stringify(body)}:{})});
  const data = await response.json(); if (!response.ok) { const error=new Error(data.error || `HTTP ${response.status}`);error.status=response.status;if(response.status===401&&session?.user&&path==='/projects'&&method==='POST'){session=null;location.assign('/?session=expired');}throw error; } return data;
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
      current=await api(`/projects/${current.id}`,'PUT',{version,document:{schemaVersion:1,engine:'grapesjs',data},settings:{designTokens:current.settings.designTokens}});
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
  const devAdmin = session?.devAuth === true && !register;
  const expiredSession=session?.sessionExpired||new URLSearchParams(location.search).get('session')==='expired';
  app.innerHTML=`<main class="auth"><div class="auth-story"><a class="brand" href="/">${mark} Juzimder <span>AI</span></a><div><p class="eyebrow">ВАШ СЛЕДУЮЩИЙ ПРОЕКТ НАЧИНАЕТСЯ ЗДЕСЬ</p><h1>Из идеи —<br>в живой сайт.</h1><p class="intro">Пространство, где дизайн складывается.<br>Одна секция, одна страница, одна идея за раз.</p></div><p class="muted">На базе GrapesJS Core · Открытая платформа</p></div><section class="auth-form"><p class="eyebrow">JUZIMDER WORKSPACE / 01</p><h2>${register?'Создайте своё пространство':'С возвращением'}</h2><p class="muted">${register?'Начните с первой страницы.':'Войдите, чтобы продолжить работу над сайтом.'}</p>${expiredSession?'<p class="muted" role="status">Сессия не относится к текущей локальной базе. Войдите снова.</p>':''}<form id="auth-form"><label>${devAdmin?'Email или локальный логин':'Email'}<input name="email" type="${devAdmin?'text':'email'}" autocomplete="${register?'email':'username'}" required ${register||!session?.devAuth?'minlength="12"':''} maxlength="254" placeholder="${devAdmin?'admin или you@studio.kz':'you@studio.kz'}"></label><label>Пароль<input name="password" type="password" autocomplete="${register?'new-password':'current-password'}" required ${register||!session?.devAuth?'minlength="12"':''} maxlength="72" placeholder="${devAdmin?'admin или не менее 12 символов':'Не менее 12 символов'}"></label><button class="primary" type="submit">${register?'Создать аккаунт':'Войти в пространство'} <span>→</span></button><p id="notice" role="status"></p></form>${devAdmin?'<p class="muted small">Локальный тестовый вход: <strong>admin / admin</strong></p>':''}<button class="text-button" id="toggle-auth">${register?'Уже есть аккаунт? Войти':'Первый раз здесь? Создать аккаунт'}</button><p class="auth-foot">Ранняя версия · Визуальное редактирование и сохранение</p></section></main>`;
  $('#toggle-auth').onclick=()=>auth(!register);
  $('#auth-form').onsubmit=async e=>{e.preventDefault();const b=e.target.querySelector('button');b.disabled=true;try{session=await api(register?'/register':'/login','POST',Object.fromEntries(new FormData(e.target)));history.replaceState(null,'','/');await dashboard();}catch(error){notify(error.message,true);}finally{b.disabled=false;}};
}
async function dashboard() {
  const projects=await api('/projects');
  app.innerHTML=`<div class="dashboard"><aside class="dash-sidebar"><a class="brand" href="/">${mark} Juzimder <span>AI</span></a><div class="workspace-label">ЛИЧНОЕ ПРОСТРАНСТВО</div><div class="nav-active">▦ <span>Мои проекты</span><small>${projects.length}</small></div><div class="sidebar-note"><span class="status-dot"></span> FOUNDATION<div class="version-label">Версия продукта <strong>${productVersion}</strong></div><p>От первой идеи<br>до первой страницы.</p></div><button id="logout" class="text-button">Выйти из аккаунта ↗</button></aside><main class="dash-main"><header class="dash-top"><span>Workspace <span class="slash">/</span> Мои проекты</span><span class="dash-user"><span>${esc(session.user.email)}</span><span class="version-label">Версия ${productVersion}</span></span></header><section class="project-intro"><p class="eyebrow">СОЗДАВАЙТЕ В СВОЁМ РИТМЕ</p><div class="title-row"><div><h1>Мои проекты<span class="accent">.</span></h1><p class="muted">Хорошие сайты начинаются с чистого листа.</p></div><button id="new-project" class="primary">+ Новый проект</button></div></section><section class="project-list">${projects.map(p=>`<button class="project-card" data-project="${esc(p.id)}"><div class="project-art"><div class="mini-window"><div></div><i></i><i></i><span></span></div><span class="project-arrow">↗</span></div><div class="project-info"><h3>${esc(p.name)}</h3><p>${p.version?'Ревизия '+p.version:'Новый проект'} <span>Страницы →</span></p></div></button>`).join('')}<button class="project-card new-card" id="empty-create"><span class="plus">+</span><strong>Новая идея</strong><span>Создайте сайт с нуля</span></button></section><p id="notice" role="status"></p><footer class="dash-footer"><span>YOUR IDEAS, TAKING SHAPE.</span><span>GrapesJS Core · PHP · SQLite</span></footer></main></div><dialog id="create-dialog"><form id="create-form"><p class="eyebrow">НОВОЕ ПРОСТРАНСТВО</p><h2>Как назовём проект?</h2><label>Название проекта<input name="name" required maxlength="100" placeholder="Например, Сайт студии"></label><p class="muted">Начнём с пустой главной страницы.</p><p id="create-error" role="alert"></p><div class="dialog-actions"><button type="button" id="cancel-create">Отмена</button><button class="primary">Создать проект →</button></div></form></dialog>`;
  const open=()=>$('#create-dialog').showModal(); $('#new-project').onclick=open;$('#empty-create').onclick=open;$('#cancel-create').onclick=()=>$('#create-dialog').close();
  $('#create-form').onsubmit=async e=>{e.preventDefault();const b=e.target.querySelector('.primary');b.disabled=true;try{const project=await api('/projects','POST',{name:new FormData(e.target).get('name')});location.assign(`/projects/${project.id}`);}catch(error){$('#create-error').textContent=error.message;b.disabled=false;}};
  for(const btn of document.querySelectorAll('[data-project]')) btn.onclick=()=>location.assign(`/projects/${btn.dataset.project}`);
  $('#logout').onclick=async()=>{await api('/logout','POST',{});session=await api('/session');auth();};
}
async function projectPages(id) {
  const response=await api(`/projects/${id}/pages`),project=response.project,pages=response.pages;
  const publicationResponse=await api(`/projects/${id}/publication`);
  let publication=publicationResponse.publication,publicUrl=publicationResponse.publicUrl;
  let pageDialogAction=null;
  const rows=pages.map(page=>`<tr><td><a class="page-title" href="/projects/${id}/pages/${encodeURIComponent(page.id)}/edit">${esc(page.name)}</a><small class="page-revision-link">Изменить содержимое →</small></td><td><button class="url-value" data-page-action="slug" data-page="${esc(page.id)}" aria-label="Изменить URL ${esc(page.name)}">${esc(page.path)}</button></td><td><span class="home-state ${page.isHome?'is-home':''}">${page.isHome?'⌂ Главная':'Обычная'}</span></td><td><details class="page-menu"><summary aria-label="Действия: ${esc(page.name)}">⋯</summary><div class="page-menu-popover"><a href="/projects/${id}/pages/${encodeURIComponent(page.id)}/edit">Редактировать</a><button data-page-action="rename" data-page="${esc(page.id)}">Переименовать</button><button data-page-action="slug" data-page="${esc(page.id)}">Изменить URL</button><button data-page-action="duplicate" data-page="${esc(page.id)}">Дублировать</button><button data-page-action="home" data-page="${esc(page.id)}" ${page.isHome?'disabled':''}>Сделать главной</button><button data-page-action="page-settings" data-page="${esc(page.id)}">Настройки страницы</button><button class="danger-action" data-page-action="delete" data-page="${esc(page.id)}">Удалить</button></div></details></td></tr>`).join('');
  app.innerHTML=`<div class="dashboard project-dashboard"><aside class="dash-sidebar"><a class="brand" href="/">${mark} Juzimder <span>AI</span></a><div class="workspace-label">ПРОЕКТ</div><a class="nav-active" href="/projects/${id}">▦ <span>Страницы</span><small>${pages.length}</small></a><a class="project-side-link" href="/projects/${id}/settings">⚙ <span>Настройки сайта</span></a><div class="sidebar-note"><span class="status-dot"></span> FOUNDATION<div class="version-label">Версия продукта <strong>${productVersion}</strong></div><p>${esc(project.name)}</p></div><a class="text-button" href="/">← Все проекты</a><button id="logout" class="text-button">Выйти из аккаунта ↗</button></aside><main class="dash-main"><header class="dash-top"><span><a href="/">Мои проекты</a><span class="slash">/</span>${esc(project.name)}</span><span class="version-label">Версия ${productVersion}</span></header><section class="project-intro"><p class="eyebrow">УПРАВЛЕНИЕ САЙТОМ</p><div class="title-row project-title-row"><div><h1>${esc(project.name)}<span class="accent">.</span></h1><p class="muted">Страницы сайта · ревизия ${project.version}</p></div><div class="project-actions"><a class="secondary-link" href="/projects/${id}/settings">Настройки сайта</a><button id="create-page" class="primary">+ Создать страницу</button></div></div></section><section class="project-pages-section publication-panel" aria-labelledby="publication-title"><div class="section-title-row"><div><h2 id="publication-title">Публикация сайта</h2><p class="muted">Публичный адрес обслуживает только сохранённый статический снимок.</p></div><span id="publication-status" class="status-pill" role="status" aria-live="polite">Проверка…</span></div><div class="publication-actions"><button id="preview-site" class="secondary-link">Предпросмотр</button><button id="publish-site" class="primary">Опубликовать сайт</button><button id="unpublish-site" class="secondary-link" hidden>Снять с публикации</button><a id="publication-link" class="secondary-link" target="_blank" rel="noopener noreferrer" hidden>Открыть сайт ↗</a></div></section><section class="project-pages-section"><div class="section-title-row"><div><h2>Страницы</h2><p class="muted">URL-адреса не меняют внутренние идентификаторы страниц.</p></div><span class="page-count">${pages.length} из 30</span></div>${pages.length?`<div class="pages-table-wrap"><table class="pages-table"><thead><tr><th>Название страницы</th><th>URL страницы</th><th>Главная</th><th><span class="visually-hidden">Действия</span></th></tr></thead><tbody>${rows}</tbody></table></div>`:`<div class="empty-pages"><h3>Здесь появится первая страница</h3><p>Создайте страницу, затем откройте её в редакторе.</p><button class="primary" id="empty-create-page">Создать первую страницу</button></div>`}</section><section class="project-pages-section system-items-section"><div class="section-title-row"><div><h2>Сквозные блоки и системные страницы</h2><p class="muted">Глобальные элементы проекта будут храниться отдельно от содержимого страниц.</p></div></div><div class="system-items"><div><span class="system-icon">↥</span><span><strong>Шапка сайта</strong><small>Общий Header для выбранных страниц</small></span><span class="status-pill">Не подключено</span></div><div><span class="system-icon">↧</span><span><strong>Подвал сайта</strong><small>Общий Footer для выбранных страниц</small></span><span class="status-pill">Не подключено</span></div><div><span class="system-icon">404</span><span><strong>Страница 404</strong><small>Системная страница для опубликованного сайта</small></span><span class="status-pill">Настраивается выше</span></div></div></section><p id="notice" role="status"></p><footer class="dash-footer"><span>YOUR IDEAS, TAKING SHAPE.</span><span>GrapesJS Core · PHP · SQLite</span></footer></main></div><dialog id="page-dialog"><form id="project-page-form"><p class="eyebrow" id="page-dialog-eyebrow">СТРАНИЦА ПРОЕКТА</p><h2 id="page-dialog-title"></h2><label id="page-dialog-label">Название<input id="page-dialog-input" name="value" required maxlength="100"></label><p class="muted small" id="page-dialog-help"></p><p id="page-dialog-error" role="alert"></p><div class="dialog-actions"><button type="button" id="cancel-page-dialog">Отмена</button><button class="primary" id="submit-page-dialog">Сохранить</button></div></form></dialog><dialog id="confirm-dialog"><form method="dialog"><p id="confirm-message"></p><div class="dialog-actions"><button value="cancel">Отмена</button><button id="confirm-submit" class="primary" value="confirm">Продолжить</button></div></form></dialog><dialog id="site-preview-dialog" class="site-preview-dialog" aria-labelledby="site-preview-title"><div class="site-preview-header"><div><p class="eyebrow">ПРЕДПРОСМОТР</p><h2 id="site-preview-title">Сохранённый сайт</h2></div><button id="close-site-preview" type="button" aria-label="Закрыть предпросмотр">×</button></div><label class="site-preview-selector">Страница<select id="site-preview-page" aria-label="Страница предпросмотра"></select></label><p id="site-preview-status" role="status" aria-live="polite">Подготовка…</p><iframe id="site-preview-frame" title="Предпросмотр страницы сайта" sandbox=""></iframe></dialog>`;
  const renderPublication=()=>{const status=$('#publication-status'),link=$('#publication-link'),publishButton=$('#publish-site'),unpublishButton=$('#unpublish-site'),stale=publication&&publication.sourceProjectVersion!==project.version;status.textContent=!publication?'Не опубликован':stale?`Устарела · версия ${publication.version}, ревизия ${publication.sourceProjectVersion}`:`Опубликована · версия ${publication.version}, ревизия ${publication.sourceProjectVersion}`;status.classList.toggle('publication-stale',Boolean(stale));publishButton.textContent=publication?`Опубликовать ревизию ${project.version}`:'Опубликовать сайт';unpublishButton.hidden=!publication;link.hidden=!publication;if(publication)link.href=publicUrl;};
  renderPublication();
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
  let project=await api(`/projects/${id}`);const sections=[['domain','Домен'],['fonts','Шрифты'],['design','Цвета и стили'],['seo','SEO'],['analytics','Аналитика'],['forms','Формы'],['code','Вставка кода'],['ai','AI Engine']];
  const active=new URLSearchParams(location.search).get('section')||'domain',selected=sections.find(([key])=>key===active)||sections[0];
  const settingsNav=`<nav class="settings-nav sidebar-settings-nav" aria-label="Разделы настроек">${sections.map(([key,label])=>`<a href="/projects/${id}/settings?section=${key}" class="${selected[0]===key?'active':''}" ${selected[0]===key?'aria-current="page"':''}>${label}</a>`).join('')}</nav>`;
  const tokens=normalizeDesignTokens(project.settings?.designTokens||DEFAULT_DESIGN_TOKENS);
  const tokenSelect=(name,label,choices,choiceLabel=value=>value)=>`<label class="settings-field">${esc(label)}<select name="${name}">${choices.map(value=>`<option value="${esc(value)}" ${tokens[name]===value?'selected':''}>${esc(choiceLabel(value))}</option>`).join('')}</select></label>`;
  const sizeSelect=(name,label)=>{const [min,max,step]=fontSizeRanges[name];const choices=Array.from({length:Math.floor((max-min)/step)+1},(_,index)=>`${min+index*step}px`);return tokenSelect(name,label,choices);};
  let body='',aiSettings=null;
  if(selected[0]==='design')body=`<form id="design-tokens-form" class="settings-form"><p class="muted settings-form-intro">Основные цвета и скругление хранятся в настройках проекта, применяются ко всем страницам и доступны редактору и AI.</p><div class="settings-color-grid">${TOKEN_FIELDS.filter(field=>field.type==='color').map(field=>`<label>${esc(field.label)}<input type="color" name="${field.name}" value="${esc(tokens[field.name])}" aria-label="${esc(field.label)}"></label>`).join('')}</div><label class="settings-field">Скругление карточек<select name="radius">${[0,4,6,8,12,16,24,32].map(px=>`<option value="${px}px" ${tokens.radius===`${px}px`?'selected':''}>${px} px</option>`).join('')}</select></label><p class="muted small">Локальные стили элементов остаются самостоятельными. Настройки сохраняются отдельной ревизией проекта; они пока не управляют публикацией, которой ещё нет.</p><div class="settings-form-actions"><button class="primary" type="submit">Сохранить цвета и стили</button><span id="settings-save-status" class="muted small" role="status"></span></div></form>`;
  else if(selected[0]==='fonts')body=`<form id="font-tokens-form" class="settings-form"><p class="muted settings-form-intro">Задайте шрифты, веса, размеры и интервалы проекта. H1–H3 адаптируются к ширине холста; значения, заданные непосредственно элементу, сохраняют приоритет.</p><div class="typography-grid">${tokenSelect('headingFontFamily','Шрифт заголовков',FONT_FAMILIES,font=>font.startsWith('Georgia')?'Georgia':font.startsWith('system-ui')?'Системный':'Arial')}${tokenSelect('bodyFontFamily','Шрифт основного текста',FONT_FAMILIES,font=>font.startsWith('Georgia')?'Georgia':font.startsWith('system-ui')?'Системный':'Arial')}${tokenSelect('headingFontWeight','Вес заголовков',FONT_WEIGHTS)}${tokenSelect('bodyFontWeight','Вес основного текста',FONT_WEIGHTS)}${sizeSelect('baseFontSize','Базовый размер текста')}${sizeSelect('fontSizeH1','Размер H1')}${sizeSelect('fontSizeH2','Размер H2')}${sizeSelect('fontSizeH3','Размер H3')}${sizeSelect('fontSizeH4','Размер H4')}${sizeSelect('fontSizeH5','Размер H5')}${sizeSelect('fontSizeH6','Размер H6')}${tokenSelect('headingLineHeight','Интервал заголовков',lineHeightOptions)}${tokenSelect('bodyLineHeight','Интервал основного текста',lineHeightOptions)}${tokenSelect('letterSpacing','Межбуквенный интервал',letterSpacingOptions)}</div><p class="muted small">Настройки сохраняются новой ревизией и используются редактором и AI-секциями. Публичная публикация и static export ещё не подключены.</p><div class="settings-form-actions"><button class="primary" type="submit">Сохранить типографику</button><span id="settings-save-status" class="muted small" role="status"></span></div></form>`;
  else if(selected[0]==='seo'){
    const pages=project.document.data.pages||[],requestedPage=new URLSearchParams(location.search).get('page'),seoPage=pages.find(page=>page.id===requestedPage)||pages[0],seo=project.settings?.pages?.[seoPage?.id]?.seo||{title:'',description:'',robots:'index',socialImage:''};
    body=`<label class="settings-field">Страница<select id="seo-page-select">${pages.map(page=>`<option value="${esc(page.id)}" ${page.id===seoPage?.id?'selected':''}>${esc(page.name)}</option>`).join('')}</select></label><form id="seo-settings-form" class="settings-form"><input type="hidden" name="pageId" value="${esc(seoPage?.id||'')}"><p class="muted settings-form-intro">SEO-метаданные сохраняются для каждой страницы вместе с ревизиями проекта. Публикация ещё не подключена, поэтому эти значения пока не влияют на внешний сайт.</p><label class="settings-field">Заголовок страницы<input name="title" maxlength="70" value="${esc(seo.title||'')}" placeholder="Пустое поле использует название страницы"><span class="muted small">До 70 символов.</span></label><label class="settings-field">Описание страницы<textarea name="description" rows="4" maxlength="160" placeholder="Кратко опишите содержание страницы">${esc(seo.description||'')}</textarea><span class="muted small">До 160 символов.</span></label><label class="settings-field">Индексация<select name="robots"><option value="index" ${seo.robots==='index'?'selected':''}>Разрешить индексацию</option><option value="noindex" ${seo.robots==='noindex'?'selected':''}>Запретить индексацию</option></select></label><label class="settings-field">Изображение для соцсетей<input name="socialImage" type="url" value="${esc(seo.socialImage||'')}" maxlength="2048" pattern="https://.*" placeholder="https://example.com/preview.jpg"><span class="muted small">Необязательно. Используется HTTPS-адрес.</span></label><div class="settings-form-actions"><button class="primary" type="submit">Сохранить SEO</button><span id="seo-save-status" class="muted small" role="status"></span></div></form>`;
  }
  else if(selected[0]==='ai'){
    try { aiSettings=await api('/projects/'+id+'/ai/settings'); }
    catch(error) { aiSettings={configured:false,model:null,activeProfileId:null,profiles:[],version:0,error:error.message}; }
    const initialProfileId=aiSettings.activeProfileId||(aiSettings.profiles[0]?.id||'');
    const initialProfile=aiSettings.profiles.find(profile=>profile.id===initialProfileId);
    const profileCards=aiSettings.profiles.map(profile=>'<button type="button" data-ai-profile-id="'+esc(profile.id)+'" class="ai-profile-card'+(profile.id===initialProfileId?' selected':'')+'" aria-pressed="'+(profile.id===initialProfileId?'true':'false')+'"><span><strong>'+esc(profile.name)+'</strong><small>'+esc(profile.model)+(profile.fallbackModel?' · запасная: '+esc(profile.fallbackModel):'')+'</small></span><span class="status-pill">'+(profile.active?'Активен':'Редактировать')+'</span></button>').join('');
    body='<section class="ai-engine-settings"><div class="settings-notice"><span class="settings-notice-icon">✳</span><div><strong id="ai-settings-heading">'+(aiSettings.configured?'Активный профиль: '+esc(aiSettings.profiles.find(profile=>profile.id===aiSettings.activeProfileId)?.name||'OpenRouter'):'Добавьте профиль OpenRouter')+'</strong><p id="ai-settings-intro">'+(aiSettings.configured?'AI-запросы используют выбранный профиль и модель «'+esc(aiSettings.model||'')+'»'+(aiSettings.fallbackModel?' · запасная модель «'+esc(aiSettings.fallbackModel)+'».':'.'):'Сохраните несколько профилей с разными API-ключами и моделями. Один профиль будет активным для AI-запросов.')+'</p></div></div><p class="muted small ai-profile-security">Ключи шифруются на сервере и не возвращаются в браузер. Чтобы заменить ключ профиля, введите новый; пустое поле сохранит прежний.</p><div class="ai-profile-manager"><div class="ai-profile-list-column"><div class="ai-profile-list-heading"><h3>Профили OpenRouter</h3><button id="ai-profile-add" class="secondary-link" type="button">+ Новый профиль</button></div><div id="ai-profile-list" class="ai-profile-list">'+(profileCards||'<p class="muted small">Профилей пока нет. Создайте первый профиль справа.</p>')+'</div></div><form id="ai-settings-form" class="settings-form ai-profile-form"><input type="hidden" name="profileId" value="'+esc(initialProfile?.id||'')+'"><label class="settings-field">Название профиля<input name="profileName" required maxlength="64" value="'+esc(initialProfile?.name||'')+'" placeholder="Например, Основной"></label><label class="settings-field">API-ключ OpenRouter<input type="password" name="apiKey" autocomplete="off" maxlength="512" placeholder="'+(initialProfile?'Ключ сохранён · пустое поле оставит его без изменений':'sk-or-v1-…')+'" spellcheck="false" '+(initialProfile?'':'required')+'></label><label class="settings-field">ID основной модели<input type="text" name="model" required maxlength="128" value="'+esc(initialProfile?.model||'')+'" placeholder="provider/model-slug" autocomplete="off" spellcheck="false"></label><label class="settings-field">ID резервной модели · необязательно<input type="text" name="fallbackModel" maxlength="128" value="'+esc(initialProfile?.fallbackModel||'')+'" placeholder="provider/fallback-model" autocomplete="off" spellcheck="false"></label><p class="muted small">При временной ошибке или лимите основной модели запрос повторится через резервную. OpenRouter может начислить стоимость за каждый запрос. Пустое поле отключает резервную модель.</p><p class="muted small">Ключ передаётся серверу вашего проекта (на production — по HTTPS), шифруется и не попадает в Project Data или историю ревизий.</p><div class="settings-form-actions"><button id="ai-profile-save" class="primary" type="submit">'+(initialProfile?'Сохранить изменения':'Создать профиль')+'</button><button id="ai-profile-activate" class="secondary-link" type="button" '+(!initialProfile||initialProfile.active?'hidden':'')+'>Использовать для AI</button><button id="ai-profile-delete" class="secondary-link danger-action" type="button" '+(initialProfile?'':'hidden')+'>Удалить профиль</button><span id="ai-settings-status" class="muted small" role="status">'+esc(aiSettings.error||'Профили AI · версия '+aiSettings.version)+'</span></div></form></div><a class="secondary-link ai-editor-link" href="/projects/'+id+'/pages/'+encodeURIComponent(project.document.data.pages[0].id)+'/edit">Перейти к редактору AI-плана</a></section>';
  }
  else body=`<div class="settings-notice settings-planned"><span class="settings-notice-icon">◷</span><div><strong>${selected[0]==='domain'?'Подключение домена пока недоступно':'Раздел пока не подключён'}</strong><p>${selected[0]==='domain'?'Публичная публикация, DNS-проверка, маршрутизация и выпуск SSL ещё не реализованы. Сайт не будет показан как подключённый без проверки.':'Для этого раздела пока нет сохранения и backend-интеграции. Форма появится вместе с рабочей серверной поддержкой.'}</p><span class="status-pill">Не реализовано</span></div></div>`;
  app.innerHTML=`<div class="dashboard project-dashboard settings-dashboard"><aside class="dash-sidebar"><a class="brand" href="/">${mark} Juzimder <span>AI</span></a><div class="workspace-label">ПРОЕКТ</div><a class="project-side-link" href="/projects/${id}">▦ <span>Страницы</span></a><a class="nav-active" href="/projects/${id}/settings">⚙ <span>Настройки сайта</span></a>${settingsNav}<div class="sidebar-note"><span class="status-dot"></span> FOUNDATION<div class="version-label">Версия продукта <strong>${productVersion}</strong></div><p>${esc(project.name)}</p></div><a class="text-button" href="/">← Все проекты</a><button id="logout" class="text-button">Выйти из аккаунта ↗</button></aside><main class="dash-main settings-main"><header class="dash-top"><span><a href="/">Мои проекты</a><span class="slash">/</span><a href="/projects/${id}">${esc(project.name)}</a><span class="slash">/</span>Настройки сайта</span><span class="version-label">Версия ${productVersion}</span></header><section class="project-intro"><p class="eyebrow">НАСТРОЙКИ ПРОЕКТА</p><h1>Настройки сайта<span class="accent">.</span></h1><p class="muted">Общие параметры проекта и подключённые возможности.</p></section><div class="settings-layout"><section class="settings-content"><div class="section-title-row"><div><p class="eyebrow">${esc(selected[1].toUpperCase())}</p><h2>${esc(selected[1])}</h2></div><span id="settings-revision" class="version-label">Проект · ревизия ${project.version}</span></div>${body}</section></div><footer class="dash-footer"><span>YOUR IDEAS, TAKING SHAPE.</span><span>GrapesJS Core · PHP · SQLite</span></footer></main></div><dialog id="confirm-dialog"><form method="dialog"><p id="confirm-message"></p><div class="dialog-actions"><button value="cancel">Отмена</button><button id="confirm-submit" class="primary" value="confirm">Продолжить</button></div></form></dialog>`;
  const settingsForm=$('#design-tokens-form')||$('#font-tokens-form');
  if(settingsForm)settingsForm.onsubmit=async event=>{event.preventDefault();const button=settingsForm.querySelector('[type="submit"]'),status=$('#settings-save-status');button.disabled=true;status.textContent='Сохранение…';try{const nextTokens=normalizeDesignTokens({...project.settings.designTokens,...Object.fromEntries(new FormData(settingsForm))});project=await api(`/projects/${id}/settings`,'PUT',{version:project.version,designTokens:nextTokens});$('#settings-revision').textContent=`Проект · ревизия ${project.version}`;status.textContent=`Сохранено · ревизия ${project.version}`;}catch(error){status.textContent=error.message;}finally{button.disabled=false;}};
  const seoPageSelect=$('#seo-page-select');if(seoPageSelect)seoPageSelect.onchange=()=>{const url=new URL(location.href);url.searchParams.set('section','seo');url.searchParams.set('page',seoPageSelect.value);location.assign(url);};
  const seoForm=$('#seo-settings-form');if(seoForm)seoForm.onsubmit=async event=>{event.preventDefault();const button=seoForm.querySelector('[type="submit"]'),status=$('#seo-save-status');button.disabled=true;status.textContent='Сохранение SEO…';try{const values=Object.fromEntries(new FormData(seoForm)),pageId=values.pageId;delete values.pageId;project=await api(`/projects/${id}/settings`,'PUT',{version:project.version,seo:{pageId,...values}});$('#settings-revision').textContent=`Проект · ревизия ${project.version}`;status.textContent=`SEO сохранено · ревизия ${project.version}`;}catch(error){status.textContent=error.message;}finally{button.disabled=false;}};
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
      activateButton.hidden=!profile||profile.active;deleteButton.hidden=!profile;
      $('#ai-settings-heading').textContent=aiSettings.configured?'Активный профиль: '+(aiSettings.profiles.find(item=>item.id===aiSettings.activeProfileId)?.name||'OpenRouter'):'Добавьте профиль OpenRouter';
      $('#ai-settings-intro').textContent=aiSettings.configured?'AI-запросы используют выбранный профиль и модель «'+aiSettings.model+'»'+(aiSettings.fallbackModel?' · запасная модель «'+aiSettings.fallbackModel+'».':'.'):'Сохраните несколько профилей с разными API-ключами и моделями. Один профиль будет активным для AI-запросов.';
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
  }
  $('#logout').onclick=async()=>{await api('/logout','POST',{});session=await api('/session');auth();};
}
async function openEditor(id,pageId=null) {
  current=await api(`/projects/${id}`);dirty=false; loading=true;
  const { default: grapesjs } = await import('grapesjs');
  saving=false;saveAgain=false;conflicted=false;savePromise=undefined;clearTimeout(saveTimer);saveTimer=undefined;
  app.innerHTML=`<main class="editor-shell" data-open-panel="none" data-inspector-open="true"><header class="editor-top"><button id="back" class="brand brand-compact" aria-label="К страницам проекта" title="К страницам проекта">${mark}</button><span class="project-name">${esc(current.name)}</span><span class="divider"></span><select id="pages" aria-label="Страница"></select><button id="add-page" class="icon-button" title="Добавить страницу">+</button><div class="device-switch" role="group" aria-label="Размер экрана"><button data-device="Desktop" class="active">Desktop</button><button data-device="Tablet">Tablet</button><button data-device="Mobile">Mobile</button></div><div class="top-actions"><button id="undo" title="Отменить (⌘Z / Ctrl+Z)" aria-label="Отменить" aria-keyshortcuts="Control+Z Meta+Z">↶</button><button id="redo" title="Повторить (⌘⇧Z / Ctrl+Shift+Z)" aria-label="Повторить" aria-keyshortcuts="Control+Shift+Z Meta+Shift+Z Control+Y">↷</button><button id="history">История</button><button id="theme" title="Настроить цвета, типографику и скругления">Тема</button><button id="export">Экспорт JSON</button><button id="static-export" title="Скачать все сохранённые страницы, SEO и стили в статическом ZIP">ZIP сайта</button><button id="save" class="primary" title="Сохранить (⌘S / Ctrl+S)" aria-keyshortcuts="Control+S Meta+S">Сохранить</button></div></header><nav class="mobile-workspace-tabs" role="group" aria-label="Панели редактора"><button type="button" data-mobile-panel="canvas" aria-pressed="true">Холст</button><button type="button" data-mobile-panel="library" aria-pressed="false">Секции</button><button type="button" data-mobile-panel="inspector" aria-pressed="false">Свойства</button></nav><nav class="editor-floating-tools" aria-label="Инструменты страницы"><button id="toggle-layers" type="button" data-open-panel="layers" aria-label="Слои" title="Слои" aria-pressed="false"><span class="layers-icon" aria-hidden="true">≡</span></button><button id="toggle-library" type="button" data-open-panel="library" aria-label="Добавить секцию" title="Добавить секцию" aria-pressed="false">+</button></nav><aside class="editor-left"><div class="panel-tabs"><button data-panel="library" class="active">Секции</button><button data-panel="layers">Слои</button></div><section id="library"><div class="panel-heading panel-heading-with-close"><div><h2>Библиотека</h2><p>Добавьте секцию на страницу</p></div><button type="button" class="panel-close" data-close-panel aria-label="Закрыть библиотеку">×</button></div><div class="block-list">${blocks.map((b,i)=>`<button data-block="${b.id}" class="block-card"><div class="block-thumbnail thumb-${i} thumb-${b.id}"><div></div><i></i><span></span></div><strong>${b.label}</strong><small>${b.description}</small><b>+</b></button>`).join('')}</div><div class="library-foot">Структурные компоненты<br>Каждый элемент можно изменить</div></section><section id="layers" hidden><div class="panel-heading panel-heading-with-close"><div><h2>Слои</h2><p>Структура выбранной страницы</p></div><button type="button" class="panel-close" data-close-panel aria-label="Закрыть слои">×</button></div><div id="layer-manager"></div></section></aside><section class="editor-center"><div class="canvas-toolbar"><span id="page-label">ГЛАВНАЯ</span><span id="canvas-size">Адаптивный холст</span><button id="preview">Предпросмотр ↗</button></div><div class="canvas-wrap"><div id="gjs"></div><div class="canvas-empty" id="canvas-empty"><div class="empty-shapes"><i></i><i></i><i></i></div><p>Здесь начинается ваш сайт</p><span>Добавьте первый экран из библиотеки слева</span><button id="start-hero">+ Добавить Hero</button></div></div></section><aside class="editor-right"><div class="panel-heading panel-heading-with-close"><div><p class="eyebrow">ИНСПЕКТОР</p><h2 id="selected-name">Выберите элемент</h2></div><button type="button" id="close-inspector" class="panel-close" aria-label="Закрыть инспектор">×</button></div><div class="inspector-hint" id="selection-hint">Нажмите на элемент в холсте, чтобы изменить его оформление.</div><section id="quick-edit" hidden><div id="text-edit"><label>Текст<textarea id="component-text" rows="3"></textarea></label><button id="apply-text">Применить текст</button></div><label>Режим компоновки<select id="layout"><option value="block">Обычный поток</option><option value="flex">Flex</option><option value="grid">Grid</option></select></label><p class="muted small">Меняется только display. Остальные настройки сохраняются; доступна отмена.</p><section class="size-controls" aria-label="Адаптивные размеры"><h3>Размер и ограничения</h3><div class="size-control-grid"><label>Ширина<select id="width-mode" aria-label="Режим ширины"><option value="auto">Авто</option><option value="fit-content">Hug</option><option value="100%">Fill</option><option value="custom" disabled>CSS вручную</option></select></label><label>Высота<select id="height-mode" aria-label="Режим высоты"><option value="auto">Hug</option><option value="100%">Fill</option><option value="custom" disabled>CSS вручную</option></select></label></div><div class="size-constraint-grid"><label>Min. ширина<input id="min-width" type="text" inputmode="text" maxlength="32" placeholder="320px" aria-label="Минимальная ширина"></label><label>Max. ширина<input id="max-width" type="text" inputmode="text" maxlength="32" placeholder="80rem" aria-label="Максимальная ширина"></label><label>Min. высота<input id="min-height" type="text" inputmode="text" maxlength="32" placeholder="240px" aria-label="Минимальная высота"></label><label>Max. высота<input id="max-height" type="text" inputmode="text" maxlength="32" placeholder="none" aria-label="Максимальная высота"></label></div><button type="button" id="apply-size-constraints">Применить min/max</button><p class="muted small">Hug следует содержимому; Fill задаёт 100%. Размеры: px, %, rem, em, vw, vh. Пустое поле снимает ограничение.</p></section></section><div id="traits"></div><div id="flex-controls" class="flex-controls" hidden>${flexControlMarkup}</div><div id="styles"></div><section class="ai-plan-panel" aria-labelledby="ai-plan-panel-title"><p class="eyebrow">AI-ПЛАНИРОВАНИЕ</p><h3 id="ai-plan-panel-title">Структура сайта</h3><p>Перед открытием AI незаписанные правки сохраняются. Секции не добавляются до вашего подтверждения.</p><button type="button" id="ai-plan-open" class="primary">Составить план</button><p id="ai-panel-status" class="muted small" role="status">Проверим доступность AI-сервера.</p></section></aside><footer class="editor-status"><span><i class="status-dot"></i> <span id="save-status">Ревизия ${current.version} · Сохранено на сервере</span></span><span id="notice" role="status"></span><span class="status-actions"><button id="reload-server" hidden>Загрузить версию сервера</button><span class="version-label editor-version" aria-label="Версия продукта ${productVersion}">Версия ${productVersion}</span></span></footer></main><dialog id="confirm-dialog"><form method="dialog"><p id="confirm-message"></p><div class="dialog-actions"><button value="cancel">Отмена</button><button id="confirm-submit" class="primary" value="confirm">Продолжить</button></div></form></dialog><dialog id="history-dialog"><div class="history-head"><div><p class="eyebrow">СОХРАНЁННЫЕ СОСТОЯНИЯ</p><h2>История проекта</h2><p class="muted">Восстановление создаст новую ревизию. Текущая история останется доступной.</p></div><button id="close-history" aria-label="Закрыть">×</button></div><div id="history-list" class="history-list"></div><p id="history-error" role="status"></p></dialog><dialog id="image-dialog" aria-labelledby="image-dialog-title"><div class="asset-dialog-head"><h2 id="image-dialog-title">Изображения проекта</h2><button type="button" id="close-image" aria-label="Закрыть">×</button></div><p class="muted asset-dialog-note">Загруженные файлы доступны только владельцу проекта. PNG, JPEG, GIF или WebP · до 4 МБ на файл · хранилище проекта 20 МБ.</p><form id="image-form"><h3>Добавить внешний адрес</h3><label>HTTPS-адрес изображения<input name="src" type="url" pattern="https://.*" required placeholder="https://example.com/image.jpg"></label><label>Описание<input name="alt" maxlength="200"></label><div class="dialog-actions"><button type="button" id="cancel-image">Отмена</button><button class="primary">Добавить адрес</button></div></form><section class="asset-manager" aria-label="Управление изображениями проекта"><h3>Загрузить изображение</h3><div class="asset-upload-row"><input id="asset-file" type="file" accept="image/png,image/jpeg,image/gif,image/webp" aria-label="Файл изображения"><button type="button" id="upload-asset">Загрузить</button></div><p id="asset-status" class="muted small" role="status" aria-live="polite"></p><div id="asset-library" class="asset-library" aria-live="polite"></div></section></dialog><dialog id="page-dialog"><form id="page-form"><h2>Новая страница</h2><label>Название<input name="name" required maxlength="80"></label><div class="dialog-actions"><button type="button" id="cancel-page">Отмена</button><button class="primary">Добавить</button></div></form></dialog><dialog id="preview-dialog"><div class="preview-head"><strong>Предпросмотр страницы</strong><button id="close-preview">Закрыть</button></div><iframe id="preview-frame" sandbox="" title="Предпросмотр сайта"></iframe></dialog><dialog id="ai-plan-dialog" aria-labelledby="ai-plan-title"><div class="ai-plan-head"><div><p class="eyebrow">AI-ПЛАНИРОВАНИЕ</p><h2 id="ai-plan-title">План сайта</h2></div><button type="button" id="close-ai-plan" aria-label="Закрыть">×</button></div><p class="muted ai-disclosure">На OpenRouter будет отправлено только описание из этого поля. Данные проекта и содержимое страницы не передаются. Незаписанные правки редактора сохраняются перед открытием этой формы; новые AI-секции добавятся только после вашего подтверждения.</p><p id="ai-provider-status" class="muted small" role="status">Проверяем настройки AI проекта…</p><a id="ai-settings-link" class="secondary-link" href="/projects/${id}/settings?section=ai" hidden>Настроить ключ в «Настройки сайта → AI Engine»</a><form id="ai-plan-form"><label for="ai-prompt">Опишите сайт, аудиторию и задачу<textarea id="ai-prompt" name="prompt" rows="4" maxlength="4000" required placeholder="Например: сайт семейной кофейни в Алматы, для жителей района, уютный стиль, цель — рассказать о меню и получить заказы"></textarea></label><div class="dialog-actions"><button type="submit" id="ai-generate" class="primary" disabled>Составить план</button></div></form><p id="ai-plan-status" class="muted small" role="status" aria-live="polite"></p><section id="ai-image-slot-chooser" class="ai-image-slot-chooser" hidden aria-label="Изображения для AI-дизайна"><h3>Выберите изображения из библиотеки проекта</h3><p class="muted small">Файлы и названия остаются в проекте. OpenRouter получает только текстовое описание.</p><div id="ai-image-slot-list"></div><div class="ai-image-upload"><label for="ai-image-upload-file">Добавить изображение</label><input id="ai-image-upload-file" type="file" accept="image/png,image/jpeg,image/gif,image/webp" aria-label="Выбрать изображение для библиотеки проекта"><button type="button" id="ai-image-upload-button">Загрузить</button></div><p id="ai-image-upload-status" class="muted small" role="status" aria-live="polite"></p></section><section id="ai-plan-preview" class="ai-plan-preview" hidden aria-label="Предпросмотр плана"></section></dialog><dialog id="theme-dialog" aria-labelledby="theme-title"><form id="theme-form"><p class="eyebrow">ДИЗАЙН-СИСТЕМА ПРОЕКТА</p><h2 id="theme-title">Глобальные токены</h2><p class="muted">Изменения применятся к готовым секциям на всех страницах.</p><div class="theme-color-grid">${themeColorInputs}</div><div class="theme-options"><label>Шрифт заголовков<select name="headingFontFamily">${themeFontOptions}</select></label><label>Шрифт основного текста<select name="bodyFontFamily">${themeFontOptions}</select></label><label>Скругление карточек<select name="radius"><option value="0px">0 px</option><option value="4px">4 px</option><option value="8px">8 px</option><option value="12px">12 px</option><option value="16px">16 px</option><option value="24px">24 px</option><option value="32px">32 px</option></select></label></div><p class="muted small">Токены сохраняются в проекте и входят в историю версий.</p><div class="dialog-actions"><button type="button" id="cancel-theme">Отмена</button><button class="primary">Применить</button></div></form></dialog>`;
  editor=grapesjs.init({container:'#gjs',height:'100%',width:'auto',storageManager:false,noticeOnUnload:false,panels:{defaults:[]},allowScripts:false,canvas:{scripts:[],styles:[]},layerManager:{appendTo:'#layer-manager'},traitManager:{appendTo:'#traits'},selectorManager:{componentFirst:true},styleManager:{appendTo:'#styles',sectors:[{name:'Компоновка',open:true,properties:['display','flex-direction','justify-content','align-items','flex-wrap','gap','grid-template-columns']},{name:'Размеры и отступы',open:false,properties:['padding','margin']},{name:'Типографика',open:false,properties:['font-family','font-size','font-weight','line-height','color','text-align']},{name:'Оформление',open:false,properties:['background-color','border','border-radius','opacity']}]},deviceManager:{devices:[{id:'Desktop',name:'Desktop',width:''},{id:'Tablet',name:'Tablet',width:'768px',widthMedia:'992px'},{id:'Mobile',name:'Mobile',width:'375px',widthMedia:'576px'}]}});
  $('#ai-plan-open').textContent='AI-дизайн';
  $('.ai-plan-panel .eyebrow').textContent='AI-ДИЗАЙН';
  $('#ai-plan-panel-title').textContent='Сгенерировать страницу';
  $('.ai-plan-panel>p:not(.eyebrow)').textContent='Опишите идею — получите HTML и CSS, которые можно редактировать в GrapesJS.';
  $('#ai-plan-title').textContent='Дизайн страницы';
  $('#ai-plan-dialog .eyebrow').textContent='AI-ГЕНЕРАЦИЯ';
  $('#ai-generate').textContent='Создать дизайн';
  $('.ai-disclosure').textContent='В OpenRouter отправится только описание сайта из этого поля. Содержимое проекта не передаётся. Если ответ нарушит формат или правила безопасности, система выполнит одну исправляющую попытку с тем же описанием. Каждая попытка проходит одинаковую серверную проверку; если повтор понадобится, расход токенов суммируется. Перед импортом вы увидите изолированный предпросмотр.';
  editor.Components.addType('image',{model:{defaults:{traits:['alt','src']}}});
  registerBlocks(editor);
  const libraryFilter=document.createElement('div');libraryFilter.className='library-filter';libraryFilter.innerHTML=`<label><span class="visually-hidden">Поиск секций и элементов</span><input id="block-search" type="search" placeholder="Поиск секций и элементов" autocomplete="off"></label><div class="library-categories" role="group" aria-label="Категории библиотеки">${[['all','Все'],['Секции','Секции'],['Элементы','Элементы'],['Компоновка','Компоновка'],['Медиа','Медиа'],['Навигация','Навигация']].map(([id,label],index)=>`<button type="button" data-library-category="${id}" aria-pressed="${index===0}">${label}</button>`).join('')}</div>`;
  $('#library .panel-heading').after(libraryFilter);
  const libraryEmpty=document.createElement('p');libraryEmpty.className='library-empty';libraryEmpty.textContent='Ничего не найдено. Измените поиск или категорию.';libraryEmpty.hidden=true;$('.block-list').after(libraryEmpty);
  const filterLibrary=()=>{const query=$('#block-search').value.trim().toLocaleLowerCase('ru'),category=$('.library-categories [aria-pressed="true"]')?.dataset.libraryCategory||'all';let visible=0;for(const card of document.querySelectorAll('.block-card')){const matchesCategory=category==='all'||card.dataset.category===category,matchesQuery=!query||card.textContent.toLocaleLowerCase('ru').includes(query);card.hidden=!(matchesCategory&&matchesQuery);if(!card.hidden)visible++;}libraryEmpty.hidden=visible>0;};
  for(const card of document.querySelectorAll('.block-card'))card.dataset.category=blockCategory(card.dataset.block);
  $('#block-search').oninput=filterLibrary;
  for(const button of document.querySelectorAll('[data-library-category]'))button.onclick=()=>{for(const sibling of document.querySelectorAll('[data-library-category]'))sibling.setAttribute('aria-pressed',String(sibling===button));filterLibrary();};
  const syncThemeForm=()=>{
    const wrapper=editor.Pages.getSelected()?.getMainComponent();
    const tokens=normalizeDesignTokens(current.settings?.designTokens||designTokensFromStyle(wrapper?.getStyle()||{}));
    for(const field of TOKEN_FIELDS){const control=$(`#theme-form [name="${field.name}"]`);if(control)control.value=tokens[field.name];}
  };
  let aiDesignState=null,aiPreviewRenderId=0;
  const aiPreviewObjectUrls=new Map();
  const clearAIPreviewUrls=()=>{aiPreviewRenderId++;for(const url of aiPreviewObjectUrls.values())URL.revokeObjectURL(url);aiPreviewObjectUrls.clear();};
  const getAIPreviewUrl=async asset=>{
    if(aiPreviewObjectUrls.has(asset.id))return aiPreviewObjectUrls.get(asset.id);
    const response=await fetch(asset.url,{credentials:'same-origin'});if(!response.ok)throw new Error('Не удалось загрузить изображение из библиотеки проекта.');
    const url=URL.createObjectURL(await response.blob());aiPreviewObjectUrls.set(asset.id,url);return url;
  };
  const updateAIDesignFrame=async()=>{
    if(!aiDesignState)return;const frame=$('#ai-design-preview-frame');if(!frame)return;
    const renderId=++aiPreviewRenderId,design=aiDesignState.design,markup=new DOMParser().parseFromString(design.html,'text/html');
    for(const slot of markup.querySelectorAll('[data-image-slot]')){
      const asset=aiDesignState.assets.find(item=>item.id===aiDesignState.imageAssignments[slot.dataset.imageSlot]);if(!asset)continue;
      const image=markup.createElement('img');image.src=await getAIPreviewUrl(asset);image.alt=aiDesignState.imageAlt[slot.dataset.imageSlot]||'';slot.replaceChildren(image);
    }
    if(renderId!==aiPreviewRenderId||!frame.isConnected)return;
    const csp="default-src 'none'; script-src 'none'; connect-src 'none'; img-src blob:; font-src 'none'; style-src 'unsafe-inline'; object-src 'none'; base-uri 'none'; form-action 'none'";
    frame.srcdoc='<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="Content-Security-Policy" content="'+csp+'"><style>*,*::before,*::after{box-sizing:border-box}body{margin:0}'+design.css+'</style></head><body>'+markup.body.innerHTML+'</body></html>';
  };
  const renderAIImageSlots=()=>{
    const chooser=$('#ai-image-slot-chooser'),list=$('#ai-image-slot-list');list.replaceChildren();
    const slots=aiDesignState?.design.imageSlots||[];chooser.hidden=!slots.length;if(!slots.length)return;
    for(const slot of slots){
      const card=document.createElement('article');card.className='ai-image-slot-card';
      const thumbnail=document.createElement('img');thumbnail.className='ai-image-slot-thumb';thumbnail.alt='';thumbnail.hidden=true;
      const fields=document.createElement('div');fields.className='ai-image-slot-fields';
      const label=document.createElement('label');label.textContent=slot.label;
      const select=document.createElement('select');select.setAttribute('aria-label',slot.label+' из библиотеки проекта');
      const empty=document.createElement('option');empty.value='';empty.textContent='Оставить без изображения';select.append(empty);
      for(const asset of aiDesignState.assets){const option=document.createElement('option');option.value=asset.id;option.textContent=asset.name;select.append(option);}
      select.value=aiDesignState.imageAssignments[slot.id]||'';
      const alt=document.createElement('input');alt.type='text';alt.maxLength=200;alt.placeholder='Описание для доступности';alt.setAttribute('aria-label','Описание '+slot.label);alt.value=aiDesignState.imageAlt[slot.id]||'';
      const syncThumbnail=()=>{const asset=aiDesignState.assets.find(item=>item.id===select.value);if(asset){thumbnail.src=asset.url;thumbnail.hidden=false;}else{thumbnail.removeAttribute('src');thumbnail.hidden=true;}};
      select.onchange=()=>{aiDesignState.imageAssignments[slot.id]=select.value;syncThumbnail();updateAIDesignFrame().catch(error=>{$('#ai-plan-status').textContent=error.message;});};
      alt.oninput=()=>{aiDesignState.imageAlt[slot.id]=alt.value;updateAIDesignFrame().catch(error=>{$('#ai-plan-status').textContent=error.message;});};
      syncThumbnail();label.append(select);fields.append(label,alt);card.append(thumbnail,fields);list.append(card);
    }
  };
  const renderAIDesign=(design,metadata)=>{
    const preview=$('#ai-plan-preview');preview.replaceChildren();
    const facts=document.createElement('div');facts.className='ai-plan-facts';
    const title=document.createElement('p');title.textContent=design.siteType;facts.append(title);
    for(const [label,value] of [['Аудитория',design.audience],['Цель',design.goal],['Стиль',design.style]]){
      const row=document.createElement('p'),caption=document.createElement('span');caption.textContent=label;row.append(caption,document.createTextNode(value));facts.append(row);
    }
    preview.append(facts);
    const frame=document.createElement('iframe');frame.id='ai-design-preview-frame';frame.className='ai-design-preview-frame';frame.title='Предпросмотр AI-дизайна';frame.setAttribute('sandbox','');
    preview.append(frame);
    renderAIImageSlots();updateAIDesignFrame().catch(error=>{$('#ai-plan-status').textContent=error.message;});
    const note=document.createElement('p');note.className='muted small';note.textContent='Секций: '+design.sectionCount+'. Выбранные изображения загружаются только из библиотеки этого проекта и не передаются OpenRouter. После импорта их можно редактировать в GrapesJS.';preview.append(note);
    const usage=metadata?.usage||{},usageParts=[];
    for(const [key,label] of [['promptTokens','входные токены'],['completionTokens','выходные токены'],['totalTokens','всего токенов']])if(Number.isInteger(usage[key])&&usage[key]>=0)usageParts.push(label+': '+usage[key]);
    if(typeof usage.costUsd==='number'&&Number.isFinite(usage.costUsd)&&usage.costUsd>=0)usageParts.push('стоимость: $'+usage.costUsd.toFixed(6));
    const model=typeof metadata?.resolvedModel==='string'?metadata.resolvedModel:'модель не указана';
    const meta=document.createElement('p');meta.className='muted small ai-plan-metadata';meta.textContent=model+(usageParts.length?' · '+usageParts.join(' · '):'');preview.append(meta);
    const actions=document.createElement('div');actions.className='dialog-actions';
    const apply=document.createElement('button');apply.type='button';apply.id='ai-plan-apply';apply.className='primary';apply.textContent='Добавить дизайн · '+design.sectionCount+' секций';apply.onclick=applyAIDesign;
    actions.append(apply);preview.append(actions);preview.hidden=false;
  };
  const buildAIDesignHtml=()=>{
    const markup=new DOMParser().parseFromString(aiDesignState.design.html,'text/html');
    for(const slot of markup.querySelectorAll('[data-image-slot]')){
      const slotId=slot.dataset.imageSlot,asset=aiDesignState.assets.find(item=>item.id===aiDesignState.imageAssignments[slotId]);slot.removeAttribute('data-image-slot');slot.replaceChildren();
      if(!asset)continue;
      const image=markup.createElement('img');image.src=asset.url;image.alt=aiDesignState.imageAlt[slotId]||'';slot.append(image);
    }
    return markup.body.innerHTML;
  };
  const refreshAIAssets=async()=>{
    if(!aiDesignState)return;
    aiDesignState.assets=await api(`/projects/${id}/assets`);
    for(const slot of aiDesignState.design.imageSlots||[])if(!aiDesignState.assets.some(asset=>asset.id===aiDesignState.imageAssignments[slot.id]))aiDesignState.imageAssignments[slot.id]='';
    renderAIImageSlots();await updateAIDesignFrame();
  };
  $('#ai-image-upload-button').onclick=async()=>{
    const input=$('#ai-image-upload-file'),file=input.files?.[0],button=$('#ai-image-upload-button'),status=$('#ai-image-upload-status');
    if(!file){status.textContent='Сначала выберите файл изображения.';return;}
    if(file.size>4194304){status.textContent='Файл превышает лимит 4 МБ.';return;}
    button.disabled=true;status.textContent='Сохраняем файл в библиотеке проекта…';
    try{
      const response=await fetch(`/api/projects/${id}/assets`,{method:'POST',headers:{'Content-Type':'application/octet-stream','X-CSRF-Token':session.csrf,'X-Asset-Name':encodeURIComponent(file.name)},body:file});
      const data=await response.json();if(!response.ok)throw new Error(data.error||`HTTP ${response.status}`);
      input.value='';await refreshAIAssets();status.textContent='Изображение сохранено в библиотеке проекта. Выберите его в списке выше.';
    }catch(error){status.textContent=error.message;}
    finally{button.disabled=false;}
  };
  const applyAIDesign=async()=>{
    if(!aiDesignState)return;
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
        for(const [property,next] of Object.entries(defaults)){const previous=style[property];if(previous===undefined||typeof previous==='string'&&(previous.includes('--juzimder-font')||previous.includes('--juzimder-line-height')))updates[property]=next;}
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
  const refreshPages=()=>{const selected=editor.Pages.getSelected();$('#pages').innerHTML=editor.Pages.getAll().map(p=>`<option value="${esc(p.id)}" ${p===selected?'selected':''}>${esc(p.get('name')||p.id)}</option>`).join('');$('#page-label').textContent=selected?.get('name')||'Страница';refreshEmpty();};
  editor.on('load',()=>{editor.loadProjectData(current.document.data);if(pageId&&editor.Pages.getAll().some(page=>page.get('id')===pageId))editor.Pages.select(pageId);applyDesignTokens(currentDesignTokens());loading=false;editor.clearDirtyCount();refreshPages();syncThemeForm();});
  editor.on('update',()=>{
    if(!loading){dirty=JSON.stringify(editor.getProjectData())!==JSON.stringify(current.document.data);if(dirty){$('#save-status').textContent=conflicted?'Версия изменилась в другой вкладке':'Есть несохранённые изменения';if(!conflicted)scheduleAutosave();}}
    refreshEmpty();
  });
  const syncPageRoute=()=>{const page=editor.Pages.getSelected();if(page)history.replaceState({},'',`/projects/${id}/pages/${encodeURIComponent(page.get('id'))}/edit`);};
  editor.on('page:select page:add page:remove',()=>{refreshPages();syncThemeForm();syncPageRoute();});
  const syncSizeControls=component=>{const style=component.getStyle();const width=style.width||'auto';const height=style.height||'auto';$('#width-mode').value=['auto','fit-content','100%'].includes(width)?width:'custom';$('#height-mode').value=['auto','100%'].includes(height)?height:'custom';for(const property of ['min-width','max-width','min-height','max-height'])$(`#${property}`).value=style[property]||'';};
  const flexDefaults={'flex-direction':'row','justify-content':'flex-start','align-items':'stretch','flex-wrap':'nowrap'};
  const syncFlexControls=component=>{const style=component?.getStyle?.()||{};$('#flex-controls').hidden=!component;for(const button of document.querySelectorAll('[data-flex-property]')){const property=button.dataset.flexProperty,value=style[property]||flexDefaults[property];const active=value===button.dataset.flexValue;button.classList.toggle('active',active);button.setAttribute('aria-pressed',String(active));}};
  editor.on('component:selected',component=>{ $('#selected-name').textContent=component.get('type')==='wrapper'?'Body · Корень страницы':component.getName();$('#selection-hint').hidden=true;$('#quick-edit').hidden=false; const isText=['text','link','textnode'].includes(component.get('type'));$('#text-edit').hidden=!isText;$('#component-text').disabled=!isText;$('#apply-text').disabled=!isText;$('#component-text').value=isText?(component.getEl()?.textContent||component.get('content')||''):'';$('#layout').value=component.getStyle().display||'block';syncSizeControls(component);syncFlexControls(component);setEditorPanel('inspector'); });
  editor.on('component:deselected',()=>{if(!editor.getSelected()){ $('#selected-name').textContent='Выберите элемент';$('#selection-hint').hidden=false;$('#quick-edit').hidden=true;syncFlexControls(null);}});
  editor.on('component:update:style',component=>{if(editor.getSelected()===component){syncSizeControls(component);syncFlexControls(component);}});
  const addBlock=id=>{if(id==='image'){$('#image-dialog').showModal();loadAssetLibrary();return;}const block=blocks.find(b=>b.id===id);const added=editor.addComponents(block.content());editor.select(added[0]);refreshEmpty();};
  for(const b of document.querySelectorAll('[data-block]'))b.onclick=()=>addBlock(b.dataset.block);
  const insertImage=(src,alt='')=>{const node=blocks.find(b=>b.id==='image').content();node.src=src;node.attributes={alt};const added=editor.addComponents(node);editor.select(added[0]);refreshEmpty();$('#image-dialog').close();};
  const loadAssetLibrary=async()=>{const container=$('#asset-library');container.replaceChildren();$('#asset-status').textContent='Загружаем изображения…';try{const assets=await api(`/projects/${id}/assets`);if(!assets.length){$('#asset-status').textContent='В проекте пока нет загруженных изображений.';return;}$('#asset-status').textContent=`Сохранено изображений: ${assets.length}`;for(const asset of assets){const card=document.createElement('article');card.className='asset-card';const image=document.createElement('img');image.src=asset.url;image.alt='';image.loading='lazy';const details=document.createElement('div');details.className='asset-card-info';const name=document.createElement('strong');name.textContent=asset.name;const meta=document.createElement('small');meta.textContent=`${asset.width} × ${asset.height} · ${(asset.size/1024).toFixed(1)} КБ`;details.append(name,meta);const insert=document.createElement('button');insert.type='button';insert.className='asset-insert';insert.dataset.assetUrl=asset.url;insert.textContent='Вставить';card.append(image,details,insert);container.append(card);}}catch(error){$('#asset-status').textContent=error.message;}};
  $('#asset-library').onclick=e=>{const button=e.target.closest('[data-asset-url]');if(button)insertImage(button.dataset.assetUrl,'');};
  $('#cancel-image').onclick=$('#close-image').onclick=()=>$('#image-dialog').close();
  $('#image-dialog').addEventListener('close',()=>$('#image-form').reset());
  $('#image-form').onsubmit=e=>{e.preventDefault();const form=new FormData(e.target);insertImage(form.get('src'),form.get('alt'));};
  $('#upload-asset').onclick=async()=>{const input=$('#asset-file'),file=input.files?.[0],button=$('#upload-asset');if(!file){$('#asset-status').textContent='Сначала выберите файл изображения.';return;}if(file.size>4194304){$('#asset-status').textContent='Файл превышает лимит 4 МБ.';return;}button.disabled=true;$('#asset-status').textContent='Сохраняем изображение в проекте…';try{const response=await fetch(`/api/projects/${id}/assets`,{method:'POST',headers:{'Content-Type':'application/octet-stream','X-CSRF-Token':session.csrf,'X-Asset-Name':encodeURIComponent(file.name)},body:file});const data=await response.json();if(!response.ok)throw new Error(data.error||`HTTP ${response.status}`);input.value='';await loadAssetLibrary();notify('Изображение сохранено в проекте');}catch(error){$('#asset-status').textContent=error.message;}finally{button.disabled=false;}};
  $('#start-hero').onclick=()=>addBlock('hero');
  for(const b of document.querySelectorAll('[data-panel]'))b.onclick=()=>setEditorPanel(b.dataset.panel);
  for(const b of document.querySelectorAll('.editor-floating-tools [data-open-panel]'))b.onclick=()=>setEditorPanel($('.editor-shell').dataset.openPanel===b.dataset.openPanel?'none':b.dataset.openPanel);
  for(const b of document.querySelectorAll('[data-close-panel]'))b.onclick=()=>setEditorPanel('none');
  $('#close-inspector').onclick=()=>{$('.editor-shell').dataset.inspectorOpen='false';};
  for(const b of document.querySelectorAll('.mobile-workspace-tabs [data-mobile-panel]'))b.onclick=()=>setEditorPanel(b.dataset.mobilePanel);
  for(const b of document.querySelectorAll('[data-flex-property]'))b.onclick=()=>{const component=editor.getSelected();if(!component)return;component.addStyle({[b.dataset.flexProperty]:b.dataset.flexValue});syncFlexControls(component);};
  for(const b of document.querySelectorAll('[data-device]'))b.onclick=()=>{editor.setDevice(b.dataset.device);for(const btn of document.querySelectorAll('[data-device]'))btn.classList.toggle('active',btn===b);$('#canvas-size').textContent=b.dataset.device==='Mobile'?'375 px':b.dataset.device==='Tablet'?'768 px':'Адаптивный холст';};
  $('#theme').onclick=()=>{syncThemeForm();$('#theme-dialog').showModal();};
  $('#cancel-theme').onclick=()=>$('#theme-dialog').close();
  $('#theme-form').onsubmit=e=>{e.preventDefault();try{applyDesignTokens(Object.fromEntries(new FormData(e.target)));$('#theme-dialog').close();notify('Тема применена ко всем страницам');}catch(error){notify(error.message,true);}};
  $('#close-ai-plan').onclick=()=>$('#ai-plan-dialog').close();
  $('#ai-plan-dialog').addEventListener('close',()=>{aiDesignState=null;clearAIPreviewUrls();$('#ai-image-slot-chooser').hidden=true;$('#ai-image-slot-list').replaceChildren();$('#ai-plan-preview').replaceChildren();});
  $('#ai-plan-open').onclick=async()=>{
    const openButton=$('#ai-plan-open');openButton.disabled=true;
    try{
      if(conflicted)throw new Error('Сначала загрузите актуальную версию проекта с сервера.');
      if(dirty){await saveDocument(false);if(dirty||conflicted)throw new Error('Не удалось сохранить текущие изменения. План не применён.');}
      aiDesignState=null;clearAIPreviewUrls();$('#ai-image-slot-chooser').hidden=true;$('#ai-image-slot-list').replaceChildren();$('#ai-image-upload-status').textContent='';$('#ai-plan-preview').replaceChildren();$('#ai-plan-preview').hidden=true;$('#ai-plan-status').textContent='';$('#ai-provider-status').textContent='Проверяем настройку AI-сервера…';$('#ai-generate').disabled=true;
      $('#ai-plan-dialog').showModal();
  const status=await api(`/projects/${id}/ai/settings`);
      if(status.configured===true){$('#ai-generate').disabled=false;$('#ai-settings-link').hidden=true;$('#ai-provider-status').textContent=`OpenRouter настроен для проекта${status.model?` · ${status.model}`:''}. Запрос будет отправлен только после нажатия «Создать дизайн».`;$('#ai-panel-status').textContent='AI настроен для проекта.';}
      else{$('#ai-settings-link').hidden=false;$('#ai-provider-status').textContent='AI не настроен. Описание останется на этой странице и не будет отправлено.';$('#ai-panel-status').textContent='Задайте ключ в «Настройки сайта → AI Engine».';}
    }catch(error){$('#ai-settings-link').hidden=false;$('#ai-provider-status').textContent=error.message;$('#ai-panel-status').textContent=error.message;notify(error.message,true);}
    finally{openButton.disabled=false;}
  };
  $('#ai-plan-form').onsubmit=async e=>{
    e.preventDefault();if($('#ai-generate').disabled)return;
    const prompt=$('#ai-prompt').value.trim();if(!prompt)return;
    const button=$('#ai-generate');button.disabled=true;aiDesignState=null;clearAIPreviewUrls();$('#ai-image-slot-chooser').hidden=true;$('#ai-plan-preview').replaceChildren();$('#ai-plan-preview').hidden=true;$('#ai-plan-status').textContent='Создаём дизайн страницы. Проект пока не меняется.';
    try{
      const page=editor.Pages.getSelected();
      if(!page)throw new Error('Выберите страницу проекта.');
      const response=await api(`/projects/${id}/ai/generate-page`,'POST',{prompt});
      if(!response.design||typeof response.design.html!=='string'||typeof response.design.css!=='string')throw new Error('AI вернул неполный дизайн. Попробуйте уточнить описание.');
      let assets=[],assetWarning='';try{assets=await api(`/projects/${id}/assets`);}catch(error){assetWarning=' Не удалось загрузить библиотеку изображений: '+error.message;}
      const slots=response.design.imageSlots||[];
      aiDesignState={design:response.design,metadata:response.metadata,pageId:page.get('id'),assets,imageAssignments:Object.fromEntries(slots.map(slot=>[slot.id,''])),imageAlt:Object.fromEntries(slots.map(slot=>[slot.id,slot.label]))};
      renderAIDesign(response.design,response.metadata);$('#ai-plan-status').textContent='Проверьте предпросмотр. Страница изменится только после отдельного подтверждения.';
      if(assetWarning)$('#ai-plan-status').textContent+=assetWarning;
    }catch(error){$('#ai-plan-status').textContent=error.message;notify(error.message,true);}
    finally{button.disabled=false;}
  };
  $('#pages').onchange=e=>editor.Pages.select(e.target.value);
  $('#add-page').onclick=()=>$('#page-dialog').showModal();$('#cancel-page').onclick=()=>$('#page-dialog').close();
  $('#page-form').onsubmit=e=>{e.preventDefault();const page=editor.Pages.add({id:crypto.randomUUID(),name:new FormData(e.target).get('name'),component:{type:'wrapper',style:designTokensToStyle(currentDesignTokens()),components:[]}});editor.Pages.select(page);$('#page-dialog').close();e.target.reset();};
  $('#undo').onclick=()=>editor.UndoManager.undo();$('#redo').onclick=()=>editor.UndoManager.redo();
  $('#layout').onchange=e=>editor.getSelected()?.addStyle({display:e.target.value});
  $('#width-mode').onchange=e=>editor.getSelected()?.addStyle({width:e.target.value});$('#height-mode').onchange=e=>editor.getSelected()?.addStyle({height:e.target.value});$('#apply-size-constraints').onclick=()=>{const component=editor.getSelected();if(!component)return;const updates={},removals=[];for(const property of ['min-width','max-width','min-height','max-height']){const input=$(`#${property}`),value=input.value.trim();if(!value){removals.push(property);continue;}const valid=/^(?:\d+(?:\.\d+)?(?:px|%|rem|em|vw|vh)|auto|none)$/i.test(value)&&(property.startsWith('min-')?value.toLowerCase()!=='none':value.toLowerCase()!=='auto');if(!valid){input.focus();notify('Укажите размер с единицей: например 320px, 60% или 40rem.',true);return;}updates[property]=value;}for(const property of removals)component.removeStyle(property);if(Object.keys(updates).length)component.addStyle(updates);syncSizeControls(component);notify('Ограничения размеров применены');};
  $('#apply-text').onclick=()=>{const selected=editor.getSelected();if(selected){selected.components(esc($('#component-text').value));notify('Текст изменён');}};
  $('#save').onclick=()=>saveDocument(false);
  $('#reload-server').onclick=async()=>{
    if(!await askConfirmation('Локальные несохранённые изменения будут заменены серверной версией. Сначала можно скачать их через «Экспорт JSON». Продолжить?','Загрузить версию'))return;
    try{const latest=await api(`/projects/${current.id}`);current=latest;loading=true;editor.loadProjectData(latest.document.data);editor.clearDirtyCount();dirty=false;loading=false;conflicted=false;$('#reload-server').hidden=true;$('#save').disabled=false;refreshPages();syncThemeForm();$('#save-status').textContent=`Ревизия ${current.version} · Загружено с сервера`;notify('Серверная версия загружена');}
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
            current=restored;loading=true;editor.loadProjectData(restored.document.data);editor.clearDirtyCount();loading=false;dirty=false;conflicted=false;$('#reload-server').hidden=true;$('#save').disabled=false;refreshPages();syncThemeForm();$('#save-status').textContent=`Восстановлена ревизия ${revision.version} · новая ревизия ${current.version}`;$('#history-dialog').close();notify('Ревизия восстановлена');
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
    clearTimeout(saveTimer);saveTimer=undefined;editor.destroy();editor=null;dirty=false;location.assign(`/projects/${id}`);
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
(async()=>{try{session=await api('/session');if(!session.user){auth();return;}const path=location.pathname;let match=path.match(/^\/projects\/([a-f0-9]{24})\/pages\/([A-Za-z0-9_-]{1,128})\/edit$/);if(match){await openEditor(match[1],decodeURIComponent(match[2]));return;}match=path.match(/^\/projects\/([a-f0-9]{24})\/settings$/);if(match){await siteSettings(match[1]);return;}match=path.match(/^\/projects\/([a-f0-9]{24})$/);if(match){await projectPages(match[1]);return;}await dashboard();}catch(e){app.innerHTML='<main class="boot-error"><h1>Не удалось открыть пространство</h1><p></p><button onclick="location.reload()">Повторить</button></main>';$('.boot-error p').textContent=e.message;}})();
