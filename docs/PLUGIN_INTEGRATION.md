# Plugin integration status · 00.00.079

The user requested the whole supplied list. Installation is not acceptance.

| Plugin | Status |
|---|---|
| blocks-basic | Four editable column blocks connected; video/map pending resource policy |
| blocks-flexbox | Editable native flex layout connected |
| style-gradient | Native Style Manager input connected |
| style-filter | Native filter input and static export connected |
| touch | Plugin loaded; physical touch-device acceptance pending |
| plugin-export | Pinned dependency; existing server ZIP remains the active path |
| plugin-filestack | Pinned; credentials/site settings and managed import pending |
| plugin-ckeditor | Pinned; editor dependency, licensing and RTE acceptance pending |
| tui-image-editor | Pinned; editor dependency and managed save pending |
| plugin-forms | Pinned; native tags, validation, submission policy pending |
| navbar | Pinned; reviewed component runtime pending |
| component-countdown | Pinned; reviewed component runtime pending |
| lory-slider | Pinned; local Lory dependency and runtime pending |
| tabs | Pinned; runtime and persisted attributes pending |
| tooltip | Pinned; generated CSS export acceptance pending |
| custom-code | Pinned; isolated custom-code execution pending |
| aviary | Retired; use TOAST UI replacement |

## Execution boundary

Core component scripts execute in the canvas. `allowScripts:false` only blocks parsed HTML script tags, not native component scripts. Do not remove the backend script prohibition without a reviewed boundary. Current scripts, event handlers, external loaders, form actions and server execution remain prohibited.

A broad CSP relaxation to unsafe-inline/self was rejected by automatic approval review. Required alternative: exact reviewed runtime hashes/nonces, unchanged editor-origin CSP, opaque sandbox without allow-same-origin for owner custom code, no editor cookies/API access, no PHP/server evaluation. Plugin code and user code need separate paths. Validate the exact pinned component code server-side and serialize props as JSON, never concatenate owner input into JavaScript.

Release acceptance per plugin: native insert/edit, full Project Data validation, save/reopen, Undo/Redo, preview, ZIP and published output. Optional services need configured credentials; do not pretend they work before configuration.
