import { test } from 'node:test';
import assert from 'node:assert/strict';
import { findLayerComponentAtTarget, remapCopiedReferences } from '../src/component-actions.js';

class Component {
  constructor(id, attributes = {}, children = [], style = {}) {
    this.id = id;
    this.attributes = attributes;
    this.children = children;
    this.style = style;
  }

  getId() { return this.id; }
  get(name) { return name === 'attributes' ? this.attributes : name === 'style' ? this.style : undefined; }
  getStyle() { return this.style; }
  components() { return { models: this.children }; }
  addAttributes(attributes) { Object.assign(this.attributes, attributes); }
  addStyle(style) { Object.assign(this.style, style); }
}

test('duplicated component references follow remapped IDs without changing external links', () => {
  const source = new Component('section', {}, [
    new Component('title', { id: 'title' }),
    new Component('anchor', { id: 'anchor', href: '#title', 'aria-labelledby': 'section title' }),
    new Component('external', { id: 'external', href: 'https://example.test/#title', 'aria-describedby': 'missing' }),
  ]);
  const copy = new Component('section-2', {}, [
    new Component('title-2', { id: 'title-2' }),
    new Component('anchor-2', { id: 'anchor-2', href: '#title', 'aria-labelledby': 'section title' }),
    new Component('external-2', { id: 'external-2', href: 'https://example.test/#title', 'aria-describedby': 'missing' }),
  ]);

  remapCopiedReferences(source, copy);

  assert.equal(copy.children[1].attributes.href, '#title-2');
  assert.equal(copy.children[1].attributes['aria-labelledby'], 'section-2 title-2');
  assert.equal(copy.children[2].attributes.href, 'https://example.test/#title');
  assert.equal(copy.children[2].attributes['aria-describedby'], 'missing');
  assert.equal(source.children[1].attributes.href, '#title', 'the original component remains unchanged');
});

test('reference remapping leaves IDs unchanged when the copied subtree has no collisions', () => {
  const source = new Component('section', {}, [new Component('title', { id: 'title' })]);
  const copy = new Component('section', {}, [new Component('title', { id: 'title' })]);
  const before = JSON.stringify(copy.children[0].attributes);

  remapCopiedReferences(source, copy);

  assert.equal(JSON.stringify(copy.children[0].attributes), before);
});

test('copy remaps HTML ID references and local CSS fragment URLs without changing external URLs', () => {
  const source = new Component('section', {}, [
    new Component('title', { id: 'title' }),
    new Component('field', { id: 'field' }),
    new Component('label', {
      id: 'label',
      for: 'field',
      'aria-controls': 'field title',
      'aria-activedescendant': 'field',
      'xlink:href': '#gradient',
      fill: 'url(#gradient)',
      style: 'clip-path: url("#clip")',
      href: 'https://example.test/page#title',
    }, [], {
      filter: 'url(#shadow)',
      mask: "url('#mask')",
      '--numeric-id-reference': 'url(#3d-gradient)',
      'background-image': 'url("https://images.example.test/pattern.svg#shadow")',
    }),
    new Component('gradient', { id: 'gradient' }),
    new Component('clip', { id: 'clip' }),
    new Component('shadow', { id: 'shadow' }),
    new Component('mask', { id: 'mask' }),
    new Component('3d-gradient', { id: '3d-gradient' }),
  ]);
  const copy = new Component('section-2', {}, [
    new Component('title-2', { id: 'title-2' }),
    new Component('field-2', { id: 'field-2' }),
    new Component('label-2', {
      id: 'label-2',
      for: 'field',
      'aria-controls': 'field title',
      'aria-activedescendant': 'field',
      'xlink:href': '#gradient',
      fill: 'url(#gradient)',
      style: 'clip-path: url("#clip")',
      href: 'https://example.test/page#title',
    }, [], {
      filter: 'url(#shadow)',
      mask: "url('#mask')",
      '--numeric-id-reference': 'url(#3d-gradient)',
      'background-image': 'url("https://images.example.test/pattern.svg#shadow")',
    }),
    new Component('gradient-2', { id: 'gradient-2' }),
    new Component('clip-2', { id: 'clip-2' }),
    new Component('shadow-2', { id: 'shadow-2' }),
    new Component('mask-2', { id: 'mask-2' }),
    new Component('3d-gradient-2', { id: '3d-gradient-2' }),
  ]);
  const sourceCssRule = {
    style: { filter: 'url(#shadow)' },
    getStyle() { return this.style; },
    addStyle(style) { Object.assign(this.style, style); },
  };
  const copiedCssRule = {
    style: { filter: 'url(#shadow)', mask: "url('#mask')", background: 'url(https://example.test/image.svg#mask)' },
    getStyle() { return this.style; },
    addStyle(style) { Object.assign(this.style, style); },
  };
  const cssComposer = {
    getRules(selector) { return selector === '#label-2' ? [copiedCssRule] : []; },
  };

  remapCopiedReferences(source, copy, cssComposer);

  assert.equal(copy.children[2].attributes.for, 'field-2');
  assert.equal(copy.children[2].attributes['aria-controls'], 'field-2 title-2');
  assert.equal(copy.children[2].attributes['aria-activedescendant'], 'field-2');
  assert.equal(copy.children[2].attributes['xlink:href'], '#gradient-2');
  assert.equal(copy.children[2].attributes.fill, 'url(#gradient-2)');
  assert.equal(copy.children[2].attributes.style, 'clip-path: url("#clip-2")');
  assert.equal(copy.children[2].attributes.href, 'https://example.test/page#title');
  assert.equal(copy.children[2].style.filter, 'url(#shadow-2)');
  assert.equal(copy.children[2].style.mask, "url('#mask-2')");
  assert.equal(copy.children[2].style['--numeric-id-reference'], 'url(#3d-gradient-2)');
  assert.equal(copy.children[2].style['background-image'], 'url("https://images.example.test/pattern.svg#shadow")');
  assert.equal(copiedCssRule.style.filter, 'url(#shadow-2)');
  assert.equal(copiedCssRule.style.mask, "url('#mask-2')");
  assert.equal(copiedCssRule.style.background, 'url(https://example.test/image.svg#mask)');
  assert.equal(sourceCssRule.style.filter, 'url(#shadow)', 'the original CSS rule remains unchanged');
  assert.equal(source.children[2].attributes.for, 'field', 'the source component remains unchanged');
  assert.equal(source.children[2].style.filter, 'url(#shadow)');
});

test('layer context targets resolve to the deepest matching component row', () => {
  const target = {};
  const childRow = { contains: node => node === target || node === childRow };
  const parentRow = { contains: node => node === target || node === childRow || node === parentRow };
  const child = new Component('child');
  child.viewLayer = { el: childRow };
  const parent = new Component('parent', {}, [child]);
  parent.viewLayer = { el: parentRow };
  const root = new Component('root', {}, [parent]);
  root.viewLayer = { el: { contains: node => node === target || node === childRow || node === parentRow } };

  assert.equal(findLayerComponentAtTarget(root, target), child);
  assert.equal(findLayerComponentAtTarget(root, {}), null);
});
