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
  assert.match(source, /id="forms-settings-form"[^>]*aria-labelledby="forms-settings-title"/);
  assert.match(source, /id="smtp-settings-form"[^>]*aria-labelledby="smtp-settings-title"/);
  assert.match(source, /name="subject"/);
  assert.match(source, /name="autoresponderEnabled"/);
  assert.match(source, /id="smtp-test"/);
  assert.match(styles, /\.form-delivery-settings \.settings-check\{display:flex;flex-direction:row;align-items:center;/, 'notification toggles override the global stacked-label style');
  assert.match(inbox, /\/projects\/\$\{projectId\}\/form-submissions/);
  assert.match(inbox, /form-inbox-dialog/);
  assert.match(inbox, /form-submissions\/\$\{submission\.id\}\/delete/);
  assert.match(inbox, /Заявки/);
});

test('project settings describe publication and SEO metadata as connected', () => {
  const source = readFileSync(new URL('../src/main.js', import.meta.url), 'utf8');
  assert.match(source, /Токены сохраняются отдельной ревизией и включаются в CSS предпросмотра, ZIP-экспорта и опубликованного сайта/);
  assert.match(source, /Типографические токены входят в предпросмотр, ZIP-экспорт и опубликованный сайт/);
  assert.match(source, /SEO-метаданные сохраняются для каждой страницы вместе с ревизиями проекта и используются в предпросмотре, ZIP-экспорте и опубликованном сайте/);
  assert.doesNotMatch(source, /Публичная публикация и static export ещё не подключены|значения пока не влияют на внешний сайт|публикацией, которой ещё нет/);
});

test('site settings sidebar stays fixed at viewport height with an independently scrolling menu', () => {
  const styles = readFileSync(new URL('../src/style.css', import.meta.url), 'utf8');
  const source = readFileSync(new URL('../src/main.js', import.meta.url), 'utf8');
  const settingsMarkupStart = source.indexOf('class="dashboard project-dashboard settings-dashboard"');
  const settingsMarkup = source.slice(settingsMarkupStart, source.indexOf('</dialog>', settingsMarkupStart));
  assert.match(styles, /\.settings-dashboard\{min-height:100dvh;height:100dvh;overflow:hidden\}/);
  assert.match(styles, /\.settings-dashboard>\.dash-sidebar\{position:fixed;inset:0 auto 0 0;width:236px;height:100vh;height:100dvh/);
  assert.match(styles, /\.settings-dashboard \.dash-sidebar \.sidebar-settings-nav\{max-height:none;min-height:0;flex:0 1 auto\}/);
  assert.match(styles, /\.settings-dashboard \.dash-footer\{display:none\}/);
  assert.match(styles, /@media\(max-width:800px\)\{\.settings-dashboard\{height:auto;min-height:100dvh;overflow:visible\}/);
  assert.match(settingsMarkup, /<div class="workspace-label">ПРОЕКТ<\/div><div class="project-sidebar-title">\$\{esc\(project\.name\)\}<\/div>/);
  assert.doesNotMatch(settingsMarkup, /<div class="version-label">Версия продукта <strong>\$\{productVersion\}<\/strong><\/div><p>\$\{esc\(project\.name\)\}<\/p>/);
  assert.match(styles, /\.project-sidebar-title\{margin:16px 8px 14px;color:#e9edeb/);
  assert.match(styles, /\.settings-dashboard>\.dash-sidebar\{padding:20px 18px;gap:10px\}/);
  assert.match(styles, /\.settings-dashboard \.workspace-label\{margin-top:12px\}/);
  assert.match(styles, /\.settings-dashboard \.project-sidebar-title\{margin:0 8px 4px\}/);
  assert.match(styles, /\.settings-dashboard \.project-side-link,\.settings-dashboard \.dash-sidebar>\.nav-active\{padding:10px 12px\}/);
});

test('site custom CSS is validated, saved and previewed only in a sandboxed public frame', () => {
  const source = readFileSync(new URL('../src/main.js', import.meta.url), 'utf8');
  const styles = readFileSync(new URL('../src/style.css', import.meta.url), 'utf8');
  assert.match(source, /name="customCss" id="custom-css-input"/);
  assert.match(source, /customCss:cssInput\.value/);
  assert.match(source, /id="custom-css-preview-frame" sandbox=""/);
  assert.match(source, /Сначала сохраните изменения CSS/);
  assert.match(source, /previewFrame\.srcdoc/);
  assert.match(source, /min-width\/max-width 320–2560 px/);
  assert.match(source, /\.cta:hover/);
  assert.match(source, /:focus-visible/);
  assert.match(styles, /#custom-css-input/);
});
