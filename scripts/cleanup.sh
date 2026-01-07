#!/bin/bash

# 🧹 Cleanup Script
# This script cleans up temporary files and caches

echo "🧹 Running cleanup..."

# Backend cleanup
echo "Cleaning backend caches..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear

# Clear compiled files
php artisan clear-compiled

# Clean logs older than 7 days
echo "Cleaning old logs..."
find storage/logs -name "*.log" -type f -mtime +7 -delete

# Clear temporary files
echo "Cleaning temporary files..."
rm -rf storage/framework/cache/*
rm -rf storage/framework/sessions/*
rm -rf storage/framework/views/*

# Frontend cleanup
echo "Cleaning frontend..."
cd frontend
rm -rf node_modules/.cache
rm -rf dist
cd ..

# Docker cleanup (if using Docker)
if command -v docker &> /dev/null; then
    read -p "Clean Docker resources? (y/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        echo "Cleaning Docker..."
        docker system prune -f
        docker volume prune -f
    fi
fi

echo "✓ Cleanup completed!"
