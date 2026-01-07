# 📊 Monitoring & Alerting Guide

**Sistem Inventaris & Peminjaman Barang**

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Application Monitoring](#application-monitoring)
3. [Error Tracking](#error-tracking)
4. [Performance Monitoring](#performance-monitoring)
5. [Infrastructure Monitoring](#infrastructure-monitoring)
6. [Log Management](#log-management)
7. [Alerting Setup](#alerting-setup)
8. [Dashboards](#dashboards)

---

## Overview

This guide covers monitoring and alerting strategies for the Sistem Inventaris application.

### Monitoring Stack

- **Backend:** Laravel Telescope, Laravel Horizon (if Redis queues)
- **Frontend:** Sentry, Web Vitals
- **Infrastructure:** Server monitoring tools
- **Logs:** Laravel Logs, Application Logs
- **Uptime:** Health check endpoints

---

## Application Monitoring

### Laravel Telescope

Already installed and configured for backend monitoring.

#### Setup

```bash
# Publish configuration
php artisan vendor:publish --tag=telescope-config

# Run migrations
php artisan migrate

# Access Telescope dashboard
# URL: https://your-domain.com/telescope
```

#### Configuration

Edit `config/telescope.php`:

```php
'enabled' => env('TELESCOPE_ENABLED', true),

'middleware' => [
    'web',
    Authorize::class,
],

'watchers' => [
    Watchers\QueryWatcher::class => [
        'enabled' => env('TELESCOPE_QUERY_WATCHER', true),
        'slow' => 100, // Log queries slower than 100ms
    ],
    
    Watchers\RequestWatcher::class => [
        'enabled' => env('TELESCOPE_REQUEST_WATCHER', true),
        'size_limit' => 64,
    ],
    
    Watchers\ExceptionWatcher::class => true,
    Watchers\LogWatcher::class => true,
    Watchers\JobWatcher::class => true,
    Watchers\MailWatcher::class => true,
    Watchers\CacheWatcher::class => true,
],
```

#### Key Metrics to Monitor

- **Requests:** Response times, status codes, routes
- **Queries:** Slow queries, N+1 problems, query count
- **Jobs:** Failed jobs, processing time
- **Exceptions:** Error frequency, error types
- **Cache:** Hit/miss ratio, cache usage

### Health Check Endpoint

Create health check endpoint for uptime monitoring.

#### Create Controller

```bash
php artisan make:controller HealthController
```

Edit `app/Http/Controllers/HealthController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class HealthController extends Controller
{
    public function check()
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'storage' => $this->checkStorage(),
        ];
        
        $healthy = !in_array(false, $checks);
        
        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'checks' => $checks,
            'timestamp' => now(),
        ], $healthy ? 200 : 503);
    }
    
    private function checkDatabase()
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    private function checkCache()
    {
        try {
            Cache::put('health_check', true, 10);
            return Cache::get('health_check') === true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    private function checkStorage()
    {
        return is_writable(storage_path());
    }
}
```

#### Add Route

Edit `routes/api.php`:

```php
Route::get('/health', [HealthController::class, 'check']);
```

---

## Error Tracking

### Sentry (Frontend)

Already configured in frontend for React error tracking.

#### Verify Configuration

Check `frontend/src/main.tsx`:

```typescript
Sentry.init({
  dsn: import.meta.env.VITE_SENTRY_DSN,
  environment: import.meta.env.MODE,
  integrations: [
    new Sentry.BrowserTracing(),
    new Sentry.Replay(),
  ],
  tracesSampleRate: 1.0,
  replaysSessionSampleRate: 0.1,
  replaysOnErrorSampleRate: 1.0,
});
```

#### Best Practices

```typescript
// Capture custom errors
try {
  // Your code
} catch (error) {
  Sentry.captureException(error, {
    tags: {
      section: 'items',
      action: 'create',
    },
    extra: {
      itemData: data,
    },
  });
}

// Add breadcrumbs
Sentry.addBreadcrumb({
  category: 'api',
  message: 'Fetching items',
  level: 'info',
});

// Set user context
Sentry.setUser({
  id: user.id,
  email: user.email,
  username: user.name,
});
```

### Sentry (Backend) - Optional

Install Sentry for Laravel:

```bash
composer require sentry/sentry-laravel
php artisan sentry:publish --dsn=YOUR_DSN
```

Configuration in `config/sentry.php`:

```php
'dsn' => env('SENTRY_LARAVEL_DSN'),
'environment' => env('APP_ENV', 'production'),
'traces_sample_rate' => 1.0,
```

---

## Performance Monitoring

### Web Vitals (Frontend)

Already configured for Core Web Vitals tracking.

#### Metrics Tracked

- **LCP:** Largest Contentful Paint
- **FID:** First Input Delay
- **CLS:** Cumulative Layout Shift
- **FCP:** First Contentful Paint
- **TTFB:** Time to First Byte

#### View Metrics

Check `frontend/src/utils/reportWebVitals.ts`:

```typescript
const reportWebVitals = (onPerfEntry?: (metric: Metric) => void) => {
  if (onPerfEntry && onPerfEntry instanceof Function) {
    import('web-vitals').then(({ getCLS, getFID, getFCP, getLCP, getTTFB }) => {
      getCLS(onPerfEntry);
      getFID(onPerfEntry);
      getFCP(onPerfEntry);
      getLCP(onPerfEntry);
      getTTFB(onPerfEntry);
    });
  }
};

// Send to analytics
reportWebVitals((metric) => {
  console.log(metric);
  // Send to Google Analytics, Sentry, etc.
});
```

### Backend Performance

#### Query Optimization

Monitor slow queries with Telescope:

```bash
# Access Telescope queries tab
https://your-domain.com/telescope/queries
```

Look for:
- Queries > 100ms
- N+1 queries
- Missing indexes

#### Profiling with Telescope

Enable profiling in `.env`:

```env
TELESCOPE_ENABLED=true
TELESCOPE_QUERY_WATCHER=true
```

---

## Infrastructure Monitoring

### Server Metrics

#### CPU & Memory

Monitor with `htop`, `top`, or monitoring tools:

```bash
# Install htop
sudo apt install htop

# Monitor resources
htop
```

#### Disk Usage

```bash
# Check disk usage
df -h

# Check folder sizes
du -sh /var/www/*
du -sh /var/log/*

# Monitor in real-time
watch -n 5 df -h
```

#### Process Monitoring

```bash
# Check PHP-FPM processes
ps aux | grep php-fpm

# Check Nginx processes
ps aux | grep nginx

# Check queue workers
ps aux | grep 'queue:work'
```

### Database Monitoring

#### MySQL Performance

```sql
-- Show slow queries
SHOW FULL PROCESSLIST;

-- Show query cache stats
SHOW STATUS LIKE 'Qcache%';

-- Show InnoDB status
SHOW ENGINE INNODB STATUS;

-- Show table sizes
SELECT 
    table_schema as 'Database',
    table_name AS 'Table',
    round(((data_length + index_length) / 1024 / 1024), 2) 'Size in MB'
FROM information_schema.TABLES
ORDER BY (data_length + index_length) DESC;
```

#### Enable Slow Query Log

Edit `/etc/mysql/my.cnf`:

```ini
[mysqld]
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow-query.log
long_query_time = 2
```

### Redis Monitoring

```bash
# Connect to Redis CLI
redis-cli

# Monitor commands
MONITOR

# Get info
INFO

# Get stats
INFO stats

# Check memory
INFO memory
```

---

## Log Management

### Laravel Logs

#### Configuration

Edit `config/logging.php`:

```php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['daily', 'slack'],
        'ignore_exceptions' => false,
    ],

    'daily' => [
        'driver' => 'daily',
        'path' => storage_path('logs/laravel.log'),
        'level' => env('LOG_LEVEL', 'debug'),
        'days' => 14,
    ],

    'slack' => [
        'driver' => 'slack',
        'url' => env('LOG_SLACK_WEBHOOK_URL'),
        'username' => 'Laravel Log',
        'emoji' => ':boom:',
        'level' => 'critical',
    ],
],
```

#### Log Rotation

Create `/etc/logrotate.d/laravel`:

```
/var/www/inventaris/storage/logs/*.log {
    daily
    rotate 14
    compress
    delaycompress
    notifempty
    create 0644 www-data www-data
    sharedscripts
    postrotate
        systemctl reload php8.2-fpm
    endscript
}
```

Test configuration:

```bash
sudo logrotate -d /etc/logrotate.d/laravel
```

### Nginx Logs

#### Access Log

```bash
# View access log
tail -f /var/log/nginx/access.log

# Count requests per IP
awk '{print $1}' /var/log/nginx/access.log | sort | uniq -c | sort -rn | head -10

# Count status codes
awk '{print $9}' /var/log/nginx/access.log | sort | uniq -c | sort -rn
```

#### Error Log

```bash
# View error log
tail -f /var/log/nginx/error.log

# Count error types
grep "error" /var/log/nginx/error.log | cut -d: -f1 | sort | uniq -c
```

---

## Alerting Setup

### Email Alerts

#### Laravel Mail Configuration

Edit `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=alerts@your-domain.com
MAIL_FROM_NAME="${APP_NAME} Alerts"
```

#### Create Alert Notification

```bash
php artisan make:notification SystemAlert
```

Edit `app/Notifications/SystemAlert.php`:

```php
public function via($notifiable)
{
    return ['mail', 'database'];
}

public function toMail($notifiable)
{
    return (new MailMessage)
        ->error()
        ->subject('System Alert: ' . $this->alertType)
        ->line($this->message)
        ->action('View Details', url('/telescope'))
        ->line('Please investigate immediately.');
}
```

### Slack Alerts

#### Setup Slack Webhook

1. Create Slack app: https://api.slack.com/apps
2. Enable Incoming Webhooks
3. Create webhook URL
4. Add to `.env`:

```env
LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
```

#### Send Alert to Slack

```php
use Illuminate\Support\Facades\Log;

Log::channel('slack')->critical('Database connection failed', [
    'server' => gethostname(),
    'time' => now(),
]);
```

### Uptime Monitoring

Use external services:

- **UptimeRobot:** https://uptimerobot.com
- **Pingdom:** https://www.pingdom.com
- **StatusCake:** https://www.statuscake.com

Configure to check:
- Main URL: `https://your-domain.com`
- Health endpoint: `https://your-domain.com/api/health`
- API endpoint: `https://your-domain.com/api/items`

---

## Dashboards

### Telescope Dashboard

Access at: `https://your-domain.com/telescope`

Key sections:
- **Requests:** All HTTP requests
- **Queries:** Database queries
- **Jobs:** Queue jobs
- **Exceptions:** Errors and exceptions
- **Logs:** Application logs
- **Mail:** Sent emails

### Grafana + Prometheus (Optional)

For advanced infrastructure monitoring:

```bash
# Install Prometheus
docker run -d --name prometheus -p 9090:9090 prom/prometheus

# Install Grafana
docker run -d --name grafana -p 3000:3000 grafana/grafana

# Access Grafana
# URL: http://localhost:3000
# Default: admin/admin
```

### Custom Monitoring Dashboard

Create simple monitoring page:

```php
Route::get('/admin/monitor', function() {
    return view('admin.monitor', [
        'disk_usage' => disk_free_space('/') / disk_total_space('/') * 100,
        'queue_size' => DB::table('jobs')->count(),
        'failed_jobs' => DB::table('failed_jobs')->count(),
        'cache_hit_ratio' => Cache::getMemory(),
    ]);
})->middleware('auth', 'admin');
```

---

## Monitoring Checklist

### Daily

- [ ] Check error logs
- [ ] Review failed jobs
- [ ] Monitor disk space
- [ ] Check backup status

### Weekly

- [ ] Review slow queries
- [ ] Analyze performance metrics
- [ ] Check security logs
- [ ] Review user reports

### Monthly

- [ ] Audit access logs
- [ ] Review database performance
- [ ] Check for updates
- [ ] Performance benchmarks

---

**Monitor everything! 📊**
