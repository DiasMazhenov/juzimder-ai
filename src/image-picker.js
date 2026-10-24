import { tablerIcon } from './editor-icons.js';

export function installImagePicker({editor,projectId,api,csrf,insert,notify}) {
  const dialog=document.querySelector('#image-dialog');
  dialog.innerHTML=`<div class="asset-dialog-head"><div><p class="eyebrow">МЕДИА САЙТА</p><h2 id="image-dialog-title">Выберите изображение</h2></div><button type="button" id="close-image" aria-label="Закрыть изображения">${tablerIcon('x')}</button></div>
    <nav class="image-source-tabs" aria-label="Источник изображения"><button type="button" data-image-source="library" aria-pressed="true">Библиотека сайта <span id="asset-count"></span></button><button type="button" data-image-source="url" aria-pressed="false">По ссылке</button></nav>
    <section id="image-library-panel"><div class="asset-upload-zone" id="asset-drop-zone"><div><strong>Добавьте свои фотографии</strong><p>Перетащите файл сюда или выберите его на компьютере.</p><small>PNG, JPEG, WebP, GIF · до 4 МБ · хранилище сайта 20 МБ</small></div><button type="button" class="primary" id="upload-asset">${tablerIcon('arrow-up')} Загрузить с компьютера</button><input id="asset-file" type="file" accept="image/png,image/jpeg,image/gif,image/webp" hidden></div>
    <div class="asset-library-toolbar"><label class="asset-search-label"><span class="visually-hidden">Поиск по библиотеке сайта</span><input type="search" id="asset-search" placeholder="Найти изображение по имени"></label><button type="button" id="refresh-assets" title="Обновить библиотеку" aria-label="Обновить библиотеку">${tablerIcon('arrow-back-up')}</button></div><p id="asset-status" role="status" aria-live="polite"></p><div id="asset-library" class="asset-library"></div></section>
    <section id="image-url-panel" hidden><form id="image-form"><label>HTTPS-адрес изображения<input name="src" type="url" required pattern="https://.*" placeholder="https://example.com/photo.jpg"></label><p class="muted small">Изображение по ссылке не загружается в личную библиотеку сайта.</p></form></section>
    <footer class="image-picker-footer"><label>Описание изображения <span class="muted small">для доступности</span><input id="image-alt" maxlength="200" placeholder="Что изображено на фотографии?"></label><div class="image-picker-actions"><span id="asset-selection" class="muted small">Выберите фото из библиотеки</span><button type="button" id="cancel-image">Отмена</button><button type="button" class="primary" id="insert-image" disabled>Вставить изображение</button></div></footer>`;
  const $=selector=>dialog.querySelector(selector);
  let assets=[],selected=null,source='library',context=null,targetSrc='',epoch=0,uploading=false;
  const status=message=>{$('#asset-status').textContent=message;};
  const sync=()=>{
    $('#insert-image').disabled=source==='library'&&!selected;
    $('#asset-selection').textContent=source==='url'?'Внешний HTTPS-адрес':selected?.name||'Выберите фото из библиотеки';
    for(const card of dialog.querySelectorAll('[data-asset-id]'))card.setAttribute('aria-pressed',String(card.dataset.assetId===selected?.id));
  };
  const render=()=>{
    const container=$('#asset-library');container.replaceChildren();$('#asset-count').textContent=String(assets.length);
    const query=$('#asset-search').value.trim().toLocaleLowerCase('ru');
    const visible=assets.filter(asset=>asset.name.toLocaleLowerCase('ru').includes(query));
    for(const asset of visible){
      const card=document.createElement('button');card.type='button';card.className='asset-card';card.dataset.assetId=asset.id;card.setAttribute('aria-label','Выбрать '+asset.name);
      const image=document.createElement('img');image.src=asset.url;image.alt='';image.loading='lazy';
      const details=document.createElement('span');details.className='asset-card-info';
      const name=document.createElement('strong');name.textContent=asset.name;name.title=asset.name;
      const meta=document.createElement('small');meta.textContent=`${asset.width} × ${asset.height} · ${Math.ceil(asset.size/1024)} КБ`;
      details.append(name,meta);card.append(image,details);container.append(card);
    }
    if(!visible.length){const empty=document.createElement('div');empty.className='asset-library-empty';empty.textContent=assets.length?'Ничего не найдено. Попробуйте другое имя.':'Здесь будут фотографии вашего сайта. Загрузите первую с компьютера.';container.append(empty);}
    sync();
  };
  const load=async()=>{
    const request=++epoch;$('#refresh-assets').disabled=true;status('Загружаем библиотеку сайта…');
    try{const result=await api(`/projects/${projectId}/assets`);if(request!==epoch)return;assets=result;selected=assets.find(asset=>asset.id===selected?.id)||assets.find(asset=>asset.url===targetSrc)||null;render();status(assets.length?'Доступно изображений: '+assets.length:'Библиотека пока пуста.');}
    catch(error){if(request===epoch)status('Не удалось загрузить библиотеку: '+error.message);}
    finally{if(request===epoch)$('#refresh-assets').disabled=false;}
  };
  const setSource=value=>{source=value;$('#image-library-panel').hidden=value!=='library';$('#image-url-panel').hidden=value!=='url';for(const button of dialog.querySelectorAll('[data-image-source]'))button.setAttribute('aria-pressed',String(button.dataset.imageSource===value));sync();};
  const apply=()=>{
    let src=selected?.url;
    if(source==='url'){if(!$('#image-form').reportValidity())return;src=$('#image-form').elements.src.value.trim();}
    if(!src)return;
    const alt=$('#image-alt').value.trim(),native=context;
    if(native){const target=native.options?.target;native.select({type:'image',src,name:selected?.name||'Изображение'},true);if(target?.is('image'))target.addAttributes({alt});}
    else insert(src,alt);
    dialog.close();
  };
  const upload=async file=>{
    if(uploading||!file)return;
    if(!['image/png','image/jpeg','image/gif','image/webp'].includes(file.type)){status('Выберите PNG, JPEG, WebP или GIF.');return;}
    if(file.size>4194304){status('Размер файла превышает 4 МБ. Выберите файл поменьше.');return;}
    uploading=true;$('#upload-asset').disabled=true;status('Загружаем «'+file.name+'»…');
    try{
      const response=await fetch(`/api/projects/${projectId}/assets`,{method:'POST',headers:{'Content-Type':'application/octet-stream','X-CSRF-Token':csrf(),'X-Asset-Name':encodeURIComponent(file.name)},body:file});
      const asset=await response.json();if(!response.ok)throw new Error(asset.error||`HTTP ${response.status}`);
      selected=asset;$('#asset-search').value='';setSource('library');await load();status('Фото загружено и выбрано. Нажмите «Вставить изображение».');notify('Изображение сохранено в библиотеке сайта');
    }catch(error){status('Не удалось загрузить фото: '+error.message);}
    finally{uploading=false;$('#upload-asset').disabled=false;$('#asset-file').value='';}
  };
  $('#upload-asset').onclick=()=>$('#asset-file').click();
  $('#asset-file').onchange=event=>upload(event.target.files?.[0]);
  $('#asset-search').oninput=render;$('#refresh-assets').onclick=load;
  $('#asset-library').onclick=event=>{const card=event.target.closest('[data-asset-id]');if(card){selected=assets.find(asset=>asset.id===card.dataset.assetId);sync();}};
  for(const button of dialog.querySelectorAll('[data-image-source]'))button.onclick=()=>setSource(button.dataset.imageSource);
  $('#image-form').onsubmit=event=>{event.preventDefault();apply();};$('#insert-image').onclick=apply;
  $('#cancel-image').onclick=$('#close-image').onclick=()=>dialog.close();
  dialog.addEventListener('close',()=>{const native=context;context=null;epoch++;$('#refresh-assets').disabled=false;if(native)native.close();});
  const zone=$('#asset-drop-zone');zone.ondragover=event=>{event.preventDefault();event.stopPropagation();zone.classList.add('drag-over');};zone.ondragleave=()=>zone.classList.remove('drag-over');zone.ondrop=event=>{event.preventDefault();event.stopPropagation();zone.classList.remove('drag-over');if(event.dataTransfer.files.length!==1){status('Перетащите одно изображение за раз.');return;}upload(event.dataTransfer.files[0]);};
  return {open(native=null){context=native;targetSrc=native?.options?.target?.getSrc?.()||'';selected=null;$('#asset-search').value='';$('#image-form').reset();if(targetSrc.startsWith('https://'))$('#image-form').elements.src.value=targetSrc;$('#image-alt').value=native?.options?.target?.getAttributes?.().alt||'';$('#insert-image').textContent=native?'Использовать изображение':'Вставить изображение';setSource('library');if(!dialog.open)dialog.showModal();load();}};
}
