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
import { videoEmbedUrl } from "./model/video.js";
import { colorFillCss } from "../ui/color-picker.js";
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
function hasVideo(elements) {
  return elements.some(
    (element) =>
      element.type === "video" ||
      (element.children?.length && hasVideo(element.children)),
  );
}
function hasForm(elements) {
  return elements.some(
    (element) =>
      element.type === "form" ||
      (element.children?.length && hasForm(element.children)),
  );
}
function hasGallery(elements) {
  return elements.some(
    (element) =>
      element.type === "gallery" ||
      (element.children?.length && hasGallery(element.children)),
  );
}
function photoCreditColor(background) {
  const match = /^#([a-f\d]{6})$/i.exec(String(background || ""));
  if (!match) return "#222629";
  const channels = [0, 2, 4].map((index) => parseInt(match[1].slice(index, index + 2), 16) / 255);
  const luminance = channels
    .map((channel) => (channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4))
    .reduce((sum, channel, index) => sum + channel * [0.2126, 0.7152, 0.0722][index], 0);
  return luminance > 0.179 ? "#222629" : "#ffffff";
}
function isPillBadge(element, style = element?.style || {}) {
  if (element?.type !== "text") return false;
  if (/(?:badge|pill|бейдж|пилюля)/iu.test(String(element.name || "")))
    return true;
  const fill = style.fill,
    hasFill =
      typeof fill === "string"
        ? fill.trim() !== "" && fill.trim().toLowerCase() !== "transparent"
        : Boolean(fill && typeof fill === "object"),
    content = String(element.content || "").trim();
  return (
    hasFill &&
    Number(style.radius || 0) >= 10 &&
    Number(style.fontSize || 99) <= 18 &&
    content.length > 0 &&
    content.length <= 64
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
  if (el.type === "video") {
    const src = videoEmbedUrl(el.videoUrl);
    return src
      ? `<iframe title="${escapeHTML(el.name || "Видео")}" src="${src}" loading="lazy" referrerpolicy="no-referrer" sandbox="allow-scripts allow-same-origin allow-presentation" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>`
      : `<span class="jz-zero-video-placeholder" role="img" aria-label="${escapeHTML(el.name || "Видео")}">Добавьте ссылку YouTube или Vimeo в настройках</span>`;
  }
  if (el.type === "form") {
    const noteId = `jz-form-note-${escapeHTML(el.id)}`;
    const fields = (el.formFields || []).map((field) => {
      const id = `jz-form-${escapeHTML(el.id)}-${escapeHTML(field.id)}`;
      const label = `${escapeHTML(field.label)}${field.required ? '<span class="jz-zero-form-required" aria-hidden="true"> *</span>' : ""}`;
      const required = field.required ? ' aria-required="true"' : "";
      const control = field.type === "textarea"
        ? `<textarea class="jz-zero-form-control" id="${id}" placeholder="${escapeHTML(field.placeholder)}" readonly aria-readonly="true"${required}></textarea>`
        : `<input class="jz-zero-form-control" id="${id}" type="${escapeHTML(field.type)}" placeholder="${escapeHTML(field.placeholder)}" readonly aria-readonly="true"${required}>`;
      return `<label class="jz-zero-form-field" for="${id}"><span>${label}</span>${control}</label>`;
    }).join("");
    return `<div class="jz-zero-form-fields" role="group" aria-label="${escapeHTML(el.name || "Форма")}" aria-describedby="${noteId}">${fields}<button class="jz-zero-form-submit" type="button" disabled aria-disabled="true">${escapeHTML(el.submitLabel || "Отправить")}</button><p class="jz-zero-form-note" id="${noteId}">Отправка формы пока не подключена.</p></div>`;
  }
  if (el.type === "gallery") {
    const items = (el.galleryItems || []).map((item) =>
      `<figure class="jz-zero-gallery-item" role="listitem"><img src="${escapeHTML(item.src)}" alt="${escapeHTML(item.alt || "")}" loading="lazy" decoding="async" draggable="false"></figure>`,
    ).join("");
    return `<div class="jz-zero-gallery-grid" role="list" aria-label="${escapeHTML(el.name || "Галерея")}">${items || '<span class="jz-zero-gallery-empty">Добавьте фото в настройках галереи</span>'}</div>`;
  }
  if (el.type === "group") return children;
  return content;
}
function artboardBackgroundCSS(board) {
  const rawColor = /^(?:#[a-f\d]{6}|transparent)$/i.test(board.background || "")
      ? board.background
      : "#ffffff",
    color = rawColor === "transparent" ? "#ffffff" : rawColor,
    alpha = Number.isFinite(board.backgroundOpacity)
      ? Math.max(0, Math.min(1, board.backgroundOpacity))
      : rawColor === "transparent" ? 0 : 1,
    colorCSS = alpha >= 1
      ? color
      : `rgba(${[1, 3, 5].map((index) => Number.parseInt(color.slice(index, index + 2), 16)).join(",")},${alpha})`,
    url = /^\/api\/projects\/[a-f\d]{24}\/assets\/[a-f\d]{24}\/file$/.test(
      board.backgroundImage || "",
    ),
    fillCSS = colorFillCss(board.backgroundFill);
  if (!url) return `background:${colorCSS};${fillCSS ? `background-image:${fillCSS};` : ""}`;
  const rgba = (key, opacityKey) => {
    const value = /^#[a-f\d]{6}$/i.test(board[key] || "") ? board[key] : "#000000",
      channels = [1, 3, 5].map((index) => Number.parseInt(value.slice(index, index + 2), 16)),
      opacity = Number.isFinite(board[opacityKey])
        ? Math.max(0, Math.min(1, board[opacityKey]))
        : 0;
    return `rgba(${channels.join(",")},${opacity})`;
  };
  const size = ["cover", "contain", "auto", "100% 100%"].includes(board.backgroundSize)
      ? board.backgroundSize
      : "cover",
    positions = ["center center", "left top", "center top", "right top", "left center", "right center", "left bottom", "center bottom", "right bottom"],
    position = positions.includes(board.backgroundPosition) ? board.backgroundPosition : "center center";
  const layers = [`linear-gradient(180deg,${rgba("filterStartColor", "filterStartOpacity")},${rgba("filterEndColor", "filterEndOpacity")})`];
  if (fillCSS) layers.push(fillCSS);
  layers.push(`url("${board.backgroundImage}")`);
  return `background:${colorCSS};background-image:${layers.join(",")};background-size:${size};background-position:${position};background-repeat:no-repeat;`;
}
export function renderZero(
  data,
  blockId,
  activeMode = null,
  { runScripts = false } = {},
) {
  const scope = "jz-zero-" + String(blockId).replace(/[^a-zA-Z0-9_-]/g, "");
  const modes = breakpointEntries(data).map(([id]) => id),
    sourceModes = [...modes].reverse().filter((mode) => mode !== "desktop"),
    animationEffects = new Set();
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
  if (hasVideo(data.elements))
    css += `.${scope} .jz-zero-video{overflow:hidden;padding:0!important;background:#111}.${scope} .jz-zero-video>iframe{display:block;width:100%;height:100%;border:0}.${scope} .jz-zero-video-placeholder{display:flex;width:100%;height:100%;align-items:center;justify-content:center;padding:16px;background:#111;color:#fff;font:14px/1.4 system-ui,sans-serif;text-align:center}.zero-app:not(.zero-preview) .${scope} .jz-zero-video>iframe{pointer-events:none}`;
  if (hasForm(data.elements))
    css += `.${scope} .jz-zero-form{overflow:auto;white-space:normal}.${scope} .jz-zero-form-fields{display:grid;align-content:start;gap:12px;width:100%;min-height:100%;box-sizing:border-box;font:14px/1.4 system-ui,sans-serif;white-space:normal}.${scope} .jz-zero-form-field{display:grid;gap:5px;color:inherit;font-weight:600}.${scope} .jz-zero-form-control{box-sizing:border-box;width:100%;min-height:40px;padding:9px 11px;border:1px solid rgba(24,62,49,.28);border-radius:8px;background:rgba(255,255,255,.9);color:#183e31;font:14px/1.4 system-ui,sans-serif;white-space:normal}.${scope} textarea.jz-zero-form-control{min-height:76px;resize:none}.${scope} .jz-zero-form-required{color:#b42318}.${scope} .jz-zero-form-submit{min-height:40px;padding:10px 14px;border:0;border-radius:8px;background:#245b46;color:#fff;font:600 14px/1.2 system-ui,sans-serif;opacity:.62;cursor:not-allowed}.${scope} .jz-zero-form-note{margin:0;color:rgba(24,62,49,.72);font:12px/1.4 system-ui,sans-serif}.zero-app:not(.zero-preview) .${scope} .jz-zero-form-fields{pointer-events:none}`;
  if (hasGallery(data.elements))
    css += `.${scope} .jz-zero-gallery{overflow:hidden!important;white-space:normal}.${scope} .jz-zero-gallery-grid{display:grid;grid-template-columns:repeat(var(--jz-gallery-columns,3),minmax(0,1fr));grid-template-rows:repeat(var(--jz-gallery-rows,1),minmax(0,1fr));gap:var(--jz-gallery-gap,16px);width:100%;height:100%;box-sizing:border-box}.${scope} .jz-zero-gallery-item{min-width:0;min-height:0;margin:0;overflow:hidden;border-radius:var(--jz-gallery-radius,0px)}.${scope} .jz-zero-gallery-item img{display:block;width:100%;height:100%;object-fit:var(--jz-gallery-fit,cover);border-radius:inherit}.${scope} .jz-zero-gallery-empty{grid-column:1/-1;display:flex;align-items:center;justify-content:center;min-width:0;min-height:0;color:inherit;text-align:center;white-space:normal}`;
  const wrap = (mode, rule) =>
    activeMode || mode === "desktop"
      ? rule
      : `@media(max-width:${breakpointMediaMax(data, mode)}px){${rule}}`;
  for (const mode of activeMode ? [activeMode] : modes) {
    const b = data.artboards[mode];
    const autoScale = !activeMode && b.scaleGrid === "autoscale";
    css += wrap(
      mode,
      `.${scope}{height:${autoScale ? scaledGridLength(b.height, b, true) : `${b.height}px`};${artboardBackgroundCSS(b)}overflow:${b.overflow}}`,
    );
    css += wrap(mode, `.${scope} .jz-zero-photo-credit{color:${photoCreditColor(b.background)}!important}`);
  }
  const walk = (list, nested = false, inheritedGrid = {}) =>
    list
      .map((el, index) => {
        for (const mode of activeMode ? [activeMode] : modes) {
          const e = resolve(el, mode),
            s = e.style,
            badge = isPillBadge(e, s),
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
              el.type === "button" || badge
                ? appearanceCSS({ ...s, textAlign: "center" }, board, autoScale)
                : appearanceCSS(s, board, autoScale),
            buttonPadding =
              autoScale && el.type === "button"
                ? `padding:${scaledGridLength(12, board, true)} ${scaledGridLength(16, board, true)};`
                : "";
          let animationStyle = "";
          const animation = el.animation,
            validAnimation =
              animation &&
              (!activeMode || runScripts) &&
              ["fade", "move", "scale"].includes(animation.effect) &&
              Number.isInteger(animation.duration) &&
              animation.duration >= 100 &&
              animation.duration <= 3000 &&
              Number.isInteger(animation.delay) &&
              animation.delay >= 0 &&
              animation.delay <= 5000 &&
              ["linear", "ease", "ease-in", "ease-out", "ease-in-out"].includes(animation.easing);
          if (validAnimation) {
            if (!animationEffects.has(animation.effect)) {
              animationEffects.add(animation.effect);
              const frames = {
                fade: "from{opacity:0}to{opacity:var(--jz-zero-end-opacity,1)}",
                move: "from{opacity:0;translate:0 16px}to{opacity:var(--jz-zero-end-opacity,1);translate:0 0}",
                scale: "from{opacity:0;scale:.92}to{opacity:var(--jz-zero-end-opacity,1);scale:1}",
              }[animation.effect];
              css += `@keyframes ${scope}-${animation.effect}{${frames}}`;
            }
            animationStyle = `--jz-zero-end-opacity:${Number(s.opacity ?? 1)};animation:${scope}-${animation.effect} ${animation.duration}ms ${animation.easing} ${animation.delay}ms both;`;
          }
          if (el.type === "gallery") {
            const options = el.galleryOptions || { columns: 3, gap: 16, objectFit: "cover" };
            const columns = Math.min(options.columns, Math.max(1, el.galleryItems?.length || 1), board.width <= 480 ? 1 : board.width <= 820 ? 2 : options.columns);
            const rows = Math.max(1, Math.ceil((el.galleryItems?.length || 1) / columns));
            const scale = (value) => autoScale ? scaledGridLength(value, board, true) : `${value}px`;
            const galleryVars = `--jz-gallery-columns:${columns};--jz-gallery-rows:${rows};--jz-gallery-gap:${scale(options.gap)};--jz-gallery-fit:${options.objectFit};--jz-gallery-radius:${scale(s.radius || 0)};`;
            css += wrap(mode, `${sel}{${galleryVars}}`);
          }
          const hoverFillCSS = s.hoverFill && typeof s.hoverFill === "object"
            ? `background-color:transparent;background-image:${colorFillCss(s.hoverFill)};`
            : s.hoverFill ? `background-color:${s.hoverFill};` : "",
            hoverCSS = s.hoverFill || s.hoverColor
              ? `${sel}:hover{${hoverFillCSS}${s.hoverColor ? `color:${s.hoverColor};` : ""}}`
              : "";
          css += wrap(
            mode,
              `${sel}{${geometryCSS(e.geometry, board, nested, autoScale)}${appearance}${buttonPadding}${animationStyle}z-index:${index + 1};display:${e.visible ? (el.type === "button" || badge ? "flex" : el.type === "tooltip" ? "grid" : el.type === "vector" ? "flex" : "block") : "none"};${badge ? "align-items:center;justify-content:center;text-align:center;" : ""}${el.type === "ellipse" ? "border-radius:50%;" : ""}${el.type === "image" && !s.objectFit ? "object-fit:cover;" : ""}${el.type === "tooltip" ? "overflow:visible;" : ""}${el.type === "video" ? "overflow:hidden;" : ""}}${hoverCSS}`,
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
          badge = isPillBadge(e, e.style),
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
        if (el.type === "image" && String(e.copyright || "").trim())
          body += `<span class="jz-zero-photo-credit">${escapeHTML(e.copyright)}</span>`;
        if (el.type === "html") return body;
        return `<${tag} class="jz-zero-element${badge ? " jz-zero-badge" : ""}${el.type === "button" ? " jz-zero-button" : ""}${el.type === "tooltip" ? " jz-zero-tooltip" : ""}${el.type === "vector" ? " jz-zero-vector" : ""}${el.type === "video" ? " jz-zero-video" : ""}${el.type === "form" ? " jz-zero-form" : ""}${el.type === "gallery" ? " jz-zero-gallery" : ""}" data-ze="${escapeHTML(el.id)}"${el.type === "vector" ? ` role="img" aria-label="${escapeHTML(e.name || "Вектор")}"` : ""}${el.type === "gallery" ? ` role="group" aria-label="${escapeHTML(e.name || "Галерея")}"` : ""}${el.type === "image" ? ' data-zero-image="true"' : ""}${tag === "a" ? ` href="${escapeHTML(e.href || "#")}" target="${escapeHTML(e.target || "_self")}" rel="noopener noreferrer"` : ""}>${body}</${tag}>`;
      })
      .join("");
  const html = walk(data.elements);
  css += `.${scope} .jz-zero-photo-credit{position:absolute;left:0;top:calc(100% + 2px);display:block;box-sizing:border-box;width:100%;height:auto;min-height:0;margin:0;padding:0;border:0;background:transparent;font-size:8px;line-height:1.2;font-family:inherit;font-weight:400;text-align:left;white-space:normal;overflow-wrap:anywhere}`;
  if (animationEffects.size)
    css += `@media(prefers-reduced-motion:reduce){.${scope} .jz-zero-element{animation:none!important;translate:none!important;scale:none!important}}`;
  return `<section class="jz-zero-block ${scope}" data-zero-id="${escapeHTML(blockId)}"><style>${css}</style>${html}</section>`;
}
