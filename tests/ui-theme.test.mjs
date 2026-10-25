import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const read = (path) => readFile(new URL(`../${path}`, import.meta.url), 'utf8');

function luminance(hex) {
  const channels = hex.replace('#', '').match(/.{2}/g).map((part) => parseInt(part, 16) / 255);
  const linear = channels.map((value) => value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4);
  return linear[0] * 0.2126 + linear[1] * 0.7152 + linear[2] * 0.0722;
}

function contrast(a, b) {
  const values = [luminance(a), luminance(b)].sort((x, y) => y - x);
  return (values[0] + 0.05) / (values[1] + 0.05);
}

test('constructor theme defines the new palette in one token sheet', async () => {
  const [theme, main, zeroApp] = await Promise.all([
    read('src/ui-theme.css'),
    read('src/main.js'),
    read('src/zero/editor/app.js'),
  ]);
  for (const [name, value] of Object.entries({
    '--jz-ui-accent-secondary': '#61892f',
    '--jz-ui-accent': '#86c232',
    '--jz-ui-bg': '#14181b',
    '--jz-ui-panel': '#1b2125',
    '--jz-ui-panel-raised': '#242b30',
    '--jz-ui-muted-surface': '#303a3f',
    '--jz-ui-border': '#303a3f',
    '--jz-ui-text': '#f5f7f7',
    '--jz-ui-text-secondary': '#b6bec1',
    '--jz-ui-text-muted': '#929da2',
    '--jz-ui-on-accent': '#14181b',
    '--jz-ui-save-active': '#86c232',
    '--jz-ui-save-active-text': '#222629',
    '--jz-ui-save-saved': '#474b4f',
    '--jz-ui-save-saved-hover': '#6b6e70',
    '--jz-ui-save-saved-text': '#f5f7f7',
    '--jz-ui-accent-hover': '#98d744',
    '--jz-ui-error': '#f16d6d',
    '--jz-ui-warning': '#e9b95b',
  })) {
    assert.match(theme, new RegExp(`${name}:\\s*${value};`, 'i'));
  }
  assert.match(main, /ui-theme\.css/);
  assert.match(zeroApp, /ui-theme\.css/);
});

test('main text and CTA color pairs meet WCAG AA contrast', async () => {
  const theme = await read('src/ui-theme.css');
  assert.ok(contrast('#f5f7f7', '#14181b') >= 4.5);
  assert.ok(contrast('#f5f7f7', '#1b2125') >= 4.5);
  assert.ok(contrast('#b6bec1', '#1b2125') >= 4.5);
  assert.ok(contrast('#929da2', '#14181b') >= 4.5);
  assert.ok(contrast('#14181b', '#86c232') >= 4.5);
  assert.ok(contrast('#222629', '#86c232') >= 4.5);
  assert.ok(contrast('#f5f7f7', '#474b4f') >= 4.5);
  assert.ok(contrast('#f5f7f7', '#6b6e70') >= 4.5);
  assert.match(theme, /--jz-ui-on-accent:\s*#14181b/i);
});

test('interactive controls use shared theme tokens for keyboard and status states', async () => {
  const overrides = await read('src/ui-overrides.css');
  assert.match(overrides, /#app:not\(:has\(\.zero-app\)\)\s*\{[^}]*color-scheme:\s*dark/s);
  assert.match(overrides, /:focus-visible\s*\{[^}]*outline:\s*2px solid var\(--jz-ui-focus\)/s);
  assert.match(overrides, /button:disabled\s*\{[^}]*cursor:\s*not-allowed/s);
  assert.match(overrides, /\[aria-busy="true"\], \.is-loading, \.loading\)\s*\{[^}]*cursor:\s*progress/s);
  assert.match(overrides, /\.danger-action[^}]*color:\s*var\(--jz-ui-error\)/s);
  assert.match(overrides, /button\[aria-pressed="true"\]\s*\{[^}]*var\(--jz-ui-selection-border\)/s);
});

test('save buttons share palette tokens and track saved/unsaved states in both editors', async () => {
  const [main, zeroApp, overrides, zeroCss] = await Promise.all([
    read('src/main.js'),
    read('src/zero/editor/app.js'),
    read('src/ui-overrides.css'),
    read('src/zero/zero.css'),
  ]);
  assert.match(main, /function syncSaveButtonState\(\)[\s\S]*?button\.dataset\.saveState=conflicted\?'conflict':saving\?'saving':dirty\?'unsaved':'saved'/);
  assert.match(main, /id="save" class="primary"/);
  assert.match(main, /editor\.on\('project:loaded',[^\n]*syncSaveButtonState/);
  assert.match(main, /syncSaveButtonState\(\);/);
  assert.match(zeroApp, /button\.dataset\.saveState = conflict \? "conflict" : saving \? "saving" : dirty \? "unsaved" : "saved"/);
  assert.match(overrides, /#save\[data-save-state="unsaved"\][\s\S]*?var\(--jz-ui-save-active\)/);
  assert.match(overrides, /#save\[data-save-state="saved"\][\s\S]*?var\(--jz-ui-save-saved\)/);
  assert.match(overrides, /#save\[data-save-state="unsaved"\]:hover[\s\S]*?var\(--jz-ui-save-active\)/);
  assert.match(zeroCss, /\[data-action="save"\]\[data-save-state="unsaved"\][\s\S]*?var\(--jz-ui-save-active/);
  assert.match(zeroCss, /\[data-action="save"\]\[data-save-state="saved"\][\s\S]*?var\(--jz-ui-save-saved/);
  assert.match(zeroCss, /\[data-action="save"\]\[data-save-state="unsaved"\]:hover[\s\S]*?var\(--jz-ui-save-active/);
});

test('constructor chrome styles stay separate from user design tokens and export', async () => {
  const [theme, overrides, tokens, exporter, zeroRenderer, zeroCss] = await Promise.all([
    read('src/ui-theme.css'),
    read('src/ui-overrides.css'),
    read('src/design-tokens.js'),
    read('backend/StaticSiteExporter.php'),
    read('src/zero/render.js'),
    read('src/zero/zero.css'),
  ]);
  assert.match(overrides, /#app:not\(:has\(\.zero-app\)\)/);
  assert.match(overrides, /#app:not\(:has\(\.zero-app\)\)\s*\{[^}]*--accent:\s*var\(--jz-ui-accent\)/s);
  assert.doesNotMatch(theme, /--(?:surface|line|muted|accent):/);
  assert.match(overrides, /\.zero-app #zero-canvas \{[^}]*color-scheme:\s*normal/s);
  assert.match(overrides, /\.zero-app #zero-canvas \{[^}]*--surface:\s*#1d211f/s);
  assert.match(overrides, /\.zero-app #zero-canvas \{[^}]*--accent:\s*#a2d4b5/s);
  for (const paletteColor of ['#222629', '#474b4f', '#6b6e70']) {
    assert.match(theme, new RegExp(paletteColor, 'i'));
  }
  assert.doesNotMatch(`${overrides}\n${zeroCss}`, /#(?:6b6e70|222629|474b4f)/i);
  assert.doesNotMatch(`${tokens}\n${exporter}\n${zeroRenderer}`, /--jz-ui-/);
  assert.doesNotMatch(zeroCss, /\.zero-app (?:button|input|select|textarea|label),\s*\.zero-app/);
  assert.match(theme, /--jz-ui-/);
});
