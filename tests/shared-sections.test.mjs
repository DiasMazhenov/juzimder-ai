import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import { summarizeSharedSections } from '../src/shared-sections.js';

const document = { data: { pages: [
  { id: 'home', frames: [{ component: { components: [{ type: 'nav', name: 'Site navigation', attributes: { id: 'shared-nav' } }] } }] },
  { id: 'about', frames: [{ component: { components: [{ type: 'footer', name: 'Contact footer', attributes: { id: 'shared-footer' } }] } }] },
  { id: 'contact', frames: [{ component: { components: [] } }] },
] } };

test('dashboard shared section summaries reflect selected components and page visibility', () => {
  assert.deepEqual(summarizeSharedSections({ sharedSections: {
    headerComponentId: 'shared-nav',
    footerComponentId: 'shared-footer',
    pages: { about: { header: false }, contact: { header: true, footer: false } },
  } }, document), {
    header: { configured: true, name: 'Site navigation', pageCount: 2 },
    footer: { configured: true, name: 'Contact footer', pageCount: 2 },
  });
});

test('dashboard reports no shared sections until valid project components are configured', () => {
  assert.deepEqual(summarizeSharedSections({ sharedSections: { headerComponentId: 'deleted' } }, document), {
    header: { configured: false, name: '', pageCount: 0 },
    footer: { configured: false, name: '', pageCount: 0 },
  });
});

test('project dashboard renders live shared-section states and settings links', async () => {
  const main = await readFile(new URL('../src/main.js', import.meta.url), 'utf8');
  assert.match(main, /summarizeSharedSections\(project\.settings,project\.document\)/);
  assert.match(main, /summary\.configured\?`\$\{esc\(summary\.name\)\} · \$\{summary\.pageCount\} стр\.`:'Не настроена'/);
  assert.match(main, /sharedSummary\.header,'header'\).*sharedSummary\.footer,'footer'/);
});
