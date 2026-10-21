import assert from 'node:assert/strict';
import test from 'node:test';
import { blocks } from '../src/blocks.js';
import { AI_SECTION_TEXT_FIELDS, buildDesignPlanOperations, normalizeDesignPlan } from '../src/ai-plan.js';

const textCounts = { navigation: 5, hero: 4, features: 9, process: 11, testimonial: 4, faq: 8, cta: 3, text: 1, footer: 3 };
const makeContent = sections => sections.map(section => ({ section, texts: Array.from({ length: textCounts[section] }, (_, index) => `${section} copy ${index + 1}`) }));

const plan = {
  siteType: 'Сайт студии', audience: 'Малый бизнес', goal: 'Получать заявки', style: 'Спокойный и современный',
  colors: { ink: '#112233', forest: '#224466', canvas: '#eaf0f5', paper: '#ffffff', body: '#334455', muted: '#556677', line: '#ccddee', accent: '#88bb99' },
  sections: ['navigation', 'hero', 'features', 'footer'], responsiveNotes: 'Карточки складываются в одну колонку на узком экране.',
  content: makeContent(['navigation', 'hero', 'features', 'footer'])
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
  assert.equal(hero.components[1].content, 'hero copy 2');
  assert.equal(hero.components[2].content, 'hero copy 3');
  assert.equal(operations[0].component.components[0].content, 'navigation copy 1');
  assert.equal(operations[2].component.components[0].components[1].content, 'features copy 2');
  assert.equal(operations[3].component.components[0].content, 'footer copy 1');
  assert.equal(hero.components[3].attributes.href, '#');
  assert.equal(operations[0].component.components[1].components[0].attributes.href, '#about');
  assert.equal(operations[0].component.components[2].attributes.href, '#');
  assert.equal(operations.at(-1).component.style['background-color'], '#112233');
  assert.doesNotMatch(JSON.stringify(operations), /var\(/i);
});

test('generated copy fills only the known editable text nodes in every supported preset', () => {
  const sections = Object.keys(AI_SECTION_TEXT_FIELDS);
  const operations = buildDesignPlanOperations({ ...plan, sections, content: makeContent(sections) }, blocks, 'home');
  assert.equal(operations.length, sections.length);
  for (const [index, operation] of operations.entries()) {
    const values = [];
    const walk = node => {
      if (node?.type === 'text' || node?.type === 'link') values.push(node.content);
      for (const child of node?.components || []) walk(child);
    };
    walk(operation.component);
    assert.equal(values.length, AI_SECTION_TEXT_FIELDS[sections[index]].length, `${sections[index]} text-slot count`);
    assert.deepEqual(values, makeContent([sections[index]])[0].texts, `${sections[index]} generated text`);
  }
});

test('keeps navigation targets when the corresponding sections are included', () => {
  const sections = ['navigation', 'hero', 'features', 'process', 'cta'];
  const completeAnchors = { ...plan, sections, content: makeContent(sections) };
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
  const sections = ['navigation', 'hero', 'features', 'process', 'cta'];
  const anchoredPlan = { ...plan, sections, content: makeContent(sections) };
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
  assert.throws(() => normalizeDesignPlan({ ...plan, content: plan.content.slice(1) }), /текст секций/);
  assert.throws(() => normalizeDesignPlan({ ...plan, content: plan.content.map(entry => entry.section === 'hero' ? { ...entry, texts: [...entry.texts.slice(0, -1), '<h1>unsafe</h1>'] } : entry) }), /текст секций/);
  assert.throws(() => normalizeDesignPlan({ ...plan, content: plan.content.map(entry => entry.section === 'hero' ? { ...entry, texts: [...entry.texts.slice(0, -1), 'https://example.test'] } : entry) }), /текст секций/);
  assert.throws(() => normalizeDesignPlan({ ...plan, content: [...plan.content, plan.content[0]] }), /текст секций/);
  assert.throws(() => buildDesignPlanOperations(plan, blocks, ''), /страница/);
});
