const MARKUP_RULE_LABELS = Object.freeze({
  tag_allowlist: 'найден тег, который нельзя использовать',
  attribute_allowlist: 'найден атрибут или встроенный стиль вне списка разрешённых',
  section_count_low: 'найдено меньше 3 секций',
  section_count_high: 'превышен максимум в 9 секций',
  hero_count: 'должен быть ровно один Hero с классом ai-hero',
  image_slot_count: 'превышен лимит фото-слотов',
  fragment_target: 'ссылка ведёт на отсутствующий элемент страницы',
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
  copy_lesson_claim: 'есть неподтверждённое описание процесса или результата',
  copy_inert_form: 'кнопка обещает отправку формы или обратную связь',
  copy_contact_placeholder: 'обнаружена заглушка контактных данных',
  copy_in_person_visit: 'личный визит не подтверждён в брифе',
});

export function markupRuleLabel(code) {
  return Object.hasOwn(MARKUP_RULE_LABELS, code)
    ? MARKUP_RULE_LABELS[code]
    : 'другое ограничение безопасной HTML-разметки';
}

export function copyRuleLabel(code) {
  return Object.hasOwn(COPY_RULE_LABELS, code)
    ? COPY_RULE_LABELS[code]
    : 'требуется проверить текст на точность и грамотность';
}
