CREATE TABLE IF NOT EXISTS project_form_submissions (
    id TEXT PRIMARY KEY,
    project_id TEXT NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    form_id TEXT NOT NULL,
    page_path TEXT NOT NULL,
    fields_json TEXT NOT NULL,
    source_ip_hash TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS project_form_submissions_project_created_idx
    ON project_form_submissions(project_id, created_at DESC, id DESC);
CREATE TABLE IF NOT EXISTS project_form_rate_limits (
    project_id TEXT NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    source_ip_hash TEXT NOT NULL,
    window_started_at INTEGER NOT NULL,
    requests INTEGER NOT NULL,
    PRIMARY KEY(project_id, source_ip_hash)
);
PRAGMA user_version = 12;
