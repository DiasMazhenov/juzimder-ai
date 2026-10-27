import test from 'node:test';
import assert from 'node:assert/strict';
import { appendPhotoCredit, PHOTO_CREDIT_MAX_LENGTH, photoCreditCaption, photoCreditText, photoFigureFromImage } from '../src/photo-credit.js';

class FakeElement {
  constructor(tagName, ownerDocument) { this.tagName=tagName.toUpperCase();this.ownerDocument=ownerDocument;this.style={};this.children=[];this.childNodes=this.children;this.parentNode=null;this.attributes={}; }
  append(...nodes) { for(const node of nodes){if(node.parentNode){const old=node.parentNode.children.indexOf(node);if(old!==-1)node.parentNode.children.splice(old,1);}node.parentNode=this;this.children.push(node);} }
  replaceChild(replacement, current) { const index=this.children.indexOf(current);if(index<0)throw new Error('Child not found');this.children[index]=replacement;replacement.parentNode=this;current.parentNode=null; }
  closest(selector) { return selector==='.ai-hero-background'?this.backgroundAncestor||null:null; }
  set textContent(value) { this.text=String(value); }
}

function fakePhotoSlot(backgroundAncestor=null) {
  const document={createElement(tag){return new FakeElement(tag,document);},createTextNode(text){return {textContent:String(text),parentNode:null};}};
  const parent=new FakeElement('section',document),slot=new FakeElement('div',document);
  slot.parentNode=parent;parent.children.push(slot);slot.backgroundAncestor=backgroundAncestor;
  return {document,parent,slot};
}

test('main-editor photo credits wrap the image without dropping geometry or image styling',()=>{
  const image={type:'image',tagName:'img',name:'Clinic photo',attributes:{id:'clinic-photo',src:'https://example.test/photo.jpg',alt:'Clinic entrance'},style:{position:'absolute',left:'24px',top:'30px',width:'340px',height:'220px','border-radius':'24px','object-fit':'cover'}};
  const figure=photoFigureFromImage(image,'Photo: Author · CC BY-SA 4.0');
  assert.equal(figure.type,'wrapper');
  assert.equal(figure.tagName,'figure');
  assert.equal(figure.style.position,'absolute');
  assert.equal(figure.style.left,'24px');
  assert.equal(figure.style.width,'340px');
  assert.equal(figure.style.height,undefined);
  assert.equal(figure.components[0].attributes.src,image.attributes.src);
  assert.equal(figure.components[0].attributes.alt,image.attributes.alt);
  assert.equal(figure.components[0].style.width,'100%');
  assert.equal(figure.components[0].style.height,'220px');
  assert.equal(figure.components[0].style['border-radius'],'24px');
  assert.equal(figure.components[0].style['object-fit'],'cover');
  assert.equal(figure.components[1].tagName,'figcaption');
  assert.equal(figure.components[1].style['font-size'],'8px');
  assert.equal(figure.components[1].content,'Photo: Author · CC BY-SA 4.0');
  assert.deepEqual(image.style,{position:'absolute',left:'24px',top:'30px',width:'340px',height:'220px','border-radius':'24px','object-fit':'cover'});
});

test('photo credit text is trimmed and limited to 500 characters',()=>{
  assert.equal(PHOTO_CREDIT_MAX_LENGTH,500);
  assert.equal(photoCreditText('  Photo: Author  '),'Photo: Author');
  assert.equal(photoCreditText('x'.repeat(600)).length,500);
  assert.equal(photoCreditCaption('x'.repeat(600)).content.length,500);
});

test('generated image attribution is a readable 8px caption below the photo',()=>{
  const {document,parent,slot}=fakePhotoSlot();
  const credit=appendPhotoCredit(slot,{kind:'commons',sourceUrl:'https://commons.wikimedia.org/photo',licenseUrl:'https://creativecommons.org/licenses/by/4.0/',author:'Photo Author',license:'CC BY 4.0'});
  const figure=parent.children[0];
  assert.equal(figure.tagName,'FIGURE');
  assert.equal(figure.className,'ai-photo-figure');
  assert.equal(figure.children[0],slot);
  assert.equal(figure.children[1],credit);
  assert.equal(credit.tagName,'FIGCAPTION');
  assert.equal(credit.style['font-size'],'8px');
  assert.equal(credit.style['margin-top'],'2px');
  assert.equal(credit.style.position,undefined);
  assert.equal(credit.children[0].href,'https://commons.wikimedia.org/photo');
  assert.equal(credit.children[2].href,'https://creativecommons.org/licenses/by/4.0/');
});

test('full-bleed hero background attribution remains legible over the image',()=>{
  const background=new FakeElement('section',null),{slot}=fakePhotoSlot(background);
  const credit=appendPhotoCredit(slot,{kind:'commons',sourceUrl:'https://commons.wikimedia.org/photo',licenseUrl:'https://creativecommons.org/licenses/by/4.0/',author:'Photo Author',license:'CC BY 4.0'});
  assert.equal(background.children[0],credit);
  assert.equal(credit.tagName,'P');
  assert.equal(credit.style.position,'absolute');
  assert.equal(credit.style.fontSize,'8px');
});
