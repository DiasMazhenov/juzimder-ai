# Juzimder AI — контекст проекта

Обновлено 20.10.2026. Цель: самостоятельная платформа визуального создания адаптивных сайтов с AI и будущим MCP. Основной репозиторий: https://github.com/DiasMazhenov/juzimder-ai . В начале папка не содержала файлов/Git, git ls-remote не вернул refs. Инициализирован main, origin настроен; push не выполнялся.

## Принятые решения

- Phase 0: статический аудит PHP-GrapesJS commit 4a464cc3edf54db3ddacdad4b3ddab5bcf7b8589. Подробности в docs/PHP_GRAPESJS_AUDIT.md. Fork отклонён из-за persistence/auth/DB/deployment/licensing проблем.
- Вариант C: собственный PHP 8.2+ API, PDO SQLite, browser JS/CSS, Vite build. PHP hosting без Node runtime. GrapesJS Core 0.23.8, без Studio SDK/форка.
- Каноническая модель: schemaVersion=1, engine=grapesjs, data=getProjectData. Сохраняем pages/frames/components/styles/assets/metadata, не отдельное HTML дерево. Структурная схема в schemas/, authoritative policy в Document validator.
- Project объединяет workspace/site для первого среза. Owner проверяется сервером. Mutations: session + CSRF + validation + expected version.
- Лицензия собственного продукта ожидает выбора владельца. Исходники PHP-GrapesJS не переносились. Notices Core сохранены.

## Реализовано

Auth регистрация/вход/выход, password hashing/session rotation/CSRF/rate limit. Dashboard: список и создание проектов. Несколько страниц в одном документе. Editor: библиотека структурных секций, слои, traits и style inspector, Flex/Grid, responsive devices, Undo/Redo, текст, изображения по HTTPS URL, preview sandbox, JSON export. Сервер хранит полный document и до 200 транзакционных revisions. Schema v1 уточнена и согласована с runtime policy; malformed/security JSON fixtures проверяются до сохранения. Автосохранение с debounce 1,4 с; CAS 409 при параллельной записи предлагает экспорт или загрузку серверного состояния. История последних 50 ревизий в UI; восстановление пишет старое состояние в новую ревизию и не стирает текущую историю. Выход из редактора сначала пытается сохранить изменения.

Рабочая структура: src/, backend/Domain.php, backend/api.php, backend/migrations/, public/router.php, schemas/, tests/, docs/. Не создавать пустые пакеты для будущих функций. Production сборка public/index.html + assets игнорируется Git и воспроизводится npm ci && npm run build. var/app.sqlite — локальные данные, не Git.

## Проверено

npm build; PHP syntax; domain assertions и API integration tests. В Codex Browser на http://127.0.0.1:8080 реально выполнены регистрация локального QA аккаунта, создание проекта, Hero, изменение текста, save revision 1, полная reload и восстановление текста/id; mobile canvas 375px; Grid insert → Undo → Redo → save revision 2; вторая страница Контакты с CTA → save revision 3. Затем на изолированном сервере/SQLite выполнены autosave revisions 1–2, открытие истории и restore revision 1; серверная версия осталась неизменной спустя 1,8 с после восстановления. Скриншоты в docs/evidence/. Подробнее docs/TESTING.md. Playwright не устанавливался.

Browser QA исправил init page event race и учёл служебный head в Core JSON. Реальный Project Data сохранён как fixture без пользовательских credentials. Ключи и cookies не записаны в repo.

## Ограничения / риски

Не готовый SaaS: нет email verification/recovery, срока бездействия session, production deployment, managed assets/upload, backup restore drill. Небольшой DB schema v1 auto-bootstrap, нет полноценного migration runner v2. SQLite для одного сервера, нагрузка не измерена. Core lazy chunk около 1.10 МБ raw / 291 КБ gzip; Vite предупреждает о размере. Свободный пользовательский JS/embed/CSS url запрещены. Inspector скрывается при узком viewport; мобильный authoring отдельно не принят. Проверена ширина mobile canvas, не реальное мобильное устройство.

AI/OpenRouter, MCP, Zero Canvas, advanced Auto Layout, publication/ZIP, custom domains ещё НЕ реализованы. JSON export не static export. Не называть весь MVP завершённым. Полный SBOM/CVE audit upstream не выполнен, audit статический, без exploit proof.

## Следующие задачи

1. Реализовано: schema/policy v1, негативные security fixtures и явный import/migration contract. Импорт JSON UI/API пока не реализован.
2. Стабилизировать block library, keyboard/accessibility и responsive authoring; расширить визуальную матрицу.
3. Реальный OpenRouter provider на сервере, pipeline и targeted ops, тесты ошибок без записи.
4. Static export/publication на отдельном origin и managed assets; затем scoped MCP transport с закрытым редактором. Подробный порядок в ROADMAP.md.
