# Запуск и размещение

Требования: PHP 8.2+ с PDO SQLite, DOM/libxml, mbstring; Node 22.12+ для Vite 8 (проверено Node 24.15.0, PHP 8.5.8). Node нужен только для сборки.

```
npm ci
npm run build
npm run serve
```

Открыть http://127.0.0.1:8080 . Создать локальный аккаунт, проект, добавить Hero, изменить текст, сохранить, перезагрузить, открыть проект снова. Demo login/пароля нет. Регистрация реальная локальная. SQLite автоматически создаётся в var/app.sqlite, schema v1 применяется при первом API запросе.

Разработка: в первом терминале `npm run serve`, во втором `npm run dev`; Vite proxy /api → localhost:8080. Production build используется через PHP. Vite dev server не размещать публично.

`JUZIMDER_DB` — полный путь SQLite, задаётся окружением PHP (пример в .env.example, автоматического dotenv нет). Каталог writable только PHP service user; DB вне public. Для Apache 2.4 включить mod_rewrite и AllowOverride для public/.htaccess; document root строго public/. Для Nginx разрешить PHP execution только router.php и направить /api/* в него; другие PHP пути запретить. В production использовать PHP-FPM/Apache, не встроенный php -S. Собирать public/assets локально/в CI, отдавать assets как статические файлы с nosniff. HTTPS обязателен для production cookies. Публичное размещение в этой задаче не выполнено.

Для AI endpoint (необязательно) задайте `OPENROUTER_API_KEY` и `OPENROUTER_MODEL` в окружении PHP-FPM/service, `OPENROUTER_TIMEOUT_SECONDS` необязателен. Секрет не хранить в web root, `.env.example`, репозитории, Project Data или frontend. `.env.example` документирует имена переменных, но файл не загружается автоматически. Без ключа `/api/ai/status` возвращает `configured:false`, а design-plan endpoint не отправляет запрос и отвечает 503. Владелец должен выбрать модель, поддерживающую OpenRouter structured outputs; настройка ключа включает отправку введённого пользователем prompt в OpenRouter. Пока нет UI consent, лимитов расходов и production user-level quota, поэтому не включайте ключ на публичном многопользовательском сервере.

Backup: консистентный SQLite backup (SQLite backup API либо остановленный writer) и соответствующие документы/asset storage. Во время работающих writer нельзя считать обычный copy достаточным. Restore drill ещё не выполнен. Автомиграция только v1; для следующей версии нужен явный migration runner и backup до upgrade.
