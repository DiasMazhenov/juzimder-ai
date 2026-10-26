import { blockMedia } from './block-media.js';
import forms from 'grapesjs-plugin-forms';
import basic from 'grapesjs-blocks-basic';
import flexbox from 'grapesjs-blocks-flexbox';
import gradient from 'grapesjs-style-gradient';
import filter from 'grapesjs-style-filter';
import touch from 'grapesjs-touch';
import { syncColorInput } from './ui/color-picker.js';

const invoke=(module,...args)=>{while(typeof module!=='function'&&module?.default)module=module.default;return module(...args);};
// Component scripts stay prohibited until the isolated runtime is implemented.
export function editorPlugins(editor) {
  // Replace GrapesJS' native color field so simple properties and composite
  // sub-fields (for example the color inside Border) share the same picker.
  editor.StyleManager.addType('color',{
    create({props,change}){
      const field=document.createElement('div');field.className='jz-color-property';
      const input=document.createElement('input');input.type='color';input.dataset.colorAlpha='true';input.dataset.colorLabel=`Цвет · ${props.name||props.property||'элемента'}`;input.value='#ffffff';
      input.addEventListener('input',event=>change({event,partial:true}));input.addEventListener('change',event=>change({event}));field.append(input);return field;
    },
    emit({updateStyle},{event,partial}){updateStyle(event.target.dataset.colorValue||event.target.value,{partial});},
    update({value,el}){syncColorInput(el.querySelector('input[type="color"]'),value||'#ffffff');},
    destroy(){},
  });
  invoke(basic,editor,{blocks:['column1','column2','column3','column3-7'],flexGrid:true,labelColumn1:'Одна колонка',labelColumn2:'Две колонки',labelColumn3:'Три колонки',labelColumn37:'Колонки 3/7'});
  invoke(flexbox,editor,{labelRow:'Строка',labelColumn:'Колонка',flexboxBlock:{label:'Flex-контейнер'}});
  invoke(forms,editor,{category:'Формы'});
  editor.Components.addType('form',{model:{defaults:{traits:[]}}});
  const labels={form:'Форма',input:'Текстовое поле',textarea:'Многострочное поле',select:'Список выбора',checkbox:'Флажок',radio:'Переключатель',label:'Подпись поля'};
  for(const [id,label] of Object.entries(labels))editor.Blocks.get(id)?.set('label',label);
  editor.Blocks.get('form')?.set('content',{type:'form',tagName:'form',style:{display:'flex','flex-direction':'column',gap:'16px',padding:'24px'},components:[{type:'label',attributes:{for:'contact-name'},components:'Имя'},{type:'input',attributes:{id:'contact-name',name:'name',placeholder:'Ваше имя','aria-label':'Имя',required:true}},{type:'label',attributes:{for:'contact-email'},components:'Email'},{type:'input',attributes:{id:'contact-email',name:'email',type:'email',required:true,'aria-label':'Email'}},{type:'label',attributes:{for:'contact-message'},components:'Сообщение'},{type:'textarea',attributes:{id:'contact-message',name:'message',rows:'4','aria-label':'Сообщение'}},{type:'button',attributes:{type:'submit'},components:'Отправить'}]});
  invoke(gradient,editor,{});invoke(filter,editor,{});invoke(touch,editor,{});
}
export function exposePluginBlocks(editor,container) {
  for(const id of ['column1','column2','column3','column3-7','flexbox','form','input','textarea','select','checkbox','radio','label']) {
    const block=editor.Blocks.get(id);if(!block)continue;
    const button=document.createElement('button');button.type='button';button.className='block-card';const formBlock=['form','input','textarea','select','checkbox','radio','label'].includes(id);button.dataset.category=formBlock?'Формы':'Компоновка';button.dataset.pluginBlock=id;
    const media=document.createElement('div');media.className='block-media';media.setAttribute('aria-hidden','true');media.innerHTML=blockMedia(id);button.append(media);
    const title=document.createElement('strong');title.textContent=String(block.getLabel()).replace(/<[^>]*>/g,'');button.append(title);
    const description=document.createElement('small');description.textContent=formBlock?'Поля формы · заявки в панели проекта':'Редактируемая компоновка GrapesJS';button.append(description);
    button.title=title.textContent+' — '+description.textContent;
    button.onclick=()=>{const components=editor.addComponents(block.getContent());editor.select(components[0]);};container.append(button);
  }
  const sector=editor.StyleManager.getSectors().find(item=>item.get('name')==='Оформление');
  if(sector){editor.StyleManager.addProperty(sector.getId(),{type:'gradient',name:'Градиент',property:'background-image'});editor.StyleManager.addProperty(sector.getId(),{extend:'filter',name:'Фильтр'});}
}
