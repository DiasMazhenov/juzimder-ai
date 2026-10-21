<?php
declare(strict_types=1);
require __DIR__.'/../backend/Domain.php';
use Juzimder\Document;

function schemaCheck(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
    echo "PASS $message\n";
}

$schema = json_decode(file_get_contents(__DIR__.'/../schemas/project.schema.json'), false, 64, JSON_THROW_ON_ERROR);
$data = $schema->properties->data;
$page = $schema->{'$defs'}->page;
$frame = $schema->{'$defs'}->frame;
$component = $schema->{'$defs'}->component;
$componentProperties = $component->properties;

schemaCheck($schema->properties->schemaVersion->const === 1 && $schema->properties->engine->const === 'grapesjs', 'Schema envelope is versioned');
schemaCheck($data->properties->pages->minItems === 1 && $data->properties->pages->maxItems === Document::MAX_PAGES, 'Schema page limit matches runtime policy');
schemaCheck($page->properties->id->minLength === 1 && $page->properties->id->maxLength === Document::MAX_PAGE_ID_LENGTH, 'Schema page id bounds match runtime policy');
schemaCheck($page->properties->frames->minItems === Document::FRAMES_PER_PAGE && $page->properties->frames->maxItems === Document::FRAMES_PER_PAGE, 'Schema frame count matches runtime policy');
schemaCheck($componentProperties->type->enum === Document::COMPONENT_TYPES, 'Schema component types match runtime policy');
schemaCheck($componentProperties->tagName->enum === Document::TAG_NAMES, 'Schema tag allowlist matches runtime policy');
schemaCheck($componentProperties->attributes->type === 'object' && $componentProperties->style->type === 'object' && $componentProperties->content->type === 'string', 'Schema component field types are explicit');
schemaCheck(isset($componentProperties->attributes->properties->src, $componentProperties->attributes->properties->href), 'Schema explicitly describes href and scoped managed-image references');
schemaCheck(isset($componentProperties->components, $componentProperties->head, $componentProperties->docEl), 'Schema covers recursive Core component fields');
schemaCheck(!isset($schema->additionalProperties, $data->additionalProperties, $page->additionalProperties, $component->additionalProperties), 'Schema preserves forward-compatible Core metadata');
