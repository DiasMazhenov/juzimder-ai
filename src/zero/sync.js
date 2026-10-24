export function serializedZero(document, pageId, blockId) {
  const page = document.data.pages.find((p) => p.id === pageId);
  let found;
  const walk = (node) => {
    if (node.type === "zero-block" && node.attributes?.id === blockId)
      found = node;
    for (const c of node.components || []) walk(c);
  };
  if (page) walk(page.frames[0].component);
  return found;
}
export function sameOutsideZero(previous, next, pageId, blockId) {
  const a = structuredClone(previous),
    b = structuredClone(next),
    az = serializedZero(a, pageId, blockId),
    bz = serializedZero(b, pageId, blockId);
  if (!az || !bz) return false;
  az.zeroData = null;
  bz.zeroData = null;
  return JSON.stringify(a) === JSON.stringify(b);
}
