# План Juzimder AI

Обновлено 21.10.2026. Галочка означает реализованное и проверенное поведение, а не обещание полного MVP.

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
- [x] Редактируемые глобальные design tokens проекта: восемь цветов, шрифт и скругление; сохраняются в Project Data и применяются к готовым секциям.
- [ ] Полная библиотека секций и полная keyboard/copy-paste QA.
- [x] Частичный Codex Browser responsive smoke: девять секций на 375/768 px, FAQ также на desktop; Tab и видимый focus ring.
- [ ] Подтвердить Cmd/Ctrl+C/V в браузере, который передаёт модификаторы; проверить Image preset с тестовым HTTPS-ресурсом.
- [x] Cmd/Ctrl+Z, Shift+Z/Y и Cmd/Ctrl+S; сочетания игнорируют поля ввода.
- [x] На viewport до 800 px доступны панели «Холст», «Секции» и «Свойства».
- [ ] Универсальные component operations и расширение schema при добавлении новых типов.
- [ ] Полная responsive browser матрица соседних блоков и приёмка на реальном мобильном устройстве.

## Phase 4 — Zero Canvas

- [ ] Отдельный free-position контейнер, drag/resize/guides/snap/alignment/breakpoints.

## Phase 5 — AI

- [x] Server OpenRouter abstraction and authenticated non-mutating design-plan endpoint with strict JSON Schema, token cap, timeout/retry, redacted errors and usage metadata.
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

**Текущее состояние:** foundation, версия продукта 00.00.003, с рабочим ручным редактором, десятью библиотечными presets, редактируемой проектной темой и первым server-side AI plan endpoint. Полные 15 критериев MVP ещё не закрыты. Следующий шаг — завершить clipboard и Image acceptance, затем связать AI plan с preview и typed component operations; ни один AI-ответ пока не применяет изменения к проекту. Import API/UI ещё не реализованы; для них описан versioned migration contract.
