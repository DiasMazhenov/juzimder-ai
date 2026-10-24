import {
  createElement,
  copy,
  resolve,
  rect,
  findElement,
  flatten,
  rotatePoint,
} from "./model.js";
import { multiply, rotatedBounds, worldMatrix } from "./transforms.js";
const selected = (data, ids) =>
  ids.map((id) => findElement(data, id)).filter(Boolean);
const identity = [1, 0, 0, 1, 0, 0];
function invert(matrix) {
  const [a, b, c, d, e, f] = matrix,
    determinant = a * d - b * c;
  if (!Number.isFinite(determinant) || Math.abs(determinant) < 1e-9)
    throw new Error("Нельзя переместить слой в вырожденную систему координат.");
  return [
    d / determinant,
    -b / determinant,
    -c / determinant,
    a / determinant,
    (c * f - d * e) / determinant,
    (b * e - a * f) / determinant,
  ];
}
function nestedGroupDepth(element) {
  if (element.type !== "group") return 0;
  return 1 + Math.max(0, ...(element.children || []).map(nestedGroupDepth));
}
function localGeometry(data, element, parent, mode, world, parentWorld) {
  const g = resolve(element, mode).geometry,
    local = parent ? multiply(invert(parentWorld), world) : world,
    rotation = Math.atan2(local[1], local[0]),
    cosine = Math.cos(rotation),
    sine = Math.sin(rotation),
    originX = g.originX * g.width,
    originY = g.originY * g.height,
    x = local[4] - originX + cosine * originX - sine * originY,
    y = local[5] - originY + sine * originX + cosine * originY,
    pg = parent && resolve(parent, mode).geometry,
    board = parent
      ? { width: pg.width, height: pg.height }
      : data.artboards[mode],
    regionWidth = parent
      ? board.width
      : g.container === "grid"
        ? Math.min(board.width, board.gridWidth)
        : board.width,
    start = parent || g.container === "window"
      ? 0
      : (board.width - regionWidth) / 2,
    round = (value) => Math.round(value * 1e6) / 1e6;
  return {
    ...g,
    x: round(x - start - g.anchorX * regionWidth + originX),
    y: round(y - g.anchorY * board.height + originY),
    rotation: round((rotation * 180) / Math.PI),
  };
}
function writeGeometry(element, mode, geometry) {
  if (mode === "desktop") element.geometry = geometry;
  else element.overrides[mode] = { ...element.overrides[mode], geometry };
}
export function moveLayer(data, id, parentId, index) {
  const source = findElement(data, id),
    destination = parentId ? findElement(data, parentId) : null;
  if (!source) throw new Error("Слой для перемещения не найден.");
  if (parentId && destination?.element.type !== "group")
    throw new Error("Слой можно поместить только в группу.");
  if (source.element.locked)
    throw new Error("Разблокируйте слой и его группу перед перемещением.");
  if (destination?.element.locked)
    throw new Error("Разблокируйте целевую группу перед перемещением.");
  let ancestor = destination?.element || null,
    destinationDepth = 0;
  while (ancestor) {
    if (ancestor.id === id)
      throw new Error("Нельзя поместить группу внутрь самой себя.");
    if (ancestor.locked)
      throw new Error("Разблокируйте целевую группу перед перемещением.");
    destinationDepth++;
    ancestor = findElement(data, ancestor.id)?.parent || null;
  }
  if (destinationDepth + nestedGroupDepth(source.element) > 8)
    throw new Error("Не более восьми уровней групп.");
  const nextList = destination ? destination.element.children : data.elements;
  if (!Array.isArray(nextList)) throw new Error("У целевой группы нет списка слоёв.");
  const oldIndex = source.list.indexOf(source.element),
    boundedIndex = Math.max(0, Math.min(nextList.length, Math.trunc(index))),
    nextIndex = source.list === nextList && oldIndex < boundedIndex
      ? boundedIndex - 1
      : boundedIndex;
  if (source.list === nextList && oldIndex === nextIndex) return false;
  let sourceAncestor = source.parent;
  while (sourceAncestor) {
    if (sourceAncestor.locked)
      throw new Error("Разблокируйте слой и его группу перед перемещением.");
    sourceAncestor = findElement(data, sourceAncestor.id)?.parent || null;
  }
  const modes = Object.keys(data.artboards),
    matrices = new Map(
      modes.map((mode) => [
        mode,
        {
          world: worldMatrix(data, id, mode),
          parent: destination
            ? worldMatrix(data, destination.element.id, mode)
            : identity,
        },
      ]),
    );
  source.list.splice(oldIndex, 1);
  nextList.splice(nextIndex, 0, source.element);
  for (const mode of modes) {
    const { world, parent } = matrices.get(mode);
    writeGeometry(
      source.element,
      mode,
      localGeometry(data, source.element, destination?.element, mode, world, parent),
    );
  }
  return true;
}
export function groupElements(data, ids, mode) {
  if (mode !== "desktop")
    throw new Error(
      "Создавайте структуру групп на Desktop; геометрия доступна во всех режимах.",
    );
  const items = selected(data, ids);
  if (
    items.length < 2 ||
    items.some((i) => i.parent !== items[0].parent || i.element.locked)
  )
    throw new Error("Выберите элементы одного уровня без блокировки.");
  if (flatten(data).length >= 300) throw new Error("Не более 300 элементов.");
  const subDepth = (el) =>
    el.children?.length ? 1 + Math.max(...el.children.map(subDepth)) : 0;
  let depth = 0,
    parent = items[0].parent;
  while (parent) {
    depth++;
    parent = findElement(data, parent.id)?.parent;
  }
  if (depth + 1 + Math.max(...items.map((i) => subDepth(i.element))) > 8)
    throw new Error("Не более восьми уровней групп.");
  parent = items[0].parent;
  const list = items[0].list,
    chosen = new Set(ids),
    group = createElement("group");
  group.children = list.filter((el) => chosen.has(el.id));
  const originals = new Map(group.children.map((el) => [el.id, copy(el)]));
  for (const mode of Object.keys(data.artboards)) {
    const pg = parent && resolve(parent, mode).geometry,
      pb = pg ? { width: pg.width, height: pg.height } : data.artboards[mode];
    const bounds = group.children.map((el) =>
      rotatedBounds(resolve(originals.get(el.id), mode).geometry, pb, !!parent),
    );
    const x = Math.min(...bounds.map((r) => r.x)),
      y = Math.min(...bounds.map((r) => r.y)),
      width = Math.max(1, Math.max(...bounds.map((r) => r.x + r.width)) - x),
      height = Math.max(1, Math.max(...bounds.map((r) => r.y + r.height)) - y);
    writeGeometry(group, mode, {
      ...group.geometry,
      container: "window",
      anchorX: 0,
      anchorY: 0,
      originX: 0,
      originY: 0,
      x,
      y,
      width,
      height,
      rotation: 0,
    });
    for (const child of group.children) {
      const g = resolve(originals.get(child.id), mode).geometry,
        r = rect(g, pb, !!parent);
      writeGeometry(child, mode, {
        ...g,
        container: "window",
        anchorX: 0,
        anchorY: 0,
        x: r.x - x + g.originX * g.width,
        y: r.y - y + g.originY * g.height,
      });
    }
  }
  const last = Math.max(...items.map((i) => list.indexOf(i.element))),
    insert = list.slice(0, last + 1).filter((el) => !chosen.has(el.id)).length,
    rest = list.filter((el) => !chosen.has(el.id));
  rest.splice(insert, 0, group);
  list.splice(0, list.length, ...rest);
  return [group.id];
}
export function ungroupElements(data, ids, mode) {
  if (mode !== "desktop")
    throw new Error("Разгруппировка доступна на Desktop.");
  const result = [];
  for (const item of selected(data, ids).filter(
    (i) => i.element.type === "group" && !i.element.locked,
  )) {
    const group = item.element,
      originals = new Map(group.children.map((el) => [el.id, copy(el)]));
    for (const child of group.children)
      for (const mode of Object.keys(data.artboards)) {
        const gg = resolve(group, mode).geometry,
          cg = resolve(originals.get(child.id), mode).geometry,
          pg = item.parent && resolve(item.parent, mode).geometry,
          pb = pg
            ? { width: pg.width, height: pg.height }
            : data.artboards[mode],
          gr = rect(gg, pb, !!item.parent),
          cr = rect(cg, { width: gg.width, height: gg.height }, true),
          pivot = {
            x: gr.x + gg.originX * gg.width,
            y: gr.y + gg.originY * gg.height,
          },
          v = rotatePoint(
            cr.x + cg.originX * cg.width - gg.originX * gg.width,
            cr.y + cg.originY * cg.height - gg.originY * gg.height,
            gg.rotation,
          );
        writeGeometry(child, mode, {
          ...cg,
          container: "window",
          anchorX: 0,
          anchorY: 0,
          x: pivot.x + v.x,
          y: pivot.y + v.y,
          rotation: cg.rotation + gg.rotation,
        });
      }
    item.list.splice(item.list.indexOf(group), 1, ...group.children);
    result.push(...group.children.map((c) => c.id));
  }
  return result;
}
