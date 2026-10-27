const PHOTO_CREDIT_STYLE={
  'font-size':'8px',
  'line-height':'1.2',
  'margin-top':'2px',
  'width':'100%',
  'text-align':'left',
  color:'inherit',
};

const FIGURE_STYLE={
  display:'inline-flex',
  'flex-direction':'column',
  'align-items':'flex-start',
  'max-width':'100%',
  margin:'0',
};

const AI_PHOTO_FIGURE_STYLE={
  display:'flex',
  'flex-direction':'column',
  'align-items':'stretch',
  width:'100%',
  'min-width':'0',
  margin:'0',
};

const FIGURE_LAYOUT_PROPERTIES=['display','position','top','right','bottom','left','z-index','transform','width','min-width','max-width','margin','align-self','flex'];

const HERO_CREDIT_STYLE={
  'box-sizing':'border-box',
  position:'absolute',
  right:'12px',
  bottom:'10px',
  'z-index':'2',
  width:'max-content',
  'max-width':'48%',
  margin:'0',
  padding:'4px 8px',
  'border-radius':'999px',
  'background-color':'rgba(0, 0, 0, 0.62)',
  color:'#ffffff',
  'font-size':'8px',
  'line-height':'1.3',
  'text-align':'right',
  'white-space':'normal',
};

const HERO_CREDIT_LINK_STYLE={'font-size':'8px','line-height':'1.4',color:'inherit','text-decoration':'underline'};

function applyInlineStyle(element, style) {
  if(typeof element?.style?.setProperty==='function'){
    for(const [property,value] of Object.entries(style))element.style.setProperty(property,String(value));
  }else if(element?.style)Object.assign(element.style,style);
}

function componentHasClass(component, className) {
  if(typeof component?.getClasses==='function')return component.getClasses().includes(className);
  const classes=component?.getAttributes?.()?.class||component?.get?.('attributes')?.class||'';
  return String(classes).split(/\s+/).includes(className);
}

function componentChildren(component) {
  const children=component?.components?.();
  return Array.isArray(children?.models)?children.models.slice():[];
}

function styleComponent(component, style) {
  if(typeof component?.addStyle==='function')component.addStyle(style);
  else if(typeof component?.setStyle==='function')component.setStyle({...component.getStyle?.(),...style});
}

function prepareHeroCredit(hero, credit) {
  const heroStyle=hero?.getStyle?.()||{};
  if(!heroStyle.position||heroStyle.position==='static')styleComponent(hero,{position:'relative'});
  styleComponent(credit,HERO_CREDIT_STYLE);
  for(const child of componentChildren(credit)){
    const tag=String(child?.get?.('tagName')||child?.get?.('type')||'').toLowerCase();
    if(tag==='a'||child?.get?.('type')==='link')styleComponent(child,HERO_CREDIT_LINK_STYLE);
  }
}

// Older saved AI pages placed background-Hero attribution as the next section sibling.
// Move only that legacy pattern; normal figure captions remain attached to their photos.
export function normalizeLegacyHeroPhotoCredits(editor) {
  let moved=0;
  const visit=component=>{
    const children=componentChildren(component);
    for(let index=0;index<children.length;index++){
      const child=children[index];
      if(componentHasClass(child,'ai-hero-background')){
        const credit=children[index+1];
        if(credit&&componentHasClass(credit,'ai-photo-credit')&&typeof credit.move==='function'){
          prepareHeroCredit(child,credit);
          credit.move(child,{at:componentChildren(child).length});
          moved++;
          continue;
        }
      }
      visit(child);
    }
  };
  for(const page of editor?.Pages?.getAll?.()||[]){
    const root=page?.getMainComponent?.();
    if(root)visit(root);
  }
  return moved;
}

export function photoCreditCaption(content) {
  return {type:'text',tagName:'figcaption',name:'Копирайт',content:String(content??''),style:{...PHOTO_CREDIT_STYLE}};
}

export function photoFigureFromImage(image, content) {
  const copy=JSON.parse(JSON.stringify(image||{}));
  const originalStyle={...(copy.style&&typeof copy.style==='object'?copy.style:{})};
  const figureStyle={...FIGURE_STYLE};
  for(const property of FIGURE_LAYOUT_PROPERTIES){
    if(originalStyle[property]!==undefined){figureStyle[property]=originalStyle[property];delete originalStyle[property];}
  }
  originalStyle.display='block';
  if(figureStyle.width!==undefined)originalStyle.width='100%';
  const imageData={...copy,style:originalStyle};
  delete imageData.components;
  delete imageData.content;
  return {
    type:'wrapper',tagName:'figure',name:'Фото',style:figureStyle,
    components:[imageData,photoCreditCaption(content)],
  };
}

export function photoCreditText(value) {
  return String(value??'').trim();
}

// A caption belongs to its photo, never to the surrounding flex/grid layout.
export function appendPhotoCredit(slot, asset) {
  if (asset.kind !== 'commons') return;
  const doc=slot.ownerDocument;
  const background=slot.closest('.ai-hero-background');
  const credit=doc.createElement(background?'p':'figcaption');
  credit.className='ai-photo-credit';
  if(background){
    const imageStyle=doc.defaultView?.getComputedStyle?.(background);
    if(!imageStyle||imageStyle.position==='static')background.style.position='relative';
    applyInlineStyle(credit,HERO_CREDIT_STYLE);
  }else applyInlineStyle(credit,{...PHOTO_CREDIT_STYLE,'margin-top':'2px'});
  for (const [url,label] of [[asset.sourceUrl,'Фото: '+asset.author+' / Wikimedia Commons'],[asset.licenseUrl,asset.license]]) {
    if(credit.childNodes.length)credit.append(doc.createTextNode(' · '));
    const link=doc.createElement('a');link.href=url;link.textContent=label;
    applyInlineStyle(link,HERO_CREDIT_LINK_STYLE);
    credit.append(link);
  }
  if(background){
    background.append(credit);
    return credit;
  }
  const figure=doc.createElement('figure');
  figure.className='ai-photo-figure';
  applyInlineStyle(figure,AI_PHOTO_FIGURE_STYLE);
  if(slot.parentNode)slot.parentNode.replaceChild(figure,slot);
  figure.append(slot,credit);
  return credit;
}
