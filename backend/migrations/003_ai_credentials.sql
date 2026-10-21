CREATE TABLE IF NOT EXISTS project_ai_credentials (
    project_id TEXT PRIMARY KEY REFERENCES projects(id) ON DELETE CASCADE,
    api_key_ciphertext TEXT,
    model TEXT,
    version INTEGER NOT NULL DEFAULT 1,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
PRAGMA user_version = 3;
