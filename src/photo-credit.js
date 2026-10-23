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
