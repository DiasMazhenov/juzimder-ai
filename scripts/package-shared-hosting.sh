#!/bin/sh
set -eu

repo_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$repo_root"

output=${1:-build/juzimder-ai-shared-hosting.zip}
web_root=${2:-public}
case "$output" in
  /*) output_path=$output ;;
  *) output_path="$repo_root/$output" ;;
esac
case "$web_root" in
  /*) web_path=$web_root ;;
  *) web_path="$repo_root/$web_root" ;;
esac
mkdir -p "$(dirname -- "$output_path")"
if ! command -v zip >/dev/null 2>&1; then
  echo "The zip command is required to create the hosting archive." >&2
  exit 1
fi
stage=$(mktemp -d "${TMPDIR:-/tmp}/juzimder-shared-hosting.XXXXXX")
trap 'rm -rf "$stage"' EXIT HUP INT TERM

if [ ! -s "$web_path/index.html" ] || [ ! -d "$web_path/assets" ] || [ -z "$(find "$web_path/assets" -type f -print -quit)" ]; then
  echo "Build the application first with: npm run package:shared-hosting" >&2
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

sed 's#href="/favicon.png"#href="/assets/favicon.png"#g' "$web_path/index.html" > "$stage/index.html"
sed 's#\.\./backend/#backend/#g; s#\.\./var/#var/#g' public/router.php > "$stage/router.php"
cp -R "$web_path/assets" "$stage/assets"
cp public/favicon.png "$stage/assets/favicon.png"
cp -R backend resources schemas "$stage/"
if [ -f composer.json ]; then
  # Keep Composer dependencies beneath the already-managed backend/ directory.
  # Older installed updaters may reject a new top-level vendor/ directory.
  cp -R vendor "$stage/backend/vendor"
fi
mkdir -p "$stage/var"

cat > "$stage/.htaccess" <<'HTACCESS'
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule ^\.well-known/oauth-(authorization-server|protected-resource)(/mcp)?$ router.php [L]
RewriteRule (^|/)\. - [F,L]
RewriteRule ^(backend|resources|schemas|var|vendor)(/|$) - [F,L]
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
3. Make the var/ directory writable by the PHP service user. Keep it private.
4. Enable Apache mod_rewrite and AllowOverride for this directory. Use HTTPS for login.
5. Open the domain and create your own account. Do not upload a local database or .env file.

The SQLite database is created automatically in var/app.sqlite. Private application
files and storage have access-denial rules. Do not remove the .htaccess files.
INSTALL

product_version=$(node -p "require('./package.json').productVersion")
package_version=$(node -p "require('./package.json').version")
case "$product_version" in
  [0-9][0-9].[0-9][0-9].[0-9][0-9][0-9]) ;;
  *) echo "Invalid productVersion in package.json: $product_version" >&2; exit 1 ;;
esac
cat > "$stage/release.json" <<MANIFEST
{"productVersion":"$product_version","packageVersion":"$package_version"}
MANIFEST

rm -f "$output_path"
(cd "$stage" && zip -qr "$output_path" .)
echo "Created $output_path"
