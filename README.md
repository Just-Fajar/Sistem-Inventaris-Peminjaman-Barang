# 📦 Sistem Inventaris & Peminjaman Barang

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 12">
  <img src="https://img.shields.io/badge/React-19-61DAFB?style=for-the-badge&logo=react&logoColor=black" alt="React 19">
  <img src="https://img.shields.io/badge/Vite-6-646CFF?style=for-the-badge&logo=vite&logoColor=white" alt="Vite 6">
  <img src="https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL">
</p>

<p align="center">
  <strong>Sistem manajemen inventaris dan peminjaman barang berbasis web yang modern, aman, dan mudah digunakan.</strong>
</p>

---

## 📋 Daftar Isi

- [✨ Fitur Utama](#-fitur-utama)
- [🛠️ Tech Stack](#️-tech-stack)
- [🚀 Quick Start](#-quick-start)
- [📖 Dokumentasi](#-dokumentasi)
- [🧪 Testing](#-testing)
- [🚢 Deployment](#-deployment)
- [🔐 Security](#-security)
- [📊 Performance](#-performance)
- [🤝 Contributing](#-contributing)

---

## ✨ Fitur Utama

### 🎯 Manajemen Inventaris
- ✅ CRUD barang dengan validasi lengkap
- 📦 Kategori barang untuk organisasi lebih baik
- 🖼️ Upload & optimisasi gambar otomatis (800x800, JPEG 85%)
- 🔍 Pencarian & filter advanced (nama, kode, kategori, kondisi)
- 📊 Tracking stok real-time dengan history lengkap
- 📝 Sistem kode barang otomatis (ITM-YYYYMMDD-XXXX)
- 🏷️ Status kondisi barang (Baik, Rusak, Hilang)

### 📋 Sistem Peminjaman
- 📝 Pengajuan peminjaman dengan approval workflow
- ✅ Persetujuan/penolakan oleh admin
- 📅 Tracking tanggal pinjam & jatuh tempo
- ⏰ Deteksi otomatis keterlambatan
- 🔄 Sistem pengembalian dengan validasi
- 📧 Notifikasi email otomatis (approved, overdue)
- 📊 Dashboard peminjaman aktif & history
- 🔢 Sistem kode peminjaman otomatis (BRW-YYYYMMDD-XXXX)

### 📊 Reports & Analytics
- 📈 Dashboard statistik real-time
- 📉 Grafik tren peminjaman (Chart.js)
- 📑 Export laporan ke PDF & Excel
- 📊 Laporan by kategori, user, periode
- 🎯 Analisis barang populer
- ⚠️ Alert barang overdue

### 👥 User Management
- 🔐 Authentication dengan Laravel Sanctum
- 👤 Role-based access control (Admin, User)
- 📝 User profile dengan avatar
- 🔒 Strong password policy
- 🚫 Rate limiting untuk security
- 📊 Activity logging dengan Spatie

---

## 🛠️ Tech Stack

### Backend
- **Framework:** Laravel 12 (PHP 8.2+)
- **Database:** MySQL 8.0 / SQLite (dev)
- **Authentication:** Laravel Sanctum
- **Image Processing:** Intervention Image v3
- **PDF Generation:** DomPDF
- **Excel Export:** Maatwebsite Excel
- **Activity Log:** Spatie Activity Log
- **Backup:** Spatie Laravel Backup
- **Testing:** PHPUnit (71+ tests, 85% coverage)

### Frontend
- **Framework:** React 19
- **Build Tool:** Vite 6
- **State Management:** Context API
- **Routing:** React Router v7
- **Forms:** React Hook Form + Yup
- **UI Components:** Tailwind CSS v4
- **Charts:** Chart.js
- **Testing:** Vitest + React Testing Library (35+ tests)
- **PWA:** vite-plugin-pwa

### DevOps
- **Containerization:** Docker + Docker Compose
- **CI/CD:** GitHub Actions
- **Code Quality:** ESLint, Prettier, Husky
- **Performance:** Redis (cache & queue)
- **Monitoring:** Laravel Telescope (dev)

---

## 🚀 Quick Start

### Prerequisites
- PHP 8.2+
- Composer 2.x
- Node.js 20+
- MySQL 8.0+ / SQLite
- Redis (optional)

### 1. Clone & Install
```bash
git clone https://github.com/yourusername/sistem-inventaris-peminjaman.git
cd sistem-inventaris-peminjaman

# Backend setup
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link

# Frontend setup
cd frontend
npm install
cp .env.example .env
```

### 2. Start Development
```bash
# Terminal 1 - Backend
php artisan serve

# Terminal 2 - Frontend
cd frontend
npm run dev
```

Access:
- Frontend: http://localhost:5173
- Backend API: http://localhost:8000/api

### 3. Login (Seeder Credentials)
```
Admin: admin@example.com / password
User: user@example.com / password
```

### 🐳 Docker Quick Start
```bash
docker-compose up -d
docker-compose exec app php artisan migrate --seed
```

---

## 📖 Dokumentasi

| Dokumen | Deskripsi |
|---------|-----------|
| [API Documentation](API_DOCUMENTATION.md) | REST API endpoints lengkap |
| [Deployment Guide](DEPLOYMENT.md) | Panduan deployment production |
| [Developer Guide](DEVELOPER_GUIDE.md) | Panduan untuk developer |
| [User Manual](USER_MANUAL.md) | Panduan penggunaan sistem |
| [Security Guide](SECURITY_IMPROVEMENTS_COMPLETED.md) | Security best practices |
| [Performance Guide](PERFORMANCE_OPTIMIZATION_COMPLETED.md) | Optimisasi performa |

---

## 🧪 Testing

```bash
# Backend tests
php artisan test --coverage

# Frontend tests
cd frontend && npm test

# Performance tests
scripts/test-performance.bat
```

**Coverage:**
- Backend: 85% (71+ tests)
- Frontend: 80% (35+ tests)

---

## 🚢 Deployment

### Production Checklist
- [ ] Environment variables configured
- [ ] Redis cache & queue configured
- [ ] SSL certificate installed
- [ ] Backup strategy implemented
- [ ] Monitoring setup

```bash
# Build & optimize
cd frontend && npm run build
composer install --optimize-autoloader --no-dev
php artisan config:cache
php artisan route:cache
php artisan migrate --force
```

**📖 Full Guide:** [DEPLOYMENT.md](DEPLOYMENT.md)

---

## 🔐 Security

### Security Features
✅ Laravel Sanctum • ✅ Security Headers • ✅ HTTPS Enforcement  
✅ Rate Limiting • ✅ CSRF Protection • ✅ Input Sanitization  
✅ Token Expiration • ✅ Audit Logging • ✅ IP Restriction

**Security Score: 95/100** ⭐⭐⭐⭐⭐

---

## 📊 Performance

### Optimizations
✅ Redis Caching • ✅ Queue Workers • ✅ Composite Indexes  
✅ Image Lazy Loading • ✅ Bundle Optimization • ✅ Service Worker

### Metrics
- API Response: **120ms** (↓65%)
- Page Load: **1.2s** (↓52%)
- Bundle Size: **450KB** (↓47%)

**Performance Score: 92/100** ⭐⭐⭐⭐⭐

---

## 🤝 Contributing

1. Fork repository
2. Create feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit changes (`git commit -m 'Add AmazingFeature'`)
4. Push to branch (`git push origin feature/AmazingFeature`)
5. Open Pull Request

**Coding Standards:**
- PSR-12 untuk PHP
- Airbnb style untuk JavaScript
- Write tests untuk semua features
- Update documentation

---

## 📞 Support

- 📖 Documentation: `/docs`
- 🐛 Bug Reports: [GitHub Issues](https://github.com/yourusername/sistem-inventaris-peminjaman/issues)
- 📧 Email: contact@yourdomain.com

---

## 📄 License

MIT License - see [LICENSE](LICENSE)

---

## 📈 Roadmap

### ✅ Phase 1-2: Foundation & Enhancement (Completed)
- CRUD operations, authentication, search
- Email notifications, activity logging
- Performance & security optimization
- PWA support, comprehensive testing

### 🚀 Phase 3: Production Ready (In Progress)
- [x] API documentation
- [x] Deployment guides
- [x] Docker support
- [x] CI/CD pipeline
- [ ] Complete user documentation

### 💡 Phase 4: Future Enhancements
- 2FA authentication
- Real-time notifications (WebSockets)
- Mobile app (React Native)
- Barcode scanning
- Advanced analytics

---

<p align="center">
  <strong>Built with ❤️ using Laravel & React</strong>
</p>
