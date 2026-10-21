CREATE TABLE IF NOT EXISTS project_assets (
    id TEXT PRIMARY KEY,
    project_id TEXT NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    mime TEXT NOT NULL CHECK (mime IN ('image/jpeg','image/png','image/gif','image/webp')),
    byte_length INTEGER NOT NULL CHECK (byte_length BETWEEN 1 AND 4194304),
    width INTEGER NOT NULL CHECK (width BETWEEN 1 AND 8192),
    height INTEGER NOT NULL CHECK (height BETWEEN 1 AND 8192),
    sha256 TEXT NOT NULL,
    content BLOB NOT NULL CHECK (length(content) = byte_length),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(project_id, id)
);
CREATE INDEX IF NOT EXISTS project_assets_project_created_idx ON project_assets(project_id, created_at DESC, id);
PRAGMA user_version = 6;
