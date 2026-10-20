# Архитектура

Браузер → same-origin PHP JSON API → Store → PDO SQLite. Vite собирает frontend в public; backend/ и var/ находятся вне document root. GrapesJS загружается динамически при открытии редактора. Нет Studio SDK, Node runtime сервера, микросервисов и форков Core.

- src/main.js: auth/dashboard/editor lifecycle, Pages/Devices/UndoManager/StyleManager/Traits/Layers, native components, debounced autosave, history and conflict recovery.
- src/blocks.js: структурные definitions Hero/Features/CTA/Text/Image. Не HTML templates.
- backend/Domain.php: Document policy и Store с проверкой owner, CAS-save/restore и revisions (последние 200 на проект).
- backend/api.php: session/CSRF/input/errors и маршрутизация.
- backend/migrations/001_initial.sql: users/projects/documents/revisions/auth_attempts, SQLite user_version=1.
- public/router.php: единственный публичный PHP entry, разрешённые assets и API.

Проект одновременно выполняет роль workspace/site в первом срезе; внутри несколько Pages. Отдельные Workspace/Site/Asset/Domain/Publication/AIAction сущности вводить при появлении использующего их поведения. Сейчас их таблиц нет.

API: GET session, POST register/login/logout, GET/POST projects, GET/PUT projects/{id}, GET projects/{id}/revisions (UI показывает до 50 последних), POST projects/{id}/revisions/restore, GET /api/ai/status, POST /api/ai/design-plan и POST /api/projects/{id}/operations. Все операции с проектом требуют session.user; каждый запрос проверяет owner; mutations требуют X-CSRF-Token. `ProjectOperations` применяет ограниченный typed-operation набор; API сохраняет итог через тот же `Store::save` с expected-version CAS, Document validation и revision insert. OpenRouter key читается только PHP backend из окружения, браузеру не передаётся.

Текущий `ProjectOperations` — начальный pure application service для преобразований дерева. Его набор ещё неполный: duplicate отложен до remap ссылок и styles. AI, editor и MCP пока не вызывают этот service. Следующий общий слой должен дать всем transport одинаковые owner, policy, expected-version и revision правила. MCP будет отдельным transport-процессом, работающим с backend без открытого браузера; endpoint сейчас не существует.

Сохранение не генерирует публикацию. Preview — iframe sandbox без scripts и same-origin capabilities; JSON export — переносимый документ, не static site export. Публикации должны использовать отдельный origin, только разрешённый статический output и управляемые assets.

GrapesJS API сверены с официальными docs: https://grapesjs.com/docs/modules/Storage.html и https://grapesjs.com/docs/api/editor.html , Context7 /grapesjs/grapesjs и исходники установленной 0.23.8. Для сохранения используется Project Data, а не getHtml/getCss. HTML/CSS используются только в sandbox preview.
