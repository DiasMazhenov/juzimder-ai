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
  radius: '8px'
});

export const COLOR_TOKEN_NAMES = Object.freeze(['ink', 'forest', 'canvas', 'paper', 'body', 'muted', 'line', 'accent']);
export const FONT_FAMILIES = Object.freeze([
  'Arial, sans-serif',
  'system-ui, sans-serif',
  'Georgia, serif'
]);

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
  { name: 'radius', label: 'Скругление карточек', variable: '--juzimder-radius', type: 'radius' }
]);

export function normalizeDesignTokens(value = {}) {
  if (!value || typeof value !== 'object' || Array.isArray(value)) throw new TypeError('Токены темы должны быть объектом.');
  const tokens = { ...DEFAULT_DESIGN_TOKENS };
  for (const name of COLOR_TOKEN_NAMES) {
    if (value[name] === undefined) continue;
    if (typeof value[name] !== 'string' || !/^#[0-9a-f]{6}$/i.test(value[name])) {
      throw new TypeError(`Некорректный цвет токена «${name}».`);
    }
    tokens[name] = value[name].toLowerCase();
  }
  if (value.fontFamily !== undefined) {
    if (!FONT_FAMILIES.includes(value.fontFamily)) throw new TypeError('Выберите шрифт из списка.');
    tokens.fontFamily = value.fontFamily;
  }
  if (value.radius !== undefined) {
    if (typeof value.radius !== 'string' || !/^(?:0|[1-9]|[12][0-9]|3[0-2])px$/.test(value.radius)) {
      throw new TypeError('Скругление должно быть от 0 до 32 px.');
    }
    tokens.radius = value.radius;
  }
  return tokens;
}

export function designTokensFromStyle(style = {}) {
  const values = {};
  for (const field of TOKEN_FIELDS) {
    const value = style[field.variable];
    if (typeof value !== 'string') continue;
    try { values[field.name] = normalizeDesignTokens({ [field.name]: value })[field.name]; }
    catch { /* Ignore invalid persisted values and keep the safe default. */ }
  }
  return normalizeDesignTokens(values);
}

export function designTokensToStyle(tokens) {
  const normalized = normalizeDesignTokens(tokens);
  return Object.fromEntries(TOKEN_FIELDS.map(field => [field.variable, normalized[field.name]]));
}

export function tokenValue(name, fallback = DEFAULT_DESIGN_TOKENS[name]) {
  const field = TOKEN_FIELDS.find(item => item.name === name);
  if (!field) throw new TypeError(`Неизвестный design token: ${name}`);
  return `var(${field.variable}, ${fallback})`;
}

const LEGACY_COLOR_VALUES = Object.freeze({
  ink: '#183e31', forest: '#245b46', canvas: '#ecf4ef', paper: '#f6faf7',
  body: '#527063', muted: '#68736c', line: '#dce4de', accent: '#b0dcc1'
});

export function migrateLegacyTokenValue(property, value) {
  if (typeof value !== 'string') return value;
  if (property === 'font-family' && value.trim().toLowerCase() === DEFAULT_DESIGN_TOKENS.fontFamily.toLowerCase()) {
    return tokenValue('fontFamily');
  }
  if (property === 'border-radius' && ['6px', '8px'].includes(value.trim())) return tokenValue('radius');
  return value.replace(/#[0-9a-f]{6}\b/gi, color => {
    const name = Object.keys(LEGACY_COLOR_VALUES).find(key => LEGACY_COLOR_VALUES[key] === color.toLowerCase());
    return name ? tokenValue(name) : color;
  });
}
