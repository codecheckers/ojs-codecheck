#!/usr/bin/env sh

# Locate the OJS install. Defaults to the four-levels-up layout used when the
# plugin sits at <ojs>/plugins/generic/codecheck; override with OJS_ROOT when
# developing in a standalone checkout linked into an OJS install.
OJS_ROOT="${OJS_ROOT:-../../../..}"
export OJS_ROOT

PHPUNIT="$OJS_ROOT/lib/pkp/lib/vendor/phpunit/phpunit/phpunit"

if [ ! -f "$PHPUNIT" ]; then
    echo "Could not find PHPUnit at $PHPUNIT" >&2
    echo "" >&2
    echo "These tests need an OJS installation with its development" >&2
    echo "dependencies installed (composer install in <ojs>/lib/pkp)." >&2
    echo "Point OJS_ROOT at one:" >&2
    echo "" >&2
    echo "    OJS_ROOT=/path/to/ojs sh runTests.sh" >&2
    echo "" >&2
    exit 1
fi

DEFAULT_CMD="php $PHPUNIT --configuration phpunit.xml ."

COVERAGE_CMD="php $PHPUNIT --configuration phpunit_with_coverage.xml ."

# No Parameter -> Standard
if [ -z "$1" ]; then
    exec $DEFAULT_CMD
fi

case "$1" in
    --coverage-report=true)
        exec $COVERAGE_CMD
        ;;
    --coverage-report=false)
        exec $DEFAULT_CMD
        ;;
    *)
        # unknown Parameter -> Standard
        exec $DEFAULT_CMD
        ;;
esac
