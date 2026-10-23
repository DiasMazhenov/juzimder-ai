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
- [x] Новый Body по умолчанию сохраняет `display:flex` и `flex-direction:column`; новый проект, новая страница и инспектор корня используют вертикальное направление, явные стили не перезаписываются.
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
- [x] Live AI text edit acceptance: real provider proposal → confirm → save → Undo/Redo → reload; QA revision59/60, stable persisted cmp-ID, verified in00.00.067.
- [ ] Live AI style-edit acceptance and additional stale-selection/conflict scenarios.
- [x] Project-scoped user Skill library: upload/paste Markdown, edit, activate, delete, owner/CAS protection, and select a saved Skill for full-page generation. The selected Skill text and design criteria are sent only after generation is confirmed; mandatory import checkboxes were removed in 00.00.063/066.
- [x] Optional versioned juzimder-rules contract in uploaded Markdown: deterministic heading/section counts, DOM text requirements/prohibitions and max-width CSS presence, stable requirement IDs, measured evidence, conflict rejection and one bounded repair before fail-closed.
- [x] AI-assisted extraction of natural-language Skill requirements into a reviewed draft: exact source quotations, allowlisted automatic rules, manual checks, source staleness guard, owner confirmation and separate save. Completeness and semantic equivalence require owner review.
- [x] Фоновая полная AI-генерация: persisted job/status/result, detached local PHP worker, restore after reload, owner/CSRF/CAS guards; real QA Skill generation/import/save/reload verified in 00.00.042. Production worker integration remains open.
- [ ] Skill-driven agent for uploaded skills: convert requirements into traceable machine-checkable criteria where possible, run validators and bounded repair loops, collect evidence, and fail closed on conflicts or requirements that cannot be verified. Arbitrary Skills still rely on model instruction-following and owner review; this version does not guarantee exact adherence.
- [x] Встроенный профиль Taste для полной AI-генерации: subject-specific concept, единый H1 в hero, CTA, заголовок и минимальный объём текста в каждой секции, адаптивное CSS-правило; одна исправляющая попытка и отказ при повторном нарушении. Перед применением пользователь обязан осмотреть 1440/768/390 px и подтвердить уместность идеи. Upstream browser/Node scripts на сервере не запускаются; источник MIT: https://github.com/Leonxlnx/taste-skill.
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
- [x] Live browser acceptance автоматических сетевых image slots по обновлённому требованию владельца: реальная генерация → Commons подбор → preview → native import/save → полный Undo/Redo → reload; QA ревизии56–58, версия00.00.066. Manual upload/blob acceptance этим не заявляется.
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

**Текущее состояние:** foundation, версия продукта 00.00.046.


## Проверка разбора Skills · 00.00.037

Реальная модель выявила избыточное дублирование id/label внутри ruleJson. Теперь модель возвращает идентификатор и название один раз; сервер собирает канонический контракт и проверяет его прежним валидатором. При неверных цитатах или формате допускается одна исправляющая попытка с безопасным кодом причины, без передачи отвергнутого ответа. Использование токенов суммируется, неизвестные значения остаются неизвестными; UI предупреждает о дополнительном расходе. Ошибки провайдера отличаются от ошибок разбора, сырые ответы и секреты не раскрываются.

Live PASS: синтетический pottery Skill разобран через существующую бесплатную модель nvidia/nemotron-3.5-super-vl:free; 5 автоматических правил и 2 ручных требования сверены с исходником. QA Skill сохранён в проекте Design token QA (библиотека v1) и повторно открыт после reload: исходник, правила и три объединённых ручных пункта сохранились. Активный AI-профиль и выбор Skill по умолчанию не менялись. Генерация страницы с этим сохранённым Skill, визуальная desktop/mobile приёмка и применение результата — NOT RUN, следующий этап. Это не подтверждает безошибочное следование произвольному Skill.

Проверки: npm test, npm run build, PHP syntax и git diff --check — PASS. Известное предупреждение Vite: GrapesJS chunk больше 500 КБ.


## Ограничение времени AI-запросов · 00.00.038

Live-проверка Skill → generation выявила PHP fatal после 120 секунд: file_get_contents с read timeout 25 секунд не ограничивал суммарное время ответа при продолжающемся потоке. AI HTTP transport теперь использует PHP cURL с общим timeout 25 секунд, connect timeout до 10 секунд, проверкой TLS, запретом редиректов и прежним лимитом 1 МБ. Один экземпляр провайдера имеет общий бюджет 100 секунд на запросы, повторы, fallback и исправление дизайна, чтобы вернуть контролируемую ошибку до лимита API 120 секунд. PHP cURL требуется для AI; без расширения запрос безопасно отклоняется. Некорректный JSON сервера больше не показывает сырую ошибку парсинга в основном API-клиенте.

PASS: HTTP fixtures проверили непрерывно поступающий медленный ответ (остановлен примерно за 1 секунду при заданном лимите 1 секунда), превышение 1 МБ, отсутствие перехода по redirect и нормальный JSON. Отдельный тест проверяет запрет передачи после исчерпания общего бюджета. npm test, npm run build, PHP syntax, git diff --check — PASS; прежнее предупреждение размера GrapesJS chunk остаётся.

Live: создана отдельная страница Skill pipeline QA — 00.00.037, page ID 4f5f7cb20ec6518534ee492d, revision 38. Выбран сохранённый pottery Skill, отправлено синтетическое описание мастерской. После исправления вместо fatal/ошибки JSON UI вернул контролируемое сообщение «OpenRouter временно недоступен… Проект не изменён». Реальный дизайн не получен: генерация → проверка пяти правил → desktop/mobile review → apply/save/reload остаются BLOCKED из-за таймаутов текущей бесплатной модели. Профили, ключи, default Skill и публичная публикация не менялись. Следующий шаг — повторить этот же сценарий при доступности провайдера; не ослаблять правила ради прохождения приёмки.


## Автоматическая замена OpenRouter-профилей · 00.00.039

Все четыре AI-операции используют request-scoped цепочку сохранённых профилей: сначала активный, затем остальные в порядке создания. Каждый профиль использует собственный зашифрованный ключ и модель; его необязательная резервная модель сохраняет прежнюю роль. При сетевом таймауте, HTTP 408/429/5xx и отказе доступа 401/403 выполняется переход к следующему профилю. Некорректный запрос или ответ, ошибка HTML/CSS/Skill validation не запускают перебор ключей. При нескольких профилях нет повторного сетевого запроса к одной модели до перехода к следующему профилю. Общий бюджет 100 секунд делится между оставшимися профилями; максимум одной передачи повышен до 60 секунд. Активный выбор в БД не меняется. Успешный профиль становится первым для исправляющей попытки в рамках того же запроса; его название доступно в metadata и preview. UI предупреждает о переключении и возможной стоимости каждой попытки. Секреты не возвращаются клиенту.

Тесты PASS: owner protection, active-first ключи из vault, сохранение active selection, failover после timeout/429/502/401, отдельный ключ каждого профиля, повторное использование успешного профиля, отсутствие переключения при HTTP400, ограниченный перебор при недоступности всех, сохранение общего времени. npm test, build, PHP syntax и diff checks прошли; Vite сохраняет предупреждение большого GrapesJS chunk.

Live: настройки показывают версию 00.00.039 и описание автоматической замены; активный профиль «Резервный» и ревизия проекта 38 сохранены. Генерация с pottery Skill и двумя существующими профилями закончилась контролируемой временной недоступностью, без нового содержимого страницы. Успешный реальный failover → дизайн → визуальная приёмка → save/reload остаётся NOT RUN из-за недоступности провайдера; успешное переключение доказано fixtures, а не реальным ответом OpenRouter.


## Короткая проверка сохранённого AI-профиля · 00.00.040

В AI Engine добавлена кнопка «Проверить профиль». Проверяется сохранённая основная модель выбранного профиля: фиксированный служебный prompt, строгий JSON acknowledgement, максимум 256 токенов, одна попытка и общий лимит 20 секунд. Ни fallback, ни другие профили не используются, активный выбор и ревизии не меняются. Несохранённые изменения требуют сохранения перед тестом. UI сообщает о расходе и показывает модель/длительность либо безопасную ошибку; успех короткого запроса не объявляется приёмкой полной генерации. Endpoint проверяет owner, CSRF, version, принадлежность profile ID, allowlist входа и общий AI rate limit. Ключи, исходные ответы и свободные диагностические тексты не возвращаются. В server diagnostics добавлен только числовой curl_errno.

Live PASS: «Резервный» ответил через nvidia/nemotron-3.5-super-vl:free за 1.4 с; «Основной профиль» (openrouter/free) ответил через dots-studio/dots-3-note-preview:free за 2.4 с. Это подтверждает работоспособность ключей и коротких запросов; не доказывает причину предыдущих таймаутов полной генерации. Активный профиль остаётся «Резервный», настройки v2, project revision 38. Повторная компактная генерация pottery Skill также получила два транспортных таймаута (curl_errno 28), без успешного ответа. Генерация → визуальная приёмка → apply/save/reload остаются BLOCKED; следующий этап — диагностика длительных structured-output запросов, а не проверка ключей. Финальный повторный browser snapshot превысил лимит Codex Browser; он не считается успешной UI-приёмкой генерации.

Проверки PASS: npm test, npm run build, PHP syntax, JS syntax, git diff --check. Тесты покрывают bounded synthetic request, неверный acknowledgement, отсутствие секрета в ответе, owner/CSRF/stale version/unknown profile/custom prompt rejection. Сохраняется предупреждение большого GrapesJS chunk.


## Диагностика длительного ответа и профиль принятого результата · 00.00.041

Транспорт фиксирует только числовые диагностические поля при сбое: curl code, HTTP status, elapsedMs, firstByteMs и receivedBytes. Ключи, headers, prompts и response body не логируются. CURL timeout 28 теперь имеет отдельную категорию timeout и понятную подсказку; он остаётся retryable для автоматической замены профилей. Ошибки соединения и отказ ключа не смешиваются с таймаутом ответа. Исправлен metadata bug: если исправляющую генерацию выполнил другой профиль, accepted result показывает его название, а не название первой неудачной попытки.

Диагностическая реальная генерация с синтетическим pottery Skill и ограничением 4000 output tokens (без сохранения проекта) не завершилась. Первый профиль: HTTP200, первый байт 772 мс, timeout49001 мс, 187 байт; второй: HTTP200, первый байт5811 мс, timeout50001 мс, 187 байт. Соединение и HTTP response доступны, но полного JSON дизайна до общего deadline нет. Это не доказывает внутреннюю причину у провайдера и не подтверждает качество дизайна. Уменьшение бюджета само по себе не решило проблему. Принудительное снятие timeout или ослабление sanitizer не выполнялись.

npm test, build, PHP syntax и diff checks — PASS. Тесты подтверждают отдельную retryable timeout category и фактический профиль принятой исправленной генерации. Реальная генерация → visual review → apply/save/reload остаются BLOCKED. Следующий инженерный шаг: отдельный сохраняемый job для длительной генерации с явным статусом и сроком ожидания, чтобы не удерживать UI/API-запрос; это пока план, не реализовано.


## Фоновая генерация и первая сквозная Skill-приёмка · 00.00.042

Редактор запускает POST generation-jobs с pageId и текущей версией; ответ 202 возвращается до обращения к модели. Отдельный доверенный PHP CLI worker получает только DB path и job ID, атомарно забирает queued задачу, читает ключи из vault и выполняет прежний sanitizer/Skill audit. В приватной SQLite сохраняются request snapshot (описание и выбранный Skill), состояния queued/running/succeeded/failed и проверенный результат. Ни ключей, ни cookies в payload нет. Project Data не изменяется worker-ом. Применение — явный preview/confirmation и прежний CAS save. Одновременно разрешена одна активная задача на проект; повторный worker не повторяет вызов провайдера. GET статуса защищён owner; POST — auth/CSRF/validation/version/rate limit. Клиент опрашивает статус короткими запросами и восстанавливает последнюю задачу страницы после reload. Закрытие окна не отменяет provider call, о чём сообщается до запуска.

У фонового worker общий AI deadline 600 секунд, одна передача до 180 секунд с разделением времени между профилями; интерактивные AI-вызовы сохраняют прежние лимиты. Зависшая задача получает failed через 660 секунд, completed/failed данные автоматически удаляются при следующем обращении спустя 24 часа. Произвольный AI-код не исполняется. Автозапуск поддержан для PHP CLI/cli-server при доступном exec (проверено локально); FPM/shared hosting требует отдельной интеграции очереди и не заявлен поддержанным. При недоступном launcher задача безопасно завершается ошибкой. Прежний синхронный API сохранён для совместимости, редактор использует новые jobs.

Live PASS: реальный pottery Skill → фоновая генерация через Резервный/nvidia/nemotron-3.5-super-vl:free завершилась за 89 секунд; 3 секции, все 5 автоматических правил прошли. Во время работы окно закрыто, редактор перезагружен, задача восстановила исходный prompt, выбранный Skill и статус, основной сервер продолжал отвечать. Просмотрены hero, карточки и booking в мобильном sandbox; отмечены три пункта ручного оформления. Результат импортирован в изолированную QA-страницу 4f5f7cb20ec6518534ee492d и сохранён revision39; после reload сохранились три секции, заголовки, текст и CSS. В редакторе просмотрены Desktop и Mobile375px. Скриншоты: /tmp/juzimder-jobs-desktop-042.png и /tmp/juzimder-jobs-mobile-042.png. Использование: 2861 prompt +2966 completion =5827 tokens, reported cost $0. Публикация не выполнялась.

Ограничения QA: изображение не выбрано, остаётся слот; модель придумала mailto:hello@topyraq.studio — это синтетический контакт, не подтверждённый владельцем, перед публикацией требуется заменить. Дизайн базовый; общая визуальная оценка и достоверность всех коммерческих утверждений не объявлены автоматически пройденными. Typography после импорта следует и глобальным токенам проекта. Полная desktop1440/tablet768 public-приёмка — NOT RUN. Это закрывает технический сценарий реальной генерации/Skill gates/import/save/reload, но не означает безошибочное следование произвольному Skill.

Проверки PASS: npm test, build, PHP/JS syntax и diff checks. Дополнительные тесты покрывают SQL migration9, owner/CSRF/stale version/page identity, единственную активную задачу, однократный claim, сохранение и восстановление результата, отсутствие записи Project Data, безопасную ошибку, expiry и реальный detached CLI worker без сетевого вызова. Известное предупреждение GrapesJS chunk >500KB остаётся.


## Отмена фоновой генерации · 00.00.043

Во время ожидания доступна «Отменить генерацию». POST cancel защищён owner/CSRF и не принимает дополнительные данные. Queued задача сразу становится cancelled и не может быть захвачена worker-ом. Running задача переходит в cancelling: слот проекта остаётся занят до подтверждения worker, чтобы новая генерация не создавала параллельный расход. Worker проверяет флаг через cURL progress callback (DB check не чаще двух раз в секунду), прерывает текущий transfer и завершает cancelled. Отмена не запускает retry/fallback; пришедший одновременно результат отбрасывается, если cancellation успела первой. Завершённую succeeded/failed задачу отменить нельзя (409). SQL migration10 сохраняет предыдущие jobs и расширяет CHECK/unique active index. При аварии worker прежний watchdog освобождает зависшую задачу.

Отмена локально прерывает запрос; провайдер может учесть уже потраченные токены и не гарантирует остановку своей внутренней обработки. Это прямо сообщается в UI. Отменённая задача восстанавливается после reload с сообщением об отмене, без preview/apply. Проект не меняется.

Live PASS: на изолированной QA-странице запущена реальная синтетическая Cancellation QA задача, нажата отмена, worker подтвердил cancelled, кнопка нового запуска снова доступна. После reload и восстановления сохранились описание и отменённый статус; apply отсутствует. Сохранённая страница осталась revision39. Снимок: /tmp/juzimder-cancellation-043.png. Новая полноценная генерация после отмены в браузере не запускалась; освобождение слота и новый запуск покрыты regression fixtures.

Проверки PASS: npm test, build, PHP/JS syntax, git diff --check. Fixtures проверяют queued cancellation без AI-вызова, running cancellation и блокировку параллельного запуска, гонку с успешным результатом, отсутствие retries/fallback, реальное прерывание медленного HTTP transfer, CSRF и ownership. Известное предупреждение размера GrapesJS chunk сохраняется.


## История AI-генераций · 00.00.044

В диалоге AI-дизайна доступна «История генераций»: до 20 последних задач выбранной страницы за срок хранения 24 часа, со статусом, временем, кратким исходным описанием, выбранным Skill и базовой ревизией. Summary API защищён owner и принадлежностью pageId, не возвращает полный Skill/result; полный результат загружается по выбранному job ID отдельным owner-scoped GET. Тексты выводятся через textContent, без вставки пользовательского HTML. Открытие истории и результата не создаёт AI-запрос и не меняет Project Data.

Успешный старый результат можно осмотреть даже после изменения проекта; при несовпадении baseVersion/current.version preview только для чтения: загрузка/назначение изображений скрыты, checklist и apply отключены. Apply handler повторно проверяет версию; обычный save сохраняет прежний server CAS. На неизменившейся версии остаются все прежние ручные gates и отдельное подтверждение. Отменённая/ошибочная задача показывает своё безопасное сообщение, без дизайна.

Live PASS: реальная история QA-страницы показала отменённую задачу revision39 и предшествующий успешный pottery результат revision38. Успешный результат открыт без генерации, с прежними пятью прошедшими правилами, метаданными модели/профиля и сообщением «только просмотр: 38 → 39». В финальной сборке upload chooser отсутствует, три чекбокса и apply disabled. Проект остался revision39. Снимок истории: /tmp/juzimder-generation-history-044.png. Новый реальный provider call и изменение страницы в этом релизе не выполнялись.

npm test, npm run build, PHP/JS syntax и diff checks — PASS. Regression tests покрывают порядок после более поздней отмены/ошибки, доступность прежнего успеха, лимит20, отсутствие полных payload/result в summary, owner/page isolation и отсутствие записи Project Data. Сохраняется Vite warning о большом GrapesJS chunk. Следующий содержательный AI-пункт — не допускать выдуманные контакты в дизайне; текущий synthetic QA email ещё требует замены перед публикацией.


## Проверка контактов полной AI-генерации · 00.00.045

После HTML/CSS sanitizer сервер проверяет внешние href, email в DOM-тексте, HTTP(S) URLs в DOM-тексте и международные телефоны с + в DOM-тексте. Email/URL/номер должны присутствовать в исходном owner prompt; контакты из примеров Skill не являются источником разрешения. Mailto сравнивается по адресу, tel — по цифрам; внешние URL сравниваются целиком, без совпадения по префиксу/поддомену. Блочные границы текста разделяются, inline-фрагменты и HTML entities декодируются DOM parser. Незаданные контакты вызывают contact_requirements, одну исправляющую попытку и fail closed при повторном нарушении. Модель заранее получает требование использовать внутренние fragment links либо обычные кнопки, если контакты не заданы. Raw contacts/response не попадают в сообщения ошибок.

Успешный дизайн содержит additive contactAudit (policy brief-contacts-v1), не изменяя schemaVersion Project Data. UI показывает область проверки. Исторические результаты без contactAudit остаются только для просмотра, даже если версия проекта совпадает; apply guard и checklist gates это учитывают. Старые сохранённые страницы автоматически не переписываются.

Границы: наличие контакта в prompt не доказывает его достоверность или уместность, в том числе при отрицательных/неоднозначных инструкциях. Почтовые адреса, local-format телефоны в обычном тексте, bare domains/обфускации и остальные бизнес-факты требуют ручной проверки. Сервер не подтверждает реальность организации и не проверяет весь произвольный текст Skill. Уже сохранённый QA mailto требует ручной замены до публикации.

npm test, npm run build, PHP/JS syntax и diff checks — PASS. Fixtures покрывают разрешённые явные контакты, внутренние ссылки, неизвестные email/tel/URL, текст и HTML entities, точное URL-сравнение, одну исправляющую попытку и повторный отказ. Live: новая Contact-safety QA задача завершилась invalid_response (модель не вернула структурированный дизайн), без изменения страницы. Успешная новая генерация с contactAudit — NOT RUN. Новый audit отдельно применён read-only к сохранённому реальному pottery результату: придуманный mailto отклонён с contact_requirements. В браузере старый результат показывает предупреждение об отсутствии проверки контактов, disabled checklist/apply и скрытый upload chooser. Проект остаётся revision39; скриншот /tmp/juzimder-contacts-045.png. Существующий сохранённый QA email автоматически не удалялся.


## Безопасная диагностика AI-задач · 00.00.046

SQL migration11 добавляет nullable diagnostics, сохраняя старые задачи. Worker атомарно с terminal status сохраняет фиксированный outcome и до20 обращений к профилям: название, модель, elapsedMs и безопасный код результата. Ответ провайдера отличён от финального server acceptance: response_received не означает успешный HTML/CSS/Skill/contact audit. Внутренние transport retries и fallback-модель одного профиля объединены в одну запись, что объяснено в UI. Неизвестная category сводится к provider; request text, ключи, headers и сырые provider bodies в diagnostics не сохраняются. Прежний owner-scoped GET возвращает отчёт; срок хранения соответствует задаче24ч. Очередная отмена, expiry и launch failure также имеют фиксированные outcomes; исторические задачи без diagnostics честно показывают отсутствие отчёта.

В диалоге при открытии задачи из истории/восстановлении показаны итог, обращения к профилям и длительность. Завершение неуспешной задачи сохраняет отчёт рядом с ошибкой, успешной — рядом с preview. Отчёт закрывается вместе с окном, чтобы не показывать его рядом с новой пустой формой. Эта функция не меняет правила генерации, ключи, active profile или Project Data.

Live PASS: запущена синтетическая Diagnostics cancellation QA задача, затем отменена. Отчёт показал cancelled, Резервный, nvidia/nemotron-3.5-super-vl:free,14.0с. После reload/«Восстановить последнюю задачу» те же данные восстановились. Проект остался revision39. Screenshot: /tmp/juzimder-job-diagnostics-046.png. Новый успешный contact-audited дизайн в этом релизе не проверялся; прежняя invalid_response задача до migration11 не получает выдуманную задним числом цепочку профилей.

npm test, npm run build, PHP/JS syntax и diff checks — PASS. Fixtures проверяют failover trace (ошибка первого профиля → ответ второго), отсутствие API keys/request text/raw response в diagnostics, нормализацию неизвестной category, безопасный error outcome даже при отказе до создания provider и persistence/reopen. Сохраняется предупреждение Vite о размере GrapesJS chunk. Следующий AI acceptance-пункт — успешный реальный дизайн с contactAudit, затем проверка качества и фактов перед публикацией.


## Некорректный ответ и переключение профиля · 00.00.047

Live QA выявил: активный Nemotron через 121с вернул invalid_response, задача остановилась без второго профиля. ProfileFailoverProvider теперь продолжает ограниченную цепочку сохранённых профилей также при invalid_response (повреждённый transport JSON, отсутствие/пустое structured content). Каждый профиль посещается не более одного раза в цепочке; общий deadline сохранён. Cancellation, HTTP400 и остальные non-transient ошибки не переключают профиль. Это не обход HTML/CSS, Skill или contact gates и не retry для невалидного дизайна после parsing.

Повторная реальная QA генерация с пользовательским pottery Skill завершилась успешно за20.3с на первом профиле Резервный, nvidia/nemotron-3.5-super-vl:free:3 секции,5/5 declared rules, contactAudit passed,3003 tokens,$0. Переключение при invalid_response подтверждено fixtures, но не потребовалось в успешном live запуске. Preview доступен через восстановление последней задачи QA-страницы. Screenshot /tmp/juzimder-contact-acceptance-047.png. Импорт не выполнялся, Project Data остался revision39. Старый сохранённый QA mailto не исправлен автоматически. Скриншот узкого preview просмотрен: пустой image slot и языковые ошибки требуют ручной доработки; полная desktop/tablet/mobile visual acceptance NOT RUN. Серверный acceptance не равен публикационной готовности.

npm test PASS (локальные HTTP integration servers запускались вне sandbox), npm run build PASS, PHP syntax PASS. Сохраняется предупреждение Vite о размере GrapesJS chunk. Следующий шаг: улучшить качество реальной композиции и текста, провести полную адаптивную приёмку, затем отдельно импортировать и проверить persistence/Undo.


## Адаптивный просмотр всех AI-профилей · 00.00.048

Desktop1440 / Tablet768 / Mobile390 теперь доступны в preview для стандартного и пользовательского Skill, а не только Taste. Кнопки размещены перед iframe; «Расширить окно» увеличивает диалог до доступной ширины, не масштабируя и не меняя CSS дизайна. Большой viewport горизонтально прокручивается; iframe сохраняет sandbox. Закрытие сбрасывает расширение. Ручные Taste/Skill gates и backend CAS не изменены.

Live: восстановлен реальный успешный результат .047 без нового AI-вызова; проверены расширение и переключение трёх ширин, видна версия .048. Desktop/mobile screenshot: /tmp/juzimder-responsive-desktop-048.png и /tmp/juzimder-responsive-mobile-048.png. Обнаружен большой пустой image slot на первом экране и ошибки русского текста. Полная visual acceptance результата не пройдена, импорт/persistence/Undo не выполнялись; Project Data revision39. Следующий шаг — поведение незаполненного слота и качество текста, затем полная визуальная приёмка.

npm test PASS, build PASS, JS syntax PASS, diff check PASS; backend не менялся. Известный Vite warning GrapesJS chunk сохраняется. После полной тестовой серии уточнён selector aria-pressed, чтобы выбор viewport не сбрасывал состояние кнопки расширения; выполнены повторная сборка и syntax check.


## Незаполненные изображения · 00.00.049

Если изображение не назначено, preview и buildAIDesignHtml удаляют сам slot node вместо пустого цветного блока. Исходный результат задачи не меняется: выбор asset при повторном рендере восстанавливает слот из design.html. Выбранные assets продолжают вставляться обычными img. UI явно обозначает «Без изображения · убрать блок». Родительские fixed/grid размеры произвольного дизайна автоматически не переписываются; возможные пустые родительские колонки требуют визуального контроля.

Live: восстановлен .047 результат, Mobile390, расширенное окно. Прежний большой прямоугольник исчез; заголовок, описание и CTA видны на первом экране. Screenshot /tmp/juzimder-empty-slot-049.png. Project Data revision39 не менялся, импорт/persistence/Undo и назначение нового изображения в этом релизе NOT RUN. Текстовые ошибки и синий default link остались: полная visual acceptance не пройдена. Следующий шаг — качество текста/CTA, затем импорт на QA и persistence/Undo.

npm test, npm run build, JS syntax, PHP syntax backend/Ai.php и diff check PASS. Backend не менялся; предупреждение большого GrapesJS chunk сохраняется.


## Базовый цвет ссылок и вычитка · 00.00.050

Sanitized дизайн теперь получает scoped baseline `.scope a{color:inherit}` перед CSS модели. Это убирает browser-blue при пропущенном color; generated rules сохраняют приоритет по cascade, подчёркивания не удаляются. Старые сохранённые designs/jobs автоматически не переписываются. Prompt всех профилей требует язык owner brief, вычитку грамматики и CTA без обещаний работающей формы/обратного звонка при inert button. Это инструкция модели, а не автоматический grammar/fact audit.

Live: новая реальная pottery QA генерация с пользовательским Skill, одна исправляющая попытка, обе на Резервный/Nemotron (39.3+38.8с),6302 tokens,$0. Итог3 секции,5/5 declared rules,contact audit passed. Mobile390 screenshot /tmp/juzimder-cta-copy-050.png: CTA «Посмотреть форматы» без browser-blue, hero без пустого slot. Контраст текста CTA слабый; текст придумал четыре занятия и обжиг за одну встречу, чего нет в brief. Полная visual/fact acceptance FAIL, импорт не выполнялся,revision39. Нельзя выдавать prompt вычитки за гарантию корректного текста. Следующий пункт — контраст и проверка бизнес-фактов перед импортом; persistence/Undo всё ещё требуют live acceptance.

npm test PASS, build PASS, PHP syntax PASS, diff check PASS. Regression проверяет scoped baseline перед модельным CSS. Известный большой GrapesJS chunk warning сохраняется.


## Общая ручная приёмка AI-дизайна · 00.00.051

Все профили требуют отдельные подтверждения фактов (услуги, цены, сроки, числа, обещания) и читаемости/контраста перед импортом. Общий predicate hasAcceptedDesignReview используется при обновлении кнопки и повторно в apply handler. Taste дополнительно требует прежние concept/content и три visited+reviewed viewport; user_skill — все ручные пункты Skill. Новый/восстановленный результат начинает с false. Owner/auth/CSRF/CAS серверного save не менялись. Это UI acceptance, не автоматический fact/contrast audit и не защита от владельца, обходящего интерфейс.

Live: восстановлен результат .050, версия .051 видна. Все три Skill checkbox временно отмечены для теста: apply остался disabled без общих facts/contrast. Тестовые отметки сброшены. Подтверждения фактов/контраста не отмечались, поскольку известны выдуманные сведения и слабый CTA contrast. Project Data остаётся revision39. /tmp/juzimder-review-gate-051.png. Новый provider call, исправление дизайна, apply/persistence/Undo NOT RUN. Пункт автоматического выявления/исправления контраста и бизнес-фактов остаётся открытым, равно как успешная полная visual acceptance.

npm test PASS (включая новые pure acceptance tests), npm run build PASS, JS syntax/PHP syntax/diff checks PASS. Предупреждение большого GrapesJS chunk сохраняется.


## Проверка альтернативных моделей и HTTP429 · 00.00.052 · 23.10.2026

По публичному каталогу OpenRouter от23.10 выбраны бесплатные модели с advertised structured_outputs. Server-only синтетический полный AiGeneratedWebsite::create (тот же sanitizer/contact gates) выполнен без изменения сохранённых профилей/ключей: xingchen-agi/xing4.0-29b-a4b:free — HTTP429 за2.1с; dots-studio/dots-3-note-preview:free — timeout90с, HTTP200, firstByte1195ms,330 полученных bytes без полного ответа. Это не successful design и не доказательство внутренней причины задержки. Секреты использовались только через vault в памяти и не выводились. Нового accepted результата нет; импорт/persistence/Undo остаются NOT RUN. QA страница Acceptance QA —23.10.2026 id6523637f-cdc0-46e3-a8c7-10c168a4ee3f создана ранее,revision40; не опубликована.

HTTP429 теперь имеет отдельную safe category rate_limited и сообщение «Провайдер ограничил запросы»; прежний bounded retry/failover сохраняется. Диагностика UI отличает rate limit от network/unavailable и timeout. Не определяется, относится ли ограничение к ключу или инфраструктуре бесплатного endpoint; raw provider body не выводится. Старые jobs не переинтерпретируются. Regression покрывает retryable category/public code и успешное переключение после429. Новый browser terminal report rate_limited NOT RUN; реальный429 получен до исправления, классификация проверена fixtures.

npm test/build/PHP syntax/JS syntax/diff checks PASS. Предупреждение GrapesJS chunk сохраняется. Цель найти стабильную модель и пройти import/persistence/Undo остаётся открытой; повторно увеличивать timeout без доказательств не следует.


## Ошибки OpenRouter внутри HTTP200 · 00.00.053 · 23.10.2026

По официальному OpenRouter errors-and-debugging HTTP200 может содержать top-level error или choices[0].error после начала обработки, в том числе с частичным content. Раньше такие ответы сводились к invalid_response либо могли пройти как content. Теперь до content обрабатывается безопасный числовой error.code:429 rate_limited,408 timeout,401/403 credentials,5xx unavailable,остальные provider. Raw message/metadata не выводятся. Retryable embedded ошибки передаются existing bounded profile failover; внутримодельный network retry для embedded ошибки не добавлен. finish_reason error/length/content_filter отклоняет даже синтаксически валидный partial content. Неизвестный error object fail closed. Канонический Project Data и API keys не менялись.

Regression PASS: top/choice embedded errors, partial valid content, fixed safe messages, transient embedded502 -> второй профиль; неполные finish reasons не принимаются. npm test/build/PHP syntax/diff checks PASS, GrapesJS chunk warning прежний. Live новая accepted генерация после исправления NOT RUN; неизвестно, были ли прежние invalid_response вызваны embedded errors. Исторические jobs не переклассифицируются. Рабочая модель и import/persistence/Undo остаются незакрытыми. Source https://github.com/OpenRouterTeam/docs/blob/main/api_reference/errors-and-debugging.mdx .


## Реальная приёмка генерации/import/Undo · 23.10.2026 · 00.00.053

На отдельной странице Acceptance QA —23.10.2026 (6523637f-cdc0-46e3-a8c7-10c168a4ee3f) в Codex Browser выполнен новый standard запрос с точным синтетическим текстом и CTA #593520/#FFFFFF. Резервный/Nemotron ответил за11.5с,2434tokens,$0;3 секции,contactAudit passed. Никаких выводов о стабильности модели по одному успеху; прежние timeout остаются реальными.

Preview осмотрен на1440/768/390px: текст соответствует QA brief, CTA белый на коричневом, пустые slots отсутствуют, секции читаются и карточки складываются. Desktop-bottom и mobile-bottom просмотрены отдельно; tablet переключён и осмотрен первый экран, нижние секции без отдельного screenshot. Это простая QA-композиция без фото, не доказательство высокого художественного качества. После ручных facts/contrast checks применён импорт ->rev41. Undo одним действием вернул пустую QA-страницу, Redo восстановил дизайн; save rev43. Выход к страницам и повторное открытие подтвердили persistence текста/секций/CTA. Нативный quick-edit изменил paragraph с временной QA-припиской, Undo её убрал, save rev45. Результат оставлен в QA, публичный snapshot не обновлялся.

Скриншоты /tmp/juzimder-acceptance-desktop-053.png, /tmp/juzimder-acceptance-desktop-bottom-053.png, /tmp/juzimder-acceptance-tablet-053.png, /tmp/juzimder-acceptance-mobile-053.png, /tmp/juzimder-acceptance-mobile-bottom-053.png, /tmp/juzimder-imported-mobile-053.png. Известное расхождение: sandbox использует serif browser default, импортированный editor наследует sans theme при отсутствии явного font-family. Pixel fidelity не принята; следующий fix — согласовать типографику preview/editor без изменения Project Data contract. В этом проходе runtime code не менялся, версия остаётся .053; npm test/build не повторялись без code changes. Сохранение/редактируемость/Undo live PASS; полная визуальная идентичность NOT ACCEPTED.


## Наследуемая типографика AI-preview · 00.00.054 · 23.10.2026

Источник расхождения .053: sandbox body по умолчанию serif, editor wrapper наследует текущую типографику страницы. updateAIDesignFrame теперь читает computed font-family/font-size/font-weight/font-style/line-height/letter-spacing/color выбранного GrapesJS wrapper и передаёт allowlisted значения через CSSStyleDeclaration в body sandbox до design.css. Символ < экранируется в CSS; arbitrary styles не копируются. Явные стили модели сохраняют приоритет. Не передаются данные в OpenRouter и не меняются Project Data/сохранённый job. CSP/sandbox прежние, внешние fonts по-прежнему запрещены в AI-preview. При недоступном rootElement применяется прежний browser fallback.

Live: в .054 восстановлен тот же .053 job без AI-запроса, Mobile390. Serif исчез, шрифт соответствует sans editor; screenshot /tmp/juzimder-typography-preview-054.png просмотрен. Старый job read-only по base40/current45, apply disabled; ревизия45 неизменна. Полное pixel-level сравнение разных тем, внешние fonts и повторный import в этом релизе NOT RUN; это исправление наследуемой типографики, не гарантия полной идентичности всех стилей/ресурсов. npm test/build/JS syntax/PHP syntax/diff checks PASS, GrapesJS chunk warning прежний.


## Одинаковая мобильная ширина · 00.00.055 · 23.10.2026

AI-preview Mobile теперь375px, как native editor Mobile. Taste disclosure и ручной viewport label согласованы. Раньше390vs375 могли давать разные переносы без CSS-дефекта. Desktop1440 и Tablet768 остаются прежними; editor Desktop адаптивный, не фиксированный1440.

Live .055: сохранённая QA revision45, editor Mobile375 и восстановленный job Mobile375 просмотрены, screenshots /tmp/juzimder-editor-375-055.png и /tmp/juzimder-preview-375-055.png. Body font совпадает, перенос заголовка соответствует. Выявлено оставшееся различие heading weight: native theme задаёт500, sandbox default bold. Полная visual identity не принята; следующий пункт — heading theme defaults, затем image/theme acceptance. Assets в этом проходе NOT RUN; новый AI/import/save не выполнялись,revision45 не менялась. npm test/build/JS syntax/PHP syntax/diff checks PASS, GrapesJS chunk warning прежний.


## Самостоятельный выбор визуалов карточек · 00.00.056 · 23.10.2026

Системные инструкции всех профилей теперь предлагают без owner-подсказки выбирать уместный визуал: встроенную символическую иконку для смысловых преимуществ/процесса либо существующий managed image slot внутри карточки, где важен внешний вид предмета. Нельзя украшать всё без смысла или рассчитывать на обязательное наличие фото. Фотослоты сохраняют прежние id/count limits; реальные фото выбираются владельцем из приватной библиотеки. Автозагрузка/поиск по стокам не реализованы.

Встроенный каталог8 декоративных символов spark/check/clock/heart/target/arrow/list/diamond. AI отдаёт пустой span.ai-icon.ai-icon-NAME. Сервер allowlist проверяет span/пустоту/один известный key/лимит12, вставляет glyph и aria-hidden=true, убирает role/aria-label. Это нативный редактируемый text span без SVG/внешних скриптов, шрифтов и API. CSS baseline задаёт размер32px перед стилями модели. SchemaVersion и JSON fields не менялись; safe HTML marker convention расширен явно здесь. OS glyph appearance может отличаться; это небольшой символьный каталог, не полноценный набор SVG pictograms.

Live: стандартный синтетический brief с карточками Забота/Практика/Свой ритм, без слов фото/иконка/визуал. Nemotron за89.2с вернул accepted3sections,3203tokens,$0. Read-only query результата подтвердил3 icon spans и4 image slots; мобильный screenshot /tmp/juzimder-auto-card-icons-056.png просмотрен: сердце в карточке Забота. Иконки скрыты от AX как decorative. Результат не импортировался, текущая сохранённая QA revision45 не менялась. Live icon import/persistence/Undo NOT RUN; нативные span допускаются прежним canonical contract. Полная визуальная приёмка нового дизайна/выбор и загрузка фото NOT RUN. Прежний пункт heading weight parity остаётся открытым.

npm test/build/PHP syntax/diff checks PASS; fixtures проверяют icon rendering, system instruction и отказ unknown/nonempty/wrong-tag/>12 markers. После удаления дублирующей baseline anchor строки повторены php tests/ai.php и syntax. Предупреждение GrapesJS chunk прежнее. Следующие пункты: приёмка импорта иконок и image selection, согласование heading defaults, отдельно stock-photo integration.


## Пустые обёртки фотослотов · 00.00.057 · 23.10.2026

При live приёмке .056 перед импортом выявлены большие пустые ai-format-image/ai-hero-slot div вокруг удалённых незаполненных slots. Новая shared removeUnassignedImageSlot используется в updateAIDesignFrame и buildAIDesignHtml. Поднимается только по div с единственным element child и пустым textContent; останавливается на id/role/aria-label, semantic element или любых соседях/тексте. Удаляет только image-only цепочку, не карточку с текстом. Не правит произвольные fixed/grid размеры оставшихся родителей. Исходный job HTML остаётся неизменным, назначение image снова строит исходную структуру. Результат с выбранным asset прежний, ветка удаления не вызывается.

Live: восстановлен .056 job, просмотрены Desktop1440/Tablet768/Mobile375 до исправления, обнаружены пустые фотообласти. В .057 Mobile375 «Выбор формата» теперь сразу показывает заголовок, copy и кнопку без пустого прямоугольника; screenshot /tmp/juzimder-empty-wrappers-057.png просмотрен. Иконки .056 до исправления видны в desktop screenshot /tmp/juzimder-icons-desktop-056.png. Импорт не выполнялся, revision45 неизменна. Иконки import/persistence/Undo и фото selection остаются NOT RUN; не объявлять их закрытыми. Новый provider call не выполнялся. npm test/build/JS syntax/PHP syntax/diff checks PASS; GrapesJS chunk warning прежний. Следующий шаг — завершить приёмку icon import, затем heading parity и реальные фото.


## Live import/persistence иконок · 23.10.2026 · 00.00.057

В Codex Browser восстановлен .056 job base45 на QA странице6523637f-cdc0-46e3-a8c7-10c168a4ee3f. После прежней визуальной приёмки и исправления пустых обёрток .057 вручную подтверждены facts/contrast и выполнен append3sections ->rev46. Старый QA sample сохранён; страница теперь содержит два образца (6sections), это не готовая единая публичная страница. Фото не назначались. Все3иконки видны native editor: /tmp/juzimder-icons-imported-057.png.

Undo потребовал2нажатия до удаления нового блока; первое не изменило видимые тексты. Точная причина первого шага не установлена, предположение о CSS grouping не подтверждено. Два Redo восстановили результат, save + выход к списку страниц далиrevision48. Повторное открытие сохранило все3иконки: /tmp/juzimder-icons-reopened-057.png просмотрен. Read-only canonical document query подтвердил3native text span components с classes ai-icon-heart/check/clock и aria-hidden=true. Прямая правка glyph через inspector в этом проходе NOT RUN; нативная структура присутствует, не заявлять edit test выполненным. Import/persistence/reopen PASS; Undo/Redo functional PASS за2шага, single-step undo NOT ACCEPTED. Публичный snapshot прежний rev0, публикация не обновлялась.

Runtime code не менялся, продукт остаётся .057; тесты/сборка без новых code changes не повторялись. Следующий пункт: исследовать и объединить undo импорта в один шаг, затем managed image selection/persistence и heading parity. Stock-photo auto-download всё ещё не реализован.


## Undo без истории выделения · 00.00.058 · 23.10.2026

Установлена причина дополнительного шага .057: bundled GrapesJS Core UndoManager config trackSelection=true по умолчанию и postLoad регистрирует selected collection. Перед Undo в QA выбиралась карточка, поэтому отдельным действием отменялось выделение. Предположение о раздельной группировке CSS/HTML не подтвердилось. Editor init теперь undoManager:{trackSelection:false}; мутации компонентов/стилей отслеживаются прежним Core, история выделения не создаётся. Core не менялся; собственный transaction/stack patch не нужен.

Live .058 на QA: quick-edit paragraph ->QA Undo058, выбран другой heading «Забота», один Undo вернул исходный paragraph при сохранённом выборе «Забота». Один Redo вернул QA Undo058. Ещё Undo + save убрали тестовую правку, итогrevision51. Все3иконки и предыдущие QA sample сохранены. Fresh AI import+singleUndo в .058 NOT RUN; проверен конкретный сценарий лишнего selection step, не объявлять весь возможный grouping доказанным. Публикация не менялась. npm test/build/JS syntax/PHP syntax/diff checks PASS; GrapesJS chunk warning прежний. Следующий шаг — реальные managed photos и heading parity; single fresh import acceptance при следующей генерации.


## Приоритет типографики AI и heading defaults · 00.00.059 · 23.10.2026

AI-preview получает h1–h6 defaults из normalized currentDesignTokens (family,size,weight,line-height), сериализованные CSSStyleDeclaration с escaping <, до design.css. Явные модельные правила в sandbox сохраняют приоритет. В applyDesignTokens обнаружено, что component.getStyle не учитывает class stylesheet: тема добавляла ID-style поверх явных ai-scope правил. Теперь для heading property проверяются совпадающие с element CSS rules editor.Css.getAll с .ai-scope- selector и данным property. При совпадении theme default не записывается, прежняя managed var(--juzimder-font/line-height) удаляется; пользовательское явное inline значение не удаляется. Решение применяется при загрузке/применении темы в редакторе; при отсутствии DOM element действует прежний fallback. Проверка учитывает совпавшие responsive rules вне зависимости от текущего media; произвольный полный CSS cascade не реализуется, требуется responsive QA.

Live .059 на сохранённой QA: native headings вернули bold модели вместо theme500; осмотрены /tmp/juzimder-heading-editor-059.png и /tmp/juzimder-heading-cards-059.png. Тот же job восстановлен read-only в preview Mobile375, /tmp/juzimder-heading-preview-059.png просмотрен. Общая визуальная идентичность всех свойств/тем не объявляется; исправлен observed weight override. Revision51 не менялась, loaded managed-style cleanup не сохранялся в этом проходе. Публичный snapshot/ZIP существующей сохранённой ревизии не обновлялись. Fresh generation/import, внешние fonts и фото NOT RUN. npm test/build/JS syntax/PHP syntax/diff checks PASS; suite повторён после второго изменения. GrapesJS chunk warning прежний. Следующий пункт — реальные managed photos в карточках: upload/assignment/import/persistence.


## Открытые сетевые фотографии · 00.00.060 · 23.10.2026

Прямое требование владельца: LLM должна брать уместные фото из открытых источников сети, без ручных upload и отдельного запроса фото. Это заменяет прежний manual-library-only default. Правило сохранено по явному «запомни» в memory ad-hoc note. Синтетический QA PNG .059 не загружен и не используется; отказ browser upload не обходился: новый источник — публичные Commons URLs по новой прямой инструкции пользователя.

Первый адаптер Wikimedia Commons (без API key). Система предлагает каждой photo slot тему data-image-query (2–5 public subject words,max100,без brand/private data/URLs), sanitizer убирает атрибут из HTML и возвращает bounded query в imageSlots metadata. Это additive convention, schemaVersion1 Project Data прежний. Новые результаты с query автоматически запускают owner-scoped GET /stock-photos?q=; старые без query имеют явный поиск в chooser. Непрошедшая проверка/устаревшая ревизия не запускает поиск. Можно заменить предложение или оставить без фото. Одна тема передаётся Commons, не весь brief/Project Data/API key; модель может ошибиться в теме, это требует review. Auto search — клиентский шаг после success job, не часть worker result. Close epoch guard предотвращает запись в закрытый dialog.

StockPhotos фиксирует единственный API origin commons.wikimedia.org, HTTPS/cert verification, no redirects,connect5s/total10s,body500KB,6metadata results,max4candidates. Owner auth до network,24searches/min/session. Только JPEG/PNG/WebP и CDN upload.wikimedia.org/thumb.wikimedia.org без userinfo/port. License allowlist CC0/Public domain/CC BY/CC BY-SA 2.0,2.5,3.0,4.0 + nonempty author; unsupported/NC/unknown/SVG пропускаются. Metadata HTML превращается в bounded plain text. Это фильтр metadata источника, не гарантия прав на все изображённые объекты или отсутствия иных ограничений; владелец проверяет source/license. API source https://www.mediawiki.org/wiki/API:Imageinfo , metadata https://commons.wikimedia.org/wiki/Help:Machine-readable_data .

Preview вставляет реальный внешний img и подпись со ссылкой на автора/file page и лицензию. При импорте тот же DOM helper сохраняет img+credit в канонические компоненты; внешний файл не скачивается в приватную asset library. Export/publication прежним contract сохраняют remote URL и требуют сеть; offline bytes не обещаются. Source selection в job history не сохраняется: до import повторный поиск может дать другие кандидаты. Unsplash/Pexels adapters не реализованы; Unsplash требует API key/hotlink/download event (официальные docs), потому не использован как random URL service. Keys, если появятся, только encrypted settings, не env.

Проверки: live серверный Commons поиск pottery bowl вернул4 candidates; browser восстановил успешный старый photo-QA job base52/current52, пользовательское поле темы заполнено QA вручную для проверки поиска (не full auto acceptance). Фото Yangshao Painted Pottery Bowl, Gary Todd, CC0 реально видно в карточке с source/license links: /tmp/juzimder-commons-photo-060.png просмотрен. Последующая новая LLM генерация без запроса фото failed: Резервный unavailable122.3с,Основной invalid_response101.2с. Поэтому full automatic LLM query->photo live NOT ACCEPTED/NOT RUN после успеха, покрыта parsing/query fixture и реализована цепочка. Import/persistence/Undo stock photo NOT RUN, ревизия52 неизменна; подпись старого QA brief «синтетическая иллюстрация» требует изменения перед применением реального фото. Видимое photo/card sizing требует дальнейшей responsive приёмки; не заявлять publication-ready.

npm test/build/JS+PHP syntax/diff checks PASS. Fixtures: strict origins, raster MIME, license/author sanitization, query extraction, anonymous401/foreign404/empty422; network в tests не вызывается. Live API probe отдельный. Известный GrapesJS chunk warning прежний. Следующее: успешный auto-query live на рабочей модели, stock photo import с credit/save/reopen, responsive image sizing.

## Постоянный локальный сервер · 00.00.061

Причина недоступности: временный PHP-процесс завершался вместе с сессией. `npm run serve:install` устанавливает пользовательский macOS LaunchAgent local.juzimder-ai: RunAtLoad, KeepAlive, loopback 127.0.0.1:8080. Требуются PHP и Python3. Логи только в ignored var/. Адрес http://juzimder-ai.localhost:8080/. Сервис работает после входа пользователя, не во время сна/выключения компьютера. Удаление: launchctl bootout gui/$(id -u)/local.juzimder-ai и удаление ~/Library/LaunchAgents/local.juzimder-ai.plist.

Проверки .061: npm test и npm run build PASS; PHP syntax и shell syntax PASS. LaunchAgent state=running; HTTP 200 до и после launchctl kickstart -k. Browser visual NOT RUN: ранее доступ инструмента заблокирован URL policy. Первоначальный sandbox test не смог открыть loopback; повтор с разрешённым loopback прошёл.

## Поиск длинных тем фотографий · 00.00.062

Live в открытой QA: успешный bakery job содержит четыре AI image queries; длинные темы не находили Commons photos. После пустого точного поиска выполняется один дополнительный запрос по отдельным словам (до8 уникальных слов, только буквы/цифры, OR quoted terms). Не повторяем одиночную тему; transport/API errors не маскируем fallback. Максимум два Commons запроса по10с; origin/licence/raster/author filters прежние. Fallback candidates отмечены searchBroadened: клиент добавляет их в список, но не назначает автоматически и просит выбрать вручную. Это необходимо: live broadened search вернул хлеб/багеты, но для rye bread также колос ржи, поэтому релевантность не гарантирована.

Live backend fallback в старой открытой frontend-сессии подтвердил реальные Commons candidates с credit links; новая защита ручного выбора добавлена после наблюдения неверного rye candidate. Новая frontend-защита browser NOT RUN: текущий пользовательский незавершённый preview не перезагружали. Макет не применялся: цены/обещания не подтверждены кратким исходным prompt. Stock import/save/reopen/Undo остаются открыты. Следующий шаг — новая QA генерация с достоверным демонстрационным текстом и проверка полного потока фото.

Проверки .062: npm test/build, node --check, PHP syntax, git diff --check PASS. Скрин списка найденных хлеба/багетов с author/licence: /tmp/juzimder-photo-search-062.png, просмотрен. Он отражает прежнюю открытую frontend-сессию до reload, не доказательство новой ручной fallback-защиты.

## Автоматический подбор и простой импорт · 00.00.063

По прямому требованию владельца удалены AI image chooser/upload из окна генерации и все обязательные acceptance checkboxes (Taste, user Skill, facts/contrast). Предпросмотр, размерные переключатели и отдельная кнопка применения сохранены. Удалён устаревший hasAcceptedDesignReview и его тесты; backend HTML/CSS/Skill/contact validation, owner/auth/CSRF/CAS и read-only для устаревшей ревизии не ослаблены. Проза Skill без machine rules больше не требует ручного чек-листа и не считается автоматически доказанной.

AiGenerationJobs после создания дизайна выполняет AiPhotoSelection: до8 тем, поиск Commons с прежними raster/licence/origin filters, один пакетный LLM выбор из server-owned candidate IDs. Каталожные названия объявлены untrusted data; выбор ограничен IDs конкретного слота, модель может отказаться. Подбор по названиям, не vision по пикселям; релевантность не гарантируется. Фото/author/licence/assignments сохраняются в приватном job result, повторное открытие использует тот же выбор. Ошибка поиска/модели не ломает дизайн, слот убирается без случайной подстановки. Дополнительный запрос использует тот же provider budget и cancellation transport; usage фото отдельно в metadata.photoSelectionUsage, основной UI usage пока относится к генерации дизайна. Legacy jobs без photoSelection сохраняют прежний автоматический точный поиск, broadened candidates автоматически не вставляются.

Приёмка .063: npm test (повтор после final selector change), npm build, JS/PHP syntax и diff checks PASS. Browser в отдельной QA-вкладке: chooser отсутствует, acceptance checkboxes отсутствуют, apply доступна после подготовки без галочек; скрин /tmp/juzimder-simple-ai-063.png просмотрен. Текущий пользовательский preview не перезагружен: auto-review отклонил reload из-за возможной потери незавершённого состояния; использована предложенная безопасная отдельная вкладка. Реальная новая генерация Standard отказала на провайдерах: Резервный network/unavailable1.1с, Основной provider rejection0.8с. Поэтому новый LLM photo-choice end-to-end NOT RUN после успешной генерации; matching IDs/reject-all/error/persisted credits проверены fixtures. Старый успешный job открыт read-only без изменения ревизии52; stock import/save/Undo остаются отдельным открытым пунктом.

## Приёмка автоподбора и его диагностика · 00.00.064

Реальная уже запущенная владельцем Taste генерация завершилась: job base52, photoSelection completed, 4 assignments/4photos; отдельный подбор725 prompt+113 completion=838tokens, reported cost0. Владелец применил дизайн, редактор показал сохранённую ревизию53 (агент apply не нажимал). Новая вкладка подтвердила повторную загрузку всех4 img (complete=true,naturalWidth960), author/licence links и native image traits Alt/Src. Агент изменил только Alt первого card image с QA suffix и сразу Undo: исходный Alt восстановился; последующее autosave создало ревизию54. Это проверка Undo собственного изменения изображения, не Undo полного AI import. Скрин /tmp/juzimder-photos-persisted-064.png просмотрен. Пользовательская основная вкладка не перезагружалась.

Восстановление job после сохранения проекта использует те же4 фото, остаётся read-only из-за base52/current54. Новый UI показывает отдельный расход photoSelectionUsage; live 838tokens/$0 видны на /tmp/juzimder-photo-usage-064.png. PhotoSelection различает no_matches и unavailable (ошибка catalogue/model), frontend сообщает отсутствие изображений без ручного выбора.

Выявлена ошибка релевантности: карточке круассана назначен фасад Le Croissant bakery. Commons теперь возвращает bounded plain-text ImageDescription вместе с title, LLM получает оба поля и правило отличать продукт от заведения, названного в его честь. Это улучшение не подтверждено новой live генерацией; сохранённую владельцем карточку не заменяли. Подбор остаётся text-metadata-based, не vision; общая уместность фото и responsive layout не объявляются PASS. Следующее — приёмка релевантности новых вариантов; Undo полного импорта ещё NOT RUN.

Проверки .064: npm test (после последних изменений), npm run build, PHP/JS syntax, git diff --check PASS. Fixtures различают отказ всех кандидатов и сетевой сбой, проверяют plain-text description, server-owned IDs/credits и отказ от случайного фото.

## Правила оформления по умолчанию · 00.00.065

Прямые требования владельца: photo credits/links не больше8px; photo radius согласован с карточками/кнопками; левый Hero задаёт левое выравнивание остальных секций; каждой содержательной секции, включая Hero, нужен pill badge. Общие инструкции AiGeneratedWebsite включают все4 правила для standard/Taste/uploaded Skills. GeneratedDesignDefaults применяется после HTML/CSS allowlists и до Skill audit: добавляет отсутствующий span.ai-pill-badge из заголовка (до40символов), добавляет scoped pill CSS, определяет desktop text-align Hero по H1/предкам и согласует секции/H1/H2/p при left/start. Center Hero не принуждает left. Навигация/подвал не считаются содержательными секциями.

Для каждого photo slot ищется border-radius ближайшего оформленного предка, иначе общий radius карточек, затем кнопок/других оформленных контейнеров, иначе0px. Тот же radius добавлен slot+img scoped CSS. При конфликте radius карточки приоритетнее кнопки. Сопоставление ограничено безопасными tag/class descendant selectors и desktop rules; индивидуальные responsive radius overrides требуют отдельной приёмки. Author paragraph и обе ссылки получают inline font-size8px в общем helper preview/import. Project Data envelope не меняется; существующие сохранённые страницы автоматически не переписываются.

Проверки: npm test/build, JS+PHP syntax, diff checks PASS. Browser контрольный пример обработан реальным GeneratedDesignDefaults: две секции left, один pill на секцию, img/card20px, credits8px; измерено computed styles, /tmp/juzimder-generator-defaults-065.png просмотрен. Это synthetic layout fixture, не успешная LLM генерация/не import acceptance; временный статический QA asset удалён, копия HTML в /tmp/juzimder-generator-defaults-065.html. Живая новая Standard генерация с простым brief без перечисления правил rejected invalid_markup после двух ответов13.0/36.8с; sanitizer не ослаблялся, ревизия54 не менялась. Full fresh-generation/import визуальная приёмка остаётся NOT RUN после успешного ответа модели.

## Точечное исправление разметки моделью · 00.00.066

Markup validator теперь прикрепляет к AiProviderFailure фиксированный validationRule: tag_allowlist, attribute_allowlist, section_count, fragment_target, class_tokens, icon_marker, photo_query или markup_structure. Код сохраняется при wrapping, передаётся в единственную исправляющую попытку с конкретной инструкцией и остаётся в приватной job diagnostics. Отклонённый HTML, значения атрибутов и raw provider responses не копируются в repair prompt/diagnostics. Фильтры и число попыток не ослаблены. Исторический точный rule прошлого отказа .065 восстановить нельзя: ранее он не сохранялся.

Убраны противоречивые backend Taste/user-Skill указания об owner asset choice/обязательных ручных checkbox reviews. metadata.manualReviewRequired=false согласован с интерфейсом .063. Машинные Skill audits прежние; произвольные prose requirements не объявляются автоматически доказанными.

Проверки .066: npm test/build, PHP syntax и diff checks PASS; дополнительно tests/ai-jobs.php после новой fixture PASS. Tests подтверждают конкретный tag_allowlist в repair, отсутствие отклонённого marker текста, ограничение двумя вызовами и сохранение безопасного rule в задаче. Новая живая Taste генерация на QA base55 остановилась до design validation: оба профиля unavailable1.6/4.2с. Скрин /tmp/juzimder-provider-check-066.png просмотрен. Проект не применялся/не менялся этой проверкой. Full fresh-generation/import приёмка правил оформления остаётся открыта; внешняя доступность моделей не доказана.

## Сквозная приёмка генерации и полного Undo · 00.00.066

Новые короткие probes обоих сохранённых профилей прошли (reported cost0), после чего реальная Taste QA генерация base55 завершилась с одной исправляющей попыткой и отдельным выбором фото: вызовы117.8/25.9/1.1с; основной расход6899tokens/$0, photo selection917tokens/$0. Два реальных Commons изображения соответствуют хлебу/багетам (Baguettes - stonesoup и ЖИТО), фасадов в новом результате нет. Новая генерация имеет4 секции и4 pill badges; computed align=left, photo/slot radius14px, credits8px. В desktop1440 и Mobile375 sandbox осмотрены карточки: /tmp/juzimder-real-desktop-066.png и /tmp/juzimder-real-mobile-066.png просмотрены. Общая художественная/publication-ready оценка и вся responsive матрица не объявляются.

На существующей QA-странице Photo card QA (9291eeeb-4cb7-4553-9e7a-a25f3cfd1496) до импорта4 секции/4images; после отдельного confirm8/6, save revision56. Один native Undo вернул4/4, DOM style tags больше не содержали новый ai-scope-ede27d2816de7645. Один Redo восстановил8/6; autosave Undo/Redo ревизии57/58. После reload revision58 новый scope сохранил4sections/4badges/2 загруженныхphotos и computed credit8px/radius14px. Native Mobile375: content viewport365px с полосой прокрутки, images301px, ни одна секция не шире365px. Screenshots /tmp/juzimder-saved-generation-066.png и /tmp/juzimder-saved-mobile-066.png просмотрены. Публикация не выполнялась, старые QA-секции сохранены; домашняя страница владельца не менялась. Native imported components доступны для выбора/редактирования.

Этот проход — QA и документация без изменения runtime кода, версия продукта остаётся00.00.066. Технический шаг fresh generation/photo selection/import/persistence/full-import Undo/Redo подтверждён для этого сценария. Следующий ближайший открытый пункт: реальная AI-правка выбранного компонента → proposal/confirm → save/reload. Полная Skill-semantic гарантия, все варианты фото и общий production acceptance остаются открыты.

## Реальная точечная AI-правка и ID · 00.00.067

Live QA выявила false rejection «Выбран другой элемент»: первый реальный proposal содержал одну replace_text операцию, но apply guard не пропускал его. componentIdentifier использовал GrapesJS getAttributes(), который синтезирует DOM/style IDs в зависимости от текущего CSS selector state (upstream Core implementation осмотрена). Новый persistedComponentId читает только model.get('attributes').id — тот ID, который backend проверяет в Project Data. Сохраняются model identity, page, path и version preconditions; renderer IDs не считаются stable identity. Helper fixtures проверяют volatile ID, explicit assignment и owner change. Успешный apply также обновляет AI target identity и quick text field; перед повторным запросом ID выбранного model сверяется заново.

После исправления реальный повторный proposal/confirm/save прошёл: один абзац на Photo card QA заменён с «Выберите подходящий сорт и оформите заказ в разделе меню.» на «Выберите любимый хлеб и посмотрите меню.». DOM сравнение нового AI scope подтвердило ровно эту замену текста, прежние section count и image URLs. Save revision59. Один Undo вернул исходный текст, Redo восстановил новый, autosave revision60. После reload новый текст и постоянный cmp-ID сохранились; инспектор показывает актуальный текст/ID. Скрин /tmp/juzimder-ai-edit-saved-067.png просмотрен. Главная страница владельца не менялась. Проверен текстовый сценарий, не вся style-edit/conflict матрица.

npm test (повтор после final runtime changes), npm run build, JS/PHP syntax и diff checks PASS. Ближайший следующий шаг — реальная AI-правка стиля выбранного компонента и приёмка stale-selection/CAS конфликтов. Project Data schema и backend validation не изменены.

## Единая библиотека иконок · 00.00.068

Текстовые символы заменены локальным проверенным набором из восьми Tabler Outline (MIT). Генератор выбирает семантические ai-icon ID; сервер вставляет только готовые изображения одного outline стиля, stroke2/viewBox24, размер32px и фиксированный slate цвет. Произвольный SVG модели по-прежнему запрещён. В документе сохраняются обычные image components с точными catalogue data URIs; JSON schema и backend policy согласованно расширены только этим enum, envelope schemaVersion1 прежний. В каждой иконке вложена MIT лицензия, экспорт сохраняет её без CDN/видимых backlinks. Старые сохранённые страницы автоматически не переписываются.

Проверки: npm test (с разрешённым запуском локального API), npm run build, PHP syntax PASS. В браузере осмотрены все8 реальных catalogue images: /tmp/juzimder-tabler-068.png. Это контрольный каталог, не новая LLM генерация/не live import-save-reload acceptance; данный end-to-end сценарий остаётся NOT RUN. Export fixture подтверждает сохранение всех иконок и отказ изменённому SVG. Следующий шаг: реальная генерация с иконками → импорт → сохранение/reload и AI style-edit acceptance.

## Preview и сквозная приёмка Tabler · 00.00.069

AI preview CSP допускал blob/Commons, но блокировал exact catalogue data URI из .068. Теперь img-src preview также допускает data:, как уже делала родительская CSP. Script/connect/object запреты и sandbox без allow-scripts прежние. Сервер и schema принимают только точные восемь доверенных SVG data URI; произвольный SVG модели не разрешён. Пробный Blob вариант не подошёл из-за parent CSP и полностью удалён; ошибочно оставшаяся ссылка helper в import handler выявлена и удалена до финальной приёмки. Во время этого QA rollback создал ревизию61, старый proposal60 корректно стал read-only; guards не обходились.

Системные инструкции теперь предпочитают semantic icons для абстрактных software capabilities (tasks/focus/reminders/security/workflow) вместо подменяющих фото телефонов/часов. Физические продукты/места по-прежнему получают автоматический подбор открытых фото. До уточнения реальный ответ выбрал stock photos и смешанное слово timely; этот вариант не импортировали. Первый Taste запрос завершился network/invalid JSON; это не исправленная доступность внешнего провайдера.

После уточнения две реальные Standard генерации без просьбы об иконках самостоятельно выбрали ai-icon-list/target/clock. Финальная base61: design20.6с, photo selection23.4с; design2911tokens/$0, photo414tokens/$0. Фото отклонены; карточки используют3 Tabler Outline. Scope ai-scope-63f3acc2643e4759. Preview: все3 naturalWidth24 и загружены, осмотрено на desktop. Import → save62; все3 native images loaded32x32. Один Undo удалил scope, Redo вернул; save64. После reload64 все3 loaded32x32, source data URI сохранён. Screenshot /tmp/juzimder-saved-icons-069.png просмотрен. Общий sandbox fixture8/8 также loaded: /tmp/juzimder-icon-preview-069.png. Главная страница не редактировалась, публикации не было; предыдущие QA-секции сохранены. Попытка mobile переключения не дала доказательства375px — mobile acceptance не PASS.

npm test, npm run build, PHP syntax, node --check src/main.js, diff checks PASS. Следующее: live AI style-edit и stale-selection/CAS сценарии; мобильная геометрия отдельно открыта.

## Иконки редактора, URL просмотра и фон с overlay · 00.00.070

Четыре требования владельца: Tabler Outline в интерфейсе редактора; Eye открывает URL выбранной сохранённой страницы в новой вкладке; background-image и цвет/непрозрачность Background Overlay в Оформлении контейнера; contextual hero background по умолчанию в AI.

Локальный MIT Tabler editor catalogue отделён от generated-page catalogue: Core icons configuration, toolbar, layers, app chrome, Flex и Rich Text. Dynamic host controls наблюдаются вне iframe, содержимое пользовательской страницы не переписывается. Лицензия сохранена в sources и встроена в каждый inline SVG. Брендовый логотип и структурные thumbnails остаются логотипом/превью, не заменяются иконками.

Eye — ссылка target=_blank, rel=noopener noreferrer на owner-authenticated GET /api/projects/{project}/pages/{page}/view. Возвращает saved static HTML со встроенным CSS/private image bytes, no-store и sandbox/no-script CSP. Не публикует сайт и не показывает unsaved changes. Anonymous401, другой owner404, missing page404 проверены API tests.

В Оформлении контейнера появился Background image (HTTPS или существующий private asset через библиотеку), цвет overlay и slider0..100%, Apply/Remove. Overlay — uniform rgba gradient в native background-image; текст не получает opacity. Canonical Project Data schemaVersion1 прежний. Backend BackgroundImage разрешает только none или один HTTPS/private-project URL с optional identical bounded rgba gradient; произвольные CSS url expressions, data/javascript/http и credential URLs отклоняются. CSS backgrounds включены в assetReferences для existing owner checks; static exporter переносит private CSS/inline backgrounds в media/, оба preview paths встраивают private bytes. Server не скачивает введённый URL.

AI получает contextual class ai-hero-background при подходящей предметной/пространственной сцене; software hero остаётся типографическим. Приложение ставит назначенное фото в cover background с чёрным55% overlay, одинаково в preview/import; fallback dark background/white text задан сервером при отсутствии фото. Модель по-прежнему не выдаёт собственные image URLs/CSS url(). Новая реальная LLM генерация Hero в этом проходе NOT RUN; instructions/marker/fallback проверены fixtures, native background path проверен браузером.

Live приёмка в отдельной /tmp SQLite на127.0.0.1:18083, не в проектах владельца: новый Background Overlay QA070, Hero → Commons CC0 фото Evan-Amos Big Mac hamburger (контроль геометрии, не тематическая генерация) → white overlay65% → save revision2. Computed container/heading opacity1. Eye реально открыл новую вкладку с HTML и тем же background/overlay. Undo→background none, Redo→background restored, save4. После reload background/rgba65% сохранены. Native Mobile: viewport375, section375, background cover; screenshot /tmp/juzimder-background-mobile-070.png просмотрен. Desktop /tmp/juzimder-background-070.png и view /tmp/juzimder-eye-070.png сохранены. Проверены слои: все отрисованные SVG host controls Tabler; CSS-rendered frame decorations не считаются картинками generated page. Production проекты не записывались, публикации не было.

npm test включая новые background/overlay и view owner tests, npm run build, PHP syntax, JS syntax и diff checks PASS. Открытые следующие шаги: live generated Hero с photo/overlay и полная responsive матрица, live AI style-edit/conflicts из предыдущего плана.

## Совместимость фоновых стилей · 00.00.071

Финальный live Eye на существующем проекте обнаружил регрессию .070: новый background validator отвергал ранее сохранённые Core linear-gradient(rgb…) и initial. BackgroundImage теперь сохраняет безопасные CSS-wide keywords/пустое значение и ограниченные non-resource linear/radial/conic gradients. Nested url()/expression(), credential URLs и опасные схемы по-прежнему rejected; fixtures добавлены. Пустое значение пропускается экспортом. Прочие .070 функции прежние.

Readonly проверка полного existing Project Data (Design token QA) PASS без миграции/перезаписи; фактический private page view на8080 вернул страницу с её заголовком вместо ошибки background validation. npm test/build, PHP syntax и diff checks PASS. Итоговая текущая версия00.00.071; скриншоты функциональной .070 приёмки остаются применимыми к фону и overlay (визуального изменения .071 нет).

## Стили native GrapesJS в просмотре и экспорте · 00.00.072

По скриншоту владельца обнаружен дефект общего StaticSiteExporter: component.classes не попадали в HTML, rules.selectorsAdd игнорировались, а часть расширенных Core CSS properties отбрасывалась. Поэтому проверки .070 на inline-styled Hero не покрывали сгенерированную страницу с class rules.

Экспортер теперь переносит классы (строки и native selector objects) в attributes.class, включая wrapper/body, без записи/миграции Project Data. Core selectors собираются конкатенацией (.a.b), selectorsAdd добавляется отдельным списком. Ограниченный parser разрешает безопасные tag/class/id descendant selectors, compound classes, comma lists и фиксированные UI pseudo states; injection fragments отбрасываются. Media rules сохраняются. Добавлены expanded border/radius/text-decoration/gap/background-position properties и box-shadow, соответствующие наблюдаемому Core сохранению. Фильтры значений/URL и owner/CSP gates прежние. Исправление общее для Eye, saved preview и новых ZIP/snapshots; существующая публикация автоматически не перепубликуется.

Новые regression fixtures используют реальный формат Core: classes отдельно, selectors пустой массив + selectorsAdd, compound selector objects/state, scoped tag selectors, responsive rule, expanded properties и malicious selector fragment. npm test, npm run build, PHP syntax, diff checks PASS.

В браузере открыта та же сохранённая Photo card QA страница с медицинским макетом (9291eeeb-4cb7-4553-9e7a-a25f3cfd1496). Осмотрен именно блок «Медицинские услуги» из скриншота: grid330.664px x3, padding64px24px, card radius12px/border1px, pill badge и blue CTA восстановлены. /tmp/juzimder-styles-fixed-072.png просмотрен. Открыта исправленная вкладка view; пользовательский editor не перезагружался и Project Data не менялись. Полный pixel-perfect/вся mobile матрица не объявляются.

## Смысловые Tabler-иконки Flex · 00.00.073

По скриншоту владельца заменены повторяющиеся generic arrows-move/menu/text-align glyphs в Flex settings. Отдельный flexIconSpec задаёт явное соответствие всех18 options: однонаправленные arrows для row/column/reverse; align-box для начала/центра/конца по нужной оси; layout-distribute/spacing/columns3 для трёх видов распределения; baseline и cross-axis arrows для stretch; text-wrap-disabled/text-wrap с отражением для wrap-reverse. Поворот Columns3 и wrap применяется только в editor UI. Существующие title/aria-label сохраняют точное CSS значение и русское описание.

Icons обновляются при выборе компонента/изменении стиля и учитывают direction, reverse и cross-axis wrap-reverse. Ни одна группа не содержит одинаковых icon+transform options. Это иконографические обозначения, не миниатюрная точная симуляция всех CSS spacing алгоритмов. Канонический Project Data и сами CSS действия прежние. Источники официальные Tabler Outline, MIT notice включён в catalogue как раньше.

Unit fixtures проверяют все18 options во всех4 directions, наличие vetted assets, отсутствие повторов внутри групп и конкретные physical-axis/reverse mappings. npm test/build, PHP/JS syntax и diff checks PASS. Browser QA выполнена в отдельной прежней /tmp SQLite на18083: row → row-reverse + wrap-reverse; DOM computed styles подтвердили оба значения, main-start icon сменился на right-middle, cross-start на bottom-center. Вернули column/nowrap, saved revision7. Все18 actual DOM iconSpec осмотрены. /tmp/juzimder-flex-icons-073.png просмотрен, полный context /tmp/juzimder-flex-editor-073.png сохранён. Пользовательские страницы на8080 не редактировались/не reload.

- [x] 00.00.074: шестерёнка настроек страницы в редакторе; название/slug, SEO, отдельные OG/Twitter metadata, существующее глобальное оформление; CAS/revisions и миграция settings v5, desktop save/reopen QA.

- [x] 00.00.075: исправить grid/flex-компоновку после вставки photo credit; компактные pill badges; сохранить offsets в HTML export; исправленная сохранённая страница и desktop QA.
- [ ] Проверять пригодность фото именно для Hero (посторонние вывески, выставочные фасады, текст на фото), а не только совпадение темы поиска.
