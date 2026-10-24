import basic from 'grapesjs-blocks-basic';
import flexbox from 'grapesjs-blocks-flexbox';
import gradient from 'grapesjs-style-gradient';
import filter from 'grapesjs-style-filter';
import touch from 'grapesjs-touch';

const invoke=(module,...args)=>{while(typeof module!=='function'&&module?.default)module=module.default;return module(...args);};
// Component scripts stay prohibited until the isolated runtime is implemented.
export function editorPlugins(editor) {
  invoke(basic,editor,{blocks:['column1','column2','column3','column3-7'],flexGrid:true,labelColumn1:'Одна колонка',labelColumn2:'Две колонки',labelColumn3:'Три колонки',labelColumn37:'Колонки 3/7'});
  invoke(flexbox,editor,{labelRow:'Строка',labelColumn:'Колонка',flexboxBlock:{label:'Flex-контейнер'}});
  invoke(gradient,editor,{});invoke(filter,editor,{});invoke(touch,editor,{});
}
export function exposePluginBlocks(editor,container) {
  for(const id of ['column1','column2','column3','column3-7','flexbox']) {
    const block=editor.Blocks.get(id);if(!block)continue;
    const button=document.createElement('button');button.type='button';button.className='block-card';button.dataset.category='Компоновка';button.dataset.pluginBlock=id;
    const title=document.createElement('strong');title.textContent=String(block.getLabel()).replace(/<[^>]*>/g,'');button.append(title);
    const description=document.createElement('small');description.textContent='Редактируемая компоновка GrapesJS';button.append(description);
    button.onclick=()=>{const components=editor.addComponents(block.getContent());editor.select(components[0]);};container.append(button);
  }
  const sector=editor.StyleManager.getSectors().find(item=>item.get('name')==='Оформление');
  if(sector){editor.StyleManager.addProperty(sector.getId(),{type:'gradient',name:'Градиент',property:'background-image'});editor.StyleManager.addProperty(sector.getId(),{extend:'filter',name:'Фильтр'});}
}
