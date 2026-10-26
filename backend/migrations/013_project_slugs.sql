ALTER TABLE projects ADD COLUMN slug TEXT;
CREATE UNIQUE INDEX projects_slug_unique_idx ON projects(slug) WHERE slug IS NOT NULL;
PRAGMA user_version = 13;
