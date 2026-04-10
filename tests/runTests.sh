#!/usr/bin/env sh

DEFAULT_CMD="php ../../../../lib/pkp/lib/vendor/phpunit/phpunit/phpunit --configuration phpunit.xml ."

COVERAGE_CMD="php ../../../../lib/pkp/lib/vendor/phpunit/phpunit/phpunit --configuration phpunit_with_coverage.xml ."

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
