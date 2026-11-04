#!/bin/sh
set -eu

repo_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$repo_root"
output=${1:-build/juzimder-ai-shared-hosting.zip}
stage=$(mktemp -d "${TMPDIR:-/tmp}/juzimder-shared-hosting-build.XXXXXX")
trap 'rm -rf "$stage"' EXIT HUP INT TERM

./node_modules/.bin/vite build --configLoader runner --outDir "$stage/web" --emptyOutDir
sh scripts/package-shared-hosting.sh "$output" "$stage/web"
