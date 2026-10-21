CREATE TABLE IF NOT EXISTS project_ai_profiles (
    id TEXT PRIMARY KEY,
    project_id TEXT NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    api_key_ciphertext TEXT NOT NULL,
    model TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(project_id, id)
);
CREATE INDEX IF NOT EXISTS project_ai_profiles_project_idx ON project_ai_profiles(project_id, created_at, id);

CREATE TABLE IF NOT EXISTS project_ai_profile_state (
    project_id TEXT PRIMARY KEY REFERENCES projects(id) ON DELETE CASCADE,
    active_profile_id TEXT,
    version INTEGER NOT NULL DEFAULT 1,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO project_ai_profiles(id, project_id, name, api_key_ciphertext, model, created_at, updated_at)
SELECT lower(hex(randomblob(12))), project_id, 'Основной профиль', api_key_ciphertext, model, updated_at, updated_at
FROM project_ai_credentials
WHERE api_key_ciphertext IS NOT NULL AND api_key_ciphertext <> '' AND model IS NOT NULL AND model <> '';

INSERT INTO project_ai_profile_state(project_id, active_profile_id, version, updated_at)
SELECT c.project_id,
       (SELECT p.id FROM project_ai_profiles p WHERE p.project_id = c.project_id ORDER BY p.created_at, p.id LIMIT 1),
       c.version,
       c.updated_at
FROM project_ai_credentials c;

DROP TABLE project_ai_credentials;
PRAGMA user_version = 5;
