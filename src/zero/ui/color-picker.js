import { getSharedColorPicker, hexToHsv, hsvToHex } from "../../ui/color-picker.js";

export { hexToHsv, hsvToHex };

export function installZeroColorPicker({ read, apply }) {
  const picker = getSharedColorPicker();
  return {
    open(trigger) {
      const current = read();
      picker.open({
        trigger,
        color: current.color,
        opacity: current.opacity,
        fill: current.fill,
        allowOpacity: true,
        allowFill: true,
        label: "Цвет фона секции",
        onChange: apply,
      });
    },
  };
}
