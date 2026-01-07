import { yupResolver } from '@hookform/resolvers/yup';
import { useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { useNavigate } from 'react-router-dom';
import * as yup from 'yup';
import api from '../services/api';
import { authService } from '../services/authService';

const profileSchema = yup.object({
  name: yup.string().required('Nama wajib diisi').min(3, 'Minimal 3 karakter'),
  email: yup.string().required('Email wajib diisi').email('Email tidak valid'),
}).required();

const passwordSchema = yup.object({
  current_password: yup.string().required('Password lama wajib diisi'),
  password: yup.string().required('Password baru wajib diisi').min(8, 'Minimal 8 karakter'),
  password_confirmation: yup.string()
    .required('Konfirmasi password wajib diisi')
    .oneOf([yup.ref('password')], 'Password tidak cocok'),
}).required();

function Profile() {
  const navigate = useNavigate();
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState('profile');
  const [submitting, setSubmitting] = useState(false);

  const profileForm = useForm({
    resolver: yupResolver(profileSchema),
  });

  const passwordForm = useForm({
    resolver: yupResolver(passwordSchema),
  });

  useEffect(() => {
    loadUserData();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const loadUserData = async () => {
    try {
      const userData = await authService.getCurrentUser();
      setUser(userData);
      profileForm.reset({
        name: userData.name,
        email: userData.email,
      });
    } catch {
      alert('Gagal memuat data profile');
      navigate('/dashboard');
    } finally {
      setLoading(false);
    }
  };

  const onSubmitProfile = async (data) => {
    try {
      setSubmitting(true);
      await api.put('/profile', data);
      alert('Profile berhasil diperbarui');
      loadUserData();
    } catch (error) {
      alert('Gagal memperbarui profile: ' + (error.response?.data?.message || 'Terjadi kesalahan'));
    } finally {
      setSubmitting(false);
    }
  };

  const onSubmitPassword = async (data) => {
    try {
      setSubmitting(true);
      await api.put('/profile/password', data);
      alert('Password berhasil diubah');
      passwordForm.reset();
    } catch (error) {
      alert('Gagal mengubah password: ' + (error.response?.data?.message || 'Terjadi kesalahan'));
    } finally {
      setSubmitting(false);
    }
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

  return (
    <div>
      {/* Header */}
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">Profile</h1>
        <p className="text-gray-600 mt-1">Kelola informasi pribadi Anda</p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
        {/* Sidebar */}
        <div className="lg:col-span-1">
          <div className="bg-white rounded-lg shadow-sm p-6">
            <div className="text-center mb-6">
              <div className="w-24 h-24 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <span className="text-3xl font-bold text-blue-600">
                  {user?.name.charAt(0).toUpperCase()}
                </span>
              </div>
              <h3 className="text-lg font-semibold text-gray-900">{user?.name}</h3>
              <p className="text-sm text-gray-600">{user?.email}</p>
              <span className={`inline-block px-3 py-1 mt-2 text-xs font-semibold rounded-full ${
                user?.role === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'
              }`}>
                {user?.role}
              </span>
            </div>

            <div className="space-y-2">
              <button
                onClick={() => setActiveTab('profile')}
                className={`w-full text-left px-4 py-2 rounded-lg ${
                  activeTab === 'profile'
                    ? 'bg-blue-50 text-blue-600 font-medium'
                    : 'text-gray-600 hover:bg-gray-50'
                }`}
              >
                Edit Profile
              </button>
              <button
                onClick={() => setActiveTab('password')}
                className={`w-full text-left px-4 py-2 rounded-lg ${
                  activeTab === 'password'
                    ? 'bg-blue-50 text-blue-600 font-medium'
                    : 'text-gray-600 hover:bg-gray-50'
                }`}
              >
                Ganti Password
              </button>
            </div>
          </div>
        </div>

        {/* Content */}
        <div className="lg:col-span-3">
          <div className="bg-white rounded-lg shadow-sm p-6">
            {activeTab === 'profile' ? (
              <div>
                <h2 className="text-lg font-semibold text-gray-900 mb-6">Edit Profile</h2>
                <form onSubmit={profileForm.handleSubmit(onSubmitProfile)} className="space-y-6">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Nama Lengkap <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="text"
                      {...profileForm.register('name')}
                      className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent ${
                        profileForm.formState.errors.name ? 'border-red-500' : 'border-gray-300'
                      }`}
                    />
                    {profileForm.formState.errors.name && (
                      <p className="mt-1 text-sm text-red-500">{profileForm.formState.errors.name.message}</p>
                    )}
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Email <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="email"
                      {...profileForm.register('email')}
                      className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent ${
                        profileForm.formState.errors.email ? 'border-red-500' : 'border-gray-300'
                      }`}
                    />
                    {profileForm.formState.errors.email && (
                      <p className="mt-1 text-sm text-red-500">{profileForm.formState.errors.email.message}</p>
                    )}
                  </div>

                  <div className="flex justify-end">
                    <button
                      type="submit"
                      disabled={submitting}
                      className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 flex items-center space-x-2"
                    >
                      {submitting && <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>}
                      <span>Simpan Perubahan</span>
                    </button>
                  </div>
                </form>
              </div>
            ) : (
              <div>
                <h2 className="text-lg font-semibold text-gray-900 mb-6">Ganti Password</h2>
                <form onSubmit={passwordForm.handleSubmit(onSubmitPassword)} className="space-y-6">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Password Lama <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="password"
                      {...passwordForm.register('current_password')}
                      className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent ${
                        passwordForm.formState.errors.current_password ? 'border-red-500' : 'border-gray-300'
                      }`}
                    />
                    {passwordForm.formState.errors.current_password && (
                      <p className="mt-1 text-sm text-red-500">{passwordForm.formState.errors.current_password.message}</p>
                    )}
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Password Baru <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="password"
                      {...passwordForm.register('password')}
                      className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent ${
                        passwordForm.formState.errors.password ? 'border-red-500' : 'border-gray-300'
                      }`}
                    />
                    {passwordForm.formState.errors.password && (
                      <p className="mt-1 text-sm text-red-500">{passwordForm.formState.errors.password.message}</p>
                    )}
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Konfirmasi Password Baru <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="password"
                      {...passwordForm.register('password_confirmation')}
                      className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent ${
                        passwordForm.formState.errors.password_confirmation ? 'border-red-500' : 'border-gray-300'
                      }`}
                    />
                    {passwordForm.formState.errors.password_confirmation && (
                      <p className="mt-1 text-sm text-red-500">{passwordForm.formState.errors.password_confirmation.message}</p>
                    )}
                  </div>

                  <div className="flex justify-end">
                    <button
                      type="submit"
                      disabled={submitting}
                      className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 flex items-center space-x-2"
                    >
                      {submitting && <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>}
                      <span>Ganti Password</span>
                    </button>
                  </div>
                </form>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

export default Profile;
