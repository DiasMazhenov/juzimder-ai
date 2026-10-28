# Open Design QA: vertical instrument photo crop

Date: 2026-10-28. Page: Design token QA / Zero Engine QA. Project revision remained 380.

## Before fix

The Standard generator selected a portrait Wikimedia Commons violin photo for a landscape Hero slot. The desktop preview cropped both ends of the instrument. On mobile the same photo was placed below the text, but the displayed frame still cropped the instrument.

## Change under review

The shared Wikimedia photo lookup now keeps source dimensions. The selected-photo renderer uses `contain` for extreme aspect ratios and preserves the prior default for ordinary photos and background images. Regression tests cover the portrait/landscape decision and renderer rule.

## After-fix live check

The same content-only brief was rerun as a controlled comparison. The Standard profile passed server validation in 9.1 seconds via the configured fallback model and produced 3 sections. Wikimedia photo selection reported temporary unavailability, so the candidate had no image. Desktop, Tablet and Mobile document widths were 1423/1423, 751/751 and 358/358 px, respectively. The screenshot check confirms responsive width and card layout only; the `contain` behavior is **NOT RUN** on the new live candidate because it had no image.

No candidate was applied, saved or published. Project Data stayed at revision 380.

## Screenshots

- `desktop-before-crop-fix.png`, `tablet-before-crop-fix.png`, and `mobile-before-crop-fix.png`: screenshots of the image-crop defect.
- `desktop-after.png`, `tablet-after.png`, and `mobile-after.png`: controlled post-fix preview; the image provider was unavailable.
