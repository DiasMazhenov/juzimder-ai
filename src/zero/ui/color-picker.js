const clamp = (value, min, max) => Math.max(min, Math.min(max, value));
const HEX = /^#[a-f\d]{6}$/i;

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

function savedColors() {
  try {
    const value = JSON.parse(localStorage.getItem("juzimder-zero-saved-colors") || "[]");
    return Array.isArray(value) ? [...new Set(value.filter((color) => typeof color === "string" && HEX.test(color)))].slice(0, 12) : [];
  } catch {
    return [];
  }
}

export function installZeroColorPicker({ host, read, apply }) {
  const dialog = document.createElement("dialog");
  dialog.className = "zero-color-dialog";
  dialog.setAttribute("aria-label", "Цвет фона секции");
  dialog.innerHTML = `<header class="zero-color-header"><span>Сплошной цвет</span><button type="button" data-color-close aria-label="Закрыть палитру">×</button></header>
    <div class="zero-color-plane" role="group" aria-label="Выбор насыщенности и яркости" tabindex="0"><span class="zero-color-plane-marker" aria-hidden="true"></span></div>
    <label class="zero-color-hue">Тон<input type="range" min="0" max="359" step="1" data-color-hue aria-label="Тон цвета"></label>
    <label class="zero-color-alpha">Прозрачность<input type="range" min="0" max="100" step="1" data-color-alpha aria-label="Прозрачность цвета"></label>
    <div class="zero-color-hex-row"><label>HEX<input type="text" data-color-hex maxlength="7" spellcheck="false" aria-label="HEX цвета фона"></label><output data-color-alpha-value>100</output><button type="button" data-color-clear aria-label="Сделать фон прозрачным" title="Сделать фон прозрачным">×</button></div>
    <section class="zero-saved-colors"><header><span>Сохранённые цвета</span><button type="button" data-color-save aria-label="Сохранить текущий цвет" title="Сохранить текущий цвет">+</button></header><div data-color-swatches></div></section>`;
  host.append(dialog);
  const $ = (selector) => dialog.querySelector(selector),
    plane = $(".zero-color-plane"),
    marker = $(".zero-color-plane-marker"),
    hue = $("[data-color-hue]"),
    alpha = $("[data-color-alpha]"),
    hex = $("[data-color-hex]"),
    alphaValue = $("[data-color-alpha-value]"),
    swatches = $("[data-color-swatches]");
  let state = { h: 0, s: 0, v: 1, a: 1 }, pointerId = null, anchor = null;

  function color() { return hsvToHex(state.h, state.s, state.v); }
  function sync() {
    const value = color();
    plane.style.setProperty("--zero-color-hue", String(state.h));
    marker.style.left = `${state.s * 100}%`;
    marker.style.top = `${(1 - state.v) * 100}%`;
    hue.value = String(Math.round(state.h));
    alpha.value = String(Math.round(state.a * 100));
    alpha.style.setProperty("--zero-color-alpha", value);
    hex.value = value.toUpperCase();
    alphaValue.value = String(Math.round(state.a * 100));
    alphaValue.textContent = alphaValue.value;
  }
  function paint(commit = false) {
    const value = color();
    sync();
    apply({ color: value, opacity: state.a }, commit);
  }
  function drawSaved() {
    swatches.replaceChildren();
    for (const saved of savedColors()) {
      const chip = document.createElement("button");
      chip.type = "button";
      chip.className = "zero-color-swatch";
      chip.style.backgroundColor = saved;
      chip.title = saved.toUpperCase();
      chip.setAttribute("aria-label", `Выбрать цвет ${saved.toUpperCase()}`);
      chip.addEventListener("click", () => {
        state = { ...hexToHsv(saved), a: state.a };
        paint(true);
      });
      swatches.append(chip);
    }
    if (!swatches.childElementCount) {
      const empty = document.createElement("span");
      empty.className = "zero-color-empty";
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
    pointerId = event.pointerId;
    plane.setPointerCapture(pointerId);
    updatePlane(event.clientX, event.clientY);
  });
  plane.addEventListener("pointermove", (event) => {
    if (event.pointerId === pointerId) updatePlane(event.clientX, event.clientY);
  });
  plane.addEventListener("pointerup", (event) => {
    if (event.pointerId !== pointerId) return;
    updatePlane(event.clientX, event.clientY, true);
    pointerId = null;
  });
  plane.addEventListener("pointercancel", () => { pointerId = null; });
  plane.addEventListener("keydown", (event) => {
    const step = event.shiftKey ? 0.1 : 0.01;
    if (!["ArrowLeft", "ArrowRight", "ArrowUp", "ArrowDown"].includes(event.key)) return;
    event.preventDefault();
    if (event.key === "ArrowLeft") state.s -= step;
    if (event.key === "ArrowRight") state.s += step;
    if (event.key === "ArrowDown") state.v -= step;
    if (event.key === "ArrowUp") state.v += step;
    state.s = clamp(state.s, 0, 1);
    state.v = clamp(state.v, 0, 1);
    paint(true);
  });
  hue.addEventListener("input", () => { state.h = Number(hue.value); paint(); });
  hue.addEventListener("change", () => { state.h = Number(hue.value); paint(true); });
  alpha.addEventListener("input", () => { state.a = Number(alpha.value) / 100; paint(); });
  alpha.addEventListener("change", () => { state.a = Number(alpha.value) / 100; paint(true); });
  hex.addEventListener("input", () => {
    if (!HEX.test(hex.value)) return;
    state = { ...hexToHsv(hex.value), a: state.a };
    paint();
  });
  hex.addEventListener("change", () => {
    if (HEX.test(hex.value)) state = { ...hexToHsv(hex.value), a: state.a };
    paint(true);
  });
  $("[data-color-clear]").addEventListener("click", () => { state.a = 0; paint(true); });
  $("[data-color-save]").addEventListener("click", () => {
    const colors = savedColors(), value = color();
    if (!colors.includes(value)) colors.unshift(value);
    try { localStorage.setItem("juzimder-zero-saved-colors", JSON.stringify(colors.slice(0, 12))); } catch {}
    drawSaved();
  });
  if ("EyeDropper" in window) {
    const eyeDropper = document.createElement("button");
    eyeDropper.type = "button";
    eyeDropper.className = "zero-color-eyedropper";
    eyeDropper.textContent = "Пипетка";
    eyeDropper.addEventListener("click", async () => {
      try {
        const result = await new EyeDropper().open();
        if (HEX.test(result.sRGBHex)) state = { ...hexToHsv(result.sRGBHex), a: state.a };
        paint(true);
      } catch { /* User cancelled the native eyedropper. */ }
    });
    $(".zero-color-header").append(eyeDropper);
  }
  dialog.addEventListener("click", (event) => {
    if (event.target === dialog) dialog.close();
  });
  document.addEventListener("pointerdown", (event) => {
    if (dialog.open && !dialog.contains(event.target) && !anchor?.contains(event.target)) dialog.close();
  });
  dialog.addEventListener("close", () => { anchor = null; });
  drawSaved();

  return {
    open(trigger) {
      const current = read(),
        parsed = hexToHsv(current.color === "transparent" ? "#ffffff" : current.color);
      state = { ...parsed, a: current.color === "transparent" ? 0 : clamp(Number(current.opacity ?? 1), 0, 1) };
      anchor = trigger;
      drawSaved();
      sync();
      if (!dialog.open) dialog.show();
      if (trigger) {
        const bounds = trigger.getBoundingClientRect(),
          panel = dialog.getBoundingClientRect(),
          left = clamp(bounds.right - panel.width, 12, window.innerWidth - panel.width - 12),
          top = clamp(bounds.bottom + 8, 12, window.innerHeight - panel.height - 12);
        dialog.style.left = `${left}px`;
        dialog.style.top = `${top}px`;
      }
      hex.focus();
      hex.select();
    },
  };
}
