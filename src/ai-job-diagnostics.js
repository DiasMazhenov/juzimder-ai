const MARKUP_RULE_LABELS = Object.freeze({
  tag_allowlist: 'найден тег, который нельзя использовать',
  attribute_allowlist: 'найден атрибут или встроенный стиль вне списка разрешённых',
  contrast_quality: 'контраст текста ниже WCAG AA',
  section_count_low: 'найдено меньше 3 секций',
  section_count_high: 'превышен максимум в 9 секций',
  hero_count: 'должен быть ровно один Hero с классом ai-hero',
  image_slot_count: 'превышен лимит фото-слотов',
  fragment_target: 'ссылка ведёт на отсутствующий элемент страницы',
  aria_labelledby_target: 'aria-labelledby ссылается на отсутствующий ID',
  aria_labelledby_value: 'некорректный список ID в aria-labelledby',
  class_tokens: 'имена CSS-классов не соответствуют формату генератора',
  icon_marker: 'маркер иконки имеет недопустимый формат',
  photo_query: 'некорректный поисковый запрос для фото',
  markup_structure: 'нарушена структура или другое ограничение HTML-разметки',
});

const COPY_RULE_LABELS = Object.freeze({
  copy_duplicate: 'повторяется заголовок или абзац',
  copy_mixed_language: 'текст содержит слова не на языке брифа',
  copy_grammar: 'нужна проверка грамматики и формулировки',
  copy_scarcity: 'есть неподтверждённое заявление о срочности или количестве мест',
  copy_business_claim: 'есть неподтверждённое условие работы или услуги',
  copy_inventory_status: 'не подтверждена доступность или наличие товара',
  copy_unconfirmed_product: 'есть неподтверждённая характеристика товара',
  copy_unconfirmed_credential: 'есть неподтверждённая квалификация или достижение',
  copy_lesson_claim: 'есть неподтверждённое описание процесса или результата',
  copy_inert_form: 'кнопка обещает отправку формы или обратную связь',
  copy_contact_placeholder: 'обнаружена заглушка контактных данных',
  copy_in_person_visit: 'личный визит не подтверждён в брифе',
  copy_empty_contact_section: 'раздел контактов не содержит подтверждённого способа связи',
  copy_repeated_facts: 'факты повторяются в разных разделах',
  copy_pricing_hero_fact: 'цена или условие тарифа повторяется в нескольких разделах',
  copy_rule_missing: 'сервер не передал конкретное правило проверки текста',
  copy_rule_unrecognized: 'код проверки текста не входит в безопасный список',
});

const CSS_RULE_LABELS = Object.freeze({
  css_empty: 'CSS пустой',
  css_unsafe_syntax: 'обнаружен запрещённый CSS-синтаксис',
  css_nesting: 'вложенность CSS превышает допустимый уровень',
  css_media_rule: 'некорректное или неподдерживаемое media-правило',
  css_rule_syntax: 'CSS-блок имеет некорректный формат',
  'css_rule_syntax:unclosed_media': 'у media-блока нет закрывающей скобки',
  'css_rule_syntax:missing_open_brace': 'у CSS-блока нет открывающей скобки',
  'css_rule_syntax:unclosed_rule': 'у CSS-блока нет закрывающей скобки',
  'css_rule_syntax:nested_rule': 'селектор содержит вложенное CSS-правило',
  css_selector_syntax: 'селектор имеет некорректный формат',
  css_rule_limit: 'превышено допустимое количество CSS-правил',
  css_no_valid_rules: 'не найдено ни одного допустимого CSS-правила',
  css_declaration_limit: 'превышено допустимое количество свойств в правиле',
});

const TASTE_RULE_LABELS = Object.freeze({
  hero_heading: 'нужен один H1 внутри Hero',
  section_headings: 'у каждой секции должен быть заголовок',
  section_copy: 'в каждой секции нужно не менее 32 видимых символов',
  hero_action: 'действие в Hero не добавляется без подходящей цели',
  responsive_css: 'нужно адаптивное правило для узкого экрана',
});

const STAGE_LABELS = Object.freeze({
  project_check: 'Проверка проекта',
  payload_load: 'Загрузка параметров задачи',
  provider_setup: 'Подготовка AI-профиля',
  design_context: 'Подготовка дизайн-контекста',
  open_design_reference: 'Поиск структурного примера',
  open_design_pipeline: 'Контракт Open Design',
  documentation_css_fallback: 'Безопасная раскладка документации',
  design_model_request: 'Запрос дизайна к модели',
  response_parse: 'Разбор ответа модели',
  markup_validation: 'Проверка структуры и HTML/CSS',
  copy_validation: 'Проверка текста',
  hero_validation: 'Проверка композиции Hero',
  contrast_validation: 'Проверка контраста',
  contact_validation: 'Проверка контактов',
  skill_validation: 'Проверка правил Skill',
  repair_decision: 'Решение об исправлении',
  repair_model_request: 'Запрос исправления к модели',
  contrast_repair: 'Исправление контраста',
  photo_catalog: 'Подбор фотографий',
  photo_model_request: 'Выбор фото по содержанию',
  result_persistence: 'Сохранение результата задачи',
  model_request: 'Запрос к модели',
});

const STAGE_STATE_LABELS = Object.freeze({
  started: 'начат',
  completed: 'готово',
  failed: 'ошибка',
  skipped: 'пропущен',
});

const OPEN_DESIGN_TEMPLATE_LABELS = Object.freeze({
  'saas-landing': 'Open Design · SaaS Landing',
  'web-prototype': 'Open Design · Web Prototype',
  'blog-post': 'Open Design · Blog Post',
  'docs-page': 'Open Design · Docs Page',
  'pricing-page': 'Open Design · Pricing Page',
  'waitlist-page': 'Open Design · Waitlist Page',
  dashboard: 'Open Design · Dashboard',
});

const OPEN_DESIGN_REFERENCE_STATUS_LABELS = Object.freeze({
  loaded: 'источник загружен',
  unavailable: 'источник недоступен; использована резервная инструкция',
  not_matched: 'подходящий источник не выбран',
});

export function generationStageLabel(stage) {
  return Object.hasOwn(STAGE_LABELS, stage) ? STAGE_LABELS[stage] : 'Этап генерации';
}

export function generationStageStateLabel(state) {
  return Object.hasOwn(STAGE_STATE_LABELS, state) ? STAGE_STATE_LABELS[state] : 'обновлён';
}

export function generationTemplateReferenceLabel(templateId) {
  return Object.hasOwn(OPEN_DESIGN_TEMPLATE_LABELS, templateId) ? OPEN_DESIGN_TEMPLATE_LABELS[templateId] : '';
}

export function generationReferenceStatusLabel(status) {
  return Object.hasOwn(OPEN_DESIGN_REFERENCE_STATUS_LABELS, status) ? OPEN_DESIGN_REFERENCE_STATUS_LABELS[status] : '';
}

export function generationTemplateReferenceFromEvents(events) {
  if (!Array.isArray(events)) return null;
  const event = events.slice().reverse().find(item => item && typeof item === 'object' && item.stage === 'open_design_reference');
  if (!event) return null;
  const templateId = Object.hasOwn(OPEN_DESIGN_TEMPLATE_LABELS, event.templateId) ? event.templateId : '';
  const referenceStatus = Object.hasOwn(OPEN_DESIGN_REFERENCE_STATUS_LABELS, event.referenceStatus) ? event.referenceStatus : '';
  return templateId && referenceStatus ? { templateId, referenceStatus } : null;
}

export function visibleGenerationEvents(events, limit = 18) {
  if (!Array.isArray(events)) return [];
  const safeLimit = Number.isInteger(limit) && limit > 0 ? Math.min(limit, 50) : 18;
  const visible = events.slice(-safeLimit);
  const sourceReference = events.slice().reverse().find(event => event && typeof event === 'object' && event.stage === 'open_design_reference');
  if (sourceReference && !visible.includes(sourceReference)) visible.unshift(sourceReference);
  const context=events.filter(event=>event?.stage==='open_design_pipeline'&&['loaded','no_match','ambiguous','unavailable','source_unavailable','blueprint_extracted','blueprint_in_prompt'].includes(event.pipelineStage));
  for(const event of context.slice().reverse())if(!visible.includes(event))visible.unshift(event);
  return visible;
}

export function markupRuleLabel(code) {
  if (typeof code === 'string' && /^composition_(hero|order|collection|records|docs|rail|article|waitlist|scope|blueprint)$/.test(code)) return `нарушен контракт композиции Open Design (${code})`;
  if (typeof code === 'string' && code.startsWith('fragment_target:')) return MARKUP_RULE_LABELS.fragment_target;
  if (typeof code === 'string' && code.startsWith('tag_allowlist:')) {
    const tag = code.slice('tag_allowlist:'.length);
    return /^[a-z][a-z0-9-]{0,31}$/.test(tag)
      ? `тег ${tag} не входит в список разрешённых`
      : MARKUP_RULE_LABELS.tag_allowlist;
  }
  if (typeof code === 'string' && code.startsWith('attribute_allowlist:')) {
    const attribute = code.slice('attribute_allowlist:'.length);
    return /^[a-z][a-z0-9:_-]{0,31}$/.test(attribute)
      ? `атрибут ${attribute} не входит в список разрешённых`
      : MARKUP_RULE_LABELS.attribute_allowlist;
  }
  return Object.hasOwn(MARKUP_RULE_LABELS, code)
    ? MARKUP_RULE_LABELS[code]
    : 'другое ограничение безопасной HTML-разметки';
}

export function copyRuleLabel(code) {
  return Object.hasOwn(COPY_RULE_LABELS, code)
    ? COPY_RULE_LABELS[code]
    : 'требуется проверить текст на точность и грамотность';
}

export function cssRuleLabel(code) {
  return Object.hasOwn(CSS_RULE_LABELS, code)
    ? CSS_RULE_LABELS[code]
    : 'другое ограничение безопасного CSS';
}

export function tasteRuleLabel(code) {
  if (typeof code !== 'string' || !code.startsWith('taste:')) return 'нарушено правило профиля Taste';
  const rules = code.slice('taste:'.length).split(',');
  if (rules.length < 1 || rules.length > 5 || rules.some(rule => !Object.hasOwn(TASTE_RULE_LABELS, rule))) return 'нарушено правило профиля Taste';
  return [...new Set(rules)].map(rule => TASTE_RULE_LABELS[rule]).join('; ');
}

export function generationEventRuleLabel(event) {
  const code = event?.validationRule;
  const isCopyRule = event?.reasonCode === 'copy_quality' || (typeof code === 'string' && code.startsWith('copy_'));
  const isCssRule = event?.reasonCode === 'invalid_css' || (typeof code === 'string' && code.startsWith('css_'));
  if (event?.reasonCode === 'skill_requirements' && typeof code === 'string' && code.startsWith('taste:')) return `Skill: ${tasteRuleLabel(code)}`;
  if (isCssRule) return `CSS: ${cssRuleLabel(code)}`;
  return `${isCopyRule ? 'текст' : 'разметка'}: ${isCopyRule ? copyRuleLabel(code) : markupRuleLabel(code)}`;
}

export function generationEventEvidenceLabel(event) {
  if (event?.validationRule === 'copy_duplicate') {
    const evidence=event?.validationEvidence;
    if(evidence?.kind==='exact'&&['heading','paragraph'].includes(evidence.copyType)
      &&Number.isInteger(evidence.firstIndex)&&evidence.firstIndex>=1&&evidence.firstIndex<=100
      &&Number.isInteger(evidence.duplicateIndex)&&evidence.duplicateIndex>=2&&evidence.duplicateIndex<=100
      &&evidence.firstIndex!==evidence.duplicateIndex){
      const label=evidence.copyType==='heading'?'заголовки':'абзацы';
      return `${label} №${evidence.firstIndex} и №${evidence.duplicateIndex} совпадают`;
    }
    if(evidence?.kind==='overlap'&&evidence.copyType==='heading_paragraph'
      &&Number.isInteger(evidence.sectionIndex)&&evidence.sectionIndex>=1&&evidence.sectionIndex<=9
      &&Number.isInteger(evidence.headingIndex)&&evidence.headingIndex>=1&&evidence.headingIndex<=24
      &&Number.isInteger(evidence.paragraphIndex)&&evidence.paragraphIndex>=1&&evidence.paragraphIndex<=24){
      const overlapLabel=evidence.overlapType==='near_duplicate'?'близкое совпадение':'повтор текста';
      return 'секция №'+evidence.sectionIndex+', заголовок №'+evidence.headingIndex+', абзац №'+evidence.paragraphIndex+': '+overlapLabel;
    }
    return '';
  }
  if (event?.validationRule !== 'copy_repeated_facts') return '';
  const evidence = event?.validationEvidence;
  const sections = evidence?.sectionIndexes;
  const paragraphs = evidence?.paragraphIndexes;
  if (!Array.isArray(sections) || sections.length !== 2 || !sections.every((value) => Number.isInteger(value) && value >= 1 && value <= 9)
    || sections[0] === sections[1]
    || !Array.isArray(paragraphs) || paragraphs.length !== 2 || !paragraphs.every((value) => Number.isInteger(value) && value >= 1 && value <= 24)) return '';
  const kinds={near_duplicate:'близкие абзацы',ordered_run:'общая последовательность',both:'оба типа перекрытия'};
  const kind=Object.hasOwn(kinds,evidence.overlapType)?kinds[evidence.overlapType]:'';
  const run=Number.isInteger(evidence.orderedRunLength)&&evidence.orderedRunLength>=0&&evidence.orderedRunLength<=100?evidence.orderedRunLength:null;
  return 'секции '+sections[0]+' и '+sections[1]+', абзацы '+paragraphs[0]+' и '+paragraphs[1]+(kind?'; '+kind:'')+(kind&&['ordered_run','both'].includes(evidence.overlapType)&&run!==null?'; подряд '+run:'');
}

const PIPELINE_LABELS = Object.freeze({no_match:'источник не выбран', ambiguous:'неоднозначный выбор', unavailable:'источник недоступен',source_unavailable:'источник недоступен', loaded:'pinned источник проверен', blueprint_extracted:'структура извлечена', blueprint_in_prompt:'контракт передан модели', output_conforms:'структурная проверка пройдена', validation_rejected:'результат отклонён', preview_ready:'кандидат готов к превью'});
export function generationPipelineLabel(stage) { return Object.hasOwn(PIPELINE_LABELS,stage) ? PIPELINE_LABELS[stage] : ''; }
