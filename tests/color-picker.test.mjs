import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { formatColor, hexToHsv, hsvToHex, parseColor, readSavedColors, removeSavedColor, saveColor } from "../src/ui/color-picker.js";

class MemoryStorage {
  values = new Map();
  getItem(key) { return this.values.get(key) ?? null; }
  setItem(key, value) { this.values.set(key, String(value)); }
}

test("shared picker normalizes HEX, RGB(A), alpha and HSV", () => {
  assert.equal(parseColor("#abc").color, "#aabbcc");
  assert.deepEqual(parseColor("rgba(12, 34, 56, 0.4)"), { color: "#0c2238", opacity: 0.4, valid: true });
  assert.equal(parseColor("var(--brand-color)").valid, false);
  assert.equal(formatColor("#0c2238", 0.4), "rgba(12, 34, 56, 0.4)");
  for (const color of ["#000000", "#ffffff", "#86c232", "#f05a45", "#183e31"])
    assert.equal(hsvToHex(...Object.values(hexToHsv(color))), color);
});

test("saved palette is shared across editors and imports legacy Zero colors", () => {
  const storage = new MemoryStorage();
  storage.setItem("juzimder-zero-saved-colors", JSON.stringify(["#86c232", "#f05a45", "bad"]));
  assert.deepEqual(readSavedColors(storage), ["#86c232", "#f05a45"]);
  saveColor("#123456", storage);
  assert.deepEqual(readSavedColors(storage), ["#123456", "#86c232", "#f05a45"]);
  assert.equal(storage.getItem("juzimder-saved-colors") !== null, true);
});

test("saved colors can be removed without reappearing from legacy storage", () => {
  const storage = new MemoryStorage();
  storage.setItem("juzimder-zero-saved-colors", JSON.stringify(["#86c232", "#f05a45"]));
  assert.deepEqual(removeSavedColor("#f05a45", storage), ["#86c232"]);
  assert.deepEqual(JSON.parse(storage.getItem("juzimder-saved-colors")), ["#86c232"]);
  assert.deepEqual(JSON.parse(storage.getItem("juzimder-zero-saved-colors")), ["#86c232"]);
  assert.deepEqual(removeSavedColor("#86c232", storage), []);
  assert.deepEqual(readSavedColors(storage), []);
  assert.deepEqual(removeSavedColor("not-a-color", storage), []);
});

test("main UI, GrapesJS color properties and Zero Block use the shared picker", () => {
  const main = readFileSync(new URL("../src/main.js", import.meta.url), "utf8"),
    plugins = readFileSync(new URL("../src/editor-plugins.js", import.meta.url), "utf8"),
    zeroInspector = readFileSync(new URL("../src/zero/ui/inspector.js", import.meta.url), "utf8"),
    background = readFileSync(new URL("../src/editor-background.js", import.meta.url), "utf8"),
    sharedPicker = readFileSync(new URL("../src/ui/color-picker.js", import.meta.url), "utf8"),
    zeroPicker = readFileSync(new URL("../src/zero/ui/color-picker.js", import.meta.url), "utf8"),
    pickerCss = readFileSync(new URL("../src/ui/color-picker.css", import.meta.url), "utf8");
  assert.match(main, /installColorPickerInputs\(document\.body\)/);
  assert.match(main, /syncColorInputs\(\$\('#theme-form'\)\)/);
  assert.match(plugins, /addType\('color'/);
  assert.match(zeroInspector, /\["fill", "color", "stroke"\][\s\S]{0,120}type: "color"/);
  assert.match(zeroInspector, /"hoverFill"[\s\S]{0,120}type: "color"/);
  assert.match(background, /syncColorInput\(field\('container-overlay-color'\)\)/);
  assert.match(zeroPicker, /getSharedColorPicker/);
  assert.match(pickerCss, /\.jz-color-dialog/);
  assert.match(sharedPicker, /contextmenu/);
  assert.match(sharedPicker, /Удалить цвет/);
  assert.match(sharedPicker, /aria-keyshortcuts/);
});
