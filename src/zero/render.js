import {
  breakpointEntries,
  breakpointMediaMax,
  resolve,
  geometryCSS,
  appearanceCSS,
  scaledGridLength,
} from "./model/model.js";
export const escapeHTML = (s) =>
  String(s ?? "").replace(
    /[&<>"']/g,
    (c) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[
        c
      ],
  );
export function elementHTML(el, children = "") {
  let content = escapeHTML(el.content || "");
  if (el.runs)
    content = el.runs
      .map((r) => {
        let s = escapeHTML(r.text);
        if (r.bold) s = `<strong>${s}</strong>`;
        if (r.italic) s = `<em>${s}</em>`;
        if (r.underline) s = `<u>${s}</u>`;
        return s;
      })
      .join("");
  if (el.type === "image")
    return `<img src="${escapeHTML(el.src)}" alt="${escapeHTML(el.alt || "")}" draggable="false" style="width:100%;height:100%;object-fit:inherit;object-position:inherit;border-radius:inherit">`;
  if (el.type === "group") return children;
  return content;
}
export function renderZero(data, blockId, activeMode = null) {
  const scope = "jz-zero-" + String(blockId).replace(/[^a-zA-Z0-9_-]/g, "");
  const modes = breakpointEntries(data).map(([id]) => id),
    sourceModes = [...modes].reverse().filter((mode) => mode !== "desktop");
  let css =
    `.${scope}{position:relative!important;display:block!important;width:100%;box-sizing:border-box;isolation:isolate}.` +
    scope +
    " .jz-zero-element{margin:0;padding:0;white-space:pre-wrap;min-width:0;min-height:0;max-width:none;font-family:inherit;overflow-wrap:anywhere}." +
    scope +
    " a.jz-zero-element{padding:12px 16px;text-align:center;text-decoration:none}." +
    scope +
    " [data-zero-image]{padding:0}";
  const wrap = (mode, rule) =>
    activeMode || mode === "desktop"
      ? rule
      : `@media(max-width:${breakpointMediaMax(data, mode)}px){${rule}}`;
  for (const mode of activeMode ? [activeMode] : modes) {
    const b = data.artboards[mode];
    const autoScale = !activeMode && b.scaleGrid === "autoscale";
    css += wrap(
      mode,
      `.${scope}{height:${autoScale ? scaledGridLength(b.height, b, true) : `${b.height}px`};background:${b.background};overflow:${b.overflow}}`,
    );
  }
  const walk = (list, nested = false, inheritedGrid = {}) =>
    list
      .map((el, index) => {
        for (const mode of activeMode ? [activeMode] : modes) {
          const e = resolve(el, mode),
            s = e.style,
            board = data.artboards[mode],
            gridAttached = nested
              ? inheritedGrid[mode]
              : e.geometry.container === "grid",
            autoScale =
              !activeMode &&
              board.scaleGrid === "autoscale" &&
              gridAttached,
            sel = `.${scope} [data-ze="${el.id}"]`,
            buttonPadding =
              autoScale && el.type === "button"
                ? `padding:${scaledGridLength(12, board, true)} ${scaledGridLength(16, board, true)};`
                : "";
          css += wrap(
            mode,
            `${sel}{${geometryCSS(e.geometry, board, nested, autoScale)}${appearanceCSS(s, board, autoScale)}${buttonPadding}z-index:${index + 1};display:${e.visible ? "block" : "none"};${el.type === "ellipse" ? "border-radius:50%;" : ""}${el.type === "image" && !s.objectFit ? "object-fit:cover;" : ""}}${s.hoverFill || s.hoverColor ? `${sel}:hover{${s.hoverFill ? `background-color:${s.hoverFill};` : ""}${s.hoverColor ? `color:${s.hoverColor};` : ""}}` : ""}`,
          );
        }
        const childGrid = Object.fromEntries(
          modes.map((mode) => [
            mode,
            nested
              ? inheritedGrid[mode]
              : resolve(el, mode).geometry.container === "grid",
          ]),
        );
        const e = resolve(el, activeMode || "desktop"),
          tag =
            el.type === "text"
              ? e.tag || "p"
              : el.type === "button" || (el.type === "image" && e.href)
                ? "a"
                : "div";
        let body = elementHTML(
          e,
          el.children ? walk(el.children, true, childGrid) : "",
        );
        if (el.type === "image" && !activeMode) {
          const sources = sourceModes
            .map(
              (m) =>
                `<source media="(max-width:${breakpointMediaMax(data, m)}px)" srcset="${escapeHTML(resolve(el, m).src.replace(/,/g, "%2C"))}">`,
            )
            .join("");
          body = `<picture style="display:block;width:100%;height:100%;object-fit:inherit;object-position:inherit;border-radius:inherit">${sources}${body}</picture>`;
        }
        return `<${tag} class="jz-zero-element" data-ze="${escapeHTML(el.id)}"${el.type === "image" ? ' data-zero-image="true"' : ""}${tag === "a" ? ` href="${escapeHTML(e.href || "#")}" target="${escapeHTML(e.target || "_self")}" rel="noopener noreferrer"` : ""}>${body}</${tag}>`;
      })
      .join("");
  const html = walk(data.elements);
  return `<section class="jz-zero-block ${scope}" data-zero-id="${escapeHTML(blockId)}"><style>${css}</style>${html}</section>`;
}
