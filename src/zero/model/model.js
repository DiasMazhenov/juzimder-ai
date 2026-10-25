export const MODES = Object.freeze({
  desktop: 1200,
  laptop: 980,
  tablet: 640,
  landscape: 480,
  mobile: 360,
});
export const LIMITS = Object.freeze({ elements: 300, depth: 8 });
export const copy = (value) => structuredClone(value);
export const newId = () => `ze-${crypto.randomUUID()}`;
const BREAKPOINT_ID = /^(?:desktop|laptop|tablet|landscape|mobile|bp-[a-f0-9-]{8,36})$/;
export function breakpointEntries(data, order = "desc") {
  const entries = Object.entries(data.artboards).sort(
    ([, left], [, right]) => right.width - left.width,
  );
  return order === "asc" ? entries.reverse() : entries;
}
export function breakpointMediaMax(data, mode) {
  const entries = breakpointEntries(data),
    index = entries.findIndex(([id]) => id === mode);
  return index <= 0 ? null : entries[index - 1][1].width - 1;
}
export function breakpointForViewport(data, viewportWidth) {
  const entries = breakpointEntries(data),
    width = Number(viewportWidth);
  if (!Number.isFinite(width) || !entries.length) return entries[0]?.[0] || "desktop";
  if (width >= entries[0][1].width) return entries[0][0];
  const index = entries.findIndex(
    ([, board], i) => i > 0 && width >= board.width && width < entries[i - 1][1].width,
  );
  return index > 0 ? entries[index][0] : entries.at(-1)[0];
}
export function breakpointName(mode, width) {
  const standard = {
    desktop: 1200,
    laptop: 980,
    tablet: 640,
    landscape: 480,
    mobile: 360,
  };
  return standard[mode] === width
    ? {
        desktop: "Desktop",
        laptop: "Laptop",
        tablet: "Tablet",
        landscape: "Mobile landscape",
        mobile: "Mobile",
      }[mode]
    : "Custom breakpoint";
}
export function validateBreakpoints(data) {
  const entries = breakpointEntries(data),
    ids = new Set(),
    widths = new Set();
  if (
    entries.length < 1 ||
    entries.length > 10 ||
    !data.artboards.desktop ||
    entries[0][0] !== "desktop"
  )
    throw new Error("Нужен один широчайший базовый брейкпоинт и не более 10 режимов.");
  for (const [id, board] of entries) {
    if (!BREAKPOINT_ID.test(id))
      throw new Error("Не удалось распознать ID брейкпоинта.");
    if (
      !Number.isInteger(board.width) ||
      board.width < 320 ||
      board.width > 2400 ||
      ids.has(id) ||
      widths.has(board.width)
    )
      throw new Error("Ширины брейкпоинтов должны быть уникальными от 320 до 2400 px.");
    ids.add(id);
    widths.add(board.width);
  }
  return entries;
}
function eachElement(elements, callback) {
  for (const element of elements) {
    callback(element);
    if (element.children) eachElement(element.children, callback);
  }
}
function resolvedOverride(element, mode) {
  const value = resolve(element, mode),
    override = {
      geometry: copy(value.geometry),
      style: copy(value.style),
      visible: value.visible,
    };
  if (element.type === "image" && value.src !== undefined)
    override.src = value.src;
  return override;
}
export function suggestBreakpointWidth(data) {
  const used = new Set(breakpointEntries(data).map(([, board]) => board.width));
  if (!used.has(768)) return 768;
  const bounds = [2401, ...[...used].sort((a, b) => b - a), 319];
  let candidate = null,
    widestGap = 0;
  for (let index = 0; index < bounds.length - 1; index++) {
    const high = bounds[index],
      low = bounds[index + 1],
      gap = high - low - 1;
    if (gap > widestGap) {
      widestGap = gap;
      candidate = Math.floor((high + low) / 2);
    }
  }
  return candidate;
}
export function addBreakpoint(data, width) {
  validateBreakpoints(data);
  if (
    Object.keys(data.artboards).length >= 10 ||
    !Number.isInteger(width) ||
    width < 320 ||
    width > 2400 ||
    Object.values(data.artboards).some((board) => board.width === width)
  )
    throw new Error("Выберите свободную ширину от 320 до 2400 px (не более 10 режимов).");

  const id = `bp-${crypto.randomUUID()}`,
    entries = breakpointEntries(data),
    highest = entries[0];
  if (width > highest[1].width) {
    const previousWidth = highest[1].width,
      previousBoard = copy(data.artboards.desktop);
    previousBoard.width = previousWidth;
    data.artboards.desktop.width = width;
    data.artboards[id] = previousBoard;
    eachElement(data.elements, (element) => {
      element.overrides ??= {};
      element.overrides[id] = resolvedOverride(element, "desktop");
    });
  } else {
    const larger = entries.filter(([, board]) => board.width > width).at(-1),
      board = copy(data.artboards[larger[0]]);
    board.width = width;
    data.artboards[id] = board;
    eachElement(data.elements, (element) => {
      element.overrides ??= {};
      element.overrides[id] = resolvedOverride(element, larger[0]);
    });
  }
  return id;
}
export function removeBreakpoint(data, id) {
  const entries = validateBreakpoints(data);
  if (!data.artboards[id]) throw new Error("Брейкпоинт уже удалён.");
  if (entries.length === 1)
    throw new Error("В Zero Block должен оставаться хотя бы один брейкпоинт.");

  if (id === "desktop") {
    const promotedId = entries[1][0],
      promotedBoard = copy(data.artboards[promotedId]);
    eachElement(data.elements, (element) => {
      const value = resolve(element, promotedId);
      element.geometry = copy(value.geometry);
      element.style = copy(value.style);
      element.visible = value.visible;
      if (element.type === "image" && value.src !== undefined)
        element.src = value.src;
      delete element.overrides?.[promotedId];
      delete element.overrides?.desktop;
      if (element.overrides && !Object.keys(element.overrides).length)
        delete element.overrides;
    });
    delete data.artboards.desktop;
    delete data.artboards[promotedId];
    data.artboards.desktop = promotedBoard;
  } else {
    delete data.artboards[id];
    eachElement(data.elements, (element) => {
      delete element.overrides?.[id];
    });
  }
  return data;
}
export function createZeroData() {
  return {
    version: 1,
    artboards: Object.fromEntries(
      Object.entries(MODES).map(([mode, width]) => [
        mode,
        {
          width,
          height: 650,
          background: "#ffffff",
          gridWidth: Math.min(1200, width),
          margin: 20,
          gutter: 20,
          overflow: "hidden",
          scaleGrid: "fixed",
          guides: [],
        },
      ]),
    ),
    elements: [],
  };
}
export function createElement(type, tokens = {}) {
  const sizes = {
    text: [420, 110],
    image: [360, 240],
    button: [220, 54],
    tooltip: [36, 36],
    html: [420, 260],
    group: [400, 300],
    rectangle: [240, 180],
    ellipse: [180, 180],
    line: [280, 2],
  };
  const [width, height] = sizes[type] || sizes.rectangle;
  return {
    id: newId(),
    type,
    name: {
      text: "Текст",
      image: "Фото",
      button: "Кнопка",
      tooltip: "Подсказка",
      html: "HTML",
      group: "Группа",
      rectangle: "Прямоугольник",
      ellipse: "Эллипс",
      line: "Линия",
    }[type],
    geometry: {
      container: "grid",
      anchorX: 0,
      anchorY: 0,
      originX: 0,
      originY: 0,
      x: 80,
      y: 80,
      width,
      height,
      rotation: 0,
    },
    style: {
      fill: ["rectangle", "ellipse", "line", "button"].includes(type)
        ? tokens.forest || "#245b46"
        : "transparent",
      color: type === "button" ? "#ffffff" : tokens.ink || "#183e31",
      fontSize: type === "text" ? 40 : 16,
      fontFamily: tokens.headingFontFamily || "Arial, sans-serif",
      ...(type === "image"
        ? { objectFit: "cover", objectPosition: "center" }
        : {}),
      fontWeight: 500,
      lineHeight: 1.2,
      radius: type === "button" ? 12 : 0,
      opacity: 1,
      strokeWidth: 0,
      textAlign: "left",
    },
    overrides: {},
    visible: true,
    locked: false,
    keepAspect: ["image", "group"].includes(type),
    ...(type === "group"
      ? { children: [] }
      : {
          content:
            type === "text"
              ? "Создавайте свободно"
              : type === "button"
                ? "Подробнее"
                : type === "tooltip"
                  ? "Дополнительная информация"
                : "",
        }),
    ...(type === "html"
      ? { code: { html: "", css: "", js: "" } }
      : {}),
  };
}
export function resolve(element, mode) {
  const o = element.overrides?.[mode] || {};
  return {
    ...element,
    ...o,
    geometry: { ...element.geometry, ...o.geometry },
    style: { ...element.style, ...o.style },
  };
}
export function setValue(element, mode, section, key, value) {
  const target =
    mode === "desktop" ? element : (element.overrides[mode] ??= {});
  if (section) {
    target[section] ??= {};
    target[section][key] = value;
  } else target[key] = value;
}
export function findElement(data, id) {
  let result;
  const walk = (list, parent = null) => {
    for (const element of list) {
      if (element.id === id) result = { element, list, parent };
      if (element.children) walk(element.children, element);
    }
  };
  walk(data.elements);
  return result;
}
export function flatten(data) {
  const out = [];
  const walk = (list, depth = 0) => {
    for (const element of list) {
      out.push({ element, depth });
      if (element.children) walk(element.children, depth + 1);
    }
  };
  walk(data.elements);
  return out;
}
export function rect(g, board, nested = false) {
  const width = nested
    ? board.width
    : g.container === "grid"
      ? Math.min(board.width, board.gridWidth)
      : board.width;
  const start =
    nested || g.container === "window" ? 0 : (board.width - width) / 2;
  return {
    x: start + g.anchorX * width + g.x - g.originX * g.width,
    y: g.anchorY * board.height + g.y - g.originY * g.height,
    width: g.width,
    height: g.height,
  };
}
export function anchorGeometry(g, board, changes, nested = false) {
  const next = { ...g, ...changes },
    old = rect(g, board, nested),
    base = rect({ ...next, x: 0, y: 0 }, board, nested),
    shift = rotatePoint(
      (next.originX - g.originX) * g.width,
      (next.originY - g.originY) * g.height,
      g.rotation,
    );
  return {
    ...next,
    x:
      old.x +
      g.originX * g.width +
      shift.x -
      base.x -
      next.originX * next.width,
    y:
      old.y +
      g.originY * g.height +
      shift.y -
      base.y -
      next.originY * next.height,
  };
}
export const rotatePoint = (x, y, degrees) => {
  const a = (degrees * Math.PI) / 180;
  return {
    x: x * Math.cos(a) - y * Math.sin(a),
    y: x * Math.sin(a) + y * Math.cos(a),
  };
};
export function resizeGeometry(g, dx, dy, handle, keepAspect = false) {
  const d = rotatePoint(dx, dy, -g.rotation);
  let width = g.width,
    height = g.height,
    lx = 0,
    ly = 0;
  if (handle.includes("e")) width = Math.max(1, g.width + d.x);
  if (handle.includes("s")) height = Math.max(1, g.height + d.y);
  if (handle.includes("w")) {
    width = Math.max(1, g.width - d.x);
    lx = g.width - width;
  }
  if (handle.includes("n")) {
    height = Math.max(1, g.height - d.y);
    ly = g.height - height;
  }
  if (keepAspect) {
    let ratio;
    if (!/[ew]/.test(handle)) ratio = height / g.height;
    else if (!/[ns]/.test(handle)) ratio = width / g.width;
    else
      ratio =
        Math.abs(width / g.width - 1) > Math.abs(height / g.height - 1)
          ? width / g.width
          : height / g.height;
    width = Math.max(1, g.width * ratio);
    height = Math.max(1, g.height * ratio);
    lx = handle.includes("w")
      ? g.width - width
      : handle.includes("e")
        ? 0
        : (g.width - width) / 2;
    ly = handle.includes("n")
      ? g.height - height
      : handle.includes("s")
        ? 0
        : (g.height - height) / 2;
  }
  const pivotShift = rotatePoint(
    lx + g.originX * (width - g.width),
    ly + g.originY * (height - g.height),
    g.rotation,
  );
  return { ...g, width, height, x: g.x + pivotShift.x, y: g.y + pivotShift.y };
}
export function screenDelta(dx, dy, zoom, parentRotation = 0) {
  return rotatePoint(dx / zoom, dy / zoom, -parentRotation);
}
export function zoomCameraAtPoint({
  zoom,
  nextZoom,
  scrollLeft = 0,
  scrollTop = 0,
  pointX = 0,
  pointY = 0,
  documentX,
  documentY,
  planeOffsetX = 80,
  planeOffsetY = 80,
  stagePaddingRight = 80,
  stagePaddingBottom = 80,
  viewportWidth = 0,
  viewportHeight = 0,
  boardWidth = 0,
  boardHeight = 0,
  minZoom = 0.25,
  maxZoom = 3,
}) {
  const currentZoom = Number.isFinite(zoom) && zoom > 0 ? zoom : 1,
    requested = Number.isFinite(nextZoom) ? nextZoom : currentZoom,
    targetZoom =
      Math.round(Math.min(maxZoom, Math.max(minZoom, requested)) * 100) / 100,
    anchorX = Number.isFinite(documentX)
      ? documentX
      : (scrollLeft + pointX - planeOffsetX) / currentZoom,
    anchorY = Number.isFinite(documentY)
      ? documentY
      : (scrollTop + pointY - planeOffsetY) / currentZoom;
  let nextOffsetX = planeOffsetX,
    nextOffsetY = planeOffsetY,
    nextScrollLeft = nextOffsetX + anchorX * targetZoom - pointX,
    nextScrollTop = nextOffsetY + anchorY * targetZoom - pointY;
  if (nextScrollLeft < 0) {
    nextOffsetX -= nextScrollLeft;
    nextScrollLeft = 0;
  }
  if (nextScrollTop < 0) {
    nextOffsetY -= nextScrollTop;
    nextScrollTop = 0;
  }
  let nextPaddingRight = Math.max(80, stagePaddingRight),
    nextPaddingBottom = Math.max(80, stagePaddingBottom),
    maxScrollLeft = Math.max(
      0,
      nextOffsetX + boardWidth * targetZoom + nextPaddingRight - viewportWidth,
    ),
    maxScrollTop = Math.max(
      0,
      nextOffsetY + boardHeight * targetZoom + nextPaddingBottom - viewportHeight,
    );
  if (nextScrollLeft > maxScrollLeft) {
    nextPaddingRight += nextScrollLeft - maxScrollLeft;
    maxScrollLeft = nextScrollLeft;
  }
  if (nextScrollTop > maxScrollTop) {
    nextPaddingBottom += nextScrollTop - maxScrollTop;
    maxScrollTop = nextScrollTop;
  }
  return {
    zoom: targetZoom,
    planeOffsetX: nextOffsetX,
    planeOffsetY: nextOffsetY,
    stagePaddingRight: nextPaddingRight,
    stagePaddingBottom: nextPaddingBottom,
    documentX: anchorX,
    documentY: anchorY,
    scrollLeft: Math.min(maxScrollLeft, nextScrollLeft),
    scrollTop: Math.min(maxScrollTop, nextScrollTop),
  };
}
export function scaledGridLength(value, board, autoScale = false) {
  if (!autoScale) return `${value}px`;
  return `${Number(((value * 100) / board.gridWidth).toFixed(4))}vw`;
}
export function geometryCSS(g, board, nested = false, autoScale = false) {
  const scale = autoScale && (nested || g.container === "grid"),
    length = (value) => scaledGridLength(value, board, scale);
  const offset = g.x;
  let left = `calc(${g.anchorX * 100}% + ${length(offset)})`;
  if (!nested && g.container === "grid") {
    if (scale)
      left =
        g.anchorX === 0.5
          ? `calc(50% + ${length(offset)})`
          : `calc(50% ${g.anchorX === 0 ? "-" : "+"} 50vw + ${length(offset)})`;
    else
      left =
        g.anchorX === 0.5
          ? `calc(50% + ${length(offset)})`
          : `calc(50% ${g.anchorX === 0 ? "-" : "+"} min(50%, ${board.gridWidth / 2}px) + ${length(offset)})`;
  }
  return `position:absolute;box-sizing:border-box;left:${left};top:calc(${g.anchorY * 100}% + ${length(g.y)});width:${length(g.width)};height:${length(g.height)};transform:translate(${-g.originX * 100}%,${-g.originY * 100}%) rotate(${g.rotation}deg);transform-origin:${g.originX * 100}% ${g.originY * 100}%;`;
}
export function appearanceCSS(s, board = null, autoScale = false) {
  const map = {
    fontSize: "font-size",
    fontWeight: "font-weight",
    lineHeight: "line-height",
    letterSpacing: "letter-spacing",
    radius: "border-radius",
    opacity: "opacity",
    color: "color",
    fill: "background-color",
    fontFamily: "font-family",
    textAlign: "text-align",
    textDecoration: "text-decoration",
    objectFit: "object-fit",
    objectPosition: "object-position",
  };
  let css = "";
  const lengths = new Set([
      "fontSize",
      "letterSpacing",
      "radius",
      "strokeWidth",
      "shadowX",
      "shadowY",
      "shadowBlur",
    ]),
    dimension = (key, value) =>
      autoScale && board
        ? scaledGridLength(value, board, true)
        : `${String(value).replace(/[;{}<>]/g, "")}px`;
  for (const [key, prop] of Object.entries(map))
    if (s[key] !== undefined)
      css += `${prop}:${lengths.has(key) ? dimension(key, s[key]) : String(s[key]).replace(/[;{}<>]/g, "")};`;
  const strokeWidth = dimension("strokeWidth", s.strokeWidth || 0);
  css += `border:${strokeWidth} solid ${s.stroke || "transparent"};`;
  if (s.shadowBlur !== undefined)
    css += `box-shadow:${dimension("shadowX", s.shadowX || 0)} ${dimension("shadowY", s.shadowY || 0)} ${dimension("shadowBlur", s.shadowBlur)} ${s.shadowColor || "#000000"};`;
  if (s.autoHeight) css += "height:auto;";
  return css;
}
export function adapt(data, mode) {
  const board = data.artboards[mode];
  for (const { element } of flatten(data)) {
    if (element.overrides[mode]) continue;
    const g = element.geometry,
      scale = Math.min(1, board.width / 1200);
    element.overrides[mode] = {
      geometry: {
        x: Math.round(g.x * scale),
        y: Math.round(g.y * scale),
        width: Math.max(1, Math.round(g.width * scale)),
        height: Math.max(1, Math.round(g.height * scale)),
      },
      style: {
        fontSize: Math.max(
          12,
          Math.round((element.style.fontSize || 16) * scale),
        ),
      },
    };
  }
}
export class History {
  constructor(value) {
    this.items = [copy(value)];
    this.index = 0;
  }
  push(value) {
    const serialized = JSON.stringify(value);
    if (serialized === JSON.stringify(this.items[this.index])) return;
    this.items = this.items.slice(0, this.index + 1);
    this.items.push(copy(value));
    if (this.items.length > 100) this.items.shift();
    this.index = this.items.length - 1;
  }
  undo() {
    if (this.index > 0) this.index--;
    return copy(this.items[this.index]);
  }
  redo() {
    if (this.index < this.items.length - 1) this.index++;
    return copy(this.items[this.index]);
  }
}
