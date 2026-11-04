import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { calculatePreviewFit } from '../src/ai-preview-fit.js';

const source = readFileSync(new URL('../src/main.js', import.meta.url), 'utf8');
const start = source.indexOf('const renderAIDesign=');
const end = source.indexOf("const frame=document.createElement('iframe')", start);
const renderer = source.slice(start, end);

test('user Skill preview distinguishes machine checks from manual review without checkboxes', () => {
  assert.ok(start >= 0 && end > start, 'AI design preview renderer is present');
  assert.match(renderer, /Array\.isArray\(audit\?\.checks\)/);
  assert.match(renderer, /Array\.isArray\(audit\?\.humanReviewRequired\)/);
  assert.match(renderer, /Проверено автоматически/);
  assert.match(renderer, /className='ai-skill-manual-review'/);
  assert.match(renderer, /Требует ручной оценки/);
  assert.match(renderer, /row\.textContent=item/);
  assert.match(renderer, /Эти требования нельзя подтвердить автоматической проверкой/);
  assert.doesNotMatch(renderer, /type=['"]checkbox['"]/);
});

test('desktop preview can fit a wide iframe without changing its CSS viewport width', () => {
  const fitted = calculatePreviewFit(1014, 1440, 430, 24);
  assert.equal(fitted.fitted, true);
  assert.ok(Math.abs(fitted.scale - 1014 / 1440) < 0.000001);
  assert.equal(fitted.wrapperHeight, Math.ceil(430 * (1014 / 1440) + 24));

  const source = readFileSync(new URL('../src/main.js', import.meta.url), 'utf8');
  assert.match(source, /selectedFrameWidth=width;frame\.style\.width=width\+'px'/);
  assert.match(source, /frame\.style\.transform=fitted\?`scale\(\$\{fit\.scale\}\)`/);
  assert.match(source, /dataset\.fitPreview='true'/);
  assert.match(source, /new ResizeObserver\(applyPreviewFit\)/);
});

test('preview fit does not enlarge a frame or produce invalid geometry', () => {
  assert.deepEqual(calculatePreviewFit(1600, 1440, 430, 24), {
    scale: 1,
    fitted: false,
    wrapperHeight: 454,
  });
  assert.deepEqual(calculatePreviewFit(0, 1440, 430, 24), {
    scale: 1,
    fitted: false,
    wrapperHeight: 454,
  });
  assert.deepEqual(calculatePreviewFit(800, -1, Number.NaN, -10), {
    scale: 1,
    fitted: false,
    wrapperHeight: 0,
  });
});
