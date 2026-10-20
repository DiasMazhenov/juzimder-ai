# Juzimder AI — контекст проекта

Обновлено 21.10.2026. Цель: самостоятельная платформа визуального создания адаптивных сайтов с AI и будущим MCP. Основной репозиторий: https://github.com/DiasMazhenov/juzimder-ai . В начале папка не содержала файлов/Git, git ls-remote не вернул refs. Репозиторий ведётся в main; ветка отслеживает origin/main. Текущая версия продукта: 00.00.003 (npm SemVer 0.1.3).

## Принятые решения

- Phase 0: статический аудит PHP-GrapesJS commit 4a464cc3edf54db3ddacdad4b3ddab5bcf7b8589. Подробности в docs/PHP_GRAPESJS_AUDIT.md. Fork отклонён из-за persistence/auth/DB/deployment/licensing проблем.
- Вариант C: собственный PHP 8.2+ API, PDO SQLite, browser JS/CSS, Vite build. PHP hosting без Node runtime. GrapesJS Core 0.23.8, без Studio SDK/форка.
- Каноническая модель: schemaVersion=1, engine=grapesjs, data=getProjectData. Сохраняем pages/frames/components/styles/assets/metadata, не отдельное HTML дерево. Структурная схема в schemas/, authoritative policy в Document validator.
- Project объединяет workspace/site для первого среза. Owner проверяется сервером. Mutations: session + CSRF + validation + expected version.
- Лицензия собственного продукта ожидает выбора владельца. Исходники PHP-GrapesJS не переносились. Notices Core сохранены.

## Реализовано

Auth регистрация/вход/выход, password hashing/session rotation/CSRF/rate limit. Локальная команда `npm run serve` включает dev login `admin/admin` только для loopback соединений; это тестовая учётная запись без отдельной admin-роли и режим запрещён для публичного хостинга. Dashboard: список и создание проектов. Несколько страниц в одном документе. Editor: библиотека структурных секций, слои, traits и style inspector, Flex/Grid, responsive devices, Undo/Redo, текст, изображения по HTTPS URL, preview sandbox, JSON export. На ширине до 800 px панели холста, секций и свойств переключаются отдельными кнопками; есть Cmd/Ctrl+Z, Shift+Z/Y и Cmd/Ctrl+S с защитой полей ввода. Сервер хранит полный document и до 200 транзакционных revisions. Schema v1 уточнена и согласована с runtime policy; malformed/security JSON fixtures проверяются до сохранения. Автосохранение с debounce 1,4 с; CAS 409 при параллельной записи предлагает экспорт или загрузку серверного состояния. История последних 50 ревизий в UI; восстановление пишет старое состояние в новую ревизию и не стирает текущую историю. Выход из редактора сначала пытается сохранить изменения.

Инспектор размеров даёт режимы ширины Авто/Hug/Fill и высоты Hug/Fill; четыре min/max поля принимают CSS-размеры с px, %, rem, em, vw или vh. Пустое значение снимает ограничение. Эти стили сохраняются в каноническом Project Data.

Рабочая структура: src/, backend/Domain.php, backend/api.php, backend/migrations/, public/router.php, schemas/, tests/, docs/. Не создавать пустые пакеты для будущих функций. Production сборка public/index.html + assets игнорируется Git и воспроизводится npm ci && npm run build. var/app.sqlite — локальные данные, не Git.

## Проверено

npm build; PHP syntax; domain assertions и API integration tests. В Codex Browser на http://127.0.0.1:8080 реально выполнены регистрация локального QA аккаунта, создание проекта, Hero, изменение текста, save revision 1, полная reload и восстановление текста/id; mobile canvas 375px; Grid insert → Undo → Redo → save revision 2; вторая страница Контакты с CTA → save revision 3. Responsive authoring проверен на 390×844 и 768×1024, desktop на 1280×800; browser Meta+Z/Meta+Shift+Z/Meta+S подтвердили Undo/Redo/save, включая защиту поля ввода. Тестовый локальный аккаунт и проект «QA — mobile authoring» оставлены в var/app.sqlite. Затем на изолированном сервере/SQLite выполнены autosave revisions 1–2, открытие истории и restore revision 1; серверная версия осталась неизменной спустя 1,8 с после восстановления. Локальный dev login `admin/admin` прошёл в существующей вкладке и сохранил сессию после reload; отдельная учётная запись пока без проектов, прежние QA-проекты оставлены у своих owners. Скриншоты базовых сценариев в docs/evidence/. Подробнее docs/TESTING.md. Playwright не устанавливался.

Hug/Fill и четыре min/max ограничения проверены в браузере на отдельной SQLite: ревизия 4 восстановила все значения после полного reload; Fill 100% проверен после сохранения и повторного открытия. Невалидный CSS-размер отклонён без записи. Evidence: docs/evidence/phase3-sizing-controls.jpg; подробности docs/TESTING.md.

Browser QA исправил init page event race и учёл служебный head в Core JSON. Реальный Project Data сохранён как fixture без пользовательских credentials. Ключи и cookies не записаны в repo.

Версия продукта 00.00.001: добавлены Navigation, Process, Testimonial, FAQ и Footer; в изолированной БД все секции вставились, автосохранились и восстановились после reload. На viewport 390×844 ширина canvas 375px, внешнего горизонтального переполнения нет. Проверки и детали в docs/TESTING.md.

Версия продукта 00.00.002: добавлена панель проектной темы с восемью цветовыми токенами, выбором шрифта и радиуса карточек. Значения хранятся в стилях wrapper каждой страницы, пресеты используют CSS variables с безопасными fallback-цветами, а стандартные значения старых секций мигрируют при применении темы. Проверено в Codex Browser на изолированной временной SQLite: тема применилась к ранее добавленному Hero, новой секции FAQ на второй странице, автосохранилась в ревизии 5 и восстановилась после возврата в dashboard и повторного открытия проекта. Подробности в docs/TESTING.md.

21.10.2026: в изолированном браузерном проекте проверены 9 из 10 секций в mobile 375 px и tablet 768 px; desktop отдельно проверен на FAQ. Горизонтального переполнения нет. Tab-навигация и видимый фокус работают. Полная clipboard проверка и Image preset остаются открытыми; Codex Browser в этой сессии не передал флаги Cmd/Ctrl для copy/paste. Подробности и точные пределы проверки — docs/TESTING.md.

Версия продукта 00.00.003: добавлен backend OpenRouter adapter и authenticated `POST /api/ai/design-plan` с фиксированной строгой JSON Schema, server-side secret, лимитом 1200 output tokens, timeout, ограниченным retry, redacted errors и usage/cost metadata. Endpoint только возвращает валидированный design plan, не меняя документ и revisions. `GET /api/ai/status` не раскрывает ключ. Реальный OpenRouter вызов не делался; UI, AI component operations, generation и save revision ещё не реализованы. Подробности: docs/AI_ENGINE.md, docs/TESTING.md.

## Ограничения / риски

Не готовый SaaS: нет email verification/recovery, срока бездействия session, production deployment, managed assets/upload, backup restore drill. Небольшой DB schema v1 auto-bootstrap, нет полноценного migration runner v2. SQLite для одного сервера, нагрузка не измерена. Core lazy chunk около 1.10 МБ raw / 291 КБ gzip; Vite предупреждает о размере. Свободный пользовательский JS/embed/CSS url запрещены. Панельный мобильный workflow проверен в браузерном viewport 390×844 и 768×1024. Дополнительная частичная responsive matrix: 9/10 секций на mobile 375 px и tablet 768 px, FAQ также на desktop; Image, touch на реальном устройстве, clipboard и полная keyboard/a11y матрица ещё не приняты.

AI chat, генерация/редактирование компонентов и сохранение AI revisions, MCP, Zero Canvas, advanced Auto Layout, publication/ZIP и custom domains ещё НЕ реализованы. Есть только OpenRouter design-plan API foundation; без ключа API остаётся не настроенным, реальный provider не проверен. JSON export не static export. Не называть весь MVP завершённым. Полный SBOM/CVE audit upstream не выполнен, audit статический, без exploit proof.

## Следующие задачи

1. Реализовано: schema/policy v1, негативные security fixtures и явный import/migration contract. Импорт JSON UI/API пока не реализован.
2. Реализованы Hug/Fill и min/max, десять секций и редактируемые global design tokens. Далее — полная keyboard/copy-paste QA и responsive-визуальная матрица соседних блоков перед интеграцией AI.
3. Реальный OpenRouter provider на сервере, pipeline и targeted ops, тесты ошибок без записи.
4. Static export/publication на отдельном origin и managed assets; затем scoped MCP transport с закрытым редактором. Подробный порядок в ROADMAP.md.
