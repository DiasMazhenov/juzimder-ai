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

test('Form settings configure delivery while the private submission inbox stays separate', () => {
  const source = readFileSync(new URL('../src/main.js', import.meta.url), 'utf8');
  const inbox = readFileSync(new URL('../src/form-inbox.js', import.meta.url), 'utf8');
  const styles = readFileSync(new URL('../src/style.css', import.meta.url), 'utf8');
  assert.match(source, /installFormInbox\(id, api\)/);
  assert.match(source, /\/projects\/\$\{id\}\/form-mail`/);
  assert.match(source, /id="smtp-settings-form"/);
  assert.match(source, /name="subject"/);
  assert.match(source, /name="autoresponderEnabled"/);
  assert.match(source, /id="smtp-test"/);
  assert.match(styles, /\.form-delivery-settings \.settings-check\{display:flex;flex-direction:row;align-items:center;/, 'notification toggles override the global stacked-label style');
  assert.match(inbox, /\/projects\/\$\{projectId\}\/form-submissions/);
  assert.match(inbox, /form-inbox-dialog/);
  assert.match(inbox, /form-submissions\/\$\{submission\.id\}\/delete/);
  assert.match(inbox, /Заявки/);
});
