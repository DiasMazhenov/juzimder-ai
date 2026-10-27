import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

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
