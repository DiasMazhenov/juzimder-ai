import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
  DEFAULT_DESIGN_TOKENS,
  TOKEN_FIELDS,
  designTokensFromStyle,
  designTokensToStyle,
  migrateLegacyTokenValue,
  normalizeDesignTokens,
  tokenValue
} from '../src/design-tokens.js';

test('design tokens are validated and converted to safe project style declarations', () => {
  const tokens = normalizeDesignTokens({ ink: '#102030', fontFamily: 'Georgia, serif', radius: '12px' });
  assert.deepEqual(tokens, { ...DEFAULT_DESIGN_TOKENS, ink: '#102030', fontFamily: 'Georgia, serif', radius: '12px' });
  const style = designTokensToStyle(tokens);
  assert.equal(Object.keys(style).length, TOKEN_FIELDS.length);
  assert.equal(style['--juzimder-color-ink'], '#102030');
  assert.equal(style['--juzimder-font-family'], 'Georgia, serif');
  assert.deepEqual(designTokensFromStyle(style), tokens);
});

test('invalid token values are rejected while invalid persisted values fall back safely', () => {
  assert.throws(() => normalizeDesignTokens({ ink: '#123' }), /Некорректный цвет/);
  assert.throws(() => normalizeDesignTokens({ fontFamily: 'url(https://example.test)' }), /шрифт/);
  assert.throws(() => normalizeDesignTokens({ radius: '40px' }), /от 0 до 32/);
  assert.equal(designTokensFromStyle({ '--juzimder-color-ink': 'url(https://example.test)' }).ink, DEFAULT_DESIGN_TOKENS.ink);
});

test('legacy preset values migrate to editable tokens without changing unrelated values', () => {
  assert.equal(migrateLegacyTokenValue('background-color', '#ecf4ef'), tokenValue('canvas'));
  assert.equal(migrateLegacyTokenValue('border', '1px solid #dce4de'), `1px solid ${tokenValue('line')}`);
  assert.equal(migrateLegacyTokenValue('font-family', 'Arial, sans-serif'), tokenValue('fontFamily'));
  assert.equal(migrateLegacyTokenValue('border-radius', '6px'), tokenValue('radius'));
  assert.equal(migrateLegacyTokenValue('color', '#ffffff'), '#ffffff');
});
