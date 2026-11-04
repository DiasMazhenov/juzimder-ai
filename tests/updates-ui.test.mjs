import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const source = await readFile(new URL('../src/main.js', import.meta.url), 'utf8');
const start = source.indexOf("if(session?.canManageUpdates===true){");
const end = source.indexOf('restoreButton.onclick=', start);
const updateFlow = source.slice(start, end);

test('update installation confirmation is an in-app dialog, not a browser confirm alert', () => {
  assert.notEqual(start, -1, 'update controls are present');
  assert.ok(end > start, 'update flow ends before backup restore handling');
  assert.match(updateFlow, /createElement\('dialog'\)/);
  assert.match(updateFlow, /updateDialog\.showModal\(\)/);
  assert.match(updateFlow, /update-install-confirm/);
  assert.doesNotMatch(updateFlow, /window\.confirm\(/);
});
