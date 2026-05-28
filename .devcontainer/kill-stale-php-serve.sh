#!/usr/bin/env bash
# preLaunchTask for the "Launch LibreNMS" debug config. Belt-and-suspenders
# cleanup of ORPHAN PHP server processes left behind by previous debug
# sessions (PPID=1, i.e. reparented to init because their parent died
# without reaping them). Healthy processes still parented to a live
# `artisan serve` or shell are left alone, so this is safe to run while the
# user has artisan serve running in an integrated terminal.
set -uo pipefail

USER_ID="$(id -u)"
killed_any=0

kill_if_orphan() {
    local pid="$1" label="$2"
    [[ -d "/proc/$pid" ]] || return
    local ppid
    ppid=$(awk '{print $4}' "/proc/$pid/stat" 2>/dev/null) || return
    if [[ "$ppid" == "1" ]]; then
        echo "[kill-stale-php-serve] killing orphan $label PID=$pid"
        kill -TERM "$pid" 2>/dev/null || true
        killed_any=1
    fi
}

while read -r pid; do
    [[ -n "$pid" ]] && kill_if_orphan "$pid" "php -S"
done < <(pgrep -u "$USER_ID" -f 'php .* -S [0-9.]+:[0-9]+.*resources/server\.php' 2>/dev/null)

while read -r pid; do
    [[ -n "$pid" ]] && kill_if_orphan "$pid" "artisan serve"
done < <(pgrep -u "$USER_ID" -f '/artisan serve' 2>/dev/null)

if [[ $killed_any -eq 1 ]]; then
    sleep 0.5
    # Escalate to SIGKILL for anything that ignored SIGTERM
    while read -r pid; do
        [[ -n "$pid" && -d "/proc/$pid" ]] || continue
        ppid=$(awk '{print $4}' "/proc/$pid/stat" 2>/dev/null) || continue
        if [[ "$ppid" == "1" ]]; then
            kill -KILL "$pid" 2>/dev/null || true
        fi
    done < <(pgrep -u "$USER_ID" -f 'php .* -S [0-9.]+:[0-9]+.*resources/server\.php|/artisan serve' 2>/dev/null)
fi

echo "[kill-stale-php-serve] done"
exit 0
