CREATE TABLE ai_generation_jobs_new (
 id TEXT PRIMARY KEY CHECK(length(id)=24 AND id NOT GLOB '*[^0-9a-f]*'),
 project_id TEXT NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
 owner_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
 page_id TEXT NOT NULL,
 base_version INTEGER NOT NULL CHECK(base_version>=0),
 status TEXT NOT NULL CHECK(status IN ('queued','running','succeeded','failed','cancelling','cancelled')),
 payload TEXT NOT NULL,
 result TEXT,
 error TEXT,
 created_at INTEGER NOT NULL,
 updated_at INTEGER NOT NULL
);
INSERT INTO ai_generation_jobs_new SELECT * FROM ai_generation_jobs;
DROP TABLE ai_generation_jobs;
ALTER TABLE ai_generation_jobs_new RENAME TO ai_generation_jobs;
CREATE UNIQUE INDEX ai_jobs_one_active ON ai_generation_jobs(project_id) WHERE status IN ('queued','running','cancelling');
CREATE INDEX ai_jobs_page ON ai_generation_jobs(project_id,page_id,created_at);
PRAGMA user_version = 10;
