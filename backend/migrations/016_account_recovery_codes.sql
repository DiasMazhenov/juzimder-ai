ALTER TABLE users ADD COLUMN auth_version INTEGER NOT NULL DEFAULT 0 CHECK(auth_version >= 0);

CREATE TABLE account_recovery_codes (
    id TEXT PRIMARY KEY,
    user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    code_hash TEXT NOT NULL,
    created_at INTEGER NOT NULL,
    UNIQUE(user_id, code_hash)
);

CREATE INDEX account_recovery_codes_user_idx ON account_recovery_codes(user_id);
PRAGMA user_version = 16;
