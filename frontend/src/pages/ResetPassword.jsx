import { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { authService } from '../services/authService';

function ResetPassword() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();

  const token = searchParams.get('token') || '';
  const emailParam = searchParams.get('email') || '';

  const [formData, setFormData] = useState({
    email: emailParam,
    password: '',
    password_confirmation: '',
  });

  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [validationErrors, setValidationErrors] = useState({});

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
    setMessage('');
    setValidationErrors({});

    if (!token) {
      setError('Token reset password tidak ditemukan di tautan URL.');
      return;
    }

    if (formData.password !== formData.password_confirmation) {
      setError('Konfirmasi password tidak cocok.');
      return;
    }

    setLoading(true);

    try {
      const response = await authService.resetPassword({
        token,
        email: formData.email,
        password: formData.password,
        password_confirmation: formData.password_confirmation,
      });

      setMessage(response.message || 'Password Anda berhasil direset! Silakan login.');
    } catch (err) {
      if (err.response?.status === 422 && err.response?.data?.errors) {
        setValidationErrors(err.response.data.errors);
      } else {
        setError(
          err.response?.data?.message ||
          'Reset password gagal. Token mungkin tidak valid atau telah kedaluwarsa.'
        );
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
            Reset Password
          </h2>
          <p className="mt-2 text-center text-sm text-gray-600">
            Masukkan password baru yang aman untuk akun Anda
          </p>
        </div>

        {error && (
          <div className="bg-red-50 border-l-4 border-red-400 p-4 rounded text-sm text-red-700">
            {error}
          </div>
        )}

        {message ? (
          <div className="space-y-6">
            <div className="bg-green-50 border-l-4 border-green-400 p-4 rounded text-sm text-green-700">
              <p className="font-medium">Berhasil!</p>
              <p className="mt-1">{message}</p>
            </div>
            <div className="text-center">
              <Link
                to="/login"
                className="inline-flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
              >
                Login Sekarang
              </Link>
            </div>
          </div>
        ) : (
          <form className="mt-8 space-y-6" onSubmit={handleSubmit}>
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
                className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                placeholder="nama@example.com"
              />
              {validationErrors.email && (
                <p className="mt-1 text-xs text-red-600">{validationErrors.email[0]}</p>
              )}
            </div>

            <div>
              <label htmlFor="password" className="block text-sm font-medium text-gray-700">
                Password Baru
              </label>
              <input
                id="password"
                name="password"
                type="password"
                required
                value={formData.password}
                onChange={handleChange}
                className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                placeholder="Minimal 8 karakter (huruf besar, angka, simbol)"
              />
              {validationErrors.password && (
                <p className="mt-1 text-xs text-red-600">{validationErrors.password[0]}</p>
              )}
            </div>

            <div>
              <label
                htmlFor="password_confirmation"
                className="block text-sm font-medium text-gray-700"
              >
                Konfirmasi Password Baru
              </label>
              <input
                id="password_confirmation"
                name="password_confirmation"
                type="password"
                required
                value={formData.password_confirmation}
                onChange={handleChange}
                className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                placeholder="Ulangi password baru"
              />
            </div>

            <div className="bg-blue-50 p-3 rounded-md text-xs text-blue-800 space-y-1">
              <p className="font-semibold">Ketentuan Password:</p>
              <ul className="list-disc list-inside space-y-0.5">
                <li>Minimal 8 karakter</li>
                <li>Mengandung huruf besar (A-Z) dan kecil (a-z)</li>
                <li>Mengandung setidaknya 1 angka (0-9)</li>
                <li>Mengandung setidaknya 1 karakter khusus (!@#$%^&amp;* dll.)</li>
              </ul>
            </div>

            <div>
              <button
                type="submit"
                disabled={loading}
                className="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
              >
                {loading ? 'Menyimpan...' : 'Simpan Password Baru'}
              </button>
            </div>

            <div className="text-center text-sm">
              <Link to="/login" className="text-blue-600 hover:text-blue-500 font-medium">
                Kembali ke Login
              </Link>
            </div>
          </form>
        )}
      </div>
    </div>
  );
}

export default ResetPassword;
