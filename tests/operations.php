<?php
declare(strict_types=1);
require __DIR__ . '/../backend/Domain.php';
require __DIR__ . '/../backend/Operations.php';
use Juzimder\{Document, Problem, ProjectOperations};

function operationCheck(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS $message\n";
}
function operationRejects(callable $call, int $status, string $message): void {
    try { $call(); } catch (Problem $error) {
        operationCheck($error->status === $status, $message);
        return;
    }
    throw new RuntimeException('Expected rejection: ' . $message);
}
function operationObject(array $value): \stdClass {
    return json_decode(json_encode($value, JSON_THROW_ON_ERROR), false, 64, JSON_THROW_ON_ERROR);
}
function operationNode(mixed $node, string $id): ?\stdClass {
    if (!$node instanceof \stdClass) return null;
    if (($node->attributes->id ?? null) === $id || ($node->id ?? null) === $id) return $node;
    foreach (get_object_vars($node) as $value) {
        if (is_array($value)) {
            foreach ($value as $child) if ($found = operationNode($child, $id)) return $found;
        } elseif ($value instanceof \stdClass && ($found = operationNode($value, $id))) return $found;
    }
    return null;
}

$fixture = json_decode(file_get_contents(__DIR__ . '/grapesjs-0.23.8.fixture.json'), false, 64, JSON_THROW_ON_ERROR);
$original = json_decode(json_encode($fixture, JSON_THROW_ON_ERROR), false, 64, JSON_THROW_ON_ERROR);

$contentUpdated = ProjectOperations::apply($fixture, [operationObject(['operation'=>'update_content','componentId'=>'ij4p','content'=>'Локальное изменение'])]);
operationCheck(operationNode($contentUpdated, 'ij4p')->content === 'Локальное изменение', 'Typed content update targets one stable component ID');
operationCheck(operationNode($fixture, 'ij4p')->content === operationNode($original, 'ij4p')->content, 'Applying operations never mutates the caller document');
$renamed = ProjectOperations::apply($fixture, [operationObject(['operation'=>'rename_component','componentId'=>'ieg3','name'=>'Новая карточка'])]);
operationCheck(operationNode($renamed, 'ieg3')->name === 'Новая карточка', 'Typed rename updates only the requested component name');
operationRejects(fn()=>ProjectOperations::apply($fixture,[operationObject(['operation'=>'rename_component','componentId'=>'ieg3','name'=>'  '])]),422,'Typed rename rejects empty names');
operationRejects(fn()=>ProjectOperations::apply($fixture,[operationObject(['operation'=>'rename_component','componentId'=>'ieg3','name'=>str_repeat('x',101)])]),422,'Typed rename enforces the 100 character limit');
$tagUpdated = ProjectOperations::apply($fixture, [operationObject(['operation'=>'update_tag_name','componentId'=>'ij4p','tagName'=>'h2'])]);
operationCheck(operationNode($tagUpdated, 'ij4p')->tagName === 'h2' && operationNode($tagUpdated, 'ij4p')->content === operationNode($fixture, 'ij4p')->content, 'Typed semantic tag update preserves selected text and stable component identity');
operationRejects(fn()=>ProjectOperations::apply($fixture,[operationObject(['operation'=>'update_tag_name','componentId'=>'ij4p','tagName'=>'script'])]),422,'Typed semantic tag update rejects tags outside the safe text allowlist');
operationRejects(fn()=>ProjectOperations::apply($fixture,[operationObject(['operation'=>'update_tag_name','componentId'=>'ij4p','tagName'=>'form'])]),422,'Typed semantic tag update cannot turn text into an interactive form');
$containerTagUpdated = ProjectOperations::apply($fixture, [operationObject(['operation'=>'update_tag_name','componentId'=>'ieg3','tagName'=>'article'])]);
operationCheck(operationNode($containerTagUpdated, 'ieg3')->tagName === 'article', 'Typed semantic tag update supports safe structural container tags');
operationRejects(fn()=>ProjectOperations::apply($fixture,[operationObject(['operation'=>'update_tag_name','componentId'=>'iwnjm','tagName'=>'section'])]),422,'Typed semantic tag update rejects structural tags for special component types');
$attributesUpdated = ProjectOperations::apply($fixture, [operationObject(['operation'=>'update_attributes','componentId'=>'iwnjm','attributes'=>['title'=>'Записаться','target'=>'_blank','rel'=>'noopener']])]);
operationCheck(operationNode($attributesUpdated, 'iwnjm')->attributes->title === 'Записаться', 'Typed attribute update changes only allowlisted attributes');
$attributesCleared = ProjectOperations::apply($attributesUpdated, [operationObject(['operation'=>'update_attributes','componentId'=>'iwnjm','attributes'=>['title'=>null]])]);
operationCheck(!isset(operationNode($attributesCleared, 'iwnjm')->attributes->title), 'Typed attribute update removes fields with null');
$rendererSafeAttributes = ProjectOperations::apply($fixture, [operationObject(['operation'=>'update_attributes','componentId'=>'iwnjm','attributes'=>['class'=>'cta primary','target'=>'_blank','rel'=>'nofollow noopener','aria-label'=>'Open details','aria-hidden'=>'false']])]);
operationCheck(operationNode($rendererSafeAttributes, 'iwnjm')->attributes->class === 'cta primary' && operationNode($rendererSafeAttributes, 'iwnjm')->attributes->rel === 'nofollow noopener' && operationNode($rendererSafeAttributes, 'iwnjm')->attributes->{'aria-hidden'} === 'false', 'Typed updates accept common attributes using static-renderer formats');
operationRejects(fn()=>ProjectOperations::apply($fixture,[operationObject(['operation'=>'update_attributes','componentId'=>'iwnjm','attributes'=>['target'=>'_parent']])]),422,'Typed target rejects values the static renderer would omit');
operationRejects(fn()=>ProjectOperations::apply($fixture,[operationObject(['operation'=>'update_attributes','componentId'=>'iwnjm','attributes'=>['rel'=>'stylesheet']])]),422,'Typed rel rejects tokens the static renderer would omit');
operationRejects(fn()=>ProjectOperations::apply($fixture,[operationObject(['operation'=>'update_attributes','componentId'=>'iwnjm','attributes'=>['aria-label'=>str_repeat('x',257)]])]),422,'Typed ARIA values respect the static renderer length limit');
operationRejects(fn()=>ProjectOperations::apply($fixture,[operationObject(['operation'=>'update_attributes','componentId'=>'iwnjm','attributes'=>['aria-hidden'=>'yes']])]),422,'Typed aria-hidden only accepts true or false');
operationRejects(fn()=>ProjectOperations::apply($fixture,[operationObject(['operation'=>'update_attributes','componentId'=>'iwnjm','attributes'=>['class'=>'cta;display:none']])]),422,'Typed class attribute rejects values the static renderer would omit');
$dataAttributesUpdated = ProjectOperations::apply($fixture, [operationObject(['operation'=>'update_attributes','componentId'=>'iwnjm','attributes'=>['data-campaign'=>'spring & summer','data-analytics-id'=>'cta-primary']])]);
operationCheck(operationNode($dataAttributesUpdated, 'iwnjm')->attributes->{'data-campaign'} === 'spring & summer' && operationNode($dataAttributesUpdated, 'iwnjm')->attributes->{'data-analytics-id'} === 'cta-primary', 'Typed attribute update accepts bounded lowercase data-* metadata');
$dataAttributesCleared = ProjectOperations::apply($dataAttributesUpdated, [operationObject(['operation'=>'update_attributes','componentId'=>'iwnjm','attributes'=>['data-campaign'=>null]])]);
operationCheck(!isset(operationNode($dataAttributesCleared, 'iwnjm')->attributes->{'data-campaign'}) && operationNode($dataAttributesCleared, 'iwnjm')->attributes->{'data-analytics-id'} === 'cta-primary', 'Typed attribute update removes one data-* key without changing siblings');
operationRejects(fn()=>ProjectOperations::apply($fixture,[operationObject(['operation'=>'update_attributes','componentId'=>'iwnjm','attributes'=>['data-Campaign'=>'spring']])]),422,'Typed data-* updates reject uppercase names');
operationRejects(fn()=>ProjectOperations::apply($fixture,[operationObject(['operation'=>'update_attributes','componentId'=>'iwnjm','attributes'=>['data-jz-form-id'=>'override']])]),422,'Typed data-* updates reserve renderer-owned names');
operationRejects(fn()=>ProjectOperations::apply($fixture,[operationObject(['operation'=>'update_attributes','componentId'=>'iwnjm','attributes'=>['data-campaign'=>str_repeat('x',2049)]])]),422,'Typed data-* values remain bounded');
$dataComponentCreated = ProjectOperations::apply(operationObject(Document::empty()), [operationObject(['operation'=>'create_component','parentId'=>'page:home','component'=>['type'=>'default','tagName'=>'section','attributes'=>['data-section-key'=>'hero']]])]);
$createdDataComponent = $dataComponentCreated->data->pages[0]->frames[0]->component->components[0];
operationCheck($createdDataComponent->attributes->{'data-section-key'} === 'hero', 'Typed component creation supports safe data-* metadata');
 $commonAttributesCreated = ProjectOperations::apply(operationObject(Document::empty()), [operationObject(['operation'=>'create_component','parentId'=>'page:home','component'=>['type'=>'default','tagName'=>'a','attributes'=>['id'=>'cta-primary','class'=>'cta primary','href'=>'https://example.test','target'=>'_blank','rel'=>'nofollow','aria-label'=>'Primary action']]])]);
$createdCommonAttributes = $commonAttributesCreated->data->pages[0]->frames[0]->component->components[0]->attributes;
operationCheck($createdCommonAttributes->id === 'cta-primary' && $createdCommonAttributes->class === 'cta primary' && $createdCommonAttributes->rel === 'nofollow', 'Typed creation accepts renderer-safe IDs, class names, link attributes and ARIA labels');
operationRejects(fn()=>ProjectOperations::apply(operationObject(Document::empty()),[operationObject(['operation'=>'create_component','parentId'=>'page:home','component'=>['type'=>'default','tagName'=>'div','attributes'=>['id'=>'not a safe id']]])]),422,'Typed creation rejects stable IDs the static renderer would omit');
operationRejects(fn()=>ProjectOperations::apply(operationObject(Document::empty()),[operationObject(['operation'=>'create_component','parentId'=>'page:home','component'=>['type'=>'default','tagName'=>'div','attributes'=>['class'=>'bad;class']]])]),422,'Typed creation rejects unsafe class attributes');
operationRejects(fn()=>ProjectOperations::apply(operationObject(Document::empty()),[operationObject(['operation'=>'create_component','parentId'=>'page:home','component'=>['type'=>'default','tagName'=>'section','attributes'=>['data-section-key'=>"hero\nunsafe"]]])]),422,'Typed component creation rejects control characters in data-* values');
$formCreated = ProjectOperations::apply(operationObject(Document::empty()), [operationObject([
    'operation'=>'create_component','parentId'=>'page:home','index'=>0,
    'component'=>['type'=>'form','tagName'=>'form','components'=>[
        ['type'=>'input','tagName'=>'input','attributes'=>['name'=>'email','type'=>'email','placeholder'=>'name@example.test','required'=>true]],
        ['type'=>'textarea','tagName'=>'textarea','attributes'=>['name'=>'message','rows'=>'4']]
    ]]
])]);
$createdForm = $formCreated->data->pages[0]->frames[0]->component->components[0];
operationCheck($createdForm->components[0]->attributes->name === 'email' && $createdForm->components[0]->attributes->required === true && $createdForm->components[1]->attributes->rows === '4', 'Typed create accepts safe native form field attributes and boolean required');
$formInputId = $createdForm->components[0]->attributes->id;
$formUpdated = ProjectOperations::apply($formCreated, [operationObject(['operation'=>'update_attributes','componentId'=>$formInputId,'attributes'=>['required'=>false,'placeholder'=>null,'disabled'=>true,'hidden'=>true]])]);
$updatedFormInput = operationNode($formUpdated, $formInputId);
operationCheck(!isset($updatedFormInput->attributes->required, $updatedFormInput->attributes->placeholder) && $updatedFormInput->attributes->disabled === true && $updatedFormInput->attributes->hidden === true, 'Typed attribute update removes false/null optional fields and persists true boolean fields');
$formUnhidden = ProjectOperations::apply($formUpdated, [operationObject(['operation'=>'update_attributes','componentId'=>$formInputId,'attributes'=>['hidden'=>false]])]);
operationCheck(!isset(operationNode($formUnhidden, $formInputId)->attributes->hidden), 'Typed update restores visibility by removing the native hidden attribute');
operationRejects(fn()=>ProjectOperations::apply($formCreated,[operationObject(['operation'=>'update_attributes','componentId'=>$formInputId,'attributes'=>['open'=>'true']])]),422,'Typed boolean open attribute rejects string values');
operationRejects(fn()=>ProjectOperations::apply($formCreated,[operationObject(['operation'=>'update_attributes','componentId'=>$formInputId,'attributes'=>['hidden'=>'true']])]),422,'Typed hidden visibility rejects string values');
operationRejects(fn()=>ProjectOperations::apply($formCreated,[operationObject(['operation'=>'update_attributes','componentId'=>$formInputId,'attributes'=>['aria-selected'=>'sometimes']])]),422,'Typed aria-selected only accepts standard ARIA values');
operationRejects(fn()=>ProjectOperations::apply($formCreated,[operationObject(['operation'=>'update_attributes','componentId'=>$formInputId,'attributes'=>['aria-expanded'=>'yes']])]),422,'Typed aria-expanded only accepts true or false');
operationRejects(fn()=>ProjectOperations::apply($formCreated,[operationObject(['operation'=>'update_attributes','componentId'=>$formInputId,'attributes'=>['aria-pressed'=>'on']])]),422,'Typed aria-pressed only accepts true, false or mixed');
$stateControl = ProjectOperations::apply(operationObject(Document::empty()), [operationObject([
    'operation'=>'create_component','parentId'=>'page:home','index'=>0,
    'component'=>['type'=>'button','tagName'=>'button','attributes'=>['aria-expanded'=>'false','aria-pressed'=>'mixed'],'content'=>'Toggle']
])]);
$stateControlId = $stateControl->data->pages[0]->frames[0]->component->components[0]->attributes->id;
operationCheck(operationNode($stateControl, $stateControlId)->attributes->{'aria-expanded'} === 'false' && operationNode($stateControl, $stateControlId)->attributes->{'aria-pressed'} === 'mixed', 'Typed create accepts validated expandable and toggle-button ARIA states');
$stateControlUpdated = ProjectOperations::apply($stateControl, [operationObject(['operation'=>'update_attributes','componentId'=>$stateControlId,'attributes'=>['aria-expanded'=>'true','aria-pressed'=>'true']])]);
operationCheck(operationNode($stateControlUpdated, $stateControlId)->attributes->{'aria-expanded'} === 'true' && operationNode($stateControlUpdated, $stateControlId)->attributes->{'aria-pressed'} === 'true', 'Typed update changes validated expandable and toggle-button ARIA states');
$detailsCreated = ProjectOperations::apply(operationObject(Document::empty()), [operationObject([
    'operation'=>'create_component','parentId'=>'page:home','index'=>0,
    'component'=>['type'=>'default','tagName'=>'details','attributes'=>['open'=>true],'components'=>[
        ['type'=>'text','tagName'=>'summary','content'=>'Подробнее'],
        ['type'=>'text','tagName'=>'p','attributes'=>['aria-controls'=>'details-panel','aria-selected'=>'false'],'content'=>'Описание']
    ]]
])]);
$createdDetails = $detailsCreated->data->pages[0]->frames[0]->component->components[0];
operationCheck($createdDetails->attributes->open === true && $createdDetails->components[1]->attributes->{'aria-controls'} === 'details-panel' && $createdDetails->components[1]->attributes->{'aria-selected'} === 'false', 'Typed create supports exporter-safe disclosure and ARIA state attributes');
$detailsClosed = ProjectOperations::apply($detailsCreated, [operationObject(['operation'=>'update_attributes','componentId'=>$createdDetails->attributes->id,'attributes'=>['open'=>false]])]);
operationCheck(!isset(operationNode($detailsClosed, $createdDetails->attributes->id)->attributes->open), 'Typed update removes the native details open attribute');
$timeCreated = ProjectOperations::apply(operationObject(Document::empty()), [operationObject([
    'operation'=>'create_component','parentId'=>'page:home','index'=>0,
    'component'=>['type'=>'text','tagName'=>'time','attributes'=>['datetime'=>'2026-10-27T12:00:00Z'],'content'=>'12:00']
])]);
operationCheck($timeCreated->data->pages[0]->frames[0]->component->components[0]->attributes->datetime === '2026-10-27T12:00:00Z', 'Typed create supports the export-safe time datetime attribute');
operationRejects(fn()=>ProjectOperations::apply($fixture,[operationObject(['operation'=>'update_attributes','componentId'=>'iwnjm','attributes'=>['id'=>'changed']])]),422,'Typed attribute update cannot replace stable IDs');
operationRejects(fn()=>ProjectOperations::apply($fixture,[operationObject(['operation'=>'update_attributes','componentId'=>'iwnjm','attributes'=>['onclick'=>'alert(1)']])]),422,'Typed attribute update rejects executable attributes');
operationRejects(fn()=>ProjectOperations::apply($fixture,[operationObject(['operation'=>'update_attributes','componentId'=>'iwnjm','attributes'=>['href'=>'javascript:alert(1)']])]),422,'Typed attribute update validates URLs through the document policy');
operationRejects(fn()=>ProjectOperations::apply($formCreated,[operationObject(['operation'=>'update_attributes','componentId'=>$formInputId,'attributes'=>['required'=>'true']])]),422,'Typed boolean attributes reject string values');
$textReplaced = ProjectOperations::apply($fixture, [operationObject(['operation'=>'replace_text','componentId'=>'ii57','content'=>'Сохранённый текст'])]);
$selectedText = operationNode($textReplaced, 'ii57');
operationCheck(!isset($selectedText->content) && ($selectedText->components[0]->content ?? null) === 'Сохранённый текст', 'Focused text replacement replaces nested GrapesJS text without changing the stable element');
operationRejects(fn()=>ProjectOperations::apply($fixture,[operationObject(['operation'=>'replace_text','componentId'=>'ii57','content'=>'<script>bad</script>'])]),422,'Focused text replacement rejects markup');
$unstableTarget = ProjectOperations::componentForAiEdit($fixture, 'home', [0,2,0], '');
$temporaryId = $unstableTarget['componentId'];
operationCheck($unstableTarget['needsId'] === true && preg_match('/^cmp-[a-f0-9]{24}$/', $temporaryId) === 1, 'An ID-less selected node receives a proposed stable ID in a draft only');
operationCheck(ProjectOperations::nodeIdentifier($fixture->data->pages[0]->frames[0]->component->components[0]->components[2]->components[0]) === null && ProjectOperations::nodeIdentifier($unstableTarget['component']) === $temporaryId, 'Proposed ID is not written into the original project document');
$unstableText = ProjectOperations::apply($unstableTarget['document'], [operationObject(['operation'=>'replace_text','componentId'=>$temporaryId,'content'=>'Новый текст'])]);
operationCheck(($unstableText->data->pages[0]->frames[0]->component->components[0]->components[2]->components[0]->content ?? null) === 'Новый текст', 'Draft ID resolves the exact selected path for an AI edit');

$styleUpdated = ProjectOperations::apply($fixture, [operationObject(['operation'=>'update_styles','componentId'=>'ieg3','styles'=>['background-color'=>'#122334','border-radius'=>'12px']])]);
operationCheck($styleUpdated->data->pages[0]->frames[0]->component->components[0]->style->{'background-color'} === '#122334', 'Style updates preserve the existing component tree');
$stylePartiallyCleared = ProjectOperations::apply($styleUpdated, [operationObject(['operation'=>'update_styles','componentId'=>'ieg3','styles'=>['border-radius'=>null]])]);
operationCheck(!isset(operationNode($stylePartiallyCleared,'ieg3')->style->{'border-radius'}) && operationNode($stylePartiallyCleared,'ieg3')->style->{'background-color'} === '#122334', 'Typed style updates remove one property with null and preserve other styles');
$styleOnly = ProjectOperations::apply(operationObject(Document::empty()), [operationObject(['operation'=>'create_component','parentId'=>'page:home','component'=>['tagName'=>'div','style'=>['color'=>'#123456']]])]);
$styleOnlyId = $styleOnly->data->pages[0]->frames[0]->component->components[0]->attributes->id;
$styleFullyCleared = ProjectOperations::apply($styleOnly, [operationObject(['operation'=>'update_styles','componentId'=>$styleOnlyId,'styles'=>['color'=>null]])]);
operationCheck(!isset(operationNode($styleFullyCleared,$styleOnlyId)->style), 'Clearing the last typed style removes the empty style object');
$layoutUpdated = ProjectOperations::apply($fixture, [operationObject(['operation'=>'update_layout','componentId'=>'ieg3','layout'=>['display'=>'grid','gridTemplateColumns'=>'repeat(3, minmax(0, 1fr))','gap'=>'20px']])]);
operationCheck($layoutUpdated->data->pages[0]->frames[0]->component->components[0]->style->display === 'grid', 'Layout updates map typed fields to validated CSS properties');
$layoutPartiallyReset = ProjectOperations::apply($layoutUpdated, [operationObject(['operation'=>'update_layout','componentId'=>'ieg3','layout'=>['display'=>null,'gap'=>null]])]);
$resetLayoutNode = operationNode($layoutPartiallyReset,'ieg3');
operationCheck(!isset($resetLayoutNode->style->display) && !isset($resetLayoutNode->style->gap) && $resetLayoutNode->style->{'grid-template-columns'} === 'repeat(3, minmax(0, 1fr))', 'Typed layout null resets selected fields while preserving unrelated layout settings');
$classesUpdated = ProjectOperations::apply($fixture, [operationObject(['operation'=>'update_classes','componentId'=>'ieg3','classes'=>['hero','content-wrap']])]);
$classTarget = operationNode($classesUpdated, 'ieg3');
operationCheck(($classTarget->classes[0]->name ?? null) === 'hero' && ($classTarget->classes[1]->name ?? null) === 'content-wrap' && $classTarget->attributes->id === 'ieg3', 'Typed CSS class updates use GrapesJS class-tag data and preserve stable identity');
$classesCleared = ProjectOperations::apply($classesUpdated, [operationObject(['operation'=>'update_classes','componentId'=>'ieg3','classes'=>[]])]);
operationCheck($classesCleared->data->pages[0]->frames[0]->component->components[0]->classes === [], 'Typed CSS class updates can clear all classes');
operationRejects(fn()=>ProjectOperations::apply($fixture,[operationObject(['operation'=>'update_classes','componentId'=>'ieg3','classes'=>['hero bad']])]),422,'Typed CSS class updates reject whitespace and selector injection');
operationRejects(fn()=>ProjectOperations::apply($fixture,[operationObject(['operation'=>'update_classes','componentId'=>'ieg3','classes'=>['hero','hero']])]),422,'Typed CSS class updates reject duplicate names');
operationRejects(fn()=>ProjectOperations::apply($fixture,[operationObject(['operation'=>'update_classes','componentId'=>'ieg3','classes'=>array_fill(0,33,'safe')])]),422,'Typed CSS class updates enforce a bounded class count');
$unsafeClassDocument = json_decode(json_encode($fixture, JSON_THROW_ON_ERROR), false, 64, JSON_THROW_ON_ERROR);
$unsafeClassDocument->data->pages[0]->frames[0]->component->components[0]->classes = ['bad class'];
operationRejects(fn()=>Document::validate($unsafeClassDocument),422,'Document validation rejects unsafe class names outside typed operations');

$created = ProjectOperations::apply(operationObject(Document::empty()), [operationObject([
    'operation'=>'create_component','parentId'=>'page:home','index'=>0,
    'component'=>['tagName'=>'section','name'=>'Generated section','components'=>[
        ['type'=>'text','tagName'=>'h2','content'=>'Generated heading'],
        ['type'=>'link','content'=>'Read more','attributes'=>['href'=>'https://example.test']]
    ]]
])]);
$createdSection = $created->data->pages[0]->frames[0]->component->components[0];
$createdIds = [$createdSection->attributes->id,$createdSection->components[0]->attributes->id,$createdSection->components[1]->attributes->id];
operationCheck(count(array_unique($createdIds)) === 3 && count(array_filter($createdIds, static fn($id)=>str_starts_with($id,'cmp-'))) === 3, 'Create assigns unique stable IDs throughout a new component subtree');

$duplicateFixture = json_decode(json_encode($fixture, JSON_THROW_ON_ERROR), false, 64, JSON_THROW_ON_ERROR);
$duplicateButton = operationNode($duplicateFixture, 'iwnjm');
$duplicateButton->attributes->href = '#ij4p';
$duplicateButton->attributes->{'aria-labelledby'} = 'ij4p';
$duplicateFixture->data->styles[] = (object)['selectors'=>['#ij4p'], 'style'=>(object)['color'=>'#123456']];
$duplicated = ProjectOperations::apply($duplicateFixture, [operationObject(['operation'=>'duplicate_component','componentId'=>'ieg3'])]);
$duplicatedOriginal = operationNode($duplicated, 'ieg3');
$duplicatedRoot = $duplicated->data->pages[0]->frames[0]->component->components[1];
$duplicatedHeading = $duplicatedRoot->components[1];
$duplicatedButton = $duplicatedRoot->components[3];
operationCheck($duplicatedRoot->attributes->id !== 'ieg3' && $duplicatedHeading->attributes->id !== 'ij4p' && $duplicatedButton->attributes->id !== 'iwnjm', 'Duplicate assigns fresh stable IDs to the complete component subtree');
operationCheck($duplicatedOriginal->components[1]->attributes->id === 'ij4p' && $duplicatedOriginal->components[3]->attributes->href === '#ij4p', 'Duplicate leaves the source subtree unchanged');
operationCheck($duplicatedButton->attributes->href === '#' . $duplicatedHeading->attributes->id && $duplicatedButton->attributes->{'aria-labelledby'} === $duplicatedHeading->attributes->id, 'Duplicate remaps internal fragment and IDREF references');
$duplicateHeadingSelector = '#' . $duplicatedHeading->attributes->id;
$duplicateHeadingRule = array_values(array_filter($duplicated->data->styles, static fn($rule) => ($rule->selectors[0] ?? null) === $duplicateHeadingSelector && ($rule->style->color ?? null) === '#123456'));
operationCheck(count($duplicateHeadingRule) === 1, 'Duplicate clones GrapesJS CSS rules and remaps string selectors');
operationRejects(fn()=>ProjectOperations::apply($fixture,[operationObject(['operation'=>'duplicate_component','componentId'=>'page:home'])]),404,'Page roots cannot be duplicated as regular components');

$moved = ProjectOperations::apply($fixture, [
    operationObject(['operation'=>'create_component','parentId'=>'page:home','component'=>['tagName'=>'section','name'=>'Target','attributes'=>['id'=>'target-section']]]),
    operationObject(['operation'=>'move_component','componentId'=>'ipvt','parentId'=>'target-section','index'=>0])
]);
operationCheck($moved->data->pages[0]->frames[0]->component->components[1]->components[0]->attributes->id === 'ipvt', 'Move preserves the component ID under its new parent');
$reordered = ProjectOperations::apply($fixture, [operationObject(['operation'=>'reorder_component','componentId'=>'iwnjm','index'=>0])]);
operationCheck($reordered->data->pages[0]->frames[0]->component->components[0]->components[0]->attributes->id === 'iwnjm', 'Reorder changes position without replacing the component');
$deleted = ProjectOperations::apply($fixture, [operationObject(['operation'=>'delete_component','componentId'=>'ij4p','confirm'=>true])]);
operationCheck(operationNode($deleted, 'ij4p') === null, 'Delete requires explicit confirmation and removes only the targeted component');

operationRejects(fn()=>ProjectOperations::apply($fixture,[operationObject(['operation'=>'delete_component','componentId'=>'ij4p'])]),422,'Delete without explicit confirmation is rejected');
operationRejects(fn()=>ProjectOperations::apply($fixture,[operationObject(['operation'=>'update_content','componentId'=>'ieg3','content'=>'wrong target'])]),422,'Text updates reject non-text containers');
operationRejects(fn()=>ProjectOperations::apply($fixture,[operationObject(['operation'=>'update_styles','componentId'=>'ieg3','styles'=>['background-image'=>'https://example.test/a.png']])]),422,'Style updates reject properties outside the allowlist');
operationRejects(fn()=>ProjectOperations::apply($fixture,[operationObject(['operation'=>'update_styles','componentId'=>'ieg3','styles'=>['background-color'=>'url(https://example.test)']])]),422,'Project security validation rejects unsafe CSS values');
operationRejects(fn()=>ProjectOperations::apply($fixture,[operationObject(['operation'=>'update_styles','componentId'=>'ieg3','styles'=>['color'=>'red; position:fixed']])]),422,'Style values cannot inject additional CSS declarations');
operationRejects(fn()=>ProjectOperations::apply($fixture,[operationObject(['operation'=>'move_component','componentId'=>'ieg3','parentId'=>'ij4p'])]),422,'Moves reject cycles into descendants');
operationRejects(fn()=>ProjectOperations::apply($fixture,[operationObject(['operation'=>'delete_component','componentId'=>'page:home','confirm'=>true])]),404,'Page root is only a container and cannot be deleted as a component');
operationRejects(fn()=>ProjectOperations::apply($fixture,[operationObject(['operation'=>'create_component','parentId'=>'page:home','component'=>['tagName'=>'section','attributes'=>['id'=>'ieg3']]])]),422,'Create rejects stable IDs already used in the document');
operationRejects(fn()=>ProjectOperations::apply($fixture,[operationObject(['operation'=>'create_component','parentId'=>'page:home','component'=>['tagName'=>'section','script'=>'alert(1)']])]),422,'Create rejects unknown component fields');
operationRejects(fn()=>ProjectOperations::apply($fixture,[operationObject(['operation'=>'no_such_operation'])]),422,'Unknown operation types are rejected');
operationRejects(fn()=>ProjectOperations::apply($fixture,[operationObject(['operation'=>'update_content','componentId'=>'missing','content'=>'text'])]),404,'Unknown component IDs are rejected');
operationRejects(fn()=>ProjectOperations::apply($fixture,array_fill(0,21,operationObject(['operation'=>'update_content','componentId'=>'ij4p','content'=>'text']))),422,'Operation batch length is bounded');

$failedBatch = [operationObject(['operation'=>'update_content','componentId'=>'ij4p','content'=>'must not persist']),operationObject(['operation'=>'update_styles','componentId'=>'ieg3','styles'=>['position'=>'fixed']])];
operationRejects(fn()=>ProjectOperations::apply($fixture,$failedBatch),422,'Invalid operation aborts its entire batch');
operationCheck(operationNode($fixture,'ij4p')->content === operationNode($original,'ij4p')->content, 'Rejected operation batches leave the source document unchanged');
