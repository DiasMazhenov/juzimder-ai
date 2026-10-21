CREATE TABLE IF NOT EXISTS publication_versions (
    id TEXT PRIMARY KEY,
    project_id TEXT NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    version INTEGER NOT NULL,
    source_project_version INTEGER NOT NULL,
    files_json TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(project_id, version),
    UNIQUE(project_id, id)
);
CREATE TABLE IF NOT EXISTS project_publications (
    project_id TEXT PRIMARY KEY REFERENCES projects(id) ON DELETE CASCADE,
    publication_id TEXT NOT NULL,
    FOREIGN KEY (project_id, publication_id) REFERENCES publication_versions(project_id, id)
);
CREATE INDEX IF NOT EXISTS publication_versions_project ON publication_versions(project_id, version DESC);
CREATE TRIGGER IF NOT EXISTS publication_versions_immutable
BEFORE UPDATE ON publication_versions
BEGIN
    SELECT RAISE(ABORT, 'Publication snapshots are immutable');
END;
PRAGMA user_version = 4;
