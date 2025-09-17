#!/bin/sh

set -e

# Wait for database
if [ -n "$DB_HOST" ]; then
  echo "Waiting for database connection..."
  timeout=60
  while [ $timeout -gt 0 ]; do
    if php -r "
      try {
        \$pdo = new PDO('mysql:host=$DB_HOST;port=3306', '$DB_USER', '$DB_PASS', [PDO::ATTR_TIMEOUT => 5]);
        echo 'Database connected';
        exit(0);
      } catch (Exception \$e) {
        exit(1);
      }
    " 2>/dev/null; then
      echo "Database is ready!"
      break
    fi
    sleep 1
    timeout=$((timeout - 1))
  done

  if [ $timeout -eq 0 ]; then
    echo "Database connection timeout"
  fi
fi

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

# Install dependencies in development mode
if [ "$APP_ENV" = "development" ] && [ ! -d "/var/www/html/vendor" ]; then
  echo "Installing development dependencies..."
  cd /var/www/html
  composer install --no-interaction
fi

# Clear caches in production
if [ "$APP_ENV" = "production" ]; then
  echo "Optimizing for production..."
  composer dump-autoload --optimize
fi

echo "Starting application..."
exec "$@"