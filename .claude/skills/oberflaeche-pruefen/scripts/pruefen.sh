#!/usr/bin/env bash
# Ein Befehl für die ganze Messung: Strukturtest, alle Seiten als Admin rendern, messen. Die Login-Seite hat wie im StAN-Manager
# ein eigenes Layout ohne Kopf und wird nur von Hand angesehen: render.php <ordner> gast /
# Gemessen wird mit layout-check.js und run.mjs aus dem StAN-Manager (gleiche Regeln, keine Kopie).
# Aufruf: pruefen.sh <leerer-ausgabeordner>   (StAN-Manager unter ../StAN-Manager oder STAN_MANAGER_DIR)
set -euo pipefail
out="${1:?Ausgabeordner fehlt}"
here="$(cd "$(dirname "$0")" && pwd)"
cd "$here/../../../.."
stan="${STAN_MANAGER_DIR:-../StAN-Manager}"
runner="$stan/.claude/skills/oberflaeche-pruefen/scripts/run.mjs"
if [ ! -f "$runner" ]; then
    echo "Prüfskript des StAN-Managers fehlt: $runner (STAN_MANAGER_DIR setzen)" >&2
    exit 2
fi
mkdir -p "$out"
php8.5 vendor/bin/phpunit tests/Templates 2>&1 | tail -1
# Alle GET-Seiten ohne Parameter aus dem Router, dazu je eine Unterseite mit echter ID; render.php sperrt, was Hermine erreicht
pages="$(php8.5 bin/console debug:router --format=json | python3 -c '
import json, sys
skip = ( "/_", "/api", "/logout", "/changelog", "/events/conversations", "/events/execute" )
for route in json.load( sys.stdin ).values():
    path, method = route["path"], route["method"]
    if "{" in path or path == "/" or path.startswith( skip ) or ( "GET" not in method and method != "ANY" ):
        continue
    print( path )
')"
rendered="$(php8.5 "$here/render.php" "$out" admin $pages /events/execute '@first:/events/{id}' '@first:/users/{id}')"
echo "$rendered"
# Eine Seite mit Fehler darf nicht als „nichts gefunden“ durchgehen
if echo "$rendered" | grep -qE '^\S+\s+5[0-9][0-9]\s'; then echo 'Abbruch: Seiten mit Serverfehler' >&2; exit 1; fi
source ~/.nvm/nvm.sh >/dev/null 2>&1 || true
node "$runner" "$out"
