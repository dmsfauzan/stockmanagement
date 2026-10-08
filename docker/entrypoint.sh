#!/bin/sh
set -e

php artisan config:clear >/dev/null 2>&1 || true

echo "Waiting for database and running migrations..."
for i in $(seq 1 30); do
    if php artisan migrate --force --no-interaction; then
        break
    fi
    echo "Database not ready (attempt ${i}/30), retrying in 3s..."
    sleep 3
done

exec "$@"
