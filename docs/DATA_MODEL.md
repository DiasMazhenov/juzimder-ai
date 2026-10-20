# Модель данных v1

Envelope: `{schemaVersion:1, engine:"grapesjs", data: ProjectData}`. Структурная JSON Schema — schemas/project.schema.json; authoritative runtime security validation — backend/Domain.php::Document::validate. Версия API и версия документа различны: `version` — optimistic concurrency counter, `schemaVersion` — формат.

`data.pages[]`: стабильные id/name, один frames[] на страницу; frame.component — wrapper, вложенные components, head/docEl служебные элементы Core. `data.styles[]` содержит selectors, style, mediaText; responsive CSS не преобразуется. `data.assets[]` сохраняется вместе с проектом. Другие Core metadata сохраняются losslessly при прохождении policy. Нельзя заявлять произвольные Core plugins совместимыми: custom type потребует schema/policy change.

В component definitions сохраняются type/tagName/name/content/attributes/style/components. GrapesJS генерирует component ids и хранит их в attributes.id; round trip не заменяет id. Нативные text/textnode/link/image/default/wrapper и служебные main/head допустимы. Script, script-export, event attrs, srcdoc/srcset/formaction запрещены; tag allowlist ограничен. Редактор может показать временное неподдерживаемое состояние, сервер вернёт 422 без записи. Продвинутая clipboard/import policy ещё не реализована.

Limits: 1–30 страниц, 1 frame/page, 2 МБ сериализованного документа, глубина 45, обход не более 50 000 значений. HTML content — только базовое inline formatting. URL — HTTPS/якорь/mailto/tel, CSS url()/@import запрещены. Удалённые ресурсы не проксируются сервером. Свойства layout используют CSS display:flex/grid, gap, padding и responsive rules; отдельного layout AST нет.

PUT body: `{version: currentVersion, document: envelope}`. Сервер сначала проверяет owner и формат, затем внутри транзакции UPDATE WHERE version=?, increment и INSERT revision. Конфликт возвращает 409; нет overwrite/автоматического merge. Данные и ревизия коммитятся вместе. Schema upgrades сейчас отклоняются; миграции v2 должны быть явными и тестировать обратную совместимость. Undo stack не сериализуется: это отдельная от revisions история открытого редактора.
