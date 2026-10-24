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
  breakpointEntries,
  breakpointMediaMax,
  breakpointForViewport,
  breakpointName,
  validateBreakpoints,
  addBreakpoint,
  removeBreakpoint,
  suggestBreakpointWidth,
  resolve,
  setValue,
  findElement,
  rect,
  anchorGeometry,
  rotatePoint,
  resizeGeometry,
  screenDelta,
  zoomCameraAtPoint,
  geometryCSS,
  History,
  adapt,
} from "../src/zero/model/model.js";
import { serializedZero, sameOutsideZero } from "../src/zero/sync.js";
import { renderZero } from "../src/zero/render.js";
import { editorMode } from "../src/zero/editor-mode.js";
import { renderInspector } from "../src/zero/ui/inspector.js";
import { layerDropIntent, moveLayer } from "../src/zero/model/operations.js";
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
test("pinch zoom keeps the document point under the gesture and clamps camera scale", () => {
  const start = {
    zoom: 0.5,
    scrollLeft: 140,
    scrollTop: 80,
    pointX: 260,
    pointY: 190,
    planeOffsetX: 80,
    planeOffsetY: 80,
    stagePaddingRight: 80,
    stagePaddingBottom: 80,
    viewportWidth: 770,
    viewportHeight: 500,
    boardWidth: 1200,
    boardHeight: 650,
  };
  for (const nextZoom of [0.25, 0.75, 1, 2, 3]) {
    const result = zoomCameraAtPoint({ ...start, nextZoom });
    epsilon(
      (result.scrollLeft + start.pointX - result.planeOffsetX) / result.zoom,
      (start.scrollLeft + start.pointX - 80) / start.zoom,
    );
    epsilon(
      (result.scrollTop + start.pointY - result.planeOffsetY) / result.zoom,
      (start.scrollTop + start.pointY - 80) / start.zoom,
    );
  }
  assert.equal(zoomCameraAtPoint({ ...start, nextZoom: 4 }).zoom, 3);
  assert.equal(zoomCameraAtPoint({ ...start, nextZoom: 0.1 }).zoom, 0.25);
  const rightEdge = zoomCameraAtPoint({
    ...start,
    zoom: 0.5,
    scrollLeft: 470,
    pointX: 170,
    nextZoom: 3,
  });
  epsilon(
    (rightEdge.scrollLeft + 170 - rightEdge.planeOffsetX) / rightEdge.zoom,
    (470 + 170 - 80) / 0.5,
  );
  assert.ok(rightEdge.stagePaddingRight > 80);
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
test("breakpoints can be added and removed while preserving per-mode layout and base geometry", () => {
  const d = createZeroData(),
    element = createElement("text");
  element.geometry.x = 100;
  setValue(element, "laptop", "geometry", "x", 220);
  d.elements.push(element);

  const custom = addBreakpoint(d, 768);
  assert.match(custom, /^bp-/);
  assert.equal(resolve(element, custom).geometry.x, 220);
  assert.equal(breakpointMediaMax(d, custom), 979);
  assert.equal(breakpointForViewport(d, 1200), "desktop");
  assert.equal(breakpointForViewport(d, 768), custom);
  assert.equal(breakpointForViewport(d, 500), "landscape");
  assert.equal(breakpointName(custom, 768), "Custom breakpoint");
  assert.equal(breakpointEntries(d, "asc").at(-1)[0], "desktop");
  validateBreakpoints(d);
  assert.match(renderZero(d, "dynamic"), /@media\(max-width:979px\)/);

  removeBreakpoint(d, custom);
  assert.equal(element.overrides[custom], undefined);
  assert.equal(Object.hasOwn(d.artboards, custom), false);
  removeBreakpoint(d, "desktop");
  assert.equal(d.artboards.desktop.width, 980);
  assert.equal(element.geometry.x, 220);
  assert.equal(resolve(element, "desktop").geometry.x, 220);
  assert.equal(element.overrides?.laptop, undefined);
  validateBreakpoints(d);
});
test("main GrapesJS device selection maps to the corresponding Zero Block artboard", () => {
  const data = createZeroData(),
    fakeEditor = (name, width) => ({
      getDevice: () => name,
      Devices: { get: () => ({ get: () => `${width}px` }) },
    });
  assert.equal(editorMode(fakeEditor("Desktop", 0), data), "desktop");
  assert.equal(editorMode(fakeEditor("Tablet", 768), data), "tablet");
  assert.equal(editorMode(fakeEditor("Mobile", 375), data), "mobile");
  const custom = addBreakpoint(data, 768);
  assert.equal(editorMode(fakeEditor("Tablet", 768), data), custom);
});
test("adding a wider breakpoint snapshots the prior base without shifting it", () => {
  const d = createZeroData(),
    element = createElement("text");
  element.geometry.x = 100;
  d.elements.push(element);
  const custom = addBreakpoint(d, 1440);
  assert.equal(d.artboards.desktop.width, 1440);
  assert.equal(resolve(element, custom).geometry.x, 100);
  assert.equal(resolve(element, "desktop").geometry.x, 100);
  assert.equal(breakpointMediaMax(d, custom), 1439);
  assert.throws(() => addBreakpoint(d, 1200), /свободную ширину/);
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
  assert.equal(
    result.autoscale,
    geometryCSS(fixture.elements[0].geometry, fixture.artboards.desktop, false, true),
  );
  assert.equal(
    result.windowAutoscale,
    geometryCSS(
      { ...fixture.elements[1].geometry, container: "window" },
      fixture.artboards.desktop,
    ),
  );
});
test("Autoscale scales Grid geometry per breakpoint and leaves Window geometry fixed", () => {
  const data = structuredClone(fixture);
  data.artboards.desktop.scaleGrid = "autoscale";
  data.artboards.mobile.scaleGrid = "autoscale";
  data.elements[1].geometry.container = "window";
  const html = renderZero(data, "auto");
  assert.match(html, /height:54\.1667vw/);
  assert.match(
    html,
    /\[data-ze="ze-title"\]\{position:absolute;box-sizing:border-box;left:calc\(50% - 50vw \+ 8\.3333vw\);top:calc\(0% \+ 10vw\);width:35vw;height:9\.1667vw/,
  );
  assert.match(html, /\[data-ze="ze-title"\]\{[^}]*font-size:3\.3333vw/);
  assert.match(html, /\[data-ze="ze-child"\]\{[^}]*width:20vw;height:15vw/);
  assert.match(
    html,
    /@media\(max-width:479px\)\{\.jz-zero-auto\{height:180\.5556vw/,
  );
  assert.match(
    html,
    /\[data-ze="ze-photo"\]\{position:absolute;box-sizing:border-box;left:calc\(0% \+ 450px\);top:calc\(0% \+ 50px\);width:360px;height:240px/,
  );
  const authoringCanvas = renderZero(data, "auto", "desktop");
  assert.match(authoringCanvas, /height:650px/);
  assert.match(authoringCanvas, /width:420px/);
});
test("Autoscale control is scoped to an artboard and new boards default to fixed", () => {
  const data = createZeroData();
  for (const board of Object.values(data.artboards))
    assert.equal(board.scaleGrid, "fixed");
  const markup = renderInspector({
    items: [],
    mode: "mobile",
    board: data.artboards.mobile,
    project: {},
  });
  assert.match(markup, /data-board="scaleGrid"/);
  assert.match(markup, /value="autoscale"[^>]*>Autoscale к ширине окна/);
  assert.match(markup, /только на этом режиме/);
  const customMarkup = renderInspector({
    items: [],
    mode: "bp-12345678",
    board: { ...data.artboards.tablet, width: 768 },
    project: {},
  });
  assert.match(customMarkup, /АРТБОРД · Custom breakpoint/);
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
test("Zero Block buttons use flex centering and keep the label centered", () => {
  const data = createZeroData(),
    button = createElement("button");
  button.id = "ze-button-center";
  button.style.textAlign = "left";
  data.elements.push(button);
  const html = renderZero(data, "button-center");
  assert.match(html, /class="jz-zero-element jz-zero-button"/);
  assert.match(
    html,
    /a\.jz-zero-button\[data-ze="ze-button-center"\]\{[^}]*display:flex;/,
  );
  assert.match(
    html,
    /a\.jz-zero-button\[data-ze="ze-button-center"\]\{[^}]*text-align:center;/,
  );
  assert.match(renderZero(fixture, "zero-mode", "desktop"), /height:650px/);
  assert.match(renderZero(fixture, "zero-mode", "desktop"), /width:1200px!important/);
  assert.doesNotMatch(renderZero(fixture, "zero-mode", "desktop"), /@media\(/);
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
  const custom = structuredClone(doc);
  custom.data.pages[0].frames[0].component.components[0].zeroData.artboards[
    "bp-12345678"
  ] = { ...structuredClone(fixture.artboards.laptop), width: 768 };
  custom.data.pages[0].frames[0].component.components[0].zeroData.elements[0].overrides[
    "bp-12345678"
  ] = { geometry: { x: -20 } };
  assert.equal(validate(custom), true, JSON.stringify(validate.errors));
  const root = doc.data.pages[0].frames[0].component.components[0];
  root.zeroData.artboards.desktop.scaleGrid = "autoscale";
  assert.equal(validate(doc), true, JSON.stringify(validate.errors));
  root.zeroData.artboards.desktop.scaleGrid = "stretch";
  assert.equal(validate(doc), false);
  delete root.zeroData.artboards.desktop.scaleGrid;
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

test("moving layers between root and groups preserves absolute placement at every breakpoint", () => {
  const d = structuredClone(fixture);
  d.elements[0].geometry.rotation = 19;
  d.elements[2].geometry.rotation = -23;
  const modes = Object.keys(d.artboards),
    ids = ["ze-title", "ze-child"],
    capture = () =>
      Object.fromEntries(
        modes.map((mode) => [
          mode,
          Object.fromEntries(ids.map((id) => [id, worldMatrix(d, id, mode)])),
        ]),
      ),
    assertSame = (before) => {
      for (const mode of modes)
        for (const id of ids)
          worldMatrix(d, id, mode).forEach((value, index) =>
            epsilon(value, before[mode][id][index]),
          );
    };

  let before = capture();
  assert.equal(moveLayer(d, "ze-title", "ze-group", 1), true);
  assert.equal(findElement(d, "ze-title").parent.id, "ze-group");
  assertSame(before);

  before = capture();
  assert.equal(moveLayer(d, "ze-child", null, 1), true);
  assert.equal(findElement(d, "ze-child").parent, null);
  assertSame(before);
});

test("layer drop zones map visual row edges to sibling z-order and group centers to children", () => {
  const d = structuredClone(fixture);
  assert.deepEqual(layerDropIntent(d, "ze-title", "ze-photo", 0.1), {
    parentId: null,
    index: 2,
    kind: "before",
    targetId: "ze-photo",
  });
  assert.deepEqual(layerDropIntent(d, "ze-title", "ze-photo", 0.9), {
    parentId: null,
    index: 1,
    kind: "after",
    targetId: "ze-photo",
  });
  assert.deepEqual(layerDropIntent(d, "ze-title", "ze-group", 0.5), {
    parentId: "ze-group",
    index: 1,
    kind: "inside",
    targetId: "ze-group",
  });
  assert.deepEqual(layerDropIntent(d, "ze-title", "ze-group", 0.1), {
    parentId: null,
    index: 3,
    kind: "before",
    targetId: "ze-group",
  });
  assert.deepEqual(layerDropIntent(d, "ze-title", "ze-group", 0.9), {
    parentId: null,
    index: 2,
    kind: "after",
    targetId: "ze-group",
  });
  assert.deepEqual(layerDropIntent(d, "ze-title", "ze-child", 0.9), {
    parentId: "ze-group",
    index: 0,
    kind: "after",
    targetId: "ze-child",
  });
  assert.deepEqual(layerDropIntent(d, "ze-title", null), {
    parentId: null,
    index: 0,
    kind: "root",
    targetId: null,
  });
  assert.equal(layerDropIntent(d, "ze-title", "ze-title", 0.2), null);
});

test("layer move supports sibling reordering and rejects cycles, locked ancestors, and excessive nesting", () => {
  const d = structuredClone(fixture),
    photo = d.elements[1],
    group = d.elements[2];
  assert.equal(moveLayer(d, photo.id, "ze-group", 0), true);
  assert.deepEqual(
    group.children.map((element) => element.id),
    ["ze-photo", "ze-child"],
  );
  assert.equal(moveLayer(d, "ze-child", "ze-group", 0), true);
  assert.deepEqual(
    group.children.map((element) => element.id),
    ["ze-child", "ze-photo"],
  );
  assert.throws(
    () => moveLayer(d, "ze-group", "ze-group", 0),
    /самой себя/,
  );
  group.locked = true;
  assert.throws(
    () => moveLayer(d, "ze-child", null, 0),
    /Разблокируйте/,
  );
  group.locked = false;
  const sourceGroup = createElement("group");
  d.elements.push(sourceGroup);
  let parent = group;
  for (let depth = 0; depth < 7; depth++) {
    const nested = createElement("group");
    nested.id = `ze-nested-${depth}`;
    parent.children.push(nested);
    parent = nested;
  }
  assert.throws(
    () => moveLayer(d, sourceGroup.id, parent.id, 0),
    /восьми уровней/,
  );
});
