import { breakpointName, resolve } from "../model/model.js";
import { VECTOR_ICONS } from "../model/vector-icons.js";
import { escapeHTML as esc } from "../render.js";
const MODE_NAMES = {};
const GEOMETRY = ["x", "y", "width", "height", "rotation"];
const LABELS = {
  x: "X",
  y: "Y",
  width: "Ширина",
  height: "Высота",
  rotation: "Поворот",
  fill: "Заливка",
  color: "Цвет текста",
  stroke: "Обводка",
  strokeWidth: "Толщина",
  radius: "Радиус",
  opacity: "Прозрачность",
  fontSize: "Кегль",
  fontWeight: "Начертание",
  lineHeight: "Интерлиньяж",
  letterSpacing: "Трекинг",
  fontFamily: "Шрифт",
  textAlign: "Выравнивание",
  textDecoration: "Оформление",
  objectFit: "Масштаб фото",
  objectPosition: "Позиция фото",
  autoHeight: "Автовысота",
  shadowBlur: "Размытие тени",
  shadowX: "Тень X",
  shadowY: "Тень Y",
  shadowColor: "Цвет тени",
  hoverFill: "Hover заливка",
  hoverColor: "Hover текст",
};

export function renderInspector({ items, mode, board, project }) {
  MODE_NAMES[mode] = breakpointName(mode, board.width);
  function field(
    section,
    key,
    value,
    { type = "number", options = null, min = null, max = null } = {},
  ) {
    const original = items[0]?.element,
      overridden =
        mode !== "desktop" &&
        original &&
        Object.hasOwn(original.overrides?.[mode]?.[section] || {}, key);
    const label = original?.type === "vector" && key === "color" ? "Цвет иконки" : LABELS[key] || key;
    return `<label class="${overridden ? "zero-overridden" : ""}"><span>${label}${mode !== "desktop" ? (overridden ? " •" : " ↳") : ""}</span>${options ? `<select data-section="${section}" data-key="${key}">${options.map((v) => `<option value="${esc(v)}" ${String(value) === String(v) ? "selected" : ""}>${esc(v)}</option>`).join("")}</select>` : `<input data-section="${section}" data-key="${key}" type="${type}" value="${esc(value)}" ${min !== null ? `min="${min}"` : ""} ${max !== null ? `max="${max}"` : ""} step="any">`}</label>`;
  }
  function inspectorMarkup() {
    const el = items[0]?.element;
    if (!el) {
      const b = board;
      return `<p class="zero-eyebrow">АРТБОРД · ${MODE_NAMES[mode]}</p><h2>Настройки секции</h2><p class="zero-help">Grid — область координат, элементы размещаются абсолютно.</p>${[
        ["height", "Высота секции"],
        ["gridWidth", "Ширина Grid"],
        ["margin", "Поля сетки"],
        ["gutter", "Gutter"],
      ]
        .map(
          ([key, label]) =>
            `<label>${label}<input data-board="${key}" type="number" value="${b[key]}" min="${key === "height" ? 40 : 0}" max="20000"></label>`,
        )
        .join(
          "",
        )}<label>Масштаб Grid Container<select data-board="scaleGrid"><option value="fixed" ${(b.scaleGrid || "fixed") === "fixed" ? "selected" : ""}>Фиксированный</option><option value="autoscale" ${b.scaleGrid === "autoscale" ? "selected" : ""}>Autoscale к ширине окна</option></select></label><p class="zero-help">Autoscale пропорционально масштабирует Grid-элементы и высоту секции. Window Container сохраняет размеры. Настройка действует только на этом режиме.</p><label>Фон<input type="color" data-board="background" value="${b.background === "transparent" ? "#ffffff" : b.background}"></label><label>Overflow<select data-board="overflow"><option ${b.overflow === "hidden" ? "selected" : ""}>hidden</option><option ${b.overflow === "visible" ? "selected" : ""}>visible</option></select></label>${mode !== "desktop" ? '<button data-action="adapt">Первичная адаптация</button><p class="zero-help">Только элементы без overrides. Абсолютная геометрия сохраняется.</p>' : ""}<button data-action="clear-guides">Удалить направляющие</button>`;
    }
    const e = resolve(el, mode),
      g = e.geometry,
      s = e.style;
  return `<p class="zero-eyebrow">${items.length > 1 ? `ВЫБРАНО ${items.length}` : esc(el.type).toUpperCase()} · ${MODE_NAMES[mode]}</p><label>Название<input data-element="name" value="${esc(el.name)}" maxlength="120"></label><h2>Геометрия · px</h2><div class="zero-fields">${GEOMETRY.map((key) => field("geometry", key, g[key])).join("")}</div><label>Контейнер<select data-container><option value="grid" ${g.container === "grid" ? "selected" : ""}>Grid Container</option><option value="window" ${g.container === "window" ? "selected" : ""}>Window Container</option></select></label><span class="zero-help">Привязка · положение сохраняется</span><div class="zero-anchors">${[0, 0.5, 1].map((y) => [0, 0.5, 1].map((x) => `<button data-anchor="${x},${y}" aria-label="Привязка ${x},${y}" aria-pressed="${g.anchorX === x && g.anchorY === y}">•</button>`).join("")).join("")}</div><div class="zero-fields">${["originX", "originY"].map((k) => field("geometry", k, g[k], { options: [0, 0.5, 1] })).join("")}</div><h2>Оформление</h2><div class="zero-fields">${["fill", "color", "stroke"].map((k) => field("style", k, s[k] || "#ffffff", { type: "text" })).join("")}${["strokeWidth", "radius", "opacity", "shadowBlur", "shadowX", "shadowY"].map((k) => field("style", k, s[k] ?? (k === "opacity" ? 1 : 0))).join("")}${field("style", "shadowColor", s.shadowColor || "#000000", { type: "color" })}</div>${["text", "button"].includes(el.type) ? `<h2>Текст</h2><label>Содержимое<textarea data-element="content" rows="3">${esc(e.content || "")}</textarea></label><p class="zero-help">Двойной клик на холсте — rich text; Cmd/Ctrl+B/I/U.</p><label>Тег<select data-element="tag">${["p", "h1", "h2", "h3", "h4", "h5", "h6"].map((tag) => `<option ${e.tag === tag ? "selected" : ""}>${tag}</option>`).join("")}</select></label>${field("style", "fontFamily", s.fontFamily || "Arial, sans-serif", { options: ["Arial, sans-serif", "system-ui, sans-serif", "Georgia, serif", project.settings?.designTokens?.headingFontFamily || "Arial, sans-serif"] })}<div class="zero-fields">${["fontSize", "fontWeight", "lineHeight", "letterSpacing"].map((k) => field("style", k, s[k] ?? 0)).join("")}${field("style", "textAlign", s.textAlign || "left", { options: ["left", "center", "right", "justify"] })}${field("style", "textDecoration", s.textDecoration || "none", { options: ["none", "underline", "line-through"] })}</div><label>Автовысота<input type="checkbox" data-auto-height ${s.autoHeight ? "checked" : ""}></label>` : ""}${el.type === "image" ? `<h2>Фото</h2><label>Сохранять пропорции<input type="checkbox" data-keep-aspect ${el.keepAspect ? "checked" : ""}></label><button data-action="replace-image">Выбрать из библиотеки</button><label>Alt<input data-element="alt" value="${esc(e.alt || "")}"></label>${field("style", "objectFit", s.objectFit || "cover", { options: ["cover", "contain", "fill", "none"] })}${field("style", "objectPosition", s.objectPosition || "center", { options: ["center", "top", "bottom", "left", "right"] })}` : ""}${["button", "image"].includes(el.type) ? `<h2>Ссылка</h2><input aria-label="Ссылка элемента" data-element="href" value="${esc(e.href || "")}" placeholder="https:// или #якорь"><label>Окно<select data-element="target"><option value="_self" ${e.target !== "_blank" ? "selected" : ""}>Текущее</option><option value="_blank" ${e.target === "_blank" ? "selected" : ""}>Новое</option></select></label>${field("style", "hoverFill", s.hoverFill || s.fill || "#245b46", { type: "text" })}${field("style", "hoverColor", s.hoverColor || s.color || "#ffffff", { type: "text" })}` : ""}${el.type === "html" ? `<h2>Код · изолированная песочница</h2><label>HTML<textarea class="zero-code-input" data-code="html" rows="7" maxlength="32768" spellcheck="false">${esc(e.code?.html || "")}</textarea></label><label>CSS<textarea class="zero-code-input" data-code="css" rows="5" maxlength="16384" spellcheck="false">${esc(e.code?.css || "")}</textarea></label><label>JavaScript<textarea class="zero-code-input" data-code="js" rows="5" maxlength="16384" spellcheck="false">${esc(e.code?.js || "")}</textarea></label><p class="zero-help">В редакторе JS запускается только в «Просмотре». В сохранённой и опубликованной странице он также выполняется в отдельном iframe. Сетевые API и внешние ресурсы заблокированы; скрипт может изменить содержимое только своего iframe.</p>` : ""}<h2>Действия</h2><div class="zero-actions">${[
      ["duplicate", "Дублировать"],
      ["delete", "Удалить"],
      ["group", "Группа"],
      ["ungroup", "Разгруппировать"],
      ["forward", "Выше"],
      ["backward", "Ниже"],
      ["front", "На передний"],
      ["back", "На задний"],
      ["align-left", "Левый край"],
      ["align-center", "Центр X"],
      ["align-right", "Правый край"],
      ["align-top", "Верхний край"],
      ["align-middle", "Центр Y"],
      ["align-bottom", "Нижний край"],
      ["distribute-x", "Распределить X"],
      ["distribute-y", "Распределить Y"],
    ]
      .map(([k, label]) => `<button data-action="${k}">${label}</button>`)
      .join(
        "",
      )}</div>${mode !== "desktop" ? '<button data-action="reset-override">Сбросить override элемента</button>' : ""}`;
  }
  const markup = inspectorMarkup();
  const selected = items[0]?.element;
  if (selected?.type === "tooltip") {
    const tooltipInspector = `<h2>Подсказка</h2><label>Текст подсказки<textarea data-element="content" rows="4" maxlength="2000">${esc(selected.content || "")}</textarea></label><p class="zero-help">Показывается при наведении на значок и при фокусе клавиатурой.</p>`;
    return markup.replace(
      "<h2>Действия</h2>",
      `${tooltipInspector}<h2>Действия</h2>`,
    );
  }
  if (selected?.type === "vector") {
    const options = Object.entries(VECTOR_ICONS)
      .map(
        ([name, label]) =>
          `<option value="${esc(name)}" ${selected.icon === name ? "selected" : ""}>${esc(label)}</option>`,
      )
      .join("");
    return markup.replace(
      "<h2>Действия</h2>",
      `<h2>Вектор</h2><label>Иконка<select data-element="icon">${options}</select></label><p class="zero-help">Встроенная Tabler Outline иконка. Произвольный SVG-код не принимается.</p><h2>Действия</h2>`,
    );
  }
  if (selected?.type === "video") {
    return markup.replace(
      "<h2>Действия</h2>",
      `<h2>Видео</h2><label>Ссылка на видео<input data-element="videoUrl" type="url" value="${esc(selected.videoUrl || "")}" placeholder="https://youtu.be/..." maxlength="2048"></label><p class="zero-help">Поддерживаются только ссылки YouTube и Vimeo. Embed-код и другие сайты запрещены.</p><h2>Действия</h2>`,
    );
  }
  return markup;
}
