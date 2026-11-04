# Запуск и размещение

Требования: PHP 8.2+ с PDO SQLite, DOM/libxml, mbstring, Fileinfo для проверки upload MIME и Zip extension для скачивания статических архивов; Node 22.12+ для Vite 8 (проверено Node 24.15.0, PHP 8.5.8). Node нужен только для сборки.

Для сохранения OpenRouter credentials также нужна PHP Sodium extension (`sodium_crypto_secretbox`). Без неё AI Engine сообщит, что защищённое хранилище недоступно; остальные функции редактора остаются доступны.

```
npm ci
npm run build
npm run serve
```

Открыть http://juzimder-ai.localhost:8080 . Старые ссылки `127.0.0.1:8080` и `localhost:8080` перенаправляются на канонический hostname. Создать локальный аккаунт, проект, добавить Hero, изменить текст, сохранить, перезагрузить, открыть проект снова. Demo login/пароля нет. Регистрация реальная локальная. SQLite автоматически создаётся в var/app.sqlite; текущие миграции применяются при первом API запросе.

Разработка: в первом терминале `npm run serve`, во втором `npm run dev`; Vite proxy `/api` направляет запросы на PHP loopback `127.0.0.1:8080`. Основной стабильный адрес приложения — `http://juzimder-ai.localhost:8080/`. Production build используется через PHP. Vite dev server не размещать публично.

`JUZIMDER_DB` — полный путь SQLite, задаётся окружением PHP (пример в .env.example, автоматического dotenv нет). Каталог writable только PHP service user; DB вне public. Для Apache 2.4 включить mod_rewrite и AllowOverride для public/.htaccess; document root строго public/. Для Nginx разрешить PHP execution только router.php и направить /api/* в него; другие PHP пути запретить. В production использовать PHP-FPM/Apache, не встроенный php -S. Собирать public/assets локально/в CI, отдавать assets как статические файлы с nosniff. HTTPS обязателен для production cookies. Реальный production deployment и DNS/TLS acceptance не выполнялись.

Если TLS завершается на reverse proxy, приложение принимает `X-Forwarded-Proto` и `X-Forwarded-For` только от непосредственных proxy IP из `JUZIMDER_TRUSTED_PROXY_IPS` (список точных IP, максимум 32, без CIDR). Пустая или некорректная настройка означает отсутствие доверия forwarded-заголовкам. Настрой каждый доверенный proxy так, чтобы он удалял клиентские значения этих заголовков и выставлял собственный однозначный `X-Forwarded-Proto` (`http` или `https`) и корректную цепочку `X-Forwarded-For`; не добавляй в allowlist адреса клиентов или общие подсети.

OpenRouter настраивается после входа в конкретный проект: «Настройки сайта → AI Engine» позволяет хранить несколько профилей с отдельными API-ключами и моделями, редактировать их и выбирать активный профиль. Ключи не задаются в окружении, не отправляются обратно браузеру и хранятся как ciphertext в SQLite; шифровальный ключ приложения создаётся рядом с базой в файле `var/app.sqlite.ai-key` с правами 0600. Пароль SMTP хранится в зашифрованном виде в SQLite, отдельный ключ — в `var/app.sqlite.forms-key`. Оба sidecar-ключа должны быть доступны только PHP service user. Для системного backup/restore сохраняйте SQLite и оба sidecar-ключа как согласованный набор в защищённом хранилище. Экспорт проекта через интерфейс намеренно не содержит API-ключей и SMTP-паролей; их надо настроить заново после импорта. Без активного профиля scoped AI endpoint отвечает 503. Реальный вызов OpenRouter требует модели с поддержкой structured outputs; явное действие пользователя отправляет только prompt.

В редакторе «ZIP сайта» скачивает статические HTML/CSS всех сохранённых страниц, SEO, design tokens и используемые растровые изображения. Архив извлекается и размещается на статическом хостинге с document root на его корне. Для локальной публикации dashboard создаёт URL вида `<project-id>.juzimder-ai.localhost:8080`. Production public origin настраивается отдельно от admin origin: задайте `JUZIMDER_PUBLIC_HOST_SUFFIX` на wildcard-host suffix (например, `sites.example.com`), направьте `*.sites.example.com` через DNS/reverse proxy на тот же PHP router и установите wildcard TLS certificate. Схема по умолчанию `https` для нелокального suffix; `JUZIMDER_PUBLIC_SCHEME` задаёт `http` или `https` явно. Проверьте, что admin hostname не совпадает с public wildcard. Custom domains пока не поддерживаются; пользовательский домен не подключать до отдельной проверки DNS/TLS и маршрутизации.

Backup: консистентный SQLite backup (SQLite backup API либо остановленный writer), включая `project_assets`, `publication_versions` и `project_publications`; source image BLOBs, AI encryption sidecar и публикационные snapshots должны резервироваться вместе. Во время работающих writer нельзя считать обычный copy достаточным. Restore drill ещё не выполнен. Миграции применяются последовательно до schema v6; перед production upgrade всё равно нужен backup/restore drill.

## Shared hosting / ByetHost

Для Apache shared hosting собери переносимый ZIP из корня репозитория:

```sh
npm ci
npm run package:shared-hosting
```

Архив появится в `build/juzimder-ai-shared-hosting.zip`. Он содержит production bundle, PHP backend и runtime resources, root `router.php` с правильными путями для распакованного document root, совместимый `.htaccess` и правила запрета прямого доступа к `backend/`, `resources/`, `schemas/`, `var/` и Composer `vendor/`. Видимый `htaccess.txt` — запасная копия для файловых менеджеров, пропускающих скрытые файлы. База и секреты из локального `var/` в архив не попадают. Если checkout содержит `composer.json`, для повторяемой сборки также обязателен `composer.lock`; упаковщик ставит зафиксированные production-зависимости без Composer scripts/plugins и включает `vendor/`.

На ByetHost распакуй архив прямо в document root домена (в этой установке это `/htdocs`). Проверь наличие скрытого `.htaccess`; если файловый менеджер его пропустил, переименуй `htaccess.txt` в `.htaccess`. Выставь PHP 8.2+; для обычной работы приложению нужна запись в `var/`, а для автообновления PHP также должен иметь возможность заменять файлы приложения в корне домена. Проверь `PDO SQLite`, `Fileinfo`, `DOM/libxml`, `mbstring`, `Sodium`, `OpenSSL` и `ZIP`, а Apache — `mod_rewrite` и `AllowOverride`. Автообновление дополнительно требует исходящие HTTPS-запросы к GitHub и PHP cURL либо `allow_url_fopen`. Для входа используй HTTPS. Открой корень сайта, создай собственную учётную запись и проверь, что `/api/session` отвечает, а `/api/projects` без входа требует авторизацию. Первый аккаунт этой установки получает управление обновлениями. Не загружай локальную базу, `.env`, sidecar-ключи или session-файлы через FTP.

Установочная ошибка, обнаруженная при первой распаковке на ByetHost: вместо приложения Apache показывал стандартную страницу ошибки сразу после извлечения ZIP. В загруженном `.htaccess` были директивы `Options`/`SetEnv` и незащищённые правила переписывания; по одному точному директиву причину не изолировали. После удаления host-incompatible директив и оборачивания rewrite правил в проверку `mod_rewrite` сайт загрузился. Общая `public/.htaccess` и новая flat-root упаковка теперь не требуют `Options` или `SetEnv`, а упаковщик явно формирует совместимый корневой router. Если снова появится HTTP 500, сначала проверь, что File Manager извлёк скрытый `.htaccess`, что в доменном корне нет старого конфликтующего файла и что включён `mod_rewrite`; не удаляй защитные правила приватных директорий.

Если архив содержит `INSTALL.txt`, его требования относятся к конкретной сборке. Перед повторным извлечением поверх уже работающего сайта сохрани отдельную копию серверной `var/` целиком, включая `app.sqlite`, `app.sqlite.ai-key` и `app.sqlite.forms-key`; распаковка поверх существующих файлов может затронуть данные.

### Обновление из GitHub

На вкладке проектов первый созданный аккаунт может проверить стабильный GitHub Release и установить его. Updater скачивает только `juzimder-ai-shared-hosting.zip` из репозитория `DiasMazhenov/juzimder-ai`, проверяет версию, SHA-256 и список допустимых путей; произвольные ветки и ZIP по пользовательскому URL не принимаются. Перед заменой кода updater делает консистентную SQLite-копию через `VACUUM INTO` и сохраняет рядом sidecar-ключи шифрования. Резервные копии хранятся в закрытом `var/updates/`, последние две сохраняются; сам `var/` при установке не заменяется.

Первую установку нужно загрузить вручную. После слияния проверенного изменения в `main` создай тег версии в формате `v00.00.333`, совпадающий с `productVersion` в `package.json`, и отправь его в GitHub. Workflow проверит, что коммит уже входит в `main`, соберёт ZIP и SHA-256 и создаст стабильный Release. Не создавай тег до проверки/слияния PR: updater устанавливает только опубликованные стабильные версии.
