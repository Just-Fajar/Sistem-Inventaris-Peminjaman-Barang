import { yupResolver } from '@hookform/resolvers/yup';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useNavigate, useParams } from 'react-router-dom';
import * as yup from 'yup';
import { borrowingService } from '../services/borrowingService';

const schema = yup.object({
  return_date: yup.date().required('Tanggal pengembalian wajib diisi'),
  notes: yup.string().nullable(),
}).required();

function ReturnForm() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [submitting, setSubmitting] = useState(false);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm({
    resolver: yupResolver(schema),
    defaultValues: {
      return_date: new Date().toISOString().split('T')[0],
    },
  });

  const onSubmit = async (data) => {
    if (!confirm('Konfirmasi pengembalian barang ini?')) return;

    try {
      setSubmitting(true);
      await borrowingService.return(id, data);
      alert('Pengembalian berhasil dicatat');
      navigate('/borrowings');
    } catch (error) {
      alert('Gagal mencatat pengembalian: ' + (error.response?.data?.message || 'Terjadi kesalahan'));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div>
      {/* Header */}
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">Kembalikan Barang</h1>
        <p className="text-gray-600 mt-1">Proses pengembalian barang peminjaman</p>
      </div>

      <div className="max-w-2xl mx-auto">
        <div className="bg-white rounded-lg shadow-sm p-6">
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
            {/* Return Date */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Tanggal Pengembalian <span className="text-red-500">*</span>
              </label>
              <input
                type="date"
                {...register('return_date')}
                className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent ${
                  errors.return_date ? 'border-red-500' : 'border-gray-300'
                }`}
              />
              {errors.return_date && <p className="mt-1 text-sm text-red-500">{errors.return_date.message}</p>}
              <p className="mt-1 text-sm text-gray-500">
                Sistem akan otomatis mendeteksi jika pengembalian terlambat
              </p>
            </div>

            {/* Notes */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Catatan Pengembalian</label>
              <textarea
                {...register('notes')}
                rows={4}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="Kondisi barang saat dikembalikan, catatan tambahan, dll (opsional)"
              />
            </div>

            {/* Info Box */}
            <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
              <div className="flex">
                <svg className="w-5 h-5 text-blue-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div className="ml-3">
                  <h3 className="text-sm font-medium text-blue-800">Informasi</h3>
                  <div className="mt-2 text-sm text-blue-700">
                    <ul className="list-disc list-inside space-y-1">
                      <li>Stok barang akan otomatis bertambah sesuai jumlah yang dipinjam</li>
                      <li>Status peminjaman akan berubah menjadi "Dikembalikan"</li>
                      <li>Jika terlambat, akan tercatat dalam sistem</li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>

            {/* Actions */}
            <div className="flex justify-end space-x-3 pt-4">
              <button
                type="button"
                onClick={() => navigate(`/borrowings/${id}`)}
                disabled={submitting}
                className="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 disabled:opacity-50"
              >
                Batal
              </button>
              <button
                type="submit"
                disabled={submitting}
                className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 flex items-center space-x-2"
              >
                {submitting && <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>}
                <span>Konfirmasi Pengembalian</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
}

export default ReturnForm;
