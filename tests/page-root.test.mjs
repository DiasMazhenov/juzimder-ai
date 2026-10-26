import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
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

test('project page list exposes accessible multi-select and guarded bulk operations', () => {
  const source = readFileSync(new URL('../src/main.js', import.meta.url), 'utf8');
  assert.match(source, /id="select-all-pages"/);
  assert.match(source, /data-page-select="\$\{esc\(page\.id\)\}"/);
  assert.match(source, /id="bulk-page-action"/);
  assert.match(source, /value="duplicate">Дублировать выбранные/);
  assert.match(source, /value="delete">Удалить выбранные/);
  assert.match(source, /selected\.length >= pages\.length/);
  assert.match(source, /project\.version = result\.version/);
});

test('project Forms settings expose the private submission inbox and safe CSV export', () => {
  const source = readFileSync(new URL('../src/main.js', import.meta.url), 'utf8');
  assert.match(source, /\/projects\/\$\{id\}\/form-submissions`/);
  assert.match(source, /id="form-submission-list"/);
  assert.match(source, /id="forms-search"/);
  assert.match(source, /id="forms-export"/);
  assert.match(source, /data-delete-submission=/);
  assert.match(source, /Удалить эту заявку\?/);
  assert.ok(source.includes("text.replace(/\"/g,'\"\"')"), 'CSV fields escape embedded quotes');
  assert.match(source, /if\(\/\^\[\\s\]\*\[=\+\\-@\]\/\.test\(text\)\)/);
  assert.match(source, /Автоматическая доставка на email и CRM пока не подключена/);
});
