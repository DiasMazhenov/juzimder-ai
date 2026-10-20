# MCP: следующий этап

Сервер и tools ещё не реализованы. Не открыт неавторизованный endpoint. Будущий Node transport использует официальный SDK и тот же backend application service, owner/permission policy, document schema и version precondition. Работает с закрытым редактором через сохранённый документ, не browser DOM.

Предварительные группы tools: list/get projects/pages, component create/update/move/delete/style, tokens, list/save/restore revisions, publication. Имена/SDK transport выбрать при реализации после проверки текущих официальных API. Authentication не через копирование browser session cookie: отдельные scoped revocable credentials, secret-safe audit. MCP не пишет SQLite напрямую, не обходит Document validator, не исполняет пользовательский PHP/JS. Готовность — внешний клиент изменил компонент, закрытый редактор затем открыл тот же документ с сохранённой revision.
