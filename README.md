# Sistem Inventaris dan Peminjaman Barang

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 12">
  <img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.2+">
  <img src="https://img.shields.io/badge/React-19-61DAFB?style=for-the-badge&logo=react&logoColor=black" alt="React 19">
  <img src="https://img.shields.io/badge/Tailwind_CSS-v4-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white" alt="Tailwind CSS v4">
  <img src="https://img.shields.io/badge/Vite-7-646CFF?style=for-the-badge&logo=vite&logoColor=white" alt="Vite">
  <img src="https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL">
  <img src="https://img.shields.io/badge/Redis-Cache_%26_Queue-DC382D?style=for-the-badge&logo=redis&logoColor=white" alt="Redis">
</p>

<p align="center">
  <strong>Sistem informasi manajemen inventaris dan alur peminjaman barang berbasis web dengan arsitektur RESTful API modern, aman, responsif, dan berperforma tinggi.</strong>
</p>

---

## Daftar Isi

- [Ikhtisar Arsitektur](#ikhtisar-arsitektur)
- [Fitur Utama](#fitur-utama)
- [Peningkatan Keamanan](#peningkatan-keamanan)
- [Panduan Instalasi Lokal](#panduan-instalasi-lokal)
- [Panduan Docker untuk Pengembangan Lokal](#panduan-docker-untuk-pengembangan-lokal)
- [Panduan Deployment Produksi VPS](#panduan-deployment-produksi-vps)
- [Pengujian Otomatis](#pengujian-otomatis)
- [Kredensial Pengujian Default](#kredensial-pengujian-default)
- [Lisensi](#lisensi)

---

## Ikhtisar Arsitektur

Project ini dibangun dengan memisahkan backend REST API dan frontend Single Page Application (SPA), menerapkan prinsip clean architecture dan Command Query Separation (CQS):

### Backend (REST API)
- **Framework:** Laravel 12 berbasis PHP 8.2+
- **Layer Arsitektur:** Controller ramping yang mendelegasikan logika bisnis ke Service Layer (`BorrowingService`, `ItemService`)
- **Validasi & Transformasi:** Validasi input terisolasi via FormRequest terdedikasi dan standarisasi respon melalui Laravel API Resources
- **Concurrency Control:** Proteksi race condition pada stok barang menggunakan database transaction dan row-level locking (`lockForUpdate`)
- **Caching & Queue:** Redis caching deterministik dengan invalidasi otomatis berbasis event, serta pemrosesan email notifikasi secara asynchronous melalui queue worker
- **Otomasi Scheduler:** Pengecekan status keterlambatan peminjaman secara periodik via Artisan Console Command (`borrowings:check-overdue`)

### Frontend (Single Page Application)
- **Framework:** React 19 dengan compiler Vite
- **Styling:** Tailwind CSS v4 dengan sistem tema dinamis
- **State Management:** React Context API terisolasi (`AuthContext`, `ThemeContext`, `NotificationContext`)
- **Navigasi & Routing:** React Router v7 dengan lazy loading / code-splitting berbasis komponen
- **Form Handling:** React Hook Form terintegrasi dengan Yup schema validator
- **Data Visualization:** Chart.js dan react-chartjs-2 untuk analitik dashboard

---

## Fitur Utama

### 1. Manajemen Barang dan Kategori
- Operasi CRUD data barang dengan kodefikasi otomatis berformat `ITM-YYYYMMDD-XXXX`
- Pengelompokan barang berdasarkan kategori dinamis
- Optimasi dan kompresi gambar otomatis saat upload menggunakan Intervention Image v3
- Pelacakan stok real-time (total stok vs stok tersedia)
- Klasifikasi kondisi fisik barang: `baik`, `rusak`, dan `hilang`
- Pencarian cerdas dengan teknik debouncing untuk mereduksi beban database

### 2. Alur Kerja Peminjaman
- Pengajuan peminjaman barang oleh pengguna dengan nomor transaksi `BRW-YYYYMMDD-XXXX`
- Workflow persetujuan admin (Approve / Reject) disertai pencatatan catatan penolakan (`rejection_note`)
- Pengurangan stok otomatis saat disetujui dan pengembalian stok saat barang dikembalikan
- Validasi durasi pinjam dan tanggal jatuh tempo
- Deteksi keterlambatan otomatis yang terisolasi dari endpoint listing

### 3. Pengalaman Pengguna (UX) dan Antarmuka Responsif
- **Navigasi Mobile Off-Canvas:** Sidebar drawer yang dapat dibuka-tutup via tombol hamburger menu pada perangkat ponsel/tablet, dilengkapi backdrop overlay dan penutupan otomatis saat navigasi berpindah
- **Tabel Responsif:** Seluruh tabel data dilengkapi scroll horizontal adaptif tanpa merusak struktur halaman
- **Dual-Mode Data Listing:** Pengguna dapat beralih antara mode pagination tradisional atau mode gulir otomatis (Infinite Scroll berbasis `IntersectionObserver`)
- **Mode Gelap (Dark Mode):** Dukungan tema terang, gelap, dan sinkronisasi otomatis dengan preferensi sistem operasi

### 4. Pelaporan dan Ekspor Data
- Dashboard metrik statistik inventaris dan peminjaman
- Grafik tren peminjaman bulanan dan status transaksi
- Ekspor laporan berformat PDF terstruktur via DomPDF
- Ekspor spreadsheet Excel via Maatwebsite Excel

---

## Peningkatan Keamanan

Sistem telah diaudit dan diperkuat dengan standar keamanan tingkat tinggi:
- **Proteksi Hak Akses Registrasi:** Field `role` dilarang (`prohibited`) pada endpoint registrasi publik untuk mencegah eskalasi hak akses ke Administrator
- **Pemulihan Kata Sandi Mandiri:** Alur forgot password dan reset password berbasis token kriptografi satu kali pakai dengan masa kedaluwarsa 60 menit dan notifikasi email antrean
- **Kebijakan Kata Sandi Kuat:** Penegakan aturan `StrongPassword` (minimal 8 karakter, kombinasi huruf besar, huruf kecil, angka, dan simbol)
- **Content Security Policy (CSP):** Header keamanan HTTP dengan kebijakan CSP berbasis nonce tanpa penggunaan `unsafe-inline` atau `unsafe-eval`
- **Autentikasi Token:** Penerbitan dan validasi personal access token menggunakan Laravel Sanctum dengan rate limiting terkonfigurasi

---

## Panduan Instalasi Lokal

### Prasyarat Sistem
- PHP >= 8.2 dengan ekstensi: `pdo_mysql`, `mbstring`, `exif`, `pcntl`, `bcmath`, `gd`, `zip`, `redis`
- Composer >= 2.x
- Node.js >= 20.x dan npm
- MySQL Server 8.0 atau SQLite
- Redis Server (opsional untuk caching & queue lokal)

### Langkah Setup Backend

1. Clone repositori ke direktori lokal:
   ```bash
   git clone https://github.com/Just-Fajar/Sistem-Inventaris-Peminjaman-Barang.git
   cd Sistem-Inventaris-Peminjaman-Barang
   ```

2. Salin file konfigurasi environment:
   ```bash
   cp .env.example .env
   ```

3. Pasang dependensi PHP:
   ```bash
   composer install
   ```

4. Buat kunci enkripsi aplikasi:
   ```bash
   php artisan key:generate
   ```

5. Konfigurasikan koneksi database pada file `.env`:
   ```ini
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=inventaris
   DB_USERNAME=root
   DB_PASSWORD=
   ```

6. Jalankan migrasi dan seeder database:
   ```bash
   php artisan migrate --seed
   ```

7. Buat symbolic link untuk file storage:
   ```bash
   php artisan storage:link
   ```

8. Jalankan server lokal:
   ```bash
   php artisan serve
   ```
   Server backend akan aktif di `http://localhost:8000`.

9. Jalankan Queue Worker dan Scheduler pada terminal terpisah:
   ```bash
   php artisan queue:work
   php artisan schedule:work
   ```

### Langkah Setup Frontend

1. Masuk ke folder frontend:
   ```bash
   cd frontend
   ```

2. Pasang dependensi JavaScript:
   ```bash
   npm install
   ```

3. Jalankan development server:
   ```bash
   npm run dev
   ```
   Aplikasi frontend akan aktif di `http://localhost:5173`.

---

## Panduan Docker untuk Pengembangan Lokal

> **Perhatian:** Konfigurasi Docker pada repositori ini diperuntukkan secara khusus hanya untuk lingkungan pengembangan dan pengujian lokal (development & testing only), bukan untuk deployment produksi.

### Prasyarat
- Docker Desktop terpasang dan berjalan di komputer lokal.

### Menjalankan Container

1. Bangun image container:
   ```bash
   docker compose build
   ```

2. Jalankan seluruh layanan di latar belakang:
   ```bash
   docker compose up -d
   ```

   Script entrypoint container akan otomatis menunggu koneksi database, menjalankan migrasi, membuat storage link, serta mengaktifkan Supervisor.

3. Jalankan pengisian data awal (seeder):
   ```bash
   docker compose exec app php artisan db:seed
   ```

### Alamat Akses Layanan Docker

| Layanan | Alamat Akses | Keterangan |
|---|---|---|
| **Frontend Web** | `http://localhost:5173` | Antarmuka pengguna React |
| **Backend REST API** | `http://localhost:8000/api` | Endpoint API Laravel |
| **PhpMyAdmin GUI** | `http://localhost:8081` | Server: `db`, User: `root`, Password: `secret` |
| **MySQL Port Host** | `localhost:3307` | Port 3307 untuk menghindari konflik port host 3306 |
| **Redis Server** | `localhost:6379` | Cache dan message queue |

### Perintah Operasional Docker
- Melihat status container: `docker compose ps`
- Melihat log aplikasi: `docker compose logs -f app`
- Menghentikan container: `docker compose down`
- Menghentikan dan membersihkan volume data: `docker compose down -v`

---

## Panduan Deployment Produksi VPS

Untuk deployment ke lingkungan produksi, disarankan menggunakan arsitektur server Linux standar (Ubuntu 22.04 / 24.04 LTS bare-metal atau Virtual Private Server) dengan konfigurasi Nginx, PHP-FPM, Supervisor, MySQL 8.0, dan Redis.

### 1. Persiapan Server Linux
Pasang paket yang dibutuhkan pada server:
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-xml \
    php8.2-bcmath php8.2-curl php8.2-gd php8.2-zip php8.2-redis mysql-server redis-server \
    git unzip supervisor certbot python3-certbot-nginx
```

### 2. Deploy Kode Sumber dan Konfigurasi Environment
1. Letakkan kode aplikasi di `/var/www/sistem-inventaris`:
   ```bash
   cd /var/www/sistem-inventaris
   composer install --no-dev --optimize-autoloader --no-interaction
   ```

2. Konfigurasikan `.env` produksi:
   ```ini
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://inventaris.example.com

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=inventaris_prod
   DB_USERNAME=inventaris_user
   DB_PASSWORD=kata_sandi_rahasia

   CACHE_STORE=redis
   QUEUE_CONNECTION=redis
   SESSION_DRIVER=redis
   ```

3. Jalankan migrasi dan optimasi cache Laravel:
   ```bash
   php artisan migrate --force
   php artisan storage:link
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

4. Atur hak akses folder storage dan bootstrap:
   ```bash
   sudo chown -R www-data:www-data /var/www/sistem-inventaris/storage /var/www/sistem-inventaris/bootstrap/cache
   sudo chmod -R 775 /var/www/sistem-inventaris/storage /var/www/sistem-inventaris/bootstrap/cache
   ```

### 3. Build dan Deploy Frontend
Pada server atau CI/CD pipeline:
```bash
cd /var/www/sistem-inventaris/frontend
npm ci
VITE_API_URL=https://inventaris.example.com/api npm run build
```
Hasil build produksi akan tersedia di direktori `frontend/dist`.

### 4. Konfigurasi Nginx
Buat file konfigurasi `/etc/nginx/sites-available/inventaris`:

```nginx
server {
    listen 80;
    server_name inventaris.example.com;

    # Frontend Single Page Application
    root /var/www/sistem-inventaris/frontend/dist;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }

    # Backend API Routing
    location ^~ /api {
        alias /var/www/sistem-inventaris/public;
        try_files $uri $uri/ @laravel;

        location ~ \.php$ {
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME /var/www/sistem-inventaris/public/index.php;
            fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        }
    }

    # Storage Symbolic Link Access
    location ^~ /storage {
        alias /var/www/sistem-inventaris/public/storage;
        access_log off;
        expires 30d;
    }

    location @laravel {
        rewrite ^/api/(.*)$ /index.php?$query_string last;
    }

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
}
```

Aktifkan konfigurasi dan pasang sertifikat SSL:
```bash
sudo ln -s /etc/nginx/sites-available/inventaris /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
sudo certbot --nginx -d inventaris.example.com
```

### 5. Konfigurasi Supervisor (Queue Worker)
Buat file `/etc/supervisor/conf.d/inventaris-worker.conf`:
```ini
[program:inventaris-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/sistem-inventaris/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/sistem-inventaris/storage/logs/worker.log
stopwaitsecs=3600
```
Perbarui proses Supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
```

### 6. Konfigurasi Cron (Task Scheduler)
Jalankan scheduler Laravel setiap menit:
```bash
sudo crontab -u www-data -e
```
Tambahkan baris berikut:
```cron
* * * * * cd /var/www/sistem-inventaris && php artisan schedule:run >> /dev/null 2>&1
```

---

## Pengujian Otomatis

Proyek ini dilengkapi dengan suite pengujian otomatis menyeluruh:

### Pengujian Backend (PHPUnit)
Mencakup pengujian unit dan fitur untuk autentikasi, transaksi peminjaman, proteksi race condition, hashing password reset, serta otorisasi policy:
```bash
php artisan test
```
Atau melalui vendor binary:
```bash
php vendor/phpunit/phpunit/phpunit
```

### Pengujian Frontend (Vitest)
Mencakup pengujian unit komponen UI, state context tema, infinite scroll hook, serta interaksi mobile navigation drawer:
```bash
cd frontend
npx vitest run
```

---

## Kredensial Pengujian Default

Setelah menjalankan seeder database (`php artisan db:seed`), akun-akun berikut tersedia untuk pengujian:

| Role | Email | Password | Keterangan |
|---|---|---|---|
| **Administrator** | `admin@example.com` | `password` | Akses penuh ke seluruh data, approval, dan laporan |
| **Staff / Peminjam** | `user@example.com` | `password` | Akses peminjaman barang dan pelacakan status mandiri |

---

## Lisensi

Proyek ini dirilis di bawah lisensi open source [MIT License](LICENSE).
