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
- [ ] Полная библиотека секций, tokens, Hug/Fill, min/max controls, keyboard/copy-paste QA.
- [ ] Универсальные component operations и расширение schema при добавлении новых типов.
- [ ] Responsive browser матрица соседних блоков и полноценного mobile authoring.

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

**Текущее состояние:** foundation с рабочим ручным редактором. Полные 15 критериев MVP ещё не закрыты. Следующий шаг — расширить библиотеку и пройти responsive/accessibility QA перед интеграцией AI. Import API/UI ещё не реализованы; для них описан versioned migration contract.
