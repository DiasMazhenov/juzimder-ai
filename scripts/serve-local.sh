#!/bin/sh
set -eu

host='127.0.0.1'
port='8080'
url="http://${host}:${port}"

if curl --silent --fail --max-time 2 "${url}/" 2>/dev/null | grep -q '<title>Juzimder AI'; then
  printf 'Juzimder AI is already running at %s/\n' "$url"
  exit 0
fi

if curl --silent --max-time 2 --output /dev/null "${url}/" 2>/dev/null; then
  printf 'Port %s is already in use by another service.\n' "$port" >&2
  exit 1
fi

export JUZIMDER_DEV_AUTH=1
printf 'Starting Juzimder AI at %s/\n' "$url"
exec php -S "${host}:${port}" -t public public/router.php
