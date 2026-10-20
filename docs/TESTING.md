# Проверки, 20.10.2026

## Автоматические

`npm run build`: PASS, Vite 8.3.4. Lazy Core chunk ~1.10 МБ raw/291 КБ gzip; остаётся предупреждение chunk>500KB. Dashboard JS отдельно ~22КБ raw. Build не включает Playwright.

`php -l backend/Domain.php`, `php -l backend/api.php`, `php -l public/router.php`: PASS.

`npm test`: domain assertions + один составной HTTP integration scenario (не выдаём его за десятки независимых tests). Проверяются migration v1, create, tenant 404, полный round trip с metadata/responsive CSS, ревизии/CAS conflict без лишней записи, запрет script/iframe/event/javascript URL/HTML payload/CSS url/custom-code, неверная schema/tree, disk reopen. Реальный Core 0.23.8 browser fixture включён в regression validation.

HTTP scenario: anonymous 401, bad CSRF 403, register/session, create, чужой list/read/write, save/get equality, stale 409, invalid 422, revisions list, logout, bad login, повторный вход и сохранённая версия. Используется временная SQLite и отдельный localhost:18081, удаляемые после теста. В sandbox, запрещающем binding порта, тест требует разрешения loopback; первоначальный запуск был блокирован средой, повторный с разрешением прошёл.

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

В процессе выявлены init race при page events и head/main schema compatibility; исправлены. Реальный Core fixture добавлен в tests. Скриншоты просмотрены: тёмные компактные панели, светлый canvas, нейтральный зелёный accent; mobile hero не имеет видимого overflow. Часть inspector ниже fold имеет собственный scroll.

## Не проверено / не реализовано

Публичный deployment, Apache/Nginx live config, production TLS/session policy, multi-user simultaneous browser tabs, реальный upload, AI provider/MCP/Zero Canvas/static publication/ZIP, revision restore, реальное мобильное устройство, narrow viewport authoring, полная keyboard/a11y матрица, pixel-perfect responsive inspection всех блоков, performance/load testing, upstream exploit/CVE verification. Не присваивать PASS этим пунктам.

Evidence: docs/evidence/dashboard.png, editor-desktop.png, editor-mobile-canvas.png; итоговый снимок после финальной сборки — editor-final.png. Снимки показывают локальный редактор, не опубликованный сайт.
