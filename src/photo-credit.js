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
    Object.assign(credit.style,{
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
    });
  }else Object.assign(credit.style,{...PHOTO_CREDIT_STYLE,'margin-top':'2px'});
  for (const [url,label] of [[asset.sourceUrl,'Фото: '+asset.author+' / Wikimedia Commons'],[asset.licenseUrl,asset.license]]) {
    if(credit.childNodes.length)credit.append(doc.createTextNode(' · '));
    const link=doc.createElement('a');link.href=url;link.textContent=label;
    Object.assign(link.style,{fontSize:'8px',lineHeight:'1.4',color:'inherit',textDecoration:'underline'});
    credit.append(link);
  }
  if(background){
    background.append(credit);
    return credit;
  }
  const figure=doc.createElement('figure');
  figure.className='ai-photo-figure';
  Object.assign(figure.style,AI_PHOTO_FIGURE_STYLE);
  if(slot.parentNode)slot.parentNode.replaceChild(figure,slot);
  figure.append(slot,credit);
  return credit;
}
