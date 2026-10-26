CREATE TABLE ai_request_limits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    requested_at INTEGER NOT NULL
);

CREATE INDEX ai_request_limits_user_window_idx ON ai_request_limits(user_id, requested_at);
CREATE INDEX ai_request_limits_expiry_idx ON ai_request_limits(requested_at);
PRAGMA user_version = 17;
