ALTER TABLE projects ADD COLUMN settings_payload TEXT NOT NULL DEFAULT '{}';
ALTER TABLE revisions ADD COLUMN settings_payload TEXT NOT NULL DEFAULT '{}';
PRAGMA user_version = 2;
