# План Juzimder AI

Обновлено 22.10.2026. Галочка означает реализованное и проверенное поведение, а не обещание полного MVP.

## Phase 0 — аудит и архитектура

- [x] Проверить пустой основной repo и branch main.
- [x] Прочитать исходники PHP-GrapesJS, auth/DB/persistence/files/routes/public rendering.
- [x] Проверить LICENSE/manifests и bundled license discrepancy; не копировать CMS.
- [x] Сравнить A/B/C и выбрать PHP-first собственный backend.
- [x] Изучить Core API и определить versioned Project Data.
- [x] Создать архитектурную документацию и security boundaries.
- [ ] Полный upstream SBOM/CVE/license review (не нужен для отклонённого reuse, обязателен перед заимствованием).

## Phase 1–2 — первый вертикальный срез

- [x] PHP API, SQLite schema v1, session auth, CSRF, owner checks.
- [x] Loopback-only `admin/admin` test login for `npm run serve`; not enabled for public hosting.
- [x] Unique local URL `http://juzimder-ai.localhost:8080/`, idempotent local launcher, redirects from legacy loopback URLs, DB-scoped session cookies and safe recovery from stale local sessions.
- [x] Dashboard: создать проект и открыть редактор.
- [x] Project Pages Dashboard: отдельные URL управления проектом и editor pages.
- [x] Разделы настроек сайта находятся в боковой панели проекта; на узких экранах используют горизонтальную прокрутку.
- [x] Server-authoritative page create/rename/duplicate/delete, unique route slugs, home selection и revision restore.
- [x] Отдельный settings navigation shell; незапущенные backend-функции честно обозначены.
- [x] Явно показывать текущую версию продукта в dashboard и editor из `productVersion`.
- [x] GrapesJS Core: слои, traits, inspector, секции, pages/devices.
- [x] Добавить → изменить → сохранить → reload → восстановить.
- [x] Полный JSON, immutable revisions, конфликт версий.
- [x] Domain/API tests и Codex Browser screenshots.
- [ ] Migration runner, account recovery, production session expiry and hardening.
- [x] History/restore UI, debounced autosave, safe conflict recovery, bounded revision retention.

## Phase 3 — Component System / Auto Layout

- [x] Базовые структурные Hero/Features/CTA/Text/Image и CSS Flex/Grid controls.
- [x] Переключение display сохраняет прочие CSS свойства; Undo/Redo Core.
- [x] Структурная schema v1 согласована с runtime policy; negative/security fixtures и контракт импорта/миграций.
- [x] Inspector: Hug/Fill для ширины и высоты; min/max ограничения с проверкой единиц и сохранением/reload.
- [x] Shared palette tokens и пять новых секций (Navigation, Process, Testimonial, FAQ, Footer); все presets проходят backend Project Data validator.
- [x] Базовые design tokens проекта: восемь цветов, общий шрифт и скругление; schema-validated настройки и CSS variables корней синхронизированы с Project Data, применяются к presets.
- [x] Расширенные typography tokens: heading/body families and weights, base size, H1–H6 scale, line-height, letter spacing, fluid H1–H3; legacy settings migrate from schema v2 and local overrides remain intact.
- [x] Библиотека десяти структурных секций и базовых элементов (контейнер/кнопка) с поиском и категориями.
- [ ] Полная keyboard/copy-paste QA.
- [x] Частичный Codex Browser responsive smoke: девять секций на 375/768 px, FAQ также на desktop; Tab и видимый focus ring.
- [x] Проверить Image preset с тестовым HTTPS-ресурсом, save и reload.
- [ ] Подтвердить Cmd/Ctrl+C/V в браузере, который передаёт модификаторы.
- [x] Cmd/Ctrl+Z, Shift+Z/Y и Cmd/Ctrl+S; сочетания игнорируют поля ввода.
- [x] На viewport до 800 px доступны панели «Холст», «Секции» и «Свойства».
- [x] Floating Layers/Sections drawers, icon Flex controls и layout для Page Root/Body; isolated-browser save/reload подтвердил Body Flex.
- [x] Начальный authenticated typed-operation API: create, update content/styles/layout, delete, move и reorder; ownership, expected version, Document policy и revision save.
- [x] Component context menu on canvas, inspector and Layers with copy/cut/paste/duplicate/reorder/select-parent/delete; Codex Browser confirmed right-click on a layer row selects that component and opens the shared menu; duplicate→Undo/reload, delete confirmation, keyboard navigation and reference remapping are also checked.
- [ ] Универсальные component operations, duplicate с безопасным remap ссылок/styles и расширение schema при добавлении новых типов.
- [ ] Полная responsive browser матрица соседних блоков и приёмка на реальном мобильном устройстве.

## Phase 4 — Zero Block (P2, только архитектура)

- [ ] Отдельный тип секции `zero-block` внутри обычного GrapesJS page component tree; не глобальный режим canvas.
- [ ] Собственное дерево элементов и responsive properties внутри full Project Data; расширить validator через версионируемую migration.
- [ ] Отдельный Zero Block Editor с открытием из секции, возвратом в основной редактор и общим CAS/revision service.
- [ ] Свободное позиционирование, drag/resize/guides/snap/alignment/breakpoints.
- [ ] Не начинать реализацию редактора до закрытия P0/P1 и проверки component schema/undo/persistence.

## Phase 5 — AI

- [x] Server OpenRouter abstraction and authenticated non-mutating design-plan endpoint with strict JSON Schema, token cap, timeout/retry, redacted errors and usage metadata.
- [x] Редактор: disclosure о передаче prompt, статус провайдера, предпросмотр плана и отдельное подтверждение перед добавлением preset-секций typed operations с новой revision.
- [x] Intent → plan → structured text generation for supported section presets → server/client validation → editable GrapesJS output → revision.
- [x] Address-scoped AI edits: selected component, stable ID/path checks, typed operations, diff confirmation and Project Data/CAS save; browser normal-editor save/reload verified in 00.00.030.
- [ ] Live AI edit acceptance: real provider proposal → confirm → save → reload; the configured free model returned a temporary unavailable response.
- [ ] Skill-driven agent: convert an uploaded skill into a traceable requirements checklist, enforce tool/result validators, collect acceptance evidence and fail closed on conflicts or unverifiable requirements.
- [x] Per-project OpenRouter key/model form in Site Settings → AI Engine; encrypted server storage, no environment-key fallback, no key in Project Data/revisions/API responses.
- [x] Несколько OpenRouter profiles на проект, редактирование, выбор активного профиля, удаление и миграция прежней конфигурации.
- [x] Необязательная резервная модель профиля; используется после повторной попытки основной модели при временной сетевой ошибке или HTTP 408/429/5xx.
- [x] Полностраничная генерация HTML/CSS: strict JSON output, серверная проверка и namespace для разрешённых тегов, атрибутов, классов, ссылок и стилей.
- [x] Исправление распространённой Latin-1/UTF-8 mojibake в AI metadata и фрагментах текста с сохранением корректной кириллицы рядом.
- [x] Безопасные пользовательские сообщения различают сбой провайдера, отказ доступа, некорректный ответ и отклонённый дизайн, не раскрывая API-ключи или сырой ответ.
- [x] JSON Schema согласована с image-slot контрактом: только пустые типизированные markers; сообщения отдельно различают schema, markup и CSS rejection.
- [x] Одна прозрачная авто-попытка исправления model output после schema/HTML/CSS rejection; тот же prompt, без передачи отвергнутого ответа, без ослабления серверного валидатора, usage/cost объединяются.
- [x] Live preview прошёл после согласования image-slot контракта: Nemotron вернул 4 секции и hero + 3 typed slots; импорт не подтверждался.
- [x] Изолированный sandbox preview без скриптов/сети; отдельное подтверждение импорта через GrapesJS Core в редактируемые components/CSS rules и сохранение обычным Project Data/CAS/revision pipeline.
- [x] Live OpenRouter acceptance для полной генерации: preview → confirm/apply → save → reload на отдельной тестовой странице; результат и URL подтверждены в 00.00.024.
- [x] Визуальная приёмка результата реальной модели в 00.00.025: scoped tag/descendant CSS исправил браузерные ссылки/списки; editor snapshot проверен на desktop и 375px mobile, revision 6 сохранена на отдельной QA-странице.
- [x] Локальные image slots в full-page AI generation: обязательное изображение hero, до трёх дополнительных; выбор/загрузка из приватной project library после генерации; file bytes, filenames, IDs and URLs are excluded from OpenRouter request; import produces editable managed GrapesJS image components.
- [ ] Live browser acceptance новых image slots: выбрать файл из библиотеки, проверить blob preview и импорт/сохранение в изолированном QA-проекте.
- [ ] Продолжить live acceptance до восстановления ревизии и публикации отдельного безопасного снимка.

## Phase 5.1 — глобальные настройки проекта (P1)

- [x] Базовые tokens (8 цветов, общий системный шрифт, radius) сохраняются в versioned project settings и revisions; forms валидируются на сервере.
- [x] Editor и AI presets используют общую тему; изменения синхронизируют page roots и сохраняют локальные overrides.
- [ ] Полная typography: раздельные heading/body fonts, weights, base size, H1–H6, line height, letter spacing и responsive шкала.
- [ ] Подключить tokens к будущему renderer/export; пока public output не существует.
- [x] Per-page SEO title, description, robots directive and HTTPS social image metadata; validated and stored in project settings/revisions.
- [ ] Inject SEO metadata into the public renderer when publication exists; settings do not affect external pages yet.
- [ ] Analytics ids; accept only when a public renderer can consume them.
- [ ] Forms: validated submission endpoints, rate limiting, safe integrations and spam protections.
- [ ] Custom HEAD/CSS with strict bounds and isolated preview; never run in admin origin.
- [ ] Domain setup after public routing/DNS/SSL verification exists.
- [ ] Global Header/Footer/404 components with per-page inclusion and real 404 responses in publication.

## Phase 6 — MCP

- [ ] Scoped credentials и shared services, official SDK transport.
- [ ] Управление компонентами/страницами/revisions при закрытом редакторе.

## Phase 7 — публикация и экспорт

- [x] Экспорт переносимого JSON (не static site export).
- [x] Server-side static HTML/CSS renderer and ZIP export for all saved pages, consuming per-page SEO metadata and validated design tokens.
- [x] Separate per-project public origin, explicit publish/unpublish, immutable static publication versions, owner/CSRF/CAS checks.
- [x] Owner-authenticated preview of the latest saved candidate across all pages in a sandboxed frame; no publish or revision mutation.
- [x] Managed raster image library: authenticated project-scoped upload/list/delivery, Project Data references, isolated preview, ZIP export and immutable public snapshots.
- [ ] Wildcard DNS/TLS deployment and custom domains after public-origin production validation.

## Phase 8 — стабилизация MVP

- [x] Первый ручной end-to-end сценарий с сохранением и PNG.
- [x] Структурированный AI-plan fixture → typed operations → локальная правка → восстановление ревизии → preview/ZIP/publish на изолированной SQLite.
- [x] Live OpenRouter full-page preview/apply/save/reload пройдено в 00.00.024 на отдельной странице проекта.
- [x] Live OpenRouter preview/import/save и desktop/mobile визуальная проверка после улучшений CSS завершены в 00.00.025.
- [ ] Продолжить отдельную live-проверку восстановления ревизии и публикации.
- [ ] Security review, backups/restore, a11y, multi-device acceptance, release license.

**Текущее состояние:** foundation, версия продукта 00.00.031.
