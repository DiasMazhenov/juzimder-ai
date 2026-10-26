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
$layoutUpdated = ProjectOperations::apply($fixture, [operationObject(['operation'=>'update_layout','componentId'=>'ieg3','layout'=>['display'=>'grid','gridTemplateColumns'=>'repeat(3, minmax(0, 1fr))','gap'=>'20px']])]);
operationCheck($layoutUpdated->data->pages[0]->frames[0]->component->components[0]->style->display === 'grid', 'Layout updates map typed fields to validated CSS properties');

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
