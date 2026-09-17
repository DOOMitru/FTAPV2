#!/usr/bin/env bash
#
# Run the whole test suite, one file at a time.
#
# PHP 8.5.4 on the development machine segfaults partway through a full
# `php artisan test` -- a different point each run, and not this project's code.
# See docs/RESUME-HERE.md. CI has never shown it and runs the suite normally;
# this script is for local work.
#
# Three things here are deliberate, and each one replaces a way this has already
# gone wrong:
#
#   1. The file list is DERIVED. The version in the docs before this hardcoded
#      seven chunks of ten, sized to the suite of the day; by 112 files it ran
#      70 of them, skipped a third of the suite and still exited 0. `ran N of N`
#      is printed every run so that cannot happen quietly again.
#
#   2. Failure is read off the SUMMARY line, matching lowercase `failed`. A
#      wrapper that grepped the output for `FAILED` matched nothing -- the tail
#      of the output says `Tests:    1 failed` and the uppercase banner is
#      further up -- and reported a green suite for a whole session while a real
#      failure sat in it.
#
#   3. The exit status is the answer: 1 if anything failed or was skipped. Do
#      not pipe this script, or you will read the exit status of whatever you
#      piped into. Capture instead: out=$(bin/suite.sh); status=$?
#
# Usage:  bin/suite.sh            every test file
#         bin/suite.sh Season     only files whose path matches "Season"

set -uo pipefail

cd "$(dirname "$0")/.." || exit 1

filter=${1:-}
files=$(find tests -name '*Test.php' | sort)

if [ -n "$filter" ]; then
    files=$(echo "$files" | grep -- "$filter")
fi

if [ -z "$files" ]; then
    echo "No test files matched ${filter:-*}."
    exit 1
fi

total=$(echo "$files" | wc -l)
ran=0
fail=0

for file in $files; do
    summary=$(php artisan test "$file" 2>&1 | grep -E '^  Tests:' | tail -1)

    case "$summary" in
        ''|*failed*|*error*)
            # Once more before believing it: a segfault takes the whole run
            # down and leaves no summary line at all.
            summary=$(php artisan test "$file" 2>&1 | grep -E '^  Tests:' | tail -1)

            case "$summary" in
                ''|*failed*|*error*)
                    echo "FAIL  $file -> ${summary:-no summary line (crashed)}"
                    fail=$((fail + 1))
                    ;;
            esac
            ;;
    esac

    ran=$((ran + 1))
done

echo "ran $ran of $total files, $fail failing"

[ "$ran" = "$total" ] && [ "$fail" = 0 ]
