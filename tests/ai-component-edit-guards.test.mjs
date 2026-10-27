import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { isAIComponentEditProposalCurrent, isAIComponentEditSnapshotCurrent } from '../src/ai-component-edit-guards.js';

const component = {};
const page = { get: key => key === 'id' ? 'home' : undefined };
const makeEditor = () => {
  const editor = {
    selected: component,
    selectedPage: page,
    data: { content: 'unchanged' },
    getSelected() { return this.selected; },
    getProjectData() { return this.data; }
  };
  editor.Pages = { getSelected: () => editor.selectedPage };
  return editor;
};

test('AI component response is accepted only for the unchanged project snapshot and target', () => {
  const editor = makeEditor();
  const input = { editor, component, pageId: 'home', baseVersion: 8, currentVersion: 8, snapshot: JSON.stringify(editor.data), dirty: false, conflicted: false };
  assert.equal(isAIComponentEditSnapshotCurrent(input), true);
  for (const change of [
    { dirty: true },
    { conflicted: true },
    { currentVersion: 9 },
    { snapshot: '{}' }
  ]) assert.equal(isAIComponentEditSnapshotCurrent({ ...input, ...change }), false);
  editor.selected = {};
  assert.equal(isAIComponentEditSnapshotCurrent(input), false, 'a changed component selection invalidates the response');
  editor.selected = component;
  editor.selectedPage = { get: key => key === 'id' ? 'about' : undefined };
  assert.equal(isAIComponentEditSnapshotCurrent(input), false, 'a changed page invalidates the response');
  editor.selectedPage = page;
  editor.data.content = 'changed while provider was responding';
  assert.equal(isAIComponentEditSnapshotCurrent(input), false, 'local Project Data changes invalidate the response');
});

test('AI proposal is revalidated after confirmation before applying', () => {
  const editor = makeEditor();
  const proposal = { componentModel: component, requestedComponentId: 'cmp-target', pageId: 'home', baseVersion: 8, editorSnapshot: JSON.stringify(editor.data) };
  const input = { proposal, currentProposal: proposal, editor, component, componentId: 'cmp-target', pageId: 'home', currentVersion: 8, conflicted: false };
  assert.equal(isAIComponentEditProposalCurrent(input), true);
  for (const change of [
    { component: {} },
    { componentId: 'cmp-other' },
    { pageId: 'about' },
    { currentVersion: 9 },
    { conflicted: true },
    { currentProposal: null }
  ]) assert.equal(isAIComponentEditProposalCurrent({ ...input, ...change }), false);
  editor.data.content = 'changed while the confirmation dialog was open';
  assert.equal(isAIComponentEditProposalCurrent(input), false, 'Project Data changes invalidate the proposal after confirmation');

  const source = readFileSync(new URL('../src/main.js', import.meta.url), 'utf8');
  assert.match(source, /isAIComponentEditSnapshotCurrent\(/, 'provider response is guarded by the request snapshot');
  assert.equal((source.match(/isAIComponentEditProposalCurrent\(/g) || []).length, 2, 'proposal is checked before and after confirmation');
});
