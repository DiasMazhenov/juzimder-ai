import {
  breakpointEntries,
  breakpointMediaMax,
  resolve,
  geometryCSS,
  appearanceCSS,
  scaledGridLength,
} from "./model/model.js";
import { sandboxFrame } from "./sandbox.js";
import iconCatalogue from "../../resources/tabler-editor/catalogue.json" with { type: "json" };
import { VECTOR_ICON_NAMES } from "./model/vector-icons.js";
export const escapeHTML = (s) =>
  String(s ?? "").replace(
    /[&<>"']/g,
    (c) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[
        c
      ],
  );
function hasTooltip(elements) {
  return elements.some(
    (element) =>
      element.type === "tooltip" ||
      (element.children?.length && hasTooltip(element.children)),
  );
}
function hasVector(elements) {
  return elements.some(
    (element) =>
      element.type === "vector" ||
      (element.children?.length && hasVector(element.children)),
  );
}
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
  if (el.type === "tooltip") {
    const id = `jz-tooltip-${escapeHTML(el.id)}`;
    return `<button class="jz-zero-tooltip-trigger" type="button" aria-label="Показать подсказку" aria-describedby="${id}">?</button><span class="jz-zero-tooltip-popover" id="${id}" role="tooltip">${content}</span>`;
  }
  if (el.type === "vector")
    return VECTOR_ICON_NAMES.includes(el.icon)
      ? iconCatalogue[el.icon]
      : iconCatalogue["arrow-right"];
  if (el.type === "group") return children;
  return content;
}
export function renderZero(
  data,
  blockId,
  activeMode = null,
  { runScripts = false } = {},
) {
  const scope = "jz-zero-" + String(blockId).replace(/[^a-zA-Z0-9_-]/g, "");
  const modes = breakpointEntries(data).map(([id]) => id),
    sourceModes = [...modes].reverse().filter((mode) => mode !== "desktop");
  let css =
    `.${scope}{position:relative!important;display:block!important;width:${activeMode ? `${data.artboards[activeMode].width}px` : "100%"}!important;box-sizing:border-box;isolation:isolate}.` +
    scope +
    " .jz-zero-element{margin:0;padding:0;white-space:pre-wrap;min-width:0;min-height:0;max-width:none;font-family:inherit;overflow-wrap:anywhere}." +
    scope +
    " a.jz-zero-element{padding:12px 16px;text-align:center;text-decoration:none}." +
    scope +
    " a.jz-zero-button{align-items:center;justify-content:center;text-align:center}." +
    scope +
    " [data-zero-image]{padding:0}." +
    scope +
    " .jz-zero-code{border:0;padding:0;overflow:hidden;pointer-events:none}." +
    scope +
    " .jz-zero-code[data-code-running=\"true\"]{pointer-events:auto}";
  if (hasTooltip(data.elements))
    css += `.${scope} .jz-zero-tooltip{overflow:visible!important;white-space:normal}.${scope} .jz-zero-tooltip-trigger{display:flex;align-items:center;justify-content:center;box-sizing:border-box;width:28px;height:28px;padding:0;border:1px solid currentColor;border-radius:50%;background:transparent;color:inherit;font:inherit;line-height:1;cursor:help}.${scope} .jz-zero-tooltip-popover{position:absolute;left:50%;top:calc(100% + 8px);z-index:10000;width:max-content;max-width:280px;box-sizing:border-box;padding:8px 12px;border:0;border-radius:8px;background:#183e31;color:#fff;font:14px/1.4 system-ui,sans-serif;text-align:left;white-space:pre-wrap;overflow-wrap:anywhere;box-shadow:0 8px 24px rgba(0,0,0,.2);opacity:0;visibility:hidden;pointer-events:none;transform:translateX(-50%);transition:opacity .12s ease,visibility .12s ease}.${scope} .jz-zero-tooltip:hover .jz-zero-tooltip-popover, .${scope} .jz-zero-tooltip:focus-within .jz-zero-tooltip-popover{opacity:1;visibility:visible}`;
  if (hasVector(data.elements))
    css += `.${scope} .jz-zero-vector{display:flex;align-items:center;justify-content:center}.${scope} .jz-zero-vector>svg{display:block;width:100%;height:100%;max-width:100%;max-height:100%}`;
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
            sel =
              el.type === "button"
                ? `.${scope} a.jz-zero-button[data-ze="${el.id}"]`
                : el.type === "tooltip"
                  ? `.${scope} .jz-zero-tooltip[data-ze="${el.id}"]`
                : `.${scope} [data-ze="${el.id}"]`,
            appearance =
              el.type === "button"
                ? appearanceCSS({ ...s, textAlign: "center" }, board, autoScale)
                : appearanceCSS(s, board, autoScale),
            buttonPadding =
              autoScale && el.type === "button"
                ? `padding:${scaledGridLength(12, board, true)} ${scaledGridLength(16, board, true)};`
                : "";
          css += wrap(
            mode,
            `${sel}{${geometryCSS(e.geometry, board, nested, autoScale)}${appearance}${buttonPadding}z-index:${index + 1};display:${e.visible ? (el.type === "button" ? "flex" : el.type === "tooltip" ? "grid" : "block") : "none"};${el.type === "ellipse" ? "border-radius:50%;" : ""}${el.type === "image" && !s.objectFit ? "object-fit:cover;" : ""}${el.type === "tooltip" ? "overflow:visible;" : ""}}${s.hoverFill || s.hoverColor ? `${sel}:hover{${s.hoverFill ? `background-color:${s.hoverFill};` : ""}${s.hoverColor ? `color:${s.hoverColor};` : ""}}` : ""}`,
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
        if (el.type === "html")
          body = sandboxFrame(e.code, {
            id: el.id,
            runScripts,
            title: el.name || "HTML-элемент",
          });
        if (el.type === "image" && !activeMode) {
          const sources = sourceModes
            .map(
              (m) =>
                `<source media="(max-width:${breakpointMediaMax(data, m)}px)" srcset="${escapeHTML(resolve(el, m).src.replace(/,/g, "%2C"))}">`,
            )
            .join("");
          body = `<picture style="display:block;width:100%;height:100%;object-fit:inherit;object-position:inherit;border-radius:inherit">${sources}${body}</picture>`;
        }
        if (el.type === "html") return body;
        return `<${tag} class="jz-zero-element${el.type === "button" ? " jz-zero-button" : ""}${el.type === "tooltip" ? " jz-zero-tooltip" : ""}${el.type === "vector" ? " jz-zero-vector" : ""}" data-ze="${escapeHTML(el.id)}"${el.type === "vector" ? ` role="img" aria-label="${escapeHTML(e.name || "Вектор")}"` : ""}${el.type === "image" ? ' data-zero-image="true"' : ""}${tag === "a" ? ` href="${escapeHTML(e.href || "#")}" target="${escapeHTML(e.target || "_self")}" rel="noopener noreferrer"` : ""}>${body}</${tag}>`;
      })
      .join("");
  const html = walk(data.elements);
  return `<section class="jz-zero-block ${scope}" data-zero-id="${escapeHTML(blockId)}"><style>${css}</style>${html}</section>`;
}
