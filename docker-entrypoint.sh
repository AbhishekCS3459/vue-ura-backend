#!/bin/bash
set -e

echo "Starting Laravel application setup..."

# Wait for MariaDB to be ready
echo "Waiting for MariaDB to be ready..."
if [ "${APP_ENV:-local}" = "production" ]; then
  MAX_ATTEMPTS=5
  CONNECT_TIMEOUT=5
else
  MAX_ATTEMPTS=30
  CONNECT_TIMEOUT=2
fi
ATTEMPT=0

# Simple connection test using PHP (connect_timeout for remote DB)
until php -r "
try {
    \$opts = [PDO::ATTR_TIMEOUT => ${CONNECT_TIMEOUT}];
    \$dsn = 'mysql:host=${DB_HOST:-mariadb};port=${DB_PORT:-3306};dbname=${DB_DATABASE:-vue_ura_db};connect_timeout=${CONNECT_TIMEOUT}';
    \$pdo = new PDO(\$dsn, '${DB_USERNAME:-vue_ura_user}', '${DB_PASSWORD:-vue_ura_password}', \$opts);
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    exit(0);
} catch (PDOException \$e) {
    exit(1);
}
" 2>/dev/null || [ $ATTEMPT -eq $MAX_ATTEMPTS ]; do
  ATTEMPT=$((ATTEMPT + 1))
  echo "MariaDB is unavailable - sleeping (attempt $ATTEMPT/$MAX_ATTEMPTS)"
  sleep 2
done

if [ $ATTEMPT -eq $MAX_ATTEMPTS ]; then
  echo "Warning: MariaDB may not be ready, but continuing..."
else
  echo "MariaDB is up - executing commands"
fi

# Install Composer dependencies
# Always install/update to ensure compatibility with current PHP version
echo "Installing/updating Composer dependencies..."
if [ "${APP_ENV:-local}" = "production" ]; then
    composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
else
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# Install NPM dependencies and build assets
echo "Installing NPM dependencies..."
# Always install to ensure dependencies are up to date
npm install

# Verify vite is available, if not reinstall
if [ ! -f "node_modules/.bin/vite" ] && [ ! -d "node_modules/vite" ]; then
    echo "vite not found in node_modules, reinstalling dependencies..."
    rm -rf node_modules
    npm install
fi

# Build frontend assets
echo "Building frontend assets..."
npm run build

# Create or update .env file from environment variables
echo "Updating .env file from environment variables..."
# In development (Docker): use .env.development and ensure DB_HOST=mariadb
# In production: use .env.production - do not override DB config
if [ "${APP_ENV:-local}" = "production" ]; then
    # Production: copy .env.production to .env if it exists
    if [ -f ".env.production" ]; then
        cp .env.production .env
        echo "Using .env.production for production"
    fi
else
    # Development: ensure .env has Docker MariaDB config
    if [ -f ".env.development" ]; then
        cp .env.development .env
    fi
    if [ -f ".env" ]; then
        # Update DB_HOST and DB_PORT for Docker (mariadb service uses internal port 3306)
        sed -i '/^#.*DB_HOST=/d' .env 2>/dev/null || sed -i '' '/^#.*DB_HOST=/d' .env 2>/dev/null || true
        if grep -q "^DB_HOST=" .env; then
            sed -i 's/^DB_HOST=.*/DB_HOST=mariadb/' .env 2>/dev/null || \
            sed -i '' 's/^DB_HOST=.*/DB_HOST=mariadb/' .env 2>/dev/null || true
        else
            echo "DB_HOST=mariadb" >> .env
        fi
        # Use port 3306 for Docker internal connection (3307 is host-mapped port only)
        sed -i 's/^DB_PORT=.*/DB_PORT=3306/' .env 2>/dev/null || \
        sed -i '' 's/^DB_PORT=.*/DB_PORT=3306/' .env 2>/dev/null || true
    else
        cat > .env <<EOF
APP_NAME=${APP_NAME:-Laravel}
APP_ENV=${APP_ENV:-local}
APP_KEY=
APP_DEBUG=${APP_DEBUG:-true}
APP_URL=${APP_URL:-http://localhost:8000}
APP_TIMEZONE=UTC

DB_CONNECTION=${DB_CONNECTION:-mariadb}
DB_HOST=mariadb
DB_PORT=${DB_PORT:-3306}
DB_DATABASE=${DB_DATABASE:-vue_ura_db}
DB_USERNAME=${DB_USERNAME:-vue_ura_user}
DB_PASSWORD=${DB_PASSWORD:-vue_ura_password}

CACHE_DRIVER=${CACHE_DRIVER:-file}
SESSION_DRIVER=${SESSION_DRIVER:-file}
QUEUE_CONNECTION=${QUEUE_CONNECTION:-sync}
EOF
    fi
    # Ensure DB_HOST=mariadb and DB_PORT=3306 for Docker development
    if ! grep -q "^DB_HOST=mariadb" .env; then
        sed -i '/^DB_HOST=/d' .env 2>/dev/null || sed -i '' '/^DB_HOST=/d' .env 2>/dev/null || true
        echo "DB_HOST=mariadb" >> .env
    fi
    if ! grep -q "^DB_PORT=3306" .env; then
        sed -i '/^DB_PORT=/d' .env 2>/dev/null || sed -i '' '/^DB_PORT=/d' .env 2>/dev/null || true
        echo "DB_PORT=3306" >> .env
    fi
fi

# Generate application key if missing
if ! grep -q "APP_KEY=base64:" .env; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

# Run migrations
echo "Running database migrations..."
php artisan migrate --force || echo "Migration failed or already run"

# Seed database (branches and treatments are synced from client API or created via UI)
echo "Seeding database..."
php artisan db:seed --class=SuperAdminSeeder --force
php artisan db:seed --class=PagePermissionSeeder --force

# Clear and cache config (optional, can be skipped in development)
echo "Optimizing application..."
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true

echo "Setup complete! Starting Laravel server..."

# Execute the main command
exec "$@"
