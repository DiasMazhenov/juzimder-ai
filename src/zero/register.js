import { tablerIcon } from "../editor-icons.js";
import { createZeroData } from "./model/model.js";
import { renderZero } from "./render.js";
export function registerZero(
  editor,
  { projectId, beforeOpen, isClean, onRemote, notify },
) {
  const open = async (model) => {
    if (!model || model.get("type") !== "zero-block") return;
    const popup = window.open("about:blank", `jz-zero-${model.getId()}`);
    if (!popup) {
      notify("Разрешите открытие Zero Editor в новом окне.", true);
      return;
    }
    const pageId = editor.Pages.getSelected().id;
    popup.opener = null;
    await beforeOpen();
    if (!isClean()) {
      popup.close();
      notify("Сначала сохраните изменения и разрешите конфликт.", true);
      return;
    }
    popup.location.href = `/projects/${projectId}/pages/${pageId}/zero/${model.getAttributes().id}/edit`;
  };
  editor.Commands.add("zero:edit", { run: () => open(editor.getSelected()) });
  editor.Components.addType("zero-block", {
    model: {
      defaults: {
        tagName: "section",
        name: "Zero Block",
        droppable: false,
        editable: false,
        stylable: false,
        resizable: false,
        zeroData: null,
        components: [],
        traits: [],
      },
      init() {
        if (!this.get("zeroData"))
          this.set("zeroData", createZeroData(), { silent: true });
        if (!this.getAttributes().id)
          this.addAttributes({ id: `zero-${crypto.randomUUID()}` });
      },
      toHTML() {
        return renderZero(this.get("zeroData"), this.getAttributes().id);
      },
    },
    view: {
      events: { dblclick: "editZero" },
      editZero(event) {
        event.preventDefault();
        event.stopPropagation();
        open(this.model);
      },
      init() {
        this.listenTo(
          this.model,
          "change:zeroData change:attributes",
          this.render,
        );
      },
      onRender() {
        this.el.innerHTML = renderZero(
          this.model.get("zeroData"),
          this.model.getAttributes().id,
        );
        this.el.querySelector(".jz-zero-block").style.pointerEvents = "none";
      },
    },
  });
  editor.Blocks.add("zero-block", {
    label: "Zero Block",
    category: "Секции",
    content: { type: "zero-block" },
  });
  const card = document.createElement("button");
  card.type = "button";
  card.className = "block-card";
  card.dataset.category = "Секции";
  card.innerHTML = `<div class="block-media" aria-hidden="true">${tablerIcon("layout-grid")}</div><strong>Zero Block</strong>`;
  card.onclick = () => {
    const [model] = editor.addComponents({ type: "zero-block" });
    editor.select(model);
  };
  document.querySelector(".block-list").prepend(card);
  const edit = document.createElement("button");
  edit.type = "button";
  edit.textContent = "Редактировать Zero Block";
  edit.hidden = true;
  edit.id = "edit-zero-block";
  edit.onclick = () => open(editor.getSelected());
  document.querySelector("#quick-edit").prepend(edit);
  const info = document.createElement("p");
  info.id = "zero-info";
  info.textContent = "Свободная абсолютная компоновка. Координаты элементов настраиваются в Zero Editor.";
  info.hidden = true;
  document.querySelector("#quick-edit").append(info);
  editor.on("component:selected", (component) => {
    info.hidden = component.get("type") !== "zero-block";
    edit.hidden = component.get("type") !== "zero-block";
  });
  editor.on("component:deselected", () => {
    edit.hidden = true;
    info.hidden = true;
  });
  const channel = new BroadcastChannel(`jz-zero-${projectId}`);
  channel.onmessage = (event) => {
    const m = event.data;
    if (m?.type === "zero:saved" && m.projectId === projectId) onRemote(m);
  };
  editor.on("destroy", () => channel.close());
}
