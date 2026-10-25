import { tablerIcon } from "../../editor-icons.js";

function menuItem({ type, label, iconName, shortcut, disabled = false }) {
  const attrs = disabled
    ? 'disabled aria-disabled="true" title="Этот тип элемента пока не поддерживается"'
    : `data-add="${type}"`;
  return `<button type="button" class="zero-element-item${disabled ? " is-planned" : ""}" role="menuitem" ${attrs}><span class="zero-element-icon">${tablerIcon(iconName)}</span><span class="zero-element-label">${label}</span>${shortcut ? `<kbd>${shortcut}</kbd>` : disabled ? '<span class="zero-element-planned">Скоро</span>' : ""}</button>`;
}

export function renderZeroElementMenu() {
  const primary = [
    { type: "text", label: "Текст", iconName: "typography", shortcut: "T" },
    { type: "image", label: "Фото", iconName: "photo", shortcut: "⇧ I" },
  ]
    .map(menuItem)
    .join("");
  const shapes = [
    ["rectangle", "Прямоугольник", "square"],
    ["ellipse", "Эллипс", "circle"],
    ["line", "Линия", "minus"],
  ]
    .map(
      ([type, label, iconName]) =>
        `<button type="button" class="zero-element-item zero-shape-choice" role="menuitem" data-add="${type}"><span class="zero-element-icon">${tablerIcon(iconName)}</span><span class="zero-element-label">${label}</span></button>`,
    )
    .join("");
  const vector = menuItem({
    type: "vector",
    label: "Вектор",
    iconName: "vector-bezier",
  });
  const customCode = menuItem({ type: "html", label: "HTML", iconName: "code" });
  const additional = [
    ["video", "Видео", "player-play"],
    ["tooltip", "Подсказка", "message"],
    ["form", "Форма", "list"],
    ["gallery", "Галерея", "stack-2"],
  ]
    .map(([type, label, iconName]) =>
      menuItem({ type, label, iconName, disabled: !["tooltip", "video", "form"].includes(type) }),
    )
    .join("");

  return `<div id="zero-element-menu" class="zero-element-menu" role="menu" aria-label="Добавить элемент" hidden><div class="zero-element-group">${primary}<button type="button" class="zero-element-item" data-action="shapes-menu" aria-expanded="false" aria-controls="zero-shape-menu"><span class="zero-element-icon">${tablerIcon("square")}</span><span class="zero-element-label">Фигура</span><kbd>R</kbd>${tablerIcon("chevron-down")}</button><div id="zero-shape-menu" class="zero-shape-menu" hidden>${shapes}</div>${menuItem({ type: "button", label: "Кнопка", iconName: "link", shortcut: "B" })}${vector}</div><div class="zero-element-separator" role="separator"></div><div class="zero-element-group"><div class="zero-element-caption">Дополнительные элементы</div>${customCode}</div><div class="zero-element-group zero-element-planned-group">${additional}</div><p class="zero-element-note">Форма — макет с отключённой отправкой данных. Галерея появится после добавления безопасного renderer.</p></div>`;
}
