import { resolve, rect, rotatePoint, findElement } from "./model.js";
export function multiply(a, b) {
  return [
    a[0] * b[0] + a[2] * b[1],
    a[1] * b[0] + a[3] * b[1],
    a[0] * b[2] + a[2] * b[3],
    a[1] * b[2] + a[3] * b[3],
    a[0] * b[4] + a[2] * b[5] + a[4],
    a[1] * b[4] + a[3] * b[5] + a[5],
  ];
}
export function localMatrix(g, board, nested = false) {
  const r = rect(g, board, nested),
    a = (g.rotation * Math.PI) / 180,
    c = Math.cos(a),
    s = Math.sin(a),
    ox = g.originX * g.width,
    oy = g.originY * g.height;
  return [c, s, -s, c, r.x + ox - c * ox + s * oy, r.y + oy - s * ox - c * oy];
}
export function worldMatrix(data, id, mode, dimensions = null) {
  const item = findElement(data, id);
  if (!item) return [1, 0, 0, 1, 0, 0];
  let g = resolve(item.element, mode).geometry;
  if (dimensions) g = { ...g, ...dimensions };
  const parent = item.parent,
    parentG = parent && resolve(parent, mode).geometry,
    board = parent
      ? { width: parentG.width, height: parentG.height }
      : data.artboards[mode];
  const local = localMatrix(g, board, !!parent);
  return parent ? multiply(worldMatrix(data, parent.id, mode), local) : local;
}
export function rotatedBounds(g, board, nested = false) {
  const m = localMatrix(g, board, nested),
    points = [
      [0, 0],
      [g.width, 0],
      [0, g.height],
      [g.width, g.height],
    ].map(([x, y]) => ({
      x: m[0] * x + m[2] * y + m[4],
      y: m[1] * x + m[3] * y + m[5],
    }));
  const x = Math.min(...points.map((p) => p.x)),
    y = Math.min(...points.map((p) => p.y));
  return {
    x,
    y,
    width: Math.max(...points.map((p) => p.x)) - x,
    height: Math.max(...points.map((p) => p.y)) - y,
  };
}

export function scaleGroupChildren(group, original, mode) {
  const from = resolve(original, mode).geometry,
    to = resolve(group, mode).geometry,
    sx = to.width / from.width,
    sy = to.height / from.height;
  for (const child of group.children || []) {
    const old = original.children.find((c) => c.id === child.id),
      g = resolve(old, mode).geometry,
      r = rect(g, { width: from.width, height: from.height }, true),
      next = { ...g, width: g.width * sx, height: g.height * sy };
    const base = rect(
      { ...next, x: 0, y: 0 },
      { width: to.width, height: to.height },
      true,
    );
    next.x = r.x * sx - base.x;
    next.y = r.y * sy - base.y;
    const target = mode === "desktop" ? child : (child.overrides[mode] ??= {});
    target.geometry = { ...target.geometry, ...next };
    const style = resolve(old, mode).style,
      scale = Math.sqrt(sx * sy);
    for (const key of [
      "fontSize",
      "radius",
      "strokeWidth",
      "shadowBlur",
      "shadowX",
      "shadowY",
    ])
      if (typeof style[key] === "number") {
        target.style ??= {};
        target.style[key] = style[key] * scale;
      }
    if (child.type === "group") scaleGroupChildren(child, old, mode);
  }
}
