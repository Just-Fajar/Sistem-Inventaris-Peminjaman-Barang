import { useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { useNavigate, useParams } from 'react-router-dom';
import { categoryService } from '../services/categoryService';
import { itemService } from '../services/itemService';

function ItemForm() {
  const navigate = useNavigate();
  const { id } = useParams();
  const isEdit = !!id;

  const [categories, setCategories] = useState([]);
  const [loading, setLoading] = useState(false);
  const [imagePreview, setImagePreview] = useState(null);

  const {
    register,
    handleSubmit,
    formState: { errors },
    setValue,
  } = useForm();

  useEffect(() => {
    loadCategories();
    if (isEdit) {
      loadItem();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  const loadCategories = async () => {
    try {
      const response = await categoryService.getAll({ all: true });
      setCategories(response.data || response);
    } catch (error) {
      console.error('Failed to load categories:', error);
    }
  };

  const loadItem = async () => {
    try {
      const response = await itemService.getById(id);
      const item = response.data;
      setValue('name', item.name);
      setValue('category_id', item.category_id);
      setValue('description', item.description);
      setValue('stock', item.stock);
      setValue('condition', item.condition);
      if (item.image) {
        setImagePreview(`http://localhost:8000/storage/${item.image}`);
      }
    } catch (error) {
      alert('Gagal memuat data barang');
      navigate('/items');
    }
  };

  const handleImageChange = (e) => {
    const file = e.target.files[0];
    if (file) {
      // Validation
      if (file.size > 2 * 1024 * 1024) {
        alert('Ukuran file maksimal 2MB');
        e.target.value = '';
        return;
      }
      if (!['image/jpeg', 'image/jpg', 'image/png'].includes(file.type)) {
        alert('Format file harus JPG, JPEG, atau PNG');
        e.target.value = '';
        return;
      }

      const reader = new FileReader();
      reader.onloadend = () => {
        setImagePreview(reader.result);
      };
      reader.readAsDataURL(file);
    }
  };

  const onSubmit = async (data) => {
    try {
      setLoading(true);

      const formData = new FormData();
      formData.append('name', data.name);
      formData.append('category_id', data.category_id);
      formData.append('description', data.description || '');
      formData.append('stock', data.stock);
      formData.append('condition', data.condition);

      const imageFile = document.querySelector('input[type="file"]').files[0];
      if (imageFile) {
        formData.append('image', imageFile);
      }

      if (isEdit) {
        formData.append('_method', 'PUT');
        await itemService.update(id, formData);
        alert('Barang berhasil diupdate');
      } else {
        await itemService.create(formData);
        alert('Barang berhasil ditambahkan');
      }

      navigate('/items');
    } catch (error) {
      alert(error.response?.data?.message || 'Gagal menyimpan barang');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div>
      {/* Header */}
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">
          {isEdit ? 'Edit Barang' : 'Tambah Barang Baru'}
        </h1>
        <p className="text-gray-600 mt-1">
          {isEdit ? 'Update informasi barang' : 'Tambahkan barang baru ke inventaris'}
        </p>
      </div>

      {/* Form */}
      <div className="bg-white rounded-lg shadow-sm p-6">
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {/* Name */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Nama Barang <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                {...register('name', { required: 'Nama barang wajib diisi' })}
                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="Contoh: Laptop Dell Latitude"
              />
              {errors.name && (
                <p className="mt-1 text-sm text-red-600">{errors.name.message}</p>
              )}
            </div>

            {/* Category */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Kategori <span className="text-red-500">*</span>
              </label>
              <select
                {...register('category_id', { required: 'Kategori wajib dipilih' })}
                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              >
                <option value="">Pilih Kategori</option>
                {categories.map((cat) => (
                  <option key={cat.id} value={cat.id}>
                    {cat.name}
                  </option>
                ))}
              </select>
              {errors.category_id && (
                <p className="mt-1 text-sm text-red-600">{errors.category_id.message}</p>
              )}
            </div>

            {/* Stock */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Jumlah Stok <span className="text-red-500">*</span>
              </label>
              <input
                type="number"
                {...register('stock', {
                  required: 'Stok wajib diisi',
                  min: { value: 0, message: 'Stok minimal 0' },
                })}
                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="0"
              />
              {errors.stock && (
                <p className="mt-1 text-sm text-red-600">{errors.stock.message}</p>
              )}
            </div>

            {/* Condition */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Kondisi <span className="text-red-500">*</span>
              </label>
              <select
                {...register('condition', { required: 'Kondisi wajib dipilih' })}
                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              >
                <option value="">Pilih Kondisi</option>
                <option value="baik">Baik</option>
                <option value="rusak">Rusak</option>
                <option value="hilang">Hilang</option>
              </select>
              {errors.condition && (
                <p className="mt-1 text-sm text-red-600">{errors.condition.message}</p>
              )}
            </div>
          </div>

          {/* Description */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Deskripsi
            </label>
            <textarea
              {...register('description')}
              rows={4}
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="Deskripsi barang (optional)"
            />
          </div>

          {/* Image Upload */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Gambar Barang
            </label>
            <div className="flex items-start space-x-4">
              <div className="flex-1">
                <input
                  type="file"
                  accept="image/jpeg,image/jpg,image/png"
                  onChange={handleImageChange}
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
                <p className="mt-1 text-xs text-gray-500">
                  Format: JPG, JPEG, PNG. Maksimal 2MB
                </p>
              </div>
              {imagePreview && (
                <div className="shrink-0">
                  <img
                    src={imagePreview}
                    alt="Preview"
                    className="h-20 w-20 object-cover rounded-lg border border-gray-300"
                  />
                </div>
              )}
            </div>
          </div>

          {/* Buttons */}
          <div className="flex justify-end space-x-4 pt-6 border-t border-gray-200">
            <button
              type="button"
              onClick={() => navigate('/items')}
              className="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
            >
              Batal
            </button>
            <button
              type="submit"
              disabled={loading}
              className="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 flex items-center space-x-2"
            >
              {loading ? (
                <>
                  <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
                  <span>Menyimpan...</span>
                </>
              ) : (
                <span>{isEdit ? 'Update' : 'Simpan'}</span>
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

export default ItemForm;
