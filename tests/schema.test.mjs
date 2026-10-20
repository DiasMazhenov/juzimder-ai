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
  'javascript URI',
  'unsafe rich text',
  'CSS URL',
  'CSS escaped URL token',
  'CSS comment-obfuscated URL token',
  'duplicate page id'
]);

test('Project Data v1 JSON Schema matches the fixture corpus', () => {
  const validate = new Ajv2020({ allErrors: true, strict: false }).compile(schema);
  assert.equal(validate(realProject), true, `GrapesJS fixture should validate: ${JSON.stringify(validate.errors)}`);

  for (const fixture of invalidFixtures) {
    const expected = schemaOnlyAccepted.has(fixture.case);
    assert.equal(validate(fixture.document), expected, `${fixture.case}: ${JSON.stringify(validate.errors)}`);
  }
});
