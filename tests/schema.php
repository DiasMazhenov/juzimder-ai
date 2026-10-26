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
schemaCheck($componentProperties->classes->type === 'array' && $componentProperties->classes->maxItems === 32 && $componentProperties->classes->items->anyOf[1]->required === ['name'], 'Schema describes bounded GrapesJS CSS class tags');
schemaCheck(isset($componentProperties->attributes->properties->src, $componentProperties->attributes->properties->href), 'Schema explicitly describes href and scoped managed-image references');
schemaCheck($componentProperties->attributes->properties->required->type === 'boolean' && $componentProperties->attributes->properties->disabled->type === 'boolean' && $componentProperties->attributes->properties->checked->type === 'boolean', 'Schema types native boolean form attributes explicitly');
schemaCheck(isset($componentProperties->attributes->properties->name, $componentProperties->attributes->properties->type, $componentProperties->attributes->properties->placeholder, $componentProperties->attributes->properties->accept, $componentProperties->attributes->properties->autocomplete), 'Schema describes common safe form field attributes');
schemaCheck(isset($componentProperties->attributes->properties->{'aria-controls'}, $componentProperties->attributes->properties->{'aria-selected'}, $componentProperties->attributes->properties->{'aria-expanded'}, $componentProperties->attributes->properties->{'aria-pressed'}, $componentProperties->attributes->properties->datetime, $componentProperties->attributes->properties->open), 'Schema describes all additional static-export allowlisted interaction attributes');
schemaCheck(isset($componentProperties->components, $componentProperties->head, $componentProperties->docEl), 'Schema covers recursive Core component fields');
schemaCheck(in_array('figure', $componentProperties->tagName->enum, true) && in_array('figcaption', $componentProperties->tagName->enum, true), 'Schema allows semantic image captions in the main editor');
schemaCheck(!isset($schema->additionalProperties, $data->additionalProperties, $page->additionalProperties, $component->additionalProperties), 'Schema preserves forward-compatible Core metadata');
