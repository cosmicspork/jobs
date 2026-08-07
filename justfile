# Development server - runs Laravel, queue, logs, and Vite concurrently
dev:
    composer run dev

# Build frontend assets. Required before the app renders: the panel theme is
# served through Vite, so a missing manifest is a hard error.
build:
    npm run build

# Format PHP code using Laravel Pint
format:
    ./vendor/bin/pint

# Check formatting without making changes
format-check:
    ./vendor/bin/pint --test

# Analyse PHP code using Larastan
analyse:
    ./vendor/bin/phpstan analyse --memory-limit=1G

# Run all code quality checks
check: format-check analyse

# Test PHP code
test:
    php artisan test

# Browser tests (Playwright). Needs built assets - run `just build` first.
test-browser:
    php artisan test --testsuite=Browser

# Run all quality checks and tests
all: check test
