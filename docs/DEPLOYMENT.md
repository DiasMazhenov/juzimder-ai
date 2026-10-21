# Запуск и размещение

Требования: PHP 8.2+ с PDO SQLite, DOM/libxml, mbstring и Zip extension для скачивания статических архивов; Node 22.12+ для Vite 8 (проверено Node 24.15.0, PHP 8.5.8). Node нужен только для сборки.

Для сохранения OpenRouter credentials также нужна PHP Sodium extension (`sodium_crypto_secretbox`). Без неё AI Engine сообщит, что защищённое хранилище недоступно; остальные функции редактора остаются доступны.

```
npm ci
npm run build
npm run serve
```

Открыть http://juzimder-ai.localhost:8080 . Старые ссылки `127.0.0.1:8080` и `localhost:8080` перенаправляются на канонический hostname. Создать локальный аккаунт, проект, добавить Hero, изменить текст, сохранить, перезагрузить, открыть проект снова. Demo login/пароля нет. Регистрация реальная локальная. SQLite автоматически создаётся в var/app.sqlite; текущие миграции применяются при первом API запросе.

Разработка: в первом терминале `npm run serve`, во втором `npm run dev`; Vite proxy `/api` направляет запросы на PHP loopback `127.0.0.1:8080`. Основной стабильный адрес приложения — `http://juzimder-ai.localhost:8080/`. Production build используется через PHP. Vite dev server не размещать публично.

`JUZIMDER_DB` — полный путь SQLite, задаётся окружением PHP (пример в .env.example, автоматического dotenv нет). Каталог writable только PHP service user; DB вне public. Для Apache 2.4 включить mod_rewrite и AllowOverride для public/.htaccess; document root строго public/. Для Nginx разрешить PHP execution только router.php и направить /api/* в него; другие PHP пути запретить. В production использовать PHP-FPM/Apache, не встроенный php -S. Собирать public/assets локально/в CI, отдавать assets как статические файлы с nosniff. HTTPS обязателен для production cookies. Публичное размещение в этой задаче не выполнено.

OpenRouter настраивается после входа в конкретный проект: «Настройки сайта → AI Engine» принимает API-ключ и ID модели. Ключ не задаётся в окружении, не отправляется обратно браузеру и хранится как ciphertext в SQLite; шифровальный ключ приложения создаётся рядом с базой в файле `<путь-к-базе>.ai-key` с правами `0600`. Каталог базы и файл ключа должны находиться вне `public/` и быть доступны только PHP service user. Для backup/restore сохраняйте SQLite и `.ai-key` как согласованную пару в защищённом хранилище. Без проекта и сохранённой конфигурации scoped AI endpoint отвечает 503. Реальный вызов OpenRouter требует модели с поддержкой structured outputs; явное действие пользователя отправляет только prompt.

В редакторе «ZIP сайта» скачивает статические HTML/CSS всех сохранённых страниц, SEO и design tokens. Архив извлекается и размещается на статическом хостинге с document root на его корне. Приложение пока не выполняет online publish, не раздаёт экспорт с отдельного origin и не подключает домены; не направляйте публичный трафик в административный PHP origin.

Backup: консистентный SQLite backup (SQLite backup API либо остановленный writer) и соответствующие документы/asset storage. Во время работающих writer нельзя считать обычный copy достаточным. Restore drill ещё не выполнен. Автомиграция только v1; для следующей версии нужен явный migration runner и backup до upgrade.
