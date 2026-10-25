import test from 'node:test';
import assert from 'node:assert/strict';
import { PHOTO_CREDIT_MAX_LENGTH, photoCreditCaption, photoCreditText, photoFigureFromImage } from '../src/photo-credit.js';

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
