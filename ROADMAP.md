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
- [ ] Live AI edit acceptance: real provider proposal → confirm → save → reload; the configured free model returned a temporary unavailable response.
- [x] Project-scoped user Skill library: upload/paste Markdown, edit, activate, delete, owner/CAS protection, and select a saved Skill for full-page generation. The selected Skill text and owner checklist are sent only after generation is confirmed; every manual checklist item must be checked before import.
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
