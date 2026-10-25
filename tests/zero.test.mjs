import {
  worldMatrix,
  documentBounds,
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
import { videoEmbedUrl } from "../src/zero/model/video.js";
import {
  pageSandboxNonce,
  sandboxFrame,
  validateSandboxPayload,
} from "../src/zero/sandbox.js";
import { editorMode } from "../src/zero/editor-mode.js";
import { renderInspector } from "../src/zero/ui/inspector.js";
import { layerDropIntent, moveLayer } from "../src/zero/model/operations.js";
import { normalizeEditorIconMarkup } from "../src/editor-icon-markup.js";
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
test("editor document bounds include rotated outboard elements and nested groups", () => {
  const d = structuredClone(fixture),
    group = d.elements[2],
    child = group.children[0];
  group.geometry.x = -220;
  group.geometry.y = -140;
  group.geometry.rotation = 30;
  child.geometry.x = -20;
  const bounds = documentBounds(d, "desktop");
  assert.ok(bounds.left < 0);
  assert.ok(bounds.top < 0);
  assert.equal(bounds.right, 1200);
  assert.equal(bounds.bottom, 650);
  child.visible = false;
  group.visible = false;
  const hiddenBounds = documentBounds(d, "desktop");
  assert.deepEqual(hiddenBounds, {
    left: 0,
    top: 0,
    right: 1200,
    bottom: 650,
    width: 1200,
    height: 650,
  });
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
test("camera keeps outboard negative geometry inside the scrollable stage", () => {
  const result = zoomCameraAtPoint({
    zoom: 1,
    nextZoom: 1.5,
    scrollLeft: 0,
    scrollTop: 0,
    pointX: 100,
    pointY: 90,
    planeOffsetX: 12,
    planeOffsetY: 12,
    viewportWidth: 800,
    viewportHeight: 600,
    boardWidth: 1200,
    boardHeight: 650,
    contentLeft: -160,
    contentTop: -80,
    contentRight: 1500,
    contentBottom: 900,
  });
  assert.ok(result.planeOffsetX + result.zoom * -160 >= 12);
  assert.ok(result.planeOffsetY + result.zoom * -80 >= 12);
  assert.ok(
    result.scrollLeft <=
      result.planeOffsetX + result.zoom * 1500 + result.stagePaddingRight - 800,
  );
  assert.ok(
    result.scrollTop <=
      result.planeOffsetY + result.zoom * 900 + result.stagePaddingBottom - 600,
  );
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
test("Autoscale emits independent responsive rules for all default and custom breakpoints", () => {
  const data = createZeroData(),
    customMode = addBreakpoint(data, 768),
    element = createElement("rectangle"),
    entries = breakpointEntries(data, "asc");
  element.id = "ze-autoscale-matrix";
  data.elements.push(element);
  const expected = entries.map(([mode, board], index) => {
    const width = board.width * (0.12 + index * 0.07),
      height = board.width * (0.08 + index * 0.015);
    board.scaleGrid = "autoscale";
    board.gridWidth = board.width;
    board.height = 420 + index * 47;
    setValue(element, mode, "geometry", "width", width);
    setValue(element, mode, "geometry", "height", height);
    return { mode, board, width, height };
  });
  assert.equal(entries.length, 6, "covers five standard modes and a 768 px custom mode");

  const html = renderZero(data, "autoscale-matrix"),
    scope = ".jz-zero-autoscale-matrix",
    vw = (value, board) => `${Number(((value * 100) / board.gridWidth).toFixed(4))}vw`;
  for (const { mode, board, width, height } of expected) {
    const media = mode === "desktop"
        ? ""
        : `@media(max-width:${breakpointMediaMax(data, mode)}px){`,
      artboardRule = `${scope}{height:${vw(board.height, board)};background:${board.background};overflow:${board.overflow}}`,
      elementRule = `${scope} [data-ze="${element.id}"]{position:absolute;box-sizing:border-box;`;
    assert.ok(html.includes(`${media}${artboardRule}`), `${mode} artboard height scales from its own grid width`);
    assert.ok(
      html.includes(`${elementRule}left:`) && html.includes(`width:${vw(width, board)};height:${vw(height, board)};`),
      `${mode} element geometry scales from its own grid width`,
    );
  }
  assert.ok(expected.some(({ mode }) => mode === customMode));
});

test("artboard background uses the project image picker and renders responsive-safe overlays", () => {
  const data = createZeroData(),
    board = data.artboards.desktop;
  board.background = "#112233";
  board.backgroundImage = `/api/projects/${"a".repeat(24)}/assets/${"b".repeat(24)}/file`;
  board.backgroundImageName = "Clinic entrance.jpg";
  board.backgroundSize = "contain";
  board.backgroundPosition = "right bottom";
  board.filterStartColor = "#000000";
  board.filterStartOpacity = 0.5;
  board.filterEndColor = "#ffffff";
  board.filterEndOpacity = 0.25;
  const html = renderZero(data, "background-settings"),
    inspector = renderInspector({ items: [], mode: "desktop", board, project: {} }),
    app = readFileSync(new URL("../src/zero/editor/app.js", import.meta.url), "utf8");
  assert.match(html, /background-image:linear-gradient\(180deg,rgba\(0,0,0,0\.5\),rgba\(255,255,255,0\.25\)\),url\("\/api\/projects\//);
  assert.match(html, /background-size:contain;background-position:right bottom;background-repeat:no-repeat/);
  assert.match(inspector, /data-action="background-image"/);
  assert.match(inspector, /data-board="filterStartOpacity"/);
  assert.match(inspector, /data-board="filterEndOpacity"/);
  assert.match(app, /installImagePicker\([\s\S]{0,180}allowExternalUrl: false/);
  const picker = readFileSync(new URL("../src/image-picker.js", import.meta.url), "utf8");
  assert.match(picker, /const imageForm=\$\('#image-form'\);if\(imageForm\)imageForm\.onsubmit=/);
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
test("Zero HTML reuses the parent page nonce required by inherited CSP", () => {
  const nonce = "0123456789abcdef0123456789abcdef";
  assert.equal(
    pageSandboxNonce({ querySelector: () => ({ content: nonce }) }),
    nonce,
  );
  assert.match(
    pageSandboxNonce({ querySelector: () => ({ content: "invalid" }) }),
    /^[a-f0-9]{32}$/,
  );
  const frame = sandboxFrame(
    { html: "<p>ok</p>", css: "", js: "document.body.dataset.ready='yes'" },
    { runScripts: true, nonce },
  );
  assert.match(frame, new RegExp(`script-src &#39;nonce-${nonce}&#39;`));
  assert.match(frame, new RegExp(`script nonce=&quot;${nonce}&quot;`));
  assert.match(frame, /sandbox="allow-scripts"/);
  assert.doesNotMatch(frame, /script-src[^;]*unsafe-inline/);
});
test("Zero HTML is rendered in a locked-down iframe and runs JavaScript only in Preview", () => {
  const data = createZeroData(),
    element = createElement("html");
  element.id = "ze-safe-html";
  element.code = {
    html: '<button id="run">Запустить</button>',
    css: "button{color:#123456}",
    js: 'document.body.dataset.ready="yes";const escaped="</script>";',
  };
  data.elements.push(element);
  const editorMarkup = renderZero(data, "zero-safe-html", "desktop"),
    previewMarkup = renderZero(data, "zero-safe-html", "desktop", {
      runScripts: true,
    });
  assert.match(editorMarkup, /<iframe[^>]*data-ze="ze-safe-html"[^>]*sandbox=""/);
  assert.match(previewMarkup, /<iframe[^>]*sandbox="allow-scripts"[^>]*srcdoc=/);
  assert.match(previewMarkup, /data-code-running="true"/);
  assert.match(editorMarkup, /&lt;meta http-equiv=&quot;Content-Security-Policy/);
  assert.match(editorMarkup, /connect-src &#39;none&#39;/);
  assert.doesNotMatch(previewMarkup, /allow-same-origin|allow-forms|allow-popups/);
  assert.doesNotMatch(previewMarkup, /<script(?:\s|>)/i);
  const menuSource = readFileSync(
    new URL("../src/zero/ui/element-menu.js", import.meta.url),
    "utf8",
  );
  assert.match(menuSource, /type: "html", label: "HTML"/);
  assert.doesNotMatch(menuSource, /\["html", "HTML", "code"\]/);
  assert.throws(
    () => validateSandboxPayload({ html: "", css: "</style>", js: "" }),
    /нельзя закрывать тег style/i,
  );
});
test("Zero Tooltip renders escaped text and appears on hover or keyboard focus", () => {
  const data = createZeroData(),
    tooltip = createElement("tooltip");
  tooltip.id = "ze-safe-tooltip";
  tooltip.content = "Информация & детали";
  data.elements.push(tooltip);
  const html = renderZero(data, "zero-tooltip"),
    inspector = renderInspector({
      items: [{ element: tooltip }],
      mode: "desktop",
      board: data.artboards.desktop,
      project: {},
    }),
    menuSource = readFileSync(
      new URL("../src/zero/ui/element-menu.js", import.meta.url),
      "utf8",
    );
  assert.match(html, /class="jz-zero-element jz-zero-tooltip" data-ze="ze-safe-tooltip"/);
  assert.match(html, /aria-describedby="jz-tooltip-ze-safe-tooltip"/);
  assert.match(html, /role="tooltip">Информация &amp; детали<\/span>/);
  assert.match(html, /\.jz-zero-tooltip:hover \.jz-zero-tooltip-popover/);
  assert.match(html, /\.jz-zero-tooltip:focus-within \.jz-zero-tooltip-popover/);
  assert.doesNotMatch(html, /<script/i);
  assert.match(inspector, /Текст подсказки/);
  assert.match(inspector, /maxlength="2000"/);
  assert.match(menuSource, /\["tooltip", "Подсказка", "message"\]/);
  assert.match(menuSource, /disabled: !\["tooltip", "video", "form", "gallery"\]\.includes\(type\)/);
});
test("Zero Gallery renders escaped project-library images with responsive internal columns", () => {
  const data = createZeroData(),
    gallery = createElement("gallery");
  gallery.id = "ze-safe-gallery";
  gallery.name = 'Gallery & "stories"';
  gallery.style.radius = 14;
  gallery.galleryOptions = { columns: 4, gap: 12, objectFit: "contain" };
  gallery.galleryItems = [
    { id: "photo-one", src: "/api/projects/aaaaaaaaaaaaaaaaaaaaaaaa/assets/bbbbbbbbbbbbbbbbbbbbbbbb/file", alt: 'A & "quoted" photo' },
    { id: "photo-two", src: "https://images.example.test/two.jpg", alt: "Second photo" },
    { id: "photo-three", src: "https://images.example.test/three.jpg", alt: "Third photo" },
    { id: "photo-four", src: "https://images.example.test/four.jpg", alt: "Fourth photo" },
  ];
  data.elements.push(gallery);
  const html = renderZero(data, "zero-gallery"),
    inspector = renderInspector({
      items: [{ element: gallery }],
      mode: "desktop",
      board: data.artboards.desktop,
      project: {},
    });
  assert.match(html, /class="jz-zero-element jz-zero-gallery" data-ze="ze-safe-gallery" role="group" aria-label="Gallery &amp; &quot;stories&quot;"/);
  assert.match(html, /\[data-ze="ze-safe-gallery"\]\{position:absolute/);
  assert.match(html, /class="jz-zero-gallery-grid" role="list"/);
  assert.match(html, /alt="A &amp; &quot;quoted&quot; photo" loading="lazy" decoding="async"/);
  assert.match(html, /--jz-gallery-columns:4;--jz-gallery-rows:1;--jz-gallery-gap:12px;--jz-gallery-fit:contain;--jz-gallery-radius:14px/);
  assert.match(html, /@media\(max-width:979px\)[\s\S]*?--jz-gallery-columns:2/);
  assert.match(html, /@media\(max-width:639px\)[\s\S]*?--jz-gallery-columns:1/);
  assert.match(html, /grid-template-columns:repeat\(var\(--jz-gallery-columns,3\),minmax\(0,1fr\)\)/);
  assert.match(inspector, /Макет галереи/);
  assert.match(inspector, /data-gallery-option="columns"/);
  assert.match(inspector, /Добавить фото из библиотеки/);
  gallery.galleryItems = [];
  assert.match(renderZero(data, "zero-gallery-empty"), /jz-zero-gallery-empty/);
});
test("Zero Vector uses a curated Tabler icon with safe, accessible rendering", () => {
  const data = createZeroData(),
    vector = createElement("vector");
  vector.id = "ze-safe-vector";
  vector.name = 'Arrow & "mark"';
  vector.icon = "arrow-right";
  data.elements.push(vector);
  const html = renderZero(data, "zero-vector"),
    inspector = renderInspector({
      items: [{ element: vector }],
      mode: "desktop",
      board: data.artboards.desktop,
      project: {},
    }),
    menuSource = readFileSync(
      new URL("../src/zero/ui/element-menu.js", import.meta.url),
      "utf8",
    );
  assert.match(html, /class="jz-zero-element jz-zero-vector" data-ze="ze-safe-vector" role="img" aria-label="Arrow &amp; &quot;mark&quot;"/);
  assert.match(html, /<svg class="tabler-editor-icon" aria-hidden="true"/);
  assert.match(html, /\.jz-zero-vector>svg\{display:block;width:100%;height:100%/);
  assert.match(inspector, /<select data-element="icon">/);
  assert.match(inspector, /value="arrow-right" selected>Стрелка вправо/);
  assert.match(inspector, /Произвольный SVG-код не принимается/);
  assert.match(menuSource, /type: "vector"/);
  assert.doesNotMatch(menuSource, /type: "vector",[\s\S]{0,100}disabled: true/);
  vector.icon = "<svg onload=alert(1)>";
  const safeFallback = renderZero(data, "zero-vector-invalid-icon");
  assert.match(safeFallback, /jz-zero-vector/);
  assert.doesNotMatch(safeFallback, /onload=/);
});
test("Zero Video accepts only YouTube/Vimeo URLs and renders a bounded sandboxed iframe", () => {
  assert.equal(videoEmbedUrl("https://youtu.be/dQw4w9WgXcQ"), "https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ");
  assert.equal(videoEmbedUrl("https://www.youtube.com/watch?v=dQw4w9WgXcQ"), "https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ");
  assert.equal(videoEmbedUrl("https://vimeo.com/123456789"), "https://player.vimeo.com/video/123456789");
  for (const url of ["http://youtu.be/dQw4w9WgXcQ", "https://youtube.com.evil.test/watch?v=dQw4w9WgXcQ", "https://user@youtu.be/dQw4w9WgXcQ", "https://youtu.be:443/dQw4w9WgXcQ", "javascript:alert(1)", "https://youtu.be/short"]) assert.equal(videoEmbedUrl(url), null, url);
  const data = createZeroData(), video = createElement("video");
  video.id = "ze-safe-video";
  video.name = "Video & title";
  video.videoUrl = "https://youtu.be/dQw4w9WgXcQ";
  data.elements.push(video);
  const html = renderZero(data, "zero-video"), inspector = renderInspector({
    items: [{ element: video }], mode: "desktop", board: data.artboards.desktop, project: {},
  });
  assert.match(html, /class="jz-zero-element jz-zero-video"/);
  assert.match(html, /<iframe title="Video &amp; title" src="https:\/\/www\.youtube-nocookie\.com\/embed\/dQw4w9WgXcQ"/);
  assert.match(html, /sandbox="allow-scripts allow-same-origin allow-presentation"/);
  assert.match(html, /referrerpolicy="no-referrer"/);
  assert.match(inspector, /data-element="videoUrl"/);
  assert.match(inspector, /только ссылки YouTube и Vimeo/i);
  video.videoUrl = "https://evil.test/embed/x";
  const fallback = renderZero(data, "zero-video-invalid");
  assert.match(fallback, /Добавьте ссылку YouTube или Vimeo/);
  assert.doesNotMatch(fallback, /evil\.test/);
});
test("Zero Form renders a safe editable-field layout with submission explicitly disabled", () => {
  const data = createZeroData(), form = createElement("form");
  form.id = "ze-safe-form";
  form.name = "Контакт & поддержка";
  form.formFields[0].label = "Имя & фамилия";
  form.formFields[0].placeholder = 'Введите "имя"';
  form.submitLabel = "Отправить запрос";
  data.elements.push(form);
  const html = renderZero(data, "zero-form-test"), inspector = renderInspector({
    items: [{ element: form }], mode: "desktop", board: data.artboards.desktop, project: {},
  });
  assert.match(html, /class="jz-zero-element jz-zero-form" data-ze="ze-safe-form"/);
  assert.match(html, /role="group" aria-label="Контакт &amp; поддержка"/);
  assert.match(html, /Имя &amp; фамилия/);
  assert.match(html, /placeholder="Введите &quot;имя&quot;" readonly aria-readonly="true"/);
  assert.match(html, /jz-zero-form-submit" type="button" disabled aria-disabled="true"/);
  assert.match(html, /Отправка формы пока не подключена/);
  assert.doesNotMatch(html, /<form\b|action=|method=|<script/i);
  assert.match(inspector, /data-form-field="name" data-form-prop="label"/);
  assert.match(inspector, /data-form-action="add"/);
  assert.match(inspector, /data-element="submitLabel"/);
  assert.match(inspector, /Данные не собираются/);
  const menuSource = readFileSync(new URL("../src/zero/ui/element-menu.js", import.meta.url), "utf8");
  assert.doesNotMatch(menuSource, /type: "form"[^\n]*disabled: true/);
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
test("editor overflow is separate from the saved-preview and publication policy", () => {
  const editor = readFileSync(
      new URL("../src/zero/editor/app.js", import.meta.url),
      "utf8",
    ),
    styles = readFileSync(new URL("../src/zero/zero.css", import.meta.url), "utf8"),
    hidden = renderZero(fixture, "zero-overflow-hidden", "desktop"),
    visibleData = structuredClone(fixture);
  visibleData.artboards.desktop.overflow = "visible";
  assert.match(editor, /preview \? b\.overflow : "visible"/);
  assert.match(styles, /--zero-workspace-background:\s*var\(--jz-ui-bg,\s*#14181b\)/);
  assert.match(
    styles,
    /\.zero-bleed-surface\s*\{[^}]*background:\s*var\(--zero-workspace-background\)/s,
  );
  assert.match(hidden, /overflow:hidden/);
  assert.match(renderZero(visibleData, "zero-overflow-visible", "desktop"), /overflow:visible/);
});
test("Zero layer controls have no individual outlines while locked and keyboard states remain visible", () => {
  const overrides = readFileSync(new URL("../src/ui-overrides.css", import.meta.url), "utf8");
  assert.match(
    overrides,
    /\.zero-app \.zero-left \.zero-layer :is\(\.zero-layer-disclosure, \.zero-layer-name, \.zero-layer-lock, \.zero-layer-visibility\)\s*\{[^}]*border:\s*0 !important;[^}]*background:\s*transparent !important/s,
  );
  assert.match(overrides, /\.zero-app \.zero-left \.zero-layer button:hover:not\(:disabled\)\s*\{[^}]*border-color:\s*transparent !important/s);
  assert.match(overrides, /\.zero-app \.zero-left \.zero-layer\.is-locked \.zero-layer-lock\s*\{[^}]*background:\s*var\(--jz-ui-muted-surface\) !important/s);
  assert.match(overrides, /:focus-visible\s*\{[^}]*outline:\s*2px solid var\(--jz-ui-focus\)/s);
});
test("Tabler lock icons without SVG classes keep their layer-panel size", () => {
  const catalogue = JSON.parse(
      readFileSync(new URL("../resources/tabler-editor/catalogue.json", import.meta.url), "utf8"),
    ),
    editorIcons = readFileSync(new URL("../src/editor-icons.js", import.meta.url), "utf8");
  assert.match(editorIcons, /normalizeEditorIconMarkup\(catalogue\[name\]/);
  for (const name of ["lock", "lock-open", "circle", "minus"]) {
    const normalized = normalizeEditorIconMarkup(catalogue[name]);
    assert.match(normalized, /<svg class="tabler-editor-icon" aria-hidden="true" focusable="false"/);
    assert.equal((normalized.match(/class="tabler-editor-icon"/g) || []).length, 1);
  }
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
  const animationDocument = structuredClone(doc),
    animatedElement = animationDocument.data.pages[0].frames[0].component.components[0].zeroData.elements[0];
  animatedElement.animation = { effect: "fade", duration: 700, delay: 100, easing: "ease-out" };
  assert.equal(validate(animationDocument), true, JSON.stringify(validate.errors));
  animatedElement.animation.effect = "url(javascript:alert(1))";
  assert.equal(validate(animationDocument), false);
  animatedElement.animation = { effect: "fade", duration: 700, delay: 100, easing: "ease-out", script: "alert(1)" };
  assert.equal(validate(animationDocument), false);
  const htmlDocument = structuredClone(doc),
    htmlElement = structuredClone(fixture.elements[0]);
  htmlElement.id = "ze-schema-html";
  htmlElement.type = "html";
  htmlElement.code = { html: "<strong>Safe</strong>", css: "", js: "" };
  htmlDocument.data.pages[0].frames[0].component.components[0].zeroData.elements = [htmlElement];
  assert.equal(validate(htmlDocument), true, JSON.stringify(validate.errors));
  delete htmlElement.code;
  assert.equal(validate(htmlDocument), false);
  htmlElement.code = { html: "", css: "", js: "", network: "yes" };
  assert.equal(validate(htmlDocument), false);
  const tooltipDocument = structuredClone(doc),
    tooltip = structuredClone(fixture.elements[0]);
  tooltip.id = "ze-schema-tooltip";
  tooltip.type = "tooltip";
  tooltip.name = "Safe Tooltip";
  tooltip.content = "Plain tooltip text";
  tooltipDocument.data.pages[0].frames[0].component.components[0].zeroData.elements = [tooltip];
  assert.equal(validate(tooltipDocument), true, JSON.stringify(validate.errors));
  delete tooltip.content;
  assert.equal(validate(tooltipDocument), false);
  tooltip.content = "x".repeat(2001);
  assert.equal(validate(tooltipDocument), false);
  const vectorDocument = structuredClone(doc),
    vector = structuredClone(fixture.elements[0]);
  vector.id = "ze-schema-vector";
  vector.type = "vector";
  vector.name = "Safe vector";
  vector.icon = "arrow-right";
  vectorDocument.data.pages[0].frames[0].component.components[0].zeroData.elements = [vector];
  assert.equal(validate(vectorDocument), true, JSON.stringify(validate.errors));
  delete vector.icon;
  assert.equal(validate(vectorDocument), false);
  vector.icon = "<svg onload=alert(1)>";
  assert.equal(validate(vectorDocument), false);
  vector.type = "text";
  vector.icon = "arrow-right";
  assert.equal(validate(vectorDocument), false);
  const videoDocument = structuredClone(doc), video = structuredClone(fixture.elements[0]);
  video.id = "ze-schema-video";
  video.type = "video";
  video.videoUrl = "https://youtu.be/dQw4w9WgXcQ";
  videoDocument.data.pages[0].frames[0].component.components[0].zeroData.elements = [video];
  assert.equal(validate(videoDocument), true, JSON.stringify(validate.errors));
  video.videoUrl = "https://evil.test/embed/x";
  assert.equal(validate(videoDocument), false);
  delete video.videoUrl;
  assert.equal(validate(videoDocument), false);
  video.type = "text";
  video.videoUrl = "https://youtu.be/dQw4w9WgXcQ";
  assert.equal(validate(videoDocument), false);
  const formDocument = structuredClone(doc), form = structuredClone(fixture.elements[0]);
  form.id = "ze-schema-form";
  form.type = "form";
  form.submitLabel = "Send";
  form.formFields = [{ id: "email", type: "email", label: "Email", placeholder: "name@example.test", required: true }];
  formDocument.data.pages[0].frames[0].component.components[0].zeroData.elements = [form];
  assert.equal(validate(formDocument), true, JSON.stringify(validate.errors));
  form.formFields[0].action = "https://attacker.test/collect";
  assert.equal(validate(formDocument), false);
  delete form.formFields[0].action;
  form.formFields[0].type = "hidden";
  assert.equal(validate(formDocument), false);
  form.formFields[0].type = "email";
  delete form.submitLabel;
  assert.equal(validate(formDocument), false);
  form.type = "text";
  form.submitLabel = "Send";
  assert.equal(validate(formDocument), false);
  const galleryDocument = structuredClone(doc), gallery = structuredClone(fixture.elements[0]);
  gallery.id = "ze-schema-gallery";
  gallery.type = "gallery";
  delete gallery.content;
  gallery.galleryOptions = { columns: 4, gap: 12, objectFit: "contain" };
  gallery.galleryItems = [{ id: "photo-one", src: "https://images.example.test/one.jpg", alt: "First photo" }];
  galleryDocument.data.pages[0].frames[0].component.components[0].zeroData.elements = [gallery];
  assert.equal(validate(galleryDocument), true, JSON.stringify(validate.errors));
  gallery.content = "Unexpected free content";
  assert.equal(validate(galleryDocument), false);
  delete gallery.content;
  assert.equal(validate(galleryDocument), true, JSON.stringify(validate.errors));
  gallery.galleryItems[0].onload = "alert(1)";
  assert.equal(validate(galleryDocument), false);
  delete gallery.galleryItems[0].onload;
  gallery.galleryOptions.columns = 7;
  assert.equal(validate(galleryDocument), false);
  gallery.galleryOptions.columns = 4;
  gallery.type = "text";
  assert.equal(validate(galleryDocument), false);
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

test("Zero Block accepts and renders the full 300-element limit", () => {
  const data = createZeroData();
  data.elements = Array.from({ length: 300 }, (_, index) => {
    const element = createElement("text");
    element.id = `ze-cap-${index}`;
    element.name = `Load item ${index}`;
    return element;
  });
  const schema = JSON.parse(
      readFileSync(new URL("../schemas/project.schema.json", import.meta.url)),
    ),
    validate = new Ajv2020({ strict: false }).compile(schema),
    document = {
      schemaVersion: 1,
      engine: "grapesjs",
      data: {
        pages: [{
          id: "home",
          frames: [{
            component: {
              type: "wrapper",
              components: [{
                type: "zero-block",
                attributes: { id: "zero-cap" },
                zeroData: data,
                components: [],
              }],
            },
          }],
        }],
      },
    };
  assert.equal(validate(document), true, JSON.stringify(validate.errors));
  const html = renderZero(data, "zero-cap");
  assert.equal((html.match(/class="jz-zero-element/g) || []).length, 300);
  data.elements.push(createElement("text"));
  assert.equal(validate(document), false, "the schema rejects element 301");
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

test("dropping a root sibling before another visible row changes its visual order", () => {
  const d = structuredClone(fixture),
    intent = layerDropIntent(d, "ze-title", "ze-photo", 0.1);
  assert.equal(moveLayer(d, "ze-title", intent.parentId, intent.index), true);
  assert.deepEqual(
    d.elements.map((element) => element.id),
    ["ze-photo", "ze-title", "ze-group"],
  );
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

test("basic Zero animations are allowlisted, scoped, preview-only in editor, and reduced-motion aware", () => {
  const data = structuredClone(fixture),
    element = data.elements[0];
  element.animation = { effect: "move", duration: 900, delay: 200, easing: "ease-out" };
  const published = renderZero(data, "animation-safe"),
    editor = renderZero(data, "animation-safe", "desktop"),
    preview = renderZero(data, "animation-safe", "desktop", { runScripts: true });
  assert.match(published, /@keyframes jz-zero-animation-safe-move\{from\{opacity:0;translate:0 16px\}to\{opacity:var\(--jz-zero-end-opacity,1\);translate:0 0\}\}/);
  assert.match(published, /animation:jz-zero-animation-safe-move 900ms ease-out 200ms both/);
  assert.match(published, /@media\(prefers-reduced-motion:reduce\).*animation:none!important/s);
  assert.doesNotMatch(editor, /animation:jz-zero-animation-safe-move/);
  assert.match(preview, /animation:jz-zero-animation-safe-move 900ms ease-out 200ms both/);
  const inspector = renderInspector({
    items: [{ element, parent: null }],
    mode: "desktop",
    board: data.artboards.desktop,
    project: {},
  });
  assert.match(inspector, /<h2>Анимация<\/h2>/);
  assert.match(inspector, /data-animation="effect"/);
  assert.match(inspector, /data-animation="duration" min="100" max="3000"/);
  assert.match(inspector, /сокращения движения/);
});

test("Zero AI dialog changes are isolated from inspector edits", () => {
  const editor = readFileSync(
    new URL("../src/zero/editor/app.js", import.meta.url),
    "utf8",
  );
  assert.match(
    editor,
    /if \(!event\.target\.closest\("#zero-inspector"\) && event\.target\.id !== "zero-zoom"\)\s+return;/,
  );
});
