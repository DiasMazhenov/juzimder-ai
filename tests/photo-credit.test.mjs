import test from 'node:test';
import assert from 'node:assert/strict';
import { appendPhotoCredit, normalizeLegacyHeroPhotoCredits, photoCreditCaption, photoCreditText, photoFigureFromImage } from '../src/photo-credit.js';

class FakeElement {
  constructor(tagName, ownerDocument) { this.tagName=tagName.toUpperCase();this.ownerDocument=ownerDocument;this.style={};this.children=[];this.childNodes=this.children;this.parentNode=null;this.attributes={}; }
  get nextSibling() { if(!this.parentNode)return null;const index=this.parentNode.children.indexOf(this);return this.parentNode.children[index+1]||null; }
  append(...nodes) { for(const node of nodes){if(node.parentNode){const old=node.parentNode.children.indexOf(node);if(old!==-1)node.parentNode.children.splice(old,1);}node.parentNode=this;this.children.push(node);} }
  replaceChild(replacement, current) { const index=this.children.indexOf(current);if(index<0)throw new Error('Child not found');this.children[index]=replacement;replacement.parentNode=this;current.parentNode=null; }
  closest(selector) { return selector==='.ai-hero-background'?this.backgroundAncestor||null:null; }
  set textContent(value) { this.text=String(value); }
  insertBefore(node, reference) { const index=reference===null?this.children.length:this.children.indexOf(reference);if(index<0)throw new Error('Reference node not found');if(node.parentNode){const old=node.parentNode.children.indexOf(node);if(old!==-1)node.parentNode.children.splice(old,1);}node.parentNode=this;this.children.splice(index,0,node); }
}

function fakePhotoSlot(backgroundAncestor=null) {
  const document={defaultView:{getComputedStyle(element){return{position:element.position||'static',maxWidth:element.maxWidth||'none',marginBottom:element.marginBottom||'0px'};}},createElement(tag){return new FakeElement(tag,document);},createTextNode(text){return {textContent:String(text),parentNode:null};}};
  const parent=new FakeElement('main',document),slot=new FakeElement('div',document);
  if(backgroundAncestor){backgroundAncestor.ownerDocument=document;backgroundAncestor.parentNode=parent;parent.children.push(backgroundAncestor);slot.parentNode=backgroundAncestor;backgroundAncestor.children.push(slot);}
  else{slot.parentNode=parent;parent.children.push(slot);}
  slot.backgroundAncestor=backgroundAncestor;
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

test('photo credit text is trimmed without a caption-specific length limit',()=>{
  assert.equal(photoCreditText('  Photo: Author  '),'Photo: Author');
  assert.equal(photoCreditText('x'.repeat(600)).length,600);
  assert.equal(photoCreditCaption('x'.repeat(600)).content.length,600);
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

test('full-bleed hero background attribution stays inside the hero as a compact overlay',()=>{
  const background=new FakeElement('section',null);background.maxWidth='1000px';background.marginBottom='40px';const {slot}=fakePhotoSlot(background);
  const credit=appendPhotoCredit(slot,{kind:'commons',sourceUrl:'https://commons.wikimedia.org/photo',licenseUrl:'https://creativecommons.org/licenses/by/4.0/',author:'Photo Author',license:'CC BY 4.0'});
  assert.equal(background.parentNode.children.length,1);
  assert.equal(background.children[1],credit);
  assert.equal(credit.tagName,'P');
  assert.equal(background.style.position,'relative');
  assert.equal(credit.style.position,'absolute');
  assert.equal(credit.style.right,'12px');
  assert.equal(credit.style.bottom,'10px');
  assert.equal(credit.style['z-index'],'2');
  assert.equal(credit.style.width,'max-content');
  assert.equal(credit.style['max-width'],'48%');
  assert.equal(credit.style.padding,'4px 8px');
  assert.equal(credit.style['background-color'],'rgba(0, 0, 0, 0.62)');
  assert.equal(credit.style.color,'#ffffff');
  assert.equal(credit.style['font-size'],'8px');
  assert.equal(credit.style.margin,'0');
  assert.equal(credit.children[0].href,'https://commons.wikimedia.org/photo');
  assert.equal(credit.children[0].style.color,'inherit');
  assert.equal(credit.children[0].style['text-decoration'],'underline');
  assert.equal(credit.children[2].href,'https://creativecommons.org/licenses/by/4.0/');
});

class FakeComponent {
  constructor({classes=[],tagName='div',style={},children=[]}={}){
    this.classes=classes;this.tagName=tagName;this.style={...style};this.children=[];this.parent=null;
    for(const child of children)this.append(child);
  }
  getClasses(){return this.classes;}
  get(name){return name==='tagName'?this.tagName:undefined;}
  getStyle(){return this.style;}
  addStyle(style){Object.assign(this.style,style);}
  components(){return{models:this.children};}
  append(child){child.parent=this;this.children.push(child);return[child];}
  move(target,{at=target.children.length}={}){
    if(this.parent){const index=this.parent.children.indexOf(this);if(index>=0)this.parent.children.splice(index,1);}
    this.parent=target;target.children.splice(at,0,this);return this;
  }
}

test('legacy adjacent Hero attribution migrates into the Hero without moving regular photo captions',()=>{
  const sourceLink=new FakeComponent({tagName:'a'}),licenseLink=new FakeComponent({tagName:'a'});
  const hero=new FakeComponent({classes:['ai-hero','ai-hero-background'],children:[new FakeComponent({classes:['ai-image-slot']})]});
  const legacyCredit=new FakeComponent({classes:['ai-photo-credit'],tagName:'p',children:[sourceLink,licenseLink]});
  const figureCredit=new FakeComponent({classes:['ai-photo-credit'],tagName:'figcaption'});
  const figure=new FakeComponent({tagName:'figure',children:[new FakeComponent({tagName:'img'}),figureCredit]});
  const root=new FakeComponent({tagName:'main',children:[hero,legacyCredit,figure]});
  const editor={Pages:{getAll:()=>[{getMainComponent:()=>root}]}};

  assert.equal(normalizeLegacyHeroPhotoCredits(editor),1);
  assert.deepEqual(root.children,[hero,figure]);
  assert.equal(hero.children.at(-1),legacyCredit);
  assert.equal(legacyCredit.parent,hero);
  assert.equal(hero.style.position,'relative');
  assert.equal(legacyCredit.style.position,'absolute');
  assert.equal(legacyCredit.style.right,'12px');
  assert.equal(legacyCredit.style.bottom,'10px');
  assert.equal(legacyCredit.style['font-size'],'8px');
  assert.deepEqual(sourceLink.style,{'font-size':'8px','line-height':'1.4',color:'inherit','text-decoration':'underline'});
  assert.deepEqual(licenseLink.style,{'font-size':'8px','line-height':'1.4',color:'inherit','text-decoration':'underline'});
  assert.equal(figure.children[1],figureCredit);
  assert.equal(normalizeLegacyHeroPhotoCredits(editor),0);
});
