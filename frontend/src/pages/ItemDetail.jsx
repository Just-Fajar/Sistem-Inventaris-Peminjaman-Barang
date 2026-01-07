import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { itemService } from '../services/itemService';

function ItemDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [item, setItem] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadItem();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  const loadItem = async () => {
    try {
      const response = await itemService.getById(id);
      setItem(response.data);
    } catch {
      alert('Gagal memuat data barang');
      navigate('/items');
    } finally {
      setLoading(false);
    }
  };

  const getConditionBadge = (condition) => {
    const styles = {
      baik: 'bg-green-100 text-green-800',
      rusak: 'bg-red-100 text-red-800',
      hilang: 'bg-gray-100 text-gray-800',
    };
    return styles[condition] || styles.baik;
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

  if (!item) {
    return null;
  }

  return (
    <div>
      {/* Header */}
      <div className="mb-6">
        <div className="flex items-center space-x-2 text-sm text-gray-600 mb-2">
          <Link to="/items" className="hover:text-blue-600">Manajemen Barang</Link>
          <span>/</span>
          <span className="text-gray-900">Detail Barang</span>
        </div>
        <div className="flex justify-between items-start">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">{item.name}</h1>
            <p className="text-gray-600 mt-1">Kode: {item.code}</p>
          </div>
          <Link
            to={`/items/${item.id}/edit`}
            className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center space-x-2"
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            <span>Edit</span>
          </Link>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Main Info */}
        <div className="lg:col-span-2 space-y-6">
          {/* Basic Information */}
          <div className="bg-white rounded-lg shadow-sm p-6">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Informasi Dasar</h2>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <p className="text-sm text-gray-500">Nama Barang</p>
                <p className="text-base font-medium text-gray-900 mt-1">{item.name}</p>
              </div>
              <div>
                <p className="text-sm text-gray-500">Kode Barang</p>
                <p className="text-base font-medium text-gray-900 mt-1">{item.code}</p>
              </div>
              <div>
                <p className="text-sm text-gray-500">Kategori</p>
                <p className="text-base font-medium text-gray-900 mt-1">{item.category?.name}</p>
              </div>
              <div>
                <p className="text-sm text-gray-500">Kondisi</p>
                <span className={`inline-block px-3 py-1 text-sm font-semibold rounded-full ${getConditionBadge(item.condition)} mt-1`}>
                  {item.condition}
                </span>
              </div>
            </div>

            {item.description && (
              <div className="mt-4 pt-4 border-t border-gray-200">
                <p className="text-sm text-gray-500">Deskripsi</p>
                <p className="text-base text-gray-900 mt-1">{item.description}</p>
              </div>
            )}
          </div>

          {/* Stock Information */}
          <div className="bg-white rounded-lg shadow-sm p-6">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Informasi Stok</h2>
            <div className="grid grid-cols-3 gap-4">
              <div className="text-center p-4 bg-blue-50 rounded-lg">
                <p className="text-sm text-gray-600">Total Stok</p>
                <p className="text-3xl font-bold text-blue-600 mt-2">{item.stock}</p>
              </div>
              <div className="text-center p-4 bg-green-50 rounded-lg">
                <p className="text-sm text-gray-600">Tersedia</p>
                <p className="text-3xl font-bold text-green-600 mt-2">{item.available_stock}</p>
              </div>
              <div className="text-center p-4 bg-orange-50 rounded-lg">
                <p className="text-sm text-gray-600">Dipinjam</p>
                <p className="text-3xl font-bold text-orange-600 mt-2">
                  {item.stock - item.available_stock}
                </p>
              </div>
            </div>
          </div>

          {/* Borrowing History */}
          {item.active_borrowings && item.active_borrowings.length > 0 && (
            <div className="bg-white rounded-lg shadow-sm p-6">
              <h2 className="text-lg font-semibold text-gray-900 mb-4">Peminjaman Aktif</h2>
              <div className="space-y-3">
                {item.active_borrowings.map((borrowing) => (
                  <div key={borrowing.id} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div className="flex-1">
                      <p className="font-medium text-gray-900">{borrowing.user?.name}</p>
                      <p className="text-sm text-gray-500">
                        {new Date(borrowing.borrow_date).toLocaleDateString('id-ID')} - {new Date(borrowing.due_date).toLocaleDateString('id-ID')}
                      </p>
                    </div>
                    <div className="text-right">
                      <p className="text-sm font-medium text-gray-900">Qty: {borrowing.quantity}</p>
                      <span className="text-xs px-2 py-1 bg-blue-100 text-blue-800 rounded-full">
                        {borrowing.status}
                      </span>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>

        {/* Sidebar */}
        <div className="space-y-6">
          {/* Image */}
          {item.image ? (
            <div className="bg-white rounded-lg shadow-sm p-6">
              <h2 className="text-lg font-semibold text-gray-900 mb-4">Gambar Barang</h2>
              <img
                src={`http://localhost:8000/storage/${item.image}`}
                alt={item.name}
                className="w-full h-auto rounded-lg"
              />
            </div>
          ) : (
            <div className="bg-white rounded-lg shadow-sm p-6">
              <div className="aspect-square bg-gray-100 rounded-lg flex items-center justify-center">
                <svg className="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </div>
              <p className="text-sm text-gray-500 text-center mt-2">Tidak ada gambar</p>
            </div>
          )}

          {/* Meta Info */}
          <div className="bg-white rounded-lg shadow-sm p-6">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Informasi Tambahan</h2>
            <div className="space-y-3">
              <div>
                <p className="text-sm text-gray-500">Dibuat</p>
                <p className="text-sm font-medium text-gray-900 mt-1">
                  {new Date(item.created_at).toLocaleDateString('id-ID', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                  })}
                </p>
              </div>
              <div>
                <p className="text-sm text-gray-500">Terakhir Diupdate</p>
                <p className="text-sm font-medium text-gray-900 mt-1">
                  {new Date(item.updated_at).toLocaleDateString('id-ID', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                  })}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

export default ItemDetail;
