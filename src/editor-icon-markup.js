export function normalizeEditorIconMarkup(markup) {
  const match = /<svg\b[^>]*>/i.exec(markup);
  if (!match || /\bclass\s*=/.test(match[0])) return markup;

  const svgTag = match[0].replace(
    /^<svg\b/i,
    '<svg class="tabler-editor-icon" aria-hidden="true" focusable="false"',
  );
  return `${markup.slice(0, match.index)}${svgTag}${markup.slice(match.index + match[0].length)}`;
}
