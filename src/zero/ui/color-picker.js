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
        allowOpacity: true,
        label: "Цвет фона секции",
        onChange: apply,
      });
    },
  };
}
