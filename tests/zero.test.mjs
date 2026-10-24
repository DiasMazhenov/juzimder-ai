import {
  worldMatrix,
  scaleGroupChildren,
  rotatedBounds,
} from "../src/zero/model/transforms.js";
import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { execFileSync } from "node:child_process";
import Ajv2020 from "ajv/dist/2020.js";
import {
  createZeroData,
  createElement,
  resolve,
  setValue,
  rect,
  anchorGeometry,
  rotatePoint,
  resizeGeometry,
  screenDelta,
  geometryCSS,
  History,
  adapt,
} from "../src/zero/model/model.js";
import { serializedZero, sameOutsideZero } from "../src/zero/sync.js";
import { renderZero } from "../src/zero/render.js";
const fixture = JSON.parse(
  readFileSync(new URL("./zero.fixture.json", import.meta.url)),
);
const epsilon = (a, b) => assert.ok(Math.abs(a - b) < 0.00001, `${a} ≠ ${b}`);
test("absolute movement does not affect siblings; negative coordinates and independent height", () => {
  const d = structuredClone(fixture),
    before = structuredClone(d.elements[1]);
  d.elements[0].geometry.x = -150;
  d.elements[0].geometry.width = 900;
  assert.deepEqual(d.elements[1], before);
  assert.equal(d.artboards.desktop.height, 650);
  assert.equal(rect(d.elements[0].geometry, d.artboards.desktop).x, -150);
});
test("grid and window use independent coordinate regions at intermediate widths", () => {
  const g = fixture.elements[0].geometry,
    b = { ...fixture.artboards.desktop, width: 1440 };
  assert.equal(rect(g, b).x, 220);
  assert.equal(rect({ ...g, container: "window" }, b).x, 100);
  assert.equal(rect(g, { ...b, width: 1050 }).x, 100);
});
test("changing all nine anchors and origins preserves rotated rendered corners", () => {
  for (const x of [0, 0.5, 1])
    for (const y of [0, 0.5, 1])
      for (const rotation of [0, 37, 90, -30]) {
        const board = fixture.artboards.desktop,
          g = { ...fixture.elements[0].geometry, rotation };
        const next = anchorGeometry(g, board, {
          anchorX: x,
          anchorY: y,
          originX: x,
          originY: y,
        });
        const point = (g) => {
          const r = rect(g, board),
            v = rotatePoint(
              -g.originX * g.width,
              -g.originY * g.height,
              g.rotation,
            );
          return {
            x: r.x + g.originX * g.width + v.x,
            y: r.y + g.originY * g.height + v.y,
          };
        };
        epsilon(point(g).x, point(next).x);
        epsilon(point(g).y, point(next).y);
      }
});
test("50/100/200% zoom does not change document geometry; resize keeps opposite pivot", () => {
  for (const zoom of [0.5, 1, 2]) {
    const d = screenDelta(100 * zoom, 40 * zoom, zoom, 90);
    epsilon(d.x, 40);
    epsilon(d.y, -100);
  }
  const g = {
      ...fixture.elements[0].geometry,
      originX: 0.5,
      originY: 0.5,
      rotation: 0,
    },
    next = resizeGeometry(g, 20, 30, "nw");
  assert.equal(next.width, g.width - 20);
  assert.equal(next.height, g.height - 30);
  assert.equal(next.x, g.x + 10);
  assert.equal(next.y, g.y + 15);
});
test("responsive overrides are independent; auto-adapt never overwrites manual work", () => {
  const d = structuredClone(fixture),
    el = d.elements[0],
    desktop = structuredClone(el.geometry);
  setValue(el, "mobile", "geometry", "x", -20);
  assert.deepEqual(el.geometry, desktop);
  assert.equal(resolve(el, "mobile").geometry.x, -20);
  assert.equal(resolve(el, "tablet").geometry.x, desktop.x);
  const mobile = structuredClone(el.overrides.mobile);
  adapt(d, "mobile");
  assert.deepEqual(el.overrides.mobile, mobile);
  delete el.overrides.mobile;
  assert.equal(resolve(el, "mobile").geometry.x, desktop.x);
});
test("one completed drag corresponds to one history record and Undo/Redo restores overrides", () => {
  const d = structuredClone(fixture),
    h = new History(d);
  for (let i = 0; i < 200; i++) d.elements[0].geometry.x = i;
  h.push(d);
  assert.equal(h.items.length, 2);
  assert.equal(h.undo().elements[0].geometry.x, 100);
  assert.equal(h.redo().elements[0].geometry.x, 199);
});
test("JS and PHP renderers use the same absolute geometry at every breakpoint and in groups", () => {
  const result = JSON.parse(
    execFileSync("php", ["tests/zero-parity.php"], { encoding: "utf8" }),
  );
  for (const [mode, board] of Object.entries(fixture.artboards))
    for (const [index, el] of fixture.elements.entries()) {
      assert.equal(
        result[mode][index],
        geometryCSS(resolve(el, mode).geometry, board),
      );
    }
  assert.equal(
    result.nested,
    geometryCSS(
      fixture.elements[2].children[0].geometry,
      { width: 400, height: 300 },
      true,
    ),
  );
});
test("the exported frontend renderer contains no layout flow or editor UI and scopes block copies", () => {
  const html = renderZero(fixture, "zero-one"),
    other = renderZero(fixture, "zero-two");
  assert.match(html, /position:absolute/);
  assert.match(html, /@media\(max-width:479px\)/);
  assert.match(html, /height:650px/);
  assert.match(html, /data-ze="ze-photo"/);
  assert.doesNotMatch(html, /zero-handle|zero-ruler|display:flex|display:grid/);
  assert.notEqual(html, other);
  assert.match(html, /Hello &amp; Zero/);
});
test("Project Data schema carries one strict zeroData contract and rejects second trees", () => {
  const schema = JSON.parse(
      readFileSync(new URL("../schemas/project.schema.json", import.meta.url)),
    ),
    validate = new Ajv2020({ strict: false }).compile(schema),
    doc = {
      schemaVersion: 1,
      engine: "grapesjs",
      data: {
        pages: [
          {
            id: "home",
            frames: [
              {
                component: {
                  type: "wrapper",
                  components: [
                    {
                      type: "zero-block",
                      attributes: { id: "zero-test" },
                      zeroData: structuredClone(fixture),
                      components: [],
                    },
                  ],
                },
              },
            ],
          },
        ],
      },
    };
  assert.equal(validate(doc), true, JSON.stringify(validate.errors));
  const root = doc.data.pages[0].frames[0].component.components[0];
  root.components = [{ type: "text", content: "duplicate" }];
  assert.equal(validate(doc), false);
  root.components = [];
  root.zeroData.elements[0].geometry.x = Infinity;
  assert.equal(validate(doc), false);
  root.zeroData.elements[0].geometry.x = 1;
  root.zeroData.elements[0].style.position = "fixed";
  assert.equal(validate(doc), false);
});

test("window synchronization patches only the block and detects unrelated remote changes", () => {
  const a = {
      data: {
        pages: [
          {
            id: "home",
            frames: [
              {
                component: {
                  components: [
                    {
                      type: "zero-block",
                      attributes: { id: "zero-a" },
                      zeroData: fixture,
                    },
                    { type: "text", content: "Keep" },
                  ],
                },
              },
            ],
          },
        ],
      },
    },
    b = structuredClone(a);
  serializedZero(b, "home", "zero-a").zeroData.elements[0].geometry.x = 777;
  assert.equal(sameOutsideZero(a, b, "home", "zero-a"), true);
  b.data.pages[0].frames[0].component.components[1].content = "Remote edit";
  assert.equal(sameOutsideZero(a, b, "home", "zero-a"), false);
  assert.equal(serializedZero(a, "wrong-page", "zero-a"), undefined);
});

test("selection overlay composes nested group transforms in document coordinates", () => {
  const d = structuredClone(fixture),
    m = worldMatrix(d, "ze-child", "desktop");
  epsilon(m[4], 140);
  epsilon(m[5], 360);
  d.elements[2].geometry.rotation = 90;
  const rotated = worldMatrix(d, "ze-child", "desktop");
  epsilon(rotated[4], 70);
  epsilon(rotated[5], 410);
  epsilon(rotated[0], 0);
  epsilon(rotated[1], 1);
});
test("group resize scales local child geometry and typography, keeping responsive modes intact", () => {
  const d = structuredClone(fixture),
    group = d.elements[2];
  group.children[0].overrides.mobile = { geometry: { x: 5, width: 50 } };
  const old = structuredClone(group),
    mobile = structuredClone(group.children[0].overrides.mobile);
  group.geometry.width *= 2;
  group.geometry.height *= 2;
  scaleGroupChildren(group, old, "desktop");
  assert.equal(group.children[0].geometry.x, -40);
  assert.equal(group.children[0].geometry.width, 480);
  assert.equal(group.children[0].style.fontSize, 32);
  assert.deepEqual(group.children[0].overrides.mobile, mobile);
});
test("aspect-locked corner and side handles preserve image proportions under rotation", () => {
  for (const handle of ["n", "s", "e", "w", "nw", "ne", "sw", "se"]) {
    const g = { ...fixture.elements[1].geometry, rotation: 25 },
      next = resizeGeometry(g, 50, 25, handle, true);
    epsilon(next.width / next.height, g.width / g.height);
  }
});
test("pending inspector edits are flushed before a drag so Undo changes only that drag", () => {
  const d = structuredClone(fixture),
    h = new History(d);
  d.elements[1].alt = "Updated inspector";
  h.push(d);
  d.elements[0].geometry.x += 50;
  h.push(d);
  const undo = h.undo();
  assert.equal(undo.elements[0].geometry.x, 100);
  assert.equal(undo.elements[1].alt, "Updated inspector");
});

test("group and ungroup preserve rotated world matrices across all five modes", async () => {
  const { groupElements, ungroupElements } = await import(
    "../src/zero/model/operations.js"
  );
  const d = structuredClone(fixture);
  d.elements[0].geometry.rotation = 31;
  d.elements[2].geometry.rotation = -17;
  const ids = ["ze-title", "ze-group", "ze-child"],
    before = Object.fromEntries(
      Object.keys(d.artboards).map((m) => [
        m,
        Object.fromEntries(ids.map((id) => [id, worldMatrix(d, id, m)])),
      ]),
    );
  const groupIds = groupElements(d, ["ze-title", "ze-group"], "desktop");
  for (const mode of Object.keys(d.artboards))
    for (const id of ids)
      worldMatrix(d, id, mode).forEach((v, i) =>
        epsilon(v, before[mode][id][i]),
      );
  const created = d.elements.find((e) => e.id === groupIds[0]);
  created.geometry.rotation = 23;
  const transformed = Object.fromEntries(
    Object.keys(d.artboards).map((m) => [
      m,
      Object.fromEntries(ids.map((id) => [id, worldMatrix(d, id, m)])),
    ]),
  );
  ungroupElements(d, groupIds, "desktop");
  for (const mode of Object.keys(d.artboards))
    for (const id of ids)
      worldMatrix(d, id, mode).forEach((v, i) =>
        epsilon(v, transformed[mode][id][i]),
      );
});
