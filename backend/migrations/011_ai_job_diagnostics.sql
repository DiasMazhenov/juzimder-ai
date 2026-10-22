ALTER TABLE ai_generation_jobs ADD COLUMN diagnostics TEXT NULL;
PRAGMA user_version = 11;
