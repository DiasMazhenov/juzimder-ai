# Open Design QA: radio station "Көкжиек" — 2026-10-28

The same content-only brief was used for a before/after control retest. The AI candidate passed server validation both times and was not applied, saved, or published. Project revision stayed at 380.

The initial screenshots showed a no-photo Hero whose copy was constrained to roughly one grid column, leaving a large empty area. The post-fix run reproduced the pipeline order: generation defaults run while the photo slot still exists, then photo selection removes the unavailable Hero slot. Photo-selection post-processing now marks the sole remaining text child and lets it use the available Hero width, capped at 760px. A Hero with a selected photo is unchanged.

## Screenshots

- Before: `desktop-before.png`, `tablet-before.png`, `mobile-before.png`
- After: `desktop-after.png`, `tablet-after.png`, `mobile-after.png`

## Post-fix browser measurements

| Preview | Document / viewport width | Hero width | Copy width |
|---|---:|---:|---:|
| Desktop | 1423 / 1423 px | 1423 px | 760 px |
| Tablet | 751 / 751 px | 751 px | 711 px |
| Mobile | 358 / 358 px | 358 px | 318 px |

No horizontal document overflow was observed. Wikimedia photo selection reported that no suitable photo was found, so this run verifies the no-photo fallback only; image-backed split layout remains outside this specific visual check.
