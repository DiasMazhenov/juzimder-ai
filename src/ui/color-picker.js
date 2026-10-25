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

export function createColorPicker(host = globalThis.document?.body) {
  const documentRef = host?.ownerDocument || globalThis.document;
  if (!host || !documentRef) return { open() {} };
  const dialog = documentRef.createElement("dialog");
  dialog.className = "jz-color-dialog";
  dialog.setAttribute("aria-label", "Выбор цвета");
  dialog.innerHTML = `<header class="jz-color-header"><span data-color-title>Цвет</span><button type="button" data-color-close aria-label="Закрыть палитру">×</button></header>
    <div class="jz-color-plane" role="group" aria-label="Выбор насыщенности и яркости" tabindex="0"><span class="jz-color-plane-marker" aria-hidden="true"></span></div>
    <label class="jz-color-hue"><span>Тон</span><input type="range" min="0" max="359" step="1" data-color-hue aria-label="Тон цвета"></label>
    <label class="jz-color-alpha" data-color-alpha-row><span>Прозрачность</span><input type="range" min="0" max="100" step="1" data-color-alpha aria-label="Прозрачность цвета"></label>
    <div class="jz-color-hex-row"><label><span>HEX</span><input type="text" data-color-hex maxlength="7" spellcheck="false" aria-label="HEX цвета"></label><output data-color-alpha-value>100%</output><button type="button" data-color-clear aria-label="Сделать цвет прозрачным" title="Сделать цвет прозрачным">×</button></div>
    <section class="jz-saved-colors"><header><span>Сохранённые цвета</span><button type="button" data-color-save aria-label="Сохранить текущий цвет" title="Сохранить текущий цвет">+</button></header><div data-color-swatches></div></section>`;
  host.append(dialog);
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
    clearAlpha = $("[data-color-clear]");
  let state = { h: 0, s: 0, v: 1, a: 1 }, pointerId = null, active = null;

  function color() { return hsvToHex(state.h, state.s, state.v); }
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
  }
  function paint(commit = false) {
    sync();
    active?.onChange?.({ color: color(), opacity: state.a }, commit);
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
      chip.addEventListener("click", () => { state = { ...hexToHsv(saved), a: state.a }; paint(true); });
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
  clearAlpha.addEventListener("click", () => { if (active?.allowOpacity) { state.a = 0; paint(true); } });
  $("[data-color-save]").addEventListener("click", () => { saveColor(color()); drawSaved(); });
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
    if (dialog.open && !dialog.contains(event.target) && !active?.trigger?.contains(event.target)) dialog.close();
  });
  dialog.addEventListener("close", () => { active = null; });
  drawSaved();

  return {
    dialog,
    open({ trigger, color: currentColor, opacity = 1, allowOpacity = false, label = "Цвет", onChange }) {
      const parsed = parseColor(currentColor, documentRef);
      state = { ...hexToHsv(parsed.color), a: allowOpacity ? clamp(Number(opacity ?? parsed.opacity), 0, 1) : 1 };
      active = { trigger, allowOpacity, onChange };
      title.textContent = label;
      dialog.setAttribute("aria-label", label);
      alphaRow.hidden = !allowOpacity;
      alpha.hidden = !allowOpacity;
      alphaValue.hidden = !allowOpacity;
      clearAlpha.hidden = !allowOpacity;
      drawSaved(); sync();
      if (!dialog.open) dialog.show();
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
      picker.open({
        trigger, color: current.color, opacity: allowOpacity ? (input.dataset.colorOpacity ?? current.opacity) : 1,
        allowOpacity, label: input.dataset.colorLabel || label,
        onChange: ({ color, opacity }, commit) => {
          input.value = color;
          input.dataset.colorOpacity = String(opacity);
          input.dataset.colorValue = allowOpacity ? formatColor(color, opacity) : color;
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
