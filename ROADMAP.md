# План Juzimder AI

Обновлено 20.10.2026. Галочка означает реализованное и проверенное поведение, а не обещание полного MVP.

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
- [x] Dashboard: создать проект и открыть редактор.
- [x] GrapesJS Core: слои, traits, inspector, секции, pages/devices.
- [x] Добавить → изменить → сохранить → reload → восстановить.
- [x] Полный JSON, immutable revisions, конфликт версий.
- [x] Domain/API tests и Codex Browser screenshots.
- [ ] Migration runner, account recovery, session expiry, production hardening.
- [x] History/restore UI, debounced autosave, safe conflict recovery, bounded revision retention.

## Phase 3 — Component System / Auto Layout

- [x] Базовые структурные Hero/Features/CTA/Text/Image и CSS Flex/Grid controls.
- [x] Переключение display сохраняет прочие CSS свойства; Undo/Redo Core.
- [x] Структурная schema v1 согласована с runtime policy; negative/security fixtures и контракт импорта/миграций.
- [x] Inspector: Hug/Fill для ширины и высоты; min/max ограничения с проверкой единиц и сохранением/reload.
- [x] Shared palette tokens и пять новых секций (Navigation, Process, Testimonial, FAQ, Footer); все presets проходят backend Project Data validator.
- [ ] Полная библиотека секций, design tokens и полная keyboard/copy-paste QA.
- [x] Cmd/Ctrl+Z, Shift+Z/Y и Cmd/Ctrl+S; сочетания игнорируют поля ввода.
- [x] На viewport до 800 px доступны панели «Холст», «Секции» и «Свойства».
- [ ] Универсальные component operations и расширение schema при добавлении новых типов.
- [ ] Полная responsive browser матрица соседних блоков и приёмка на реальном мобильном устройстве.

## Phase 4 — Zero Canvas

- [ ] Отдельный free-position контейнер, drag/resize/guides/snap/alignment/breakpoints.

## Phase 5 — AI

- [ ] Server OpenRouter abstraction, limits/timeout/retry/cost/error handling.
- [ ] Intent → plan → structured generation → validate → editable output → revision.
- [ ] Targeted edits по stable id и реальные browser save/reload сценарии.

## Phase 6 — MCP

- [ ] Scoped credentials и shared services, official SDK transport.
- [ ] Управление компонентами/страницами/revisions при закрытом редакторе.

## Phase 7 — публикация и экспорт

- [x] Экспорт переносимого JSON (не static site export).
- [ ] Static renderer/assets, ZIP, отдельный public origin, SEO.
- [ ] Domains и publication versions.

## Phase 8 — стабилизация MVP

- [x] Первый ручной end-to-end сценарий с сохранением и PNG.
- [ ] AI/local edit/revision restore/static publish end-to-end.
- [ ] Security review, backups/restore, a11y, multi-device acceptance, release license.

**Текущее состояние:** foundation, версия продукта 00.00.001, с рабочим ручным редактором и десятью библиотечными presets. Полные 15 критериев MVP ещё не закрыты. Следующий шаг — редактируемые глобальные design tokens, полная keyboard/copy-paste и responsive/accessibility QA перед интеграцией AI. Import API/UI ещё не реализованы; для них описан versioned migration contract.
