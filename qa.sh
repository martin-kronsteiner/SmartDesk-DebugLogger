#!/usr/bin/env bash
set -euo pipefail
composer validate --no-check-publish --strict
php vendor/bin/phpstan analyse --no-progress
php vendor/bin/phpcs
php vendor/bin/phpunit --colors=always
