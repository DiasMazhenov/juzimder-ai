import { flexIconSpec } from './flex-icons.js';
import catalogue from '../resources/tabler-editor/catalogue.json';
import playerPlay from '../resources/tabler-editor/player-play.svg?raw';
import message from '../resources/tabler-editor/message.svg?raw';
import list from '../resources/tabler-editor/list.svg?raw';
import vectorBezier from '../resources/tabler-editor/vector-bezier.svg?raw';
import { normalizeEditorIconMarkup } from './editor-icon-markup.js';
const extraTablerIcons = Object.fromEntries(Object.entries({playerPlay,message,list,vectorBezier}).map(([name,source])=>[name.replace(/[A-Z]/g,letter=>`-${letter.toLowerCase()}`),source.slice(source.indexOf('<svg')).replace('<svg','<svg class="tabler-editor-icon" aria-hidden="true" focusable="false"')]));
export const tablerIcon = name => normalizeEditorIconMarkup(catalogue[name] || extraTablerIcons[name] || catalogue.square);
export const tablerCoreIcons = Object.fromEntries(Object.entries({close:'x',move:'arrows-move',plus:'plus',caret:'chevron-down',delete:'trash',copy:'copy',arrowUp:'arrow-up',chevron:'chevron-up',eye:'eye',eyeOff:'eye-off'}).map(([key,name])=>[key,tablerIcon(name)]));
export function installEditorIcons(editor) {
  const shell=document.body;
  const set=(selector,name,compact=false)=>{for(const node of shell.querySelectorAll(selector)){if(node.querySelector('.tabler-editor-icon'))continue;const label=node.getAttribute('aria-label')||node.title||node.textContent.trim();if(compact){node.setAttribute('aria-label',label);node.title ||= label;node.innerHTML=tablerIcon(name);}else node.insertAdjacentHTML('afterbegin',tablerIcon(name));}};
  const update=()=>{
    for(const action of editor.RichTextEditor.getAll()){const name=({bold:'bold',italic:'italic',underline:'underline',strikethrough:'strikethrough',link:'link',wrap:'typography'})[action.name];if(name){action.icon=tablerIcon(name);if(action.btn&&!action.btn.querySelector('.tabler-editor-icon'))action.btn.innerHTML=action.icon;}}
    set('.gjs-frame-wrapper__icon,.frame-wrapper__icon','layout-grid',true);
    set('#theme','settings',true);
  for(const [selector,name] of Object.entries({'#history':'history','#export':'code','#static-export':'download','#save':'device-floppy','#add-page':'plus','#undo':'arrow-back-up','#redo':'arrow-forward-up','#close-inspector':'x','.dialog-close':'x','button[id^="close-"]:not(#close-preview)':'x','[data-close-panel]':'x','#toggle-layers':'stack-2','#toggle-library':'plus','#component-actions-trigger':'dots','.block-card b':'plus'}))set(selector,name,true);
    for(const [selector,name] of Object.entries({'#preview':'eye','#start-hero':'plus','[data-device="Desktop"]':'device-desktop','[data-device="Tablet"]':'device-tablet','[data-device="Mobile"]':'device-mobile'}))set(selector,name);
    for(const node of shell.querySelectorAll('.fa')){const names={'fa-arrows':'arrows-move','fa-trash-o':'trash','fa-trash':'trash','fa-clone':'copy','fa-arrow-up':'arrow-up','fa-chevron-right':'chevron-right','fa-chevron-down':'chevron-down','fa-eye':'eye','fa-eye-slash':'eye-off','fa-cog':'settings','fa-bars':'menu','fa-plus':'plus','fa-minus':'chevron-up'};for(const [cls,name]of Object.entries(names))if(node.classList.contains(cls)&&!node.querySelector('.tabler-editor-icon')){node.classList.add('tabler-replaced');node.innerHTML=tablerIcon(name);}}
  };
  updateFlexIcons(document,{});
  const preview=shell.querySelector('#preview');if(preview)preview.textContent=preview.textContent.replace('↗','').trim();
  const hero=shell.querySelector('#start-hero');if(hero)hero.textContent=hero.textContent.replace(/^\+\s*/,'');
  update();const observer=new MutationObserver(update);observer.observe(shell,{childList:true,subtree:true});editor.on('destroy',()=>observer.disconnect());
}

export function updateFlexIcons(root, style) {
  const direction=style['flex-direction']||'row',wrap=style['flex-wrap']||'nowrap';
  for(const button of root.querySelectorAll('[data-flex-property]')){
    const spec=flexIconSpec(button.dataset.flexProperty,button.dataset.flexValue,direction,wrap);if(!spec)continue;
    const key=spec.name+':'+(spec.transform||'');if(button.dataset.iconSpec===key)continue;
    button.innerHTML=tablerIcon(spec.name);button.dataset.iconSpec=key;
    const svg=button.querySelector('svg');if(svg&&spec.transform)svg.style.transform=spec.transform;
  }
}
