#!/usr/bin/env bash
# Wrapper around `php artisan serve` invoked by VS Code's "Launch LibreNMS"
# debug config. On SIGTERM/SIGINT (Stop in the debug toolbar) it propagates
# the signal to artisan serve AND its `php -S` grandchild, so the listening
# socket is released and no orphaned process is left behind.
#
# Without this wrapper, artisan serve's `php -S` child gets reparented to
# init when VS Code kills the adapter-spawned parent; its STDERR pipe to the
# dead parent then returns EPIPE on every request log write (the classic
# "file_put_contents(): Write of N bytes failed with errno=32 Broken pipe in
# .../resources/server.php on line 21" error).
set -uo pipefail

LIBRENMS_FOLDER="${LIBRENMS_FOLDER:-/var/www/html/librenms}"
HOST="${LIBRENMS_HOST:-0.0.0.0}"
PORT="${LIBRENMS_PORT:-8000}"
PHP_BIN="${PHP_BINARY:-php}"

SERVE_PID=""

cleanup() {
    trap - TERM INT HUP EXIT
    if [[ -n "$SERVE_PID" ]] && kill -0 "$SERVE_PID" 2>/dev/null; then
        local kids
        kids="$(pgrep -P "$SERVE_PID" 2>/dev/null | tr '\n' ' ')"
        echo "[serve-wrapper] stopping artisan serve (PID=$SERVE_PID, children=${kids:-none})" >&2
        kill -TERM "$SERVE_PID" $kids 2>/dev/null || true
        for _ in 1 2 3 4 5 6 7 8 9 10; do
            kill -0 "$SERVE_PID" 2>/dev/null || break
            sleep 0.2
        done
        kill -KILL "$SERVE_PID" $kids 2>/dev/null || true
    fi
    exit 0
}
trap cleanup TERM INT HUP EXIT

cd "$LIBRENMS_FOLDER" || {
    echo "[serve-wrapper] LIBRENMS_FOLDER=$LIBRENMS_FOLDER does not exist" >&2
    exit 1
}

echo "[serve-wrapper] $PHP_BIN artisan serve --host=$HOST --port=$PORT" >&2
"$PHP_BIN" artisan serve --host="$HOST" --port="$PORT" &
SERVE_PID=$!
wait "$SERVE_PID"
