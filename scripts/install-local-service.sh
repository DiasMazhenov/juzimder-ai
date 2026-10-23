#!/bin/sh
set -eu
root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
php_bin=$(command -v php)
mkdir -p "$HOME/Library/LaunchAgents" "$root/var"
plist="$HOME/Library/LaunchAgents/local.juzimder-ai.plist"
/usr/bin/python3 - "$root" "$php_bin" "$plist" <<'PY'
import plistlib, sys
from pathlib import Path
root, php, target = sys.argv[1:]
config = {
 'Label': 'local.juzimder-ai',
 'ProgramArguments': [php, '-S', '127.0.0.1:8080', '-t', root+'/public', root+'/public/router.php'],
 'WorkingDirectory': root,
 'EnvironmentVariables': {'JUZIMDER_DEV_AUTH':'1', 'PATH': '/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin'},
 'RunAtLoad': True, 'KeepAlive': True, 'ThrottleInterval': 10,
 'StandardOutPath': root+'/var/local-server.log',
 'StandardErrorPath': root+'/var/local-server-error.log',
}
Path(target).write_bytes(plistlib.dumps(config))
PY
launchctl bootout "gui/$(id -u)/local.juzimder-ai" 2>/dev/null || true
launchctl bootstrap "gui/$(id -u)" "$plist"
printf 'Local service installed: http://juzimder-ai.localhost:8080/\n'
