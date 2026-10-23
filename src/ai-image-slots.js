// Remove only empty div wrappers devoted exclusively to an unassigned image.
// Keep text, siblings, anchors and semantic containers intact.
export function removeUnassignedImageSlot(slot) {
  let removable = slot;
  for (let parent = removable.parentElement; parent; parent = removable.parentElement) {
    if (parent.tagName !== 'DIV' || parent.id || parent.hasAttribute('role') || parent.hasAttribute('aria-label')) break;
    if (parent.children.length !== 1 || parent.firstElementChild !== removable || parent.textContent.trim() !== '') break;
    removable = parent;
  }
  removable.remove();
}
