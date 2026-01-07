#!/bin/bash

# 🐳 Docker Setup Script
# This script sets up the Docker development environment

set -e

echo "🐳 Setting up Docker environment..."

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

# Check Docker
if ! command -v docker &> /dev/null; then
    print_error "Docker is not installed"
    exit 1
fi
print_success "Docker found: $(docker -v)"

if ! command -v docker-compose &> /dev/null; then
    print_error "Docker Compose is not installed"
    exit 1
fi
print_success "Docker Compose found: $(docker-compose -v)"

# Create .env files if they don't exist
if [ ! -f .env ]; then
    cp .env.example .env
    print_success "Created .env file"
    print_info "Please edit .env with your configuration"
fi

if [ ! -f frontend/.env ]; then
    cp frontend/.env.example frontend/.env
    print_success "Created frontend/.env file"
fi

# Build images
print_info "Building Docker images..."
docker-compose build

# Start containers
print_info "Starting containers..."
docker-compose up -d

# Wait for database
print_info "Waiting for database to be ready..."
sleep 10

# Install backend dependencies
print_info "Installing backend dependencies..."
docker-compose exec app composer install

# Generate key
print_info "Generating application key..."
docker-compose exec app php artisan key:generate

# Run migrations
print_info "Running migrations..."
docker-compose exec app php artisan migrate

read -p "Do you want to seed the database? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    docker-compose exec app php artisan db:seed
    print_success "Database seeded"
fi

# Create storage link
print_info "Creating storage link..."
docker-compose exec app php artisan storage:link

# Install frontend dependencies
print_info "Installing frontend dependencies..."
docker-compose exec frontend npm install

print_success "Docker setup completed!"

echo ""
echo "=========================================="
echo "🎉 Docker Environment Ready!"
echo "=========================================="
echo ""
echo "Services running:"
echo "  Backend API:  http://localhost:8000"
echo "  Frontend:     http://localhost:5173"
echo "  PhpMyAdmin:   http://localhost:8080"
echo "  MySQL:        localhost:3306"
echo "  Redis:        localhost:6379"
echo ""
echo "Useful commands:"
echo "  docker-compose ps              # View running containers"
echo "  docker-compose logs -f         # View logs"
echo "  docker-compose exec app bash   # Access backend shell"
echo "  docker-compose down            # Stop containers"
echo ""
