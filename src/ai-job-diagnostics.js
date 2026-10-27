const MARKUP_RULE_LABELS = Object.freeze({
  tag_allowlist: 'найден тег, который нельзя использовать',
  attribute_allowlist: 'найден атрибут или встроенный стиль вне списка разрешённых',
  section_count: 'неверное количество секций, Hero или фото-слотов',
  fragment_target: 'ссылка ведёт на отсутствующий элемент страницы',
  class_tokens: 'имена CSS-классов не соответствуют формату генератора',
  icon_marker: 'маркер иконки имеет недопустимый формат',
  photo_query: 'некорректный поисковый запрос для фото',
  markup_structure: 'нарушена структура или другое ограничение HTML-разметки',
});

export function markupRuleLabel(code) {
  return Object.hasOwn(MARKUP_RULE_LABELS, code)
    ? MARKUP_RULE_LABELS[code]
    : 'другое ограничение безопасной HTML-разметки';
}
