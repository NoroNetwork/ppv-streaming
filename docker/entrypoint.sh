#!/bin/sh

set -e

# Wait for database
echo "Waiting for database connection..."
while ! nc -z $DB_HOST 3306; do
  sleep 1
done
echo "Database is ready!"

# Run database migrations if needed
if [ "$APP_ENV" = "production" ] && [ -f database/schema.sql ]; then
  echo "Running database setup..."
  mysql -h$DB_HOST -u$DB_USER -p$DB_PASS $DB_NAME < database/schema.sql 2>/dev/null || true
  mysql -h$DB_HOST -u$DB_USER -p$DB_PASS $DB_NAME < database/security-tables.sql 2>/dev/null || true
fi

# Set proper permissions
chown -R app:app /var/www/html
chmod -R 755 /var/www/html

# Create log directory
mkdir -p /var/www/html/storage/logs
chown -R app:app /var/www/html/storage

# Clear caches in production
if [ "$APP_ENV" = "production" ]; then
  echo "Optimizing for production..."
  composer dump-autoload --optimize
fi

echo "Starting application..."
exec "$@"