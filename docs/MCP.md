# Juzimder AI remote MCP — read/write stage

## Status

The repository contains a Streamable HTTP endpoint at `/mcp`, a Juzimder-account OAuth authorization flow, four read tools, and two bounded write tools. A write requires a separately granted `projects:write` scope and the current project version. The isolated HTTP acceptance suite exercises OAuth, protocol calls, writes, access denials, revocation, and non-mutation on denied calls against a temporary SQLite database. This is local integration evidence; it is not acceptance of a hosted deployment or of Codex/Claude Code client logins.

Current source version: `0.1.369` / product version `00.00.369`. ByetHost `00.00.366` previously returned 503 for OAuth discovery because that hosting plan does not provide the required environment variable. The source now derives the issuer from a validated HTTPS request host when no explicit origin is configured; confirm discovery on a deployed host before connecting clients. Composer dependencies are locked in `composer.lock`; the SDK is `mcp/sdk` `0.8.1`. The MCP PHP SDK provides the protocol and resource-server pieces, not an authorization server, so `league/oauth2-server` issues the Juzimder-bound tokens. This implementation does not add a Node service or a second project-data model.

## Requirements and server configuration

- PHP 8.2 or newer, Composer, PDO SQLite, OpenSSL, and cURL.
- Install the locked PHP dependencies during deployment: `composer install --no-dev --prefer-dist --classmap-authoritative`.
- On a standard HTTPS virtual host, the OAuth issuer is derived from the validated request host, so shared hosting does not need environment-variable support. Set `JUZIMDER_PUBLIC_BASE_URL` to the HTTPS origin only (no path) when the app sits behind a proxy that does not expose HTTPS to PHP, or when the public origin must be fixed explicitly. The MCP resource URL becomes `<origin>/mcp`.
- Keep the existing Juzimder database and its data directory persistent and writable by PHP. The app migration runner creates MCP OAuth/session tables. OAuth signing keys are created under the database data directory with restrictive file permissions; back up that directory with the database.
- Serve the application through HTTPS. Plain HTTP issuer configuration is accepted only for explicit local development (`JUZIMDER_DEV_AUTH=1` and a loopback host); never use that mode on a public host.
- Keep application debug output disabled in production. MCP diagnostics log only safe exception locations, never authorization headers, cookies, request bodies, passwords, or tool results.

The routed endpoints are:

- `POST`, `GET`, `DELETE`, `OPTIONS /mcp` — MCP Streamable HTTP.
- `/.well-known/oauth-protected-resource` and `/.well-known/oauth-protected-resource/mcp` — protected-resource metadata.
- `/.well-known/oauth-authorization-server` — OAuth authorization-server metadata.
- `/oauth/register`, `/oauth/authorize`, `/oauth/token`, `/oauth/revoke` — public-client registration and authorization-code flow.

OAuth uses authorization code + PKCE S256, public clients (`token_endpoint_auth_method=none`), Dynamic Client Registration, RFC 9207 issuer identification, `projects:read` and `projects:write` scopes, 15-minute access tokens and 30-day refresh tokens. Authorization defaults to `projects:read`; write is a distinct scope that the client must request and that the user must approve on the Juzimder consent page. Refresh exchanges rotate both tokens; the old access token and refresh token are rejected. A refresh token remains usable after its linked access token expires, but is invalidated by revoking that access token or changing the account auth version. Every MCP request validates token signature, issuer, exact MCP resource audience, expiry, scopes, revocation state, and current account auth version. Project ownership and page/project association are checked again by each tool. Authorization code, login, and consent pages use the existing Juzimder account; browser cookies are not accepted as MCP bearer credentials.

## Tools

| Tool | Purpose | Bounds |
| --- | --- | --- |
| `list_projects` | Projects owned by the signed-in user | `limit` 1–50, `offset` 0–100000 |
| `list_pages(project_id)` | Pages in an owned project | `limit` 1–50, `offset` 0–1000 |
| `get_page(project_id, page_id)` | Safe canonical page projection and project version | response bounded to 256 KiB and 50,000 nodes; custom-code payload redacted; password input values cleared |
| `get_project_settings(project_id)` | Explicit allowlist of safe settings | does not return form delivery, custom CSS/code, AI credentials, or private operational settings |
| `apply_page_operations(project_id, page_id, expected_version, operations)` | Apply 1–20 typed operations to components on one owned page | requires `projects:write`; max 1 MiB; version precondition; backend `ProjectOperations` validation; no cross-page component targets; custom-code insertion/duplication is blocked |
| `update_project_settings(project_id, expected_version, updates)` | Update explicitly allowlisted project settings | requires `projects:write`; max 32 KiB; version precondition and existing settings validators; allowed: design tokens, page name/slug, SEO, 404 and shared-section settings |

`get_page` returns the existing Project Data envelope, including `schemaVersion`; it does not define a parallel MCP document model. Use `projectVersion` from `get_page` or `list_pages` as `expected_version`; a stale write is rejected. Each `apply_page_operations` call saves once and returns the new version. Tools do not expose application source/code, mail delivery, credentials, AI generation or publication. Generation, applying an AI candidate, and publication are intentionally separate future operations; page edits through the bounded write tool are already available.

## Connect clients after HTTPS deployment

Replace the example origin with the deployed origin.

Codex CLI (same MCP configuration is shared with the Codex IDE integration). Request only `projects:read` for read access; include `projects:write` only when edits are intended:

```sh
codex mcp add juzimder --url https://builder.example.com/mcp
codex mcp login juzimder --scopes projects:read,projects:write
codex mcp list
```

Claude Code (the OAuth scope string is space-separated; the stored server is private to your user):

```sh
claude mcp add-json --scope user juzimder '{"type":"http","url":"https://builder.example.com/mcp","oauth":{"scopes":"projects:read projects:write"}}'
claude mcp login juzimder
claude mcp list
```

In Claude Code, complete OAuth in the interactive `/mcp` flow if the CLI login command is unavailable in the installed release. Both clients should show the Juzimder consent screen with the requested scopes. Confirm the server is connected and call `list_projects`, then `list_pages` and `get_page` for one of the returned items. Do not test against a production project when a dedicated QA project is available.

## Revoke access

- Codex CLI: `codex mcp logout juzimder` revokes the stored OAuth credential when the client sends the server's revocation request; `codex mcp remove juzimder` removes the local server entry.
- Claude Code: `claude mcp logout juzimder` revokes OAuth access where supported, then `claude mcp remove juzimder` removes its local entry. Removing only the entry clears local credentials but is not proof of server-side revocation.
- Access tokens expire after 15 minutes and refresh tokens after 30 days. The server rejects revoked tokens on the next request. The installed Claude Code `2.1.126` was not authenticated to an Anthropic account and its `claude mcp --help` did not include OAuth login/logout commands; real Claude Code login and tool-call acceptance remain unverified.

## Evidence and remaining acceptance

For `00.00.369`, `php tests/mcp.php` covers tool projections, guarded page/settings writes, HTTPS-origin derivation without environment variables, malformed-host rejection, and authorization policy. `php tests/mcp-http.php` starts an isolated local HTTP server and verifies metadata/discovery, DCR, account login/consent, code + PKCE exchange, Streamable HTTP handshake, `tools/list`, all six tool calls, successful expected-version writes, stale/cross-owner/cross-page/private-setting/custom-code denial, read-only token denial, wrong resource, expiry, refresh rotation after access expiry, revocation and secret-safe logs. Both MCP suites, full `npm test`, production build, PHP syntax and `git diff --check` passed on 2026-11-05; local fake-SMTP and Vite temporary-file tests required the corresponding filesystem/loopback permission. Live ByetHost OAuth discovery and separate Codex/Claude Code logins/tool calls remain open. Vite reports the existing GrapesJS bundle exceeds 500 kB.

Codex CLI `0.166.0-alpha.5.1` completed a real local DCR + OAuth/PKCE login against an isolated Juzimder QA endpoint, requested `projects:read` and `projects:write`, and showed the explicit consent screen. Its isolated profile had no model login, so invoking tools from a Codex model session is NOT RUN. Claude Code `2.1.126` was not logged into Anthropic; its OAuth/client tool-call test is NOT RUN. Neither client has completed OAuth against a deployed HTTPS Juzimder endpoint. Completing client acceptance requires a public HTTPS deployment with a persistent database/key directory and an authenticated Claude Code session; the local protocol and client-login fixtures alone do not satisfy this criterion. Local `stdio` remains a separate later stage.


## Personal access tokens

On the project dashboard, choose **Доступ для AI** in the account header. Select owned projects, read access or explicit write access, and 7/30/90 days. Save the token immediately: it is shown once. Send it only as `Authorization: Bearer <token>` to the installation HTTPS `/mcp` endpoint, never in a URL. Codex supports `bearer_token_env_var`; Claude Code HTTP servers support an Authorization header. These are remote HTTP connections; no local MCP server or relay is required. OAuth remains available.

Tokens are stored only as SHA-256 hashes. Every request validates expiry, revocation, current account auth version, ownership and the selected project allowlist. Revoke from the same dialog. Project backups do not transfer these account credentials. Standard HTTPS JSON POST access to `/mcp` is still required. Token authentication removes OAuth registration/token exchange, but does not establish network reachability. Real client acceptance remains pending.
