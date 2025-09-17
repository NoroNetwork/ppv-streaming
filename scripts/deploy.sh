#!/bin/bash

# Deployment script for PPV Streaming Platform
# Usage: ./scripts/deploy.sh [staging|production]

set -e

ENVIRONMENT=${1:-staging}
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Configuration
STAGING_HOST="${STAGING_HOST:-staging.your-domain.com}"
PRODUCTION_HOST="${PRODUCTION_HOST:-your-domain.com}"
DEPLOY_USER="${DEPLOY_USER:-deploy}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
    exit 1
}

check_requirements() {
    log "Checking deployment requirements..."

    # Check if git is clean
    if [[ -n $(git status --porcelain) ]]; then
        error "Git working directory is not clean. Please commit or stash changes."
    fi

    # Check if on correct branch
    CURRENT_BRANCH=$(git branch --show-current)
    if [[ "$ENVIRONMENT" == "production" && "$CURRENT_BRANCH" != "main" ]]; then
        error "Production deployments must be from main branch. Current branch: $CURRENT_BRANCH"
    fi

    # Check required environment variables
    case $ENVIRONMENT in
        staging)
            required_vars="STAGING_HOST STAGING_USER STAGING_SSH_KEY"
            ;;
        production)
            required_vars="PRODUCTION_HOST PRODUCTION_USER PRODUCTION_SSH_KEY"
            ;;
        *)
            error "Invalid environment: $ENVIRONMENT. Use 'staging' or 'production'"
            ;;
    esac

    for var in $required_vars; do
        if [[ -z "${!var}" ]]; then
            error "Required environment variable $var is not set"
        fi
    done

    success "Requirements check passed"
}

run_tests() {
    log "Running tests before deployment..."

    # Run PHPUnit tests
    if ! vendor/bin/phpunit --configuration phpunit.xml; then
        error "Tests failed. Deployment aborted."
    fi

    # Run code quality checks
    if ! vendor/bin/phpcs --standard=PSR12 src/; then
        warning "Code style issues detected, but continuing deployment"
    fi

    success "Tests passed"
}

build_assets() {
    log "Building assets..."

    # Install dependencies
    composer install --no-dev --optimize-autoloader

    # Build frontend assets if needed
    if [[ -f package.json ]]; then
        npm ci
        npm run build
    fi

    success "Assets built successfully"
}

create_deployment_package() {
    log "Creating deployment package..."

    TIMESTAMP=$(date +%Y%m%d_%H%M%S)
    PACKAGE_NAME="ppv-streaming-${ENVIRONMENT}-${TIMESTAMP}.tar.gz"

    # Create temporary directory
    TEMP_DIR=$(mktemp -d)
    cp -r "$PROJECT_DIR" "$TEMP_DIR/ppv-streaming"

    # Remove development files
    cd "$TEMP_DIR/ppv-streaming"
    rm -rf .git .github tests docker *.md
    rm -f docker-compose.yml Dockerfile .env.* phpunit.xml

    # Create package
    cd "$TEMP_DIR"
    tar -czf "$PROJECT_DIR/$PACKAGE_NAME" ppv-streaming/

    # Cleanup
    rm -rf "$TEMP_DIR"

    echo "$PACKAGE_NAME"
    success "Deployment package created: $PACKAGE_NAME"
}

deploy_to_server() {
    local host=$1
    local user=$2
    local package=$3

    log "Deploying to $host..."

    # Upload package
    scp "$package" "$user@$host:/tmp/"

    # Execute deployment on server
    ssh "$user@$host" << EOF
        set -e

        # Backup current deployment
        if [[ -d /var/www/ppv-streaming ]]; then
            sudo cp -r /var/www/ppv-streaming /var/backups/ppv-backup-\$(date +%Y%m%d_%H%M%S)
        fi

        # Extract new version
        cd /tmp
        tar -xzf $package

        # Stop services
        sudo systemctl stop php8.1-fpm nginx

        # Deploy new version
        sudo rm -rf /var/www/ppv-streaming
        sudo mv ppv-streaming /var/www/
        sudo chown -R www-data:www-data /var/www/ppv-streaming

        # Update environment
        sudo cp /var/www/ppv-streaming/.env.${ENVIRONMENT} /var/www/ppv-streaming/.env

        # Run database migrations
        if [[ -f /var/www/ppv-streaming/database/migrations.sql ]]; then
            mysql -u \$DB_USER -p\$DB_PASS \$DB_NAME < /var/www/ppv-streaming/database/migrations.sql
        fi

        # Clear caches
        cd /var/www/ppv-streaming
        sudo -u www-data composer dump-autoload --optimize

        # Restart services
        sudo systemctl start php8.1-fpm nginx

        # Cleanup
        rm -f /tmp/$package

        echo "Deployment completed successfully"
EOF

    success "Deployment to $host completed"
}

health_check() {
    local host=$1

    log "Running health checks on $host..."

    # Wait for services to start
    sleep 30

    # Check HTTP response
    if curl -f "https://$host/health" > /dev/null 2>&1; then
        success "Health check passed"
    else
        error "Health check failed"
    fi

    # Check admin panel
    if curl -f -I "https://$host/admin" > /dev/null 2>&1; then
        success "Admin panel accessible"
    else
        warning "Admin panel check failed"
    fi
}

rollback() {
    local host=$1
    local user=$2

    warning "Starting rollback process..."

    ssh "$user@$host" << 'EOF'
        set -e

        # Find latest backup
        LATEST_BACKUP=$(ls -t /var/backups/ppv-backup-* | head -1)

        if [[ -z "$LATEST_BACKUP" ]]; then
            echo "No backup found for rollback"
            exit 1
        fi

        echo "Rolling back to: $LATEST_BACKUP"

        # Stop services
        sudo systemctl stop php8.1-fpm nginx

        # Restore backup
        sudo rm -rf /var/www/ppv-streaming
        sudo cp -r "$LATEST_BACKUP" /var/www/ppv-streaming
        sudo chown -R www-data:www-data /var/www/ppv-streaming

        # Restart services
        sudo systemctl start php8.1-fpm nginx

        echo "Rollback completed"
EOF

    success "Rollback completed"
}

send_notification() {
    local status=$1
    local environment=$2

    if [[ -n "$SLACK_WEBHOOK_URL" ]]; then
        curl -X POST -H 'Content-type: application/json' \
            --data "{\"text\":\"🚀 Deployment to $environment: $status\"}" \
            "$SLACK_WEBHOOK_URL"
    fi
}

main() {
    log "Starting deployment to $ENVIRONMENT..."

    case $ENVIRONMENT in
        staging)
            HOST=$STAGING_HOST
            USER=$STAGING_USER
            ;;
        production)
            HOST=$PRODUCTION_HOST
            USER=$PRODUCTION_USER
            ;;
    esac

    # Pre-deployment checks
    check_requirements
    run_tests
    build_assets

    # Create and deploy package
    PACKAGE=$(create_deployment_package)

    # Deploy with error handling
    if deploy_to_server "$HOST" "$USER" "$PACKAGE"; then
        if health_check "$HOST"; then
            success "Deployment to $ENVIRONMENT completed successfully!"
            send_notification "SUCCESS" "$ENVIRONMENT"
        else
            error "Health checks failed. Consider rolling back."
        fi
    else
        error "Deployment failed"
        rollback "$HOST" "$USER"
        send_notification "FAILED" "$ENVIRONMENT"
    fi

    # Cleanup
    rm -f "$PACKAGE"
}

# Handle script interruption
trap 'error "Deployment interrupted"' INT TERM

# Run main function
main "$@"