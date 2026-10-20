# Juzimder AI

From idea to website. Платформа визуального создания сайтов на базе **GrapesJS Core**. Собственная оболочка редактора, PHP API и переносимое состояние проекта. Самостоятельный продукт, не официальный продукт GrapesJS.

**Версия продукта: 00.00.009 · Статус: foundation.** В проектном dashboard можно управлять страницами, их URL и главной страницей. Цвета, шрифты заголовков и основного текста, веса, размеры H1–H6, интервалы и скругления проходят серверную валидацию и сохраняются в ревизиях проекта. Редактор и AI-секции используют глобальные токены, а явные локальные стили элементов сохраняют приоритет. Публичная публикация, HTML/CSS export, SEO metadata injection и домены ещё не реализованы. npm-поле `version` сохраняет SemVer-формат (`0.1.9`), а `productVersion` задаёт номер продукта; актуальная версия показывается в dashboard и editor.

Реализовано: локальная регистрация/вход, проекты с проверкой владельца, несколько страниц, десять структурных секций, глобальная тема проекта (восемь цветов, базовый шрифт и скругление) с сохранением в валидируемых настройках проекта и синхронизацией с Project Data, слои и инспектор стилей/свойств, Flex/Grid, desktop/tablet/mobile canvas, Undo/Redo, sandbox preview, debounce-автосохранение, история до 200 ревизий с безопасным восстановлением, защита от конфликта версий и JSON export.

AI foundation: серверный OpenRouter adapter и authenticated design-plan endpoint. Отдельный authenticated `POST /api/projects/{id}/operations` принимает ограниченный список typed operations (`create`, `update_content`, `update_styles`, `update_layout`, `delete`, `move`, `reorder`) и сохраняет каждое успешное применение новой CAS-ревизией. Удаление требует `confirm: true`; создание назначает стабильные component IDs. Эти API пока не подключены к AI Chat или редактору. Для реального вызова OpenRouter серверу нужны `OPENROUTER_API_KEY` и `OPENROUTER_MODEL`. Также не реализованы MCP, Zero Canvas, upload/media storage, static publication/ZIP, custom domains и templates marketplace.

## Локальный запуск

PHP 8.2+ (PDO SQLite, DOM/libxml, mbstring), Node 22.12+ для сборки. Проверено на PHP 8.5.8 и Node 24.15.0.

```sh
npm ci
npm run build
npm run serve
```

Открыть [localhost:8080](http://127.0.0.1:8080). Для локальной разработки `npm run serve` включает loopback-only вход `admin/admin`; при первом входе создаётся отдельный тестовый аккаунт. Этот режим не включать на публичном сервере. Также можно зарегистрироваться, создать проект, добавить Hero, выбрать текст на холсте и изменить его, сохранить, перезагрузить и снова открыть проект. Данные — var/app.sqlite вне web root. Ключи и внешние сервисы для редактора не нужны; при вызове AI plan prompt отправляется в OpenRouter только если серверный ключ настроен. Настройка DB и AI задаётся в окружении PHP; .env.example документирует переменные, dotenv не подключён. Для hot reload: параллельно `npm run dev`.

```sh
npm test
```

Стек: GrapesJS 0.23.8, Vite 8.3.4, browser JS/CSS, native PHP/PDO SQLite. Node не требуется на PHP hosting после сборки. Backend и SQLite расположены вне public. Текущая версия предназначена для локальной работы; перед публичным размещением пройти [security checklist](docs/SECURITY.md) и [deployment guide](docs/DEPLOYMENT.md).

## Документация

- [Контекст](CONTEXT.md), [план](ROADMAP.md), [инструкции](AGENTS.md).
- [Аудит PHP-GrapesJS](docs/PHP_GRAPESJS_AUDIT.md), [решение A/B/C](docs/ARCHITECTURE_DECISION.md).
- [Архитектура](docs/ARCHITECTURE.md), [модель](docs/DATA_MODEL.md), [схема](schemas/project.schema.json).
- [Проверки](docs/TESTING.md), [безопасность](docs/SECURITY.md), [AI](docs/AI_ENGINE.md), [MCP](docs/MCP.md).

Лицензия собственного кода ожидает выбора владельца; пока разрешение на распространение исходников не объявлено. Условия зависимостей — THIRD_PARTY_NOTICES.md. Сторонняя CMS не копировалась.
