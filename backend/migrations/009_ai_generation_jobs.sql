CREATE TABLE ai_generation_jobs (
 id TEXT PRIMARY KEY CHECK(length(id)=24 AND id NOT GLOB '*[^0-9a-f]*'),
 project_id TEXT NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
 owner_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
 page_id TEXT NOT NULL,
 base_version INTEGER NOT NULL CHECK(base_version>=0),
 status TEXT NOT NULL CHECK(status IN ('queued','running','succeeded','failed')),
 payload TEXT NOT NULL,
 result TEXT,
 error TEXT,
 created_at INTEGER NOT NULL,
 updated_at INTEGER NOT NULL
);
CREATE UNIQUE INDEX ai_jobs_one_active ON ai_generation_jobs(project_id) WHERE status IN ('queued','running');
CREATE INDEX ai_jobs_page ON ai_generation_jobs(project_id,page_id,created_at);
PRAGMA user_version = 9;
