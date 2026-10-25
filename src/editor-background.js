import { syncColorInput } from './ui/color-picker.js';

export function backgroundValue(src, color = '#000000', opacity = 0) {
  if (!src) return 'none';
  if (!/^(https:\/\/[^\s"<>\\(){};]+|\/api\/projects\/[a-f0-9]{24}\/assets\/[a-f0-9]{24}\/file)$/.test(src)) throw new Error('Укажите HTTPS URL изображения или файл библиотеки проекта.');
  const parsed = src.startsWith('https:') ? new URL(src) : null;
  if (parsed && (parsed.username || parsed.password)) throw new Error('URL с логином или паролем запрещён.');
  if (!/^#[a-f\d]{6}$/i.test(color)) throw new Error('Некорректный цвет overlay.');
  const alpha = Math.round(Math.max(0, Math.min(1, Number(opacity) || 0)) * 1000) / 1000;
  const [r,g,b] = [1,3,5].map(index=>parseInt(color.slice(index,index+2),16));
  const shade=`rgba(${r}, ${g}, ${b}, ${alpha})`;
  return `linear-gradient(${shade}, ${shade}), url("${src}")`;
}
export function parseBackground(value = '') {
  const src=value.match(/url\(["']?([^"')]+)["']?\)/)?.[1] || '';
  const rgba=value.match(/rgba\((\d+),\s*(\d+),\s*(\d+),\s*([\d.]+)\)/);
  return {src,color:rgba?'#'+rgba.slice(1,4).map(n=>Number(n).toString(16).padStart(2,'0')).join(''):'#000000',opacity:rgba?Number(rgba[4]):0};
}
export function installBackgroundControls(editor, projectId, api, notify) {
  const panel=document.createElement('fieldset');panel.className='background-controls';panel.innerHTML='<legend>Фон контейнера</legend><label>Background image<input id="container-background-url" type="url" placeholder="https://… или файл библиотеки"></label><label>Библиотека проекта<select id="container-background-asset"><option value="">Выберите изображение</option></select></label><button type="button" id="background-library-load">Обновить библиотеку</button><label>Background Overlay<input id="container-overlay-color" type="color" value="#000000"></label><label>Непрозрачность overlay <output id="container-overlay-value">0%</output><input id="container-overlay-opacity" type="range" min="0" max="100" value="0"></label><button type="button" id="background-apply">Применить фон</button><button type="button" id="background-remove">Убрать фон</button><p class="muted small" id="background-status">Выберите контейнер.</p>';
  let target=null;
  const field=id=>panel.querySelector('#'+id);
  const mount=()=>{const sector=[...document.querySelectorAll('#styles .gjs-sm-sector')].find(node=>node.querySelector('.gjs-sm-sector-title')?.textContent.includes('Оформление'));const host=sector?.querySelector('.gjs-sm-properties')||document.querySelector('#styles');if(host&&!panel.isConnected)host.append(panel);};
  const sync=component=>{mount();target=component;const tag=component?.get('tagName');const allowed=component&&!['img','a','button','h1','h2','h3','h4','p','span'].includes(tag);panel.hidden=!allowed;if(!allowed)return;const value=parseBackground(component.getStyle()['background-image']);field('container-background-url').value=value.src;field('container-overlay-color').value=value.color;syncColorInput(field('container-overlay-color'));field('container-overlay-opacity').value=String(value.opacity*100);field('container-overlay-value').textContent=Math.round(value.opacity*100)+'%';field('background-status').textContent='Фон покрывает контейнер; overlay не меняет прозрачность текста.';};
  field('container-overlay-opacity').oninput=event=>field('container-overlay-value').textContent=event.target.value+'%';
  field('container-background-asset').onchange=event=>field('container-background-url').value=event.target.value;
  field('background-library-load').onclick=async()=>{try{const assets=await api(`/projects/${projectId}/assets`);const select=field('container-background-asset');select.replaceChildren(new Option('Выберите изображение',''));for(const asset of assets)select.add(new Option(asset.name,asset.url));}catch(error){field('background-status').textContent=error.message;}};
  field('background-apply').onclick=()=>{if(!target||editor.getSelected()!==target)return;try{target.addStyle({'background-image':backgroundValue(field('container-background-url').value.trim(),field('container-overlay-color').value,Number(field('container-overlay-opacity').value)/100),'background-size':'cover','background-position':'center','background-repeat':'no-repeat'});field('background-status').textContent='Фон применён. Доступна отмена.';}catch(error){notify(error.message,true);}};
  field('background-remove').onclick=()=>{if(target&&editor.getSelected()===target){target.addStyle({'background-image':'none'});sync(target);}};
  editor.on('component:selected',sync);editor.on('undo redo',()=>sync(editor.getSelected()));editor.on('component:deselected',()=>panel.hidden=true);editor.on('load',mount);mount();panel.hidden=true;
}
