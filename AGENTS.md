# Инструкции Juzimder AI

- Все задачи начинать с «Бисмиллях»; общаться по-русски, код/идентификаторы на английском. Не соглашаться автоматически: аргументировать исправления и решения.
- Перед значимыми изменениями читать CONTEXT.md; обновлять его и ROADMAP.md после существенных изменений.
- Использовать GrapesJS Core, не Studio SDK. Не форкать Core без причины.
- Не добавлять Playwright, браузерные test runners и самостоятельную browser infrastructure. Визуальные проверки выполнять через доступный Codex Browser. Если недоступен, фиксировать NOT RUN с причиной.
- Сохранять полный Project Data с envelope schemaVersion. Не создавать альтернативные AI/MCP деревья и не менять JSON contract молча.
- Backend — authority: owner + auth + CSRF + validation + version precondition. Не доверять клиентскому/AI/MCP JSON. Не исполнять AI-код на сервере.
- Не хранить секреты, cookies, API keys, var DB и полные session logs в Git.
- Не переписывать большие подсистемы без причины, не создавать пустые packages.
- Не копировать код PHP-GrapesJS: audit выявил license discrepancy. Не назначать продукту лицензию без выбора владельца.
- Проверять npm run build, npm test и PHP syntax после изменений. Документировать непроверенное отдельно от PASS.
- Не выдавать запланированные AI/MCP/publish/Zero Canvas функции за реализованные. Документировать значимые решения.
