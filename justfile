# Development server - runs Laravel, queue, and logs concurrently
dev:
    composer run dev

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

# Run all quality checks and tests
all: check test
