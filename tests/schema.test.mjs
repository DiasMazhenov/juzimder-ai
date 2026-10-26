import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import Ajv2020 from 'ajv/dist/2020.js';

const schema = JSON.parse(await readFile(new URL('../schemas/project.schema.json', import.meta.url), 'utf8'));
const realProject = JSON.parse(await readFile(new URL('./grapesjs-0.23.8.fixture.json', import.meta.url), 'utf8'));
const invalidFixtures = JSON.parse(await readFile(new URL('./fixtures/invalid-documents.json', import.meta.url), 'utf8'));
const schemaOnlyAccepted = new Set([
  'script field',
  'event handler',
  'unsafe rich text',
  'CSS URL',
  'CSS escaped URL token',
  'CSS comment-obfuscated URL token',
  'duplicate page id'
]);

test('Project Data v1 JSON Schema matches the fixture corpus', () => {
  const validate = new Ajv2020({ allErrors: true, strict: false }).compile(schema);
  assert.equal(validate(realProject), true, `GrapesJS fixture should validate: ${JSON.stringify(validate.errors)}`);

  const withClasses = structuredClone(realProject);
  withClasses.data.pages[0].frames[0].component.components[0].classes = [{ name: 'hero' }, 'content-wrap'];
  assert.equal(validate(withClasses), true, `GrapesJS CSS class tags should validate: ${JSON.stringify(validate.errors)}`);
  withClasses.data.pages[0].frames[0].component.components[0].classes = ['unsafe class'];
  assert.equal(validate(withClasses), false, 'Project Data schema rejects unsafe CSS class tokens');

  const managedImage = structuredClone(realProject);
  managedImage.data.pages[0].frames[0].component.components[0].attributes = {
    src: `/api/projects/${'a'.repeat(24)}/assets/${'b'.repeat(24)}/file`,
    alt: 'Managed image',
  };
  assert.equal(validate(managedImage), true, `Scoped managed image reference should validate: ${JSON.stringify(validate.errors)}`);
  managedImage.data.pages[0].frames[0].component.components[0].attributes.src = 'data:image/svg+xml,<svg/>';
  assert.equal(validate(managedImage), false, 'Project Data schema rejects arbitrary data URLs');

  for (const fixture of invalidFixtures) {
    const expected = schemaOnlyAccepted.has(fixture.case);
    assert.equal(validate(fixture.document), expected, `${fixture.case}: ${JSON.stringify(validate.errors)}`);
  }
});
