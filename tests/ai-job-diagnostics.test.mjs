import test from 'node:test';
import assert from 'node:assert/strict';
import { copyRuleLabel, markupRuleLabel } from '../src/ai-job-diagnostics.js';

test('markup diagnostics translate only allowlisted validation codes', () => {
  assert.equal(markupRuleLabel('tag_allowlist'), 'найден тег, который нельзя использовать');
  assert.equal(markupRuleLabel('attribute_allowlist'), 'найден атрибут или встроенный стиль вне списка разрешённых');
  assert.equal(markupRuleLabel('section_count_low'), 'найдено меньше 3 секций');
  assert.equal(markupRuleLabel('section_count_high'), 'превышен максимум в 9 секций');
  assert.equal(markupRuleLabel('hero_count'), 'должен быть ровно один Hero с классом ai-hero');
  assert.equal(markupRuleLabel('image_slot_count'), 'превышен лимит фото-слотов');
  assert.equal(markupRuleLabel('fragment_target'), 'ссылка ведёт на отсутствующий элемент страницы');
  assert.equal(markupRuleLabel('class_tokens'), 'имена CSS-классов не соответствуют формату генератора');
  assert.equal(markupRuleLabel('icon_marker'), 'маркер иконки имеет недопустимый формат');
  assert.equal(markupRuleLabel('photo_query'), 'некорректный поисковый запрос для фото');
  assert.equal(markupRuleLabel('markup_structure'), 'нарушена структура или другое ограничение HTML-разметки');
});

test('unknown or provider-supplied strings never appear in markup diagnostics', () => {
  assert.equal(markupRuleLabel('secret raw model output'), 'другое ограничение безопасной HTML-разметки');
  assert.equal(markupRuleLabel(undefined), 'другое ограничение безопасной HTML-разметки');
});

test('copy diagnostics translate only fixed audit codes', () => {
  assert.equal(copyRuleLabel('copy_mixed_language'), 'текст содержит слова не на языке брифа');
  assert.equal(copyRuleLabel('copy_duplicate'), 'повторяется заголовок или абзац');
  assert.equal(copyRuleLabel('copy_in_person_visit'), 'личный визит не подтверждён в брифе');
  assert.equal(copyRuleLabel('secret raw model output'), 'требуется проверить текст на точность и грамотность');
});
