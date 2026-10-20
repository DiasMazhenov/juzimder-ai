# Архитектура

Браузер → same-origin PHP JSON API → Store → PDO SQLite. Vite собирает frontend в public; backend/ и var/ находятся вне document root. GrapesJS загружается динамически при открытии редактора. Нет Studio SDK, Node runtime сервера, микросервисов и форков Core.

- src/main.js: auth/dashboard/editor lifecycle, Pages/Devices/UndoManager/StyleManager/Traits/Layers, native components, явное сохранение.
- src/blocks.js: структурные definitions Hero/Features/CTA/Text/Image. Не HTML templates.
- backend/Domain.php: Document policy и Store с проверкой owner, CAS-save и revisions.
- backend/api.php: session/CSRF/input/errors и маршрутизация.
- backend/migrations/001_initial.sql: users/projects/documents/revisions/auth_attempts, SQLite user_version=1.
- public/router.php: единственный публичный PHP entry, разрешённые assets и API.

Проект одновременно выполняет роль workspace/site в первом срезе; внутри несколько Pages. Отдельные Workspace/Site/Asset/Domain/Publication/AIAction сущности вводить при появлении использующего их поведения. Сейчас их таблиц нет.

API: GET session, POST register/login/logout, GET/POST projects, GET/PUT projects/{id}, GET projects/{id}/revisions. Все операции с проектом требуют session.user; каждый запрос проверяет owner; mutations требуют X-CSRF-Token. Секретов AI в браузере нет: интеграции пока нет.

Дальнейший application service слой должен оборачивать Document + Store и добавлять структурные операции, а не дублировать их для API/AI/MCP. MCP будет отдельным transport-процессом, работающим с backend без открытого браузера. Его endpoint сейчас не существует.

Сохранение не генерирует публикацию. Preview — iframe sandbox без scripts и same-origin capabilities; JSON export — переносимый документ, не static site export. Публикации должны использовать отдельный origin, только разрешённый статический output и управляемые assets.

GrapesJS API сверены с официальными docs: https://grapesjs.com/docs/modules/Storage.html и https://grapesjs.com/docs/api/editor.html , Context7 /grapesjs/grapesjs и исходники установленной 0.23.8. Для сохранения используется Project Data, а не getHtml/getCss. HTML/CSS используются только в sandbox preview.
