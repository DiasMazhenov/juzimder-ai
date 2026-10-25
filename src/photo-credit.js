export const PHOTO_CREDIT_MAX_LENGTH=500;

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

const FIGURE_LAYOUT_PROPERTIES=['display','position','top','right','bottom','left','z-index','transform','width','min-width','max-width','margin','align-self','flex'];

export function photoCreditCaption(content) {
  return {type:'text',tagName:'figcaption',name:'Копирайт',content:String(content??'').slice(0,PHOTO_CREDIT_MAX_LENGTH),style:{...PHOTO_CREDIT_STYLE}};
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
  return String(value??'').trim().slice(0,PHOTO_CREDIT_MAX_LENGTH);
}

// A caption belongs to its photo, never to the surrounding flex/grid layout.
export function appendPhotoCredit(slot, asset) {
  if (asset.kind !== 'commons') return;
  const host=slot.closest('.ai-hero-background') || slot;
  const doc=slot.ownerDocument, credit=doc.createElement('p');
  credit.className='ai-photo-credit';
  Object.assign(host.style,{position:'relative'});
  Object.assign(credit.style,{position:'absolute',left:'0px',right:'0px',bottom:'0px',margin:'0px',padding:'4px 6px',fontSize:'8px',lineHeight:'1.4',color:'#ffffff',backgroundColor:'#17211b',textAlign:'left'});
  for (const [url,label] of [[asset.sourceUrl,'Фото: '+asset.author+' / Wikimedia Commons'],[asset.licenseUrl,asset.license]]) {
    if(credit.childNodes.length)credit.append(doc.createTextNode(' · '));
    const link=doc.createElement('a');link.href=url;link.textContent=label;
    Object.assign(link.style,{fontSize:'8px',lineHeight:'1.4',color:'#ffffff',textDecoration:'underline'});
    credit.append(link);
  }
  host.append(credit);
  return credit;
}
