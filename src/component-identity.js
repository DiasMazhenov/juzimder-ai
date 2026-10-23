// AI preconditions use the ID serialized in Project Data, not GrapesJS renderer IDs.
export function persistedComponentId(component) {
  const id = component?.get?.('attributes')?.id;
  return typeof id === 'string' ? id : '';
}
