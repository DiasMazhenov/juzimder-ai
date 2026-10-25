const COMPONENT_IDREF_ATTRIBUTES = new Set([
  'aria-labelledby',
  'aria-describedby',
  'aria-controls',
  'aria-owns',
  'aria-flowto',
  'aria-details',
  'aria-errormessage',
  'aria-activedescendant',
  'for',
  'form',
  'list',
  'headers',
  'itemref',
]);

function getChildren(component) {
  const children = component?.components?.();
  return children?.models || [];
}

function isPageRoot(component) {
  return component?.get?.('type') === 'wrapper';
}

export function remapCopiedReferences(source, copy) {
  if (!source || !copy) return;

  const idMap = new Map();
  const collectIds = (original, duplicate) => {
    const originalId = original?.getId?.();
    const duplicateId = duplicate?.getId?.();
    if (typeof originalId === 'string' && typeof duplicateId === 'string' && originalId !== duplicateId) {
      idMap.set(originalId, duplicateId);
    }
    const originalChildren = getChildren(original);
    const duplicateChildren = getChildren(duplicate);
    originalChildren.forEach((child, index) => collectIds(child, duplicateChildren[index]));
  };
  collectIds(source, copy);
  if (!idMap.size) return;

  const remapTokens = value => value.split(/\s+/).map(token => idMap.get(token) || token).join(' ');
  const remapFragmentUrls = value => value.replace(
    /url\(\s*(["']?)#([^"'()\s]+)\1\s*\)/gi,
    (match, quote, id) => {
      const replacement = idMap.get(id);
      return replacement ? `url(${quote}#${replacement}${quote})` : match;
    },
  );
  const remapAttribute = (name, value) => {
    if (typeof value !== 'string') return value;
    if (name === 'href' || name === 'xlink:href' || name === 'usemap') {
      if (!value.startsWith('#')) return value;
      const replacement = idMap.get(value.slice(1));
      return replacement ? `#${replacement}` : value;
    }
    return COMPONENT_IDREF_ATTRIBUTES.has(name) ? remapTokens(value) : remapFragmentUrls(value);
  };
  const updateReferences = component => {
    const attributes = component.get?.('attributes') || {};
    const updated = {};
    for (const [name, value] of Object.entries(attributes)) {
      const remapped = remapAttribute(name, value);
      if (remapped !== value) updated[name] = remapped;
    }
    if (Object.keys(updated).length) component.addAttributes(updated);
    const styles = component.getStyle?.() || {};
    const updatedStyles = {};
    for (const [name, value] of Object.entries(styles)) {
      if (typeof value !== 'string') continue;
      const remapped = remapFragmentUrls(value);
      if (remapped !== value) updatedStyles[name] = remapped;
    }
    if (Object.keys(updatedStyles).length) component.addStyle(updatedStyles);
    getChildren(component).forEach(updateReferences);
  };
  updateReferences(copy);
}

function getClipboard(editor) {
  return editor.getModel?.().get?.('clipboard') || [];
}

export function findLayerComponentAtTarget(rootComponent, target) {
  const visit = component => {
    for (const child of getChildren(component)) {
      const nested = visit(child);
      if (nested) return nested;
    }
    const layerElement = component?.viewLayer?.el;
    return layerElement && (layerElement === target || layerElement.contains?.(target)) ? component : null;
  };
  return visit(rootComponent);
}

function pasteFromClipboard(editor, sources, action = 'paste-component') {
  const pasted = [];
  const onPaste = component => pasted.push(component);
  editor.on('component:paste', onPaste);
  try {
    editor.runCommand('core:paste', { action });
  } finally {
    editor.off('component:paste', onPaste);
  }
  pasted.forEach((component, index) => remapCopiedReferences(sources[index], component));
  const last = pasted.at(-1);
  if (last) editor.select(last);
  return pasted;
}

export function installComponentActions({ editor, confirmDelete, notify }) {
  const trigger = document.createElement('button');
  trigger.type = 'button';
  trigger.id = 'component-actions-trigger';
  trigger.className = 'panel-close component-actions-trigger';
  trigger.textContent = '⋯';
  trigger.title = 'Действия с элементом';
  trigger.setAttribute('aria-label', 'Действия с выбранным элементом');
  trigger.setAttribute('aria-haspopup', 'menu');
  trigger.setAttribute('aria-expanded', 'false');
  document.querySelector('.editor-right .panel-heading-with-close')?.insertBefore(trigger, document.querySelector('#close-inspector'));

  const menu = document.createElement('div');
  menu.id = 'component-context-menu';
  menu.className = 'component-context-menu';
  menu.setAttribute('role', 'menu');
  menu.setAttribute('aria-label', 'Действия с элементом');
  menu.hidden = true;
  const entries = [
    ['cut', 'Вырезать', '⌘/Ctrl+X'],
    ['copy', 'Копировать', '⌘/Ctrl+C'],
    ['paste', 'Вставить рядом', '⌘/Ctrl+V'],
    ['duplicate', 'Дублировать', ''],
    ['separator', '', ''],
    ['move-up', 'Переместить выше', ''],
    ['move-down', 'Переместить ниже', ''],
    ['select-parent', 'Выбрать родителя', ''],
    ['separator', '', ''],
    ['delete', 'Удалить', ''],
  ];
  for (const [action, label, shortcut] of entries) {
    if (action === 'separator') {
      const separator = document.createElement('div');
      separator.setAttribute('role', 'separator');
      menu.append(separator);
      continue;
    }
    const button = document.createElement('button');
    button.type = 'button';
    button.dataset.componentAction = action;
    button.setAttribute('role', 'menuitem');
    button.textContent = label;
    if (shortcut) {
      const hint = document.createElement('span');
      hint.textContent = shortcut;
      button.append(hint);
    }
    if (action === 'delete') button.classList.add('danger-action');
    menu.append(button);
  }
  document.body.append(menu);

  let isOpen = false;
  const close = ({ restoreFocus = false } = {}) => {
    if (!isOpen) return;
    isOpen = false;
    menu.hidden = true;
    trigger.setAttribute('aria-expanded', 'false');
    if (restoreFocus) trigger.focus();
  };

  const stateFor = component => {
    const root = isPageRoot(component);
    const parent = component?.parent?.();
    const index = component?.index?.() ?? -1;
    const siblings = parent?.components?.().length ?? 0;
    const canEdit = !!component && !root;
    const hasClipboard = getClipboard(editor).length > 0;
    return {
      cut: canEdit && component.get('removable') !== false,
      copy: canEdit && component.get('copyable') !== false,
      paste: hasClipboard,
      duplicate: canEdit && component.get('copyable') !== false && component.get('removable') !== false,
      'move-up': canEdit && !!parent && index > 0,
      'move-down': canEdit && !!parent && index >= 0 && index < siblings - 1,
      'select-parent': !!parent,
      delete: canEdit && component.get('removable') !== false,
    };
  };

  const renderState = () => {
    const component = editor.getSelected();
    const state = stateFor(component);
    for (const button of menu.querySelectorAll('[data-component-action]')) {
      button.disabled = !state[button.dataset.componentAction];
    }
    trigger.disabled = !component;
  };

  const open = (x, y, component = editor.getSelected(), focus = true) => {
    if (component && component !== editor.getSelected()) editor.select(component);
    if (!editor.getSelected()) return;
    renderState();
    menu.hidden = false;
    isOpen = true;
    trigger.setAttribute('aria-expanded', 'true');
    const width = menu.offsetWidth;
    const height = menu.offsetHeight;
    menu.style.left = `${Math.max(8, Math.min(x, window.innerWidth - width - 8))}px`;
    menu.style.top = `${Math.max(8, Math.min(y, window.innerHeight - height - 8))}px`;
    if (focus) menu.querySelector('[data-component-action]:not(:disabled)')?.focus({ preventScroll: true });
  };

  const runAction = async action => {
    const component = editor.getSelected();
    if (!component) return;
    close();
    switch (action) {
      case 'copy':
        editor.runCommand('core:copy');
        notify('Скопировано в буфер редактора');
        break;
      case 'cut':
        editor.runCommand('core:copy');
        component.remove();
        notify('Элемент вырезан · вставьте его в другом месте или отмените действие');
        break;
      case 'paste': {
        const sources = getClipboard(editor).slice();
        const pasted = pasteFromClipboard(editor, sources);
        if (pasted.length) notify('Элемент вставлен рядом');
        break;
      }
      case 'duplicate': {
        editor.runCommand('core:copy');
        const sources = getClipboard(editor).slice();
        const pasted = pasteFromClipboard(editor, sources, 'clone-component');
        if (pasted.length) notify('Копия создана');
        break;
      }
      case 'move-up':
      case 'move-down': {
        const parent = component.parent();
        const targetIndex = component.index() + (action === 'move-up' ? -1 : 2);
        component.move(parent, { at: targetIndex });
        editor.select(component);
        notify(action === 'move-up' ? 'Элемент перемещён выше' : 'Элемент перемещён ниже');
        break;
      }
      case 'select-parent': {
        const parent = component.parent();
        if (parent) editor.select(parent);
        close();
        break;
      }
      case 'delete': {
        const name = component.getName?.() || 'элемент';
        if (!await confirmDelete(`Удалить «${name}»? Это действие можно отменить через Undo.`, 'Удалить')) break;
        component.remove();
        notify('Элемент удалён · отмените действие через Undo');
        break;
      }
    }
    renderState();
  };

  menu.addEventListener('click', event => {
    const button = event.target.closest('[data-component-action]');
    if (button && !button.disabled) void runAction(button.dataset.componentAction);
  });
  trigger.addEventListener('click', event => {
    event.stopPropagation();
    if (isOpen) return close();
    const rect = trigger.getBoundingClientRect();
    open(rect.left, rect.bottom + 6);
  });
  const handlePointerdown = event => {
    if (isOpen && !menu.contains(event.target) && event.target !== trigger) close();
  };
  const handleKeydown = event => {
    if (!isOpen) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      event.stopPropagation();
      close({ restoreFocus: true });
      return;
    }
    const buttons = [...menu.querySelectorAll('[data-component-action]:not(:disabled)')];
    const index = buttons.indexOf(document.activeElement);
    let next = null;
    if (event.key === 'ArrowDown') next = buttons[(index + 1 + buttons.length) % buttons.length];
    else if (event.key === 'ArrowUp') next = buttons[(index - 1 + buttons.length) % buttons.length];
    else if (event.key === 'Home') next = buttons[0];
    else if (event.key === 'End') next = buttons.at(-1);
    if (next) {
      event.preventDefault();
      next.focus();
    }
  };
  document.addEventListener('pointerdown', handlePointerdown, true);
  document.addEventListener('keydown', handleKeydown, true);

  const attachedDocuments = new WeakSet();
  const layerManager = document.querySelector('#layer-manager');
  const handleLayerContextMenu = event => {
    const component = findLayerComponentAtTarget(editor.getWrapper(), event.target);
    if (!component || isPageRoot(component)) return;
    event.preventDefault();
    event.stopPropagation();
    open(event.clientX, event.clientY, component);
  };
  layerManager?.addEventListener('contextmenu', handleLayerContextMenu, true);

  const attachCanvasContextMenu = frame => {
    const frameWindow = frame?.window;
    const frameDocument = frameWindow?.document;
    const frameElement = frameWindow?.frameElement;
    if (!frameDocument?.body || !frameElement || attachedDocuments.has(frameDocument)) return;
    attachedDocuments.add(frameDocument);
    frameDocument.body.addEventListener('contextmenu', event => {
      const element = event.target.closest?.('[data-gjs-type][id]');
      const component = element?.id ? editor.DomComponents.getById(element.id) : null;
      if (!component) return;
      event.preventDefault();
      event.stopPropagation();
      const rect = frameElement.getBoundingClientRect();
      const scaleX = rect.width / (frameElement.clientWidth || rect.width);
      const scaleY = rect.height / (frameElement.clientHeight || rect.height);
      open(rect.left + event.clientX * scaleX, rect.top + event.clientY * scaleY, component);
    }, true);
    frameDocument.addEventListener('pointerdown', () => close(), true);
  };

  editor.on('component:selected component:deselected component:add component:remove', renderState);
  editor.on('canvas:frame:load:body', attachCanvasContextMenu);
  editor.on('canvas:frame:load', attachCanvasContextMenu);
  trigger.disabled = !editor.getSelected();

  return {
    close,
    destroy() {
      close();
      editor.off('component:selected component:deselected component:add component:remove', renderState);
      editor.off('canvas:frame:load:body', attachCanvasContextMenu);
      editor.off('canvas:frame:load', attachCanvasContextMenu);
      document.removeEventListener('pointerdown', handlePointerdown, true);
      document.removeEventListener('keydown', handleKeydown, true);
      layerManager?.removeEventListener('contextmenu', handleLayerContextMenu, true);
      trigger.remove();
      menu.remove();
    },
  };
}
