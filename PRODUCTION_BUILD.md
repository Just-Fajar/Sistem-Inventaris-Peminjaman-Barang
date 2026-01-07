# 🏗️ Production Build Guide

**Sistem Inventaris & Peminjaman Barang**

---

## 📋 Table of Contents

1. [Pre-Build Checklist](#pre-build-checklist)
2. [Backend Build Process](#backend-build-process)
3. [Frontend Build Process](#frontend-build-process)
4. [Build Optimization](#build-optimization)
5. [Build Verification](#build-verification)
6. [Troubleshooting](#troubleshooting)

---

## Pre-Build Checklist

Before building for production, ensure:

### ✅ Code Quality
- [ ] All tests passing (`php artisan test` and `npm run test`)
- [ ] No linter errors (`npm run lint`)
- [ ] Code reviewed and approved
- [ ] Security vulnerabilities checked
- [ ] Dependencies updated

### ✅ Configuration
- [ ] `.env` configured for production
- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] Database credentials set
- [ ] Cache/Queue drivers configured
- [ ] Mail settings configured
- [ ] API keys/secrets set

### ✅ Database
- [ ] Migrations tested
- [ ] Seeders reviewed (if used in production)
- [ ] Backup strategy in place

### ✅ Assets
- [ ] Images optimized
- [ ] Fonts loaded
- [ ] Icons available
- [ ] Static files organized

---

## Backend Build Process

### 1. Environment Setup

```bash
# Set production environment
cp .env.example .env
nano .env  # Edit with production values

# Required production values:
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
```

### 2. Install Dependencies

```bash
# Install production dependencies only
composer install --optimize-autoloader --no-dev --no-interaction

# Verify installation
composer check-platform-reqs
```

### 3. Optimize Application

```bash
# Generate optimized autoloader
composer dump-autoload --optimize

# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Cache events
php artisan event:cache
```

### 4. Database Migration

```bash
# Run migrations (with backup first!)
php artisan migrate --force

# Verify migrations
php artisan migrate:status
```

### 5. Storage Setup

```bash
# Create storage link
php artisan storage:link

# Set permissions
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### 6. Queue Configuration

```bash
# Restart queue workers
php artisan queue:restart

# Check queue status
php artisan queue:monitor
```

---

## Frontend Build Process

### 1. Environment Setup

```bash
cd frontend

# Create production .env
cp .env.example .env
nano .env  # Edit with production values

# Required production values:
VITE_API_URL=https://api.your-domain.com
VITE_APP_NAME="Sistem Inventaris"
VITE_SENTRY_DSN=your_sentry_dsn
```

### 2. Install Dependencies

```bash
# Clean install
rm -rf node_modules package-lock.json
npm install

# Or with exact versions
npm ci
```

### 3. Build for Production

```bash
# Production build
npm run build

# Build with source maps (for debugging)
npm run build -- --sourcemap

# Build with bundle analysis
npm run build -- --mode analyze
```

### 4. Verify Build Output

```bash
# Check dist folder
ls -lh dist/

# Verify files exist:
# - index.html
# - assets/*.js
# - assets/*.css
# - favicon.ico
```

### 5. Test Production Build Locally

```bash
# Preview production build
npm run preview

# Access at http://localhost:4173
```

---

## Build Optimization

### Backend Optimization

#### 1. OPcache Configuration

Edit `/etc/php/8.2/fpm/conf.d/10-opcache.ini`:

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.save_comments=1
opcache.fast_shutdown=1
```

#### 2. Composer Optimization

```bash
# Optimize autoloader with APCu
composer install --optimize-autoloader --classmap-authoritative --no-dev

# Dump optimized autoloader
composer dump-autoload --optimize --classmap-authoritative
```

#### 3. Laravel Optimization

```bash
# Optimize everything at once
php artisan optimize

# Individual optimizations:
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### Frontend Optimization

#### 1. Vite Configuration

Edit `vite.config.js`:

```javascript
export default defineConfig({
  build: {
    rollupOptions: {
      output: {
        manualChunks: {
          vendor: ['react', 'react-dom'],
          router: ['react-router-dom'],
        },
      },
    },
    chunkSizeWarningLimit: 1000,
    minify: 'terser',
    terserOptions: {
      compress: {
        drop_console: true,
        drop_debugger: true,
      },
    },
  },
});
```

#### 2. Code Splitting

Implement dynamic imports:

```javascript
// Lazy load routes
const Dashboard = lazy(() => import('./pages/Dashboard'));
const Items = lazy(() => import('./pages/Items'));
```

#### 3. Asset Optimization

```bash
# Optimize images before build
npm run optimize-images  # If you have this script

# Or manually with tools:
# - TinyPNG for PNG/JPG
# - SVGO for SVG
```

#### 4. Bundle Analysis

```bash
# Analyze bundle size
npm run build -- --mode analyze

# Opens browser with bundle visualization
```

---

## Build Verification

### Backend Verification

```bash
# 1. Test application
php artisan test --env=testing

# 2. Check for common issues
php artisan about

# 3. Verify routes
php artisan route:list

# 4. Check storage permissions
ls -la storage/
ls -la bootstrap/cache/

# 5. Test database connection
php artisan tinker
>>> DB::connection()->getPdo();

# 6. Verify queue connection
php artisan queue:monitor

# 7. Check scheduled tasks
php artisan schedule:list
```

### Frontend Verification

```bash
cd frontend

# 1. Check build output
ls -lh dist/

# 2. Verify critical files
test -f dist/index.html && echo "✓ index.html exists"
test -d dist/assets && echo "✓ assets folder exists"

# 3. Check bundle sizes
du -sh dist/assets/*.js
du -sh dist/assets/*.css

# 4. Test production build
npm run preview

# 5. Lighthouse audit (in browser DevTools)
# - Performance
# - Accessibility
# - Best Practices
# - SEO
```

### Integration Testing

```bash
# 1. Health check endpoint
curl -f https://your-domain.com/api/health

# 2. Test API endpoints
curl -H "Accept: application/json" https://your-domain.com/api/items

# 3. Test frontend
curl -I https://your-domain.com

# 4. Check SSL
curl -vI https://your-domain.com 2>&1 | grep -i ssl

# 5. Performance test
curl -o /dev/null -s -w 'Time: %{time_total}s\n' https://your-domain.com
```

---

## Troubleshooting

### Backend Build Issues

#### 500 Error After Build

```bash
# Check logs
tail -f storage/logs/laravel.log

# Common fixes:
php artisan cache:clear
php artisan config:clear
chmod -R 775 storage bootstrap/cache
```

#### Cache Issues

```bash
# Clear all caches
php artisan optimize:clear

# Rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

#### Permission Issues

```bash
# Fix permissions
sudo chown -R www-data:www-data /var/www/inventaris
sudo chmod -R 755 /var/www/inventaris
sudo chmod -R 775 /var/www/inventaris/storage
sudo chmod -R 775 /var/www/inventaris/bootstrap/cache
```

#### Queue Not Working

```bash
# Check supervisor status
supervisorctl status

# Restart workers
php artisan queue:restart
supervisorctl restart all

# Check failed jobs
php artisan queue:failed
```

### Frontend Build Issues

#### Build Fails

```bash
# Clear cache and rebuild
rm -rf node_modules package-lock.json
npm install
npm run build

# Check for memory issues
NODE_OPTIONS="--max-old-space-size=4096" npm run build
```

#### Assets Not Loading

```bash
# Check base URL in vite.config.js
base: '/',

# Verify .env variables
VITE_API_URL=https://api.your-domain.com

# Clear browser cache
# Hard refresh: Ctrl+Shift+R
```

#### Blank Page

```bash
# Check console errors in browser DevTools
# Common causes:
# - Wrong API URL
# - CORS issues
# - Missing environment variables

# Verify environment variables
grep VITE_ .env

# Test API connection
curl https://api.your-domain.com/api/health
```

### Performance Issues

#### Slow Backend

```bash
# Enable query log
php artisan telescope:install

# Check slow queries in Telescope
# Optimize with indexes

# Profile with Xdebug or Blackfire
```

#### Slow Frontend

```bash
# Analyze bundle
npm run build -- --mode analyze

# Check bundle sizes
# Split large chunks
# Lazy load routes
# Use CDN for static assets
```

---

## Build Checklist

### Pre-Production

- [ ] All tests passing
- [ ] Code reviewed
- [ ] Security scan completed
- [ ] Performance tested
- [ ] Backup created

### Build Process

- [ ] Dependencies installed (production only)
- [ ] Configuration cached
- [ ] Routes cached
- [ ] Views cached
- [ ] Frontend built
- [ ] Assets optimized

### Post-Build

- [ ] Health check passing
- [ ] Migrations successful
- [ ] Queue workers running
- [ ] Scheduled tasks configured
- [ ] Monitoring enabled
- [ ] Logs rotating

### Rollback Plan

- [ ] Database backup available
- [ ] Code backup available
- [ ] Rollback script tested
- [ ] Recovery time documented

---

**Ready for production! 🚀**
