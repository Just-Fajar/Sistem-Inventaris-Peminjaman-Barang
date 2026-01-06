import { yupResolver } from '@hookform/resolvers/yup';
import { useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { useNavigate } from 'react-router-dom';
import * as yup from 'yup';
import { borrowingService } from '../services/borrowingService';
import { itemService } from '../services/itemService';

const schema = yup.object({
  item_id: yup.string().required('Barang wajib dipilih'),
  quantity: yup.number().required('Jumlah wajib diisi').min(1, 'Minimal 1').typeError('Jumlah harus berupa angka'),
  borrow_date: yup.date().required('Tanggal pinjam wajib diisi'),
  due_date: yup.date().required('Tanggal kembali wajib diisi').min(yup.ref('borrow_date'), 'Tanggal kembali harus setelah tanggal pinjam'),
  notes: yup.string().nullable(),
}).required();

function BorrowingForm() {
  const navigate = useNavigate();
  const [items, setItems] = useState([]);
  const [selectedItem, setSelectedItem] = useState(null);
  const [submitting, setSubmitting] = useState(false);

  const {
    register,
    handleSubmit,
    formState: { errors },
    watch,
  } = useForm({
    resolver: yupResolver(schema),
    defaultValues: {
      borrow_date: new Date().toISOString().split('T')[0],
      due_date: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0], // 7 days from now
    },
  });

  const itemId = watch('item_id');
  const quantity = watch('quantity');

  useEffect(() => {
    loadItems();
  }, []);

  useEffect(() => {
    if (itemId) {
      const item = items.find((i) => i.id === parseInt(itemId));
      setSelectedItem(item || null);
    } else {
      setSelectedItem(null);
    }
  }, [itemId, items]);

  const loadItems = async () => {
    try {
      const response = await itemService.getAll({ all: true });
      const availableItems = (response.data.data || response.data || []).filter(
        (item) => item.available_stock > 0
      );
      setItems(availableItems);
    } catch {
      alert('Gagal memuat data barang');
    }
  };

  const onSubmit = async (data) => {
    if (selectedItem && quantity > selectedItem.available_stock) {
      alert(`Stok tidak mencukupi. Stok tersedia: ${selectedItem.available_stock}`);
      return;
    }

    try {
      setSubmitting(true);
      await borrowingService.create(data);
      alert('Peminjaman berhasil diajukan');
      navigate('/borrowings');
    } catch (error) {
      alert('Gagal mengajukan peminjaman: ' + (error.response?.data?.message || 'Terjadi kesalahan'));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div>
      {/* Header */}
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">Pinjam Barang</h1>
        <p className="text-gray-600 mt-1">Ajukan peminjaman barang inventaris</p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Form */}
        <div className="lg:col-span-2">
          <div className="bg-white rounded-lg shadow-sm p-6">
            <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
              {/* Item Selection */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Pilih Barang <span className="text-red-500">*</span>
                </label>
                <select
                  {...register('item_id')}
                  className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent ${
                    errors.item_id ? 'border-red-500' : 'border-gray-300'
                  }`}
                >
                  <option value="">Pilih barang...</option>
                  {items.map((item) => (
                    <option key={item.id} value={item.id}>
                      {item.name} ({item.code}) - Tersedia: {item.available_stock}
                    </option>
                  ))}
                </select>
                {errors.item_id && <p className="mt-1 text-sm text-red-500">{errors.item_id.message}</p>}
              </div>

              {/* Quantity */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Jumlah <span className="text-red-500">*</span>
                </label>
                <input
                  type="number"
                  {...register('quantity')}
                  className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent ${
                    errors.quantity ? 'border-red-500' : 'border-gray-300'
                  }`}
                  placeholder="Masukkan jumlah"
                  min="1"
                />
                {errors.quantity && <p className="mt-1 text-sm text-red-500">{errors.quantity.message}</p>}
                {selectedItem && quantity > selectedItem.available_stock && (
                  <p className="mt-1 text-sm text-red-500">
                    Stok tidak mencukupi. Tersedia: {selectedItem.available_stock}
                  </p>
                )}
              </div>

              {/* Borrow Date */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Tanggal Pinjam <span className="text-red-500">*</span>
                </label>
                <input
                  type="date"
                  {...register('borrow_date')}
                  className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent ${
                    errors.borrow_date ? 'border-red-500' : 'border-gray-300'
                  }`}
                />
                {errors.borrow_date && <p className="mt-1 text-sm text-red-500">{errors.borrow_date.message}</p>}
              </div>

              {/* Due Date */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Tanggal Jatuh Tempo <span className="text-red-500">*</span>
                </label>
                <input
                  type="date"
                  {...register('due_date')}
                  className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent ${
                    errors.due_date ? 'border-red-500' : 'border-gray-300'
                  }`}
                />
                {errors.due_date && <p className="mt-1 text-sm text-red-500">{errors.due_date.message}</p>}
              </div>

              {/* Notes */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                <textarea
                  {...register('notes')}
                  rows={3}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="Catatan tambahan (opsional)"
                />
              </div>

              {/* Actions */}
              <div className="flex justify-end space-x-3 pt-4">
                <button
                  type="button"
                  onClick={() => navigate('/borrowings')}
                  disabled={submitting}
                  className="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={submitting || (selectedItem && quantity > selectedItem.available_stock)}
                  className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 flex items-center space-x-2"
                >
                  {submitting && <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>}
                  <span>Ajukan Peminjaman</span>
                </button>
              </div>
            </form>
          </div>
        </div>

        {/* Item Info Sidebar */}
        <div>
          {selectedItem && (
            <div className="bg-white rounded-lg shadow-sm p-6">
              <h3 className="text-lg font-semibold text-gray-900 mb-4">Info Barang</h3>
              
              {selectedItem.image && (
                <img
                  src={`http://localhost:8000/storage/${selectedItem.image}`}
                  alt={selectedItem.name}
                  className="w-full h-48 object-cover rounded-lg mb-4"
                />
              )}

              <div className="space-y-3">
                <div>
                  <p className="text-sm text-gray-500">Nama Barang</p>
                  <p className="text-base font-medium text-gray-900">{selectedItem.name}</p>
                </div>
                <div>
                  <p className="text-sm text-gray-500">Kode</p>
                  <p className="text-base font-medium text-gray-900">{selectedItem.code}</p>
                </div>
                <div>
                  <p className="text-sm text-gray-500">Kategori</p>
                  <p className="text-base font-medium text-gray-900">{selectedItem.category?.name}</p>
                </div>
                <div>
                  <p className="text-sm text-gray-500">Kondisi</p>
                  <span className="inline-block px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800">
                    {selectedItem.condition}
                  </span>
                </div>
                <div className="pt-3 border-t border-gray-200">
                  <div className="grid grid-cols-2 gap-3">
                    <div className="text-center p-3 bg-blue-50 rounded-lg">
                      <p className="text-sm text-gray-600">Total Stok</p>
                      <p className="text-2xl font-bold text-blue-600">{selectedItem.stock}</p>
                    </div>
                    <div className="text-center p-3 bg-green-50 rounded-lg">
                      <p className="text-sm text-gray-600">Tersedia</p>
                      <p className="text-2xl font-bold text-green-600">{selectedItem.available_stock}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

export default BorrowingForm;
