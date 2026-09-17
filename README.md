# Sistem Inventaris dan Peminjaman Barang

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 12">
  <img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.2+">
  <img src="https://img.shields.io/badge/React-19-61DAFB?style=for-the-badge&logo=react&logoColor=black" alt="React 19">
  <img src="https://img.shields.io/badge/Tailwind_CSS-v4-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white" alt="Tailwind CSS v4">
  <img src="https://img.shields.io/badge/Vite-7-646CFF?style=for-the-badge&logo=vite&logoColor=white" alt="Vite">
  <img src="https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL">
  <img src="https://img.shields.io/badge/Redis-Cache_%26_Queue-DC382D?style=for-the-badge&logo=redis&logoColor=white" alt="Redis">
  <img src="https://img.shields.io/badge/PWA-Ready-10B981?style=for-the-badge&logo=pwa&logoColor=white" alt="PWA Ready">
  <img src="https://img.shields.io/badge/Lighthouse-100%25_A11y_%26_SEO-059669?style=for-the-badge&logo=lighthouse&logoColor=white" alt="Lighthouse 100%">
</p>

<p align="center">
  <strong>Sistem informasi manajemen inventaris dan alur peminjaman barang berbasis web dengan arsitektur RESTful API modern, aman, responsif, aksesibel, dan berkinerja tinggi.</strong>
</p>

---

## Daftar Isi

- [Tentang Proyek](#tentang-proyek)
- [Ikhtisar Arsitektur Sistem](#ikhtisar-arsitektur-sistem)
- [Teknologi dan Dependensi](#teknologi-dan-dependensi)
- [Struktur Direktori Proyek](#struktur-direktori-proyek)
- [Fitur-Fitur Utama](#fitur-fitur-utama)
- [Kontrol Akses Berbasis Peran (RBAC)](#kontrol-akses-berbasis-peran-rbac)
- [Alur Kerja Transaksi Peminjaman](#alur-kerja-transaksi-peminjaman)
- [Peningkatan Keamanan dan Integritas Sistem](#peningkatan-keamanan-dan-integritas-sistem)
- [Dokumentasi Endpoint RESTful API](#dokumentasi-endpoint-restful-api)
- [Skema Basis Data dan Relasi](#skema-basis-data-dan-relasi)
- [Panduan Instalasi Lokal](#panduan-instalasi-lokal)
- [Panduan Docker untuk Lingkungan Pengujian](#panduan-docker-untuk-lingkungan-pengujian)
- [Panduan Deployment Produksi VPS](#panduan-deployment-produksi-vps)
- [Pengujian Otomatis dan Kualitas Kode](#pengujian-otomatis-dan-kualitas-kode)
- [Kredensial Pengujian Default](#kredensial-pengujian-default)
- [Lisensi](#lisensi)

---

## Tentang Proyek

Sistem Inventaris dan Peminjaman Barang adalah platform enterprise berbasis web yang dirancang untuk mengotomatisasi seluruh siklus hidup pengelolaan inventaris kantor, peralatan operasional, dan transaksi peminjaman barang.

Platform ini menyelesaikan berbagai kendala operasional yang umum terjadi pada pencatatan manual:
- Kehilangan aset akibat pelacakan barang yang tidak transparan.
- Kesalahan stok akibat peminjaman bersamaan (race conditions).
- Keterlambatan pengembalian barang tanpa sistem peringatan otomatis.
- Birokrasi persetujuan peminjaman yang lambat dan tanpa audit trail yang jelas.
- Antarmuka sistem internal yang sulit digunakan pada perangkat mobile atau memiliki aksesibilitas rendah.

Sistem dibangun dengan memisahkan Backend RESTful API dan Frontend Single Page Application (SPA), menerapkan prinsip Clean Architecture, Command Query Separation (CQS), dan kepatuhan penuh terhadap standar web modern (WCAG AAA, PWA, dan Lighthouse 100/100).

---

## Ikhtisar Arsitektur Sistem

### 1. Diagram Arsitektur Komponen

```text
[ Browser / Mobile Device ]
          |
          |  (HTTP/HTTPS, JSON, PWA Service Worker)
          v
[ Reverse Proxy Nginx / Port 5173 (Prod: 80/443) ]
     |                                      |
     | (Static SPA Assets / Web Manifest)   | (Proxy /api/*)
     v                                      v
[ Frontend React 19 + Vite ]          [ Backend Laravel 12 API ]
     - Tailwind CSS v4                     - FormRequest Validation Layer
     - Context State (Auth, Theme)         - Controller Layer (Slim)
     - Router v7 + AdminRoute Guard        - Service Layer (Business Logic)
     - Infinite Scroll / Pagination        - Sanctum Token Authentication
     - Dark Mode (WCAG AAA)                - DB Transactions (lockForUpdate)
                                           - Policy Authorization Layer
                                                    |
                                  +-----------------+-----------------+
                                  |                                   |
                                  v                                   v
                        [ MySQL 8.0 Engine ]                [ Redis In-Memory ]
                          - InnoDB Tables                     - Cache Tags & TTL
                          - Composite Indexes                 - Asynchronous Queues
                          - Foreign Key Cascades              - Scheduled Jobs
```

### 2. Pola Desain Backend (Laravel 12)
- **Service-Oriented Architecture:** Controller bertindak sebagai transport layer ramping. Seluruh kalkulasi stok, penomoran kode otomatis, pemrosesan transaksi, dan pembatalan didelegasikan ke Service Layer terisolasi (`BorrowingService`, `ItemService`, `ReportService`).
- **FormRequest Isolation:** Validasi data input dipisahkan sepenuhnya ke dalam FormRequest classes terdedikasi, menjamin integritas tipe data sebelum mencapai controller.
- **API Resources Transformation:** Format data keluar distandarisasi menggunakan Laravel API Resources (`BorrowingResource`, `ItemResource`, `CategoryResource`), mencegah kebocoran atribut sensitif basis data.
- **Pessimistic Concurrency Control:** Pengurangan dan pengembalian kuantitas stok barang diproteksi menggunakan database transaction dengan penguncian tingkat baris (`lockForUpdate`), meniadakan potensi race condition pada transaksi serentak.
- **Event-Driven Cache Invalidation:** Redis cache dimanfaatkan secara deterministik untuk katalog barang dan kategori dengan invalidasi otomatis ketika data diperbarui.
- **Asynchronous Queue Worker:** Pengiriman notifikasi email dan audit logging diproses di latar belakang menggunakan antrean Redis.
- **Automated Cron Scheduler:** Perintah artisan `borrowings:check-overdue` mendeteksi transaksi yang melewati batas tenggat pengembalian secara otomatis setiap menit.

### 3. Pola Desain Frontend (React 19 + Vite)
- **Atomic & Reusable Components:** Komponen UI modular terisolasi pada folder `components/` dan `components/common/` (Button, Input, Select, Modal, ThemeToggle, Header, Sidebar).
- **Route Guarding:** Implementasi komponen pelindung rute `AdminRoute` pada React Router v7 yang memverifikasi peran pengguna dari `authService.isAdmin()`. Upaya akses langsung URL terlarang akan dialihkan seketika ke `/dashboard`.
- **Dual-Mode Data Listing:** Pengguna dapat beralih antara paginasi tombol tradisional dan infinite scroll otomatis berbasis `IntersectionObserver`.
- **Accessible Form Controls:** Seluruh elemen formulir dan tombol icon-only dilengkapi asosiasi label eksplisit (`htmlFor`, `id`, `aria-label`).
- **Progressive Web App (PWA):** Dukungan penuh PWA melalui `@vite-plugin-pwa` dengan Web App Manifest valid dan service worker Workbox untuk precaching aset statis dan runtime caching API.

---

## Teknologi dan Dependensi

### Backend
| Teknologi | Versi | Peran dan Kegunaan |
|---|---|---|
| **PHP** | 8.2+ | Bahasa pemrograman backend |
| **Laravel Framework** | 12.x | Framework backend RESTful API modern |
| **Laravel Sanctum** | 4.x | Autentikasi API berbasis personal access token |
| **MySQL** | 8.0 | Sistem basis data relasional utama (InnoDB Engine) |
| **Redis** | Alpine | Cache layer berkecepatan tinggi dan antrean background worker |
| **Intervention Image** | 3.x | Manipulasi, resizing, dan kompresi gambar upload |
| **DomPDF (barryvdh/laravel-dompdf)** | 3.x | Generator laporan inventaris dan peminjaman berformat PDF |
| **Maatwebsite Excel** | 3.x | Generator laporan ekspor berformat spreadsheet Excel (XLSX) |
| **Spatie Laravel Activitylog** | 4.x | Pencatatan rekam jejak aktivitas sistem (audit log) |

### Frontend
| Teknologi | Versi | Peran dan Kegunaan |
|---|---|---|
| **React** | 19.x | Library antarmuka Single Page Application (SPA) |
| **Vite (rolldown-vite)** | 7.x | Build tool dan development bundler berkecepatan tinggi |
| **Tailwind CSS** | 4.x | Framework utility-first CSS dengan dukungan dark mode dinamis |
| **React Router** | 7.x | Routing sisi klien dengan lazy loading komponen dan route guards |
| **React Hook Form** | 7.x | Manajemen formulir performan dengan re-render minimal |
| **Yup** | 1.x | Skema validasi formulir sisi klien |
| **Chart.js & react-chartjs-2** | 4.x / 5.x | Rendering grafik tren transaksi dan analitik visual |
| **Axios** | 1.x | Klien HTTP berbasis Promise dengan interseptor token dan error |
| **Vite PWA (vite-plugin-pwa)** | 1.x | Generasi service worker dan konfigurasi Web App Manifest |

### Alat Pengujian dan DevOps
| Alat | Peran dan Kegunaan |
|---|---|
| **PHPUnit** | Suite pengujian otomatis backend (Unit, Feature, Policy, Concurrency) |
| **Vitest & Testing Library** | Suite pengujian otomatis frontend (Komponen, Hook, Context, A11y, RBAC) |
| **Docker & Docker Compose** | Kontainerisasi multi-layanan untuk lingkungan pengujian lokal |
| **Nginx** | Reverse proxy, kompresi Gzip, static asset delivery, dan penegakan header keamanan |
| **Supervisor** | Pengawas proses queue worker Laravel di lingkungan produksi |

---

## Struktur Direktori Proyek

```text
Sistem-Inventaris-Peminjaman-Barang/
├── app/
│   ├── Console/Commands/       # Artisan commands (borrowings:check-overdue)
│   ├── Http/
│   │   ├── Controllers/Api/    # Controller API ramping (Auth, Item, Borrowing, User, dll.)
│   │   ├── Middleware/         # AdminMiddleware, SecurityHeaders, RateLimiting
│   │   ├── Requests/           # FormRequest validasi input
│   │   └── Resources/          # Transformasi respon API Resources
│   ├── Models/                 # Model Eloquent (User, Item, Category, Borrowing, Notification)
│   ├── Policies/               # Kebijakan otorisasi hak akses peran
│   └── Services/               # Logika bisnis utama (BorrowingService, ItemService, ReportService)
├── bootstrap/                  # Inisialisasi framework Laravel
├── config/                     # Berkas konfigurasi sistem (app, database, cache, auth, filesystems)
├── database/
│   ├── factories/              # Factory data uji pengujian otomatis
│   ├── migrations/             # Skema tabel, indeks performa, dan relasi basis data
│   └── seeders/                # Pengisian data awal akun default, kategori, dan barang
├── docker/                     # Berkas konfigurasi Nginx, MySQL, dan Supervisor kontainer
├── frontend/
│   ├── public/                 # Ikon biner PWA (192x192, 512x512, apple-touch, favicon), robots.txt
│   ├── src/
│   │   ├── components/         # Komponen antarmuka (Header, Sidebar, CategoryForm, common/)
│   │   ├── contexts/           # State context React (AuthContext, ThemeContext)
│   │   ├── hooks/              # Custom hook (useInfiniteScroll)
│   │   ├── pages/              # Halaman SPA (Dashboard, ItemList, BorrowingList, UserList, dll.)
│   │   ├── services/           # Service API klien Axios (authService, itemService, borrowingService)
│   │   └── test/               # Suite pengujian Vitest (Komponen, Layanan, Hook, A11y, RBAC)
│   ├── index.html              # Template HTML utama dengan metadata SEO dan PWA
│   ├── package.json            # Dependensi JavaScript dan skrip eksekusi
│   └── vite.config.js          # Konfigurasi Vite, Tailwind, dan VitePWA
├── routes/
│   ├── api.php                 # Rute RESTful API (dual versioning: unversioned & v1)
│   └── console.php             # Penjadwalan perintah scheduler
├── storage/                    # Tempat penyimpanan berkas upload, berkas log, dan cache
├── tests/
│   ├── Feature/                # 31 berkas pengujian integrasi dan fitur API
│   └── Unit/                   # 5 berkas pengujian logika unit backend
├── docker-compose.yml          # Konfigurasi orkestrasi kontainer lokal
└── README.md                   # Dokumentasi teknis proyek
```

---

## Fitur-Fitur Utama

### 1. Manajemen Master Barang
- **Kodefikasi Otomatis:** Sistem menerbitkan nomor kode unik terstandarisasi dengan format `ITM-YYYYMMDD-XXXX`.
- **Kategori Dinamis:** Pengelompokan barang ke dalam kategori master dengan pembaruan counter jumlah item secara otomatis.
- **Pemrosesan Gambar Teroptimasi:** Setiap foto barang yang diunggah dikompresi dan dikonversi secara otomatis untuk menjaga efisiensi ruang penyimpanan dan kecepatan loading halaman.
- **Pelacakan Stok Riil:** Menjaga pencatatan total stok dan stok tersedia secara terpisah guna menghindari konflik saat proses peminjaman berjalan.
- **Status Kondisi Barang:** Pengelompokan kondisi fisik barang menjadi `baik`, `rusak`, atau `hilang`.
- **Pencarian Cerdas:** Fasilitas pencarian data dengan teknik debouncing guna menekan frekuensi kueri berlebih ke basis data.
- **Penghapusan Massal (Bulk Delete):** Kemampuan menghapus banyak barang sekaligus dengan validasi integritas data keterkaitan peminjaman aktif.

### 2. Manajemen Kategori
- Pembuatan, pembaruan, dan penghapusan data kategori inventaris.
- Penghitungan jumlah barang terkait secara otomatis.
- Penyaringan dan pencarian nama kategori secara instan.

### 3. Alur Peminjaman Barang Lengkap
- **Pengajuan Peminjaman:** Pengguna memilih barang, kuantitas pinjam, tanggal peminjaman, serta tanggal estimasi pengembalian.
- **Kode Transaksi Terstandarisasi:** Diterbitkan otomatis dengan pola `BRW-YYYYMMDD-XXXX`.
- **Alur Persetujuan Administrator:** Administrator dapat menyetujui (Approve) atau menolak (Reject) peminjaman. Penolakan mewajibkan pengisian alasan penolakan (`rejection_note`).
- **Integritas Stok Otomatis:** Stok berkurang otomatis pada saat peminjaman disetujui, dan bertambah kembali ketika pengembalian barang diselesaikan.
- **Deteksi Keterlambatan Otomatis:** Perintah scheduler backend mendeteksi peminjaman yang melampaui tanggal jatuh tempo dan mengubah status menjadi `terlambat`.
- **Perpanjangan Durasi (Extend):** Fasilitas permohonan penambahan durasi pinjam yang tercatat secara transparan.

### 4. Pengalaman Pengguna (UX), Aksesibilitas (A11y), dan Mode Gelap
- **Navigasi Mobile Off-Canvas:** Navigasi samping ponsel berupa drawer geser yang dilengkapi overlay latar belakang dan penutupan otomatis saat tautan berpindah.
- **Tabel Responsif:** Seluruh tabel data dilengkapi scroll horizontal adaptif tanpa merusak struktur halaman pada layar sempit.
- **Dual-Mode Data Listing:** Tombol pengalih mode paginasi tradisional atau infinite scroll otomatis.
- **Kontras Tinggi Mode Gelap:** Pemilihan palet warna dark mode yang memenuhi rasio kontras WCAG AAA (rasio >13:1).
- **Aksesibilitas Sempurna:** Seluruh tombol berbasis ikon memiliki accessible name (`aria-label`), dan formulir memiliki keterkaitan `htmlFor` dan `id` eksplisit.
- **Skor Lighthouse 100/100:** Terverifikasi meraih nilai 100 pada kategori Accessibility, SEO, dan Best Practices baik pada audit desktop maupun mobile.

### 5. Progressive Web App (PWA)
- Terintegrasi penuh dengan Web App Manifest standar.
- Memiliki berkas binary PNG asli (`icon-192x192.png`, `icon-512x512.png`, `apple-touch-icon.png`, `favicon.png`, `favicon.ico`).
- Dilengkapi service worker Workbox untuk precaching aset aplikasi dan offline browsing.

### 6. Pelaporan dan Analitik Ekspor
- Visualisasi metrik total barang, barang dipinjam, stok menipis, dan keterlambatan.
- Grafik tren peminjaman bulanan interaktif menggunakan Chart.js.
- Ekspor laporan komprehensif ke format dokumen PDF menggunakan DomPDF.
- Ekspor data mentah ke lembar kerja spreadsheet Microsoft Excel (XLSX).

### 7. Audit Trail dan Log Aktivitas
- Pencatatan otomatis setiap tindakan Create, Update, Delete, Approval, dan Rejection melalui Spatie Activitylog.
- Informasi pencatat mencakup ID pelaku (causer), model target (subject), nilai perubahan lama dan baru, serta cap waktu (timestamp).

---

## Kontrol Akses Berbasis Peran (RBAC)

Sistem menerapkan pembagian hak akses secara tegas antara dua peran utama: **Administrator** dan **Staff**:

| Modul / Tindakan | Administrator | Staff / Peminjam | Catatan Keamanan |
|---|---|---|---|
| **Melihat Dashboard** | Akses Penuh | Akses Pribadi | Staff hanya melihat ringkasan status pinjaman miliknya |
| **Daftar Barang (`/items`)** | Akses Penuh | Read-Only | Tombol Tambah, Edit, dan Hapus tersembunyi bagi staff |
| **Form Barang (`/items/create`, `edit`)** | Akses Penuh | Diblokir | URL langsung dicegat oleh `AdminRoute` dan diarahkan ke dashboard |
| **Daftar Kategori (`/categories`)** | Akses Penuh | Read-Only | Tombol Tambah dan seluruh kolom Aksi tersembunyi bagi staff |
| **Persetujuan Peminjaman (Approve/Reject)** | Akses Penuh | Dilarang | Endpoint API diproteksi dengan policy otorisasi |
| **Pengajuan Peminjaman** | Tersedia | Tersedia | Dilengkapi validasi kuantitas terhadap stok tersedia |
| **Pengembalian Barang** | Akses Penuh | Akses Penuh | Menyesuaikan stok dan menghentikan status pinjam aktif |
| **Melihat Laporan & Ekspor** | Akses Penuh | Terbatas | Staff tidak dapat mengunduh rekapan data master |
| **Manajemen Pengguna (`/users/*`)** | Akses Penuh | Diblokir | Hanya dapat diakses oleh Administrator |

---

## Alur Kerja Transaksi Peminjaman

```text
[ Pengguna / Staff ]
       |
       | 1. Memilih barang dan durasi pinjam (POST /api/borrowings)
       v
[ Status: PENDING ]
(Stok belum berkurang)
       |
       +------------------------------------+
       |                                    |
       | (Persetujuan Admin)                | (Penolakan Admin + Catatan)
       v                                    v
[ Status: DIPINJAM ]                 [ Status: DITOLAK ]
(Stok dikunci via lockForUpdate,      (Transaksi dihentikan, alasan penolakan
 stok berkurang secara atomik)         tercatat dalam rejection_note)
       |
       +------------------------------------+
       |                                    |
       | (Barang dikembalikan tepat waktu)  | (Melewati tenggat tanggal return_date)
       v                                    v
[ Status: DIKEMBALIKAN ]             [ Status: TERLAMBAT ]
(Stok dipulihkan secara otomatis,     (Terdeteksi via artisan borrowings:check-overdue,
 actual_return_date tercatat)          notifikasi peringatan dikirim)
                                            |
                                            | (Pengembalian fisik dilakukan)
                                            v
                                     [ Status: DIKEMBALIKAN ]
                                     (Stok dipulihkan, status riwayat tersimpan)
```

---

## Peningkatan Keamanan dan Integritas Sistem

1. **Proteksi Registrasi Akun:** Atribut `role` dilarang keras (`prohibited`) pada endpoint registrasi publik `/api/auth/register` untuk mengeliminasi risiko eskalasi hak akses ilegal ke Administrator.
2. **Pemulihan Kata Sandi Aman:** Fitur pemulihan kata sandi menggunakan token kriptografi acak satu kali pakai dengan masa kedaluwarsa 60 menit yang dikirim melalui email antrean.
3. **Kebijakan Kata Sandi Ketat (`StrongPassword`):** Mewajibkan pengguna menggunakan kata sandi dengan minimal 8 karakter yang mengandung perpaduan huruf besar, huruf kecil, angka, dan karakter khusus/simbol.
4. **Content Security Policy (CSP) Tanpa Nilai Rentan:** Middleware menyematkan header HTTP CSP berbasis nonce tanpa memperbolehkan `unsafe-inline` atau `unsafe-eval`.
5. **Autentikasi Token Laravel Sanctum:** Validasi token API dengan masa berlaku terkelola dan pembatasan frekuensi kueri (Rate Limiting):
   - Endpoint autentikasi: 10 request / menit.
   - Endpoint protected data: 60 request / menit.
6. **Proteksi Concurrency Control:** Pencegahan race condition stok barang menggunakan transaksi basis data dengan mekanisme row-locking (`DB::transaction` dan `lockForUpdate`).

---

## Dokumentasi Endpoint RESTful API

Sistem menyediakan antarmuka REST API berformat JSON dengan dukungan dual-routing: versi umum `/api/...` dan versi terstruktur `/api/v1/...`.

### 1. Autentikasi dan Akun
| Method | Endpoint | Deskripsi | Akses |
|---|---|---|---|
| `POST` | `/api/auth/register` | Pendaftaran akun baru (role default: staff) | Publik |
| `POST` | `/api/auth/login` | Autentikasi akun dan penerbitan token Sanctum | Publik |
| `POST` | `/api/auth/forgot-password` | Pengiriman token pemulihan kata sandi ke email | Publik |
| `POST` | `/api/auth/reset-password` | Pengaturan ulang kata sandi dengan token valid | Publik |
| `POST` | `/api/auth/logout` | Revokasi personal access token aktif | Terautentikasi |
| `GET` | `/api/auth/me` | Mengambil data profil pengguna yang sedang login | Terautentikasi |

### 2. Manajemen Master Barang
| Method | Endpoint | Deskripsi | Akses |
|---|---|---|---|
| `GET` | `/api/items` | Menampilkan daftar barang dengan filter dan paginasi | Terautentikasi |
| `POST` | `/api/items` | Menambahkan data barang baru beserta upload gambar | Administrator |
| `GET` | `/api/items/{id}` | Menampilkan detail data spesifik barang | Terautentikasi |
| `POST` / `PUT` | `/api/items/{id}` | Memperbarui data barang atau mengganti foto | Administrator |
| `DELETE` | `/api/items/{id}` | Menghapus data barang | Administrator |
| `DELETE` | `/api/items/bulk-delete` | Menghapus banyak data barang sekaligus | Administrator |
| `GET` | `/api/items/search-suggestions`| Mendapatkan saran pencarian cepat nama/kode barang | Terautentikasi |

### 3. Manajemen Kategori
| Method | Endpoint | Deskripsi | Akses |
|---|---|---|---|
| `GET` | `/api/categories` | Menampilkan daftar seluruh kategori | Terautentikasi |
| `POST` | `/api/categories` | Membuat data kategori baru | Administrator |
| `GET` | `/api/categories/{id}` | Menampilkan detail kategori beserta data relasi | Terautentikasi |
| `PUT` | `/api/categories/{id}` | Memperbarui nama dan deskripsi kategori | Administrator |
| `DELETE` | `/api/categories/{id}` | Menghapus kategori | Administrator |

### 4. Transaksi Peminjaman
| Method | Endpoint | Deskripsi | Akses |
|---|---|---|---|
| `GET` | `/api/borrowings` | Menampilkan riwayat transaksi peminjaman | Terautentikasi |
| `POST` | `/api/borrowings` | Mengajukan peminjaman barang baru (status: pending) | Terautentikasi |
| `GET` | `/api/borrowings/{id}` | Menampilkan detail spesifik transaksi peminjaman | Terautentikasi |
| `POST` / `PUT` | `/api/borrowings/{id}/approve` | Menyetujui peminjaman dan memotong kuantitas stok | Administrator |
| `POST` / `PUT` | `/api/borrowings/{id}/reject` | Menolak peminjaman disertai catatan penolakan | Administrator |
| `POST` / `PUT` | `/api/borrowings/{id}/return` | Memproses pengembalian barang dan memulihkan stok | Terautentikasi |
| `POST` / `PUT` | `/api/borrowings/{id}/extend` | Mengajukan perpanjangan durasi pinjam | Terautentikasi |
| `GET` | `/api/borrowings/my/list` | Menampilkan daftar peminjaman pribadi milik pengguna | Terautentikasi |

### 5. Laporan dan Ekspor
| Method | Endpoint | Deskripsi | Akses |
|---|---|---|---|
| `GET` | `/api/reports/borrowings` | Statistik rangkuman seluruh peminjaman | Terautentikasi |
| `GET` | `/api/reports/items` | Analitik ketersediaan dan utilisasi barang | Terautentikasi |
| `GET` | `/api/reports/overdue` | Data rekapitulasi keterlambatan pengembalian | Terautentikasi |
| `GET` | `/api/reports/monthly` | Data tren transaksi bulanan untuk grafik analitik | Terautentikasi |
| `GET` | `/api/reports/export/borrowings/pdf` | Mengunduh berkas laporan peminjaman format PDF | Terautentikasi |
| `GET` | `/api/reports/export/borrowings/excel` | Mengunduh berkas laporan peminjaman format Excel | Terautentikasi |

### 6. Notifikasi, Profil, dan Log
| Method | Endpoint | Deskripsi | Akses |
|---|---|---|---|
| `GET` | `/api/notifications` | Menampilkan daftar notifikasi pengguna | Terautentikasi |
| `GET` | `/api/notifications/unread-count` | Mengambil jumlah notifikasi yang belum dibaca | Terautentikasi |
| `POST` | `/api/notifications/{id}/read` | Menandai satu notifikasi telah dibaca | Terautentikasi |
| `POST` | `/api/notifications/mark-all-read` | Menandai seluruh notifikasi telah dibaca | Terautentikasi |
| `PUT` | `/api/profile` | Memperbarui nama dan informasi kontak profil | Terautentikasi |
| `PUT` | `/api/profile/password` | Memperbarui kata sandi akun | Terautentikasi |
| `GET` | `/api/activity-logs` | Melihat rekam jejak aktivitas audit sistem | Administrator |
| `GET` | `/api/users` | Mengelola data dan hak akses akun pengguna | Administrator |
| `GET` | `/api/health` | Pemeriksaan kesehatan layanan backend dan basis data | Publik |

---

## Skema Basis Data dan Relasi

Sistem menggunakan mesin penyimpanan InnoDB MySQL 8.0 dengan dukungan foreign key cascade, pemeriksaan indeks komposit performa, dan integritas data ACID:

### 1. Tabel `users`
Menyimpan data identitas akun pengguna:
- `id` (BIGINT, Primary Key, Auto Increment)
- `name` (VARCHAR 255)
- `email` (VARCHAR 255, Unique Index)
- `password` (VARCHAR 255)
- `role` (ENUM: 'admin', 'staff', Default: 'staff', Index)
- `email_verified_at` (TIMESTAMP, Nullable)
- `created_at`, `updated_at` (TIMESTAMP)

### 2. Tabel `categories`
Menyimpan kelompok klasifikasi barang:
- `id` (BIGINT, Primary Key, Auto Increment)
- `name` (VARCHAR 255, Index)
- `description` (TEXT, Nullable)
- `created_at`, `updated_at` (TIMESTAMP)

### 3. Tabel `items`
Menyimpan data detail katalog barang:
- `id` (BIGINT, Primary Key, Auto Increment)
- `category_id` (BIGINT, Foreign Key -> `categories.id`, ON DELETE RESTRICT)
- `code` (VARCHAR 50, Unique Index)
- `name` (VARCHAR 255, Index)
- `description` (TEXT, Nullable)
- `image` (VARCHAR 255, Nullable)
- `stock` (INT UNSIGNED, Default: 0)
- `available_stock` (INT UNSIGNED, Default: 0)
- `condition` (ENUM: 'baik', 'rusak', 'hilang', Default: 'baik', Index)
- `created_at`, `updated_at` (TIMESTAMP)

### 4. Tabel `borrowings`
Menyimpan data transaksi operasional peminjaman:
- `id` (BIGINT, Primary Key, Auto Increment)
- `user_id` (BIGINT, Foreign Key -> `users.id`, ON DELETE RESTRICT)
- `item_id` (BIGINT, Foreign Key -> `items.id`, ON DELETE RESTRICT)
- `code` (VARCHAR 50, Unique Index)
- `quantity` (INT UNSIGNED, Default: 1)
- `borrow_date` (DATE, Index)
- `return_date` (DATE, Index)
- `actual_return_date` (DATE, Nullable)
- `status` (ENUM: 'pending', 'dipinjam', 'dikembalikan', 'terlambat', 'ditolak', Default: 'pending', Index)
- `approved_at` (TIMESTAMP, Nullable)
- `rejection_note` (TEXT, Nullable)
- `notes` (TEXT, Nullable)
- `created_at`, `updated_at` (TIMESTAMP)

### 5. Tabel Pendukung Lainnya
- `notifications`: Menyimpan pesan pemberitahuan pengguna (`user_id`, `title`, `message`, `type`, `is_read`).
- `activity_log`: Rekam jejak Spatie Activitylog (`log_name`, `description`, `subject_type`, `subject_id`, `causer_type`, `causer_id`, `properties`).
- `personal_access_tokens`: Token otentikasi Sanctum (`tokenable_type`, `tokenable_id`, `token`, `abilities`, `last_used_at`, `expires_at`).

---

## Panduan Instalasi Lokal

### Prasyarat Sistem
- PHP >= 8.2 dengan ekstensi terpasang: `pdo_mysql`, `mbstring`, `exif`, `pcntl`, `bcmath`, `gd`, `zip`, `redis`
- Composer >= 2.x
- Node.js >= 20.x dan npm
- MySQL Server 8.0 atau SQLite 3
- Redis Server (sangat direkomendasikan untuk fungsionalitas queue dan cache)

### Langkah Setup Backend

1. Buka terminal dan clone repositori:
   ```bash
   git clone https://github.com/Just-Fajar/Sistem-Inventaris-Peminjaman-Barang.git
   cd Sistem-Inventaris-Peminjaman-Barang
   ```

2. Salin template konfigurasi lingkungan `.env`:
   ```bash
   cp .env.example .env
   ```

3. Pasang paket dependensi PHP:
   ```bash
   composer install
   ```

4. Buat kunci enkripsi aplikasi:
   ```bash
   php artisan key:generate
   ```

5. Konfigurasikan koneksi basis data pada `.env`:
   ```ini
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=inventaris
   DB_USERNAME=root
   DB_PASSWORD=
   
   QUEUE_CONNECTION=redis
   CACHE_STORE=redis
   ```

6. Jalankan migrasi dan pengisian data uji awal (seeder):
   ```bash
   php artisan migrate --seed
   ```

7. Buat tautan simbolis penyimpanan publik:
   ```bash
   php artisan storage:link
   ```

8. Jalankan server lokal:
   ```bash
   php artisan serve
   ```
   API backend aktif di `http://localhost:8000`.

9. Jalankan antrean background worker dan scheduler pada jendela terminal terpisah:
   ```bash
   php artisan queue:work
   php artisan schedule:work
   ```

### Langkah Setup Frontend

1. Masuk ke direktori frontend:
   ```bash
   cd frontend
   ```

2. Pasang paket dependensi Node.js:
   ```bash
   npm install
   ```

3. Jalankan development server:
   ```bash
   npm run dev
   ```
   Aplikasi antarmuka web aktif di `http://localhost:5173`.

---

## Panduan Docker untuk Lingkungan Pengujian

> **Pemberitahuan:** Konfigurasi Docker pada repositori ini diperuntukkan secara khusus untuk lingkungan pengembangan dan pengujian lokal (development and testing only), bukan untuk deployment langsung ke produksi.

### Prasyarat
- Docker Desktop aktif pada komputer lokal.

### Menjalankan Seluruh Layanan

1. Bangun image kontainer:
   ```bash
   docker compose build
   ```

2. Aktifkan seluruh container dalam mode background:
   ```bash
   docker compose up -d
   ```
   Script entrypoint container akan otomatis menunggu koneksi database, mengeksekusi migrasi, membuat storage link, serta mengaktifkan Supervisor.

3. Jalankan pengisian data awal (seeder) ke dalam kontainer database:
   ```bash
   docker compose exec app php artisan db:seed
   ```

### Alamat Akses Layanan Docker

| Layanan | Alamat Akses | Keterangan |
|---|---|---|
| **Frontend Web** | `http://localhost:5173` | Antarmuka pengguna React & PWA |
| **Backend REST API** | `http://localhost:8000/api` | Endpoint API Laravel |
| **PhpMyAdmin GUI** | `http://localhost:8081` | Server: `db`, User: `root`, Password: `secret` |
| **MySQL Port Host** | `localhost:3307` | Diteruskan ke port 3307 untuk menghindari bentrok port lokal |
| **Redis In-Memory** | `localhost:6379` | Cache dan message queue server |

### Perintah Operasional Docker
- Memeriksa status kontainer: `docker compose ps`
- Memeriksa log aplikasi: `docker compose logs -f app`
- Menghentikan kontainer: `docker compose down`
- Menghentikan dan menghapus volume data: `docker compose down -v`

---

## Panduan Deployment Produksi VPS

Untuk deployment lingkungan produksi, disarankan menggunakan server Linux standar (Ubuntu 22.04 atau 24.04 LTS) dengan konfigurasi Nginx, PHP 8.2-FPM, Supervisor, MySQL 8.0, dan Redis.

### 1. Instalasi Paket Server Linux
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
   APP_NAME="Sistem Inventaris"
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

4. Atur kepemilikan dan hak akses berkas:
   ```bash
   sudo chown -R www-data:www-data /var/www/sistem-inventaris/storage /var/www/sistem-inventaris/bootstrap/cache
   sudo chmod -R 775 /var/www/sistem-inventaris/storage /var/www/sistem-inventaris/bootstrap/cache
   ```

### 3. Kompilasi Frontend
```bash
cd /var/www/sistem-inventaris/frontend
npm ci
VITE_API_URL=https://inventaris.example.com/api npm run build
```
Hasil kompilasi produksi siap disajikan dari direktori `frontend/dist`.

### 4. Konfigurasi Virtual Host Nginx
Buat berkas konfigurasi `/etc/nginx/sites-available/inventaris`:

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

    # Storage File Access
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

Aktifkan konfigurasi dan pasang sertifikat SSL Let's Encrypt:
```bash
sudo ln -s /etc/nginx/sites-available/inventaris /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
sudo certbot --nginx -d inventaris.example.com
```

### 5. Konfigurasi Supervisor (Queue Worker)
Buat berkas `/etc/supervisor/conf.d/inventaris-worker.conf`:
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
Terapkan perubahan Supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
```

### 6. Konfigurasi Penjadwalan Cron (Task Scheduler)
Jalankan scheduler Laravel setiap menit:
```bash
sudo crontab -u www-data -e
```
Tambahkan baris berikut:
```cron
* * * * * cd /var/www/sistem-inventaris && php artisan schedule:run >> /dev/null 2>&1
```

---

## Pengujian Otomatis dan Kualitas Kode

Proyek ini menerapkan pengujian otomatis menyeluruh untuk menjamin keandalan sistem pada setiap perubahan:

### 1. Pengujian Backend (PHPUnit)
Menjalankan pengujian fitur dan unit backend:
```bash
php artisan test
```

Cakupan pengujian backend (36 berkas uji):
- Autentikasi dan registrasi sanitasi hak akses.
- Alur lengkap persetujuan peminjaman (Approval Flow).
- Proteksi race condition stok barang menggunakan pessimistic locking.
- Atomisitas transaksi basis data dan rollback otomatis saat kegagalan.
- Efisiensi kueri basis data bebas masalah N+1 Query.
- Filter kueri dan pemetaan enum status peminjaman.
- Otorisasi hak akses policy pada setiap model.
- Validasi header keamanan CSP dan proteksi form request.

### 2. Pengujian Frontend (Vitest)
Menjalankan pengujian komponen dan integrasi frontend:
```bash
cd frontend
npm test -- --run
```

Cakupan pengujian frontend (10 test suite, 69 unit tests lulus 100%):
1. `borrowingService.test.js`: Pengujian API service peminjaman barang.
2. `useInfiniteScroll.test.jsx`: Pengujian hook infinite scroll berbasis IntersectionObserver.
3. `NotFound.test.jsx`: Pengujian fallback route halaman 404.
4. `ThemeContext.test.jsx`: Pengujian transisi tema terang, gelap, dan sistem.
5. `Button.test.jsx`: Pengujian varian tombol, state loading, dan event listener.
6. `Modal.test.jsx`: Pengujian dialog modal, backdrop overlay, dan escape key handler.
7. `Input.test.jsx`: Pengujian elemen input, pesan kesalahan, dan asosiasi label.
8. `ResponsiveLayout.test.jsx`: Pengujian drawer navigasi ponsel dan backdrop backdrop overlay.
9. `RbacVisibility.test.jsx`: Pengujian pembatasan visibilitas tombol kontrol Admin vs Staff pada ItemList, CategoryList, dan ItemDetail.
10. `A11ySeo.test.jsx`: Pengujian kelengkapan label aksesibilitas (`aria-label`, `htmlFor`, role) pada Header, filter tabel, dan elemen formulir.

### 3. Tolok Ukur Lighthouse
Pengujian audit antarmuka menggunakan Chrome DevTools Lighthouse menghasilkan skor sempurna:
- **Accessibility:** 100 / 100
- **SEO:** 100 / 100
- **Best Practices:** 100 / 100

---

## Kredensial Pengujian Default

Setelah menjalankan migrasi dan seeder awal (`php artisan migrate --seed`), akun pengujian berikut siap digunakan:

| Role | Email | Password | Hak Akses dan Kewenangan |
|---|---|---|---|
| **Administrator** | `admin@example.com` | `password` | Akses penuh: manajemen data barang, master kategori, manajemen akun pengguna, persetujuan transaksi, dan ekspor laporan |
| **Staff / Peminjam** | `staff@example.com` | `password` | Akses terbatas: melihat katalog barang dan kategori (read-only), pengajuan permohonan peminjaman, serta pemantauan status pinjaman pribadi |

---

## Lisensi

Proyek ini dirilis secara open-source di bawah lisensi [MIT License](LICENSE).
Semua pengembang bebas menggunakan, memodifikasi, dan mendistribusikan kode ini sesuai ketentuan lisensi.
