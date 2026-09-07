#!/bin/sh
set -e

echo "==> Initializing Laravel application container..."

# Ensure storage directories exist with appropriate permissions
mkdir -p /var/www/storage/framework/cache/data \
         /var/www/storage/framework/sessions \
         /var/www/storage/framework/views \
         /var/www/storage/logs \
         /var/www/bootstrap/cache

chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Remove any stale package or service cache files mounted from host
rm -f /var/www/bootstrap/cache/*.php

# Wait for database if DB_HOST is set
if [ -n "$DB_HOST" ] && [ "$DB_CONNECTION" = "mysql" ]; then
    echo "==> Waiting for database at $DB_HOST:${DB_PORT:-3306}..."
    MAX_TRIES=30
    TRIES=0
    until php -r "
        \$host = getenv('DB_HOST') ?: '127.0.0.1';
        \$port = getenv('DB_PORT') ?: '3306';
        \$db   = getenv('DB_DATABASE') ?: '';
        \$user = getenv('DB_USERNAME') ?: 'root';
        \$pass = getenv('DB_PASSWORD') ?: '';
        try {
            new PDO(\"mysql:host=\$host;port=\$port;dbname=\$db\", \$user, \$pass, [
                PDO::ATTR_TIMEOUT => 2,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            exit(0);
        } catch (Exception \$e) {
            exit(1);
        }
    " > /dev/null 2>&1; do
        TRIES=$((TRIES + 1))
        if [ "$TRIES" -ge "$MAX_TRIES" ]; then
            echo "==> Database timeout reached ($MAX_TRIES attempts). Proceeding anyway..."
            break
        fi
        echo "==> Waiting for MySQL... ($TRIES/$MAX_TRIES)"
        sleep 2
    done
    echo "==> Database connection verified."
fi

# Run database migrations
if [ "$APP_ENV" != "testing" ]; then
    echo "==> Running database migrations..."
    php artisan migrate --force || echo "==> Migration skipped or failed, continuing..."
fi

# Ensure symbolic link for storage
if [ ! -L /var/www/public/storage ]; then
    echo "==> Creating storage link..."
    php artisan storage:link || true
fi

echo "==> Application container ready."

# Execute CMD
if [ $# -gt 0 ]; then
    exec "$@"
else
    exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
fi
