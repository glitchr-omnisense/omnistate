#!/bin/sh
# Composes the harness (this core + every source), installs it when it
# changed, then runs the command: "bare" (the default) for the script that
# uses the packages with no bundle, no container and no console, "test" for
# PHPUnit, "composer ..." or "sh" as they are.
set -e
php /omnistate/core/docker/harness/setup.php
cd /harness
if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --no-progress
elif [ composer.json -nt composer.lock ]; then
    composer update --no-interaction --no-progress
fi
case "${1:-bare}" in
    test) shift; exec vendor/bin/phpunit "$@" ;;
    bare) [ $# -gt 0 ] && shift; exec php /omnistate/core/docker/harness/bin/bare "$@" ;;
    composer|sh|php) exec "$@" ;;
    *) echo "Unknown command \"$1\": bare [--recorded] [--json], test, composer, sh, php." >&2; exit 64 ;;
esac
