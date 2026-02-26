#!/bin/sh
# Docker entrypoint for Laravel microservices
# This script:
#   1. Waits for MySQL database to be ready
#   2. Runs database migrations
#   3. Starts PHP-FPM

set -e

echo "Starting Laravel service..."

DB_HOST_VALUE="${DB_HOST:-localhost}"
DB_PORT_VALUE="${DB_PORT:-3306}"

# Wait for database to be ready
echo "Waiting for database at ${DB_HOST_VALUE}:${DB_PORT_VALUE}..."

RETRIES=30
COUNT=0
until php -r "
    \$conn = @new mysqli('${DB_HOST_VALUE}', '${DB_USERNAME}', '${DB_PASSWORD}', '${DB_DATABASE}', ${DB_PORT_VALUE});
    if (\$conn->connect_error) { exit(1); }
    exit(0);
" 2>/dev/null; do
    COUNT=$((COUNT + 1))
    if [ "$COUNT" -ge "$RETRIES" ]; then
        echo "Error: Could not connect to database after ${RETRIES} attempts."
        exit 1
    fi
    echo "Database not ready (attempt ${COUNT}/${RETRIES}), retrying in 2s..."
    sleep 2
done

echo "Database connection established!"

# Change to application directory
cd /var/www/html

# Run migrations
echo "Running database migrations..."
php artisan migrate --force

echo "Migrations completed successfully!"

# Relay the Laravel log file to Docker stderr so logs appear in `docker compose up`.
# PHP-FPM's internal log routing is unreliable in Alpine; writing to a plain file
# and tailing it sidesteps the entire php-fpm error_log mechanism.
touch /tmp/phpfpm-errors
chmod 666 /tmp/phpfpm-errors
tail -f /tmp/phpfpm-errors >&2 &

# Start PHP-FPM
echo "Starting PHP-FPM..."
exec php-fpm --nodaemonize --fpm-config /usr/local/etc/php-fpm.conf
