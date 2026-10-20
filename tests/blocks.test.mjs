import { test } from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { blocks } from '../src/blocks.js';
import { DEFAULT_DESIGN_TOKENS, designTokensToStyle } from '../src/design-tokens.js';

test('section and element library has unique ids and complete labels', () => {
  const ids = blocks.map(block => block.id);
  assert.equal(new Set(ids).size, ids.length, 'block ids must be unique');
  assert.deepEqual(ids, ['hero', 'features', 'cta', 'text', 'image', 'navigation', 'process', 'testimonial', 'faq', 'footer', 'container', 'button']);
  for (const block of blocks) {
    assert.ok(block.label.trim(), `${block.id} has a visible label`);
    assert.ok(block.description.trim(), `${block.id} has a description`);
    assert.notStrictEqual(block.content(), block.content(), `${block.id} returns fresh content for each insertion`);
  }
});

test('internal links resolve when the complete section library is composed', () => {
  const ids = new Set();
  const targets = [];
  const visit = value => {
    if (Array.isArray(value)) return value.forEach(visit);
    if (!value || typeof value !== 'object') return;
    if (typeof value.attributes?.id === 'string') ids.add(value.attributes.id);
    if (typeof value.attributes?.href === 'string' && value.attributes.href.startsWith('#')) targets.push(value.attributes.href.slice(1));
    Object.values(value).forEach(visit);
  };
  blocks.forEach(block => visit(block.content()));
  for (const target of targets) assert.ok(ids.has(target), `#${target} should point to a section in the library`);
});

test('all block presets pass the authoritative PHP Project Data validator', () => {
  const document = {
    schemaVersion: 1,
    engine: 'grapesjs',
    data: {
      assets: [],
      styles: [],
      pages: [{
        id: 'home',
        name: 'Главная',
        frames: [{ component: { type: 'wrapper', style: designTokensToStyle(DEFAULT_DESIGN_TOKENS), components: blocks.map(block => block.content()) } }]
      }]
    }
  };
  const validation = spawnSync('php', ['-r', "require_once getenv('JUZIMDER_DOMAIN_PATH'); \\Juzimder\\Document::validate(json_decode(stream_get_contents(STDIN)));"], {
    encoding: 'utf8',
    input: JSON.stringify(document),
    env: { ...process.env, JUZIMDER_DOMAIN_PATH: fileURLToPath(new URL('../backend/Domain.php', import.meta.url)) }
  });
  assert.equal(validation.status, 0, validation.stderr || validation.stdout || 'PHP validator rejected the section library');
});

test('new section presets use editable global tokens for shared colors, typography and radius', () => {
  const cssValues = [];
  const visit = value => {
    if (Array.isArray(value)) return value.forEach(visit);
    if (!value || typeof value !== 'object') return;
    for (const entry of Object.values(value.style || {})) cssValues.push(entry);
    Object.values(value).forEach(visit);
  };
  blocks.forEach(block => visit(block.content()));
  assert.ok(cssValues.some(value => String(value).includes('--juzimder-color-ink')));
  assert.ok(cssValues.some(value => String(value).includes('--juzimder-font-heading')));
  assert.ok(cssValues.some(value => String(value).includes('--juzimder-font-body')));
  assert.ok(cssValues.some(value => String(value).includes('--juzimder-font-size-h1')));
  assert.ok(cssValues.some(value => String(value).includes('--juzimder-radius')));
});
