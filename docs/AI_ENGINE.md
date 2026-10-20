# AI Engine

## Реализовано в версии 00.00.003

Первый backend-шаг — негенерирующий изменений проектный план. API принимает `POST /api/ai/design-plan` с `{ "prompt": "..." }`, требует входа и CSRF-токен, ограничивает текст 4000 символами и отвечает структурированным планом без изменения документа, страницы или ревизии. `GET /api/ai/status` сообщает только, настроены ли provider и модель; он не проверяет доступность OpenRouter и никогда не возвращает ключ.

Провайдер OpenRouter отправляет только текст пользовательского запроса. Текущий Project Data, выбранный компонент и история в модель не передаются. При вызове запроса текст уходит на OpenRouter; будущая AI-панель должна явно объяснить это пользователю до первого запроса.

Ответ запрашивается через Chat Completions API с `response_format.type=json_schema`, строгой схемой, `provider.require_parameters=true` и `usage.include=true`. Сервер повторно валидирует язык/длины полей, восемь цветов, уникальность разрешённых секций и обязательный Hero. HTML, CSS, JavaScript и произвольные URL не принимаются. Результат содержит resolved model, токены и стоимость, если провайдер прислал их.

Параметры берутся только из окружения процесса PHP:

- `OPENROUTER_API_KEY` — секрет; хранить вне репозитория, Project Data, frontend и логов.
- `OPENROUTER_MODEL` — модельный slug, выбранный владельцем сервера.
- `OPENROUTER_TIMEOUT_SECONDS` — таймаут 5–60 секунд, по умолчанию 25.

Один запрос ограничен 1200 выходными токенами и 1 МБ ответа; на одну PHP-сессию допускается до пяти вызовов за минуту. Это защита от случайного повторения, не квота на аккаунт или денежный бюджет. Перед публичным запуском нужна серверная per-user/per-project квота и мониторинг расходов. Провайдер повторяет только один явный transient HTTP-ответ из ограниченного набора; сетевые сбои, ошибки авторизации и невалидный результат не повторяются.

Для JSON Schema endpoint должен поддерживать structured outputs. При `require_parameters=true` маршрутизатору запрещено молча отправлять запрос endpoint без этого параметра; ошибка возвращается без изменения проекта. Форма `response_format` и `max_completion_tokens` сверена с [OpenRouter Chat Completions API](https://openrouter.ai/docs/api/api-reference/chat/send-chat-completion-request), строгие structured outputs — с [OpenRouter Structured Outputs](https://openrouter.ai/docs/structured-outputs). Для token/cost metadata запрос включает `usage.include`; формат проверен по [OpenRouter usage announcement](https://openrouter.ai/blog/announcements/smarter-charts-inline-svgs-and-live-usage-accounting/).

## Ограничения следующего этапа

Реальный вызов OpenRouter пока не выполнен: локальный API-ключ не настроен, поэтому ни один пользовательский текст не отправлялся провайдеру. Юзерский UI/AI Chat, получение списка моделей, лимит расходов, история диалога и передача контекста проекта ещё не реализованы. Отдельный typed-operation endpoint появился в версии 00.00.004, но генератор его пока не вызывает.

## Typed operations · версия 00.00.004

`POST /api/projects/{id}/operations` принимает 1–20 typed operations и `version`. Поддерживаются create, update content/styles/layout, delete, move и reorder. Сервер назначает стабильные ID новым деревьям компонентов, проверяет владельца и ожидаемую версию, после каждой операции валидирует весь Project Data и сохраняет успешную пачку стандартной CAS-ревизией. Delete требует `confirm: true`. Duplicate отложен: для него нужно согласованно переназначить ID, якорные ссылки и глобальные style selectors.

Этот endpoint пока не соединён с design planner или редактором. Перед AI integration нужны preview/diff, явное подтверждение пользователя, а затем реальное сохранение и browser reload verification; наличие `confirm: true` само по себе не доказывает согласие пользователя.

Следующий технический этап: передавать plan в structured generation и typed operations через общий application service, сохранив preview/confirmation до любой AI mutation.
