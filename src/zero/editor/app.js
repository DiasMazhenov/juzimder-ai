import { renderInspector } from "../ui/inspector.js";
import {
  groupElements,
  layerDropIntent,
  moveLayer,
  selectionRoots,
  cloneElementForPaste,
  ungroupElements,
} from "../model/operations.js";
import {
  worldMatrix,
  documentBounds,
  rotatedBounds,
  scaleGroupChildren,
} from "../model/transforms.js";
import {
  breakpointEntries,
  breakpointName,
  validateBreakpoints,
  addBreakpoint,
  removeBreakpoint,
  suggestBreakpointWidth,
  copy,
  createElement,
  resolve,
  setValue,
  findElement,
  flatten,
  rect,
  anchorGeometry,
  screenDelta,
  zoomCameraAtPoint,
  resizeGeometry,
  rotatePoint,
  geometryCSS,
  appearanceCSS,
  gridMetrics,
  adapt,
  History,
} from "../model/model.js";
import { renderZero, escapeHTML as esc } from "../render.js";
import { renderZeroElementMenu } from "../ui/element-menu.js";
import { tablerIcon } from "../../editor-icons.js";
import { installImagePicker } from "../../image-picker.js";
import { installZeroColorPicker } from "../ui/color-picker.js";
import packageInfo from "../../../package.json";
import "../../ui-theme.css";
import "../zero.css";
import "../../ui-overrides.css";
function breakpointIcon(width) {
  if (width <= 430) return { name: "device-mobile", orientation: "portrait" };
  if (width <= 560) return { name: "device-mobile", orientation: "rotated" };
  if (width <= 820) return { name: "device-tablet", orientation: "portrait" };
  if (width <= 1024) return { name: "device-tablet", orientation: "rotated" };
  return { name: "device-desktop", orientation: "landscape" };
}
function gridRgba(value, alpha) {
  const match = /^#([a-f\d]{6})$/i.exec(value || "#86c232"),
    hex = match?.[1] || "86c232",
    channels = [0, 2, 4].map((index) => Number.parseInt(hex.slice(index, index + 2), 16));
  return `rgba(${channels.join(",")},${Math.max(0, Math.min(1, alpha))})`;
}
export async function openZeroEditor({
  projectId,
  pageId,
  blockId,
  api,
  session,
  app,
}) {
  let project = await api(`/projects/${projectId}`),
    data,
    aiCandidateData = null,
    baseData,
    version = project.version,
    mode = "desktop",
    selection = new Set(),
    zoom = 1,
    snap = true,
    grid = true,
    guides = true,
    rulers = true,
    dirty = false,
    saving = false,
    saveRetries = 0,
    conflict = false,
    timer,
    space = false,
    touchPointers = new Map(),
    clipboard = [],
    gesture = null,
    lastTextPointer = null,
    frame = 0,
    fieldTimer,
    pendingField = null,
    breakpointDraft = null,
    gridSettingsScope = "current",
    smartLines = [],
    preview = false,
    destroyed = false;
  const locate = (p) => {
    const page = p.document.data.pages.find((page) => page.id === pageId);
    let result;
    const walk = (n) => {
      if (n.type === "zero-block" && n.attributes?.id === blockId) result = n;
      for (const c of n.components || []) walk(c);
    };
    if (page) walk(page.frames[0].component);
    if (!result) throw new Error("Zero Block не найден на странице.");
    return result;
  };
  const block = locate(project);
  data = copy(block.zeroData);
  baseData = copy(data);
  const history = new History(data);
  const channel = new BroadcastChannel(`jz-zero-${projectId}`),
    $ = (s) => app.querySelector(s);
  app.innerHTML = `<main class="zero-app"><header class="zero-top"><a class="zero-brand" href="/projects/${projectId}/pages/${pageId}/edit" >Juzimder <b>ZERO</b></a><span class="zero-title">${esc(block.name || "Zero Block")}</span><span class="zero-version">${packageInfo.productVersion}</span><button data-action="add-menu" aria-label="Добавить элемент" title="Добавить элемент">${tablerIcon("plus")}</button><button data-action="layers" aria-label="Слои" title="Слои">${tablerIcon("stack-2")}</button><button class="zero-mobile-inspector-toggle" data-action="inspector" aria-label="Инспектор" title="Инспектор">${tablerIcon("settings")}</button><button data-action="grid" aria-pressed="true" aria-label="Сетка">${tablerIcon("layout-grid")}</button><button data-action="undo" aria-label="Отменить" title="Отменить">${tablerIcon("arrow-back-up")}</button><button data-action="redo" aria-label="Повторить" title="Повторить">${tablerIcon("arrow-forward-up")}</button><div class="zero-modes" aria-label="Размеры экрана"></div><button data-action="breakpoints" aria-expanded="false" aria-controls="zero-breakpoint-manager" aria-label="Настроить брейкпоинты" title="Настроить брейкпоинты">${tablerIcon("settings")}</button><section id="zero-breakpoint-manager" class="zero-breakpoint-manager" hidden aria-label="Настройки брейкпоинтов"></section><button data-action="preview" aria-label="Просмотр" title="Просмотр">${tablerIcon("eye")}<span>Просмотр</span></button><button class="primary" data-action="save" aria-label="Сохранить" title="Сохранить">${tablerIcon("device-floppy")}<span>Сохранить</span></button><button data-action="close" aria-label="Закрыть" title="Закрыть">${tablerIcon("x")}<span>Закрыть</span></button><button data-action="preferences-menu" aria-label="Дополнительные настройки Zero Block" title="Дополнительные настройки" aria-haspopup="menu" aria-expanded="false" aria-controls="zero-preferences-menu">${tablerIcon("dots")}</button><menu id="zero-preferences-menu" class="zero-preferences-menu" role="menu" aria-label="Меню Zero Block" hidden><button type="button" role="menuitem" data-action="undo">${tablerIcon("arrow-back-up")}<span>Отменить</span><kbd>⌘ Z</kbd></button><button type="button" role="menuitem" data-action="redo">${tablerIcon("arrow-forward-up")}<span>Повторить</span><kbd>⌘ ⇧ Z</kbd></button><hr><button type="button" role="menuitem" data-action="zoom-in">${tablerIcon("plus")}<span>Увеличить</span><kbd>⌘ +</kbd></button><button type="button" role="menuitem" data-action="zoom-out">${tablerIcon("minus")}<span>Уменьшить</span><kbd>⌘ −</kbd></button><hr><button type="button" role="menuitem" data-action="artboard-settings">${tablerIcon("settings")}<span>Настройки артборда</span></button><button type="button" role="menuitem" data-action="grid-settings">${tablerIcon("layout-grid")}<span>Параметры сетки</span></button><hr><button type="button" role="menuitemcheckbox" data-pref-toggle="layers" data-action="layers" aria-checked="true">${tablerIcon("stack-2")}<span>Слои</span><kbd>⌘ L</kbd></button><button type="button" role="menuitemcheckbox" data-pref-toggle="grid" data-action="grid" aria-checked="true">${tablerIcon("layout-grid")}<span>Сетка</span><kbd>G</kbd></button><button type="button" role="menuitemcheckbox" data-pref-toggle="rulers" data-action="rulers" aria-checked="true">${tablerIcon("baseline")}<span>Линейки</span><kbd>⇧ R</kbd></button><button type="button" role="menuitemcheckbox" data-pref-toggle="snap" data-action="snap" aria-checked="true">${tablerIcon("arrows-move")}<span>Привязка</span></button><button type="button" role="menuitemcheckbox" data-pref-toggle="guides" data-action="guides" aria-checked="true">${tablerIcon("selector")}<span>Направляющие</span></button></menu></header><div class="zero-main"><button class="zero-panel-backdrop" data-action="panel-close" aria-label="Закрыть панель" hidden></button><aside class="zero-left"><section class="zero-add"><h2>Добавить элемент</h2>${[
    ["text", "Текст"],
    ["image", "Фото"],
    ["rectangle", "Прямоугольник"],
    ["ellipse", "Эллипс"],
    ["line", "Линия"],
    ["button", "Кнопка"],
  ]
    .map(([type, label]) => `<button data-add="${type}">${label}</button>`)
    .join(
      "",
    )}</section><section class="zero-layer-panel"><h2>Слои</h2><div id="zero-layers"></div></section></aside><div class="zero-left-resizer" role="separator" aria-orientation="vertical" aria-valuemin="140" aria-valuenow="190" aria-valuemax="480" aria-label="Ширина панели слоёв" tabindex="0" title="Перетащите, чтобы изменить ширину"></div><section class="zero-workspace" tabindex="0" aria-label="Холст Zero Block"><div class="zero-stage"><div class="zero-plane"><div class="zero-bleed-surface" aria-hidden="true"></div><div class="zero-ruler-x"></div><div class="zero-ruler-y"></div><div id="zero-canvas"></div><div class="zero-grid"></div><div class="zero-guides"></div><div class="zero-selection-overlays"></div><div class="zero-marquee" hidden></div></div></div></section><aside class="zero-inspector"><div id="zero-inspector"></div></aside></div><footer class="zero-bottom"><label><span>Zoom</span><select id="zero-zoom"><option value="0.5">50%</option><option value="0.75">75%</option><option value="1" selected>100%</option><option value="1.5">150%</option><option value="2">200%</select></label>${[
    ["snap", "Snap"],
    ["grid", "Grid"],
    ["guides", "Guides"],
    ["rulers", "Rulers"],
  ]
    .map(
      ([key, label]) =>
        `<button data-action="${key}" aria-label="${label}" title="${label}" aria-pressed="true">${tablerIcon({ snap: "arrows-move", grid: "layout-grid", guides: "selector", rulers: "baseline" }[key])}<span>${label}</span></button>`,
    )
    .join(
      "",
    )}<button data-action="fit" aria-label="Вписать" title="Вписать">${tablerIcon("layout-grid")}<span>Вписать</span></button><span id="zero-status" role="status">Ревизия ${version}</span><button data-action="rebase" hidden>Проверить конфликт</button><button data-action="export" aria-label="Скачать JSON" title="Скачать JSON">${tablerIcon("download")}<span>Скачать JSON</span></button></footer><dialog id="zero-assets"><header><h2>Библиотека сайта</h2><button data-action="assets-close">Закрыть</button></header><label class="zero-upload">Загрузить с компьютера<input id="zero-upload" type="file" accept="image/png,image/jpeg,image/webp,image/gif"></label><input id="zero-asset-search" placeholder="Найти фото"><div class="zero-asset-list"></div><p id="zero-asset-status" role="status"></p></dialog><dialog id="zero-grid-settings" aria-labelledby="zero-grid-settings-title"><header><div><p class="zero-eyebrow">НАСТРОЙКИ АРТБОРДА</p><h2 id="zero-grid-settings-title">Сетка</h2></div><button type="button" data-action="grid-settings-close" aria-label="Закрыть настройки сетки">${tablerIcon("x")}</button></header><p class="zero-help">Сетка служит ориентиром в редакторе и не отображается в превью или на сайте.</p><div class="zero-grid-settings-fields"><label>Ширина Grid · px<input type="number" data-grid-setting="gridWidth" min="320" max="2400" step="1"></label><label>Колонки<input type="number" data-grid-setting="gridColumns" min="1" max="24" step="1"></label><label>Ширина колонки · px<output id="zero-grid-column-width">—</output></label><label>Межколонник · px<input type="number" data-grid-setting="gutter" min="0" max="200" step="1"></label><label>Поля · px<input type="number" data-grid-setting="margin" min="0" max="500" step="1"></label></div><div class="zero-grid-color-row"><span>Цвет сетки</span><button type="button" id="zero-grid-color-trigger" data-action="grid-color" aria-haspopup="dialog"><span class="zero-grid-color-swatch"></span><span id="zero-grid-color-value"></span></button></div><label class="zero-grid-range">Заливка колонок <output data-grid-output="gridOpacity"></output><input type="range" data-grid-setting="gridOpacity" min="0" max="100" step="1"></label><label class="zero-grid-range">Линии колонок <output data-grid-output="gridBorderOpacity"></output><input type="range" data-grid-setting="gridBorderOpacity" min="0" max="100" step="1"></label><label>Охват<select id="zero-grid-setting-scope"><option value="current">Активный брейкпоинт</option><option value="all">Все брейкпоинты Zero Block</option></select></label><p class="zero-help">При выборе всех брейкпоинтов значения сетки применятся ко всему этому Zero Block.</p><div class="zero-grid-settings-actions"><button type="button" data-action="grid-reset">Сбросить сетку</button><button type="button" class="primary" data-action="grid-settings-close">Готово</button></div></dialog><dialog id="zero-ai-prompt"><header><h2>AI-композиция</h2><button type="button" data-action="ai-prompt-close" aria-label="Закрыть">${tablerIcon("x")}</button></header><p>Опишите задачу и визуальную идею. AI подготовит редактируемые слои и при уместности подберёт фото из Wikimedia Commons.</p><label>Описание<textarea id="zero-ai-prompt-text" rows="5" maxlength="2000" placeholder="Например: первый экран клиники с заголовком слева, фотографией справа и кнопкой записи"></textarea></label><p class="zero-help">Сначала появится предпросмотр. До нажатия «Применить вариант» текущая композиция не меняется. Отмену после применения можно сделать через Undo.</p><p id="zero-ai-prompt-status" role="status"></p><button type="button" class="primary" data-action="ai-generate">${tablerIcon("sparkles")} Сгенерировать вариант</button></dialog><dialog id="zero-ai-candidate"><header><h2>Предпросмотр AI-композиции</h2><button type="button" data-action="ai-candidate-cancel" aria-label="Закрыть">${tablerIcon("x")}</button></header><div id="zero-ai-preview-controls" class="zero-ai-preview-controls" role="group" aria-label="Размер предпросмотра"></div><div id="zero-ai-preview"></div><p id="zero-ai-candidate-status" role="status"></p><div class="zero-ai-candidate-actions"><button type="button" data-action="ai-candidate-cancel">Отменить</button><button type="button" class="primary" data-action="ai-candidate-apply">Применить вариант</button></div></dialog><menu id="zero-context" hidden>${[
    ["copy", "Копировать"],
    ["paste", "Вставить"],
    ["duplicate", "Дублировать"],
    ["group", "Сгруппировать"],
    ["ungroup", "Разгруппировать"],
    ["front", "На передний план"],
    ["back", "На задний план"],
    ["delete", "Удалить"],
  ]
    .map(([key, label]) => `<button data-action="${key}">${label}</button>`)
    .join("")}</menu></main>`;
  const zeroRoot = $(".zero-app"),
    workspace = $(".zero-workspace"),
    canvas = $("#zero-canvas"),
    plane = $(".zero-plane"),
    bleedSurface = $(".zero-bleed-surface"),
    status = (message) => {
      $("#zero-status").textContent = message;
    };
  const imageDialog = document.createElement("dialog");
  imageDialog.id = "image-dialog";
  zeroRoot.append(imageDialog);
  const backgroundImagePicker = installImagePicker({
    projectId,
    api,
    csrf: () => session.csrf,
    allowExternalUrl: false,
    notify: (message) => status(message),
    insert: (src, description, assetName) => {
      board().backgroundImage = src;
      board().backgroundImageName = assetName || description;
      changed();
    },
  });
  const backgroundColorPicker = installZeroColorPicker({
    host: zeroRoot,
    read: () => ({ color: board().background, opacity: board().backgroundOpacity, fill: board().backgroundFill }),
    apply: ({ color, opacity, fill }, commit) => {
      if (pendingField && pendingField !== "board:background") flushField();
      pendingField = "board:background";
      if (fill) board().backgroundFill = fill;
      else {
        delete board().backgroundFill;
        board().background = color;
        board().backgroundOpacity = opacity;
      }
      if (commit) {
        clearTimeout(fieldTimer);
        changed();
      } else {
        dirty = true;
        clearTimeout(timer);
        drawCanvas();
        status(conflict ? "Конфликт · локальные изменения сохранены в окне" : "Изменения не сохранены");
        clearTimeout(fieldTimer);
        fieldTimer = setTimeout(() => {
          flushField();
          if (dirty && !conflict) timer = setTimeout(save, 800);
        }, 500);
      }
    },
  });
  const gridColorPicker = installZeroColorPicker({
    read: () => ({
      color: gridSettingValue("gridColor"),
      opacity: gridSettingValue("gridOpacity"),
      fill: null,
    }),
    apply: applyGridColor,
    label: "Цвет сетки",
    allowFill: false,
  });
  const addTrigger = $('[data-action="add-menu"]'),
    layerToggle = $('[data-action="layers"]'),
    mobilePanelQuery = matchMedia("(max-width: 720px)");
  const leftPanel = $(".zero-left"),
    leftResizer = $(".zero-left-resizer"),
    inspectorPanel = $(".zero-inspector"),
    minSidebarWidth = 140,
    sidebarPreferenceKey = "juzimder-zero-sidebar-width";
  let rememberSidebarWidth = false;
  function maxSidebarWidth() {
    const inspectorWidth = inspectorPanel.getBoundingClientRect().width || 270;
    const workspaceMinimum = 280;
    return Math.max(
      minSidebarWidth,
      Math.min(480, window.innerWidth - inspectorWidth - workspaceMinimum),
    );
  }
  function setSidebarWidth(width) {
    const maxWidth = maxSidebarWidth();
    const nextWidth = Math.round(
      Math.min(maxWidth, Math.max(minSidebarWidth, width)),
    );
    zeroRoot.style.setProperty("--zero-left-width", `${nextWidth}px`);
    leftResizer.setAttribute("aria-valuemax", String(maxWidth));
    leftResizer.setAttribute("aria-valuenow", String(nextWidth));
    return nextWidth;
  }
  leftResizer.setAttribute("aria-valuemax", String(maxSidebarWidth()));
  leftResizer.setAttribute(
    "aria-valuenow",
    String(Math.round(Number.parseFloat(getComputedStyle(leftPanel).width) || 190)),
  );
  function saveSidebarWidth(width) {
    try {
      localStorage.setItem(sidebarPreferenceKey, String(width));
      rememberSidebarWidth = true;
    } catch {
      // Resizing remains available when browser storage is disabled.
    }
  }
  try {
    const savedWidth = Number(localStorage.getItem(sidebarPreferenceKey));
    if (Number.isFinite(savedWidth) && savedWidth > 0) {
      setSidebarWidth(savedWidth);
      rememberSidebarWidth = true;
    }
  } catch {
    // The CSS default width is used when browser storage is unavailable.
  }
  let sidebarResizePointer = null;
  const finishSidebarResize = (event) => {
    if (sidebarResizePointer === null || event.pointerId !== sidebarResizePointer)
      return;
    sidebarResizePointer = null;
    zeroRoot.classList.remove("zero-sidebar-resizing");
    leftResizer.classList.remove("is-resizing");
    saveSidebarWidth(Number.parseFloat(leftResizer.getAttribute("aria-valuenow")));
    if (leftResizer.hasPointerCapture(event.pointerId))
      leftResizer.releasePointerCapture(event.pointerId);
  };
  leftResizer.addEventListener("pointerdown", (event) => {
    if (
      mobilePanelQuery.matches ||
      !zeroRoot.classList.contains("zero-layers-open") ||
      event.button !== 0
    )
      return;
    event.preventDefault();
    sidebarResizePointer = event.pointerId;
    leftResizer.setPointerCapture(event.pointerId);
    zeroRoot.classList.add("zero-sidebar-resizing");
    leftResizer.classList.add("is-resizing");
  });
  leftResizer.addEventListener("pointermove", (event) => {
    if (sidebarResizePointer === null || event.pointerId !== sidebarResizePointer)
      return;
    const mainLeft = $(".zero-main").getBoundingClientRect().left;
    setSidebarWidth(event.clientX - mainLeft);
  });
  leftResizer.addEventListener("pointerup", finishSidebarResize);
  leftResizer.addEventListener("pointercancel", finishSidebarResize);
  leftResizer.addEventListener("lostpointercapture", (event) => {
    if (sidebarResizePointer === event.pointerId) {
      sidebarResizePointer = null;
      zeroRoot.classList.remove("zero-sidebar-resizing");
      leftResizer.classList.remove("is-resizing");
      saveSidebarWidth(Number.parseFloat(leftResizer.getAttribute("aria-valuenow")));
    }
  });
  leftResizer.addEventListener("keydown", (event) => {
    const currentWidth = Number.parseFloat(
      leftResizer.getAttribute("aria-valuenow"),
    );
    let nextWidth;
    if (event.key === "ArrowLeft") nextWidth = currentWidth - 16;
    else if (event.key === "ArrowRight") nextWidth = currentWidth + 16;
    else if (event.key === "Home") nextWidth = minSidebarWidth;
    else if (event.key === "End") nextWidth = maxSidebarWidth();
    else return;
    event.preventDefault();
    event.stopPropagation();
    saveSidebarWidth(setSidebarWidth(nextWidth));
  });
  const onSidebarViewportResize = () => {
    leftResizer.setAttribute("aria-valuemax", String(maxSidebarWidth()));
    if (!mobilePanelQuery.matches) {
      const currentWidth = Number.parseFloat(getComputedStyle(leftPanel).width);
      const appliedWidth = setSidebarWidth(currentWidth);
      if (rememberSidebarWidth) saveSidebarWidth(appliedWidth);
    }
  };
  window.addEventListener("resize", onSidebarViewportResize);
  let layerPanelOpen = !mobilePanelQuery.matches;
  zeroRoot.classList.toggle("zero-layers-open", layerPanelOpen);
  layerToggle.setAttribute("aria-pressed", String(layerPanelOpen));
  $(".zero-top").prepend(addTrigger);
  const deviceControls = document.createElement("div"),
    modeButtons = $(".zero-modes"),
    breakpointsButton = $('[data-action="breakpoints"]');
  deviceControls.className = "zero-device-controls";
  deviceControls.setAttribute("role", "group");
  deviceControls.setAttribute("aria-label", "Размеры экрана и брейкпоинты");
  modeButtons.before(deviceControls);
  deviceControls.append(modeButtons, breakpointsButton, $("#zero-breakpoint-manager"));
  addTrigger.classList.add("zero-add-trigger");
  addTrigger.setAttribute("aria-expanded", "false");
  addTrigger.setAttribute("aria-haspopup", "menu");
  addTrigger.setAttribute("aria-controls", "zero-element-menu");
  $(".zero-add").remove();
  addTrigger.insertAdjacentHTML("afterend", renderZeroElementMenu());
  const elementMenu = $("#zero-element-menu"),
    shapeMenu = $("#zero-shape-menu"),
    preferencesMenu = $("#zero-preferences-menu"),
    preferencesTrigger = $('[data-action="preferences-menu"]');
  function closePreferencesMenu({ restoreFocus = false } = {}) {
    preferencesMenu.hidden = true;
    preferencesTrigger.setAttribute("aria-expanded", "false");
    if (restoreFocus) preferencesTrigger.focus();
  }
  const onPreferencesMenuOutside = (event) => {
    if (
      !preferencesMenu.hidden &&
      !event.target.closest("#zero-preferences-menu") &&
      !event.target.closest('[data-action="preferences-menu"]')
    )
      closePreferencesMenu();
  };
  document.addEventListener("pointerdown", onPreferencesMenuOutside);
  function closeElementMenu({ restoreFocus = false } = {}) {
    elementMenu.hidden = true;
    shapeMenu.hidden = true;
    addTrigger.setAttribute("aria-expanded", "false");
    $('[data-action="shapes-menu"]').setAttribute("aria-expanded", "false");
    if (restoreFocus) addTrigger.focus();
  }
  function toggleElementMenu() {
    const open = elementMenu.hidden;
    elementMenu.hidden = !open;
    addTrigger.setAttribute("aria-expanded", String(open));
    if (!open) {
      shapeMenu.hidden = true;
      $('[data-action="shapes-menu"]').setAttribute("aria-expanded", "false");
    }
  }
  const onElementMenuOutside = (event) => {
    if (
      !elementMenu.hidden &&
      !event.target.closest("#zero-element-menu") &&
      !event.target.closest('[data-action="add-menu"]')
    )
      closeElementMenu();
  };
  document.addEventListener("pointerdown", onElementMenuOutside);
  let planeOffsetX = 80,
    planeOffsetY = 80,
    stagePaddingRight = 80,
    stagePaddingBottom = 80;
  const compactVersion = document.createElement("span");
  compactVersion.className = "zero-version-footer";
  compactVersion.textContent = `Версия ${packageInfo.productVersion}`;
  $("#zero-status").before(compactVersion);
  function syncLayerToggle() {
    const isOpen = mobilePanelQuery.matches
      ? zeroRoot.classList.contains("zero-left-open") &&
        zeroRoot.dataset.mobilePanel === "layers"
      : zeroRoot.classList.contains("zero-layers-open");
    layerToggle.setAttribute("aria-pressed", String(isOpen));
    syncPreferencesMenu();
  }
  function syncPreferencesMenu() {
    const isLayersOpen = mobilePanelQuery.matches
      ? zeroRoot.classList.contains("zero-left-open") &&
        zeroRoot.dataset.mobilePanel === "layers"
      : zeroRoot.classList.contains("zero-layers-open");
    for (const [key, value] of Object.entries({
      layers: isLayersOpen,
      grid,
      rulers,
      snap,
      guides,
    }))
      preferencesMenu
        .querySelector(`[data-pref-toggle="${key}"]`)
        ?.setAttribute("aria-checked", String(value));
  }
  function closeMobilePanels({
    restoreSections = true,
    preserveLayerPreference = false,
  } = {}) {
    const wasLayerDrawerOpen =
      zeroRoot.classList.contains("zero-left-open") &&
      zeroRoot.dataset.mobilePanel === "layers";
    zeroRoot.classList.remove("zero-left-open", "zero-inspector-open");
    $(".zero-panel-backdrop").hidden = true;
    if (!preserveLayerPreference && wasLayerDrawerOpen) layerPanelOpen = false;
    if (restoreSections) {
      $(".zero-layer-panel").hidden = false;
      delete zeroRoot.dataset.mobilePanel;
    }
    syncLayerToggle();
  }
  function toggleMobilePanel(panel, mode = "") {
    const className = panel === "left" ? "zero-left-open" : "zero-inspector-open";
    if (
      zeroRoot.classList.contains(className) &&
      (panel !== "left" || zeroRoot.dataset.mobilePanel === mode)
    ) {
      closeMobilePanels();
      return;
    }
    closeMobilePanels({ restoreSections: false });
    if (panel === "left") {
      $(".zero-layer-panel").hidden = mode !== "layers";
      zeroRoot.dataset.mobilePanel = mode;
      layerPanelOpen = mode === "layers";
    }
    zeroRoot.classList.add(className);
    $(".zero-panel-backdrop").hidden = false;
    syncLayerToggle();
  }
  mobilePanelQuery.addEventListener("change", () => {
    closeMobilePanels({ preserveLayerPreference: true });
    zeroRoot.classList.toggle(
      "zero-layers-open",
      !mobilePanelQuery.matches && layerPanelOpen,
    );
    syncLayerToggle();
  });
  $("#zero-zoom").setAttribute(
    "aria-label",
    "Масштаб холста; можно менять жестом щипка или Ctrl/Cmd + колесо",
  );
  $("#zero-zoom").title = "Жест щипка на тачпаде/экране или Ctrl/Cmd + колесо";
  const selected = () =>
    [...selection].map((id) => findElement(data, id)).filter(Boolean);
  const board = () => data.artboards[mode];
  const gridSettingTargets = () =>
    gridSettingsScope === "all" ? Object.values(data.artboards) : [board()];
  const gridSettingValue = (key, target = board()) =>
    target[key] ??
    ({
      gridWidth: Math.min(1200, target.width),
      gridColumns: 12,
      gridColor: "#86c232",
      gridOpacity: 0.08,
      gridBorderOpacity: 0.12,
      gutter: 20,
      margin: 20,
    })[key];
  function syncGridSettingsDialog() {
    const dialog = $("#zero-grid-settings");
    if (!dialog?.open) return;
    for (const input of dialog.querySelectorAll("[data-grid-setting]")) {
      const key = input.dataset.gridSetting,
        value = gridSettingValue(key);
      input.value =
        key === "gridOpacity" || key === "gridBorderOpacity"
          ? String(Math.round(value * 100))
          : String(value);
      const output = dialog.querySelector(`[data-grid-output="${key}"]`);
      if (output) output.value = `${input.value}%`;
    }
    const metrics = gridMetrics(board());
    $("#zero-grid-column-width").value = `${Number(metrics.columnWidth.toFixed(2))} px`;
    const color = gridSettingValue("gridColor");
    $("#zero-grid-color-trigger .zero-grid-color-swatch").style.backgroundColor = color;
    $("#zero-grid-color-value").textContent = color.toUpperCase();
    $("#zero-grid-setting-scope").value = gridSettingsScope;
  }
  function markGridSettingInput(input, commit) {
    const key = input.dataset.gridSetting,
      raw = Number(input.value),
      opacity = key === "gridOpacity" || key === "gridBorderOpacity",
      value = opacity ? raw / 100 : Math.round(raw),
      bounds = {
        gridWidth: [1, 2400],
        gridColumns: [1, 24],
        gutter: [0, 200],
        margin: [0, 500],
        gridOpacity: [0, 1],
        gridBorderOpacity: [0, 1],
      }[key];
    if (!bounds || !Number.isFinite(value) || value < bounds[0] || value > bounds[1])
      return;
    const fieldKey = `grid:${gridSettingsScope}:${key}`;
    if (pendingField && pendingField !== fieldKey) flushField();
    pendingField = fieldKey;
    for (const target of gridSettingTargets()) target[key] = value;
    syncGridSettingsDialog();
    if (commit) {
      clearTimeout(fieldTimer);
      changed();
      return;
    }
    dirty = true;
    clearTimeout(timer);
    drawCanvas();
    status(conflict ? "Конфликт · локальные изменения сохранены в окне" : "Изменения не сохранены");
    clearTimeout(fieldTimer);
    fieldTimer = setTimeout(() => {
      flushField();
      if (dirty && !conflict) timer = setTimeout(save, 800);
    }, 500);
  }
  function applyGridColor({ color, opacity }, commit) {
    const key = `grid:${gridSettingsScope}:appearance`;
    if (pendingField && pendingField !== key) flushField();
    pendingField = key;
    for (const target of gridSettingTargets()) {
      target.gridColor = color;
      target.gridOpacity = opacity;
    }
    syncGridSettingsDialog();
    if (commit) {
      clearTimeout(fieldTimer);
      changed();
      return;
    }
    dirty = true;
    clearTimeout(timer);
    drawCanvas();
    status(conflict ? "Конфликт · локальные изменения сохранены в окне" : "Изменения не сохранены");
    clearTimeout(fieldTimer);
    fieldTimer = setTimeout(() => {
      flushField();
      if (dirty && !conflict) timer = setTimeout(save, 800);
    }, 500);
  }
  function resetGridSettings() {
    if (pendingField) flushField();
    for (const target of gridSettingTargets()) {
      target.gridWidth = Math.min(1200, target.width);
      target.gridColumns = 12;
      target.gutter = 20;
      target.margin = 20;
      target.gridColor = "#86c232";
      target.gridOpacity = 0.08;
      target.gridBorderOpacity = 0.12;
    }
    syncGridSettingsDialog();
    changed();
  }
  const parentBoard = (item) =>
    item.parent
      ? {
          width: resolve(item.parent, mode).geometry.width,
          height: resolve(item.parent, mode).geometry.height,
          gridWidth: 0,
        }
      : board();
  const parentRotation = (item) => {
    let angle = 0,
      p = item.parent;
    while (p) {
      angle += resolve(p, mode).geometry.rotation;
      p = findElement(data, p.id)?.parent;
    }
    return angle;
  };
  const setGeometry = (el, g) => {
    for (const key of Object.keys(g))
      setValue(
        el,
        mode,
        "geometry",
        key,
        Math.round(g[key] * 100) / 100 || g[key],
      );
  };
  function flushField() {
    clearTimeout(fieldTimer);
    if (pendingField) {
      history.push(data);
      pendingField = null;
    }
  }
  function changed() {
    flushField();
    history.push(data);
    dirty = JSON.stringify(data) !== JSON.stringify(baseData);
    status(
      conflict
        ? "Конфликт · локальные изменения сохранены в окне"
        : "Изменения не сохранены",
    );
    clearTimeout(timer);
    if (!conflict) timer = setTimeout(save, 1200);
    render();
  }
  const collapsedLayerGroups = new Set();
  function layers() {
    const root = $("#zero-layers"),
      entries = [],
      groups = new Set(
        flatten(data)
          .filter(({ element }) => element.type === "group")
          .map(({ element }) => element.id),
      );
    const visit = (items, depth = 0, parentId = "", ancestorCollapsed = false) => {
      for (const el of [...items].reverse()) {
        entries.push({ element: el, depth, parentId, hidden: ancestorCollapsed });
        if (el.type !== "group") continue;
        if (el.children?.length)
          visit(
            el.children,
            depth + 1,
            el.id,
            ancestorCollapsed || collapsedLayerGroups.has(el.id),
          );
      }
    };
    visit(data.elements);
    for (const id of collapsedLayerGroups)
      if (!groups.has(id)) collapsedLayerGroups.delete(id);
    const descendantCount = (el) =>
      (el.children || []).reduce(
        (count, child) => count + 1 + descendantCount(child),
        0,
      );
    root.innerHTML = entries
      .map(({ element: el, depth, parentId, hidden }) => {
        const hasChildren = el.type === "group" && !!el.children?.length,
          collapsed = collapsedLayerGroups.has(el.id),
          disclosure = hasChildren
            ? `<button class="zero-layer-disclosure" data-toggle-group="${esc(el.id)}" aria-expanded="${String(!collapsed)}" aria-label="${collapsed ? "Раскрыть" : "Свернуть"} группу ${esc(el.name)}" title="${collapsed ? "Раскрыть" : "Свернуть"} группу">${tablerIcon(collapsed ? "chevron-right" : "chevron-down")}</button>`
            : '<span class="zero-layer-disclosure-spacer" aria-hidden="true"></span>',
          groupBadge =
            hasChildren
              ? `<span class="zero-layer-count" aria-label="${descendantCount(el)} вложенных элементов"${collapsed ? "" : " hidden"}>${descendantCount(el)}</span>`
              : "",
          typeIcon = {
            text: "typography",
            image: "photo",
            rectangle: "square",
            ellipse: "circle",
            line: "minus",
            button: "link",
            html: "code",
            group: "stack-2",
          }[el.type];
        return `<div class="zero-layer ${selection.has(el.id) ? "selected" : ""} ${el.type === "group" ? "is-group" : ""} ${el.locked ? "is-locked" : ""}"${hidden ? " hidden" : ""} data-layer="${esc(el.id)}" data-layer-parent="${esc(parentId)}" data-layer-depth="${depth}" style="padding-left:${depth * 3}px">${disclosure}<button class="zero-layer-name" data-select="${esc(el.id)}" aria-label="Слой ${esc(el.name)}" title="${esc(el.name)}"><span class="zero-layer-icon" aria-hidden="true">${tablerIcon(typeIcon)}</span><span class="zero-layer-label">${esc(el.name)}</span>${groupBadge}</button><button class="zero-layer-lock" data-lock="${esc(el.id)}" title="Блокировка" aria-label="Блокировка ${esc(el.name)}">${tablerIcon(el.locked ? "lock" : "lock-open")}</button><button class="zero-layer-visibility" data-visibility="${esc(el.id)}" title="Видимость" aria-label="Видимость ${esc(el.name)}">${tablerIcon(resolve(el, mode).visible ? "eye" : "eye-off")}</button></div>`;
      })
      .join("");
  }
  function syncLayerTree() {
    const root = $("#zero-layers"),
      rows = [...root.querySelectorAll(".zero-layer")],
      byId = new Map(rows.map((row) => [row.dataset.layer, row]));
    for (const row of rows) {
      let parentId = row.dataset.layerParent,
        hidden = false;
      while (parentId) {
        if (collapsedLayerGroups.has(parentId)) {
          hidden = true;
          break;
        }
        parentId = byId.get(parentId)?.dataset.layerParent || "";
      }
      row.hidden = hidden;
      row.classList.toggle("selected", selection.has(row.dataset.layer));
      const disclosure = row.querySelector(".zero-layer-disclosure");
      if (!disclosure) continue;
      const collapsed = collapsedLayerGroups.has(row.dataset.layer),
        label = row.querySelector(".zero-layer-label")?.textContent || "группу",
        action = collapsed ? "Раскрыть" : "Свернуть";
      disclosure.setAttribute("aria-expanded", String(!collapsed));
      disclosure.setAttribute("aria-label", `${action} группу ${label}`);
      disclosure.title = `${action} группу`;
      disclosure.innerHTML = tablerIcon(collapsed ? "chevron-right" : "chevron-down");
      const badge = row.querySelector(".zero-layer-count");
      if (badge) badge.hidden = !collapsed;
    }
  }
  function toggleLayerGroup(id) {
    const item = findElement(data, id)?.element;
    if (item?.type !== "group" || !item.children?.length) return;
    if (collapsedLayerGroups.has(id)) collapsedLayerGroups.delete(id);
    else collapsedLayerGroups.add(id);
    syncLayerTree();
  }
  function beginLayerRename(id, button) {
    const item = findElement(data, id)?.element;
    if (!item || preview) return;
    selection = new Set([id]);
    syncLayerTree();
    drawCanvas();
    inspector();
    const input = document.createElement("input"),
      originalName = item.name || "";
    input.className = "zero-layer-rename";
    input.type = "text";
    input.maxLength = 120;
    input.value = originalName;
    input.setAttribute("aria-label", `Название слоя ${originalName}`);
    button.replaceWith(input);
    const row = input.closest(".zero-layer");
    if (row) row.draggable = false;
    let cancelled = false,
      finished = false;
    const finish = (commit) => {
      if (finished) return;
      finished = true;
      const current = findElement(data, id)?.element,
        nextName = input.value.trim();
      if (!commit || !current || !nextName || nextName === originalName) {
        render();
        return;
      }
      current.name = nextName;
      changed();
    };
    input.addEventListener("keydown", (event) => {
      event.stopPropagation();
      if (event.key === "Enter") {
        event.preventDefault();
        input.blur();
      } else if (event.key === "Escape") {
        event.preventDefault();
        cancelled = true;
        input.blur();
      }
    });
    input.addEventListener("blur", () => finish(!cancelled), { once: true });
    input.focus();
    input.select();
  }
  function inspector() {
    const root = $("#zero-inspector"),
      items = selected(),
      locked = items.length > 0 && items.every(({ element }) => element.locked);
    root.innerHTML = renderInspector({
      items,
      mode,
      board: board(),
      project,
    });
    if (locked) {
      root.querySelectorAll("input,select,textarea,[data-anchor]").forEach((control) => {
        control.disabled = true;
      });
      const note = document.createElement("p");
      note.className = "zero-help";
      note.textContent = "Слой заблокирован. Снимите замок в панели слоёв, чтобы редактировать свойства.";
      root.prepend(note);
    }
  }
  const breakpointManager = () => $(".zero-breakpoint-manager");
  function renderBreakpointManager() {
    if (!breakpointDraft) return;
    const entries = breakpointEntries(breakpointDraft, "asc");
    breakpointManager().innerHTML = `<div class="zero-breakpoint-heading"><span>Брейкпоинты, px</span><button data-action="breakpoints-cancel" aria-label="Закрыть без сохранения" title="Закрыть">${tablerIcon("x")}</button></div><div class="zero-breakpoint-rows">${entries
      .map(([id, item]) => {
        const icon = breakpointIcon(item.width),
          label = `${breakpointName(id, item.width)} · ${item.width} px`;
        return `<div class="zero-breakpoint-row"><span class="zero-mode-icon" data-orientation="${icon.orientation}" aria-hidden="true">${tablerIcon(icon.name)}</span><label class="zero-breakpoint-width"><span class="zero-sr-only">Ширина ${esc(breakpointName(id, item.width))}</span><input data-breakpoint-width="${esc(id)}" type="number" min="320" max="2400" step="1" value="${item.width}" aria-label="${esc(label)}"></label><button data-remove-breakpoint="${esc(id)}" aria-label="Удалить ${esc(label)}" title="Удалить брейкпоинт" ${entries.length === 1 ? "disabled" : ""}>${tablerIcon("x")}</button></div>`;
      })
      .join("")}</div><div class="zero-breakpoint-actions"><button data-action="breakpoints-add">${tablerIcon("plus")} Добавить брейкпоинт</button><button class="primary" data-action="breakpoints-save">Сохранить</button></div><p class="zero-help">Минимум 320 px, максимум 2400 px. Самый широкий режим задаёт базовую сетку.</p>`;
  }
  function readBreakpointWidths() {
    if (!breakpointDraft) return false;
    const inputs = [...breakpointManager().querySelectorAll("[data-breakpoint-width]")],
      values = inputs.map((input) => Number(input.value));
    if (
      inputs.some((input) => !input.value.trim()) ||
      values.some((width) => !Number.isInteger(width) || width < 320 || width > 2400) ||
      new Set(values).size !== values.length
    ) {
      status("Ширины должны быть целыми, уникальными значениями от 320 до 2400 px.");
      return false;
    }
    inputs.forEach((input, index) => {
      breakpointDraft.artboards[input.dataset.breakpointWidth].width = values[index];
    });
    try {
      validateBreakpoints(breakpointDraft);
      return true;
    } catch (error) {
      status(error.message);
      return false;
    }
  }
  function openBreakpointManager() {
    breakpointDraft = copy(data);
    breakpointManager().hidden = false;
    $('[data-action="breakpoints"]').setAttribute("aria-expanded", "true");
    renderBreakpointManager();
  }
  function addBreakpointFromManager() {
    if (!readBreakpointWidths()) return;
    const width = suggestBreakpointWidth(breakpointDraft);
    if (width === null) {
      status("Нет свободного диапазона для нового брейкпоинта.");
      return;
    }
    try {
      const id = addBreakpoint(breakpointDraft, width);
      renderBreakpointManager();
      breakpointManager()
        .querySelector(`[data-breakpoint-width="${id}"]`)
        ?.focus();
    } catch (error) {
      status(error.message);
    }
  }
  function removeBreakpointFromManager(id) {
    if (!readBreakpointWidths()) return;
    try {
      removeBreakpoint(breakpointDraft, id);
      renderBreakpointManager();
    } catch (error) {
      status(error.message);
    }
  }
  async function saveBreakpointManager() {
    if (!readBreakpointWidths()) return;
    data = breakpointDraft;
    breakpointDraft = null;
    breakpointManager().hidden = true;
    $('[data-action="breakpoints"]').setAttribute("aria-expanded", "false");
    if (!data.artboards[mode]) mode = "desktop";
    changed();
    await save();
  }
  function syncZoomControl() {
    const select = $("#zero-zoom"),
      custom = select.querySelector("option[data-zoom-custom]"),
      preset = [...select.options].find(
        (option) => !option.dataset.zoomCustom && Number(option.value) === zoom,
      );
    if (preset) {
      custom?.remove();
      select.value = preset.value;
      return;
    }
    const option = custom || document.createElement("option");
    if (!custom) option.dataset.zoomCustom = "true";
    option.value = String(zoom);
    option.textContent = `${Math.round(zoom * 100)}%`;
    if (!option.isConnected) select.append(option);
    select.value = option.value;
  }
  function canvasDocumentBounds() {
    const initial = documentBounds(data, mode),
      rect = canvas.getBoundingClientRect();
    if (rect.width && rect.height)
      for (const element of canvas.querySelectorAll("[data-ze]")) {
        if (!element.getClientRects().length) continue;
        const box = element.getBoundingClientRect(),
          left = (box.left - rect.left) / zoom,
          top = (box.top - rect.top) / zoom,
          right = (box.right - rect.left) / zoom,
          bottom = (box.bottom - rect.top) / zoom;
        initial.left = Math.min(initial.left, left);
        initial.top = Math.min(initial.top, top);
        initial.right = Math.max(initial.right, right);
        initial.bottom = Math.max(initial.bottom, bottom);
      }
    return initial;
  }
  function expandedCanvasBounds(bounds = canvasDocumentBounds()) {
    const b = board(),
      margin = 32 / Math.max(zoom, 0.01);
    return {
      left: bounds.left < 0 ? bounds.left - margin : 0,
      top: bounds.top < 0 ? bounds.top - margin : 0,
      right: bounds.right > b.width ? bounds.right + margin : b.width,
      bottom: bounds.bottom > b.height ? bounds.bottom + margin : b.height,
    };
  }
  function fitZoom(bounds, maxZoom) {
    const b = board(),
      edgePaddingX = (bounds.left < 0 ? 32 : 0) + (bounds.right > b.width ? 32 : 0),
      edgePaddingY = (bounds.top < 0 ? 32 : 0) + (bounds.bottom > b.height ? 32 : 0),
      fit = Math.min(
        (workspace.clientWidth - 24 - edgePaddingX) / (bounds.right - bounds.left),
        (workspace.clientHeight - 24 - edgePaddingY) / (bounds.bottom - bounds.top),
      );
    return Math.round(
      Math.min(maxZoom, Math.max(0.25, fit)) * 100,
    ) / 100;
  }
  function updateStageBounds(bounds = expandedCanvasBounds()) {
    planeOffsetX = Math.max(planeOffsetX, 12 - bounds.left * zoom);
    planeOffsetY = Math.max(planeOffsetY, 12 - bounds.top * zoom);
    plane.style.left = `${planeOffsetX}px`;
    plane.style.top = `${planeOffsetY}px`;
    bleedSurface.style.left = `${bounds.left}px`;
    bleedSurface.style.top = `${bounds.top}px`;
    bleedSurface.style.width = `${bounds.right - bounds.left}px`;
    bleedSurface.style.height = `${bounds.bottom - bounds.top}px`;
    bleedSurface.hidden = preview && board().overflow !== "visible";
    const stage = $(".zero-stage");
    stage.style.width = `${planeOffsetX + bounds.right * zoom + stagePaddingRight}px`;
    stage.style.height = `${planeOffsetY + bounds.bottom * zoom + stagePaddingBottom}px`;
  }
  function zoomAt(nextZoom, clientX, clientY, documentAnchor = null) {
    const viewport = workspace.getBoundingClientRect(),
      content = expandedCanvasBounds(),
      result = zoomCameraAtPoint({
        zoom,
        nextZoom,
        scrollLeft: workspace.scrollLeft,
        scrollTop: workspace.scrollTop,
        pointX: clientX - viewport.left,
        pointY: clientY - viewport.top,
        documentX: documentAnchor?.x,
        documentY: documentAnchor?.y,
        planeOffsetX,
        planeOffsetY,
        stagePaddingRight,
        stagePaddingBottom,
        viewportWidth: workspace.clientWidth,
        viewportHeight: workspace.clientHeight,
        boardWidth: board().width,
        boardHeight: board().height,
        contentLeft: content.left,
        contentTop: content.top,
        contentRight: content.right,
        contentBottom: content.bottom,
      });
    zoom = result.zoom;
    planeOffsetX = result.planeOffsetX;
    planeOffsetY = result.planeOffsetY;
    stagePaddingRight = result.stagePaddingRight;
    stagePaddingBottom = result.stagePaddingBottom;
    drawCanvas();
    workspace.scrollLeft = result.scrollLeft;
    workspace.scrollTop = result.scrollTop;
    return { x: result.documentX, y: result.documentY };
  }
  function drawCanvas() {
    canvas.innerHTML = renderZero(data, blockId, mode, { runScripts: preview });
    const b = board();
    canvas.firstElementChild?.style.setProperty(
      "overflow",
      preview ? b.overflow : "visible",
      "important",
    );
    plane.style.width = b.width + "px";
    plane.style.height = b.height + "px";
    plane.style.left = `${planeOffsetX}px`;
    plane.style.top = `${planeOffsetY}px`;
    plane.style.transform = `scale(${zoom})`;
    updateStageBounds();
    syncZoomControl();
    for (const el of canvas.querySelectorAll("[data-ze]")) {
      const item = findElement(data, el.dataset.ze);
      if (item?.element.locked) el.dataset.locked = "true";
      if (el.tagName === "A") el.onclick = (event) => event.preventDefault();
    }
    const overlays = $(".zero-selection-overlays");
    overlays.replaceChildren();
    if (!preview)
      for (const item of selected()) {
        const source = canvas.querySelector(`[data-ze="${item.element.id}"]`);
        if (!source || !resolve(item.element, mode).visible) continue;
        const box = document.createElement("div");
        box.className = "zero-selection-box";
        box.dataset.for = item.element.id;
        box.style.width = source.offsetWidth + "px";
        box.style.height = source.offsetHeight + "px";
        box.style.transform = `matrix(${worldMatrix(data, item.element.id, mode, { width: source.offsetWidth, height: source.offsetHeight }).join(",")})`;
        if (selection.size === 1 && !item.element.locked)
          for (const handle of [
            "nw",
            "n",
            "ne",
            "e",
            "se",
            "s",
            "sw",
            "w",
            "rotate",
          ]) {
            const node = document.createElement("i");
            node.className = "zero-handle zero-handle-" + handle;
            node.dataset.handle = handle;
            node.setAttribute("aria-label", handle);
            box.append(node);
          }
        overlays.append(box);
      }
    const gridEl = $(".zero-grid");
    gridEl.hidden = !grid || preview;
    const metrics = gridMetrics(b),
      color = /^#[a-f\d]{6}$/i.test(b.gridColor || "") ? b.gridColor : "#86c232",
      columnFill = gridRgba(color, b.gridOpacity ?? 0.08),
      columnBorder = gridRgba(color, b.gridBorderOpacity ?? 0.12),
      backgrounds = [];
    if ((b.gridOpacity ?? 0.08) > 0 && metrics.columnWidth > 0)
      backgrounds.push(`repeating-linear-gradient(to right,${columnFill} 0px,${columnFill} ${metrics.columnWidth}px,transparent ${metrics.columnWidth}px,transparent ${metrics.step}px)`);
    if ((b.gridBorderOpacity ?? 0.12) > 0)
      backgrounds.push(`repeating-linear-gradient(to right,${columnBorder} 0px,${columnBorder} 1px,transparent 1px,transparent ${metrics.step}px)`);
    gridEl.style.cssText = `left:${(b.width - metrics.containerWidth) / 2 + metrics.margin}px;width:${Math.max(1, metrics.contentWidth)}px;height:${b.height}px;background-image:${backgrounds.join(",") || "none"};background-repeat:repeat`;
    $(".zero-guides").innerHTML =
      guides && !preview
        ? b.guides
            .map(
              (g, i) =>
                `<div class="zero-guide zero-guide-${g.axis}" data-guide="${i}" style="${g.axis === "x" ? "left" : "top"}:${g.position}px"></div>`,
            )
            .join("") +
          (gesture?.kind === "drag"
            ? smartLines
                .map(
                  (g) =>
                    `<div class="zero-guide zero-guide-${g.axis} zero-smart-guide" style="${g.axis === "x" ? "left" : "top"}:${g.position}px"></div>`,
                )
                .join("")
            : "")
        : "";
    $(".zero-ruler-x").hidden = !rulers || preview;
    $(".zero-ruler-y").hidden = !rulers || preview;
    $(".zero-ruler-x").innerHTML = Array.from(
      { length: Math.ceil(b.width / 100) },
      (_, i) => `<span style="left:${i * 100}px">${i * 100}</span>`,
    ).join("");
    $(".zero-ruler-y").innerHTML = Array.from(
      { length: Math.ceil(b.height / 100) },
      (_, i) => `<span style="top:${i * 100}px">${i * 100}</span>`,
    ).join("");
    updateStageBounds();
  }
  function drawGesture(task) {
    const ids = new Set();
    const collect = (el) => {
      ids.add(el.id);
      for (const c of el.children || []) collect(c);
    };
    for (const snap of task.snapshots || []) {
      const i = findElement(data, snap.id);
      if (i) collect(i.element);
    }
    for (const id of ids) {
      const item = findElement(data, id),
        e = resolve(item.element, mode),
        el = canvas.querySelector(`[data-ze="${id}"]`);
      if (!el) continue;
      el.style.cssText =
        geometryCSS(e.geometry, board(), !!item.parent) +
        appearanceCSS(e.style) +
        `z-index:${item.list.indexOf(item.element) + 1};display:${e.visible ? "block" : "none"};` +
        (e.type === "ellipse" ? "border-radius:50%;" : "") +
        (e.type === "image" && !e.style.objectFit ? "object-fit:cover;" : "");
    }
    for (const box of $(".zero-selection-overlays").children) {
      const item = findElement(data, box.dataset.for),
        g = resolve(item.element, mode).geometry;
      box.style.width = g.width + "px";
      box.style.height = g.height + "px";
      box.style.transform = `matrix(${worldMatrix(data, box.dataset.for, mode).join(",")})`;
    }
    $(".zero-guides")
      .querySelectorAll(".zero-smart-guide")
      .forEach((n) => n.remove());
    if (guides)
      for (const g of smartLines) {
        const el = document.createElement("div");
        el.className = `zero-guide zero-guide-${g.axis} zero-smart-guide`;
        el.style[g.axis === "x" ? "left" : "top"] = g.position + "px";
        $(".zero-guides").append(el);
      }
    updateStageBounds();
  }
  function render() {
    if (destroyed) return;
    drawCanvas();
    layers();
    inspector();
    $(".zero-modes").innerHTML = breakpointEntries(data, "asc")
      .map(([id, item]) => {
        const icon = breakpointIcon(item.width),
          label = `${breakpointName(id, item.width)} · ${item.width} px`;
        return `<button data-mode="${esc(id)}" title="${esc(label)}" aria-label="${esc(label)}" aria-pressed="${id === mode}"><span class="zero-mode-icon" data-orientation="${icon.orientation}" aria-hidden="true">${tablerIcon(icon.name)}</span></button>`;
      })
      .join("");
    app.querySelector(".zero-app").classList.toggle("zero-preview", preview);
    syncGridSettingsDialog();
    syncPreferencesMenu();
  }
  async function save() {
    clearTimeout(timer);
    if (!dirty || saving || conflict || gesture) return;
    saving = true;
    let retry = false;
    status("Сохранение…");
    const draft = copy(data);
    try {
      const result = await api(
        `/projects/${projectId}/pages/${pageId}/zero/${blockId}`,
        "PUT",
        { version, zeroData: draft },
      );
      saveRetries = 0;
      retry = true;
      version = result.version;
      baseData = draft;
      dirty = JSON.stringify(data) !== JSON.stringify(draft);
      channel.postMessage({
        type: "zero:saved",
        projectId,
        pageId,
        blockId,
        version,
      });
      status(`Сохранено · ревизия ${version}`);
    } catch (error) {
      if (error.status === 409) {
        conflict = true;
        $('[data-action="rebase"]').hidden = false;
        status("Конфликт версий · изменения остались в окне");
      } else {
        retry = (!error.status || error.status >= 500) && saveRetries++ < 2;
        status(error.message);
      }
    } finally {
      saving = false;
      if (dirty && !conflict && retry) timer = setTimeout(save, 1500);
    }
  }
  async function rebase() {
    try {
      const latest = await api(`/projects/${projectId}`),
        remote = locate(latest).zeroData;
      if (JSON.stringify(remote) === JSON.stringify(baseData)) {
        version = latest.version;
        conflict = false;
        $('[data-action="rebase"]').hidden = true;
        dirty = JSON.stringify(data) !== JSON.stringify(remote);
        if (!dirty) {
          baseData = copy(remote);
          status(`Сохранено · ревизия ${version}`);
          return;
        }
        status("Другие изменения объединены; сохраняем Zero Block");
        await save();
      } else if (
        window.confirm(
          "Этот Zero Block изменён в другом окне. Загрузить серверный вариант? Текущую версию можно сначала скачать через «Скачать JSON».",
        )
      ) {
        data = copy(remote);
        baseData = copy(remote);
        version = latest.version;
        dirty = false;
        conflict = false;
        history.push(data);
        $('[data-action="rebase"]').hidden = true;
        render();
        status(`Загружена ревизия ${version}`);
      }
    } catch (error) {
      status(error.message);
    }
  }
  let assetTarget = null,
    assets = [];
  function assetList() {
    const query = $("#zero-asset-search").value.toLowerCase();
    $(".zero-asset-list").innerHTML =
      assets
        .filter((a) => a.name.toLowerCase().includes(query))
        .map(
          (a) =>
            `<button data-asset="${a.id}"><img src="${esc(a.url)}" alt=""><span>${esc(a.name)}</span></button>`,
        )
        .join("") ||
      "<p>Фото пока нет. Загрузите изображение с компьютера.</p>";
  }
  async function chooseImage(target = null) {
    assetTarget = target || { kind: "image-new" };
    $("#zero-assets").showModal();
    try {
      assets = await api(`/projects/${projectId}/assets`);
      assetList();
    } catch (e) {
      $("#zero-asset-status").textContent = e.message;
    }
  }
  $("#zero-asset-search").oninput = assetList;
  $("#zero-upload").onchange = async (event) => {
    const file = event.target.files[0];
    if (!file) return;
    try {
      if (
        file.size > 4194304 ||
        !["image/png", "image/jpeg", "image/webp", "image/gif"].includes(
          file.type,
        )
      )
        throw new Error("Допустим PNG/JPEG/WebP/GIF до 4 МБ.");
      $("#zero-asset-status").textContent = "Загрузка…";
      const r = await fetch(`/api/projects/${projectId}/assets`, {
          method: "POST",
          headers: {
            "Content-Type": "application/octet-stream",
            "X-CSRF-Token": session.csrf,
            "X-Asset-Name": encodeURIComponent(file.name),
          },
          body: file,
        }),
        a = await r.json();
      if (!r.ok) throw new Error(a.error);
      assets.unshift(a);
      assetList();
      $("#zero-asset-status").textContent =
        "Фото добавлено в библиотеку сайта.";
    } catch (e) {
      $("#zero-asset-status").textContent = e.message;
    } finally {
      event.target.value = "";
    }
  };
  function add(type) {
    closeElementMenu();
    if (type === "image") {
      chooseImage();
      return;
    }
    const el = createElement(type, project.settings?.designTokens);
    if (flatten(data).length >= 300) {
      status("Не более 300 элементов");
      return;
    }
    data.elements.push(el);
    selection = new Set([el.id]);
    changed();
  }
  function duplicate() {
    const roots = selectionRoots(data, [...selection]);
    const count = (el) =>
      1 + (el.children || []).reduce((n, c) => n + count(c), 0);
    if (
      flatten(data).length + roots.reduce((n, i) => n + count(i.element), 0) >
      300
    ) {
      status("Не более 300 элементов.");
      return;
    }
    const copies = [];
    for (const item of roots) {
      const clone = cloneElementForPaste(item.element);
      item.list.splice(item.list.indexOf(item.element) + 1, 0, clone);
      copies.push(clone.id);
    }
    selection = new Set(copies);
    changed();
  }
  function group() {
    try {
      selection = new Set(groupElements(data, [...selection], mode));
      changed();
    } catch (e) {
      status(e.message);
    }
  }
  function ungroup() {
    try {
      selection = new Set(ungroupElements(data, [...selection], mode));
      changed();
    } catch (e) {
      status(e.message);
    }
  }
  function reorder(direction) {
    for (const item of selected()) {
      const at = item.list.indexOf(item.element),
        to =
          direction === "front"
            ? item.list.length - 1
            : direction === "back"
              ? 0
              : Math.max(
                  0,
                  Math.min(
                    item.list.length - 1,
                    at + (direction === "forward" ? 1 : -1),
                  ),
                );
      item.list.splice(at, 1);
      item.list.splice(to, 0, item.element);
    }
    changed();
  }
  function align(axis) {
    const items = selected().filter((i) => !i.element.locked);
    for (const item of items) {
      const g = resolve(item.element, mode).geometry,
        pb = parentBoard(item),
        r = rect(g, pb, !!item.parent);
      let dx = 0,
        dy = 0;
      if (axis === "left") dx = -r.x;
      else if (axis === "center") dx = pb.width / 2 - r.x - r.width / 2;
      else if (axis === "right") dx = pb.width - r.x - r.width;
      else if (axis === "top") dy = -r.y;
      else if (axis === "bottom") dy = pb.height - r.y - r.height;
      else dy = pb.height / 2 - r.y - r.height / 2;
      setValue(item.element, mode, "geometry", "x", g.x + dx);
      setValue(item.element, mode, "geometry", "y", g.y + dy);
    }
    changed();
  }
  function distribute(axis) {
    const items = selected();
    if (items.length < 3 || items.some((i) => i.parent !== items[0].parent))
      return;
    const coord = axis === "x" ? "x" : "y",
      size = axis === "x" ? "width" : "height",
      entries = items
        .map((i) => ({
          ...i,
          r: rect(
            resolve(i.element, mode).geometry,
            parentBoard(i),
            !!i.parent,
          ),
        }))
        .sort((a, b) => a.r[coord] - b.r[coord]);
    const first = entries[0].r[coord],
      last = entries.at(-1).r[coord] + entries.at(-1).r[size],
      gap =
        (last - first - entries.reduce((s, i) => s + i.r[size], 0)) /
        (entries.length - 1);
    let cursor = first;
    for (const i of entries) {
      const g = resolve(i.element, mode).geometry;
      setValue(
        i.element,
        mode,
        "geometry",
        coord,
        g[coord] + cursor - i.r[coord],
      );
      cursor += i.r[size] + gap;
    }
    changed();
  }
  let aiCandidateBaseVersion = null;
  function openZeroAiPrompt() {
    closeElementMenu();
    if (!preview) {
      $("#zero-ai-prompt-text").value = "";
      $("#zero-ai-prompt-status").textContent = "";
      $("#zero-ai-prompt").showModal();
      $("#zero-ai-prompt-text").focus();
    }
  }
  function renderZeroAiCandidatePreview(activeMode = "desktop") {
    const candidate = aiCandidateData,
      stage = $("#zero-ai-preview"),
      controls = $("#zero-ai-preview-controls"),
      board = candidate?.artboards?.[activeMode];
    if (!candidate || !board || !stage || !controls) return;
    controls.innerHTML = breakpointEntries(candidate, "asc")
      .map(([id, item]) => {
        const icon = breakpointIcon(item.width),
          label = `${breakpointName(id, item.width)} · ${item.width} px`;
        return `<button type="button" data-action="ai-preview-breakpoint" data-preview-breakpoint="${esc(id)}" aria-label="${esc(label)}" title="${esc(label)}" aria-pressed="${id === activeMode}">${tablerIcon(icon.name)}<span>${item.width}</span></button>`;
      })
      .join("");
    stage.dataset.previewMode = activeMode;
    stage.innerHTML = renderZero(
      candidate,
      `${blockId}-ai-preview`,
      activeMode,
    );
    requestAnimationFrame(() => {
      if (candidate !== aiCandidateData || stage.dataset.previewMode !== activeMode)
        return;
      const section = stage.firstElementChild;
      if (!section) return;
      const scale = Math.min(0.72, Math.max(0.2, (stage.clientWidth - 16) / board.width));
      section.style.transformOrigin = "top left";
      section.style.transform = `scale(${scale})`;
      stage.style.height = `${board.height * scale}px`;
    });
  }
  function applyAiArtboardBackground(candidate, settings) {
    if (
      !candidate?.artboards ||
      !/^#[a-f\d]{6}$/i.test(settings?.backgroundColor || "") ||
      !["clear", "preserve"].includes(settings?.backgroundImageMode)
    ) return;
    for (const board of Object.values(candidate.artboards)) {
      board.background = settings.backgroundColor;
      board.backgroundOpacity = 1;
      if (settings.backgroundImageMode === "clear") {
        board.backgroundImage = "";
        board.backgroundImageName = "";
        delete board.backgroundFill;
        board.filterStartOpacity = 0;
        board.filterEndOpacity = 0;
      }
    }
  }
  async function generateZeroAiProposal() {
    const prompt = $("#zero-ai-prompt-text").value.trim(),
      submit = $('[data-action="ai-generate"]'),
      message = $("#zero-ai-prompt-status");
    if (!prompt) {
      message.textContent = "Опишите композицию, чтобы продолжить.";
      return;
    }
    submit.disabled = true;
    message.textContent = "Сохраняю текущую версию и готовлю предложение…";
    try {
      flushField();
      if (dirty) await save();
      if (dirty || conflict) throw new Error("Сначала разрешите сохранение текущих изменений.");
      const baseVersion = version,
        proposal = await api(`/projects/${projectId}/ai/zero-proposal`, "POST", {
          prompt,
          pageId,
          blockId,
          version: baseVersion,
        });
      if (proposal.baseVersion !== version)
        throw new Error("Версия проекта успела измениться. Запросите новый вариант.");
      if (!Array.isArray(proposal.elements) || proposal.elements.length < 2)
        throw new Error("AI не подготовил достаточно безопасных элементов.");
      aiCandidateData = copy(data);
      aiCandidateData.elements = proposal.elements;
      applyAiArtboardBackground(aiCandidateData, proposal.artboardBackground);
      aiCandidateBaseVersion = proposal.baseVersion;
      const candidateStatus = $("#zero-ai-candidate-status");
      const photoStatus = proposal.metadata?.photoSelection;
      if (photoStatus === "completed") {
        candidateStatus.textContent =
          "Подобраны открытые фото с указанием автора и лицензии. Предложение не сохранено.";
      } else if (photoStatus === "unavailable") {
        candidateStatus.textContent =
          "Поиск Wikimedia Commons или подбор фото временно недоступен. Предложение не сохранено.";
      } else if (proposal.metadata?.photoSlotCount > 0) {
        candidateStatus.textContent =
          "Подходящее фото из Wikimedia Commons не найдено. Предложение не сохранено.";
      } else if (proposal.metadata?.automaticHeroPhoto) {
        candidateStatus.textContent =
          "Для Hero не найдено подходящее фото. Предложение не сохранено.";
      } else {
        candidateStatus.textContent =
          "Для этой композиции фото не подбирались. Предложение не сохранено.";
      }
      $("#zero-ai-prompt").close();
      $("#zero-ai-candidate").showModal();
      renderZeroAiCandidatePreview("desktop");
    } catch (error) {
      message.textContent = error.message || "Не удалось подготовить предложение.";
    } finally {
      submit.disabled = false;
    }
  }
  async function action(key, trigger = null) {
    flushField();
    $("#zero-context").hidden = true;
    if (key !== "preferences-menu") closePreferencesMenu();
    if (key === "preferences-menu") {
      const open = preferencesMenu.hidden;
      preferencesMenu.hidden = !open;
      preferencesTrigger.setAttribute("aria-expanded", String(open));
      if (open)
        preferencesMenu.querySelector('[role="menuitem"]')?.focus();
      return;
    }
    if (key === "artboard-settings") {
      selection.clear();
      render();
      if (mobilePanelQuery.matches) toggleMobilePanel("inspector");
      else inspectorPanel.scrollTop = 0;
      return;
    }
    if (key === "grid-settings") {
      $("#zero-grid-settings").showModal();
      syncGridSettingsDialog();
      return;
    }
    if (key === "grid-settings-close") {
      $("#zero-grid-settings").close();
      return;
    }
    if (key === "grid-color") {
      gridColorPicker.open(trigger);
      return;
    }
    if (key === "grid-reset") {
      resetGridSettings();
      return;
    }
    if (key === "zoom-in" || key === "zoom-out") {
      const bounds = workspace.getBoundingClientRect(),
        next = Math.max(
          0.25,
          Math.min(2, zoom + (key === "zoom-in" ? 0.1 : -0.1)),
        );
      zoomAt(next, bounds.left + bounds.width / 2, bounds.top + bounds.height / 2);
      return;
    }
    if (key === "ai-zero") return openZeroAiPrompt();
    if (key === "ai-prompt-close") {
      $("#zero-ai-prompt").close();
      return;
    }
    if (key === "ai-generate") return generateZeroAiProposal();
    if (key === "ai-candidate-cancel") {
      $("#zero-ai-candidate").close();
      aiCandidateData = null;
      aiCandidateBaseVersion = null;
      return;
    }
    if (key === "ai-preview-breakpoint") {
      const nextMode = trigger?.dataset.previewBreakpoint;
      if (nextMode && aiCandidateData?.artboards?.[nextMode])
        renderZeroAiCandidatePreview(nextMode);
      return;
    }
    if (key === "ai-candidate-apply") {
      if (!aiCandidateData || aiCandidateBaseVersion !== version || conflict) {
        status("Версия изменилась. Закройте предложение и запросите новый вариант.");
        return;
      }
      data = aiCandidateData;
      aiCandidateData = null;
      aiCandidateBaseVersion = null;
      selection.clear();
      $("#zero-ai-candidate").close();
      changed();
      return;
    }
    if (key === "breakpoints") {
      closeElementMenu();
      if (breakpointDraft) {
        breakpointDraft = null;
        breakpointManager().hidden = true;
        $('[data-action="breakpoints"]').setAttribute("aria-expanded", "false");
      } else openBreakpointManager();
      return;
    }
    if (key === "breakpoints-add") return addBreakpointFromManager();
    if (key === "breakpoints-save") return saveBreakpointManager();
    if (key === "breakpoints-cancel") {
      breakpointDraft = null;
      breakpointManager().hidden = true;
      $('[data-action="breakpoints"]').setAttribute("aria-expanded", "false");
      return;
    }
    if (key === "shapes-menu") {
      const open = shapeMenu.hidden;
      shapeMenu.hidden = !open;
      $('[data-action="shapes-menu"]').setAttribute(
        "aria-expanded",
        String(open),
      );
      return;
    }
    if (key === "save") {
      await save();
      return;
    }
    if (key === "rebase") {
      await rebase();
      return;
    }
    if (key === "close") {
      await save();
      if (dirty && !window.confirm("Изменения не сохранены. Закрыть окно?"))
        return;
      window.close();
      return;
    }
    if (key === "undo" || key === "redo") {
      data = history[key]();
      selection = new Set([...selection].filter((id) => findElement(data, id)));
      dirty = JSON.stringify(data) !== JSON.stringify(baseData);
      clearTimeout(timer);
      if (!conflict) {
        status(dirty ? "Изменения не сохранены" : `Сохранено · ревизия ${version}`);
        if (dirty) timer = setTimeout(save, 1200);
      }
      render();
      return;
    }
    if (key === "copy") {
      clipboard = selectionRoots(data, [...selection]).map((item) => ({
        element: copy(item.element),
        parentId: item.parent?.id || null,
      }));
      return;
    }
    if (key === "paste") {
      if (!clipboard.length) return;
      const count = (el) =>
        1 + (el.children || []).reduce((n, c) => n + count(c), 0);
      if (
        flatten(data).length +
          clipboard.reduce((n, c) => n + count(c.element), 0) >
        300
      ) {
        status("Не более 300 элементов.");
        return;
      }
      const destinations = clipboard.map((item) =>
        item.parentId ? findElement(data, item.parentId)?.element : null,
      );
      if (
        destinations.some(
          (parent, index) =>
            clipboard[index].parentId &&
            (!parent || parent.type !== "group" || parent.locked),
        )
      ) {
        status(
          "Исходная группа удалена или заблокирована. Скопируйте слои повторно.",
        );
        return;
      }
      selection = new Set();
      for (const [index, original] of clipboard.entries()) {
        const el = cloneElementForPaste(original.element),
          parent = destinations[index];
        (parent ? parent.children : data.elements).push(el);
        selection.add(el.id);
      }
      changed();
      return;
    }
    if (key === "delete") {
      for (const i of selected().filter((i) => !i.element.locked))
        i.list.splice(i.list.indexOf(i.element), 1);
      selection.clear();
      changed();
      return;
    }
    if (key === "duplicate") return duplicate();
    if (key === "group") return group();
    if (key === "ungroup") return ungroup();
    if (["front", "back", "forward", "backward"].includes(key))
      return reorder(key);
    if (key.startsWith("align-")) return align(key.slice(6));
    if (key.startsWith("distribute-")) return distribute(key.slice(11));
    if (key === "reset-override") {
      for (const i of selected()) delete i.element.overrides[mode];
      changed();
      return;
    }
    if (key === "adapt") {
      adapt(data, mode);
      changed();
      return;
    }
    if (key === "replace-image")
      return chooseImage({ kind: "image", elementId: selected()[0]?.element.id });
    if (key === "assets-close") {
      $("#zero-assets").close();
      return;
    }
    if (key === "panel-close") {
      closeMobilePanels();
      return;
    }
    if (key === "inspector") {
      if (matchMedia("(max-width: 720px)").matches)
        toggleMobilePanel("inspector");
      return;
    }
    if (key === "add-menu") {
      closeMobilePanels();
      toggleElementMenu();
      return;
    }
    if (key === "fit") {
      const bounds = canvasDocumentBounds();
      zoom = fitZoom(bounds, 2);
      const expanded = expandedCanvasBounds(bounds);
      planeOffsetX = 12 - expanded.left * zoom;
      planeOffsetY = 12 - expanded.top * zoom;
      stagePaddingRight = stagePaddingBottom = 12;
      $("#zero-zoom").value = String(zoom);
      drawCanvas();
      workspace.scrollLeft = workspace.scrollTop = 0;
      return;
    }
    if (key === "preview") {
      preview = !preview;
      render();
      return;
    }
    if (key === "layers") {
      closeElementMenu();
      if (matchMedia("(max-width: 720px)").matches) {
        toggleMobilePanel("left", "layers");
        return;
      }
      layerPanelOpen = !zeroRoot.classList.contains("zero-layers-open");
      zeroRoot.classList.toggle("zero-layers-open", layerPanelOpen);
      syncLayerToggle();
      return;
    }
    if (key === "export") {
      const a = document.createElement("a"),
        url = URL.createObjectURL(
          new Blob([JSON.stringify(data, null, 2)], {
            type: "application/json",
          }),
        );
      a.href = url;
      a.download = `${blockId}-local.json`;
      a.click();
      setTimeout(() => URL.revokeObjectURL(url), 1000);
      return;
    }
    if (key === "clear-guides") {
      board().guides = [];
      changed();
      return;
    }
    if (key === "background-image") {
      backgroundImagePicker.open(null, "Фоновое изображение секции", "Применить фон");
      return;
    }
    if (key === "background-color") {
      backgroundColorPicker.open(trigger);
      return;
    }
    if (key === "background-remove") {
      board().backgroundImage = "";
      board().backgroundImageName = "";
      changed();
      return;
    }
    if (["snap", "grid", "guides", "rulers"].includes(key)) {
      if (key === "snap") snap = !snap;
      if (key === "grid") grid = !grid;
      if (key === "guides") guides = !guides;
      if (key === "rulers") rulers = !rulers;
      for (const b of app.querySelectorAll(`[data-action="${key}"]`))
        b.setAttribute(
          "aria-pressed",
          String({ snap, grid, guides, rulers }[key]),
        );
      syncPreferencesMenu();
      drawCanvas();
    }
  }
  app.addEventListener("click", async (event) => {
    const target = event.target.closest("button");
    if (!target) return;
    if (
      target.matches(".zero-layer-name[data-select]") &&
      performance.now() < suppressLayerClickUntil
    ) {
      event.preventDefault();
      return;
    }
    if (target.dataset.toggleGroup) {
      toggleLayerGroup(target.dataset.toggleGroup);
      return;
    }
    if (target.dataset.galleryAdd || target.dataset.galleryPick) {
      flushField();
      const gallery = target.dataset.galleryAdd
        ? findElement(data, target.dataset.galleryAdd)?.element
        : selected().find(({ element }) => element.type === "gallery")?.element;
      if (!gallery || gallery.type !== "gallery" || gallery.locked) return;
      if (target.dataset.galleryAdd && gallery.galleryItems.length >= 12) {
        status("В галерее может быть не более 12 фото");
        return;
      }
      return chooseImage({
        kind: target.dataset.galleryAdd ? "gallery-add" : "gallery-item",
        elementId: gallery.id,
        itemId: target.dataset.galleryPick || null,
      });
    }
    if (target.dataset.galleryRemove) {
      flushField();
      const gallery = selected().find(({ element }) => element.type === "gallery")?.element;
      if (!gallery || gallery.locked) return;
      gallery.galleryItems = gallery.galleryItems.filter(
        (item) => item.id !== target.dataset.galleryRemove,
      );
      changed();
      return;
    }
    if (target.dataset.formAction || target.dataset.formRemove) {
      flushField();
      const form = selected().find(({ element }) => element.type === "form")?.element;
      if (!form || form.locked) return;
      form.formFields ??= [];
      if (target.dataset.formAction === "add") {
        if (form.formFields.length >= 8) {
          status("В макете формы может быть не более 8 полей");
          return;
        }
        form.formFields.push({
          id: `field-${crypto.randomUUID().replaceAll("-", "").slice(0, 8)}`,
          type: "text",
          label: "Новое поле",
          placeholder: "",
          required: false,
        });
      } else if (target.dataset.formRemove) {
        if (form.formFields.length <= 1) return;
        form.formFields = form.formFields.filter(
          (field) => field.id !== target.dataset.formRemove,
        );
      }
      changed();
      return;
    }
    if (target.dataset.removeBreakpoint)
      return removeBreakpointFromManager(target.dataset.removeBreakpoint);
    if (target.dataset.action) return action(target.dataset.action, target);
    if (target.dataset.add) {
      closeMobilePanels();
      return add(target.dataset.add);
    }
    if (target.dataset.mode) {
      mode = target.dataset.mode;
      render();
      return;
    }
    if (target.dataset.select) {
      const id = target.dataset.select,
        item = findElement(data, id)?.element;
      if (!item) return;
      selection = event.shiftKey ? new Set([...selection, id]) : new Set([id]);
      if (item.type === "group" && item.children?.length) {
        if (collapsedLayerGroups.has(id)) collapsedLayerGroups.delete(id);
        else collapsedLayerGroups.add(id);
      }
      syncLayerTree();
      drawCanvas();
      inspector();
      return;
    }
    if (target.dataset.visibility) {
      const el = findElement(data, target.dataset.visibility).element;
      setValue(el, mode, null, "visible", !resolve(el, mode).visible);
      changed();
      return;
    }
    if (target.dataset.lock) {
      const el = findElement(data, target.dataset.lock).element;
      el.locked = !el.locked;
      changed();
      return;
    }
    if (target.dataset.anchor) {
      const [x, y] = target.dataset.anchor.split(",").map(Number);
      for (const i of selected())
        setGeometry(
          i.element,
          anchorGeometry(
            resolve(i.element, mode).geometry,
            parentBoard(i),
            { anchorX: x, anchorY: y, originX: x, originY: y },
            !!i.parent,
          ),
        );
      changed();
      return;
    }
    if (target.dataset.asset) {
      const asset = assets.find((a) => a.id === target.dataset.asset);
      if (!asset) return;
      const targetSpec = assetTarget || { kind: "image-new" };
      const el = targetSpec.kind === "image-new"
        ? createElement("image", project.settings?.designTokens)
        : findElement(data, targetSpec.elementId)?.element;
      if (!el) return;
      if (targetSpec.kind === "gallery-add" || targetSpec.kind === "gallery-item") {
        if (el.type !== "gallery") return;
        el.galleryItems ??= [];
        if (targetSpec.kind === "gallery-add") {
          if (el.galleryItems.length >= 12) return;
          el.galleryItems.push({
            id: `photo-${crypto.randomUUID().replaceAll("-", "").slice(0, 8)}`,
            src: asset.url,
            alt: asset.name,
          });
        } else {
          const item = el.galleryItems.find((candidate) => candidate.id === targetSpec.itemId);
          if (!item) return;
          item.src = asset.url;
          item.alt = asset.name;
        }
      } else {
        if (targetSpec.kind === "image-new") data.elements.push(el);
        setValue(el, mode, null, "src", asset.url);
        if (!el.src) el.src = asset.url;
        setValue(el, "desktop", null, "alt", asset.name);
      }
      selection = new Set([el.id]);
      assetTarget = null;
      $("#zero-assets").close();
      changed();
    }
  });
  app.addEventListener("dblclick", (event) => {
    const button = event.target.closest(".zero-layer-name[data-select]");
    if (!button) return;
    event.preventDefault();
    beginLayerRename(button.dataset.select, button);
  });
  function applyInput(input, commit = true) {
    if (
      input.type === "number" &&
      (!input.value.trim() || !Number.isFinite(Number(input.value)))
    )
      return;
    if (input.id === "zero-zoom") {
      const bounds = plane.getBoundingClientRect();
      zoomAt(
        Number(input.value),
        bounds.left + bounds.width / 2,
        bounds.top + bounds.height / 2,
      );
      return;
    }
    if (input.dataset.board) {
      const boardKey = input.dataset.board;
      const boardValue = boardKey.endsWith("Opacity")
        ? Number(input.value) / 100
        : input.type === "number"
          ? Number(input.value)
          : input.value;
      board()[boardKey] = boardValue;
      if (input.type === "range") {
        const output = $(`[data-board-output="${boardKey}"]`);
        if (output) output.value = `${input.value}%`;
        if (output) output.textContent = `${input.value}%`;
      }
      if (commit) changed();
      else {
        dirty = true;
        clearTimeout(timer);
        drawCanvas();
      }
      return;
    }
    const targets = selected();
    if (targets.length && targets.every(({ element }) => element.locked)) {
      status("Слой заблокирован · разблокируйте его, чтобы изменить");
      return;
    }
    for (const i of targets) {
      if (i.element.locked) continue;
      if (input.hasAttribute("data-container")) {
        setGeometry(
          i.element,
          anchorGeometry(
            resolve(i.element, mode).geometry,
            parentBoard(i),
            { container: input.value },
            !!i.parent,
          ),
        );
      } else if (input.hasAttribute("data-keep-aspect"))
        i.element.keepAspect = input.checked;
      else if (input.hasAttribute("data-auto-height")) {
        if (!input.checked) {
          const el = canvas.querySelector(`[data-ze="${i.element.id}"]`);
          if (el)
            setValue(i.element, mode, "geometry", "height", el.offsetHeight);
        }
        setValue(i.element, mode, "style", "autoHeight", input.checked);
      } else if (input.dataset.code) {
        i.element.code ??= { html: "", css: "", js: "" };
        i.element.code[input.dataset.code] = input.value;
      } else if (input.dataset.animation) {
        const key = input.dataset.animation;
        if (key === "effect") {
          if (input.value === "none") delete i.element.animation;
          else if (["fade", "move", "scale"].includes(input.value))
            i.element.animation = {
              effect: input.value,
              duration: i.element.animation?.duration ?? 700,
              delay: i.element.animation?.delay ?? 0,
              easing: i.element.animation?.easing ?? "ease",
            };
        } else if (i.element.animation && ["duration", "delay", "easing"].includes(key)) {
          const value = key === "easing" ? input.value : Number(input.value);
          if (key === "duration" && Number.isInteger(value) && value >= 100 && value <= 3000)
            i.element.animation.duration = value;
          else if (key === "delay" && Number.isInteger(value) && value >= 0 && value <= 5000)
            i.element.animation.delay = value;
          else if (key === "easing" && ["linear", "ease", "ease-in", "ease-out", "ease-in-out"].includes(value))
            i.element.animation.easing = value;
        }
      } else if (input.dataset.formField && i.element.type === "form") {
        const field = i.element.formFields?.find(
          (candidate) => candidate.id === input.dataset.formField,
        );
        const key = input.dataset.formProp;
        if (!field || !["label", "type", "placeholder", "required"].includes(key))
          continue;
        field[key] =
          key === "required"
            ? input.checked
            : input.value.slice(0, key === "label" ? 80 : 120);
      } else if (input.dataset.galleryItem && i.element.type === "gallery") {
        const item = i.element.galleryItems?.find(
          (candidate) => candidate.id === input.dataset.galleryItem,
        );
        if (item && input.dataset.galleryProp === "alt")
          item.alt = input.value.slice(0, 300);
      } else if (input.dataset.galleryOption && i.element.type === "gallery") {
        i.element.galleryOptions ??= { columns: 3, gap: 16, objectFit: "cover" };
        const key = input.dataset.galleryOption;
        if (key === "objectFit" && ["cover", "contain"].includes(input.value))
          i.element.galleryOptions.objectFit = input.value;
        else if (["columns", "gap"].includes(key)) {
          const value = Number(input.value);
          const [min, max] = key === "columns" ? [1, 6] : [0, 80];
          if (Number.isInteger(value) && value >= min && value <= max)
            i.element.galleryOptions[key] = value;
        }
      } else if (input.dataset.key) {
        let val =
          input.type === "number" || input.dataset.key.startsWith("origin")
            ? Number(input.value)
            : input.value;
        if (input.dataset.colorFill === "true") {
          try { val = JSON.parse(input.dataset.colorFillValue || "null") || input.dataset.colorValue || input.value; }
          catch { val = input.dataset.colorValue || input.value; }
        }
        if (typeof val === "number" && !Number.isFinite(val)) return;
        if (["originX", "originY"].includes(input.dataset.key))
          setGeometry(
            i.element,
            anchorGeometry(
              resolve(i.element, mode).geometry,
              parentBoard(i),
              { [input.dataset.key]: val },
              !!i.parent,
            ),
          );
        else
          setValue(
            i.element,
            mode,
            input.dataset.section,
            input.dataset.key,
            val,
          );
      } else if (input.dataset.element) {
        const key = input.dataset.element;
        if (key === "name") i.element.name = input.value;
        else if (key === "submitLabel" && i.element.type === "form")
          i.element.submitLabel = input.value.slice(0, 60);
        else if (input.value || !["href", "src"].includes(key)) {
          const value = key === "copyright" ? input.value.slice(0, 500) : key === "alt" ? input.value.slice(0, 300) : input.value;
          setValue(i.element, "desktop", null, key, value);
          if (key === "content") {
            delete i.element.runs;
            if (i.element.overrides[mode])
              delete i.element.overrides[mode].runs;
          }
        } else {
          delete i.element[key];
          if (i.element.overrides[mode]) delete i.element.overrides[mode][key];
        }
      }
    }
    if (commit) changed();
    else {
      dirty = true;
      clearTimeout(timer);
      if (input.dataset.code) {
        status(conflict ? "Конфликт · локальные изменения сохранены в окне" : "Изменения не сохранены");
        return;
      }
      drawCanvas();
      if (input.dataset.element === "name") layers();
      status(
        conflict
          ? "Конфликт · локальные изменения сохранены в окне"
          : "Изменения не сохранены",
      );
    }
  }
  app.addEventListener("change", (event) => {
    if (event.target.matches("[data-breakpoint-width]")) return;
    if (event.target.matches("[data-grid-setting]")) {
      markGridSettingInput(event.target, true);
      return;
    }
    if (event.target.id === "zero-grid-setting-scope") {
      gridSettingsScope = event.target.value === "all" ? "all" : "current";
      syncGridSettingsDialog();
      return;
    }
    if (!event.target.closest("#zero-inspector") && event.target.id !== "zero-zoom")
      return;
    if (
      event.target.dataset.formProp === "label" &&
      !event.target.value.trim()
    )
      event.target.value = "Поле";
    if (
      event.target.dataset.element === "submitLabel" &&
      !event.target.value.trim()
    )
      event.target.value = "Отправить";
    applyInput(event.target, true);
  });
  app.addEventListener("input", (event) => {
    if (event.target.matches("[data-grid-setting]")) {
      markGridSettingInput(event.target, false);
      return;
    }
    if (
      !event.target.matches(
        "#zero-inspector input:not([type=checkbox]),#zero-inspector textarea",
      )
    )
      return;
    const key =
      event.target.dataset.key ||
      (event.target.dataset.animation ? `animation:${event.target.dataset.animation}` : "") ||
      event.target.dataset.element ||
      (event.target.dataset.formField
        ? `form-field:${event.target.dataset.formField}:${event.target.dataset.formProp}`
        : "") ||
      (event.target.dataset.galleryItem
        ? `gallery-item:${event.target.dataset.galleryItem}:${event.target.dataset.galleryProp}`
        : "") ||
      (event.target.dataset.galleryOption
        ? `gallery-option:${event.target.dataset.galleryOption}`
        : "") ||
      event.target.dataset.board ||
      (event.target.dataset.code ? `code:${event.target.dataset.code}` : "");
    if (pendingField && pendingField !== key) flushField();
    pendingField = key;
    applyInput(event.target, false);
    clearTimeout(fieldTimer);
    fieldTimer = setTimeout(() => {
      flushField();
      if (dirty && !conflict) timer = setTimeout(save, 800);
    }, 500);
  });
  let layerPointer = null,
    layerDrop = null,
    suppressLayerClickUntil = 0;
  function clearLayerDrop() {
    for (const row of $("#zero-layers").querySelectorAll(".zero-layer"))
      row.classList.remove("drop-before", "drop-after", "drop-inside");
    $("#zero-layers").classList.remove("drop-root");
    layerDrop = null;
  }
  function layerDropAt(row, event, sourceId = layerPointer?.id) {
    const bounds = row.getBoundingClientRect(),
      ratio = bounds.height ? (event.clientY - bounds.top) / bounds.height : 0.5;
    return layerDropIntent(data, sourceId, row.dataset.layer, ratio);
  }
  function updateLayerDrop(event, sourceId) {
    clearLayerDrop();
    const pointed = document.elementFromPoint(event.clientX, event.clientY),
      row = pointed?.closest("#zero-layers [data-layer]");
    if (row) {
      const intent = layerDropAt(row, event, sourceId);
      if (!intent) return null;
      layerDrop = intent;
      row.classList.add(
        intent.kind === "inside" ? "drop-inside" : `drop-${intent.kind}`,
      );
      return intent;
    }
    if (pointed?.closest("#zero-layers")) {
      const intent = layerDropIntent(data, sourceId, null);
      if (!intent) return null;
      layerDrop = intent;
      $("#zero-layers").classList.add("drop-root");
      return intent;
    }
    return null;
  }
  function finishLayerDrop(id) {
    const intent = layerDrop;
    try {
      if (intent && moveLayer(data, id, intent.parentId, intent.index)) {
        selection = new Set([id]);
        changed();
      }
    } catch (error) {
      status(error.message || "Не удалось переместить слой.");
    }
    clearLayerDrop();
  }
  app.addEventListener("pointerdown", (event) => {
    if (event.button !== 0 || event.isPrimary === false) return;
    const name = event.target.closest(".zero-layer-name[data-select]"),
      row = name?.closest("#zero-layers [data-layer]");
    if (!row) return;
    layerPointer = {
      id: row.dataset.layer,
      row,
      pointerId: event.pointerId,
      x: event.clientX,
      y: event.clientY,
      dragging: false,
    };
    suppressLayerClickUntil = 0;
  });
  app.addEventListener("pointermove", (event) => {
    if (!layerPointer || event.pointerId !== layerPointer.pointerId) return;
    if (!layerPointer.dragging) {
      if (
        Math.hypot(event.clientX - layerPointer.x, event.clientY - layerPointer.y) <
        4
      )
        return;
      layerPointer.dragging = true;
      layerPointer.row.classList.add("is-dragging");
    }
    event.preventDefault();
    updateLayerDrop(event, layerPointer.id);
  });
  app.addEventListener("pointerup", (event) => {
    if (!layerPointer || event.pointerId !== layerPointer.pointerId) return;
    const current = layerPointer;
    layerPointer = null;
    if (current.dragging) {
      event.preventDefault();
      suppressLayerClickUntil = performance.now() + 250;
      updateLayerDrop(event, current.id);
      finishLayerDrop(current.id);
    }
    current.row.classList.remove("is-dragging");
  });
  app.addEventListener("pointercancel", (event) => {
    if (!layerPointer || event.pointerId !== layerPointer.pointerId) return;
    layerPointer.row.classList.remove("is-dragging");
    layerPointer = null;
    clearLayerDrop();
  });
  function snapGeometry(item, g) {
    if (!snap || item.parent) return g;
    const b = board(),
      r = rotatedBounds(g, b),
      xTargets = [
        0,
        b.width / 2,
        b.width,
        (b.width - Math.min(b.width, b.gridWidth)) / 2,
        (b.width + Math.min(b.width, b.gridWidth)) / 2,
      ],
      yTargets = [0, b.height / 2, b.height];
    for (const guide of b.guides)
      (guide.axis === "x" ? xTargets : yTargets).push(guide.position);
    for (const el of data.elements) {
      if (selection.has(el.id) || !resolve(el, mode).visible) continue;
      const o = rotatedBounds(resolve(el, mode).geometry, b);
      xTargets.push(o.x, o.x + o.width / 2, o.x + o.width);
      yTargets.push(o.y, o.y + o.height / 2, o.y + o.height);
    }
    if (grid) {
      const metrics = gridMetrics(b),
        start = (b.width - metrics.containerWidth) / 2 + metrics.margin;
      for (let c = 0; c <= metrics.columns; c++)
        xTargets.push(start + c * metrics.step);
    }
    const nearest = (points, targets, axis) => {
      let delta = 6 / zoom,
        found = 0,
        at = null;
      for (const a of points)
        for (const b of targets)
          if (Math.abs(b - a) < delta) {
            delta = Math.abs(b - a);
            found = b - a;
            at = b;
          }
      if (at !== null) smartLines.push({ axis, position: at });
      return found;
    };
    smartLines = [];
    return {
      ...g,
      x: g.x + nearest([r.x, r.x + r.width / 2, r.x + r.width], xTargets, "x"),
      y:
        g.y + nearest([r.y, r.y + r.height / 2, r.y + r.height], yTargets, "y"),
    };
  }
  function beginTextEditing(item, node) {
    if (!node || node.isContentEditable) return;
    lastTextPointer = null;
    node.contentEditable = "true";
    node.focus();
    node.onblur = () => {
      const runs = [];
      const walk = (n, marks = {}) => {
        if (n.nodeType === 3) {
          if (n.textContent) runs.push({ text: n.textContent, ...marks });
          return;
        }
        const tag = n.tagName?.toLowerCase();
        if (tag === "br") {
          runs.push({ text: "\n" });
          return;
        }
        const next = {
          ...marks,
          ...(["b", "strong"].includes(tag) ? { bold: true } : {}),
          ...(["i", "em"].includes(tag) ? { italic: true } : {}),
          ...(tag === "u" ? { underline: true } : {}),
        };
        for (const child of n.childNodes) walk(child, next);
        if (["div", "p"].includes(tag)) runs.push({ text: "\n" });
      };
      for (const child of node.childNodes) walk(child);
      setValue(item.element, "desktop", null, "runs", runs);
      setValue(item.element, "desktop", null, "content", node.innerText);
      changed();
    };
  }
  function beginPinch(event) {
    const pointerIds = [...touchPointers.keys()].slice(0, 2),
      [a, b] = pointerIds.map((id) => touchPointers.get(id));
    if (!a || !b) return;
    if (gesture?.before) data = gesture.before;
    gesture = null;
    render();
    const bounds = workspace.getBoundingClientRect(),
      center = { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
    gesture = {
      kind: "pinch",
      pointerIds,
      startDistance: Math.max(1, Math.hypot(a.x - b.x, a.y - b.y)),
      startZoom: zoom,
      anchor: {
        x:
          (workspace.scrollLeft + center.x - bounds.left - planeOffsetX) / zoom,
        y:
          (workspace.scrollTop + center.y - bounds.top - planeOffsetY) / zoom,
      },
    };
    workspace.setPointerCapture(event.pointerId);
    event.preventDefault();
  }
  workspace.addEventListener("pointerdown", (event) => {
    if (
      matchMedia("(max-width: 720px)").matches &&
      (zeroRoot.classList.contains("zero-left-open") ||
        zeroRoot.classList.contains("zero-inspector-open"))
    )
      closeMobilePanels();
    if (
      event.pointerType === "touch" &&
      !preview &&
      !event.target.closest('[contenteditable="true"]')
    ) {
      touchPointers.set(event.pointerId, {
        x: event.clientX,
        y: event.clientY,
      });
      if (touchPointers.size === 2) {
        beginPinch(event);
        return;
      }
      if (touchPointers.size > 2) return;
    }
    if (
      event.button !== 0 ||
      preview ||
      event.target.closest('[contenteditable="true"]')
    )
      return;
    flushField();
    history.push(data);
    if (space) {
      gesture = {
        kind: "pan",
        x: event.clientX,
        y: event.clientY,
        left: workspace.scrollLeft,
        top: workspace.scrollTop,
      };
      workspace.setPointerCapture(event.pointerId);
      event.preventDefault();
      return;
    }
    const ruler = event.target.closest(".zero-ruler-x,.zero-ruler-y");
    if (ruler) {
      const b = copy(data),
        r = canvas.getBoundingClientRect(),
        axis = ruler.classList.contains("zero-ruler-x") ? "x" : "y";
      board().guides.push({
        axis,
        position: Math.round(
          (axis === "x" ? event.clientX - r.left : event.clientY - r.top) /
            zoom,
        ),
      });
      gesture = {
        kind: "guide",
        original: copy(board().guides.at(-1)),
        index: board().guides.length - 1,
        x: event.clientX,
        y: event.clientY,
        before: b,
      };
      workspace.setPointerCapture(event.pointerId);
      drawCanvas();
      return;
    }
    const guide = event.target.closest("[data-guide]");
    if (guide) {
      gesture = {
        kind: "guide",
        index: Number(guide.dataset.guide),
        x: event.clientX,
        y: event.clientY,
        before: copy(data),
      };
      workspace.setPointerCapture(event.pointerId);
      return;
    }
    let node = event.target.closest("[data-ze]");
    const handle = event.target.closest("[data-handle]")?.dataset.handle,
      overlay = event.target.closest("[data-for]");
    let item = overlay
      ? findElement(data, overlay.dataset.for)
      : node && findElement(data, node.dataset.ze);
    if (overlay)
      node = canvas.querySelector(`[data-ze="${overlay.dataset.for}"]`);
    if (
      item?.parent &&
      !event.metaKey &&
      !event.ctrlKey &&
      !selection.has(item.element.id)
    ) {
      let ancestor = item.parent;
      while (
        findElement(data, ancestor.id)?.parent &&
        !selection.has(ancestor.id)
      )
        ancestor = findElement(data, ancestor.id).parent;
      item = findElement(data, ancestor.id);
      node = canvas.querySelector(`[data-ze="${ancestor.id}"]`);
    }

    if (item?.element.type === "text" && !item.element.locked && !handle) {
      const now = performance.now(),
        repeated =
          lastTextPointer?.id === item.element.id &&
          now - lastTextPointer.time <= 600 &&
          Math.hypot(
            event.clientX - lastTextPointer.x,
            event.clientY - lastTextPointer.y,
          ) <= 6;
      lastTextPointer = repeated
        ? null
        : { id: item.element.id, time: now, x: event.clientX, y: event.clientY };
      if (repeated) {
        selection = new Set([item.element.id]);
        beginTextEditing(item, canvas.querySelector(`[data-ze="${item.element.id}"]`));
        return;
      }
    } else lastTextPointer = null;

    if (item && !item.element.locked) {
      if (event.shiftKey && !handle) {
        if (selection.has(item.element.id)) selection.delete(item.element.id);
        else selection.add(item.element.id);
      } else if (!selection.has(item.element.id))
        selection = new Set([item.element.id]);
      const snapshots = selected()
        .filter((i) => !i.element.locked)
        .filter((i) => {
          let p = i.parent;
          while (p) {
            if (selection.has(p.id)) return false;
            p = findElement(data, p.id)?.parent;
          }
          return true;
        })
        .map((i) => ({
          id: i.element.id,
          g: {
            ...copy(resolve(i.element, mode).geometry),
            ...(resolve(i.element, mode).style.autoHeight
              ? {
                  height: canvas.querySelector(`[data-ze="${i.element.id}"]`)
                    .offsetHeight,
                }
              : {}),
          },
          rotation: parentRotation(i),
        }));
      const box = node.getBoundingClientRect(),
        gg = resolve(item.element, mode).geometry,
        corner = rotatePoint(
          (0.5 - gg.originX) * gg.width,
          (0.5 - gg.originY) * gg.height,
          gg.rotation + parentRotation(item),
        ),
        cx = box.left + box.width / 2 - corner.x * zoom,
        cy = box.top + box.height / 2 - corner.y * zoom;
      gesture = {
        kind: handle === "rotate" ? "rotate" : handle ? "resize" : "drag",
        handle,
        x: event.clientX,
        y: event.clientY,
        before: copy(data),
        snapshots,
        cx,
        cy,
        angle:
          (Math.atan2(event.clientY - cy, event.clientX - cx) * 180) / Math.PI,
      };
      render();
    } else {
      if (!event.shiftKey) selection.clear();
      const r = canvas.getBoundingClientRect();
      gesture = {
        kind: "marquee",
        x: event.clientX,
        y: event.clientY,
        startX: (event.clientX - r.left) / zoom,
        startY: (event.clientY - r.top) / zoom,
      };
      render();
    }
    workspace.setPointerCapture(event.pointerId);
    event.preventDefault();
  });
  workspace.addEventListener(
    "wheel",
    (event) => {
      if (
        (!event.ctrlKey && !event.metaKey) ||
        preview ||
        event.target.closest('[contenteditable="true"],input,select,textarea')
      )
        return;
      event.preventDefault();
      const unit =
        event.deltaMode === 1
          ? 16
          : event.deltaMode === 2
            ? workspace.clientHeight
            : 1;
      zoomAt(
        zoom * Math.exp(-event.deltaY * unit * 0.002),
        event.clientX,
        event.clientY,
      );
    },
    { passive: false },
  );
  function updatePointer(event) {
    if (!gesture) return;
    const task = gesture;
    if (task.kind === "pinch") {
      const [a, b] = task.pointerIds.map((id) => touchPointers.get(id));
      if (!a || !b) return;
      zoomAt(
        task.startZoom *
          (Math.hypot(a.x - b.x, a.y - b.y) / task.startDistance),
        (a.x + b.x) / 2,
        (a.y + b.y) / 2,
        task.anchor,
      );
      return;
    }
    const dx = event.clientX - task.x,
      dy = event.clientY - task.y;
    if (["drag", "resize", "rotate"].includes(task.kind) && !task.moved) {
      // A click also ends with pointerup. Do not treat it as a zero-distance
      // drag: snapGeometry can otherwise move an element simply on selection.
      if (Math.hypot(dx, dy) < 1.5) return;
      task.moved = true;
      lastTextPointer = null;
    }
    if (task.kind === "pan") {
      workspace.scrollLeft = task.left - dx;
      workspace.scrollTop = task.top - dy;
      return;
    }
    if (task.kind === "guide") {
      const original =
          task.original || task.before.artboards[mode].guides[task.index],
        g = board().guides[task.index];
      g.position = Math.round(
        original.position + (original.axis === "x" ? dx : dy) / zoom,
      );
      const line = $(`.zero-guides [data-guide="${task.index}"]`);
      if (line) line.style[g.axis === "x" ? "left" : "top"] = g.position + "px";
      return;
    }
    if (task.kind === "marquee") {
      const box = $(".zero-marquee");
      box.hidden = false;
      box.style.cssText = `left:${task.startX + Math.min(0, dx / zoom)}px;top:${task.startY + Math.min(0, dy / zoom)}px;width:${Math.abs(dx / zoom)}px;height:${Math.abs(dy / zoom)}px`;
      task.end = {
        x: task.startX + Math.min(0, dx / zoom),
        y: task.startY + Math.min(0, dy / zoom),
        width: Math.abs(dx / zoom),
        height: Math.abs(dy / zoom),
      };
      return;
    }
    for (const snapshot of task.snapshots) {
      const item = findElement(data, snapshot.id);
      if (!item) continue;
      const d = screenDelta(dx, dy, zoom, snapshot.rotation);
      let next;
      if (task.kind === "resize") {
        if (
          /[ns]/.test(task.handle) &&
          resolve(item.element, mode).style.autoHeight
        )
          setValue(item.element, mode, "style", "autoHeight", false);
        next = resizeGeometry(
          snapshot.g,
          d.x,
          d.y,
          task.handle,
          item.element.keepAspect || event.shiftKey,
        );
      } else if (task.kind === "rotate")
        next = {
          ...snapshot.g,
          rotation:
            snapshot.g.rotation +
            (Math.atan2(event.clientY - task.cy, event.clientX - task.cx) *
              180) /
              Math.PI -
            task.angle,
        };
      else
        next = { ...snapshot.g, x: snapshot.g.x + d.x, y: snapshot.g.y + d.y };
      if (task.kind === "drag" && !event.altKey)
        next = snapGeometry(item, next);
      setGeometry(item.element, next);
      if (task.kind === "resize" && item.element.type === "group")
        scaleGroupChildren(
          item.element,
          findElement(task.before, snapshot.id).element,
          mode,
        );
    }
    drawGesture(task);
  }
  workspace.addEventListener("pointermove", (event) => {
    if (event.pointerType === "touch" && touchPointers.has(event.pointerId))
      touchPointers.set(event.pointerId, {
        x: event.clientX,
        y: event.clientY,
      });
    cancelAnimationFrame(frame);
    frame = requestAnimationFrame(() => updatePointer(event));
  });
  workspace.addEventListener("pointerup", (event) => {
    if (event.pointerType === "touch") touchPointers.delete(event.pointerId);
    if (gesture?.kind === "pinch") {
      if (!gesture.pointerIds.includes(event.pointerId)) return;
      cancelAnimationFrame(frame);
      gesture = null;
      if (workspace.hasPointerCapture(event.pointerId))
        workspace.releasePointerCapture(event.pointerId);
      return;
    }
    if (!gesture) return;
    cancelAnimationFrame(frame);
    updatePointer(event);
    const task = gesture;
    gesture = null;
    if (task.kind === "marquee" && task.end) {
      const q = task.end;
      for (const el of data.elements) {
        const e = resolve(el, mode),
          r = rect(e.geometry, board());
        if (
          !el.locked &&
          e.visible &&
          r.x >= q.x &&
          r.y >= q.y &&
          r.x + r.width <= q.x + q.width &&
          r.y + r.height <= q.y + q.height
        )
          selection.add(el.id);
      }
      $(".zero-marquee").hidden = true;
      render();
    } else if (
      task.before &&
      JSON.stringify(task.before) !== JSON.stringify(data)
    ) {
      if (task.kind === "resize")
        for (const snapshot of task.snapshots) {
          const item = findElement(data, snapshot.id);
          if (item?.element.type === "group")
            scaleGroupChildren(
              item.element,
              findElement(task.before, snapshot.id).element,
              mode,
            );
        }
      changed();
    } else render();
    if (workspace.hasPointerCapture(event.pointerId))
      workspace.releasePointerCapture(event.pointerId);
  });
  workspace.addEventListener("pointercancel", (event) => {
    if (event.pointerType === "touch") touchPointers.delete(event.pointerId);
    cancelAnimationFrame(frame);
    if (gesture?.kind === "pinch") {
      if (gesture.pointerIds.includes(event.pointerId)) gesture = null;
      return;
    }
    if (gesture?.before) data = gesture.before;
    gesture = null;
    render();
  });
  app.addEventListener("contextmenu", (event) => {
    const node = event.target.closest("[data-ze],[data-layer],[data-for]");
    if (!node) return;
    const id = node.dataset.ze || node.dataset.layer || node.dataset.for;
    event.preventDefault();
    if (!selection.has(id)) selection = new Set([id]);
    render();
    const menu = $("#zero-context");
    menu.hidden = false;
    menu.style.left = Math.min(event.clientX, innerWidth - 180) + "px";
    menu.style.top = Math.min(event.clientY, innerHeight - 320) + "px";
  });
  workspace.addEventListener("dblclick", (event) => {
    if (event.target.closest(".zero-ruler-x,.zero-ruler-y")) {
      const bounds = canvas.getBoundingClientRect(),
        axis = event.target.closest(".zero-ruler-x") ? "x" : "y";
      board().guides.push({
        axis,
        position: Math.round(
          (axis === "x"
            ? event.clientX - bounds.left
            : event.clientY - bounds.top) / zoom,
        ),
      });
      changed();
      return;
    }
    const node =
      event.target.closest("[data-ze]") ||
      document.elementFromPoint(event.clientX, event.clientY)?.closest("[data-ze]");
    const item = node && findElement(data, node.dataset.ze);
    if (
      !item ||
      item.element.locked ||
      item.element.type !== "text" ||
      preview ||
      node.isContentEditable
    )
      return;
    selection = new Set([item.element.id]);
    beginTextEditing(item, node);
  });
  $(".zero-brand").onclick = async (event) => {
    event.preventDefault();
    await save();
    if (
      dirty &&
      !window.confirm("Изменения не сохранены. Вернуться к странице?")
    )
      return;
    location.assign(`/projects/${projectId}/pages/${pageId}/edit`);
  };
  const onKey = (event) => {
    const editing =
      event.target instanceof Element &&
      event.target.closest('input,textarea,select,[contenteditable="true"]');
    if (editing) return;
    const mod = event.metaKey || event.ctrlKey,
      key = event.key.toLowerCase();
    if (event.code === "Space") {
      space = true;
      event.preventDefault();
      return;
    }
    if (key === "escape") {
      if (!preferencesMenu.hidden) {
        closePreferencesMenu({ restoreFocus: true });
        return;
      }
      if (!elementMenu.hidden) {
        closeElementMenu({ restoreFocus: true });
        return;
      }
      if (gesture?.before) data = gesture.before;
      gesture = null;
      selection.clear();
      $("#zero-context").hidden = true;
      render();
      return;
    }
    if (mod && (event.key === "+" || event.key === "=")) {
      event.preventDefault();
      action("zoom-in");
      return;
    }
    if (mod && key === "-") {
      event.preventDefault();
      action("zoom-out");
      return;
    }
    if (!mod && !event.shiftKey && key === "g") {
      event.preventDefault();
      action("grid");
      return;
    }
    if (!mod && event.shiftKey && key === "r") {
      event.preventDefault();
      action("rulers");
      return;
    }
    const shortcut = mod
      ? {
          z: event.shiftKey ? "redo" : "undo",
          y: "redo",
          s: "save",
          l: "layers",
          c: "copy",
          v: "paste",
          d: "duplicate",
          g: event.shiftKey ? "ungroup" : "group",
        }[key]
      : null;
    if (shortcut) {
      event.preventDefault();
      action(shortcut);
      return;
    }
    if (!mod && ["t", "r", "b"].includes(key)) {
      event.preventDefault();
      add({ t: "text", r: "rectangle", b: "button" }[key]);
      return;
    }
    if (!mod && event.shiftKey && key === "i") {
      event.preventDefault();
      add("image");
      return;
    }
    if (mod && key === "a") {
      event.preventDefault();
      selection = new Set(
        data.elements.filter((el) => !el.locked).map((el) => el.id),
      );
      render();
      return;
    }
    if (["delete", "backspace"].includes(key)) {
      event.preventDefault();
      action("delete");
      return;
    }
    if (["arrowleft", "arrowright", "arrowup", "arrowdown"].includes(key)) {
      event.preventDefault();
      const step = event.shiftKey ? 10 : 1;
      for (const i of selected()) {
        if (i.element.locked) continue;
        const g = resolve(i.element, mode).geometry,
          k = ["arrowleft", "arrowright"].includes(key) ? "x" : "y";
        setValue(
          i.element,
          mode,
          "geometry",
          k,
          g[k] + (["arrowleft", "arrowup"].includes(key) ? -step : step),
        );
      }
      changed();
    }
  };
  const onUp = (event) => {
      if (event.code === "Space") space = false;
    },
    beforeUnload = (e) => {
      if (dirty) {
        e.preventDefault();
        e.returnValue = "";
      }
    };
  document.addEventListener("keydown", onKey);
  document.addEventListener("keyup", onUp);
  window.addEventListener("beforeunload", beforeUnload);
  window.addEventListener(
    "pagehide",
    () => {
      destroyed = true;
      clearTimeout(timer);
      clearTimeout(fieldTimer);
      cancelAnimationFrame(frame);
      channel.close();
      document.removeEventListener("keydown", onKey);
      document.removeEventListener("keyup", onUp);
      document.removeEventListener("pointerdown", onElementMenuOutside);
      document.removeEventListener("pointerdown", onPreferencesMenuOutside);
      window.removeEventListener("beforeunload", beforeUnload);
      window.removeEventListener("resize", onSidebarViewportResize);
    },
    { once: true },
  );
  channel.onmessage = (event) => {
    if (
      event.data?.type === "zero:saved" &&
      event.data.blockId === blockId &&
      event.data.version > version
    ) {
      conflict = true;
      clearTimeout(timer);
      $('[data-action="rebase"]').hidden = false;
      status("Zero Block сохранён в другом окне · проверьте конфликт");
    }
  };
  const initialBounds = documentBounds(data, mode);
  zoom = fitZoom(initialBounds, 1);
  const initialExpandedBounds = expandedCanvasBounds(initialBounds);
  planeOffsetX = 12 - initialExpandedBounds.left * zoom;
  planeOffsetY = 12 - initialExpandedBounds.top * zoom;
  stagePaddingRight = stagePaddingBottom = 12;
  $("#zero-zoom").value = String(zoom);
  render();
}
