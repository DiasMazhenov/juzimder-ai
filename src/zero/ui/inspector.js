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
    const label = original?.type === "vector" && key === "color" ? "Цвет иконки" : LABELS[key] || key,
      inputValue = type === "color" && !/^#[a-f\d]{6}$/i.test(String(value)) ? "#ffffff" : value;
    return `<label class="${overridden ? "zero-overridden" : ""}"><span>${label}${mode !== "desktop" ? (overridden ? " •" : " ↳") : ""}</span>${options ? `<select data-section="${section}" data-key="${key}">${options.map((v) => `<option value="${esc(v)}" ${String(value) === String(v) ? "selected" : ""}>${esc(v)}</option>`).join("")}</select>` : `<input data-section="${section}" data-key="${key}" type="${type}" value="${esc(inputValue)}" ${min !== null ? `min="${min}"` : ""} ${max !== null ? `max="${max}"` : ""} step="any">`}</label>`;
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
        )}<label>Масштаб Grid Container<select data-board="scaleGrid"><option value="fixed" ${(b.scaleGrid || "fixed") === "fixed" ? "selected" : ""}>Фиксированный</option><option value="autoscale" ${b.scaleGrid === "autoscale" ? "selected" : ""}>Autoscale к ширине окна</option></select></label><p class="zero-help">Autoscale пропорционально масштабирует Grid-элементы и высоту секции. Window Container сохраняет размеры. Настройка действует только на этом режиме.</p><h2>Фон секции</h2><label>Цвет фона<input type="color" data-board="background" value="${b.background === "transparent" ? "#ffffff" : b.background}"></label><div class="zero-background-image">${b.backgroundImage ? `<img src="${esc(b.backgroundImage)}" alt="" loading="lazy"><span>${esc(b.backgroundImageName || "Фоновое изображение выбрано")}</span>` : '<p class="zero-help">Изображение не выбрано</p>'}<div><button type="button" data-action="background-image">Выбрать из библиотеки сайта</button>${b.backgroundImage ? '<button type="button" data-action="background-remove" aria-label="Убрать изображение фона">Убрать</button>' : ""}</div></div><label>Поведение<select data-board="backgroundSize"><option value="cover" ${(b.backgroundSize || "cover") === "cover" ? "selected" : ""}>Заполнить секцию</option><option value="contain" ${b.backgroundSize === "contain" ? "selected" : ""}>Показать целиком</option><option value="auto" ${b.backgroundSize === "auto" ? "selected" : ""}>Исходный размер</option><option value="100% 100%" ${b.backgroundSize === "100% 100%" ? "selected" : ""}>Растянуть</option></select></label><label>Позиция<select data-board="backgroundPosition">${["center center", "left top", "center top", "right top", "left center", "right center", "left bottom", "center bottom", "right bottom"].map((value) => `<option value="${value}" ${(b.backgroundPosition || "center center") === value ? "selected" : ""}>${({ "center center": "По центру", "left top": "Слева сверху", "center top": "По центру сверху", "right top": "Справа сверху", "left center": "Слева по центру", "right center": "Справа по центру", "left bottom": "Слева снизу", "center bottom": "По центру снизу", "right bottom": "Справа снизу" })[value]}</option>`).join("")}</select></label><div class="zero-background-filter"><p>Фильтр · начало</p><div class="zero-fields"><label>Цвет<input type="color" data-board="filterStartColor" value="${b.filterStartColor || "#000000"}"></label><label>Непрозрачность <output data-board-output="filterStartOpacity">${Math.round((b.filterStartOpacity ?? 0) * 100)}%</output><input type="range" min="0" max="100" step="1" data-board="filterStartOpacity" value="${Math.round((b.filterStartOpacity ?? 0) * 100)}"></label></div><p>Фильтр · конец</p><div class="zero-fields"><label>Цвет<input type="color" data-board="filterEndColor" value="${b.filterEndColor || "#000000"}"></label><label>Непрозрачность <output data-board-output="filterEndOpacity">${Math.round((b.filterEndOpacity ?? 0) * 100)}%</output><input type="range" min="0" max="100" step="1" data-board="filterEndOpacity" value="${Math.round((b.filterEndOpacity ?? 0) * 100)}"></label></div></div><label>Overflow<select data-board="overflow"><option ${b.overflow === "hidden" ? "selected" : ""}>hidden</option><option ${b.overflow === "visible" ? "selected" : ""}>visible</option></select></label>${mode !== "desktop" ? '<button data-action="adapt">Первичная адаптация</button><p class="zero-help">Только элементы без overrides. Абсолютная геометрия сохраняется.</p>' : ""}<button data-action="clear-guides">Удалить направляющие</button>`;
    }
    const e = resolve(el, mode),
      g = e.geometry,
      s = e.style;
  return `<p class="zero-eyebrow">${items.length > 1 ? `ВЫБРАНО ${items.length}` : esc(el.type).toUpperCase()} · ${MODE_NAMES[mode]}</p><label>Название<input data-element="name" value="${esc(el.name)}" maxlength="120"></label><h2>Геометрия · px</h2><div class="zero-fields">${GEOMETRY.map((key) => field("geometry", key, g[key])).join("")}</div><label>Контейнер<select data-container><option value="grid" ${g.container === "grid" ? "selected" : ""}>Grid Container</option><option value="window" ${g.container === "window" ? "selected" : ""}>Window Container</option></select></label><span class="zero-help">Привязка · положение сохраняется</span><div class="zero-anchors">${[0, 0.5, 1].map((y) => [0, 0.5, 1].map((x) => `<button data-anchor="${x},${y}" aria-label="Привязка ${x},${y}" aria-pressed="${g.anchorX === x && g.anchorY === y}">•</button>`).join("")).join("")}</div><div class="zero-fields">${["originX", "originY"].map((k) => field("geometry", k, g[k], { options: [0, 0.5, 1] })).join("")}</div><h2>Оформление</h2><div class="zero-fields">${["fill", "color", "stroke"].map((k) => field("style", k, s[k] || "#ffffff", { type: "color" })).join("")}${["strokeWidth", "radius", "opacity", "shadowBlur", "shadowX", "shadowY"].map((k) => field("style", k, s[k] ?? (k === "opacity" ? 1 : 0))).join("")}${field("style", "shadowColor", s.shadowColor || "#000000", { type: "color" })}</div>${["text", "button"].includes(el.type) ? `<h2>Текст</h2><label>Содержимое<textarea data-element="content" rows="3">${esc(e.content || "")}</textarea></label><p class="zero-help">Двойной клик на холсте — rich text; Cmd/Ctrl+B/I/U.</p><label>Тег<select data-element="tag">${["p", "h1", "h2", "h3", "h4", "h5", "h6"].map((tag) => `<option ${e.tag === tag ? "selected" : ""}>${tag}</option>`).join("")}</select></label>${field("style", "fontFamily", s.fontFamily || "Arial, sans-serif", { options: ["Arial, sans-serif", "system-ui, sans-serif", "Georgia, serif", project.settings?.designTokens?.headingFontFamily || "Arial, sans-serif"] })}<div class="zero-fields">${["fontSize", "fontWeight", "lineHeight", "letterSpacing"].map((k) => field("style", k, s[k] ?? 0)).join("")}${field("style", "textAlign", s.textAlign || "left", { options: ["left", "center", "right", "justify"] })}${field("style", "textDecoration", s.textDecoration || "none", { options: ["none", "underline", "line-through"] })}</div><label>Автовысота<input type="checkbox" data-auto-height ${s.autoHeight ? "checked" : ""}></label>` : ""}${el.type === "image" ? `<h2>Фото</h2><label>Сохранять пропорции<input type="checkbox" data-keep-aspect ${el.keepAspect ? "checked" : ""}></label><button data-action="replace-image">Выбрать из библиотеки</button><label>Alt<input data-element="alt" value="${esc(e.alt || "")}"></label>${field("style", "objectFit", s.objectFit || "cover", { options: ["cover", "contain", "fill", "none"] })}${field("style", "objectPosition", s.objectPosition || "center", { options: ["center", "top", "bottom", "left", "right"] })}` : ""}${["button", "image"].includes(el.type) ? `<h2>Ссылка</h2><input aria-label="Ссылка элемента" data-element="href" value="${esc(e.href || "")}" placeholder="https:// или #якорь"><label>Окно<select data-element="target"><option value="_self" ${e.target !== "_blank" ? "selected" : ""}>Текущее</option><option value="_blank" ${e.target === "_blank" ? "selected" : ""}>Новое</option></select></label>${field("style", "hoverFill", s.hoverFill || s.fill || "#245b46", { type: "color" })}${field("style", "hoverColor", s.hoverColor || s.color || "#ffffff", { type: "color" })}` : ""}${el.type === "html" ? `<h2>Код · изолированная песочница</h2><label>HTML<textarea class="zero-code-input" data-code="html" rows="7" maxlength="32768" spellcheck="false">${esc(e.code?.html || "")}</textarea></label><label>CSS<textarea class="zero-code-input" data-code="css" rows="5" maxlength="16384" spellcheck="false">${esc(e.code?.css || "")}</textarea></label><label>JavaScript<textarea class="zero-code-input" data-code="js" rows="5" maxlength="16384" spellcheck="false">${esc(e.code?.js || "")}</textarea></label><p class="zero-help">В редакторе JS запускается только в «Просмотре». В сохранённой и опубликованной странице он также выполняется в отдельном iframe. Сетевые API и внешние ресурсы заблокированы; скрипт может изменить содержимое только своего iframe.</p>` : ""}<h2>Анимация</h2><label>Эффект<select data-animation="effect"><option value="none" ${!el.animation ? "selected" : ""}>Без анимации</option><option value="fade" ${el.animation?.effect === "fade" ? "selected" : ""}>Появление</option><option value="move" ${el.animation?.effect === "move" ? "selected" : ""}>Смещение</option><option value="scale" ${el.animation?.effect === "scale" ? "selected" : ""}>Масштаб</option></select></label><div class="zero-fields"><label>Длительность · мс<input type="number" data-animation="duration" min="100" max="3000" step="50" value="${el.animation?.duration ?? 700}"></label><label>Задержка · мс<input type="number" data-animation="delay" min="0" max="5000" step="50" value="${el.animation?.delay ?? 0}"></label></div><label>Плавность<select data-animation="easing">${["ease", "linear", "ease-in", "ease-out", "ease-in-out"].map((v) => `<option value="${v}" ${(el.animation?.easing || "ease") === v ? "selected" : ""}>${v}</option>`).join("")}</select></label><p class="zero-help">Запуск по загрузке страницы. В редакторе проигрывается только в «Просмотре»; уважает системную настройку сокращения движения.</p><h2>Действия</h2><div class="zero-actions">${[
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
  const backgroundHex = /^#[a-f\d]{6}$/i.test(board.background || "") ? board.background : "#ffffff",
    backgroundOpacity = Math.round((board.background === "transparent" ? 0 : (board.backgroundOpacity ?? 1)) * 100),
    colorControl = `<div class="zero-background-color"><span>Цвет фона</span><button type="button" class="zero-background-color-trigger" data-action="background-color" aria-haspopup="dialog" aria-label="Настроить цвет фона"><span class="zero-background-swatch" style="background-color:${esc(backgroundHex)};opacity:${backgroundOpacity / 100}"></span><span>${esc(backgroundHex.toUpperCase())}</span></button><output>${backgroundOpacity}%</output></div>`,
    markup = inspectorMarkup().replace(/<label>Цвет фона<input type="color" data-board="background" value="[^"]+"><\/label>/, colorControl);
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
  if (selected?.type === "form") {
    const fields = (selected.formFields || []).map((field) =>
      `<fieldset class="zero-form-field-editor"><legend>${esc(field.label)}</legend><label>Подпись<input data-form-field="${esc(field.id)}" data-form-prop="label" value="${esc(field.label)}" maxlength="80"></label><label>Тип поля<select data-form-field="${esc(field.id)}" data-form-prop="type">${[["text", "Текст"], ["email", "Email"], ["tel", "Телефон"], ["textarea", "Большой текст"]].map(([value, label]) => `<option value="${value}" ${field.type === value ? "selected" : ""}>${label}</option>`).join("")}</select></label><label>Подсказка<input data-form-field="${esc(field.id)}" data-form-prop="placeholder" value="${esc(field.placeholder || "")}" maxlength="120"></label><label class="zero-form-required">Обязательное<input type="checkbox" data-form-field="${esc(field.id)}" data-form-prop="required" ${field.required ? "checked" : ""}></label><button type="button" data-form-remove="${esc(field.id)}" ${selected.formFields.length <= 1 ? "disabled" : ""}>Удалить поле</button></fieldset>`
    ).join("");
    return markup.replace(
      "<h2>Действия</h2>",
      `<h2>Поля макета</h2><div class="zero-form-field-list">${fields}</div><button type="button" data-form-action="add" ${selected.formFields.length >= 8 ? "disabled" : ""}>Добавить поле</button><label>Текст кнопки<input data-element="submitLabel" value="${esc(selected.submitLabel || "Отправить")}" maxlength="60"></label><p class="zero-help">Данные не собираются и никуда не отправляются. Обработчик формы можно подключить отдельно.</p><h2>Действия</h2>`,
    );
  }
  if (selected?.type === "gallery") {
    const options = selected.galleryOptions || { columns: 3, gap: 16, objectFit: "cover" };
    const images = (selected.galleryItems || []).map((item, index) =>
      `<fieldset class="zero-gallery-item-editor"><legend>Фото ${index + 1}</legend><img src="${esc(item.src)}" alt=""><label>Alt-текст<input data-gallery-item="${esc(item.id)}" data-gallery-prop="alt" value="${esc(item.alt || "")}" maxlength="300"></label><div class="zero-gallery-item-actions"><button type="button" data-gallery-pick="${esc(item.id)}">Заменить</button><button type="button" data-gallery-remove="${esc(item.id)}">Удалить</button></div></fieldset>`,
    ).join("");
    return markup.replace(
      "<h2>Действия</h2>",
      `<h2>Макет галереи</h2><div class="zero-fields"><label>Колонки<input type="number" data-gallery-option="columns" min="1" max="6" step="1" value="${esc(options.columns)}"></label><label>Расстояние · px<input type="number" data-gallery-option="gap" min="0" max="80" step="1" value="${esc(options.gap)}"></label></div><label>Отображение фото<select data-gallery-option="objectFit">${[["cover", "Заполнить"], ["contain", "Вписать"]].map(([value, label]) => `<option value="${value}" ${options.objectFit === value ? "selected" : ""}>${label}</option>`).join("")}</select></label><p class="zero-help">На узких режимах число колонок автоматически уменьшается. Скругление настраивается выше в «Оформлении».</p><h2>Фотографии · ${selected.galleryItems.length}/12</h2><div class="zero-gallery-item-list">${images || '<p class="zero-help">Добавьте изображения из библиотеки проекта.</p>'}</div><button type="button" data-gallery-add="${esc(selected.id)}" ${selected.galleryItems.length >= 12 ? "disabled" : ""}>Добавить фото из библиотеки</button><h2>Действия</h2>`,
    );
  }
  return markup;
}
