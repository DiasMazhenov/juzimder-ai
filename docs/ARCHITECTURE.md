# Архитектура

Браузер → same-origin PHP JSON API → Store → PDO SQLite. Vite собирает frontend в public; backend/ и var/ находятся вне document root. GrapesJS загружается динамически при открытии редактора. Нет Studio SDK, Node runtime сервера, микросервисов и форков Core.

- src/main.js: auth/dashboard/editor lifecycle, Pages/Devices/UndoManager/StyleManager/Traits/Layers, native components, debounced autosave, history and conflict recovery.
- src/blocks.js: структурные definitions Hero/Features/CTA/Text/Image. Не HTML templates.
- backend/Domain.php: Document policy и Store с проверкой owner, CAS-save/restore и revisions (последние 200 на проект).
- backend/api.php: session/CSRF/input/errors и маршрутизация.
- backend/migrations/001_initial.sql: users/projects/documents/revisions/auth_attempts, SQLite user_version=1.
- public/router.php: единственный публичный PHP entry, разрешённые assets и API.

Проект одновременно выполняет роль workspace/site в первом срезе; внутри несколько Pages. Отдельные Workspace/Site/Asset/Domain/Publication/AIAction сущности вводить при появлении использующего их поведения. Сейчас их таблиц нет.

API: GET session, POST register/login/logout, GET/POST projects, GET/PUT projects/{id}, GET projects/{id}/revisions (UI показывает до 50 последних), POST projects/{id}/revisions/restore, GET/PUT/DELETE projects/{id}/ai/settings, POST projects/{id}/ai/design-plan, POST projects/{id}/operations и GET projects/{id}/export/static. Проектные операции требуют session.user и owner check; mutations требуют X-CSRF-Token. `ProjectOperations` сохраняет typed operations через `Store::save` с expected-version CAS, Document validation и revision insert. Static export повторно валидирует сохранённый envelope и выдаёт ZIP только владельцу; `StaticSiteExporter` преобразует allowlisted компоненты в HTML, ограничивает CSS правила/медиазапросы, применяет SEO/design tokens и не экспортирует скрипты. Он не создаёт публикационную запись и не раздаёт архив на public origin. OpenRouter credentials принадлежат проекту: API key хранится зашифрованным в `project_ai_credentials`, шифровальный key-файл с правами 0600 лежит рядом с SQLite. UI и Project Data получают только статус/модель, секрет возвращается только внутреннему backend-коду для вызова фиксированного OpenRouter endpoint.

Текущий `ProjectOperations` — начальный pure application service для преобразований дерева. Его набор ещё неполный: duplicate отложен до remap ссылок и styles. AI, editor и MCP пока не вызывают этот service. Следующий общий слой должен дать всем transport одинаковые owner, policy, expected-version и revision правила. MCP будет отдельным transport-процессом, работающим с backend без открытого браузера; endpoint сейчас не существует.

Сохранение не генерирует публикацию. Preview — iframe sandbox без scripts и same-origin capabilities; JSON export — переносимый документ. Server-side ZIP export создаёт статические HTML/CSS файлы из сохранённого Project Data и настроек, но приложение ещё не размещает сайт публично. Публикации должны использовать отдельный origin, только разрешённый статический output и управляемые assets.

GrapesJS API сверены с официальными docs: https://grapesjs.com/docs/modules/Storage.html и https://grapesjs.com/docs/api/editor.html , Context7 /grapesjs/grapesjs и исходники установленной 0.23.8. Для сохранения используется Project Data, а не getHtml/getCss. HTML/CSS используются только в sandbox preview.
