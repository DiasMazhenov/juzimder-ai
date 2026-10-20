# Технический аудит PHP-GrapesJS

Дата: 20.10.2026. Источник: https://github.com/pepiuox/PHP-GrapesJS . Проверен commit `4a464cc3edf54db3ddacdad4b3ddab5bcf7b8589` (shallow clone в /private/tmp, без установки и исполнения его кода). Статический аудит конкретных путей, не пентест и не доказательство отсутствия остальных дефектов. Код не переносился.

## Архитектура и пригодность

| Область | Наблюдение по исходникам | Последствие |
|---|---|---|
| Backend | core/controller, core/managers, core/pages, build; PHP templates и прямые endpoint-файлы | Нет единого обязательного application-service слоя |
| Routing | Router.php, Routers.php, RouterOptimized.php; Router.php содержит роли и проверки URI | Несколько реализаций; наличие защищённого router не защищает прямой PHP endpoint |
| DB | core/config/dbconnection.php:20–22 выдаёт PDO, managers/save.php:14 вызывает bind_param | Реальный save endpoint несовместим с текущим DB connector |
| Контроллеры и модели | PageManager, PageRepository, PageController; несколько моделей пользователя и авторизации | Нужна консолидация, а не простая замена интерфейса |
| Auth | controller/UserManager.php:20–43 использует prepared SELECT и password_verify; controller/User.php использует bcrypt | Современное хеширование есть, но нет основания считать все альтернативные login paths эквивалентными |
| Роли | Auth.php:245–266 и RoleManager предлагают роли/permissions/canEditPage | Проверка не является обязательной частью каждого метода записи |
| Страницы | PageManager.php:19–44 создаёт страницы с user_id | Создание страницы есть, полноценного workspace/site aggregate в проверенном пути нет |
| Хранение редактора | managers/builder.php:254 отключает Storage Manager; :1035–1040 отправляет getHtml/getCss в save.php | Полный getProjectData не сохраняется; metadata, pages, traits и стабильный round trip не гарантированы |
| Схема | installer/sql/db.sql:2442–2477: pages с content/style; PageManager:25–30 и builder:68 ожидают другие поля | Installer schema и runtime расходятся; SQL dump не заменяет версионные миграции |
| Файлы | FileManager.php имеет MIME/extension checks и random filename; параллельно существуют gallery.php и elFinder | Несколько независимых периметров безопасности |
| Публикация | PagePublic.php рендерит PHP layout/includes; builder хранит HTML/CSS | Это CMS-рендер, не изолированный static publication service с версиями |
| API/MCP | Прямые endpoints под UI; стабильный версионный API и MCP transport в проверенных путях не обнаружены | Для Node/MCP требуется новая граница сервисов, tenancy и API auth |
| Несколько сайтов | Пользователи и user_id страниц существуют; глобальные site_settings/configuration | Наличие user_id не доказывает изоляцию многих сайтов одного пользователя |
| Админка | PHP views и большой inline JS builder, множество bundled plugins | Расширяется изменением templates; высокий риск связности и регрессий |
| Deployment | .htaccess:21 безусловный RewriteRule .* - [F,L] | Текущая конфигурация Apache блокирует запросы до маршрутизации |

## Security findings

Ссылки ниже относятся к [зафиксированному commit](https://github.com/pepiuox/PHP-GrapesJS/tree/4a464cc3edf54db3ddacdad4b3ddab5bcf7b8589), номера строк — его исходникам.

1. **Высокий: отсутствие обязательной object authorization.** `core/managers/save.php:6–16` принимает idp/tbl/content/style и выполняет UPDATE; отсутствуют login, owner predicate, CSRF. Подключаемый dbconnection.php создаёт соединение и сессию, но не отклоняет анонимного пользователя. `PageManager.php:56–105` update/delete/get фильтрует по id без owner. Auth.canEditPage существует отдельно и не вызывается этими методами. Эксплуатируемость save сейчас дополнительно ограничена PDO/mysqli поломкой — это не security boundary.
2. **Высокий: SQL identifier injection surface.** `save.php:8,12`: клиентский tbl интерполируется в SQL без whitelist. Параметры контента не защищают имя таблицы. Динамическую эксплуатацию на MySQL не выполняли; многозапросное выполнение не утверждается.
3. **Высокий: upload без авторизации.** `managers/upload_image.php:23–28`: login check закомментирован. Размер, MIME, расширения, getimagesize и random name реализованы; это снижает риск исполнения файлов, но не предотвращает анонимное потребление диска и общий доступ к gallery. Заявление комментария о полном предотвращении PHP polyglot неверно: запрет PHP execution в upload directory всё равно нужен.
4. **Высокий: stored active HTML в административном origin.** `builder.php:213–219` выводит decodeContent HTML и CSS прямо в DOM перед init(fromElement). Нужны sanitizer, policy scripts и sandbox; в этом пути их нет. Само сохранение произвольного HTML не всегда баг, но его рендер внутри доверенного origin опасен.
5. **Средний: reflected XSS.** `core/pages/check_logs/verify.php:31–33` помещает GET id/code/hash в HTML value без escaping. Достижимость зависит от routing и server config; браузерный exploit не запускался.
6. **Высокий при экспонировании connector:** `filemanager/php/connector.minimal.php:1–6,139–146` подключает path/autoload, задаёт общий uploads, не требует CMS auth; access callback скрывает dotfiles, а не проверяет owner. MIME allowlist присутствует. Ограничения конкретной инсталляции не проверялись.
7. **Требует исправления: PHP include/path boundary.** `PagePublic.php:78–102`: realpath + prefix strpos без проверки separator допускает совпадение sibling-prefix. Нужен root + DIRECTORY_SEPARATOR; контролируемость path_file из публичного запроса не установлена. include PHP существует, доказанного исполнения загруженного пользователем PHP не получено.
8. **Sessions/CSRF:** в отдельных модулях есть защитные настройки (upload_image) и CSRF (UsersNotes, installer), но dbconnection.php просто session_start. Нет подтверждения единой cookie/rotation/expiry policy для всех auth implementations. Недопустимо объявлять CSRF «полностью отсутствующим» во всём проекте — дефект в конкретных write paths.
9. **Секреты:** .env.example и генерация PHP-конфига installer присутствуют; installer пишет исполняемые config files. Значения секретов не сохранялись. Полный secret-history scan и проверка server document root не выполнялись.

## Зависимости и лицензии

- Корневой LICENSE: BSD-3-Clause style с copyright Jose Mantilla и Artur Arseniev; composer.json: `MIT`. Это расхождение не разрешено автором; нельзя автоматически применять MIT ко всему дереву.
- package.json не содержит license и зависит от собственного `php-grapesjs: file:`; npm manifest не описывает фактический состав bundled JS.
- composer.json: PHP ^8.2, phpdotenv v5.6.4, php-parser v5.8.0; dev Psalm/PHPStan/PHPUnit/PHPCS/php-cs-fixer/phpactor. Корневого composer.lock в снимке нет. Их установка/совместимость не проверялась.
- Bundled elFinder 2.1.65: BSD-3-Clause (`core/filemanager/LICENSE.md`); PHPMailer: LGPL-2.1 (`core/PHPMailer/LICENSE`); CKEditor LICENSE содержит коммерческие условия LTS 4.23+ и альтернативные условия для <=4.22. Нужна идентификация конкретных файлов/версий перед reuse.
- Vendored Bootstrap/jQuery/UI/Font Awesome/CKEditor/GrapesJS plugins не исчерпываются Composer/npm. Полный SBOM/CVE scan и правовой аудит **NOT RUN**. Не заявляем отсутствие известных CVE. Установка неполного npm manifest не дала бы достоверного security audit всего CMS.
- Для Juzimder установлен отдельно GrapesJS Core 0.23.8 (BSD-3-Clause), Vite 8.3.4 (MIT). Условия Core сохранены в THIRD_PARTY_NOTICES.md. Код PHP-GrapesJS не заимствован.

## Вывод

Fork потребует переработки persistence, auth, tenancy, deployment и dependency inventory одновременно. Selective reuse кода тоже не даёт выигрыша до выяснения лицензий. Рекомендуется новый PHP backend с отдельной моделью document/version и Core editor без модификации ядра. Дальнейшая архитектура — ARCHITECTURE_DECISION.md.
