import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { authService } from '../services/authService';
import { borrowingService } from '../services/borrowingService';

function BorrowingDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [borrowing, setBorrowing] = useState(null);
  const [loading, setLoading] = useState(true);
  const isAdmin = authService.isAdmin();

  useEffect(() => {
    loadBorrowing();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  const loadBorrowing = async () => {
    try {
      const response = await borrowingService.getById(id);
      setBorrowing(response.data);
    } catch {
      alert('Gagal memuat data peminjaman');
      navigate('/borrowings');
    } finally {
      setLoading(false);
    }
  };

  const handleApprove = async () => {
    if (!confirm('Approve peminjaman ini?')) return;

    try {
      await borrowingService.approve(id);
      loadBorrowing();
    } catch (error) {
      alert('Gagal approve peminjaman: ' + (error.response?.data?.message || 'Terjadi kesalahan'));
    }
  };

  const getStatusBadge = () => {
    if (!borrowing) return '';
    if (borrowing.status === 'returned') return 'bg-green-100 text-green-800';
    if (borrowing.is_overdue) return 'bg-red-100 text-red-800';
    if (borrowing.status === 'pending') return 'bg-yellow-100 text-yellow-800';
    return 'bg-blue-100 text-blue-800';
  };

  const getStatusText = () => {
    if (!borrowing) return '';
    if (borrowing.status === 'returned') return 'Dikembalikan';
    if (borrowing.is_overdue) return 'Terlambat';
    if (borrowing.status === 'pending') return 'Pending';
    if (borrowing.status === 'approved') return 'Dipinjam';
    return borrowing.status;
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="text-center">
          <div className="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
          <p className="mt-4 text-gray-600">Memuat data...</p>
        </div>
      </div>
    );
  }

  if (!borrowing) {
    return null;
  }

  return (
    <div>
      {/* Header */}
      <div className="mb-6">
        <div className="flex items-center space-x-2 text-sm text-gray-600 mb-2">
          <Link to="/borrowings" className="hover:text-blue-600">Manajemen Peminjaman</Link>
          <span>/</span>
          <span className="text-gray-900">Detail Peminjaman</span>
        </div>
        <div className="flex justify-between items-start">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Detail Peminjaman</h1>
            <p className="text-gray-600 mt-1">Kode: {borrowing.code}</p>
          </div>
          <div className="flex space-x-3">
            {borrowing.status === 'approved' && (
              <Link
                to={`/borrowings/${borrowing.id}/return`}
                className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center space-x-2"
              >
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                </svg>
                <span>Kembalikan</span>
              </Link>
            )}
            {isAdmin && borrowing.status === 'pending' && (
              <button
                onClick={handleApprove}
                className="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 flex items-center space-x-2"
              >
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Approve</span>
              </button>
            )}
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Main Info */}
        <div className="lg:col-span-2 space-y-6">
          {/* Status & Timeline */}
          <div className="bg-white rounded-lg shadow-sm p-6">
            <div className="flex items-center justify-between mb-6">
              <h2 className="text-lg font-semibold text-gray-900">Status Peminjaman</h2>
              <span className={`px-4 py-2 text-sm font-semibold rounded-full ${getStatusBadge()}`}>
                {getStatusText()}
              </span>
            </div>

            <div className="space-y-4">
              <div className="flex items-start">
                <div className="shrink-0 w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                  <svg className="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                </div>
                <div className="ml-4 flex-1">
                  <p className="text-sm font-medium text-gray-900">Tanggal Pinjam</p>
                  <p className="text-sm text-gray-600">
                    {new Date(borrowing.borrow_date).toLocaleDateString('id-ID', {
                      weekday: 'long',
                      year: 'numeric',
                      month: 'long',
                      day: 'numeric',
                    })}
                  </p>
                </div>
              </div>

              <div className="flex items-start">
                <div className={`shrink-0 w-10 h-10 rounded-full flex items-center justify-center ${
                  borrowing.is_overdue ? 'bg-red-100' : 'bg-orange-100'
                }`}>
                  <svg className={`w-5 h-5 ${borrowing.is_overdue ? 'text-red-600' : 'text-orange-600'}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div className="ml-4 flex-1">
                  <p className="text-sm font-medium text-gray-900">Tanggal Jatuh Tempo</p>
                  <p className="text-sm text-gray-600">
                    {new Date(borrowing.due_date).toLocaleDateString('id-ID', {
                      weekday: 'long',
                      year: 'numeric',
                      month: 'long',
                      day: 'numeric',
                    })}
                  </p>
                  {borrowing.is_overdue && (
                    <p className="text-sm text-red-600 font-medium mt-1">⚠ Terlambat</p>
                  )}
                </div>
              </div>

              {borrowing.return_date && (
                <div className="flex items-start">
                  <div className="shrink-0 w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                    <svg className="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                  </div>
                  <div className="ml-4 flex-1">
                    <p className="text-sm font-medium text-gray-900">Tanggal Pengembalian</p>
                    <p className="text-sm text-gray-600">
                      {new Date(borrowing.return_date).toLocaleDateString('id-ID', {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                      })}
                    </p>
                  </div>
                </div>
              )}
            </div>
          </div>

          {/* Item Details */}
          <div className="bg-white rounded-lg shadow-sm p-6">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Detail Barang</h2>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <p className="text-sm text-gray-500">Nama Barang</p>
                <p className="text-base font-medium text-gray-900 mt-1">{borrowing.item?.name}</p>
              </div>
              <div>
                <p className="text-sm text-gray-500">Kode Barang</p>
                <p className="text-base font-medium text-gray-900 mt-1">{borrowing.item?.code}</p>
              </div>
              <div>
                <p className="text-sm text-gray-500">Kategori</p>
                <p className="text-base font-medium text-gray-900 mt-1">{borrowing.item?.category?.name}</p>
              </div>
              <div>
                <p className="text-sm text-gray-500">Jumlah Dipinjam</p>
                <p className="text-base font-medium text-gray-900 mt-1">{borrowing.quantity} unit</p>
              </div>
            </div>
          </div>

          {/* Notes */}
          {borrowing.notes && (
            <div className="bg-white rounded-lg shadow-sm p-6">
              <h2 className="text-lg font-semibold text-gray-900 mb-4">Catatan</h2>
              <p className="text-gray-700">{borrowing.notes}</p>
            </div>
          )}
        </div>

        {/* Sidebar */}
        <div className="space-y-6">
          {/* User Info */}
          <div className="bg-white rounded-lg shadow-sm p-6">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Peminjam</h2>
            <div className="space-y-3">
              <div>
                <p className="text-sm text-gray-500">Nama</p>
                <p className="text-base font-medium text-gray-900 mt-1">{borrowing.user?.name}</p>
              </div>
              <div>
                <p className="text-sm text-gray-500">Email</p>
                <p className="text-base text-gray-900 mt-1">{borrowing.user?.email}</p>
              </div>
              <div>
                <p className="text-sm text-gray-500">Role</p>
                <span className="inline-block px-3 py-1 text-sm font-semibold rounded-full bg-blue-100 text-blue-800 mt-1">
                  {borrowing.user?.role}
                </span>
              </div>
            </div>
          </div>

          {/* Item Image */}
          {borrowing.item?.image && (
            <div className="bg-white rounded-lg shadow-sm p-6">
              <h2 className="text-lg font-semibold text-gray-900 mb-4">Foto Barang</h2>
              <img
                src={`http://localhost:8000/storage/${borrowing.item.image}`}
                alt={borrowing.item.name}
                className="w-full h-auto rounded-lg"
              />
            </div>
          )}

          {/* Meta Info */}
          <div className="bg-white rounded-lg shadow-sm p-6">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Info Tambahan</h2>
            <div className="space-y-3">
              <div>
                <p className="text-sm text-gray-500">Dibuat</p>
                <p className="text-sm font-medium text-gray-900 mt-1">
                  {new Date(borrowing.created_at).toLocaleDateString('id-ID', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                  })}
                </p>
              </div>
              {borrowing.updated_at !== borrowing.created_at && (
                <div>
                  <p className="text-sm text-gray-500">Terakhir Diupdate</p>
                  <p className="text-sm font-medium text-gray-900 mt-1">
                    {new Date(borrowing.updated_at).toLocaleDateString('id-ID', {
                      year: 'numeric',
                      month: 'long',
                      day: 'numeric',
                    })}
                  </p>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

export default BorrowingDetail;
