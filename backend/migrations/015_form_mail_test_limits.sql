CREATE TABLE IF NOT EXISTS project_form_mail_test_limits (
    project_id TEXT NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    window_started_at INTEGER NOT NULL,
    requests INTEGER NOT NULL,
    PRIMARY KEY(project_id, user_id)
);
PRAGMA user_version = 15;
