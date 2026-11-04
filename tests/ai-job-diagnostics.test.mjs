import test from 'node:test';
import assert from 'node:assert/strict';
import { copyRuleLabel, cssRuleLabel, generationEventEvidenceLabel, generationEventRuleLabel, generationReferenceStatusLabel, generationStageLabel, generationStageStateLabel, generationTemplateReferenceFromEvents, generationTemplateReferenceLabel, markupRuleLabel, tasteRuleLabel, visibleGenerationEvents } from '../src/ai-job-diagnostics.js';

test('markup diagnostics translate only allowlisted validation codes', () => {
  assert.equal(markupRuleLabel('tag_allowlist'), 'найден тег, который нельзя использовать');
  assert.equal(markupRuleLabel('tag_allowlist:figure'), 'тег figure не входит в список разрешённых');
  assert.equal(markupRuleLabel('tag_allowlist:figure'), 'тег figure не входит в список разрешённых');
  assert.equal(markupRuleLabel('attribute_allowlist'), 'найден атрибут или встроенный стиль вне списка разрешённых');
  assert.equal(markupRuleLabel('attribute_allowlist:aria-labelledby'), 'атрибут aria-labelledby не входит в список разрешённых');
  assert.equal(markupRuleLabel('aria_labelledby_target'), 'aria-labelledby ссылается на отсутствующий ID');
  assert.equal(markupRuleLabel('aria_labelledby_value'), 'некорректный список ID в aria-labelledby');
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

test('CSS diagnostics describe fixed allowlisted validation codes without exposing provider output', () => {
  assert.equal(cssRuleLabel('css_empty'), 'CSS пустой');
  assert.equal(cssRuleLabel('css_rule_syntax'), 'CSS-блок имеет некорректный формат');
  assert.equal(cssRuleLabel('css_rule_syntax:unclosed_rule'), 'у CSS-блока нет закрывающей скобки');
  assert.equal(cssRuleLabel('css_rule_syntax:nested_rule'), 'селектор содержит вложенное CSS-правило');
  assert.equal(cssRuleLabel('private-css-fragment'), 'другое ограничение безопасного CSS');
  assert.equal(generationEventRuleLabel({ reasonCode: 'invalid_css', validationRule: 'css_empty' }), 'CSS: CSS пустой');
  assert.equal(generationEventRuleLabel({ reasonCode: 'invalid_css', validationRule: 'css_rule_syntax:unclosed_rule' }), 'CSS: у CSS-блока нет закрывающей скобки');
});

test('unknown or provider-supplied strings never appear in markup diagnostics', () => {
  assert.equal(markupRuleLabel('secret raw model output'), 'другое ограничение безопасной HTML-разметки');
  assert.equal(markupRuleLabel(undefined), 'другое ограничение безопасной HTML-разметки');
  assert.equal(markupRuleLabel('attribute_allowlist:<script>secret'), 'найден атрибут или встроенный стиль вне списка разрешённых');
  assert.equal(markupRuleLabel('tag_allowlist:<script>secret'), 'найден тег, который нельзя использовать');
});

test('copy diagnostics translate only fixed audit codes', () => {
  assert.equal(copyRuleLabel('copy_mixed_language'), 'текст содержит слова не на языке брифа');
  assert.equal(copyRuleLabel('copy_duplicate'), 'повторяется заголовок или абзац');
  assert.equal(copyRuleLabel('copy_in_person_visit'), 'личный визит не подтверждён в брифе');
  assert.equal(copyRuleLabel('copy_repeated_facts'), 'факты повторяются в разных разделах');
  assert.equal(copyRuleLabel('copy_pricing_hero_fact'), 'цена или условие тарифа повторяется в нескольких разделах');
  assert.equal(copyRuleLabel('copy_inert_form'), 'кнопка обещает отправку формы или обратную связь');
  assert.equal(copyRuleLabel('copy_rule_missing'), 'сервер не передал конкретное правило проверки текста');
  assert.equal(copyRuleLabel('copy_rule_unrecognized'), 'код проверки текста не входит в безопасный список');
  assert.equal(copyRuleLabel('secret raw model output'), 'требуется проверить текст на точность и грамотность');
});

test('generation timeline labels only known stages and states', () => {
  assert.equal(generationStageLabel('markup_validation'), 'Проверка структуры и HTML/CSS');
  assert.equal(generationStageLabel('documentation_css_fallback'), 'Безопасная раскладка документации');
  assert.equal(generationStageLabel('private model response'), 'Этап генерации');
  assert.equal(generationStageStateLabel('failed'), 'ошибка');
  assert.equal(generationStageStateLabel('raw provider error'), 'обновлён');
});

test('generation timeline labels only allowlisted Open Design references and load states', () => {
  assert.equal(generationTemplateReferenceLabel('blog-post'), 'Open Design · Blog Post');
  assert.equal(generationTemplateReferenceLabel('dashboard'), 'Open Design · Dashboard');
  assert.equal(generationTemplateReferenceLabel('private-source-path'), '');
  assert.equal(generationReferenceStatusLabel('loaded'), 'источник загружен');
  assert.equal(generationReferenceStatusLabel('unavailable'), 'источник недоступен; использована резервная инструкция');
  assert.equal(generationReferenceStatusLabel('private-provider-text'), '');
});

test('generation preview resolves only allowlisted Open Design source events', () => {
  assert.deepEqual(generationTemplateReferenceFromEvents([
    { stage: 'open_design_reference', templateId: 'docs-page', referenceStatus: 'loaded' },
    { stage: 'markup_validation', state: 'completed' },
  ]), { templateId: 'docs-page', referenceStatus: 'loaded' });
  assert.equal(generationTemplateReferenceFromEvents([{ stage: 'open_design_reference', templateId: 'private-path', referenceStatus: 'loaded' }]), null);
  assert.equal(generationTemplateReferenceFromEvents([{ stage: 'open_design_reference', templateId: 'docs-page', referenceStatus: 'raw-output' }]), null);
  assert.equal(generationTemplateReferenceFromEvents(null), null);
});

test('generation timeline keeps the Open Design source visible outside the recent event window', () => {
  const sourceEvent = { stage: 'open_design_reference', state: 'completed', templateId: 'docs-page', referenceStatus: 'loaded' };
  const events = [sourceEvent, ...Array.from({ length: 18 }, (_, index) => ({ stage: `stage-${index}`, state: 'completed' }))];
  const visible = visibleGenerationEvents(events);
  assert.equal(visible.length, 19);
  assert.equal(visible[0], sourceEvent);
  assert.equal(visible.at(-1), events.at(-1));
  assert.deepEqual(visibleGenerationEvents(null), []);
});

test('generation timeline classifies safe copy failures even if a stage lacks its category', () => {
  assert.equal(generationEventRuleLabel({ validationRule: 'copy_repeated_facts' }), 'текст: факты повторяются в разных разделах');
  assert.equal(generationEventRuleLabel({ reasonCode: 'copy_quality', validationRule: 'unknown' }), 'текст: требуется проверить текст на точность и грамотность');
  assert.equal(generationEventRuleLabel({ reasonCode: 'copy_quality', validationRule: 'copy_rule_missing' }), 'текст: сервер не передал конкретное правило проверки текста');
  assert.equal(generationEventRuleLabel({ validationRule: 'tag_allowlist' }), 'разметка: найден тег, который нельзя использовать');
  assert.equal(generationEventRuleLabel({ validationRule: 'raw provider output' }), 'разметка: другое ограничение безопасной HTML-разметки');
  assert.equal(tasteRuleLabel('taste:hero_action,section_copy'), 'действие в Hero не добавляется без подходящей цели; в каждой секции нужно не менее 32 видимых символов');
  assert.equal(tasteRuleLabel('taste:private text'), 'нарушено правило профиля Taste');
  assert.equal(generationEventRuleLabel({ reasonCode: 'skill_requirements', validationRule: 'taste:section_copy' }), 'Skill: в каждой секции нужно не менее 32 видимых символов');
});

test('repeat-fact diagnostics show only bounded coordinates and allowed overlap metadata', () => {
  assert.equal(generationEventEvidenceLabel({ validationRule: 'copy_repeated_facts', validationEvidence: { sectionIndexes: [1, 3], paragraphIndexes: [2, 1], overlapType: 'both', orderedRunLength: 4, sharedTokenCount: 7, smallerParagraphTokenCount: 8, rejectedCopy: 'private text' } }), 'секции 1 и 3, абзацы 2 и 1; оба типа перекрытия; подряд 4');
  assert.equal(generationEventEvidenceLabel({ validationRule: 'copy_repeated_facts', validationEvidence: { sectionIndexes: [1, 3], paragraphIndexes: [2, 1], overlapType: 'ordered_run', orderedRunLength: 3, sharedTokenCount: 7, smallerParagraphTokenCount: 8 } }), 'секции 1 и 3, абзацы 2 и 1; общая последовательность; подряд 3');
  assert.equal(generationEventEvidenceLabel({ validationRule: 'copy_repeated_facts', validationEvidence: { sectionIndexes: [1, 2], paragraphIndexes: [1, 1], overlapType: 'near_duplicate', sharedTokenCount: 7, smallerParagraphTokenCount: 8 } }), 'секции 1 и 2, абзацы 1 и 1; близкие абзацы');
  assert.equal(generationEventEvidenceLabel({ validationRule: 'copy_repeated_facts', validationEvidence: { sectionIndexes: [1, 1], paragraphIndexes: [2, 1], overlapType: 'ordered_run', orderedRunLength: 3 } }), '');
  assert.equal(generationEventEvidenceLabel({ validationRule: 'copy_repeated_facts', validationEvidence: { sectionIndexes: ['private text', 3], paragraphIndexes: [2, 1], overlapType: 'ordered_run', orderedRunLength: 3 } }), '');
});
test('duplicate-copy diagnostics identify only safe copy coordinates', () => {
  assert.equal(generationEventEvidenceLabel({ validationRule: 'copy_duplicate', validationEvidence: { kind: 'exact', copyType: 'heading', firstIndex: 1, duplicateIndex: 3 } }), 'заголовки №1 и №3 совпадают');
  assert.equal(generationEventEvidenceLabel({ validationRule: 'copy_duplicate', validationEvidence: { kind: 'exact', copyType: 'paragraph', firstIndex: 2, duplicateIndex: 4, rejectedCopy: 'private text' } }), 'абзацы №2 и №4 совпадают');
  assert.equal(generationEventEvidenceLabel({ validationRule: 'copy_duplicate', validationEvidence: { kind: 'overlap', copyType: 'heading_paragraph', sectionIndex: 2, headingIndex: 1, paragraphIndex: 2, overlapType: 'near_duplicate', sharedTokenCount: 5, smallerTokenCount: 6, rejectedCopy: 'private text' } }), 'секция №2, заголовок №1, абзац №2: близкое совпадение');
  assert.equal(generationEventEvidenceLabel({ validationRule: 'copy_duplicate', validationEvidence: { kind: 'exact', copyType: 'heading', firstIndex: 1, duplicateIndex: 1 } }), '');
  assert.equal(generationEventEvidenceLabel({ validationRule: 'copy_duplicate', validationEvidence: { kind: 'overlap', copyType: 'heading_paragraph', sectionIndex: 2, headingIndex: 1, paragraphIndex: 2, overlapType: 'near_duplicate', sharedTokenCount: 7, smallerTokenCount: 6 } }), 'секция №2, заголовок №1, абзац №2: близкое совпадение');
});

test('Open Design pipeline uses fixed labels and rejects arbitrary stage text', async () => {
  const {generationPipelineLabel}=await import('../src/ai-job-diagnostics.js');
  assert.equal(generationPipelineLabel('blueprint_in_prompt'),'контракт передан модели');
  assert.equal(generationPipelineLabel('unavailable'),'источник недоступен');
  assert.equal(generationPipelineLabel('private provider payload'),'');
  assert.match(markupRuleLabel('composition_collection'),/Open Design/);
  assert.match(generationEventEvidenceLabel({validationRule:'copy_repeated_facts',validationEvidence:{sectionIndexes:[1,2],paragraphIndexes:[1,1],overlapType:'ordered_run',orderedRunLength:3}}),/общая последовательность; подряд 3/);
});
