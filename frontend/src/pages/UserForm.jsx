import { yupResolver } from '@hookform/resolvers/yup';
import { useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { useNavigate, useParams } from 'react-router-dom';
import * as yup from 'yup';
import { userService } from '../services/userService';

const schema = yup.object({
  name: yup.string().required('Nama wajib diisi').min(3, 'Minimal 3 karakter'),
  email: yup.string().required('Email wajib diisi').email('Email tidak valid'),
  password: yup.string().when('$isEdit', {
    is: false,
    then: (schema) => schema.required('Password wajib diisi').min(8, 'Minimal 8 karakter'),
    otherwise: (schema) => schema.min(8, 'Minimal 8 karakter').nullable(),
  }),
  role: yup.string().required('Role wajib dipilih').oneOf(['admin', 'staff']),
}).required();

function UserForm() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [submitting, setSubmitting] = useState(false);
  const isEdit = !!id;

  const {
    register,
    handleSubmit,
    formState: { errors },
    reset,
  } = useForm({
    resolver: yupResolver(schema),
    context: { isEdit },
  });

  useEffect(() => {
    if (isEdit) {
      loadUser();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id, isEdit]);

  const loadUser = async () => {
    try {
      const response = await userService.getById(id);
      reset({
        name: response.data.name,
        email: response.data.email,
        role: response.data.role,
      });
    } catch {
      alert('Gagal memuat data user');
      navigate('/users');
    }
  };

  const onSubmit = async (data) => {
    try {
      setSubmitting(true);
      
      // Remove password if empty in edit mode
      if (isEdit && !data.password) {
        delete data.password;
      }

      if (isEdit) {
        await userService.update(id, data);
      } else {
        await userService.create(data);
      }

      navigate('/users');
    } catch (error) {
      alert('Gagal menyimpan user: ' + (error.response?.data?.message || 'Terjadi kesalahan'));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div>
      {/* Header */}
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">
          {isEdit ? 'Edit User' : 'Tambah User'}
        </h1>
        <p className="text-gray-600 mt-1">
          {isEdit ? 'Perbarui informasi user' : 'Tambahkan user baru ke sistem'}
        </p>
      </div>

      <div className="max-w-2xl">
        <div className="bg-white rounded-lg shadow-sm p-6">
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
            {/* Name */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Nama Lengkap <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                {...register('name')}
                className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent ${
                  errors.name ? 'border-red-500' : 'border-gray-300'
                }`}
                placeholder="John Doe"
              />
              {errors.name && <p className="mt-1 text-sm text-red-500">{errors.name.message}</p>}
            </div>

            {/* Email */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Email <span className="text-red-500">*</span>
              </label>
              <input
                type="email"
                {...register('email')}
                className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent ${
                  errors.email ? 'border-red-500' : 'border-gray-300'
                }`}
                placeholder="john@example.com"
              />
              {errors.email && <p className="mt-1 text-sm text-red-500">{errors.email.message}</p>}
            </div>

            {/* Password */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Password {!isEdit && <span className="text-red-500">*</span>}
                {isEdit && <span className="text-gray-500 text-xs">(Kosongkan jika tidak ingin mengubah)</span>}
              </label>
              <input
                type="password"
                {...register('password')}
                className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent ${
                  errors.password ? 'border-red-500' : 'border-gray-300'
                }`}
                placeholder="Minimal 8 karakter"
              />
              {errors.password && <p className="mt-1 text-sm text-red-500">{errors.password.message}</p>}
            </div>

            {/* Role */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Role <span className="text-red-500">*</span>
              </label>
              <select
                {...register('role')}
                className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent ${
                  errors.role ? 'border-red-500' : 'border-gray-300'
                }`}
              >
                <option value="">Pilih role...</option>
                <option value="admin">Admin</option>
                <option value="staff">Staff</option>
              </select>
              {errors.role && <p className="mt-1 text-sm text-red-500">{errors.role.message}</p>}
            </div>

            {/* Info Box */}
            <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
              <div className="flex">
                <svg className="w-5 h-5 text-blue-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div className="ml-3">
                  <h3 className="text-sm font-medium text-blue-800">Role Permissions</h3>
                  <div className="mt-2 text-sm text-blue-700">
                    <ul className="list-disc list-inside space-y-1">
                      <li><strong>Admin:</strong> Full access, dapat manage users dan approve peminjaman</li>
                      <li><strong>Staff:</strong> Dapat manage items, categories, dan borrowings</li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>

            {/* Actions */}
            <div className="flex justify-end space-x-3 pt-4">
              <button
                type="button"
                onClick={() => navigate('/users')}
                disabled={submitting}
                className="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 disabled:opacity-50"
              >
                Batal
              </button>
              <button
                type="submit"
                disabled={submitting}
                className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 flex items-center space-x-2"
              >
                {submitting && <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>}
                <span>{isEdit ? 'Simpan' : 'Tambah'}</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
}

export default UserForm;
