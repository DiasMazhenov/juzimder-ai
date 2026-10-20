# Проверки, 21.10.2026

## Автоматические

`npm run build`: PASS, Vite 8.3.4. Lazy Core chunk ~1.10 МБ raw/291 КБ gzip; остаётся предупреждение chunk>500KB. Dashboard JS отдельно ~22КБ raw. Build не включает Playwright.

`php -l backend/Domain.php`, `php -l backend/Ai.php`, `php -l backend/Operations.php`, `php -l backend/api.php`, `php -l public/router.php`: PASS.

`npm test`: domain assertions, focused typed-operation PHP tests и составной HTTP integration scenario. Проверяются migration v1, create, tenant 404, полный round trip с metadata/responsive CSS и CSS custom properties темы, ревизии/CAS conflict без лишней записи, запрет script/iframe/event/javascript URL/HTML payload/CSS url/custom-code, неверная schema/tree и disk reopen. Реальный Core 0.23.8 browser fixture включён в regression validation. Node tests проверяют нормализацию токенов, fallback для повреждённых сохранённых значений, миграцию старых значений и использование токенов всеми presets. `tests/ai.php` проверяет OpenRouter request envelope, secret redaction, output cap, timeout, bounded retry, usage/cost metadata и strict validation результата без real network call. `tests/operations.php` покрывает stable IDs, create/update/delete/move/reorder, allowlists, cycle rejection и abort всей пачки при ошибке.

HTTP scenario: anonymous 401, bad CSRF 403, register/session, create, чужой list/read/write, save/get equality, stale 409, invalid 422, revision list/restore (новая версия, сохранение истории, stale/foreign/missing target), logout, bad login, повторный вход и сохранённая версия. Используется временная SQLite и отдельный localhost:18081, удаляемые после теста. В sandbox, запрещающем binding порта, тест требует разрешения loopback; первоначальный запуск был блокирован средой, повторный с разрешением прошёл.

`npm install` сообщил 0 vulnerabilities для установленного дерева. Это не полный vulnerability/SBOM review и не аудит bundled PHP-GrapesJS.

AI API integration tests проверяют авторизацию `GET /ai/status`, CSRF и authentication для plan request, пустой/слишком длинный prompt, отключённый endpoint без серверных credentials и отсутствие создания/изменения проектов. Typed-operation integration проверяет owner, CSRF, expected version, создание component IDs, сохранение revisions и отсутствие записи при невалидных операциях. В test server OpenRouter variables принудительно пустые; provider не вызывается.

## Codex Browser: выполнено через UI

URL http://127.0.0.1:8080, CSS viewport screenshot 1280×720, built production assets, реальный PHP server + локальная SQLite, без mock API. QA аккаунт только локальный, пользовательские credentials не использовались и не коммитились.

1. Регистрация → пустой dashboard → create «Первый сайт — QA» → editor: PASS.
2. Add Hero → выбрать текст → изменить через inspector → Save → revision 1: PASS.
3. Full reload → dashboard → открыть → текст «Проверка Juzimder: этот текст сохранится после перезагрузки.» и прежние component id: PASS. PNG editor-desktop.png.
4. Mobile device → canvas 375px → перенос заголовка и абзаца, кнопка остаётся внутри: PASS. PNG editor-mobile-canvas.png. Это mobile canvas внутри desktop viewport, не отдельный phone viewport.
5. Grid features → Undo убирает секцию → Redo возвращает → Save revision 2 → reload/open обе секции: PASS.
6. Создать страницу «Контакты» → пустой canvas → CTA → Save revision 3 → full reload → обе страницы в selector: PASS.
7. Sandbox preview: визуальный рендер Hero и текста подтверждён, PNG preview.png. После финальной сборки новых console errors не обнаружено; два старых сообщения относятся к исправленному первому build.

## Codex Browser: autosave и restore

Проверено на временном PHP server `127.0.0.1:18082` с отдельной SQLite в `/private/tmp` и синтетическим QA аккаунтом. Добавление Text создало revision 1 через autosave; добавление Hero создало revision 2. История показала обе версии. Восстановление revision 1 вернуло canvas к текстовому блоку и создало новую revision 5; UI остался в статусе «Восстановлена ревизия 1 · новая ревизия 5». Через 1,8 с сервер по-прежнему имел version 5 и пять записей revisions, без лишнего autosave после загрузки. Временные server и база остановлены/удалены после приёмки.

PNG: `docs/evidence/revision-history-restore.jpg`.

В процессе выявлены init race при page events и head/main schema compatibility; исправлены. Реальный Core fixture добавлен в tests. Скриншоты просмотрены: тёмные компактные панели, светлый canvas, нейтральный зелёный accent; mobile hero не имеет видимого overflow. Часть inspector ниже fold имеет собственный scroll.

## Codex Browser: responsive authoring и клавиатура

На основном локальном приложении `http://127.0.0.1:8080` создан синтетический QA аккаунт и отдельный проект «QA — mobile authoring»; они остались в локальной SQLite. Проверка выполнена на production build, без mock API.

- Viewport 320×740 и 390×844: холст, библиотека и инспектор доступны через панели «Холст», «Секции», «Свойства»; добавление Text из библиотеки открывает свойства выбранного блока. Пустое состояние холста отображается поверх frame; горизонтального overflow нет.
- Viewport 768×1024: панель свойств доступна и прокручивается; горизонтального overflow нет.
- Viewport 1280×800: сохранён трёхколоночный редактор с библиотекой, холстом и инспектором.
- Meta+Z отменил добавление Text, Meta+Shift+Z вернул его, Meta+S показал «Сохранено · ревизия 5». Meta+Z при фокусе в textarea не отменил компонент.

После проверки временная Hero-секция была удалена восстановлением ревизии 6; создалась новая ревизия 9, история сохранена. Эти проверки подтверждают панельный мобильный workflow в браузерном viewport, не реальное устройство, touch-жесты, все комбинации блоков или полную a11y/copy-paste матрицу.

## Локальная авторизация admin/admin

`npm run serve` запускает PHP только на `127.0.0.1` и включает `JUZIMDER_DEV_AUTH=1`. В Codex Browser вход `admin/admin` подтвердил открытие dashboard; после полной перезагрузки сессия сохранилась. Автотест проверяет тот же вход на отдельной временной SQLite, повторный login после создания пользователя и отказ от коротких credentials без dev-флага. Аккаунт `admin@localhost.test` в рабочей `var/app.sqlite` создан как обычный пользователь, не admin-role; он пока не владеет проектами. Два существовавших QA-проекта остались у прежних владельцев.

Screenshot: `docs/evidence/admin-login-success.jpg`.

## Codex Browser: Hug/Fill и min/max

В изолированной QA SQLite на временном loopback-сервере проверены режимы ширины Auto/Hug/Fill и высоты Hug/Fill. Min/max для Hero (360px, 880px, 480px, 720px) применились, сохранились как CSS в Project Data и вернулись в инспектор после полной перезагрузки и повторного открытия проекта. Отдельно после reload подтверждён Fill 100% по ширине и высоте, затем QA-проект возвращён к Hug. Некорректное значение `inherit` отклонено без новой ревизии. Тестовая БД и сервер использовались только для этой проверки.

Screenshot: `docs/evidence/phase3-sizing-controls.jpg`.

## Проектная тема · версия продукта 00.00.002

`node --test tests/design-tokens.test.mjs` проверяет восемь цветов, список шрифтов, границу радиуса 0–32 px, чтение/запись CSS variables и замену стандартных значений старых presets. `tests/blocks.test.mjs` подтверждает, что десять presets используют общие токены и что готовая библиотека вместе с переменными темы проходит backend `Document::validate`.

Codex Browser, отдельная копия приложения с временной SQLite и отдельной session cookie: применены новая палитра, Georgia и 12 px к уже вставленному Hero; добавлена Features-секция; создана вторая страница и вставлен FAQ. Автосохранение дошло до ревизии 5. После выхода в dashboard и повторного открытия проекта контролы показали те же значения темы. Desktop viewport 1280×720: Hero и FAQ отрисовали новую типографику, цвета и границы. Пользовательская база и сессия не использовались.

## Codex Browser: responsive matrix и keyboard smoke · версия 00.00.002

Проверено в изолированной временной копии приложения со своей SQLite и проектом `Design token QA`; основная база и вкладка пользователя не использовались. В главную страницу добавлены Hero, Features, CTA, Text, Navigation, Process, Testimonial и Footer; FAQ открыт на второй странице.

- Mobile: CSS viewport 375 px; главная страница и FAQ не имеют горизонтального переполнения. Визуально выборочно проверены Hero и FAQ.
- Tablet: CSS viewport 768 px; главная страница и FAQ не имеют горизонтального переполнения.
- Desktop: FAQ визуально проверен в доступной ширине редактора (734 px); горизонтального переполнения нет. Main page на desktop в этом прогоне не проверялся.
- Клавиша Tab переводит фокус между контролами и показывает видимый focus ring. Кнопки в проверенном accessibility tree имеют доступные имена.
- GrapesJS Core 0.23.8 содержит стандартные keymaps `⌘/Ctrl+C` и `⌘/Ctrl+V`; само сочетание в этом прогоне не подтверждено. Codex Browser не передал `metaKey`/`ctrlKey` при синтетическом нажатии модификаторов, поэтому clipboard acceptance остаётся NOT RUN. Пользовательский clipboard не читался и не изменялся.
- Image preset не включён в визуальную матрицу: для осмысленной проверки ему нужен отдельный HTTPS-ресурс.

Это частичная браузерная матрица, не приёмка на физическом устройстве и не полная keyboard/a11y проверка. Скриншоты проверены в текущей QA-сессии; отдельные PNG в репозиторий не добавлялись.

## AI design-plan API · версия продукта 00.00.003

Проверены PHP unit tests с поддельным HTTP transport и API integration tests на отдельной временной SQLite. Покрыты JSON Schema request, fixed OpenRouter endpoint, server-only bearer header, `max_completion_tokens=1200`, structured outputs, usage request, timeout clamp, resolved model/cost parsing, однократный retry на 429 и отсутствие retry на 401. Ошибка не содержит upstream response/key. Недопустимые JSON планы, неизвестные секции и отсутствие Hero отклоняются. API возвращает 401 без сессии, 403 без CSRF, 422 для пустого/длинного prompt и 503 без env credentials; проект не меняется.

Реального OpenRouter вызова не было: `OPENROUTER_API_KEY` и `OPENROUTER_MODEL` в процессе не настроены. Браузерный интерфейс, согласие на передачу prompt, реальная стоимость и AI-операции над Project Data пока не существуют.

## Typed operations · версия продукта 00.00.004

`tests/operations.php` проверяет чистое преобразование документов, create с серверными stable IDs, update content/styles/layout, delete с явным `confirm`, move/reorder, неизвестные поля/свойства, unsafe CSS, циклы, лимит пачки и отказ без изменения входного документа. HTTP integration на временной SQLite проверяет создание секции и ID, targeted content update, stale 409, owner 404, удаление только с подтверждающим флагом, новую revision на успехе и сохранение исходной версии при ошибке. AI и UI этот endpoint ещё не вызывают.

Codex Browser на UI build версии 00.00.003 (Image UI в 00.00.004 не менялся): в отдельном `.localhost` origin и отдельной временной SQLite Image preset с `https://placehold.co/1200x700/png` отрендерил изображение, сохранил alt/src в revision 1 и восстановил его после reload/reopen. На 375 px видимого горизонтального overflow нет. Это один проверенный mobile width, не полная responsive matrix. Cmd/Ctrl+C/V на выбранной Hero в том же изолированном проекте не вызвали копирование/вставку через доступный Codex Browser; clipboard acceptance остаётся NOT RUN, системный clipboard не читался.

## Не проверено / не реализовано

Публичный deployment, Apache/Nginx live config, production TLS/session policy, multi-user simultaneous browser tabs, реальный upload, live OpenRouter call с save/reload, AI-генерация собственного текста/компонентов и targeted edits, MCP/Zero Canvas/static publication/ZIP, реальное мобильное устройство, полная responsive matrix Image и соседних блоков, clipboard и полная keyboard/a11y матрица, performance/load testing, upstream exploit/CVE verification. Не присваивать PASS этим пунктам.

Evidence: docs/evidence/dashboard.png, editor-desktop.png, editor-mobile-canvas.png; итоговый снимок после финальной сборки — editor-final.png. Снимки показывают локальный редактор, не опубликованный сайт.


## Библиотека секций · версия продукта 00.00.001

`node --test tests/blocks.test.mjs`: проверяет уникальные id и метаданные десяти presets, свежий объект для каждой вставки, разрешение внутренних ссылок и принятие всей библиотеки серверным `Document::validate` по Project Data v1. Codex Browser: все пять новых блоков вставлены в отдельный локальный проект, autosave сохранил ревизию 2, повторное открытие восстановило их. На desktop проверены навигация и карточки этапов; на viewport 390×844 canvas шириной 375px, внешний документ не переполняет экран (390/390). QA выполнялась на копии backend с отдельным именем session cookie и временной SQLite; пользовательская сессия/БД не менялись.

## Версия продукта · 00.00.005

На production build в отдельной `.localhost` вкладке с временной SQLite визуально проверены dashboard и editor: оба показывают `00.00.005`, dashboard с подписью «Версия продукта», editor — «Версия». В проверке использовалась сборка после `npm run build`; существующая пользовательская вкладка на `127.0.0.1:8080` не менялась. В CSS версия оставлена видимой и в мобильной компоновке; отдельный визуальный mobile viewport smoke для этой подписи не выполнялся.


## AI-план и typed preset operations · версия продукта 00.00.006

`node --test tests/ai-plan.test.mjs` проверяет разбор плана, ограничения списка секций и палитры, использование только известных библиотечных presets, разрешение цветов плана при сохранении текущего font/radius, устранение ссылок на отсутствующие секции и переназначение конфликтующих preset-якорей. `npm test` прошёл целиком после разрешённого запуска HTTP integration на loopback с временной SQLite. `npm run build`, PHP syntax и `git diff --check` прошли; Vite сохранил известное предупреждение о большом GrapesJS chunk (~1.10 МБ raw / 291 КБ gzip).

В отдельной Codex Browser вкладке на `juzimder-ai.localhost:18083`, с временной SQLite и dev login, создан тестовый проект. Проверены AI-панель, диалог, disclosure о передаче только prompt и disabled-состояние кнопки, когда `/api/ai/status` сообщает `configured: false`. Prompt не отправлялся; тестовый проект не менялся. Live OpenRouter запрос и UI save/reload после реального ответа остаются NOT RUN, потому что серверные credentials не настроены. Unit/API тесты helper и typed operations этот live acceptance не заменяют.

В ходе этого продолжения повторно оценён clipboard: Codex Browser не выполнил сочетание в GrapesJS iframe и вернул timeout для selector/press, поэтому результат остаётся NOT RUN, не дефектом приложения. Системный clipboard не читался и не менялся.

## Управление страницами и редактор · версия продукта 00.00.007

`tests/project-settings.php` и API integration scenario проверяют migration 001→002 с сохранением полных документов/revisions, home по умолчанию, русско-казахскую slug-нормализацию, запрет системных/дублирующихся URL, CRUD страниц, rename отдельно от slug, дублирование, atomic home selection, delete-home fallback, tenant isolation, stale-version rejection и restore маршрутов вместе с Project Data. `npm run build`, полный `npm test`, PHP syntax lint и `git diff --check` завершились успешно.

В Codex Browser использовалась временная SQLite и тестовый проект; существующая вкладка и рабочая база пользователя не менялись. Визуально проверены Pages Dashboard и отдельная область Header/Footer/404; создана вторая страница «О компании», открыта Site Settings navigation, открыт Layers и Sections drawer. Поиск и фильтр «Элементы» показали нужные карточки; базовая кнопка добавилась в canvas и сохранилась revision 1. В editor выбран Body, изменён режим на Flex, автосохранение создало revision 2; после полного reload Layers снова показал Body, а inspector — Flex. Настройки Fonts/SEO/Analytics/Forms/Code/Domain пока только показывают честный статус реализации; global Header/Footer/404 и публикация не подключены. Эти пункты не считаются функционально принятыми.

## Глобальные design tokens · версия продукта 00.00.008

`tests/project-settings.php` проверяет versioned settings envelope v2, legacy token inference, strict validation, раскладку CSS variables по всем page roots и преобразование preset palette без изменения пользовательских цветов. API integration проверяет owner, CSRF, unknown/unsafe values, CAS 409, сохранение новой ревизии, передачу токенов новым страницам, restore и ограниченный settings payload редактора. Полный `npm test`, `npm run build`, PHP syntax lint и `git diff --check` прошли. Build оставляет существующее предупреждение о GrapesJS chunk ~1.10 МБ raw / 291 КБ gzip.

В изолированном Codex Browser проекте `UI QA Project 007` на `localhost:18084` проверены обе формы. Цвет основного текста изменён на `#345678`, системный шрифт выбран через Fonts, формы создали ревизии 3 и 4; после reload оба значения восстановились. Затем в редакторе акцент изменён на `#aa88cc`, сохранён revision 5 и после reload отображается в диалоге темы вместе с общим системным шрифтом. QA использовал отдельную временную SQLite; вкладка пользователя на `127.0.0.1:8080` и рабочая база не менялись. Публичная публикация/export не проверялись и не реализованы.

## Typography tokens · версия продукта 00.00.009

`npm test` прошёл полностью: JS and PHP validation, API owner/CSRF/CAS/revision coverage, legacy schema v2 font migration, strict typography bounds, responsive H1–H3 CSS variables, AI preset resolution, and preservation of custom local heading styles. `npm run build`, PHP syntax checks for `ProjectSettings.php`, `Domain.php`, `api.php`, `router.php`, and `git diff --check` passed. The existing Vite warning for the GrapesJS chunk (~1.10 MB raw / 291 KB gzip) remains.

In Codex Browser, an isolated QA database/project saved heading font Georgia, body font system-ui, heading/body weights 700/300, base text 18px, H1 72px, H2 42px, H3 30px, H4–H6 values, line heights 1.3/1.8, and letter spacing 0.5px as revision 1. After full reload all selections remained. Adding Hero rendered the heading in Georgia and body in system-ui; autosave created revision 2. The editor quick-theme dialog showed the two separate families; changing only the heading family to Arial preserved system-ui for body text, saved revision 3, and both values survived reload. Public rendering and static HTML/CSS export remain unimplemented and were not accepted by this test.
