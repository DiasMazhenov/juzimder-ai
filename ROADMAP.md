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
- [x] Project Pages Dashboard: отдельные URL управления проектом и editor pages.
- [x] Server-authoritative page create/rename/duplicate/delete, unique route slugs, home selection и revision restore.
- [x] Отдельный settings navigation shell; незапущенные backend-функции честно обозначены.
- [x] Явно показывать текущую версию продукта в dashboard и editor из `productVersion`.
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
- [ ] Intent → plan → structured generation → validate → editable output → revision.
- [ ] Targeted edits по stable id и реальные browser save/reload сценарии.
- [ ] P1: несколько пользовательских AI profiles и защищённые credentials; сейчас active model/key принадлежат окружению сервера.

## Phase 5.1 — глобальные настройки проекта (P1)

- [x] Базовые tokens (8 цветов, общий системный шрифт, radius) сохраняются в versioned project settings и revisions; forms валидируются на сервере.
- [x] Editor и AI presets используют общую тему; изменения синхронизируют page roots и сохраняют локальные overrides.
- [ ] Полная typography: раздельные heading/body fonts, weights, base size, H1–H6, line height, letter spacing и responsive шкала.
- [ ] Подключить tokens к будущему renderer/export; пока public output не существует.
- [ ] SEO metadata and analytics ids; inject only in public renderer.
- [ ] Forms: validated submission endpoints, rate limiting, safe integrations and spam protections.
- [ ] Custom HEAD/CSS with strict bounds and isolated preview; never run in admin origin.
- [ ] Domain setup after public routing/DNS/SSL verification exists.
- [ ] Global Header/Footer/404 components with per-page inclusion and real 404 responses in publication.

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

**Текущее состояние:** foundation, версия продукта 00.00.009. P0 project pages/dashboard, versioned routes/home, settings navigation shell и editor Layers/Sections/Body/Flex controls реализованы. P1 slices `.008` и `.009` добавили валидируемые цвета, typography tokens, сохранение/revisions/restore, редактор и AI presets; Codex Browser подтвердил сохранение, reload и рендер в редакторе. Header/Footer/404 остаются неподключёнными. Следующий slice — SEO settings с backend validation и persistence; до публикационного потребителя не называть их подключёнными. Analytics/forms/custom code/AI profiles/domain/publication ещё в очереди. Clipboard Cmd/Ctrl+C/V остаётся NOT RUN из-за ограничений браузерного моста; live OpenRouter acceptance ожидает credentials. Import API/UI пока не реализованы; versioned migration contract уже описан.
