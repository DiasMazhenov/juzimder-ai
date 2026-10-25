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

test('constructor theme defines the requested palette in one token sheet', async () => {
  const [theme, main, zeroApp] = await Promise.all([
    read('src/ui-theme.css'),
    read('src/main.js'),
    read('src/zero/editor/app.js'),
  ]);
  for (const [name, value] of Object.entries({
    '--jz-ui-accent-secondary': '#61892f',
    '--jz-ui-accent': '#86c232',
    '--jz-ui-bg': '#222629',
    '--jz-ui-panel': '#474b4f',
    '--jz-ui-muted-surface': '#6b6e70',
  })) {
    assert.match(theme, new RegExp(`${name}:\\s*${value};`, 'i'));
  }
  assert.match(main, /ui-theme\.css/);
  assert.match(zeroApp, /ui-theme\.css/);
});

test('main text and CTA color pairs meet WCAG AA contrast', async () => {
  const theme = await read('src/ui-theme.css');
  assert.ok(contrast('#f4f5f5', '#222629') >= 4.5);
  assert.ok(contrast('#f4f5f5', '#474b4f') >= 4.5);
  assert.ok(contrast('#d2d4d5', '#474b4f') >= 4.5);
  assert.ok(contrast('#222629', '#86c232') >= 4.5);
  assert.match(theme, /--jz-ui-on-accent:\s*#222629/i);
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
  assert.doesNotMatch(`${tokens}\n${exporter}\n${zeroRenderer}`, /--jz-ui-/);
  assert.doesNotMatch(zeroCss, /\.zero-app (?:button|input|select|textarea|label),\s*\.zero-app/);
  assert.match(theme, /--jz-ui-/);
});
