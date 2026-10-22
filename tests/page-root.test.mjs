import test from 'node:test';
import assert from 'node:assert/strict';
import { createPageRoot } from '../src/page-root.js';

test('new page Body defaults to a vertical flex layout', () => {
  assert.deepEqual(createPageRoot(), {
    type: 'wrapper',
    style: { display: 'flex', 'flex-direction': 'column' },
    components: []
  });
});

test('page Body defaults preserve supplied styles and explicit layout choices', () => {
  const root = createPageRoot({ color: '#123456', display: 'grid', 'flex-direction': 'row' });
  assert.deepEqual(root.style, { color: '#123456', display: 'grid', 'flex-direction': 'row' });
});
