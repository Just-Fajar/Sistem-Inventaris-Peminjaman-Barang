import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { userService } from '../services/userService';

function UserDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadUser();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  const loadUser = async () => {
    try {
      setLoading(true);
      const response = await userService.getById(id);
      setUser(response.data || response);
    } catch {
      alert('Gagal memuat data pengguna');
      navigate('/users');
    } finally {
      setLoading(false);
    }
  };

  const getRoleBadge = (role) => {
    return role === 'admin'
      ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300'
      : 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300';
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[50vh]">
        <div className="text-center">
          <div className="inline-block animate-spin rounded-full h-10 w-10 border-b-2 border-blue-600"></div>
          <p className="mt-3 text-sm text-gray-600 dark:text-gray-400">Memuat data pengguna...</p>
        </div>
      </div>
    );
  }

  if (!user) return null;

  return (
    <div>
      {/* Breadcrumbs */}
      <div className="mb-6">
        <div className="flex items-center space-x-2 text-sm text-gray-600 dark:text-gray-400 mb-2">
          <Link to="/users" className="hover:text-blue-600 dark:hover:text-blue-400">
            Manajemen User
          </Link>
          <span>/</span>
          <span className="text-gray-900 dark:text-gray-100">Detail Pengguna</span>
        </div>

        <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
          <div>
            <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">{user.name}</h1>
            <p className="text-gray-600 dark:text-gray-400 mt-1 text-sm">{user.email}</p>
          </div>

          <div className="flex items-center space-x-3">
            <Link
              to="/users"
              className="px-4 py-2 border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 text-sm font-medium transition-colors"
            >
              Kembali
            </Link>
            <Link
              to={`/users/${user.id}/edit`}
              className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium flex items-center space-x-2 transition-colors shadow-sm"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                />
              </svg>
              <span>Edit User</span>
            </Link>
          </div>
        </div>
      </div>

      {/* User Information Card */}
      <div className="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-800 p-6 max-w-2xl">
        <div className="flex items-center space-x-4 mb-6 pb-6 border-b border-gray-200 dark:border-gray-800">
          <div className="w-16 h-16 rounded-full bg-blue-600 text-white flex items-center justify-center text-2xl font-bold">
            {user.name?.charAt(0).toUpperCase()}
          </div>
          <div>
            <h2 className="text-xl font-semibold text-gray-900 dark:text-gray-100">{user.name}</h2>
            <p className="text-sm text-gray-500 dark:text-gray-400">{user.email}</p>
            <span className={`inline-block px-3 py-0.5 text-xs font-semibold rounded-full mt-2 ${getRoleBadge(user.role)}`}>
              {user.role}
            </span>
          </div>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
          <div>
            <p className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">ID Pengguna</p>
            <p className="text-base font-semibold text-gray-900 dark:text-gray-100 mt-1">#{user.id}</p>
          </div>
          <div>
            <p className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Peran (Role)</p>
            <p className="text-base font-semibold text-gray-900 dark:text-gray-100 mt-1 capitalize">{user.role}</p>
          </div>
          <div>
            <p className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tanggal Bergabung</p>
            <p className="text-base font-semibold text-gray-900 dark:text-gray-100 mt-1">
              {user.created_at ? new Date(user.created_at).toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric' }) : '-'}
            </p>
          </div>
          <div>
            <p className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Terakhir Diperbarui</p>
            <p className="text-base font-semibold text-gray-900 dark:text-gray-100 mt-1">
              {user.updated_at ? new Date(user.updated_at).toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric' }) : '-'}
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}

export default UserDetail;
