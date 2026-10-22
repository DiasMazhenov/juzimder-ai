CREATE TABLE project_ai_skills (
    id TEXT PRIMARY KEY CHECK(length(id)=24 AND id NOT GLOB '*[^0-9a-f]*'),
    project_id TEXT NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    name TEXT NOT NULL CHECK(length(name) BETWEEN 1 AND 64),
    content TEXT NOT NULL CHECK(length(content) BETWEEN 1 AND 16000),
    manual_checks_json TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(project_id,id)
);
CREATE INDEX project_ai_skills_project ON project_ai_skills(project_id,created_at,id);
CREATE TABLE project_ai_skill_state (
    project_id TEXT PRIMARY KEY REFERENCES projects(id) ON DELETE CASCADE,
    active_skill_id TEXT NULL,
    version INTEGER NOT NULL DEFAULT 0 CHECK(version>=0),
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(active_skill_id) REFERENCES project_ai_skills(id) ON DELETE SET NULL
);
PRAGMA user_version = 8;
