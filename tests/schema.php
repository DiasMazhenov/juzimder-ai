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
schemaCheck(isset($componentProperties->attributes->properties->id, $componentProperties->attributes->properties->class, $componentProperties->attributes->properties->alt, $componentProperties->attributes->properties->title, $componentProperties->attributes->properties->rel, $componentProperties->attributes->properties->target, $componentProperties->attributes->properties->role), 'Schema describes the renderer-safe common HTML attributes');
schemaCheck($componentProperties->attributes->properties->id->pattern === '^[A-Za-z][A-Za-z0-9_:-]{0,127}$' && $componentProperties->attributes->properties->target->enum === ['_blank','_self'] && $componentProperties->attributes->properties->rel->maxLength === 2048, 'Schema constrains IDs, link targets and rel tokens to renderer behavior');
schemaCheck($componentProperties->attributes->properties->{'aria-label'}->maxLength === 256 && $componentProperties->attributes->properties->{'aria-hidden'}->enum === ['true','false'] && $componentProperties->attributes->properties->{'aria-controls'}->maxLength === 256, 'Schema bounds and types ARIA values accepted by static export');
schemaCheck($componentProperties->attributes->properties->required->type === 'boolean' && $componentProperties->attributes->properties->disabled->type === 'boolean' && $componentProperties->attributes->properties->checked->type === 'boolean', 'Schema types native boolean form attributes explicitly');
schemaCheck($componentProperties->attributes->properties->hidden->type === 'boolean', 'Schema types the native hidden visibility attribute explicitly');
schemaCheck(isset($componentProperties->attributes->properties->name, $componentProperties->attributes->properties->type, $componentProperties->attributes->properties->placeholder, $componentProperties->attributes->properties->accept, $componentProperties->attributes->properties->autocomplete), 'Schema describes common safe form field attributes');
schemaCheck(isset($componentProperties->attributes->properties->{'aria-controls'}, $componentProperties->attributes->properties->{'aria-selected'}, $componentProperties->attributes->properties->{'aria-expanded'}, $componentProperties->attributes->properties->{'aria-pressed'}, $componentProperties->attributes->properties->datetime, $componentProperties->attributes->properties->open), 'Schema describes all additional static-export allowlisted interaction attributes');
schemaCheck(($componentProperties->attributes->patternProperties->{'^data-(?!jz-)[a-z][a-z0-9]*(?:-[a-z0-9]+)*$'}->type ?? null) === 'string' && $componentProperties->attributes->patternProperties->{'^data-(?!jz-)[a-z][a-z0-9]*(?:-[a-z0-9]+)*$'}->maxLength === 2048, 'Schema documents bounded lowercase data-* attributes and reserves renderer metadata');
schemaCheck(isset($componentProperties->components, $componentProperties->head, $componentProperties->docEl), 'Schema covers recursive Core component fields');
schemaCheck(in_array('figure', $componentProperties->tagName->enum, true) && in_array('figcaption', $componentProperties->tagName->enum, true), 'Schema allows semantic image captions in the main editor');
schemaCheck(!isset($schema->additionalProperties, $data->additionalProperties, $page->additionalProperties, $component->additionalProperties), 'Schema preserves forward-compatible Core metadata');
