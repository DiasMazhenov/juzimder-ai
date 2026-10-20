# AI Engine: запланированный контракт

Не реализован в 0.1. Ключи не запрашиваются, генерация не имитируется. OpenRouter — первый provider, не фиксированная модель. Перед implementation сверить текущий API. Ключ только на сервере, стоимость/лимиты/timeout/retry и resolved model в metadata, redact logs.

Pipeline: intent → design plan → структурные компоненты Core → Document validation → targeted application service operation с expected version → save revision. Local update не заменяет весь документ. Preview и подтверждение опасных изменений должны показывать реальный diff. Ошибка провайдера не меняет проект. Использовать схему v1, stable component ids и responsive styles; не HTML-first generator. Сначала расширить validator операциями и тестами допустимых/запрещённых изменений, затем подключать реальный provider. Критерий готовности — редактируемая секция и точечное изменение после server save/reload с browser evidence.
