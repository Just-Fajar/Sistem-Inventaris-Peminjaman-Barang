# Performance Features Quick Reference

## 🚀 Quick Start

### 1. Enable Redis Caching

**Edit `.env`:**
```env
CACHE_STORE=redis
QUEUE_CONNECTION=redis
```

**Test Redis:**
```bash
php artisan tinker
>>> Cache::put('test', 'value', 60);
>>> Cache::get('test');
```

---

## 📊 Database Optimization

### Check Index Usage
```bash
# Show table structure
php artisan db:show items

# Check query performance
php artisan tinker
>>> DB::connection()->enableQueryLog();
>>> Item::where('category_id', 1)->where('condition', 'baik')->get();
>>> DB::getQueryLog();
```

### Archive Old Data
```bash
# Dry run (no deletion)
php artisan data:archive --dry-run

# Archive data older than 365 days
php artisan data:archive

# Custom retention (180 days)
php artisan data:archive --days=180

# Schedule monthly (add to app/Console/Kernel.php)
$schedule->command('data:archive')->monthly();
```

---

## ⚡ Queue Workers

### Start Queue Workers

**Development:**
```bash
php artisan queue:work
```

**Production (Supervisor):**
```bash
# Copy config
sudo cp docker/supervisor/queue-worker.conf /etc/supervisor/conf.d/

# Update paths in config file
sudo nano /etc/supervisor/conf.d/queue-worker.conf

# Start workers
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start inventaris-queue-worker:*
```

### Monitor Queues
```bash
# Check queue status
php artisan queue:work --once

# View failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush
```

---

## 📦 Cache Management

### Use Cached Data in Controllers
```php
use App\Services\ItemService;

public function index(ItemService $itemService)
{
    $items = $itemService->getCachedItems([
        'category_id' => $request->category_id,
        'search' => $request->search,
        'per_page' => 15
    ]);
    
    return response()->json($items);
}
```

### Clear Cache
```bash
# Clear application cache
php artisan cache:clear

# Clear specific tag
php artisan tinker
>>> Cache::tags(['items'])->flush();

# Clear all Redis cache
redis-cli FLUSHALL
```

---

## 🎨 Frontend Performance

### Analyze Bundle Size
```bash
cd frontend

# Build and analyze
npm run analyze

# Opens browser with bundle visualization
# File: frontend/dist/stats.html
```

### Use LazyImage Component
```jsx
import LazyImage from '@/components/LazyImage';

// Basic usage
<LazyImage 
  src="/storage/items/image.jpg" 
  alt="Item name"
  className="w-full h-48"
/>

// With custom threshold
<LazyImage 
  src="/storage/items/image.jpg" 
  alt="Item name"
  threshold={0.5}  // Load when 50% visible
/>

// With custom placeholder
<LazyImage 
  src="/storage/items/image.jpg" 
  alt="Item name"
  placeholder="/loading.gif"
/>
```

### Optimize Build
```bash
cd frontend

# Production build with optimizations
npm run build

# Check bundle sizes
ls -lh dist/assets/

# Preview production build
npm run preview
```

---

## 🔍 Performance Monitoring

### Check Response Times
```bash
# Enable query logging
php artisan tinker
>>> DB::connection()->enableQueryLog();
>>> // Run your queries
>>> DB::getQueryLog();
```

### Monitor Redis
```bash
# Connect to Redis
redis-cli

# Monitor commands
MONITOR

# Check memory usage
INFO memory

# Check key count
DBSIZE
```

### Check Queue Performance
```bash
# Monitor queue in real-time
php artisan queue:monitor redis

# Check queue size
php artisan tinker
>>> Queue::size('default');
```

---

## 📈 Performance Testing

### Backend Performance Test
```bash
# Run performance verification
scripts/test-performance.bat  # Windows
# or
bash scripts/test-performance.sh  # Linux/Mac

# Test archive command
php artisan data:archive --dry-run

# Test cache
php artisan tinker
>>> Cache::put('test', 'value');
>>> Cache::get('test');
```

### Frontend Performance Test
```bash
cd frontend

# Run tests
npm test

# Check bundle size
npm run build
npm run analyze

# Lighthouse audit (Chrome DevTools)
# 1. Open site in Chrome
# 2. F12 > Lighthouse tab
# 3. Generate report
```

---

## 🛠️ Troubleshooting

### Cache Issues
```bash
# Problem: Cache not working
php artisan cache:clear
php artisan config:clear

# Check Redis connection
redis-cli ping
# Should return: PONG

# Check .env
grep CACHE_STORE .env
```

### Queue Issues
```bash
# Problem: Jobs not processing
# 1. Check queue worker is running
ps aux | grep "queue:work"

# 2. Check failed jobs
php artisan queue:failed

# 3. Restart workers
php artisan queue:restart

# 4. Check logs
tail -f storage/logs/laravel.log
```

### Database Issues
```bash
# Problem: Slow queries
# 1. Check indexes exist
php artisan migrate:status

# 2. Analyze query
EXPLAIN SELECT * FROM items WHERE category_id = 1 AND condition = 'baik';

# 3. Rebuild indexes
php artisan migrate:refresh
```

---

## 📋 Checklist for Production

### Before Deployment:
- [ ] Run `php artisan migrate` (add indexes)
- [ ] Set `CACHE_STORE=redis` in `.env`
- [ ] Set `QUEUE_CONNECTION=redis` in `.env`
- [ ] Configure supervisor for queue workers
- [ ] Add archive command to cron/scheduler
- [ ] Run `npm run build` for frontend
- [ ] Test Redis connection
- [ ] Test queue workers

### After Deployment:
- [ ] Clear all caches (`php artisan cache:clear`)
- [ ] Start queue workers (`supervisorctl start`)
- [ ] Monitor logs for errors
- [ ] Run performance tests
- [ ] Check bundle sizes
- [ ] Verify lazy loading works

---

## 💡 Best Practices

### Caching
```php
// ✅ Good - Use cache with tags
Cache::tags(['items'])->remember('key', 3600, fn() => $data);

// ❌ Bad - Direct query every time
$items = Item::all();
```

### Queue Jobs
```php
// ✅ Good - Dispatch to queue
SendEmailJob::dispatch($user);

// ❌ Bad - Synchronous email
Mail::to($user)->send(new Email());
```

### Database Queries
```php
// ✅ Good - Use eager loading
$borrowings = Borrowing::with(['user', 'item'])->get();

// ❌ Bad - N+1 queries
$borrowings = Borrowing::all();
foreach($borrowings as $b) {
    echo $b->user->name; // Triggers query
}
```

### Frontend Images
```jsx
// ✅ Good - Lazy load
<LazyImage src={url} alt="..." />

// ❌ Bad - Load all immediately
<img src={url} alt="..." />
```

---

## 📊 Performance Targets

### Backend
- **API Response:** < 200ms
- **Database Query:** < 50ms
- **Cache Hit Rate:** > 80%
- **Queue Processing:** < 1s per job

### Frontend
- **Initial Load:** < 2s
- **First Contentful Paint:** < 1.5s
- **Bundle Size:** < 500KB (gzipped)
- **Lighthouse Score:** > 90

### Database
- **Query with Index:** < 20ms
- **Archive Cleanup:** Monthly
- **Index Usage:** > 90% of queries

---

## 🚀 Next Steps

1. **Monitor Performance:**
   - Set up application monitoring (e.g., New Relic, DataDog)
   - Monitor Redis memory usage
   - Track queue job metrics

2. **Optimize Further:**
   - Add query result caching
   - Implement API response caching
   - Consider CDN for assets

3. **Scale as Needed:**
   - Add read replicas for database
   - Horizontal scaling for queue workers
   - Load balancing for application servers

---

**Happy optimizing! 🎯**
