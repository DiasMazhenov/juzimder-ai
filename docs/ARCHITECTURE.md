# Архитектура

Браузер → same-origin PHP JSON API → Store → PDO SQLite. Vite собирает frontend в public; backend/ и var/ находятся вне document root. GrapesJS загружается динамически при открытии редактора. Нет Studio SDK, Node runtime сервера, микросервисов и форков Core.

- src/main.js: auth/dashboard/editor lifecycle, Pages/Devices/UndoManager/StyleManager/Traits/Layers, native components, debounced autosave, history and conflict recovery.
- src/blocks.js: структурные definitions Hero/Features/CTA/Text/Image. Не HTML templates.
- backend/Domain.php: Document policy и Store с проверкой owner, CAS-save/restore и revisions (последние 200 на проект).
- backend/api.php: session/CSRF/input/errors и маршрутизация.
- backend/migrations/: sequential SQLite schema upgrades; schema v4 adds immutable `publication_versions` and an active `project_publications` pointer.
- public/router.php: dispatches the admin app/API and isolates `<project-id>.<JUZIMDER_PUBLIC_HOST_SUFFIX>` to static public routes.

Проект одновременно выполняет роль workspace/site; внутри несколько Pages. Публикация использует `publication_versions` для immutable snapshots и `project_publications` для активного снимка. Managed Asset и Domain storage ещё не реализованы.

API: GET session, POST register/login/logout, GET/POST projects, GET/PUT projects/{id}, GET projects/{id}/revisions (UI показывает до 50 последних), POST projects/{id}/revisions/restore, GET/PUT/DELETE projects/{id}/ai/settings, POST projects/{id}/ai/design-plan, POST projects/{id}/operations и GET projects/{id}/export/static, GET projects/{id}/publication, POST projects/{id}/publish и POST projects/{id}/unpublish. Проектные операции требуют session.user и owner check; mutations требуют X-CSRF-Token. `ProjectOperations` сохраняет typed operations через `Store::save` с expected-version CAS, Document validation и revision insert. Static export повторно валидирует сохранённый envelope и выдаёт ZIP только владельцу; `StaticSiteExporter` преобразует allowlisted компоненты в HTML, ограничивает CSS правила/медиазапросы, применяет SEO/design tokens и не экспортирует скрипты. Publish повторно рендерит только сохранённый документ, проверяет project owner и expected document version и атомарно активирует новый immutable snapshot; unpublish удаляет активный указатель, оставляя версии в истории. OpenRouter credentials принадлежат проекту: API key хранится зашифрованным в `project_ai_credentials`, шифровальный key-файл с правами 0600 лежит рядом с SQLite. UI и Project Data получают только статус/модель, секрет возвращается только внутреннему backend-коду для вызова фиксированного OpenRouter endpoint.

Текущий `ProjectOperations` — начальный pure application service для преобразований дерева. Его набор ещё неполный: duplicate отложен до remap ссылок и styles. AI, editor и MCP пока не вызывают этот service. Следующий общий слой должен дать всем transport одинаковые owner, policy, expected-version и revision правила. MCP будет отдельным transport-процессом, работающим с backend без открытого браузера; endpoint сейчас не существует.

Обычное сохранение не меняет опубликованный сайт. Preview редактора остаётся sandbox iframe; JSON export — переносимый документ. Явный publish рендерит static HTML/CSS с серверной allowlist-фильтрацией и доступен на отдельном per-project origin. Public router не запускает admin session и не раздаёт API; поддерживает только главную, page slugs и общий CSS. Managed assets и preview candidate до публикации ещё не реализованы.

GrapesJS API сверены с официальными docs: https://grapesjs.com/docs/modules/Storage.html и https://grapesjs.com/docs/api/editor.html , Context7 /grapesjs/grapesjs и исходники установленной 0.23.8. Для сохранения используется Project Data, а не getHtml/getCss. HTML/CSS используются только в sandbox preview.
