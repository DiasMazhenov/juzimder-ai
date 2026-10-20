# Juzimder AI

From idea to website. Платформа визуального создания сайтов на базе **GrapesJS Core**. Собственная оболочка редактора, PHP API и переносимое состояние проекта. Самостоятельный продукт, не официальный продукт GrapesJS.

**Статус: foundation 0.1.0.** Первый вертикальный срез работает; полноценный MVP с AI и публикацией ещё не готов.

Реализовано: локальная регистрация/вход, проекты с проверкой владельца, несколько страниц, структурные Hero/Features/CTA/Text/Image, слои и инспектор стилей/свойств, Flex/Grid, desktop/tablet/mobile canvas, Undo/Redo, sandbox preview, серверное сохранение полного Project Data, ревизии, защита от конфликта версий, JSON export.

Не реализовано: OpenRouter/AI chat, MCP, Zero Canvas, upload/media storage, static publication/ZIP, custom domains, templates marketplace, autosave, revision restore UI. Интерфейс честно обозначает AI как следующий этап.

## Локальный запуск

PHP 8.2+ (PDO SQLite, DOM/libxml, mbstring), Node 22.12+ для сборки. Проверено на PHP 8.5.8 и Node 24.15.0.

```sh
npm ci
npm run build
npm run serve
```

Открыть [localhost:8080](http://127.0.0.1:8080). Зарегистрироваться, создать проект, добавить Hero, выбрать текст на холсте и изменить его, сохранить, перезагрузить и снова открыть проект. Данные — var/app.sqlite вне web root. Ключей и внешних сервисов не требуется. Настройка DB через `JUZIMDER_DB` в окружении процесса; .env.example поясняет переменную, dotenv не подключён. Для hot reload: параллельно `npm run dev`.

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
