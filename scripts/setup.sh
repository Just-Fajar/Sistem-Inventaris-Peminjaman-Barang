#!/bin/bash

# 🚀 Setup Script - Sistem Inventaris & Peminjaman Barang
# This script sets up the development environment

set -e

echo "🚀 Starting setup..."

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_info() {
    echo -e "${YELLOW}➜ $1${NC}"
}

# Check if running on Windows
if [[ "$OSTYPE" == "msys" || "$OSTYPE" == "win32" ]]; then
    print_info "Detected Windows. Using Windows commands..."
    IS_WINDOWS=true
else
    IS_WINDOWS=false
fi

# Check prerequisites
print_info "Checking prerequisites..."

if ! command -v php &> /dev/null; then
    print_error "PHP is not installed. Please install PHP 8.2+"
    exit 1
fi
print_success "PHP found: $(php -v | head -n 1)"

if ! command -v composer &> /dev/null; then
    print_error "Composer is not installed. Please install Composer"
    exit 1
fi
print_success "Composer found: $(composer -V)"

if ! command -v node &> /dev/null; then
    print_error "Node.js is not installed. Please install Node.js 20+"
    exit 1
fi
print_success "Node.js found: $(node -v)"

if ! command -v npm &> /dev/null; then
    print_error "npm is not installed"
    exit 1
fi
print_success "npm found: $(npm -v)"

# Setup backend
print_info "Setting up backend..."

if [ ! -f .env ]; then
    cp .env.example .env
    print_success "Created .env file"
else
    print_info ".env file already exists"
fi

print_info "Installing Composer dependencies..."
composer install

print_info "Generating application key..."
php artisan key:generate

print_info "Creating storage link..."
php artisan storage:link

# Database setup
print_info "Setting up database..."
read -p "Do you want to run migrations? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    php artisan migrate
    print_success "Migrations completed"
    
    read -p "Do you want to seed the database? (y/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        php artisan db:seed
        print_success "Database seeded"
    fi
fi

# Setup frontend
print_info "Setting up frontend..."

cd frontend

if [ ! -f .env ]; then
    cp .env.example .env
    print_success "Created frontend .env file"
else
    print_info "Frontend .env file already exists"
fi

print_info "Installing npm dependencies..."
npm install

cd ..

# Create necessary directories
print_info "Creating necessary directories..."
mkdir -p storage/logs
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p bootstrap/cache

# Set permissions (Unix-like systems only)
if [ "$IS_WINDOWS" = false ]; then
    print_info "Setting permissions..."
    chmod -R 775 storage
    chmod -R 775 bootstrap/cache
    print_success "Permissions set"
fi

# Cache configuration
print_info "Caching configuration..."
php artisan config:cache
php artisan route:cache

print_success "Setup completed!"

echo ""
echo "=========================================="
echo "🎉 Setup Complete!"
echo "=========================================="
echo ""
echo "To start the application:"
echo "  Backend:  php artisan serve"
echo "  Frontend: cd frontend && npm run dev"
echo ""
echo "Default admin credentials (if seeded):"
echo "  Email:    admin@example.com"
echo "  Password: password"
echo ""
