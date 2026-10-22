import { test } from 'node:test';
import assert from 'node:assert/strict';
import { remapCopiedReferences } from '../src/component-actions.js';

class Component {
  constructor(id, attributes = {}, children = []) {
    this.id = id;
    this.attributes = attributes;
    this.children = children;
  }

  getId() { return this.id; }
  get(name) { return name === 'attributes' ? this.attributes : undefined; }
  components() { return { models: this.children }; }
  addAttributes(attributes) { Object.assign(this.attributes, attributes); }
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
