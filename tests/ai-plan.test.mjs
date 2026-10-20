import assert from 'node:assert/strict';
import test from 'node:test';
import { blocks } from '../src/blocks.js';
import { buildDesignPlanOperations, normalizeDesignPlan } from '../src/ai-plan.js';

const plan = {
  siteType: 'Сайт студии', audience: 'Малый бизнес', goal: 'Получать заявки', style: 'Спокойный и современный',
  colors: { ink: '#112233', forest: '#224466', canvas: '#eaf0f5', paper: '#ffffff', body: '#334455', muted: '#556677', line: '#ccddee', accent: '#88bb99' },
  sections: ['navigation', 'hero', 'features', 'footer'], responsiveNotes: 'Карточки складываются в одну колонку на узком экране.'
};

test('normalizes a valid server-validated plan without changing its sections', () => {
  const normalized = normalizeDesignPlan(plan);
  assert.deepEqual(normalized.sections, plan.sections);
  assert.equal(normalized.colors.ink, '#112233');
  assert.notEqual(normalized.colors, plan.colors);
});

test('builds only typed create operations from known presets and applies the planned palette', () => {
  const operations = buildDesignPlanOperations(plan, blocks, 'home');
  assert.equal(operations.length, plan.sections.length);
  assert.ok(operations.every(operation => operation.operation === 'create_component' && operation.parentId === 'page:home'));
  const hero = operations[1].component;
  assert.equal(hero.style['background-color'], '#eaf0f5');
  assert.equal(hero.style.color, '#112233');
  assert.equal(hero.components[3].style['background-color'], '#224466');
  assert.equal(hero.style['font-family'], 'Arial, sans-serif');
  assert.equal(hero.style['border-radius'], undefined);
  assert.equal(hero.components[3].attributes.href, '#');
  assert.equal(operations[0].component.components[1].components[0].attributes.href, '#about');
  assert.equal(operations[0].component.components[2].attributes.href, '#');
  assert.equal(operations.at(-1).component.style['background-color'], '#112233');
  assert.doesNotMatch(JSON.stringify(operations), /var\(/i);
});

test('keeps navigation targets when the corresponding sections are included', () => {
  const completeAnchors = { ...plan, sections: ['navigation', 'hero', 'features', 'process', 'cta'] };
  const [navigation] = buildDesignPlanOperations(completeAnchors, blocks, 'home');
  assert.equal(navigation.component.components[1].components[0].attributes.href, '#about');
  assert.equal(navigation.component.components[1].components[1].attributes.href, '#services');
  assert.equal(navigation.component.components[1].components[2].attributes.href, '#contact');
});

test('new presets inherit project typography and radius while using the planned colors', () => {
  const operations = buildDesignPlanOperations(plan, blocks, 'home', {}, {
    headingFontFamily: 'Georgia, serif', bodyFontFamily: 'system-ui, sans-serif', headingFontWeight: '700', bodyFontWeight: '300',
    baseFontSize: '18px', fontSizeH1: '72px', headingLineHeight: '1.3', bodyLineHeight: '1.8', letterSpacing: '1px', radius: '12px'
  });
  const hero = operations[1].component;
  assert.equal(hero.style['font-family'], 'system-ui, sans-serif');
  const heading = hero.components[1].style;
  assert.equal(heading['font-family'], 'Georgia, serif');
  assert.equal(heading['font-size'], 'clamp(28px, calc(18px + 2.35vw), 72px)');
  assert.equal(heading['font-weight'], '700');
  assert.equal(heading['line-height'], '1.3');
  assert.equal(heading['letter-spacing'], '1px');
  const body = hero.components[2].style;
  assert.equal(body['font-size'], '18px');
  assert.equal(body['font-weight'], '300');
  assert.equal(body['line-height'], '1.8');
  assert.equal(hero.components[3].style['border-radius'], '12px');
  assert.equal(hero.style['background-color'], plan.colors.canvas);
  assert.doesNotMatch(JSON.stringify(operations), /var\(/i);
});

test('remaps preset anchors and links when a plan is appended to an existing project', () => {
  const existingData = { pages: [{ frames: [{ component: { type: 'wrapper', components: [
    { attributes: { id: 'about' } }, { attributes: { id: 'ai-about' } },
    { attributes: { id: 'services' } }, { attributes: { id: 'contact' } }
  ] } }] }] };
  const anchoredPlan = { ...plan, sections: ['navigation', 'hero', 'features', 'process', 'cta'] };
  const operations = buildDesignPlanOperations(anchoredPlan, blocks, 'home', existingData);
  const navigation = operations[0].component;
  const features = operations[2].component;
  const process = operations[3].component;
  const cta = operations[4].component;
  assert.equal(features.attributes.id, 'ai-about-2');
  assert.equal(process.attributes.id, 'ai-services');
  assert.equal(cta.attributes.id, 'ai-contact');
  assert.equal(navigation.components[1].components[0].attributes.href, '#ai-about-2');
  assert.equal(navigation.components[1].components[1].attributes.href, '#ai-services');
  assert.equal(navigation.components[1].components[2].attributes.href, '#ai-contact');
});

test('rejects malformed plans, unsupported sections, and missing pages', () => {
  assert.throws(() => normalizeDesignPlan({ ...plan, sections: ['hero', 'image', 'faq'] }), /список секций/);
  assert.throws(() => normalizeDesignPlan({ ...plan, colors: { ...plan.colors, ink: 'red' } }), /палитру/);
  assert.throws(() => normalizeDesignPlan({ ...plan, sections: ['hero', 'faq'] }), /список секций/);
  assert.throws(() => buildDesignPlanOperations(plan, blocks, ''), /страница/);
});
