const PREFIX='jzcc1.';
export const DEFAULT_CUSTOM_CODE=Object.freeze({html:'<section class="custom-code-example"><h2>Ваш HTML</h2><p>Отредактируйте этот блок в свойствах страницы.</p></section>',css:'.custom-code-example{padding:24px;border:1px solid #d8dfda;border-radius:12px;font:16px/1.5 system-ui,sans-serif}.custom-code-example h2{margin:0 0 8px}',js:''});
export const DEFAULT_TABS_CODE=Object.freeze({
  html:'<fieldset class="jz-tabs"><legend>Информационные вкладки</legend><input class="jz-tab-toggle" type="radio" id="jz-tab-about" name="jz-tab" checked><label for="jz-tab-about">Описание</label><input class="jz-tab-toggle" type="radio" id="jz-tab-details" name="jz-tab"><label for="jz-tab-details">Детали</label><input class="jz-tab-toggle" type="radio" id="jz-tab-terms" name="jz-tab"><label for="jz-tab-terms">Условия</label><div class="jz-tab-panels"><section class="jz-tab-panel"><h3>Описание</h3><p>Добавьте информацию о проекте или услуге.</p></section><section class="jz-tab-panel"><h3>Детали</h3><p>Уточните характеристики и важные особенности.</p></section><section class="jz-tab-panel"><h3>Условия</h3><p>Укажите подтверждённые условия и ограничения.</p></section></div></fieldset>',
  css:'.jz-tabs{display:flex;flex-wrap:wrap;gap:8px;border:0;padding:0;margin:0;font:16px/1.5 system-ui,sans-serif;color:#17231d}.jz-tab-toggle{position:absolute;width:1px;height:1px;opacity:0}.jz-tab-toggle+label{display:inline-flex;align-items:center;min-height:42px;padding:8px 16px;border:1px solid #c9d2cb;border-radius:999px;cursor:pointer}.jz-tab-toggle:checked+label{border-color:#44755c;background:#e8f2eb;color:#214c35}.jz-tab-toggle:focus-visible+label{outline:3px solid #78ac8a;outline-offset:2px}.jz-tab-panels{width:100%;margin-top:10px;padding:20px;border:1px solid #d8dfda;border-radius:12px}.jz-tab-panel{display:none}.jz-tab-panel h3{margin:0 0 8px}.jz-tab-panel p{margin:0}.jz-tab-toggle:nth-of-type(1):checked~.jz-tab-panels .jz-tab-panel:nth-child(1),.jz-tab-toggle:nth-of-type(2):checked~.jz-tab-panels .jz-tab-panel:nth-child(2),.jz-tab-toggle:nth-of-type(3):checked~.jz-tab-panels .jz-tab-panel:nth-child(3){display:block}' ,
  js:''
});

export function encodeCustomCode(value){
  const source=JSON.stringify({html:String(value?.html??''),css:String(value?.css??''),js:String(value?.js??'')});
  const bytes=new TextEncoder().encode(source);let binary='';
  for(let i=0;i<bytes.length;i+=0x8000)binary+=String.fromCharCode(...bytes.subarray(i,i+0x8000));
  return PREFIX+btoa(binary).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'');
}

export function decodeCustomCode(value){
  try{
    if(typeof value!=='string'||!value.startsWith(PREFIX))return {...DEFAULT_CUSTOM_CODE};
    const base64=value.slice(PREFIX.length).replace(/-/g,'+').replace(/_/g,'/');
    const binary=atob(base64+'='.repeat((4-base64.length%4)%4));
    const bytes=Uint8Array.from(binary,char=>char.charCodeAt(0));
    const parsed=JSON.parse(new TextDecoder().decode(bytes));
    if(!parsed||typeof parsed!=='object'||Array.isArray(parsed)||Object.keys(parsed).sort().join(',')!=='css,html,js')return {...DEFAULT_CUSTOM_CODE};
    return {html:String(parsed.html),css:String(parsed.css),js:String(parsed.js)};
  }catch{return {...DEFAULT_CUSTOM_CODE};}
}

const frameGenerations=new WeakMap();

export async function submitSandboxFrame({iframe,projectId,csrf,kind='custom-code',payload={}}){
  const generation=(frameGenerations.get(iframe)||0)+1;
  frameGenerations.set(iframe,generation);
  iframe.setAttribute('sandbox','allow-scripts');
  iframe.referrerPolicy='no-referrer';
  try{
    const body=new URLSearchParams({csrf,kind,...payload});
    const response=await fetch(`/api/projects/${encodeURIComponent(projectId)}/custom-code/sandbox`,{method:'POST',credentials:'same-origin',cache:'no-store',redirect:'error',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body});
    const result=await response.json();
    if(!response.ok)throw new Error(result.error||'Не удалось подготовить изолированный предпросмотр.');
    if(frameGenerations.get(iframe)!==generation||!iframe.isConnected)return false;
    const target=new URL(result.url,location.origin);
    const expectedPath=`/api/projects/${encodeURIComponent(projectId)}/custom-code/sandbox/`;
    if(target.origin!==location.origin||!target.pathname.startsWith(expectedPath))throw new Error('Сервер вернул недопустимый адрес предпросмотра.');
    iframe.src=target.href;
    return true;
  }catch(error){
    if(frameGenerations.get(iframe)!==generation||!iframe.isConnected)return false;
    const message=String(error?.message||'Ошибка предпросмотра').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
    iframe.srcdoc=`<!doctype html><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="default-src 'none'; style-src 'unsafe-inline'"><style>body{font:14px/1.5 system-ui,sans-serif;color:#823d32;padding:16px}</style><p>${message}</p>`;
    return false;
  }
}

export function clearSandboxFrame(iframe){
  if(!iframe)return;
  frameGenerations.set(iframe,(frameGenerations.get(iframe)||0)+1);
  iframe.src='about:blank';
}

const runtimeView=(projectId,csrf,kind)=>({
  init({model}){this.listenTo(model,'change:content change:attributes',()=>this.render());},
  removed(){clearSandboxFrame(this.el?.querySelector('iframe'));},
  onRender(){
    if(!this.el||!this.el.ownerDocument)return;
    const previous=this.el.querySelector('iframe');if(previous)clearSandboxFrame(previous);
    const doc=this.el.ownerDocument,iframe=doc.createElement('iframe');
    iframe.title=kind==='countdown'?'Обратный отсчёт':'Пользовательский код в изолированной песочнице';
    iframe.style.cssText='display:block;width:100%;height:320px;border:0;pointer-events:none';
    this.el.replaceChildren(iframe);
    const payload=kind==='countdown'?{deadline:this.model.getAttributes()['data-countdown-end']||'2027-01-01T00:00:00Z'}:decodeCustomCode(this.model.get('content'));
    submitSandboxFrame({iframe,projectId,csrf:csrf(),kind,payload});
  }
});

export function registerSafeRuntimeComponents(editor,{projectId,csrf}){
  editor.Components.addType('custom-code',{extendView:'default',model:{defaults:{tagName:'div',name:'Custom Code',content:encodeCustomCode(DEFAULT_CUSTOM_CODE),attributes:{class:'jz-custom-code'},style:{width:'100%','min-height':'180px'}}},view:runtimeView(projectId,csrf,'custom-code')});
  editor.Components.addType('countdown',{extendView:'default',model:{defaults:{tagName:'div',name:'Countdown',attributes:{class:'jz-countdown','data-countdown-end':'2027-01-01T00:00:00Z'},traits:[{type:'text',name:'data-countdown-end',label:'Дата окончания (UTC)'}],style:{width:'100%','min-height':'72px'}}},view:runtimeView(projectId,csrf,'countdown')});
}
