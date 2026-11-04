CREATE TABLE mcp_oauth_clients (
    client_id TEXT PRIMARY KEY,
    client_name TEXT NOT NULL,
    redirect_uris TEXT NOT NULL,
    created_at INTEGER NOT NULL
);

CREATE TABLE mcp_oauth_auth_codes (
    id TEXT PRIMARY KEY,
    client_id TEXT NOT NULL REFERENCES mcp_oauth_clients(client_id) ON DELETE CASCADE,
    user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    auth_version INTEGER NOT NULL,
    expires_at INTEGER NOT NULL,
    revoked INTEGER NOT NULL DEFAULT 0 CHECK(revoked IN (0,1))
);

CREATE INDEX mcp_oauth_auth_codes_expiry_idx ON mcp_oauth_auth_codes(expires_at);

CREATE TABLE mcp_oauth_access_tokens (
    jti TEXT PRIMARY KEY,
    client_id TEXT NOT NULL REFERENCES mcp_oauth_clients(client_id) ON DELETE CASCADE,
    user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    auth_version INTEGER NOT NULL,
    scopes TEXT NOT NULL,
    expires_at INTEGER NOT NULL,
    revoked_at INTEGER
);

CREATE INDEX mcp_oauth_access_tokens_user_idx ON mcp_oauth_access_tokens(user_id, client_id, expires_at);

CREATE TABLE mcp_sessions (
    id TEXT PRIMARY KEY,
    payload TEXT NOT NULL,
    expires_at INTEGER NOT NULL
);

CREATE INDEX mcp_sessions_expiry_idx ON mcp_sessions(expires_at);
PRAGMA user_version = 18;
