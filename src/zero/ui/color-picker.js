import { getSharedColorPicker, hexToHsv, hsvToHex } from "../../ui/color-picker.js";

export { hexToHsv, hsvToHex };

export function installZeroColorPicker({
  read,
  apply,
  label = "Цвет фона секции",
  allowOpacity = true,
  allowFill = true,
}) {
  const picker = getSharedColorPicker();
  return {
    open(trigger) {
      const current = read();
      picker.open({
        trigger,
        color: current.color,
        opacity: current.opacity,
        fill: current.fill,
        allowOpacity,
        allowFill,
        label,
        onChange: apply,
      });
    },
  };
}
