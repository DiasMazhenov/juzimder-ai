import {
  createElement,
  copy,
  resolve,
  rect,
  findElement,
  flatten,
  rotatePoint,
} from "./model.js";
import { rotatedBounds } from "./transforms.js";
const selected = (data, ids) =>
  ids.map((id) => findElement(data, id)).filter(Boolean);
function writeGeometry(element, mode, geometry) {
  if (mode === "desktop") element.geometry = geometry;
  else element.overrides[mode] = { ...element.overrides[mode], geometry };
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
