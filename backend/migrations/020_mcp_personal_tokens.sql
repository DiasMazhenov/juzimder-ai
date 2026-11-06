CREATE TABLE mcp_personal_tokens (
 id TEXT PRIMARY KEY, user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
 name TEXT NOT NULL, token_hash TEXT NOT NULL UNIQUE, project_ids TEXT NOT NULL,
 can_write INTEGER NOT NULL DEFAULT 0, auth_version INTEGER NOT NULL,
 expires_at INTEGER NOT NULL, created_at INTEGER NOT NULL, revoked_at INTEGER
);
PRAGMA user_version=20;
