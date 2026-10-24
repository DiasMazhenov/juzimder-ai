import {
  breakpointEntries,
  breakpointForViewport,
} from "./model/model.js";

export function editorMode(editor, data) {
  const name = editor.getDevice();
  if (name === "Desktop") return "desktop";
  const device = editor.Devices.get(name);
  const width = Number.parseFloat(device?.get?.("width"));
  return Number.isFinite(width)
    ? breakpointForViewport(data, width)
    : breakpointEntries(data)[0]?.[0] || "desktop";
}
