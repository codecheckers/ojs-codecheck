#!/usr/bin/env sh

# Standard-Command
DEFAULT_CMD="php ../../../../lib/pkp/lib/vendor/phpunit/phpunit/phpunit --configuration phpunit.xml ."

# Command mit Coverage
COVERAGE_CMD="php ../../../../lib/pkp/lib/vendor/phpunit/phpunit/phpunit --configuration phpunit_with_coverage.xml ."

# Kein Parameter -> Standard
if [ -z "$1" ]; then
    exec $DEFAULT_CMD
fi

# Parameter prüfen
case "$1" in
    --coverage-report=true)
        exec $COVERAGE_CMD
        ;;
    --coverage-report=false)
        exec $DEFAULT_CMD
        ;;
    *)
        # unbekannter Parameter -> Standard
        exec $DEFAULT_CMD
        ;;
esac