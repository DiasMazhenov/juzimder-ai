# Juzimder AI

From idea to website. Платформа визуального создания сайтов на базе **GrapesJS Core**. Собственная оболочка редактора, PHP API и переносимое состояние проекта. Самостоятельный продукт, не официальный продукт GrapesJS.

**Версия продукта: 00.00.022 · Статус: foundation.** AI Engine поддерживает несколько редактируемых OpenRouter профилей с зашифрованными ключами и необязательной резервной моделью на временные сбои. AI-план создаёт текст для готовых секций и показывает его до подтверждения; применённые тексты остаются обычными редактируемыми компонентами. В редакторе есть библиотека изображений проекта: PNG/JPEG/GIF/WebP до 4 МБ на файл, общий лимит — 20 МБ и 200 изображений на проект. Файлы требуют авторизации владельца; ZIP экспортирует используемые изображения, публикация включает их в неизменяемый снимок. Preview показывает сохранённые изображения в sandboxed iframe. Также доступны страницы, глобальные стили, SEO и отдельный public origin; production DNS/TLS и custom domains пока не настроены. npm SemVer — 0.1.22, productVersion — 00.00.022; версия отображается в dashboard и editor.

Реализовано: локальная регистрация/вход, проекты с проверкой владельца, несколько страниц, десять структурных секций, глобальная тема проекта (восемь цветов, типографика и скругление) с сохранением в валидируемых настройках проекта и синхронизацией с Project Data, слои и инспектор стилей/свойств, Flex/Grid, desktop/tablet/mobile canvas, Undo/Redo, sandbox preview, debounce-автосохранение, история до 200 ревизий с безопасным восстановлением, защита от конфликта версий, JSON export и статический ZIP export.

AI foundation: серверный OpenRouter adapter и authenticated design-plan endpoint. Отдельный authenticated `POST /api/projects/{id}/operations` принимает ограниченный список typed operations (`create`, `update_content`, `update_styles`, `update_layout`, `delete`, `move`, `reorder`) и сохраняет каждое успешное применение новой CAS-ревизией. Удаление требует `confirm: true`; создание назначает стабильные component IDs. API-ключ, основная и резервная модели OpenRouter настраиваются в «Настройки сайта → AI Engine» конкретного проекта; ключ хранится сервером в зашифрованном виде и не входит в Project Data. В версии 00.00.022 исправлена ошибка параметра лимита/таймаута, live OpenRouter успешно создал preview; подтверждение применения и дальнейшее сохранение пока не проверялись. Также не реализованы targeted AI edits, MCP, Zero Canvas, custom domains и templates marketplace.

## Локальный запуск

PHP 8.2+ (PDO SQLite, DOM/libxml, mbstring, Zip), Node 22.12+ для сборки. Проверено на PHP 8.5.8 и Node 24.15.0.

```sh
npm ci
npm run build
npm run serve
```

После `npm run serve` приложение открывается по уникальному локальному адресу [http://juzimder-ai.localhost:8080](http://juzimder-ai.localhost:8080). Команда проверяет этот адрес: если Juzimder уже работает, повторный запуск сообщит URL и завершится без ошибки занятого порта; старые адреса `127.0.0.1:8080` и `localhost:8080` перенаправляются на него. Для локальной разработки команда включает loopback-only вход `admin/admin`; при первом входе создаётся отдельный тестовый аккаунт. Сервер остаётся привязан только к этому компьютеру; не включать dev login на публичном сервере. Локальные сессии изолируются по пути SQLite-базы, а после переключения базы устаревшая сессия сбрасывается с предложением войти снова. Данные — `var/app.sqlite` вне web root. API-ключ AI вводится только в «Настройки сайта → AI Engine» и не задаётся через переменные окружения. Локальный опубликованный сайт использует host вида `<project-id>.juzimder-ai.localhost:8080`; production требует wildcard DNS/TLS и конфигурации `JUZIMDER_PUBLIC_HOST_SUFFIX`. `JUZIMDER_DB` можно использовать для отдельной локальной базы; `.env.example` документирует только серверный путь к базе, dotenv не подключён. Для hot reload: параллельно `npm run dev`.

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
