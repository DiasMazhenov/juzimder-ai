# Проверки, 21.10.2026

## Автоматические

`npm run build`: PASS, Vite 8.3.4. Lazy Core chunk ~1.10 МБ raw/291 КБ gzip; остаётся предупреждение chunk>500KB. Dashboard JS отдельно ~22КБ raw. Build не включает Playwright.

`php -l backend/Domain.php`, `php -l backend/api.php`, `php -l public/router.php`: PASS.

`npm test`: domain assertions + один составной HTTP integration scenario (не выдаём его за десятки независимых tests). Проверяются migration v1, create, tenant 404, полный round trip с metadata/responsive CSS и CSS custom properties темы, ревизии/CAS conflict без лишней записи, запрет script/iframe/event/javascript URL/HTML payload/CSS url/custom-code, неверная schema/tree, disk reopen. Реальный Core 0.23.8 browser fixture включён в regression validation. Отдельные Node tests проверяют нормализацию токенов, fallback для повреждённых сохранённых значений, миграцию старых значений и использование токенов всеми presets.

HTTP scenario: anonymous 401, bad CSRF 403, register/session, create, чужой list/read/write, save/get equality, stale 409, invalid 422, revision list/restore (новая версия, сохранение истории, stale/foreign/missing target), logout, bad login, повторный вход и сохранённая версия. Используется временная SQLite и отдельный localhost:18081, удаляемые после теста. В sandbox, запрещающем binding порта, тест требует разрешения loopback; первоначальный запуск был блокирован средой, повторный с разрешением прошёл.

`npm install` сообщил 0 vulnerabilities для установленного дерева. Это не полный vulnerability/SBOM review и не аудит bundled PHP-GrapesJS.

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

## Не проверено / не реализовано

Публичный deployment, Apache/Nginx live config, production TLS/session policy, multi-user simultaneous browser tabs, реальный upload, AI provider/MCP/Zero Canvas/static publication/ZIP, реальное мобильное устройство, responsive-проверка Image preset, clipboard и полная keyboard/a11y матрица, performance/load testing, upstream exploit/CVE verification. Не присваивать PASS этим пунктам.

Evidence: docs/evidence/dashboard.png, editor-desktop.png, editor-mobile-canvas.png; итоговый снимок после финальной сборки — editor-final.png. Снимки показывают локальный редактор, не опубликованный сайт.


## Библиотека секций · версия продукта 00.00.001

`node --test tests/blocks.test.mjs`: проверяет уникальные id и метаданные десяти presets, свежий объект для каждой вставки, разрешение внутренних ссылок и принятие всей библиотеки серверным `Document::validate` по Project Data v1. Codex Browser: все пять новых блоков вставлены в отдельный локальный проект, autosave сохранил ревизию 2, повторное открытие восстановило их. На desktop проверены навигация и карточки этапов; на viewport 390×844 canvas шириной 375px, внешний документ не переполняет экран (390/390). QA выполнялась на копии backend с отдельным именем session cookie и временной SQLite; пользовательская сессия/БД не менялись.
