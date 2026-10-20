# Проверки, 20.10.2026

## Автоматические

`npm run build`: PASS, Vite 8.3.4. Lazy Core chunk ~1.10 МБ raw/291 КБ gzip; остаётся предупреждение chunk>500KB. Dashboard JS отдельно ~22КБ raw. Build не включает Playwright.

`php -l backend/Domain.php`, `php -l backend/api.php`, `php -l public/router.php`: PASS.

`npm test`: domain assertions + один составной HTTP integration scenario (не выдаём его за десятки независимых tests). Проверяются migration v1, create, tenant 404, полный round trip с metadata/responsive CSS, ревизии/CAS conflict без лишней записи, запрет script/iframe/event/javascript URL/HTML payload/CSS url/custom-code, неверная schema/tree, disk reopen. Реальный Core 0.23.8 browser fixture включён в regression validation.

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

## Не проверено / не реализовано

Публичный deployment, Apache/Nginx live config, production TLS/session policy, multi-user simultaneous browser tabs, реальный upload, AI provider/MCP/Zero Canvas/static publication/ZIP, реальное мобильное устройство, полная responsive matrix всех блоков, clipboard и полная keyboard/a11y матрица, performance/load testing, upstream exploit/CVE verification. Не присваивать PASS этим пунктам.

Evidence: docs/evidence/dashboard.png, editor-desktop.png, editor-mobile-canvas.png; итоговый снимок после финальной сборки — editor-final.png. Снимки показывают локальный редактор, не опубликованный сайт.
