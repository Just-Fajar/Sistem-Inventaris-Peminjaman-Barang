# 🐳 Docker Guide

**Sistem Inventaris & Peminjaman Barang**

---

## 📋 Prerequisites

- Docker Engine 20.10+
- Docker Compose 2.0+
- 4GB RAM minimum
- 10GB disk space

## 🚀 Quick Start

### Development Environment

```bash
# Clone repository
git clone https://github.com/Just-Fajar/Sistem-Inventaris-Peminjaman-Barang.git
cd Sistem-Inventaris-Peminjaman-Barang

# Copy environment files
cp .env.example .env
cp frontend/.env.example frontend/.env

# Start containers
docker-compose up -d

# Install dependencies and setup
docker-compose exec app composer install
docker-compose exec app php artisan key:generate
docker-compose exec app php artisan migrate --seed
docker-compose exec app php artisan storage:link

# Access application
# Backend API: http://localhost:8000
# Frontend: http://localhost:5173
# PhpMyAdmin: http://localhost:8080
```

### Production Environment

```bash
# Create .env for docker-compose
cp .env.example .env.docker
# Edit .env.docker with production values

# Start production containers
docker-compose -f docker-compose.prod.yml --env-file .env.docker up -d

# Setup application
docker-compose -f docker-compose.prod.yml exec app php artisan migrate --force
docker-compose -f docker-compose.prod.yml exec app php artisan config:cache
docker-compose -f docker-compose.prod.yml exec app php artisan route:cache
docker-compose -f docker-compose.prod.yml exec app php artisan view:cache
```

## 🛠️ Docker Commands

### Container Management

```bash
# Start all containers
docker-compose up -d

# Stop all containers
docker-compose down

# Restart containers
docker-compose restart

# View logs
docker-compose logs -f

# View specific service logs
docker-compose logs -f app

# Execute command in container
docker-compose exec app php artisan migrate

# Access container shell
docker-compose exec app bash
```

### Database

```bash
# Access MySQL
docker-compose exec db mysql -u root -p

# Import database
docker-compose exec -T db mysql -u root -psecret inventaris < backup.sql

# Export database
docker-compose exec db mysqldump -u root -psecret inventaris > backup.sql

# Run migrations
docker-compose exec app php artisan migrate

# Fresh database
docker-compose exec app php artisan migrate:fresh --seed
```

### Cache & Queue

```bash
# Clear all caches
docker-compose exec app php artisan optimize:clear

# Queue worker status
docker-compose exec app supervisorctl status

# Restart queue workers
docker-compose exec app supervisorctl restart laravel-worker:*
```

## 🔧 Configuration

### Environment Variables

Create `.env.docker` for docker-compose:

```env
DB_DATABASE=inventaris
DB_USERNAME=inventaris
DB_PASSWORD=your_secure_password
DB_ROOT_PASSWORD=your_root_password
REDIS_PASSWORD=your_redis_password
```

### Custom nginx Configuration

Edit `docker/nginx/default.conf` for backend or `docker/nginx/frontend.conf` for frontend.

### Supervisor Configuration

Edit `docker/supervisor/supervisord.conf` to adjust worker processes.

## 📊 Monitoring

### Container Status

```bash
# Check running containers
docker-compose ps

# Check container resource usage
docker stats

# View container logs
docker-compose logs -f
```

### Application Health

```bash
# Check backend health
curl http://localhost:8000/api/health

# Check database connection
docker-compose exec app php artisan tinker
>>> DB::connection()->getPdo();
```

## 🔄 Updates & Maintenance

### Update Application

```bash
# Pull latest code
git pull origin main

# Rebuild containers
docker-compose build --no-cache

# Restart containers
docker-compose up -d

# Run migrations
docker-compose exec app php artisan migrate

# Clear caches
docker-compose exec app php artisan optimize:clear
```

### Backup

```bash
# Backup database
docker-compose exec db mysqldump -u root -psecret inventaris > backup-$(date +%Y%m%d).sql

# Backup volumes
docker run --rm -v inventaris_dbdata:/data -v $(pwd):/backup alpine tar czf /backup/dbdata-backup.tar.gz /data
```

### Restore

```bash
# Restore database
docker-compose exec -T db mysql -u root -psecret inventaris < backup-20260107.sql

# Restore volumes
docker run --rm -v inventaris_dbdata:/data -v $(pwd):/backup alpine sh -c "cd /data && tar xzf /backup/dbdata-backup.tar.gz --strip 1"
```

## 🐛 Troubleshooting

### Port Already in Use

```bash
# Check what's using the port
netstat -ano | findstr :8000

# Stop the process or change port in docker-compose.yml
ports:
  - "8001:80"  # Change host port
```

### Permission Errors

```bash
# Fix permissions
docker-compose exec app chown -R www-data:www-data /var/www
docker-compose exec app chmod -R 755 /var/www
docker-compose exec app chmod -R 775 /var/www/storage /var/www/bootstrap/cache
```

### Container Won't Start

```bash
# View logs for errors
docker-compose logs app

# Remove and recreate containers
docker-compose down -v
docker-compose up -d --force-recreate
```

### Database Connection Failed

```bash
# Check if database is ready
docker-compose exec db mysql -u root -psecret -e "SELECT 1"

# Check DB_HOST in .env (should be 'db' not 'localhost')
docker-compose exec app cat .env | grep DB_HOST
```

## 📚 Additional Resources

- **Docker Documentation:** https://docs.docker.com/
- **Docker Compose:** https://docs.docker.com/compose/
- **Laravel in Docker:** https://laravel.com/docs/sail

---

**Ready to containerize! 🐳**
