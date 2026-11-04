<?php
declare(strict_types=1);

namespace Juzimder\McpAuth;

use Juzimder\Store;
use Mcp\Server\Session\SessionStoreInterface;
use Symfony\Component\Uid\Uuid;

/** SQLite-backed protocol sessions survive the short PHP request lifecycle. */
final class McpSessionStore implements SessionStoreInterface
{
    public function __construct(private readonly Store $store, private readonly int $ttl = 3600) {}

    public function exists(Uuid $id): bool
    {
        return (bool)$this->store->query('SELECT 1 FROM mcp_sessions WHERE id=? AND expires_at>? LIMIT 1', [$id->toRfc4122(), time()])->fetchColumn();
    }

    public function read(Uuid $id): string|false
    {
        $row = $this->store->query('SELECT payload,expires_at FROM mcp_sessions WHERE id=?', [$id->toRfc4122()])->fetch();
        if (!$row) return false;
        if ((int)$row['expires_at'] <= time()) {
            $this->store->query('DELETE FROM mcp_sessions WHERE id=?', [$id->toRfc4122()]);
            return false;
        }
        return (string)$row['payload'];
    }

    public function write(Uuid $id, string $data): bool
    {
        $this->store->query('INSERT INTO mcp_sessions(id,payload,expires_at) VALUES(?,?,?) ON CONFLICT(id) DO UPDATE SET payload=excluded.payload,expires_at=excluded.expires_at', [
            $id->toRfc4122(), $data, time() + $this->ttl,
        ]);
        return true;
    }

    public function destroy(Uuid $id): bool
    {
        $this->store->query('DELETE FROM mcp_sessions WHERE id=?', [$id->toRfc4122()]);
        return true;
    }

    public function gc(): array
    {
        $expired = $this->store->query('SELECT id FROM mcp_sessions WHERE expires_at<=?', [time()])->fetchAll();
        $this->store->query('DELETE FROM mcp_sessions WHERE expires_at<=?', [time()]);
        return array_map(static fn(array $row): Uuid => Uuid::fromString((string)$row['id']), $expired);
    }
}
