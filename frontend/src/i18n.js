import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

// Translation resources
const resources = {
  id: {
    translation: {
      // Common
      'common.loading': 'Memuat...',
      'common.save': 'Simpan',
      'common.cancel': 'Batal',
      'common.delete': 'Hapus',
      'common.edit': 'Edit',
      'common.view': 'Lihat',
      'common.search': 'Cari',
      'common.filter': 'Filter',
      'common.export': 'Export',
      'common.actions': 'Aksi',
      'common.yes': 'Ya',
      'common.no': 'Tidak',
      'common.back': 'Kembali',
      'common.next': 'Selanjutnya',
      'common.previous': 'Sebelumnya',
      
      // Auth
      'auth.login': 'Masuk',
      'auth.logout': 'Keluar',
      'auth.register': 'Daftar',
      'auth.email': 'Email',
      'auth.password': 'Password',
      'auth.remember': 'Ingat Saya',
      'auth.forgot_password': 'Lupa Password?',
      
      // Navigation
      'nav.dashboard': 'Dashboard',
      'nav.items': 'Barang',
      'nav.categories': 'Kategori',
      'nav.borrowings': 'Peminjaman',
      'nav.reports': 'Laporan',
      'nav.users': 'Pengguna',
      'nav.profile': 'Profil',
      
      // Items
      'items.title': 'Daftar Barang',
      'items.add': 'Tambah Barang',
      'items.edit': 'Edit Barang',
      'items.detail': 'Detail Barang',
      'items.name': 'Nama Barang',
      'items.code': 'Kode',
      'items.category': 'Kategori',
      'items.stock': 'Stok',
      'items.available': 'Tersedia',
      'items.condition': 'Kondisi',
      'items.location': 'Lokasi',
      'items.image': 'Gambar',
      
      // Borrowings
      'borrowings.title': 'Daftar Peminjaman',
      'borrowings.add': 'Tambah Peminjaman',
      'borrowings.detail': 'Detail Peminjaman',
      'borrowings.borrow_date': 'Tanggal Pinjam',
      'borrowings.due_date': 'Tanggal Kembali',
      'borrowings.return_date': 'Tanggal Dikembalikan',
      'borrowings.status': 'Status',
      'borrowings.quantity': 'Jumlah',
      
      // Status
      'status.pending': 'Menunggu',
      'status.approved': 'Disetujui',
      'status.rejected': 'Ditolak',
      'status.returned': 'Dikembalikan',
      'status.overdue': 'Terlambat',
      
      // Conditions
      'condition.baik': 'Baik',
      'condition.rusak': 'Rusak',
      
      // Messages
      'message.success': 'Berhasil',
      'message.error': 'Terjadi Kesalahan',
      'message.confirm_delete': 'Apakah Anda yakin ingin menghapus data ini?',
      'message.no_data': 'Tidak ada data',
      'message.offline': 'Tidak ada koneksi internet',
    }
  },
  en: {
    translation: {
      // Common
      'common.loading': 'Loading...',
      'common.save': 'Save',
      'common.cancel': 'Cancel',
      'common.delete': 'Delete',
      'common.edit': 'Edit',
      'common.view': 'View',
      'common.search': 'Search',
      'common.filter': 'Filter',
      'common.export': 'Export',
      'common.actions': 'Actions',
      'common.yes': 'Yes',
      'common.no': 'No',
      'common.back': 'Back',
      'common.next': 'Next',
      'common.previous': 'Previous',
      
      // Auth
      'auth.login': 'Login',
      'auth.logout': 'Logout',
      'auth.register': 'Register',
      'auth.email': 'Email',
      'auth.password': 'Password',
      'auth.remember': 'Remember Me',
      'auth.forgot_password': 'Forgot Password?',
      
      // Navigation
      'nav.dashboard': 'Dashboard',
      'nav.items': 'Items',
      'nav.categories': 'Categories',
      'nav.borrowings': 'Borrowings',
      'nav.reports': 'Reports',
      'nav.users': 'Users',
      'nav.profile': 'Profile',
      
      // Items
      'items.title': 'Item List',
      'items.add': 'Add Item',
      'items.edit': 'Edit Item',
      'items.detail': 'Item Detail',
      'items.name': 'Item Name',
      'items.code': 'Code',
      'items.category': 'Category',
      'items.stock': 'Stock',
      'items.available': 'Available',
      'items.condition': 'Condition',
      'items.location': 'Location',
      'items.image': 'Image',
      
      // Borrowings
      'borrowings.title': 'Borrowing List',
      'borrowings.add': 'Add Borrowing',
      'borrowings.detail': 'Borrowing Detail',
      'borrowings.borrow_date': 'Borrow Date',
      'borrowings.due_date': 'Due Date',
      'borrowings.return_date': 'Return Date',
      'borrowings.status': 'Status',
      'borrowings.quantity': 'Quantity',
      
      // Status
      'status.pending': 'Pending',
      'status.approved': 'Approved',
      'status.rejected': 'Rejected',
      'status.returned': 'Returned',
      'status.overdue': 'Overdue',
      
      // Conditions
      'condition.baik': 'Good',
      'condition.rusak': 'Damaged',
      
      // Messages
      'message.success': 'Success',
      'message.error': 'An Error Occurred',
      'message.confirm_delete': 'Are you sure you want to delete this data?',
      'message.no_data': 'No data available',
      'message.offline': 'No internet connection',
    }
  }
};

i18n
  .use(initReactI18next)
  .init({
    resources,
    lng: localStorage.getItem('language') || 'id', // Default language
    fallbackLng: 'id',
    interpolation: {
      escapeValue: false // React already escapes
    },
    detection: {
      order: ['localStorage', 'navigator'],
      caches: ['localStorage']
    }
  });

export default i18n;
