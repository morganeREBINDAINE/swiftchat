#!/bin/sh
set -e

STAGED=$(git diff --cached --name-only --diff-filter=ACM | grep '\.php$' || true)
[ -z "$STAGED" ] && exit 0

printf 'PHP-CS-Fixer: checking staged files...\n'

if ! docker compose exec -T php true 2>/dev/null; then
    printf 'WARNING: PHP container not running — skipping CS Fixer (run: make up)\n'
    exit 0
fi

# Fix staged files in-place, then re-stage them
# --config is required by php-cs-fixer when multiple paths are passed
echo "$STAGED" | xargs docker compose exec -T php php vendor/bin/php-cs-fixer fix --config .php-cs-fixer.dist.php
echo "$STAGED" | xargs git add

printf 'PHP-CS-Fixer: done.\n'
