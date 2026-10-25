const clamp = (value, min, max) => Math.max(min, Math.min(max, value));
const HEX = /^#[a-f\d]{6}$/i;
const COLOR_KEY = "juzimder-saved-colors";
const LEGACY_COLOR_KEY = "juzimder-zero-saved-colors";

export function hexToHsv(hex) {
  if (!HEX.test(hex)) return { h: 0, s: 0, v: 1 };
  const values = [1, 3, 5].map((index) => Number.parseInt(hex.slice(index, index + 2), 16) / 255),
    [r, g, b] = values,
    max = Math.max(r, g, b),
    min = Math.min(r, g, b),
    delta = max - min;
  let h = 0;
  if (delta) {
    if (max === r) h = ((g - b) / delta) % 6;
    else if (max === g) h = (b - r) / delta + 2;
    else h = (r - g) / delta + 4;
    h = (h * 60 + 360) % 360;
  }
  return { h, s: max ? delta / max : 0, v: max };
}

export function hsvToHex(h, s, v) {
  const hue = ((Number(h) % 360) + 360) % 360,
    saturation = clamp(Number(s), 0, 1),
    value = clamp(Number(v), 0, 1),
    chroma = value * saturation,
    x = chroma * (1 - Math.abs((hue / 60) % 2 - 1)),
    m = value - chroma,
    rgb = hue < 60 ? [chroma, x, 0]
      : hue < 120 ? [x, chroma, 0]
        : hue < 180 ? [0, chroma, x]
          : hue < 240 ? [0, x, chroma]
            : hue < 300 ? [x, 0, chroma]
              : [chroma, 0, x];
  return `#${rgb.map((channel) => Math.round((channel + m) * 255).toString(16).padStart(2, "0")).join("")}`;
}

export function parseColor(value, documentRef = globalThis.document) {
  const source = String(value || "").trim();
  if (/^var\(/i.test(source)) return { color: "#ffffff", opacity: 1, valid: false };
  if (source.toLowerCase() === "transparent") return { color: "#ffffff", opacity: 0, valid: true };
  const hex = source.match(/^#([a-f\d]{3,8})$/i)?.[1];
  if (hex) {
    if (hex.length === 3 || hex.length === 4) {
      const color = `#${[0, 1, 2].map((index) => hex[index].repeat(2)).join("")}`;
      return { color, opacity: hex.length === 4 ? Number.parseInt(hex[3].repeat(2), 16) / 255 : 1, valid: true };
    }
    if (hex.length === 6 || hex.length === 8)
      return { color: `#${hex.slice(0, 6)}`.toLowerCase(), opacity: hex.length === 8 ? Number.parseInt(hex.slice(6), 16) / 255 : 1, valid: true };
  }
  const rgb = source.match(/^rgba?\(\s*([\d.]+%?)\s*,\s*([\d.]+%?)\s*,\s*([\d.]+%?)(?:\s*,\s*([\d.]+%?))?\s*\)$/i);
  if (rgb) {
    const channels = rgb.slice(1, 4).map((part) => Math.round(part.endsWith("%") ? Number.parseFloat(part) * 2.55 : Number(part)));
    if (channels.every((part) => Number.isFinite(part) && part >= 0 && part <= 255)) {
      const color = `#${channels.map((part) => part.toString(16).padStart(2, "0")).join("")}`;
      const alphaText = rgb[4];
      const opacity = alphaText ? clamp(alphaText.endsWith("%") ? Number.parseFloat(alphaText) / 100 : Number(alphaText), 0, 1) : 1;
      return { color, opacity, valid: Number.isFinite(opacity) };
    }
  }
  if (source && documentRef?.createElement) {
    const canvas = documentRef.createElement("canvas"), context = canvas.getContext("2d");
    if (context) {
      context.fillStyle = "#ffffff";
      context.fillStyle = source;
      const normalized = context.fillStyle;
      if (normalized !== source) return parseColor(normalized, null);
    }
  }
  return { color: "#ffffff", opacity: 1, valid: false };
}

export function formatColor(color, opacity = 1) {
  if (!HEX.test(color)) return "#ffffff";
  const alpha = clamp(Number(opacity), 0, 1);
  if (alpha >= 0.999) return color.toLowerCase();
  const [r, g, b] = [1, 3, 5].map((index) => Number.parseInt(color.slice(index, index + 2), 16));
  return `rgba(${r}, ${g}, ${b}, ${Math.round(alpha * 1000) / 1000})`;
}

const FILL_TYPES = new Set(["linear", "radial", "conic", "multicolor"]);
const finiteClamp = (value, min, max, fallback) => Number.isFinite(Number(value))
  ? clamp(Number(value), min, max)
  : fallback;

export function normalizeColorFill(fill) {
  if (!fill || !FILL_TYPES.has(fill.type) || !Array.isArray(fill.stops)) return null;
  const minStops = fill.type === "multicolor" ? 3 : 2;
  if (fill.stops.length < minStops || fill.stops.length > 6) return null;
  const stops = fill.stops.map((stop) => ({
    color: typeof stop?.color === "string" && HEX.test(stop.color) ? stop.color.toLowerCase() : null,
    opacity: finiteClamp(stop?.opacity, 0, 1, NaN),
    position: finiteClamp(stop?.position, 0, 100, NaN),
  }));
  if (stops.some((stop) => !stop.color || !Number.isFinite(stop.opacity) || !Number.isFinite(stop.position))) return null;
  stops.sort((a, b) => a.position - b.position);
  return {
    type: fill.type,
    angle: finiteClamp(fill.angle, 0, 360, 90),
    stops,
  };
}

export function colorFillCss(fill) {
  const normalized = normalizeColorFill(fill);
  if (!normalized) return "";
  const stops = normalized.stops.map(({ color, opacity, position }) =>
    `${formatColor(color, opacity).replaceAll(", ", ",")} ${Math.round(position * 100) / 100}%`,
  ).join(",");
  if (normalized.type === "radial") return `radial-gradient(circle,${stops})`;
  if (normalized.type === "conic") return `conic-gradient(from ${normalized.angle}deg,${stops})`;
  const angle = normalized.type === "multicolor" ? 90 : normalized.angle;
  return `linear-gradient(${angle}deg,${stops})`;
}

function safeLocalStorage() {
  try { return globalThis.localStorage; } catch { return null; }
}

function parseStoredColors(storage, key) {
  try {
    const parsed = JSON.parse(storage?.getItem(key) || "[]");
    return Array.isArray(parsed) ? [...new Set(parsed.filter((color) => typeof color === "string" && HEX.test(color)).map((color) => color.toLowerCase()))].slice(0, 12) : [];
  } catch {
    return [];
  }
}

export function readSavedColors(storage = safeLocalStorage()) {
  const current = parseStoredColors(storage, COLOR_KEY);
  return current.length ? current : parseStoredColors(storage, LEGACY_COLOR_KEY);
}

export function saveColor(color, storage = safeLocalStorage()) {
  if (!HEX.test(color) || !storage) return readSavedColors(storage);
  const colors = readSavedColors(storage), value = color.toLowerCase();
  try { storage.setItem(COLOR_KEY, JSON.stringify([value, ...colors.filter((item) => item !== value)].slice(0, 12))); } catch { /* Private browsing may disable local storage. */ }
  return readSavedColors(storage);
}

export function removeSavedColor(color, storage = safeLocalStorage()) {
  if (!HEX.test(color) || !storage) return readSavedColors(storage);
  const colors = readSavedColors(storage).filter((item) => item !== color.toLowerCase());
  try {
    storage.setItem(COLOR_KEY, JSON.stringify(colors));
    storage.setItem(LEGACY_COLOR_KEY, JSON.stringify(colors));
  } catch { /* Private browsing may disable local storage. */ }
  return readSavedColors(storage);
}

export function createColorPicker(host = globalThis.document?.body) {
  const documentRef = host?.ownerDocument || globalThis.document;
  if (!host || !documentRef) return { open() {} };
  const dialog = documentRef.createElement("dialog");
  dialog.className = "jz-color-dialog";
  dialog.setAttribute("aria-label", "Выбор цвета");
  dialog.innerHTML = `<header class="jz-color-header"><span data-color-title>Цвет</span><button type="button" data-color-close aria-label="Закрыть палитру">×</button></header>
    <div class="jz-color-fill-controls" data-color-fill-controls hidden><label>Тип заливки<select data-color-fill-type aria-label="Тип заливки"><option value="solid">Solid</option><option value="linear">Линейный градиент</option><option value="radial">Радиальный градиент</option><option value="conic">Конический градиент</option><option value="multicolor">Multicolor</option></select></label><div class="jz-color-gradient-settings" data-color-gradient-settings hidden><label class="jz-color-angle-row" data-color-angle-row>Угол<input type="range" min="0" max="360" step="1" data-color-angle aria-label="Угол градиента"><output data-color-angle-value>90°</output></label><div class="jz-color-gradient-preview" data-color-gradient-preview aria-label="Предпросмотр градиента"></div><div class="jz-color-stops" data-color-stops></div><button type="button" class="jz-color-add-stop" data-color-add-stop>+ Добавить цвет</button></div></div>
    <div class="jz-color-plane" role="group" aria-label="Выбор насыщенности и яркости" tabindex="0"><span class="jz-color-plane-marker" aria-hidden="true"></span></div>
    <label class="jz-color-hue"><span>Тон</span><input type="range" min="0" max="359" step="1" data-color-hue aria-label="Тон цвета"></label>
    <label class="jz-color-alpha" data-color-alpha-row><span>Прозрачность</span><input type="range" min="0" max="100" step="1" data-color-alpha aria-label="Прозрачность цвета"></label>
    <div class="jz-color-hex-row"><label><span>HEX</span><input type="text" data-color-hex maxlength="7" spellcheck="false" aria-label="HEX цвета"></label><output data-color-alpha-value>100%</output><button type="button" data-color-clear aria-label="Сделать цвет прозрачным" title="Сделать цвет прозрачным">×</button></div>
    <section class="jz-saved-colors"><header><span>Сохранённые цвета</span><button type="button" data-color-save aria-label="Сохранить текущий цвет" title="Сохранить текущий цвет">+</button></header><div data-color-swatches></div></section>`;
  host.append(dialog);
  const colorMenu = documentRef.createElement("div");
  colorMenu.className = "jz-color-context-menu";
  colorMenu.setAttribute("role", "menu");
  colorMenu.hidden = true;
  const deleteColorButton = documentRef.createElement("button");
  deleteColorButton.type = "button";
  deleteColorButton.className = "jz-color-delete-action";
  deleteColorButton.setAttribute("role", "menuitem");
  colorMenu.append(deleteColorButton);
  dialog.append(colorMenu);
  const $ = (selector) => dialog.querySelector(selector),
    plane = $(".jz-color-plane"),
    marker = $(".jz-color-plane-marker"),
    hue = $("[data-color-hue]"),
    alpha = $("[data-color-alpha]"),
    alphaRow = $("[data-color-alpha-row]"),
    alphaValue = $("[data-color-alpha-value]"),
    hex = $("[data-color-hex]"),
    swatches = $("[data-color-swatches]"),
    title = $("[data-color-title]"),
    clearAlpha = $("[data-color-clear]"),
    fillControls = $("[data-color-fill-controls]"),
    fillType = $("[data-color-fill-type]"),
    gradientSettings = $("[data-color-gradient-settings]"),
    angle = $("[data-color-angle]"),
    angleValue = $("[data-color-angle-value]"),
    gradientPreview = $("[data-color-gradient-preview]"),
    stopList = $("[data-color-stops]"),
    addStop = $("[data-color-add-stop]");
  let state = { h: 0, s: 0, v: 1, a: 1 }, pointerId = null, active = null, menuColor = null, fill = null, selectedStop = 0;

  function closeColorMenu() {
    colorMenu.hidden = true;
    menuColor = null;
  }
  function openColorMenu(savedColor, x, y) {
    menuColor = savedColor;
    deleteColorButton.textContent = `Удалить цвет ${savedColor.toUpperCase()}`;
    colorMenu.hidden = false;
    colorMenu.style.left = "0px";
    colorMenu.style.top = "0px";
    const bounds = colorMenu.getBoundingClientRect(), view = documentRef.defaultView;
    colorMenu.style.left = `${clamp(x, 8, Math.max(8, view.innerWidth - bounds.width - 8))}px`;
    colorMenu.style.top = `${clamp(y, 8, Math.max(8, view.innerHeight - bounds.height - 8))}px`;
    deleteColorButton.focus();
  }

  function color() { return hsvToHex(state.h, state.s, state.v); }
  function midpointColor(a, b) {
    return `#${[1, 3, 5].map((index) => Math.round((Number.parseInt(a.slice(index, index + 2), 16) + Number.parseInt(b.slice(index, index + 2), 16)) / 2).toString(16).padStart(2, "0")).join("")}`;
  }
  function fillForType(type) {
    const first = { color: color(), opacity: state.a, position: 0 },
      other = { color: color().toLowerCase() === "#ffffff" ? "#222629" : "#ffffff", opacity: 1, position: 100 };
    if (type === "multicolor") return { type, angle: 90, stops: [first, { color: midpointColor(first.color, other.color), opacity: 1, position: 50 }, other] };
    return { type, angle: 90, stops: [first, other] };
  }
  function drawStops() {
    stopList.replaceChildren();
    if (!fill) return;
    const minimum = fill.type === "multicolor" ? 3 : 2;
    fill.stops.forEach((stop, index) => {
      const row = documentRef.createElement("div"), select = documentRef.createElement("button"), position = documentRef.createElement("input"), output = documentRef.createElement("output"), remove = documentRef.createElement("button");
      row.className = "jz-color-stop";
      select.type = "button"; select.className = "jz-color-stop-swatch"; select.style.backgroundColor = formatColor(stop.color, stop.opacity); select.setAttribute("aria-label", `Редактировать цвет ${index + 1}`); select.setAttribute("aria-pressed", String(index === selectedStop));
      select.addEventListener("click", () => { selectedStop = index; state = { ...hexToHsv(stop.color), a: stop.opacity }; sync(); });
      position.type = "range"; position.min = index ? String(fill.stops[index - 1].position) : "0"; position.max = index < fill.stops.length - 1 ? String(fill.stops[index + 1].position) : "100"; position.step = "1"; position.value = String(stop.position); position.setAttribute("aria-label", `Позиция цвета ${index + 1}`);
      output.textContent = `${Math.round(stop.position)}%`;
      position.addEventListener("input", () => { stop.position = Number(position.value); output.textContent = `${position.value}%`; position.min = index ? String(fill.stops[index - 1].position) : "0"; position.max = index < fill.stops.length - 1 ? String(fill.stops[index + 1].position) : "100"; sync(); });
      position.addEventListener("change", () => paint(true));
      remove.type = "button"; remove.textContent = "×"; remove.className = "jz-color-stop-remove"; remove.disabled = fill.stops.length <= minimum; remove.setAttribute("aria-label", `Удалить цвет ${index + 1}`);
      remove.addEventListener("click", () => { fill.stops.splice(index, 1); selectedStop = Math.min(selectedStop, fill.stops.length - 1); const next = fill.stops[selectedStop]; state = { ...hexToHsv(next.color), a: next.opacity }; drawStops(); sync(); paint(true); });
      row.append(select, position, output, remove); stopList.append(row);
    });
    addStop.disabled = fill.stops.length >= 6;
  }
  function syncFill() {
    fillType.value = fill?.type || "solid";
    gradientSettings.hidden = !fill;
    const showAngle = fill?.type === "linear" || fill?.type === "conic";
    $("[data-color-angle-row]").hidden = !showAngle;
    if (fill) {
      angle.value = String(Math.round(fill.angle));
      angleValue.value = `${Math.round(fill.angle)}°`;
      angleValue.textContent = angleValue.value;
      gradientPreview.style.backgroundImage = colorFillCss(fill);
      [...stopList.querySelectorAll(".jz-color-stop")].forEach((row, index) => {
        const stop = fill.stops[index], swatch = row.querySelector(".jz-color-stop-swatch"), slider = row.querySelector('input[type="range"]'), output = row.querySelector("output");
        if (!stop || !swatch || !slider) return;
        swatch.style.backgroundColor = formatColor(stop.color, stop.opacity);
        swatch.setAttribute("aria-pressed", String(index === selectedStop));
        slider.value = String(stop.position); slider.min = index ? String(fill.stops[index - 1].position) : "0"; slider.max = index < fill.stops.length - 1 ? String(fill.stops[index + 1].position) : "100";
        output.textContent = `${Math.round(stop.position)}%`;
      });
    } else gradientPreview.style.backgroundImage = "none";
  }
  function sync() {
    const value = color();
    plane.style.setProperty("--jz-color-hue", String(state.h));
    marker.style.left = `${state.s * 100}%`;
    marker.style.top = `${(1 - state.v) * 100}%`;
    hue.value = String(Math.round(state.h));
    alpha.value = String(Math.round(state.a * 100));
    alpha.style.setProperty("--jz-color-alpha", value);
    hex.value = value.toUpperCase();
    alphaValue.value = `${Math.round(state.a * 100)}%`;
    alphaValue.textContent = alphaValue.value;
    if (fill) {
      fill.stops[selectedStop] = { ...fill.stops[selectedStop], color: value, opacity: state.a };
    }
    syncFill();
  }
  function paint(commit = false) {
    sync();
    active?.onChange?.({ color: color(), opacity: state.a, fill: normalizeColorFill(fill) }, commit);
  }
  function drawSaved() {
    swatches.replaceChildren();
    for (const saved of readSavedColors()) {
      const chip = documentRef.createElement("button");
      chip.type = "button";
      chip.className = "jz-color-swatch";
      chip.style.backgroundColor = saved;
      chip.title = saved.toUpperCase();
      chip.setAttribute("aria-label", `Выбрать цвет ${saved.toUpperCase()}`);
      chip.setAttribute("aria-haspopup", "menu");
      chip.setAttribute("aria-keyshortcuts", "Shift+F10 ContextMenu");
      chip.addEventListener("click", () => { state = { ...hexToHsv(saved), a: state.a }; paint(true); });
      chip.addEventListener("contextmenu", (event) => {
        event.preventDefault();
        openColorMenu(saved, event.clientX, event.clientY);
      });
      chip.addEventListener("keydown", (event) => {
        if (event.key !== "ContextMenu" && !(event.key === "F10" && event.shiftKey)) return;
        event.preventDefault();
        const bounds = chip.getBoundingClientRect();
        openColorMenu(saved, bounds.left, bounds.bottom);
      });
      swatches.append(chip);
    }
    if (!swatches.childElementCount) {
      const empty = documentRef.createElement("span");
      empty.className = "jz-color-empty";
      empty.textContent = "Нажмите +, чтобы сохранить цвет";
      swatches.append(empty);
    }
  }
  function updatePlane(x, y, commit = false) {
    const bounds = plane.getBoundingClientRect();
    state.s = clamp((x - bounds.left) / bounds.width, 0, 1);
    state.v = 1 - clamp((y - bounds.top) / bounds.height, 0, 1);
    paint(commit);
  }

  fillType.addEventListener("change", () => {
    const type = fillType.value;
    if (type === "solid") fill = null;
    else {
      fill = fill ? { ...fill, type } : fillForType(type);
      if (type === "multicolor" && fill.stops.length < 3) {
        const first = fill.stops[0], last = fill.stops.at(-1);
        fill.stops.splice(1, 0, { color: midpointColor(first.color, last.color), opacity: (first.opacity + last.opacity) / 2, position: (first.position + last.position) / 2 });
      }
      selectedStop = Math.min(selectedStop, fill.stops.length - 1);
      const stop = fill.stops[selectedStop];
      state = { ...hexToHsv(stop.color), a: stop.opacity };
    }
    drawStops(); sync(); paint(true);
  });
  angle.addEventListener("input", () => { if (fill) { fill.angle = Number(angle.value); angleValue.value = `${angle.value}°`; angleValue.textContent = angleValue.value; syncFill(); paint(); } });
  angle.addEventListener("change", () => paint(true));
  addStop.addEventListener("click", () => {
    if (!fill || fill.stops.length >= 6) return;
    const sorted = fill.stops.map((stop) => ({ ...stop })).sort((a, b) => a.position - b.position);
    let left = sorted[0], right = sorted[1];
    for (let index = 1; index < sorted.length - 1; index++) {
      if (sorted[index + 1].position - sorted[index].position > right.position - left.position) { left = sorted[index]; right = sorted[index + 1]; }
    }
    const stop = { color: midpointColor(left.color, right.color), opacity: (left.opacity + right.opacity) / 2, position: (left.position + right.position) / 2 };
    fill.stops.push(stop);
    fill.stops.sort((a, b) => a.position - b.position);
    selectedStop = fill.stops.indexOf(stop);
    state = { ...hexToHsv(stop.color), a: stop.opacity };
    drawStops(); sync(); paint(true);
  });

  $("[data-color-close]").addEventListener("click", () => dialog.close());
  plane.addEventListener("pointerdown", (event) => {
    if (event.button !== 0) return;
    event.preventDefault(); pointerId = event.pointerId; plane.setPointerCapture(pointerId); updatePlane(event.clientX, event.clientY);
  });
  plane.addEventListener("pointermove", (event) => { if (event.pointerId === pointerId) updatePlane(event.clientX, event.clientY); });
  plane.addEventListener("pointerup", (event) => { if (event.pointerId !== pointerId) return; updatePlane(event.clientX, event.clientY, true); pointerId = null; });
  plane.addEventListener("pointercancel", () => { pointerId = null; });
  plane.addEventListener("keydown", (event) => {
    const step = event.shiftKey ? 0.1 : 0.01;
    if (!["ArrowLeft", "ArrowRight", "ArrowUp", "ArrowDown"].includes(event.key)) return;
    event.preventDefault();
    if (event.key === "ArrowLeft") state.s -= step;
    if (event.key === "ArrowRight") state.s += step;
    if (event.key === "ArrowDown") state.v -= step;
    if (event.key === "ArrowUp") state.v += step;
    state.s = clamp(state.s, 0, 1); state.v = clamp(state.v, 0, 1); paint(true);
  });
  hue.addEventListener("input", () => { state.h = Number(hue.value); paint(); });
  hue.addEventListener("change", () => { state.h = Number(hue.value); paint(true); });
  alpha.addEventListener("input", () => { state.a = Number(alpha.value) / 100; paint(); });
  alpha.addEventListener("change", () => { state.a = Number(alpha.value) / 100; paint(true); });
  hex.addEventListener("input", () => {
    if (!HEX.test(hex.value)) return;
    state = { ...hexToHsv(hex.value), a: state.a }; paint();
  });
  hex.addEventListener("change", () => {
    if (HEX.test(hex.value)) state = { ...hexToHsv(hex.value), a: state.a };
    paint(true);
  });
  clearAlpha.addEventListener("click", () => { if (active?.allowOpacity || active?.allowFill) { state.a = 0; paint(true); } });
  $("[data-color-save]").addEventListener("click", () => { saveColor(color()); drawSaved(); });
  deleteColorButton.addEventListener("click", () => {
    if (menuColor) removeSavedColor(menuColor);
    closeColorMenu();
    drawSaved();
    $("[data-color-save]").focus();
  });
  if ("EyeDropper" in globalThis) {
    const eyeDropper = documentRef.createElement("button");
    eyeDropper.type = "button"; eyeDropper.className = "jz-color-eyedropper"; eyeDropper.textContent = "Пипетка";
    eyeDropper.addEventListener("click", async () => {
      try {
        const result = await new globalThis.EyeDropper().open();
        if (HEX.test(result.sRGBHex)) state = { ...hexToHsv(result.sRGBHex), a: state.a };
        paint(true);
      } catch { /* The native eyedropper may be cancelled. */ }
    });
    $(".jz-color-header").append(eyeDropper);
  }
  dialog.addEventListener("click", (event) => { if (event.target === dialog) dialog.close(); });
  documentRef.addEventListener("pointerdown", (event) => {
    if (!colorMenu.hidden && !colorMenu.contains(event.target)) closeColorMenu();
    if (dialog.open && !dialog.contains(event.target) && !colorMenu.contains(event.target) && !active?.trigger?.contains(event.target)) dialog.close();
  });
  dialog.addEventListener("close", () => { if (dialog.open) return; active = null; closeColorMenu(); });
  drawSaved();

  return {
    dialog,
    open({ trigger, color: currentColor, opacity = 1, fill: currentFill = null, allowOpacity = false, allowFill = false, label = "Цвет", onChange }) {
      const parsed = parseColor(currentColor, documentRef);
      fill = allowFill ? normalizeColorFill(currentFill) : null;
      selectedStop = 0;
      const selectedColor = fill?.stops[0]?.color || parsed.color,
        selectedOpacity = fill?.stops[0]?.opacity ?? (allowOpacity ? clamp(Number(opacity ?? parsed.opacity), 0, 1) : 1);
      state = { ...hexToHsv(selectedColor), a: selectedOpacity };
      active = { trigger, allowOpacity, allowFill, onChange };
      title.textContent = label;
      dialog.setAttribute("aria-label", label);
      fillControls.hidden = !allowFill;
      alphaRow.hidden = !(allowOpacity || (allowFill && fill));
      alpha.hidden = alphaRow.hidden;
      alphaValue.hidden = alphaRow.hidden;
      clearAlpha.hidden = alphaRow.hidden;
      drawSaved(); drawStops(); sync();
      if (dialog.open && !dialog.matches(":modal")) dialog.close();
      if (!dialog.open) dialog.showModal();
      if (trigger) {
        const bounds = trigger.getBoundingClientRect(), panel = dialog.getBoundingClientRect();
        dialog.style.left = `${clamp(bounds.right - panel.width, 12, Math.max(12, globalThis.innerWidth - panel.width - 12))}px`;
        dialog.style.top = `${clamp(bounds.bottom + 8, 12, Math.max(12, globalThis.innerHeight - panel.height - 12))}px`;
      }
      hex.focus(); hex.select();
    },
  };
}

let sharedPicker;
export function getSharedColorPicker(host = globalThis.document?.body) {
  if (!sharedPicker) sharedPicker = createColorPicker(host);
  return sharedPicker;
}

function updateTrigger(input) {
  const trigger = input.nextElementSibling;
  if (!trigger?.classList.contains("jz-color-field-trigger")) return;
  const parsed = parseColor(input.dataset.colorValue || input.value, input.ownerDocument),
    swatch = trigger.querySelector(".jz-color-chip"),
    value = trigger.querySelector(".jz-color-value"),
    raw = input.dataset.colorValue || input.value,
    hex = parsed.valid ? parsed.color.toUpperCase() : String(raw || "Цвет").toUpperCase();
  swatch.style.backgroundColor = parsed.valid ? formatColor(parsed.color, parsed.opacity) : "transparent";
  value.textContent = hex;
  const label = input.dataset.colorLabel || input.getAttribute("aria-label") || input.closest("label")?.textContent?.trim() || "Цвет";
  trigger.setAttribute("aria-label", `${label}: ${hex}. Открыть палитру`);
  trigger.title = `${label}: ${hex}`;
}

export function syncColorInput(input, value = input?.dataset.colorValue || input?.value) {
  if (!input) return;
  const parsed = parseColor(value, input.ownerDocument);
  if (parsed.valid) input.value = parsed.color;
  input.dataset.colorValue = parsed.valid ? formatColor(parsed.color, parsed.opacity) : String(value || input.value);
  input.dataset.colorOpacity = String(parsed.opacity);
  updateTrigger(input);
}

export function syncColorInputs(root = globalThis.document) {
  root?.querySelectorAll?.('input[type="color"]').forEach((input) => syncColorInput(input));
}

export function installColorPickerInputs(root = globalThis.document?.body) {
  if (!root) return { destroy() {} };
  const documentRef = root.ownerDocument || globalThis.document,
    picker = getSharedColorPicker(documentRef.body);
  const enhance = (input) => {
    if (!input.matches?.('input[type="color"]') || input.dataset.jzColorEnhanced) return;
    input.dataset.jzColorEnhanced = "true";
    input.classList.add("jz-color-native-input");
    input.tabIndex = -1;
    input.setAttribute("aria-hidden", "true");
    const label = input.getAttribute("aria-label") || input.closest("label")?.textContent?.trim() || input.name || "Цвет";
    input.dataset.colorLabel = label;
    const trigger = documentRef.createElement("button"), swatch = documentRef.createElement("span"), text = documentRef.createElement("span");
    trigger.type = "button"; trigger.className = "jz-color-field-trigger"; trigger.setAttribute("aria-haspopup", "dialog");
    swatch.className = "jz-color-chip"; swatch.setAttribute("aria-hidden", "true");
    text.className = "jz-color-value"; trigger.append(swatch, text); input.insertAdjacentElement("afterend", trigger);
    const update = () => updateTrigger(input);
    input.addEventListener("input", update); input.addEventListener("change", update);
    trigger.addEventListener("click", (event) => {
      event.preventDefault(); event.stopPropagation();
      const current = parseColor(input.dataset.colorValue || input.value, documentRef), allowOpacity = input.dataset.colorAlpha === "true";
      let currentFill = null;
      try { currentFill = JSON.parse(input.dataset.colorFillValue || "null"); } catch { currentFill = null; }
      const allowFill = input.dataset.colorFill === "true";
      picker.open({
        trigger, color: current.color, opacity: allowOpacity ? (input.dataset.colorOpacity ?? current.opacity) : 1,
        fill: currentFill, allowOpacity, allowFill, label: input.dataset.colorLabel || label,
        onChange: ({ color, opacity, fill }, commit) => {
          input.value = color;
          input.dataset.colorOpacity = String(opacity);
          input.dataset.colorValue = allowOpacity ? formatColor(color, opacity) : color;
          if (allowFill) input.dataset.colorFillValue = fill ? JSON.stringify(fill) : "";
          update();
          const EventCtor = documentRef.defaultView.Event;
          input.dispatchEvent(new EventCtor("input", { bubbles: true }));
          if (commit) input.dispatchEvent(new EventCtor("change", { bubbles: true }));
        },
      });
    });
    syncColorInput(input);
  };
  const scan = (node) => {
    if (node.nodeType === 1) enhance(node);
    node.querySelectorAll?.('input[type="color"]').forEach(enhance);
  };
  scan(root);
  const Observer = documentRef.defaultView?.MutationObserver || globalThis.MutationObserver;
  const observer = Observer ? new Observer((records) => records.forEach((record) => record.addedNodes.forEach(scan))) : null;
  observer?.observe(root, { childList: true, subtree: true });
  return { picker, destroy: () => observer?.disconnect() };
}
