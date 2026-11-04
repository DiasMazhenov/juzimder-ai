ALTER TABLE mcp_oauth_clients ADD COLUMN grant_types TEXT NOT NULL DEFAULT '["authorization_code"]';

CREATE TABLE mcp_oauth_refresh_tokens (
    id TEXT PRIMARY KEY,
    client_id TEXT NOT NULL REFERENCES mcp_oauth_clients(client_id) ON DELETE CASCADE,
    user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    access_token_jti TEXT NOT NULL REFERENCES mcp_oauth_access_tokens(jti) ON DELETE CASCADE,
    auth_version INTEGER NOT NULL,
    scopes TEXT NOT NULL,
    expires_at INTEGER NOT NULL,
    revoked_at INTEGER
);

CREATE INDEX mcp_oauth_refresh_tokens_user_idx ON mcp_oauth_refresh_tokens(user_id, client_id, expires_at);
CREATE INDEX mcp_oauth_refresh_tokens_access_idx ON mcp_oauth_refresh_tokens(access_token_jti);
PRAGMA user_version = 19;
