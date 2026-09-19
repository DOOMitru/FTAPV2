#!/usr/bin/env bash
#
# Fail on serious advisories in the dependencies that ship; report the rest.
#
# On 2026-09-19 this app was seven months behind inside its own major and
# carrying 39 advisories, two of them in code paths reachable from a public
# form: CRLF injection in the default `email` validation rule, which guards
# /register and /contact, and a host-check bypass in guzzle, which
# App\Rules\Recaptcha calls Google through. Every other check in the pipeline
# was green the whole time. Nothing watched dependencies, so this does.
#
# WHY THE SEVERITY FILTER IS DONE HERE AND NOT BY COMPOSER: `composer audit`
# advertises --ignore-severity, and in Composer 2.10.2 it changes nothing --
# 36 advisories reported with it, 36 without, whichever values are passed. The
# JSON carries a correct `severity` per advisory, so the tiering is read off
# that. Do not "simplify" this back to the flag without checking that it has
# started working.
#
# The bar for stopping a release: high or critical, in a package that is NOT
# dev-only. An advisory with NO severity set also stops it -- unknown is not
# the same as low, and the framework advisory that prompted this had a blank
# one.
#
# Usage:  bin/audit.sh          gate + report
#         bin/audit.sh --report everything, never fails

set -uo pipefail

cd "$(dirname "$0")/.." || exit 1

mode=${1:-gate}

report=$(composer audit --locked --format=table 2>&1)
json=$(composer audit --locked --no-dev --format=json 2>/dev/null)

# An empty or unparseable document means the check did not run -- the advisory
# database was unreachable, or composer changed its output. Deliberately NOT
# treated as "nothing found": a gate that passes when it could not do its job
# is worse than one that asks for a re-run.
if [ -z "$json" ]; then
    echo "composer audit produced no JSON. The advisory database may be unreachable."
    echo "This check has not run. Re-run the job rather than ignoring it."
    exit 1
fi

echo "$report"
echo

printf '%s' "$json" | php -r '
$raw = stream_get_contents(STDIN);
$data = json_decode($raw, true);

if (! is_array($data) || ! array_key_exists("advisories", $data)) {
    fwrite(STDERR, "Could not parse composer audit JSON; the check has not run.\n");
    exit(1);
}

$blocking = [];
$other = 0;

foreach ($data["advisories"] as $package => $advisories) {
    foreach ($advisories as $advisory) {
        $severity = strtolower((string) ($advisory["severity"] ?? ""));

        if (in_array($severity, ["high", "critical"], true) || $severity === "") {
            $blocking[] = sprintf(
                "  %-26s %-8s %s",
                $package,
                $severity === "" ? "unknown" : $severity,
                $advisory["title"] ?? $advisory["advisoryId"] ?? "?"
            );
            continue;
        }

        $other++;
    }
}

if ($blocking === []) {
    printf("No high, critical or unknown-severity advisories in shipped dependencies.%s\n",
        $other > 0 ? sprintf(" (%d lower-severity listed above.)", $other) : "");
    exit(0);
}

printf("%d advisory(ies) of high, critical or unknown severity in shipped dependencies:\n\n", count($blocking));
echo implode("\n", $blocking), "\n\n";
echo "Fix with `composer update` and commit composer.lock.\n";
exit(1);
'
status=$?

[ "$mode" = "--report" ] && exit 0
exit $status
