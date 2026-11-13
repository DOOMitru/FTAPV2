#!/usr/bin/env bash
#
# Sweep every GET route in a real browser.
#
# The suite asks whether a route responds. This asks whether the page it
# returns is right once a browser has run it: did the Inertia page mount, did
# anything throw, is the markup sound, is the shell's furniture there. Those are
# the defects a green suite has hidden in this project more than once -- a
# captured flash that went stale, three index pages that swallowed every
# confirmation, a gutter rule that had stopped being enforced.
#
# Nothing in the working tree is touched. It builds its own SQLite database,
# serves the app against it on its own port, drives Chrome over CDP, and signs
# in through the real login form. The harness this replaces patched three
# layout files and appended routes to routes/web.php, restoring them from
# .sweepbak copies afterwards; that broke when layouts/app.blade.php was
# deleted, and it is how probe instrumentation once reached a commit.
#
# Two things are deliberate:
#
#   1. Assets are BUILT first. The sweep reads public/build, so without this it
#      measures the last build rather than the working tree -- which has twice
#      looked like a defect in code that was fine.
#
#   2. PHP_CLI_SERVER_WORKERS=4. `artisan serve` is single-threaded, and an
#      Inertia page that fetches while the server is busy rendering deadlocks.
#
# Usage:  bin/sweep.sh          build, sweep, report
#         SWEEP_KEEP=1 bin/sweep.sh   leave the server and browser up

set -uo pipefail

cd "$(dirname "$0")/.." || exit 1

CHROME=${CHROME:-/snap/bin/chromium}
PORT=${SWEEP_PORT:-8123}
DEVTOOLS=${SWEEP_DEVTOOLS:-9333}
WORK=$(mktemp -d)

[ -x "$CHROME" ] || { echo "No chromium at $CHROME. Set CHROME=/path/to/chrome."; exit 1; }

# Whatever is listening on a port, by PID.
#
# `artisan serve` spawns a child php process that does the actual listening, so
# killing the pid this script started leaves that child holding the port. A
# later run then found a server already answering 200, swept it happily, and
# reported nothing -- while every page was a 500 from the previous run's
# database, which had been deleted with its work directory.
listener() {
    ss -ltnp 2>/dev/null | grep -oP "(?<=:)$1\b[^\n]*pid=\K[0-9]+" | sort -u
}

# The one file the sweep writes outside its work directory. It has to live on
# the project's public disk, because that is where the server it starts looks
# for a sponsor logo -- so it is named for the fixture rather than after
# anything a real sponsor might be called, and it goes on the way out.
FIXTURE_LOGO="storage/app/public/sponsor-logos/route-fixture.png"

cleanup() {
    rm -f "$FIXTURE_LOGO"

    [ -n "${SWEEP_KEEP:-}" ] && { echo "Left running: $BASE (devtools :$DEVTOOLS), work dir $WORK"; return; }

    for pid in ${SERVE_PID:-} ${CHROME_PID:-} $(listener "$PORT") $(listener "$DEVTOOLS"); do
        kill -9 "$pid" 2>/dev/null
    done

    rm -rf "$WORK"
}
trap cleanup EXIT

# Refused rather than reused. A server already on this port is serving some
# other database, and its answers would look exactly like a clean sweep.
for port in "$PORT" "$DEVTOOLS"; do
    if [ -n "$(listener "$port")" ]; then
        echo "Port $port is already in use by pid $(listener "$port" | tr '\n' ' ')."
        echo "That is probably a sweep that did not shut down. Kill it and run again."
        exit 1
    fi
done

BASE="http://127.0.0.1:$PORT"

echo "==> Building assets"
npm run build >"$WORK/build.log" 2>&1 || { echo "Build failed:"; tail -20 "$WORK/build.log"; exit 1; }

echo "==> Seeding a scratch database"
export DB_CONNECTION=sqlite
export DB_DATABASE="$WORK/sweep.sqlite"
: >"$DB_DATABASE"

php artisan migrate --force >"$WORK/migrate.log" 2>&1 \
    || { echo "Migrate failed:"; tail -20 "$WORK/migrate.log"; exit 1; }

# tinker prints its own noise around the script's output, so the plan is cut
# out by its braces rather than taken as the whole of stdout.
php artisan tinker --execute="require base_path('bin/sweep-seed.php');" >"$WORK/seed.out" 2>&1
sed -n '/^{/,/^}/p' "$WORK/seed.out" >"$WORK/plan.json"

grep -q '"urls"' "$WORK/plan.json" || {
    echo "Seeding failed:"; tail -30 "$WORK/seed.out"; exit 1;
}

echo "==> Serving on $PORT"
PHP_CLI_SERVER_WORKERS=4 php artisan serve --port="$PORT" >"$WORK/serve.log" 2>&1 &
SERVE_PID=$!

# A 200 is not enough on its own -- see the note on listener() above. The port
# was checked to be free before this, so a 200 here is ours.
ready=
for _ in $(seq 1 60); do
    if [ "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/login")" = 200 ]; then ready=1; break; fi
    sleep 0.5
done

[ -n "$ready" ] || { echo "Server never came up:"; tail -20 "$WORK/serve.log"; exit 1; }

echo "==> Starting Chrome on $DEVTOOLS"
# --disable-extensions as well as the target filter in sweep.mjs: an extension
# adds a background page to /json/list, and attaching to that swept nothing.
"$CHROME" --headless --disable-gpu --no-sandbox --hide-scrollbars --disable-extensions \
    --remote-debugging-port="$DEVTOOLS" --user-data-dir="$WORK/chrome" \
    --window-size=1280,900 about:blank >"$WORK/chrome.log" 2>&1 &
CHROME_PID=$!

echo "==> Sweeping"
SWEEP_BASE="$BASE" SWEEP_CDP="http://127.0.0.1:$DEVTOOLS" \
    node bin/sweep.mjs <"$WORK/plan.json"
