# 🚀 Deployment Guide

**Sistem Inventaris & Peminjaman Barang**  
**Last Updated:** 7 Januari 2026  
**Version:** 1.0.0

---

## 📋 Table of Contents

1. [Server Requirements](#server-requirements)
2. [Pre-Deployment Checklist](#pre-deployment-checklist)
3. [Installation Steps](#installation-steps)
4. [Environment Configuration](#environment-configuration)
5. [Database Setup](#database-setup)
6. [Production Optimizations](#production-optimizations)
7. [Post-Deployment](#post-deployment)
8. [Rollback Procedure](#rollback-procedure)
9. [Troubleshooting](#troubleshooting)

---

## 🖥️ Server Requirements

### Minimum Requirements

**Backend (Laravel):**
- PHP >= 8.2
- Composer 2.x
- MySQL 8.0+ or PostgreSQL 13+
- Redis (optional, recommended for caching)
- Nginx or Apache
- SSL Certificate (Let's Encrypt recommended)

**Frontend (React):**
- Node.js >= 20.x
- npm >= 10.x

**Server Specifications:**
- RAM: 2GB minimum, 4GB recommended
- Storage: 20GB minimum, 50GB recommended
- CPU: 2 cores minimum

### PHP Extensions Required

```bash
php -m | grep -E '(BCMath|Ctype|Fileinfo|JSON|Mbstring|OpenSSL|PDO|Tokenizer|XML|GD|Zip)'
```

Required extensions:
- BCMath
- Ctype
- Fileinfo
- JSON
- Mbstring
- OpenSSL
- PDO
- Tokenizer
- XML
- GD (for image processing)
- Zip

---

## ✅ Pre-Deployment Checklist

### Code & Testing
- [ ] All tests passing (`php artisan test` & `npm test`)
- [ ] No console.log statements in production code
- [ ] Error handling implemented
- [ ] Security headers configured
- [ ] API documentation up to date
- [ ] Database migrations tested
- [ ] Seeders working correctly

### Configuration
- [ ] `.env.production` configured
- [ ] Frontend `.env` configured
- [ ] Database credentials secured
- [ ] SMTP mail configured
- [ ] Redis configured (if using)
- [ ] S3/Storage configured
- [ ] API keys and secrets secured

### Security
- [ ] SSL certificate installed
- [ ] HTTPS enforced
- [ ] CORS configured properly
- [ ] Rate limiting enabled
- [ ] Security headers added
- [ ] File upload restrictions set
- [ ] Strong passwords enforced

### Performance
- [ ] Database indexes created
- [ ] Images optimized
- [ ] Frontend bundle optimized
- [ ] Caching configured
- [ ] CDN setup (optional)

### Monitoring
- [ ] Error tracking configured (Sentry)
- [ ] Backup automated
- [ ] Monitoring tools configured
- [ ] Log rotation setup

---

## 🔧 Installation Steps

### Step 1: Server Setup

```bash
# Update system packages
sudo apt update && sudo apt upgrade -y

# Install PHP 8.2
sudo add-apt-repository ppa:ondrej/php
sudo apt install php8.2-fpm php8.2-cli php8.2-common php8.2-mysql \
  php8.2-zip php8.2-gd php8.2-mbstring php8.2-curl php8.2-xml \
  php8.2-bcmath php8.2-redis -y

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Node.js 20.x
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install nodejs -y

# Install MySQL
sudo apt install mysql-server -y
sudo mysql_secure_installation

# Install Nginx
sudo apt install nginx -y

# Install Redis (optional)
sudo apt install redis-server -y
sudo systemctl enable redis-server

# Install Certbot for SSL
sudo apt install certbot python3-certbot-nginx -y
```

### Step 2: Clone Repository

```bash
# Create web directory
sudo mkdir -p /var/www/inventaris
cd /var/www

# Clone repository
sudo git clone https://github.com/Just-Fajar/Sistem-Inventaris-Peminjaman-Barang.git inventaris
cd inventaris

# Set permissions
sudo chown -R www-data:www-data /var/www/inventaris
sudo chmod -R 755 /var/www/inventaris
sudo chmod -R 775 storage bootstrap/cache
```

### Step 3: Backend Setup

```bash
# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Copy environment file
cp .env.production.example .env

# Edit environment variables
nano .env

# Generate application key
php artisan key:generate

# Clear and cache config
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations
php artisan migrate --force

# Seed database (if needed)
php artisan db:seed --force

# Link storage
php artisan storage:link

# Set proper permissions
sudo chown -R www-data:www-data /var/www/inventaris
sudo chmod -R 755 /var/www/inventaris
sudo chmod -R 775 storage bootstrap/cache
```

### Step 4: Frontend Setup

```bash
cd /var/www/inventaris/frontend

# Install dependencies
npm ci --production

# Copy environment file
cp .env.example .env

# Edit environment variables
nano .env

# Build for production
npm run build

# Copy build to public directory
sudo cp -r dist/* /var/www/inventaris/public/
```

### Step 5: Nginx Configuration

```bash
sudo nano /etc/nginx/sites-available/inventaris
```

**Nginx Configuration:**

```nginx
server {
    listen 80;
    server_name inventaris.example.com;
    root /var/www/inventaris/public;
    
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";
    add_header Referrer-Policy "strict-origin-when-cross-origin";
    
    index index.php index.html;
    
    charset utf-8;
    
    # Frontend routes (SPA)
    location / {
        try_files $uri $uri/ /index.html;
    }
    
    # API routes
    location /api {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    # PHP handling
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Deny access to hidden files
    location ~ /\. {
        deny all;
    }
    
    # Deny access to sensitive files
    location ~ /\.(?!well-known).* {
        deny all;
    }
    
    # Optimize static files
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
    
    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript;
}
```

**Enable site and restart Nginx:**

```bash
sudo ln -s /etc/nginx/sites-available/inventaris /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

### Step 6: SSL Certificate

```bash
# Generate SSL certificate with Let's Encrypt
sudo certbot --nginx -d inventaris.example.com

# Auto-renewal test
sudo certbot renew --dry-run
```

### Step 7: Supervisor (Queue Workers)

```bash
sudo apt install supervisor -y

# Create supervisor config
sudo nano /etc/supervisor/conf.d/inventaris-worker.conf
```

**Supervisor Configuration:**

```ini
[program:inventaris-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/inventaris/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/inventaris/storage/logs/worker.log
stopwaitsecs=3600
```

**Start supervisor:**

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start inventaris-worker:*
```

### Step 8: Cron Jobs

```bash
sudo crontab -e -u www-data
```

**Add Laravel scheduler:**

```cron
* * * * * cd /var/www/inventaris && php artisan schedule:run >> /dev/null 2>&1
```

---

## ⚙️ Environment Configuration

### Backend (.env)

```env
APP_NAME="Sistem Inventaris"
APP_ENV=production
APP_KEY=base64:GENERATED_KEY
APP_DEBUG=false
APP_URL=https://inventaris.example.com

LOG_CHANNEL=daily
LOG_LEVEL=error
LOG_DAILY_DAYS=14

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=inventaris_production
DB_USERNAME=inventaris_user
DB_PASSWORD=STRONG_PASSWORD_HERE

BROADCAST_DRIVER=log
CACHE_DRIVER=redis
FILESYSTEM_DISK=s3
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@inventaris.example.com
MAIL_FROM_NAME="${APP_NAME}"

AWS_ACCESS_KEY_ID=your_aws_key
AWS_SECRET_ACCESS_KEY=your_aws_secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your_bucket_name

SANCTUM_STATEFUL_DOMAINS=inventaris.example.com
SESSION_DOMAIN=.inventaris.example.com

RATE_LIMIT_ENABLED=true
RATE_LIMIT_PER_MINUTE=60
```

### Frontend (.env)

```env
VITE_API_URL=https://inventaris.example.com/api
VITE_API_TIMEOUT=30000
VITE_APP_NAME="Sistem Inventaris & Peminjaman Barang"
VITE_APP_ENV=production
VITE_APP_VERSION=1.0.0
VITE_DEBUG=false
VITE_MAX_IMAGE_SIZE=2
VITE_ITEMS_PER_PAGE=15
VITE_DATE_FORMAT=DD/MM/YYYY
VITE_TIMEZONE=Asia/Jakarta
VITE_SENTRY_DSN=https://your-sentry-dsn@sentry.io/project-id
VITE_GA_TRACKING_ID=UA-XXXXXXXXX-X
```

---

## 🗄️ Database Setup

### Create Database and User

```sql
-- Login to MySQL
sudo mysql -u root -p

-- Create database
CREATE DATABASE inventaris_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user
CREATE USER 'inventaris_user'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD_HERE';

-- Grant privileges
GRANT ALL PRIVILEGES ON inventaris_production.* TO 'inventaris_user'@'localhost';

-- Flush privileges
FLUSH PRIVILEGES;

-- Exit
EXIT;
```

### Import Database (if migrating)

```bash
# Export from old server
php artisan db:backup

# Or use mysqldump
mysqldump -u username -p database_name > backup.sql

# Import to new server
mysql -u inventaris_user -p inventaris_production < backup.sql
```

---

## ⚡ Production Optimizations

### Laravel Optimizations

```bash
# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimize autoloader
composer dump-autoload --optimize

# Clear application cache
php artisan cache:clear
```

### PHP-FPM Optimization

```bash
sudo nano /etc/php/8.2/fpm/php.ini
```

**Recommended PHP settings:**

```ini
memory_limit = 512M
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 300
max_input_time = 300
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
```

### MySQL Optimization

```bash
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
```

**Recommended MySQL settings:**

```ini
[mysqld]
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT
max_connections = 200
query_cache_size = 0
query_cache_type = 0
```

### Redis Configuration

```bash
sudo nano /etc/redis/redis.conf
```

**Recommended Redis settings:**

```ini
maxmemory 256mb
maxmemory-policy allkeys-lru
```

---

## 📊 Post-Deployment

### Verification Steps

```bash
# 1. Check application status
curl https://inventaris.example.com/api/health

# 2. Check database connection
php artisan tinker
>>> DB::connection()->getPdo();

# 3. Check queue workers
sudo supervisorctl status

# 4. Check scheduled tasks
php artisan schedule:list

# 5. Check logs
tail -f storage/logs/laravel.log

# 6. Test email
php artisan tinker
>>> Mail::raw('Test email', function($msg) { $msg->to('admin@example.com')->subject('Test'); });

# 7. Run backup test
php artisan backup:run

# 8. Check disk space
df -h

# 9. Check memory usage
free -m

# 10. Check CPU usage
top
```

### Monitoring Setup

```bash
# Install monitoring tools
# Example: Install netdata for real-time monitoring
bash <(curl -Ss https://my-netdata.io/kickstart.sh)

# Access at: http://your-server-ip:19999
```

### Log Monitoring

```bash
# Watch Laravel logs
tail -f /var/www/inventaris/storage/logs/laravel.log

# Watch API logs
tail -f /var/www/inventaris/storage/logs/api.log

# Watch Nginx access logs
sudo tail -f /var/log/nginx/access.log

# Watch Nginx error logs
sudo tail -f /var/log/nginx/error.log
```

---

## 🔄 Rollback Procedure

### Quick Rollback

```bash
# 1. Navigate to project directory
cd /var/www/inventaris

# 2. Checkout previous version
git fetch --all
git checkout <previous-commit-hash>

# 3. Install dependencies
composer install --no-dev --optimize-autoloader
cd frontend && npm ci --production && npm run build

# 4. Rollback migrations (if needed)
php artisan migrate:rollback --step=1

# 5. Clear caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Restart services
sudo systemctl restart php8.2-fpm
sudo systemctl restart nginx
sudo supervisorctl restart inventaris-worker:*
```

### Database Rollback

```bash
# Restore from backup
mysql -u inventaris_user -p inventaris_production < backup-YYYY-MM-DD.sql

# Or use Laravel backup restore
php artisan backup:restore
```

---

## 🔧 Troubleshooting

### Common Issues

#### 1. **500 Internal Server Error**

```bash
# Check PHP-FPM status
sudo systemctl status php8.2-fpm

# Check error logs
tail -100 /var/www/inventaris/storage/logs/laravel.log
sudo tail -100 /var/log/nginx/error.log

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Check permissions
sudo chown -R www-data:www-data /var/www/inventaris
sudo chmod -R 755 /var/www/inventaris
sudo chmod -R 775 storage bootstrap/cache
```

#### 2. **Database Connection Failed**

```bash
# Test database connection
php artisan tinker
>>> DB::connection()->getPdo();

# Check MySQL status
sudo systemctl status mysql

# Check credentials in .env
cat .env | grep DB_

# Test MySQL login
mysql -u inventaris_user -p inventaris_production
```

#### 3. **Queue Jobs Not Processing**

```bash
# Check supervisor status
sudo supervisorctl status

# Restart workers
sudo supervisorctl restart inventaris-worker:*

# Check worker logs
tail -100 /var/www/inventaris/storage/logs/worker.log

# Manually process queue
php artisan queue:work --once
```

#### 4. **Scheduled Tasks Not Running**

```bash
# Check cron is running
sudo systemctl status cron

# Check crontab
sudo crontab -l -u www-data

# Manually run scheduler
php artisan schedule:run

# Check schedule list
php artisan schedule:list
```

#### 5. **Frontend Not Loading**

```bash
# Check if build exists
ls -la /var/www/inventaris/public/assets

# Rebuild frontend
cd /var/www/inventaris/frontend
npm run build
sudo cp -r dist/* /var/www/inventaris/public/

# Check Nginx config
sudo nginx -t

# Restart Nginx
sudo systemctl restart nginx
```

#### 6. **SSL Certificate Issues**

```bash
# Check certificate
sudo certbot certificates

# Renew certificate
sudo certbot renew

# Test renewal
sudo certbot renew --dry-run
```

### Performance Issues

```bash
# Check server resources
htop
df -h
free -m

# Check slow queries
sudo mysql -u root -p -e "SHOW FULL PROCESSLIST;"

# Enable query log
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
# Add: slow_query_log = 1
# Add: long_query_time = 2
sudo systemctl restart mysql

# Check PHP-FPM processes
ps aux | grep php-fpm

# Check Redis memory
redis-cli INFO memory
```

---

## 📞 Support & Maintenance

### Regular Maintenance Tasks

**Daily:**
- Monitor error logs
- Check backup status
- Review security alerts

**Weekly:**
- Review performance metrics
- Check disk space
- Update dependencies (security patches)

**Monthly:**
- Full backup verification
- Security audit
- Performance optimization review
- SSL certificate check

### Emergency Contacts

- **Developer:** developer@example.com
- **System Admin:** sysadmin@example.com
- **Hosting Support:** support@hosting.com

### Useful Commands

```bash
# Restart all services
sudo systemctl restart nginx php8.2-fpm mysql redis-server
sudo supervisorctl restart inventaris-worker:*

# Clear all caches
php artisan optimize:clear

# Check application version
php artisan --version

# Check disk usage by directory
du -sh /var/www/inventaris/*

# Find large files
find /var/www/inventaris -type f -size +10M -exec ls -lh {} \;

# Check failed login attempts
grep "Failed" /var/log/auth.log | tail -50
```

---

## 📚 Additional Resources

- **Laravel Deployment:** https://laravel.com/docs/deployment
- **Nginx Documentation:** https://nginx.org/en/docs/
- **Let's Encrypt:** https://letsencrypt.org/
- **Server Security:** https://www.digitalocean.com/community/tutorials/an-introduction-to-securing-your-linux-vps

---

**Deployment Checklist Complete! ✅**

After following this guide, your application should be running smoothly in production. Remember to monitor logs regularly and keep your system updated with security patches.

**Good luck with your deployment! 🚀**
