#!/bin/sh
set -eu

repo_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$repo_root"

output=${1:-build/juzimder-ai-shared-hosting.zip}
case "$output" in
  /*) output_path=$output ;;
  *) output_path="$repo_root/$output" ;;
esac
mkdir -p "$(dirname -- "$output_path")"
if ! command -v zip >/dev/null 2>&1; then
  echo "The zip command is required to create the hosting archive." >&2
  exit 1
fi
stage=$(mktemp -d "${TMPDIR:-/tmp}/juzimder-shared-hosting.XXXXXX")
trap 'rm -rf "$stage"' EXIT HUP INT TERM

if [ ! -s public/index.html ] || [ ! -d public/assets ] || [ -z "$(find public/assets -type f -print -quit)" ]; then
  echo "Build the application first with: npm run build" >&2
  exit 1
fi

if [ -f composer.json ]; then
  if [ ! -f composer.lock ]; then
    echo "composer.lock is required so the hosting package uses reproducible Composer dependencies." >&2
    exit 1
  fi
  if ! command -v composer >/dev/null 2>&1; then
    echo "Composer dependencies are required for this checkout. Install Composer, then run composer install --no-dev." >&2
    exit 1
  fi
  composer install --no-dev --classmap-authoritative --no-interaction --prefer-dist --no-scripts --no-plugins
fi

cp public/index.html "$stage/index.html"
sed 's#\.\./backend/#backend/#g; s#\.\./var/#var/#g' public/router.php > "$stage/router.php"
cp -R public/assets "$stage/assets"
cp -R backend resources schemas "$stage/"
node --input-type=commonjs -e 'const fs=require("node:fs");const p=JSON.parse(fs.readFileSync("package.json","utf8"));process.stdout.write(JSON.stringify({productVersion:p.productVersion,packageVersion:p.version})+"\n")' > "$stage/release.json"
if [ -f composer.json ]; then
  cp -R vendor "$stage/vendor"
fi
mkdir -p "$stage/var"

cat > "$stage/.htaccess" <<'HTACCESS'
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule ^\.well-known/oauth-(authorization-server|protected-resource)(/mcp)?$ router.php [L]
RewriteRule (^|/)\. - [F,L]
RewriteRule ^(backend|resources|schemas|var|vendor)(/|$) - [F,L]
RewriteRule ^release\.json$ - [F,L]
RewriteCond %{REQUEST_URI} !^/assets/[a-zA-Z0-9_.-]+$
RewriteRule ^ router.php [L]
</IfModule>
HTACCESS
cp "$stage/.htaccess" "$stage/htaccess.txt"

for private_dir in backend resources schemas var vendor; do
  if [ -d "$stage/$private_dir" ]; then
    cat > "$stage/$private_dir/.htaccess" <<'PRIVATE_HTACCESS'
<IfModule mod_authz_core.c>
Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
Order allow,deny
Deny from all
</IfModule>
PRIVATE_HTACCESS
  fi
done

cat > "$stage/INSTALL.txt" <<'INSTALL'
Juzimder AI shared hosting package

1. Use PHP 8.2 or newer with PDO SQLite, Fileinfo, DOM/libxml, mbstring, Sodium, OpenSSL and ZIP.
2. Extract every file into the domain document root. Confirm the hidden root .htaccess exists; if the file manager skipped it, rename the visible htaccess.txt to .htaccess.
3. Make var/ writable by the PHP service user. Keep it private. For one-click updates, PHP must also be allowed to replace the application files and directories in the document root; otherwise use a fresh package manually.
4. Enable Apache mod_rewrite and AllowOverride for this directory. Use HTTPS for login.
5. Open the domain and create your own account. The first account is the installation manager and can check/install updates. Do not upload a local database or .env file.

Updates are downloaded from published stable GitHub Releases for DiasMazhenov/juzimder-ai. The updater requires outbound HTTPS access to GitHub and either PHP cURL or allow_url_fopen. It verifies the release checksum and version before replacing application files. Before installation, SQLite and its encryption keys are copied to the private var/updates/ directory. Keep enough free disk space for the update archive, temporary files and backup.

The first release must be installed manually. Later releases can be checked from the projects dashboard by the installation manager. The public release metadata contains only the product version.

The SQLite database is created automatically in var/app.sqlite. Private application
files and storage have access-denial rules. Do not remove the .htaccess files.
INSTALL

rm -f "$output_path"
(cd "$stage" && zip -qr "$output_path" .)
echo "Created $output_path"
