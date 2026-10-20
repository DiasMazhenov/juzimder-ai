# Project UI/UX refinement

## Gap analysis at 00.00.007

Before this change the dashboard created projects and opened the editor directly. GrapesJS already held multiple pages in one full Project Data document, while global routes, the project settings shell, and the requested floating editor tools were missing. The editor already had a theme dialog, layer/block managers, and Flex/Grid style controls; those remain the underlying mechanisms.

The P0 slice now adds a project Pages Dashboard, page CRUD and route metadata, one home route, a settings navigation shell, separate status rows for global Header/Footer/404, floating Layers/Sections panels with search/categories, ten section presets plus container/button primitives, icon-based Flex controls, and Page Root/Body controls. P1 integration work remains open: persistent site typography/style settings, SEO, analytics, forms, custom code, AI profiles, domain setup, and publication. The P0 status cards state that global elements and publication are unavailable; no fake success states are shown.

## Page and route data

GrapesJS `data.pages` and page ids remain canonical. Route slugs and `isHome` are stored separately from editor data in a versioned `projects.settings_payload` envelope (`schemaVersion: 1`, `pages` keyed by stable GrapesJS page id). Revision rows snapshot that envelope alongside the matching full Project Data. This keeps URL edits independent from internal ids and lets restore return page content and routing together.

The 001→002 SQLite migration fills route metadata for existing documents/revisions based on current page names, gives the first page `/`, preserves the existing payloads, and records schema version 2. All page mutations use project owner, session, CSRF, expected version, document validation, and a transaction. Slugs are single path segments, normalized to ASCII lowercase with transliteration for Russian/Kazakh names, checked for uniqueness, and rejected when reserved. Deleting the home page selects the first remaining page; the project must retain at least one page. Actual public routing is deferred until publication exists.

## Editor behavior

The persistent left column is replaced by one floating Layers/Sections control at a time. Selecting a component in Layers synchronizes GrapesJS selection. Flex buttons update the selected component's actual style, expose CSS values in accessible names/tooltips, and follow GrapesJS selection so Undo/Redo and responsive device state remain owned by Core. Selecting the existing page wrapper is presented as “Body · Корень страницы”; layout settings write to that wrapper's Project Data styles instead of mutating the editor document body.

## Zero Block architecture (P2, not implemented)

- A Zero Block is a section inside an ordinary GrapesJS page tree, never a global editor mode. It owns a stable component id, a nested element tree, and responsive properties in the same full Project Data envelope.
- The future `zero-block` component type requires a deliberate schema and validator migration. Until that migration is designed and tested, the existing component allowlist remains unchanged and no fake block is offered in the library.
- A dedicated editor opens from the selected section and edits the section's nested model. Save uses the existing authenticated owner/CAS/revision service; returning to the page editor restores selection and reflects the same model.
- Free positioning, drag/resize, guides, snapping, alignment, and per-breakpoint properties belong to that specialized editor. The normal page editor continues to lay out the surrounding document.
- Acceptance must cover nesting, stable ids, responsive save/reload, revision restore, Undo/Redo, and validation before enabling this type for projects.

## Next implementation sequence

1. Persist and validate project-level typography/design tokens through a shared settings service, then apply them to the editor and AI-generated sections without overwriting local styles.
2. Implement SEO and analytics settings together with static publication metadata injection; keep the UI unavailable until output can consume the settings.
3. Implement form delivery with server-side validation, rate limits, and SSRF-safe webhook handling before presenting a working form integration.
4. Add custom code and AI profile management only with isolated preview/security boundaries and server-side secret handling.
5. Add domain verification and publication routing after the public renderer exists.
