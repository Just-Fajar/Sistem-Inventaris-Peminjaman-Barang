import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { authService } from '../services/authService';

function Register() {
  const navigate = useNavigate();
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
  });
  const [error, setError] = useState('');
  const [validationErrors, setValidationErrors] = useState({});
  const [loading, setLoading] = useState(false);

  const handleChange = (e) => {
    setFormData({
      ...formData,
      [e.target.name]: e.target.value,
    });
    if (validationErrors[e.target.name]) {
      setValidationErrors({
        ...validationErrors,
        [e.target.name]: null,
      });
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setValidationErrors({});

    if (formData.password !== formData.password_confirmation) {
      setError('Konfirmasi password tidak cocok.');
      return;
    }

    setLoading(true);

    try {
      await authService.register(formData);
      navigate('/dashboard');
    } catch (err) {
      if (err.response?.status === 422 && err.response?.data?.errors) {
        setValidationErrors(err.response.data.errors);
      } else {
        setError(err.response?.data?.message || 'Registrasi gagal. Silakan periksa data Anda.');
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-100 py-12 px-4 sm:px-6 lg:px-8">
      <div className="max-w-md w-full space-y-8 p-8 bg-white rounded-lg shadow-lg">
        <div>
          <h2 className="text-center text-3xl font-bold text-gray-900">
            Daftar Akun Baru
          </h2>
          <p className="mt-2 text-center text-sm text-gray-600">
            Sistem Inventaris & Peminjaman Barang
          </p>
        </div>

        <form className="mt-8 space-y-6" onSubmit={handleSubmit}>
          {error && (
            <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded text-sm">
              {error}
            </div>
          )}

          <div className="space-y-4">
            <div>
              <label htmlFor="name" className="block text-sm font-medium text-gray-700">
                Nama Lengkap
              </label>
              <input
                id="name"
                name="name"
                type="text"
                required
                value={formData.name}
                onChange={handleChange}
                className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                placeholder="Masukkan nama lengkap"
              />
              {validationErrors.name && (
                <p className="mt-1 text-xs text-red-600">{validationErrors.name[0]}</p>
              )}
            </div>

            <div>
              <label htmlFor="email" className="block text-sm font-medium text-gray-700">
                Alamat Email
              </label>
              <input
                id="email"
                name="email"
                type="email"
                required
                value={formData.email}
                onChange={handleChange}
                className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                placeholder="nama@instansi.id"
              />
              {validationErrors.email && (
                <p className="mt-1 text-xs text-red-600">{validationErrors.email[0]}</p>
              )}
            </div>

            <div>
              <label htmlFor="password" className="block text-sm font-medium text-gray-700">
                Password
              </label>
              <input
                id="password"
                name="password"
                type="password"
                required
                value={formData.password}
                onChange={handleChange}
                className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                placeholder="Minimal 8 karakter (huruf, angka, simbol)"
              />
              {validationErrors.password && (
                <p className="mt-1 text-xs text-red-600">{validationErrors.password[0]}</p>
              )}
            </div>

            <div>
              <label htmlFor="password_confirmation" className="block text-sm font-medium text-gray-700">
                Konfirmasi Password
              </label>
              <input
                id="password_confirmation"
                name="password_confirmation"
                type="password"
                required
                value={formData.password_confirmation}
                onChange={handleChange}
                className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                placeholder="Ulangi password"
              />
            </div>
          </div>

          <div>
            <button
              type="submit"
              disabled={loading}
              className="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
            >
              {loading ? 'Memproses...' : 'Daftar Sebagai Staff'}
            </button>
          </div>

          <div className="text-center text-sm">
            <span className="text-gray-600">Sudah punya akun? </span>
            <Link to="/login" className="text-blue-600 hover:text-blue-500 font-medium">
              Login di sini
            </Link>
          </div>
        </form>
      </div>
    </div>
  );
}

export default Register;
