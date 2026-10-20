export const DEFAULT_DESIGN_TOKENS = Object.freeze({
  ink: '#183e31',
  forest: '#245b46',
  canvas: '#ecf4ef',
  paper: '#f6faf7',
  body: '#527063',
  muted: '#68736c',
  line: '#dce4de',
  accent: '#b0dcc1',
  fontFamily: 'Arial, sans-serif',
  headingFontFamily: 'Arial, sans-serif',
  bodyFontFamily: 'Arial, sans-serif',
  bodyFontWeight: '400',
  headingFontWeight: '500',
  baseFontSize: '16px',
  fontSizeH1: '48px',
  fontSizeH2: '36px',
  fontSizeH3: '24px',
  fontSizeH4: '20px',
  fontSizeH5: '18px',
  fontSizeH6: '16px',
  bodyLineHeight: '1.6',
  headingLineHeight: '1.15',
  letterSpacing: '0px',
  radius: '8px'
});

export const COLOR_TOKEN_NAMES = Object.freeze(['ink', 'forest', 'canvas', 'paper', 'body', 'muted', 'line', 'accent']);
export const FONT_FAMILIES = Object.freeze([
  'Arial, sans-serif',
  'system-ui, sans-serif',
  'Georgia, serif'
]);
export const FONT_WEIGHTS = Object.freeze(['300', '400', '500', '600', '700', '800']);
export const TOKEN_FIELDS = Object.freeze([
  { name: 'ink', label: 'Основной текст', variable: '--juzimder-color-ink', type: 'color' },
  { name: 'forest', label: 'Брендовый цвет', variable: '--juzimder-color-forest', type: 'color' },
  { name: 'canvas', label: 'Фон секций', variable: '--juzimder-color-canvas', type: 'color' },
  { name: 'paper', label: 'Поверхность карточек', variable: '--juzimder-color-paper', type: 'color' },
  { name: 'body', label: 'Вторичный текст', variable: '--juzimder-color-body', type: 'color' },
  { name: 'muted', label: 'Приглушённый текст', variable: '--juzimder-color-muted', type: 'color' },
  { name: 'line', label: 'Границы', variable: '--juzimder-color-line', type: 'color' },
  { name: 'accent', label: 'Акцент', variable: '--juzimder-color-accent', type: 'color' },
  { name: 'fontFamily', label: 'Шрифт', variable: '--juzimder-font-family', type: 'font' },
  { name: 'headingFontFamily', label: 'Шрифт заголовков', variable: '--juzimder-font-heading', type: 'font' },
  { name: 'bodyFontFamily', label: 'Шрифт текста', variable: '--juzimder-font-body', type: 'font' },
  { name: 'bodyFontWeight', label: 'Вес основного текста', variable: '--juzimder-font-weight-body', type: 'weight' },
  { name: 'headingFontWeight', label: 'Вес заголовков', variable: '--juzimder-font-weight-heading', type: 'weight' },
  { name: 'baseFontSize', label: 'Базовый размер текста', variable: '--juzimder-font-size-base', type: 'size' },
  { name: 'fontSizeH1', label: 'Размер H1', variable: '--juzimder-font-size-h1', type: 'size' },
  { name: 'fontSizeH2', label: 'Размер H2', variable: '--juzimder-font-size-h2', type: 'size' },
  { name: 'fontSizeH3', label: 'Размер H3', variable: '--juzimder-font-size-h3', type: 'size' },
  { name: 'fontSizeH4', label: 'Размер H4', variable: '--juzimder-font-size-h4', type: 'size' },
  { name: 'fontSizeH5', label: 'Размер H5', variable: '--juzimder-font-size-h5', type: 'size' },
  { name: 'fontSizeH6', label: 'Размер H6', variable: '--juzimder-font-size-h6', type: 'size' },
  { name: 'bodyLineHeight', label: 'Межстрочный интервал текста', variable: '--juzimder-line-height-body', type: 'lineHeight' },
  { name: 'headingLineHeight', label: 'Межстрочный интервал заголовков', variable: '--juzimder-line-height-heading', type: 'lineHeight' },
  { name: 'letterSpacing', label: 'Межбуквенный интервал', variable: '--juzimder-letter-spacing', type: 'letterSpacing' },
  { name: 'radius', label: 'Скругление карточек', variable: '--juzimder-radius', type: 'radius' }
]);

const sizeBounds = Object.freeze({
  baseFontSize: [12, 24], fontSizeH1: [28, 80], fontSizeH2: [22, 64], fontSizeH3: [18, 48],
  fontSizeH4: [16, 40], fontSizeH5: [14, 32], fontSizeH6: [12, 28]
});
const lineHeights = Object.freeze(['1', '1.1', '1.15', '1.2', '1.25', '1.3', '1.4', '1.5', '1.6', '1.7', '1.8', '1.9', '2']);
const letterSpacings = Object.freeze(['-2px', '-1px', '-0.5px', '0px', '0.25px', '0.5px', '1px', '1.5px', '2px']);
const fieldByName = new Map(TOKEN_FIELDS.map(field => [field.name, field]));

function validateTokenValue(name, value) {
  const field = fieldByName.get(name);
  if (!field) throw new TypeError(`Неизвестный design token: ${name}`);
  if (field.type === 'color') {
    if (typeof value !== 'string' || !/^#[0-9a-f]{6}$/i.test(value)) throw new TypeError(`Некорректный цвет токена «${name}».`);
    return value.toLowerCase();
  }
  if (field.type === 'font') {
    if (!FONT_FAMILIES.includes(value)) throw new TypeError('Выберите шрифт из списка.');
    return value;
  }
  if (field.type === 'weight') {
    if (!FONT_WEIGHTS.includes(value)) throw new TypeError('Укажите вес шрифта от 300 до 800.');
    return value;
  }
  if (field.type === 'size') {
    const bounds = sizeBounds[name];
    const size = typeof value === 'string' && value.match(/^(\d{1,3})px$/);
    if (!size || Number(size[1]) < bounds[0] || Number(size[1]) > bounds[1]) throw new TypeError(`Размер ${name} должен быть в диапазоне ${bounds[0]}–${bounds[1]} px.`);
    return `${Number(size[1])}px`;
  }
  if (field.type === 'lineHeight') {
    if (!lineHeights.includes(value)) throw new TypeError('Межстрочный интервал должен быть от 1 до 2.');
    return value;
  }
  if (field.type === 'letterSpacing') {
    if (!letterSpacings.includes(value)) throw new TypeError('Выберите межбуквенный интервал из списка.');
    return value;
  }
  if (field.type === 'radius') {
    if (typeof value !== 'string' || !/^(?:0|[1-9]|[12][0-9]|3[0-2])px$/.test(value)) throw new TypeError('Скругление должно быть от 0 до 32 px.');
    return value;
  }
  throw new TypeError(`Неподдерживаемый тип токена «${name}».`);
}

export function normalizeDesignTokens(value = {}) {
  if (!value || typeof value !== 'object' || Array.isArray(value)) throw new TypeError('Токены темы должны быть объектом.');
  const unknown = Object.keys(value).filter(name => !fieldByName.has(name));
  if (unknown.length) throw new TypeError(`Неизвестный design token: ${unknown[0]}`);
  const tokens = { ...DEFAULT_DESIGN_TOKENS };
  for (const [name, item] of Object.entries(value)) tokens[name] = validateTokenValue(name, item);
  const hasSeparateFamilies = Object.hasOwn(value, 'headingFontFamily') || Object.hasOwn(value, 'bodyFontFamily');
  if (Object.hasOwn(value, 'fontFamily') && !hasSeparateFamilies) {
    tokens.headingFontFamily = tokens.fontFamily;
    tokens.bodyFontFamily = tokens.fontFamily;
  } else {
    if (Object.hasOwn(value, 'headingFontFamily')) tokens.headingFontFamily = validateTokenValue('headingFontFamily', value.headingFontFamily);
    if (Object.hasOwn(value, 'bodyFontFamily')) tokens.bodyFontFamily = validateTokenValue('bodyFontFamily', value.bodyFontFamily);
    tokens.fontFamily = tokens.bodyFontFamily;
  }
  return tokens;
}

function variableValue(field, value) {
  if (field.name === 'fontSizeH1') return `clamp(28px, calc(18px + 2.35vw), ${value})`;
  if (field.name === 'fontSizeH2') return `clamp(22px, calc(17px + 1.5vw), ${value})`;
  if (field.name === 'fontSizeH3') return `clamp(18px, calc(12px + 0.9vw), ${value})`;
  return value;
}

export function designTokensFromStyle(style = {}) {
  const values = {};
  for (const field of TOKEN_FIELDS) {
    const value = style[field.variable];
    if (typeof value !== 'string') continue;
    if (field.name === 'fontSizeH1' || field.name === 'fontSizeH2' || field.name === 'fontSizeH3') {
      const fluid = value.match(/,\s*(\d{1,3}px)\s*\)$/);
      if (fluid) { values[field.name] = fluid[1]; continue; }
    }
    try { values[field.name] = validateTokenValue(field.name, value); }
    catch { /* Ignore invalid persisted values and keep the safe default. */ }
  }
  if (!Object.hasOwn(values, 'bodyFontFamily') && typeof style['--juzimder-font-family'] === 'string') {
    try { values.fontFamily = validateTokenValue('fontFamily', style['--juzimder-font-family']); } catch { /* Safe default. */ }
  }
  return normalizeDesignTokens(values);
}

export function designTokensToStyle(tokens) {
  const normalized = normalizeDesignTokens(tokens);
  return Object.fromEntries(TOKEN_FIELDS.map(field => [field.variable, variableValue(field, normalized[field.name])]));
}

export function tokenValue(name, fallback = DEFAULT_DESIGN_TOKENS[name]) {
  const field = fieldByName.get(name);
  if (!field) throw new TypeError(`Неизвестный design token: ${name}`);
  return `var(${field.variable}, ${variableValue(field, fallback)})`;
}

const LEGACY_COLOR_VALUES = Object.freeze({
  ink: '#183e31', forest: '#245b46', canvas: '#ecf4ef', paper: '#f6faf7',
  body: '#527063', muted: '#68736c', line: '#dce4de', accent: '#b0dcc1'
});
const LEGACY_HEADING_SIZES = Object.freeze({
  H1: ['clamp(36px, 6vw, 76px)'],
  H2: ['36px', 'clamp(28px, 4vw, 42px)', 'clamp(28px,4vw,42px)'],
  H3: ['20px', '18px', '16px']
});

export function migrateLegacyTokenValue(property, value, tagName = '') {
  if (typeof value !== 'string') return value;
  const tag = String(tagName).toLowerCase();
  if (property === 'font-family') {
    if (value.trim().toLowerCase() === DEFAULT_DESIGN_TOKENS.fontFamily.toLowerCase() || value.includes('--juzimder-font-family')) {
      return tokenValue(/^h[1-6]$/.test(tag) ? 'headingFontFamily' : 'bodyFontFamily');
    }
  }
  const heading = tag.match(/^h([1-6])$/)?.[1];
  if (property === 'font-size' && heading) {
    const name = `fontSizeH${heading}`;
    if (value.includes(`--juzimder-font-size-h${heading}`)) return tokenValue(name);
    if (LEGACY_HEADING_SIZES[`H${heading}`]?.includes(value.trim())) return tokenValue(name);
  }
  if (property === 'font-size' && tag === 'p' && value.trim() === '18px') return tokenValue('baseFontSize');
  if (property === 'font-weight' && heading && ['500', '600', '700'].includes(value.trim())) return tokenValue('headingFontWeight');
  if (property === 'line-height') {
    if (heading && ['1.05', '1.15', '1.2'].includes(value.trim())) return tokenValue('headingLineHeight');
    if (!heading && ['1.6', '1.65', '1.7'].includes(value.trim())) return tokenValue('bodyLineHeight');
  }
  if (property === 'border-radius' && ['6px', '8px'].includes(value.trim())) return tokenValue('radius');
  return value.replace(/#[0-9a-f]{6}\b/gi, color => {
    const name = Object.keys(LEGACY_COLOR_VALUES).find(key => LEGACY_COLOR_VALUES[key] === color.toLowerCase());
    return name ? tokenValue(name) : color;
  });
}
