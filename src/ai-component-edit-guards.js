export function isAIComponentEditSnapshotCurrent({editor,component,pageId,baseVersion,currentVersion,snapshot,dirty,conflicted}){
  return !dirty&&!conflicted&&currentVersion===baseVersion&&editor.getSelected()===component&&editor.Pages.getSelected()?.get('id')===pageId&&JSON.stringify(editor.getProjectData())===snapshot;
}

export function isAIComponentEditProposalCurrent({proposal,currentProposal,editor,component,componentId,pageId,currentVersion,conflicted}){
  return !!proposal&&currentProposal===proposal&&!conflicted&&currentVersion===proposal.baseVersion&&component===proposal.componentModel&&componentId===proposal.requestedComponentId&&pageId===proposal.pageId&&JSON.stringify(editor.getProjectData())===proposal.editorSnapshot;
}
