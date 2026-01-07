#!/bin/bash

# 🚀 Production Deployment Script
# This script deploys the application to production

set -e

echo "🚀 Starting deployment..."

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_info() {
    echo -e "${YELLOW}➜ $1${NC}"
}

# Configuration
DEPLOY_PATH="/var/www/inventaris"
BACKUP_PATH="/var/backups/inventaris"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    print_error "Please run as root or with sudo"
    exit 1
fi

# Create backup
print_info "Creating backup..."
mkdir -p $BACKUP_PATH
mysqldump -u root inventaris > $BACKUP_PATH/db_backup_$TIMESTAMP.sql
tar -czf $BACKUP_PATH/files_backup_$TIMESTAMP.tar.gz -C $DEPLOY_PATH storage
print_success "Backup created: $BACKUP_PATH/backup_$TIMESTAMP"

# Enable maintenance mode
print_info "Enabling maintenance mode..."
cd $DEPLOY_PATH
php artisan down || true

# Pull latest code
print_info "Pulling latest code..."
git fetch origin main
git reset --hard origin/main

# Backend deployment
print_info "Installing backend dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# Run migrations
print_info "Running migrations..."
php artisan migrate --force

# Clear and cache config
print_info "Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Restart queue workers
print_info "Restarting queue workers..."
php artisan queue:restart

# Frontend deployment
print_info "Building frontend..."
cd frontend
npm ci --production=false
npm run build
cd ..

# Set permissions
print_info "Setting permissions..."
chown -R www-data:www-data $DEPLOY_PATH
chmod -R 755 $DEPLOY_PATH
chmod -R 775 $DEPLOY_PATH/storage
chmod -R 775 $DEPLOY_PATH/bootstrap/cache

# Restart services
print_info "Restarting services..."
systemctl restart php8.2-fpm
systemctl restart nginx
supervisorctl restart all

# Disable maintenance mode
print_info "Disabling maintenance mode..."
php artisan up

# Health check
print_info "Running health check..."
sleep 5
HEALTH_CHECK=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/api/health)

if [ "$HEALTH_CHECK" == "200" ]; then
    print_success "Health check passed!"
else
    print_error "Health check failed! Status code: $HEALTH_CHECK"
    print_info "Rolling back..."
    
    # Rollback
    git reset --hard HEAD~1
    composer install --no-dev --optimize-autoloader
    php artisan migrate:rollback --force
    php artisan config:cache
    php artisan route:cache
    
    # Restore backup
    mysql -u root inventaris < $BACKUP_PATH/db_backup_$TIMESTAMP.sql
    
    print_error "Deployment failed and rolled back"
    exit 1
fi

# Clean old backups (keep last 5)
print_info "Cleaning old backups..."
cd $BACKUP_PATH
ls -t db_backup_*.sql | tail -n +6 | xargs -r rm
ls -t files_backup_*.tar.gz | tail -n +6 | xargs -r rm

print_success "Deployment completed successfully!"

echo ""
echo "=========================================="
echo "🎉 Deployment Complete!"
echo "=========================================="
echo ""
echo "Backup location: $BACKUP_PATH/backup_$TIMESTAMP"
echo "Application URL: https://your-domain.com"
echo ""
