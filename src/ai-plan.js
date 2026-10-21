import { designTokensToStyle, normalizeDesignTokens } from './design-tokens.js';

const COLOR_NAMES = ['ink', 'forest', 'canvas', 'paper', 'body', 'muted', 'line', 'accent'];
const SECTION_IDS = ['navigation', 'hero', 'features', 'process', 'testimonial', 'faq', 'cta', 'text', 'footer'];
export const AI_SECTION_TEXT_FIELDS = Object.freeze({
  navigation: ['Название проекта', 'Подпись ссылки «О проекте»', 'Подпись ссылки «Услуги»', 'Подпись ссылки «Контакты»', 'Текст кнопки в шапке'],
  hero: ['Надзаголовок', 'Заголовок', 'Описание', 'Текст кнопки'],
  features: ['Номер карточки 1', 'Заголовок карточки 1', 'Описание карточки 1', 'Номер карточки 2', 'Заголовок карточки 2', 'Описание карточки 2', 'Номер карточки 3', 'Заголовок карточки 3', 'Описание карточки 3'],
  process: ['Надзаголовок', 'Заголовок', 'Номер шага 1', 'Название шага 1', 'Описание шага 1', 'Номер шага 2', 'Название шага 2', 'Описание шага 2', 'Номер шага 3', 'Название шага 3', 'Описание шага 3'],
  testimonial: ['Надзаголовок', 'Цитата', 'Автор', 'Роль автора'],
  faq: ['Надзаголовок', 'Заголовок', 'Вопрос 1', 'Ответ 1', 'Вопрос 2', 'Ответ 2', 'Вопрос 3', 'Ответ 3'],
  cta: ['Заголовок', 'Описание', 'Текст ссылки'],
  text: ['Абзац'],
  footer: ['Название проекта и год', 'Подпись ссылки «О проекте»', 'Подпись ссылки «Контакты»']
});
const PROJECT_STYLE_TOKENS = new Set([
  '--juzimder-font-family', '--juzimder-font-heading', '--juzimder-font-body',
  '--juzimder-font-weight-body', '--juzimder-font-weight-heading', '--juzimder-font-size-base',
  '--juzimder-font-size-h1', '--juzimder-font-size-h2', '--juzimder-font-size-h3',
  '--juzimder-font-size-h4', '--juzimder-font-size-h5', '--juzimder-font-size-h6',
  '--juzimder-line-height-body', '--juzimder-line-height-heading', '--juzimder-letter-spacing', '--juzimder-radius'
]);

export const AI_SECTION_LABELS = Object.freeze({
  navigation: 'Навигация', hero: 'Первый экран', features: 'Возможности', process: 'Этапы работы',
  testimonial: 'Отзыв', faq: 'Вопросы и ответы', cta: 'Контакты', text: 'Текст', footer: 'Подвал'
});

export function normalizeDesignPlan(plan) {
  if (!plan || typeof plan !== 'object' || Array.isArray(plan)) throw new TypeError('AI вернул некорректный план.');
  for (const [field, maxLength] of Object.entries({ siteType: 120, audience: 240, goal: 200, style: 200, responsiveNotes: 400 })) {
    if (typeof plan[field] !== 'string' || !plan[field].trim() || plan[field].trim().length > maxLength) {
      throw new TypeError(`AI вернул некорректное поле «${field}».`);
    }
  }
  if (!plan.colors || typeof plan.colors !== 'object' || Array.isArray(plan.colors)
      || COLOR_NAMES.some(name => typeof plan.colors[name] !== 'string' || !/^#[0-9a-f]{6}$/i.test(plan.colors[name]))) {
    throw new TypeError('AI вернул некорректную палитру.');
  }
  if (!Array.isArray(plan.sections) || plan.sections.length < 3 || plan.sections.length > 9
      || !plan.sections.includes('hero') || new Set(plan.sections).size !== plan.sections.length
      || plan.sections.some(id => !SECTION_IDS.includes(id))) {
    throw new TypeError('AI вернул некорректный список секций.');
  }
  if (!Array.isArray(plan.content) || plan.content.length !== plan.sections.length) throw new TypeError('AI вернул некорректный текст секций.');
  const contentBySection = new Map();
  for (const entry of plan.content) {
    if (!entry || typeof entry !== 'object' || Array.isArray(entry)
        || Object.keys(entry).length !== 2 || !Object.hasOwn(entry, 'section') || !Object.hasOwn(entry, 'texts')
        || !plan.sections.includes(entry.section) || contentBySection.has(entry.section)
        || !Array.isArray(entry.texts) || entry.texts.length !== AI_SECTION_TEXT_FIELDS[entry.section]?.length
        || entry.texts.some(value => typeof value !== 'string' || !value.trim() || value.trim().length > 220 || /[<>\u0000-\u0008\u000b\u000c\u000e-\u001f\u007f]|https?:\/\/|www\./iu.test(value))) {
      throw new TypeError('AI вернул некорректный текст секций.');
    }
    contentBySection.set(entry.section, { section: entry.section, texts: entry.texts.map(value => value.trim()) });
  }
  if (contentBySection.size !== plan.sections.length) throw new TypeError('AI вернул неполный текст секций.');
  return {
    siteType: plan.siteType.trim(), audience: plan.audience.trim(), goal: plan.goal.trim(), style: plan.style.trim(),
    responsiveNotes: plan.responsiveNotes.trim(),
    colors: Object.fromEntries(COLOR_NAMES.map(name => [name, plan.colors[name].toLowerCase()])),
    sections: [...plan.sections],
    content: plan.sections.map(section => contentBySection.get(section))
  };
}

function resolveCssVariables(value, styles) {
  if (typeof value !== 'string') return value;
  let output = '';
  for (let index = 0; index < value.length;) {
    if (value.slice(index, index + 4).toLowerCase() !== 'var(') {
      output += value[index++];
      continue;
    }
    let depth = 1, cursor = index + 4;
    for (; cursor < value.length && depth > 0; cursor++) {
      if (value[cursor] === '(') depth++;
      else if (value[cursor] === ')') depth--;
    }
    if (depth !== 0) { output += value.slice(index); break; }
    const expression = value.slice(index + 4, cursor - 1);
    let nested = 0, comma = -1;
    for (let offset = 0; offset < expression.length; offset++) {
      if (expression[offset] === '(') nested++;
      else if (expression[offset] === ')') nested--;
      else if (expression[offset] === ',' && nested === 0) { comma = offset; break; }
    }
    const name = (comma < 0 ? expression : expression.slice(0, comma)).trim();
    const fallback = comma < 0 ? '' : expression.slice(comma + 1).trim();
    const replacement = styles[name];
    output += typeof replacement === 'string' ? replacement : fallback ? resolveCssVariables(fallback, styles) : value.slice(index, cursor);
    index = cursor;
  }
  return output;
}

function resolveToken(value, colors, theme) {
  if (typeof value !== 'string') return value;
  const values = { ...designTokensToStyle(theme), '--juzimder-font-family': theme.bodyFontFamily };
  for (const [name, color] of Object.entries(colors)) values[`--juzimder-color-${name}`] = color;
  return resolveCssVariables(value, values);
}

function collectProjectIds(data) {
  const ids = new Set();
  const walk = node => {
    if (!node || typeof node !== 'object') return;
    const id = node.attributes?.id;
    if (typeof id === 'string') ids.add(id);
    if (Array.isArray(node.components)) node.components.forEach(walk);
  };
  for (const page of data?.pages || []) for (const frame of page.frames || []) walk(frame.component);
  return ids;
}

function prepareComponent(component, colors, sectionIds, anchorIds, theme) {
  if (!component || typeof component !== 'object' || Array.isArray(component)) throw new TypeError('Библиотека вернула некорректную секцию.');
  const prepared = structuredClone(component);
  const walk = node => {
    if (node.style && typeof node.style === 'object' && !Array.isArray(node.style)) {
      for (const [property, value] of Object.entries(node.style)) {
        node.style[property] = resolveToken(value, colors, theme);
        if (typeof node.style[property] === 'string' && /var\(/i.test(node.style[property])) {
          const refs = [...node.style[property].matchAll(/var\(\s*(--[\w-]+)/gi)].map(match => match[1]);
          if (refs.some(name => !PROJECT_STYLE_TOKENS.has(name))) throw new TypeError(`Для свойства «${property}» не удалось разрешить тему.`);
        }
      }
    }
    if (node.attributes && typeof node.attributes === 'object' && !Array.isArray(node.attributes)) {
      const anchorTargets = { about: 'features', services: 'process', contact: 'cta' };
      const nodeId = node.attributes.id;
      if (typeof nodeId === 'string' && anchorIds[nodeId]) node.attributes.id = anchorIds[nodeId];
      const href = node.attributes.href;
      const match = typeof href === 'string' && href.match(/^#(about|services|contact)$/);
      if (match) node.attributes.href = sectionIds.has(anchorTargets[match[1]]) ? `#${anchorIds[match[1]]}` : '#';
    }
    if (Array.isArray(node.components)) node.components.forEach(walk);
  };
  walk(prepared);
  return prepared;
}

function applyGeneratedText(component, texts, sectionId) {
  const targets = [];
  const walk = node => {
    if (!node || typeof node !== 'object') return;
    if ((node.type === 'text' || node.type === 'link') && typeof node.content === 'string') targets.push(node);
    if (Array.isArray(node.components)) node.components.forEach(walk);
  };
  walk(component);
  if (targets.length !== texts.length) throw new TypeError(`Текстовые поля секции «${sectionId}» не совпадают со схемой.`);
  targets.forEach((node, index) => { node.content = texts[index]; });
}

export function buildDesignPlanOperations(planInput, blocks, pageId, existingData = {}, themeInput = {}) {
  const plan = normalizeDesignPlan(planInput);
  const theme = normalizeDesignTokens(themeInput);
  if (typeof pageId !== 'string' || !pageId.trim()) throw new TypeError('Не выбрана страница проекта.');
  if (!Array.isArray(blocks)) throw new TypeError('Библиотека секций недоступна.');
  const byId = new Map(blocks.map(block => [block.id, block]));
  const sectionIds = new Set(plan.sections);
  const existingIds = collectProjectIds(existingData);
  const reservedIds = new Set(existingIds);
  const anchorTargets = { about: 'features', services: 'process', contact: 'cta' };
  const anchorIds = {};
  for (const [anchor, section] of Object.entries(anchorTargets)) {
    if (!sectionIds.has(section)) continue;
    let candidate = anchor;
    if (reservedIds.has(candidate)) {
      candidate = `ai-${anchor}`;
      let suffix = 2;
      while (reservedIds.has(candidate)) candidate = `ai-${anchor}-${suffix++}`;
    }
    anchorIds[anchor] = candidate;
    reservedIds.add(candidate);
  }
  const copyBySection = new Map(plan.content.map(entry => [entry.section, entry.texts]));
  return plan.sections.map(id => {
    const block = byId.get(id);
    if (!block || typeof block.content !== 'function') throw new TypeError(`Секция «${id}» отсутствует в библиотеке.`);
    const component = prepareComponent(block.content(), plan.colors, sectionIds, anchorIds, theme);
    applyGeneratedText(component, copyBySection.get(id), id);
    return {
      operation: 'create_component',
      parentId: `page:${pageId}`,
      component
    };
  });
}
