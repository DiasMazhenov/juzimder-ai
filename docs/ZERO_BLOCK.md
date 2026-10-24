# Zero Block — absolute positioning engine

Version: zeroData v1, product 00.00.102. Audited 24.10.2026: GrapesJS Core 0.23.8, Vite 8.3.4, PHP 8.5.8 locally; supported backend remains PHP 8.2+. Studio SDK and browser test infrastructure were not added.

## Architecture decision (Z0)

A `zero-block` is one normal GrapesJS section with a stable `attributes.id`, an empty `components` collection and one `zeroData` object. There is no second project, database table, DOM component tree, AI document or publication pipeline. The additive component contract keeps the outer `{schemaVersion:1,engine:"grapesjs",data:ProjectData}` envelope. `zeroData.version=1` is mandatory; unknown versions fail closed. A future incompatible change must supply an explicit version migration. Existing non-Zero documents require no migration.

`zeroData` contains five artboards and a bounded tree of typed elements. Types: text, image, rectangle, ellipse, line, button and group. Text is plain text or an array of safe marked runs (`text`, boolean `bold/italic/underline`), not HTML. Groups alone can have children. IDs are unique within each block and renderer CSS is scoped by the containing component ID, so whole-block copies remain isolated.

Authoritative validation: `backend/ZeroBlock.php`, called by `Document::validate`. JSON Schema mirrors the structural contract in `schemas/project.schema.json`; runtime validation also enforces uniqueness, total node count, depth, URL parsing, finite numbers and byte limits. Limits: 300 elements, eight group levels, 256 KiB per block, coordinates/rotation within ±20000, positive sizes up to 20000. The existing project-wide 2 MB and complexity limits still apply. Style keys, colours, font names, anchors, containers, tags and URL schemes are allowlisted. Arbitrary SVG, scripts, HTML embeds and animation payloads are not accepted as Zero elements.

## Coordinate contract

All element positions are absolute. A section has independent height, `position:relative`, `width:100%` and a selectable overflow policy. Every object, including a child of a group, has independent X/Y/W/H, rotation, container, anchor X/Y and origin X/Y. Negative offsets and overlap are supported. Array order defines deterministic sibling z-index; a group is its own stacking context. No Flex/Grid document flow is used to place Zero elements. Flex/Grid in the surrounding app UI or ordinary GrapesJS sections is unrelated to this engine.

For a container of size C and an element of size S:

```
pivot = containerStart + anchor * C + offset
localMatrix = translate(pivot) * rotate(angle) * translate(-origin * S)
worldMatrix = parentWorldMatrix * localMatrix
```

Anchor/origin components are 0, 0.5 or 1. Changing anchor, origin or Grid/Window container adjusts offsets to preserve the rendered position, including rotated elements. A group supplies its own local width/height; its children do not reference the outer page. Group/ungroup converts all five modes while preserving world matrices. Group resize scales local geometry and typography; image/group proportions can be preserved. Zoom and pan belong only to the camera, never to saved coordinates.

At the top level, Window is the full-width section viewport. Grid is a centered coordinate region of `min(section width, gridWidth)`; it is not CSS Grid. Its default desktop width is 1200 px. Grid columns, margins, gutter, rulers and guides are editor aids. The published renderer never emits them.

JS geometry: `src/zero/model/model.js`; affine matrices and group scaling: `transforms.js`; structural operations: `operations.js`. Pure tests compare JS and PHP geometry for every mode and verify rotated anchor/group transformations. The frontend `renderZero` and PHP `ZeroBlockRenderer` use the same formula and media boundaries. The PHP renderer escapes text and attributes and emits standalone HTML/CSS, without GrapesJS, Zero Editor or executable JavaScript.

## Responsive contract

| Mode | Reference width | Published media range |
|---|---:|---|
| desktop | 1200 | 1200 and wider |
| laptop | 980 | 980–1199 |
| tablet | 640 | 640–979 |
| landscape | 480 | 480–639 |
| mobile | 360 | below 480 |

Reference widths are not media boundaries. Desktop properties form the base; each other mode overlays only its own geometry/style/visibility/source properties. Overrides do not cascade through another smaller mode. Artboards independently store height, background, grid width, margin, gutter, overflow and guides. Content, rich text and links are shared; image source, visual styles and geometry can differ by mode. `<picture>` handles responsive source selection in static output.

Inspector markers: `↳` inherited, `•` overridden. Reset removes the current element's mode override. Initial adaptation scales only elements without an existing override, preserving existing values. Group/ungroup materializes per-mode geometry to preserve placement; users can reset an override before requesting initial adaptation. Mobile never becomes a vertical Flex layout.

## Save and synchronization

Entry: `/projects/:projectId/pages/:pageId/zero/:blockId/edit`. The section card, selected-section button and double click open a named separate window after saving the main editor. The main editor treats its content as opaque and cannot drag internal objects with GrapesJS handles.

`PUT /api/projects/:projectId/pages/:pageId/zero/:blockId` accepts exactly `{version,zeroData}`. Normal session, owner and CSRF checks apply. The server resolves the page and unique component ID, validates Zero data, replaces that block in a copy of Project Data and calls the existing `Store::save`. Asset ownership, expected-version CAS, settings reconciliation and revision creation are unchanged. `update_zero_block` is also available through the existing typed operation service; future AI/MCP commands must use the same validated canonical model, not a parallel tree. No new AI Engine was added.

Completed operations debounce autosave. Pointermove updates the local model and only affected DOM styles through requestAnimationFrame; it does not send network mutations or rebuild all images. A continuous gesture records one history entry. Selection handles occupy an editor-only overlay above all element stacking contexts.

BroadcastChannel messages contain project/page/block IDs and revision only. The server remains authoritative. A clean main editor reads the saved version and patches only the target model; changes elsewhere trigger a clean reload. Dirty or in-flight main edits are retained and produce an explicit conflict. A 409 in Zero Editor retains the complete local model and exposes JSON download. Rebase is safe only when the remote block is still identical to the originally loaded block; otherwise loading the remote model needs an explicit discard confirmation. Main dirty tracking compares the accepted GrapesJS serialization snapshot, avoiding normalization-driven save loops.

## Delivered controls

- Element library, Inspector, nested Layers with type icons, visibility, lock, sibling drag reorder and shared canvas/Layers context menu.
- Pointer selection, shift selection, marquee, drag, eight resize handles and rotation handle; numeric geometry and 3×3 anchors.
- Text double-click editing with safe marks, H1–H6, typography and auto height; project font choices.
- Existing project raster library/upload; alt/link, fit/position, radius, opacity, aspect ratio. It uses the existing authenticated asset API.
- Shapes, strokes, shadows, buttons with links/targets and hover colours.
- Alignment/distribution, grouping, nested groups, duplicate/copy/paste, layer order, Undo/Redo and keyboard shortcuts. Input/contenteditable fields retain normal keyboard editing.
- Five modes, overrides/reset, artboard settings, twelve-column grid, rulers, draggable guides, smart alignment guides and zoom-adjusted snapping.
- Camera zoom 50/75/100/150/200%, Fit, Space+Drag pan, preview, save and close.

## Validation and acceptance

Automated coverage: negative/independent geometry; Grid/Window math; all anchor/origin combinations under rotation; zoom transforms; aspect ratio; history; responsive inheritance/reset; group matrices/scaling; serializer/schema; bounds, IDs, depth, unsafe HTML/CSS/URLs and NaN/Infinity; scoped update/CAS/owner/CSRF; private images in source overrides, saved preview, ZIP and immutable public snapshots. Full npm suite, production build and PHP syntax checks are required for release.

Fresh Codex Browser QA uses the existing server at port 8080 and at most two tabs. Dedicated page: `a0d6b777-a709-48ec-b17a-d8239dcd2c15` in project `7768bf245f8756460cbc9849`. Stable block: `zero-c69b09d3-7391-4b7a-bc4d-b5b25bf2ee82`. A synthetic 800×600 PNG `zero-geometry-qa.png` was uploaded only as a geometry test asset; it is not automatic photo sourcing.

Observed: create section → open separate editor; text/image/shape/button composition; independent numeric editing; Drag at 50/100/200%; resize without neighbor movement; Undo/Redo; negative X; lock preventing drag; duplicate/delete; overlap; rotated anchor preservation (<0.01 px observed); group movement, proportional resize, ungroup preserving placement; all five mode geometries after save/reopen; BroadcastChannel synchronization without a second save loop; the main GrapesJS renders the section; saved server HTML displays the same composition with absolute elements, loaded 800×600 image and no editor controls. Selected desktop/mobile screenshots: `/tmp/juzimder-zero-098-desktop.png`, `/tmp/juzimder-zero-098-mobile.png`; saved renderer: `/tmp/juzimder-zero-098-saved-view.png`. The first race found during QA was fixed: main normalization must not create an extra document revision after a Zero save.

## Open acceptance / subsequent phases

This is a working absolute engine and Z1 vertical slice, with initial Z2–Z5 tooling. It is not a claim of complete Tilda feature parity.

- Z0 complete: additive contract, validator, model, CAS flow, renderer design and bounded security policy.
- Z1 working: library → separate editor → text/coordinates → save/reopen → main display → existing preview/export/public snapshot pipeline. API tests cover ZIP/publication; a manual public-host visual run remains open.
- Z2–Z5 controls listed above are implemented. Codex Browser verified all eight resize handles at 50% zoom; Undo after each handle restored the exact starting X/Y/W/H and rotation. Rotation and sibling layer ordering also passed with Undo.
- A click or double-click used to select/edit text must not invoke snap. The pointer gesture now waits for 1.5 CSS px of movement before applying drag/resize/rotation; smaller pointer jitter remains a click. Browser regression evidence and saved QA revision are recorded below. Nested rotated groups, rich-text keyboard editing, ruler/snap combinations and all intermediate viewport widths remain open.
- Artboard Autoscale is not implemented. Percent/relative units, advanced SVG/video/gallery/forms/embed elements, animation and AI generation are intentionally not declared implemented. Fixed pixel geometry is the v1 contract.
- Layer drag reorder currently operates among siblings; structural nesting uses Group/Ungroup. Clipboard is internal to the Zero Editor.
- Z6 remains open: full conflict/revision-restore visual matrix, browser public-host/ZIP acceptance, 300-node performance measurement and real mobile-device authoring. API/static tests do not substitute for these visual checks.
- Basic/step-by-step animation is future work, after geometry/persistence/publication acceptance; any future runtime must respect reduced motion and the existing CSP.

References: https://help.tilda.cc/zero , https://help.tilda.cc/zero/advanced , https://help.tilda.cc/zero/grid , https://help.tilda.cc/zero/advanced-animation , https://grapesjs.com/docs/modules/Components.html . Only functional/UX principles were studied. Tilda code and brand assets were not copied. New UI icons are official Tabler Outline; MIT notices are retained in `resources/tabler-editor/LICENSE` and the added catalogue entries. No product license was selected.

Финальная проверка 00.00.098: npm test и npm run build PASS; PHP syntax изменённых файлов PASS. Основной GrapesJS: дублирование Zero Block успешно сохранено отдельной ревизией, Undo вернул один блок и также сохранён. В повторно открытом Zero Editor доступно контекстное меню слоя; локальные проверки retry ограничены сетевыми/5xx ошибками, ошибки валидации не повторяются бесконечно.

Revision restore through the project history preserves the selected page ID when it exists in the restored snapshot. This avoids navigating to Home after restoring a Zero QA page.
Browser QA also restored a historical revision and returned to the latest Zero composition while keeping the Zero QA page selected. The editor reopened at the current revision afterward.

Conflict recovery in the main GrapesJS editor now retains the active page when loading the server version, matching revision restore. Unsaved local changes remain available until the user explicitly chooses this action.

Browser conflict acceptance: a temporary local GrapesJS text change remained in the editor when the Zero window saved; normal Save was disabled, an explicit server reload discarded only that temporary edit, kept the Zero QA page selected, and synced the restored white artboard without a redundant revision. Final QA revision: 153. A mouse rotation was observed at 13.62 degrees and undone to zero; Bring Forward reordered a sibling layer and Undo restored the initial z-order.

00.00.102 pointer-gesture acceptance: a normal/double click at 50% zoom previously caused snapGeometry to move the selected heading from Y=140 to 131.42, even though it was not dragged. Added a 1.5 CSS-pixel movement threshold before drag/resize/rotation geometry is applied. On the isolated Zero QA page, ordinary and double clicks left X=100, Y=140, W=520, H=170 unchanged at revision 157. An actual drag changed geometry to X=118.33/Y=155.24; Undo followed by reload restored and confirmed X=100, Y=140, W=520, H=170 at revision 159. The all-eight-handle test also passed at 50% zoom: every handle changed geometry, and an immediate Undo restored the exact original geometry each time. Rotation (13.62° → Undo 0°) and Bring Forward → Undo restored layer order. Rich-text keyboard acceptance remains NOT RUN: the browser harness did not verify text input/caret in contenteditable, so no pass is claimed.
