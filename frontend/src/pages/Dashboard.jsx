import { useEffect, useState } from 'react';
import { authService } from '../services/authService';
import { dashboardService } from '../services/dashboardService';

function Dashboard() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const user = authService.getCurrentUser();

  useEffect(() => {
    loadDashboard();
  }, []);

  const loadDashboard = async () => {
    try {
      const response = await dashboardService.getData();
      setData(response);
    } catch (error) {
      console.error('Failed to load dashboard:', error);
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="text-xl">Loading...</div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Dashboard</h1>
        <p className="text-gray-600 dark:text-gray-400 mt-1">
          Ringkasan aktivitas dan inventaris barang
        </p>
      </div>

      {/* Statistics Cards */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm rounded-lg p-5">
          <div className="flex items-center">
            <div className="flex-1">
              <dt className="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                Total Barang
              </dt>
              <dd className="mt-1 text-3xl font-semibold text-gray-900 dark:text-gray-100">
                {data?.items?.total || 0}
              </dd>
            </div>
          </div>
        </div>

        <div className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm rounded-lg p-5">
          <div className="flex items-center">
            <div className="flex-1">
              <dt className="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                Barang Tersedia
              </dt>
              <dd className="mt-1 text-3xl font-semibold text-green-600 dark:text-green-400">
                {data?.items?.available || 0}
              </dd>
            </div>
          </div>
        </div>

        <div className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm rounded-lg p-5">
          <div className="flex items-center">
            <div className="flex-1">
              <dt className="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                Sedang Dipinjam
              </dt>
              <dd className="mt-1 text-3xl font-semibold text-blue-600 dark:text-blue-400">
                {data?.borrowings?.active || 0}
              </dd>
            </div>
          </div>
        </div>
      </div>

      {/* Recent Borrowings */}
      <div className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm rounded-lg p-6">
        <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
          Peminjaman Terbaru
        </h2>
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
            <thead className="bg-gray-50 dark:bg-gray-800">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Kode
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Barang
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  User
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Status
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200 dark:divide-gray-800 bg-white dark:bg-gray-900">
              {data?.recent_borrowings && data.recent_borrowings.length > 0 ? (
                data.recent_borrowings.map((borrowing) => (
                  <tr key={borrowing.id} className="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                      {borrowing.borrow_code || borrowing.code}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                      {borrowing.item?.name}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                      {borrowing.user?.name}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span
                        className={`px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${
                          borrowing.status === 'dipinjam' || borrowing.status === 'approved'
                            ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300'
                            : borrowing.status === 'terlambat' || borrowing.is_overdue
                            ? 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300'
                            : borrowing.status === 'dikembalikan' || borrowing.status === 'returned'
                            ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                            : borrowing.status === 'pending'
                            ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300'
                            : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300'
                        }`}
                      >
                        {borrowing.status === 'dipinjam' || borrowing.status === 'approved'
                          ? 'Dipinjam'
                          : borrowing.status === 'terlambat' || borrowing.is_overdue
                          ? 'Terlambat'
                          : borrowing.status === 'dikembalikan' || borrowing.status === 'returned'
                          ? 'Dikembalikan'
                          : borrowing.status === 'pending'
                          ? 'Pending'
                          : borrowing.status}
                      </span>
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan="4" className="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                    Belum ada riwayat peminjaman terbaru.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}

export default Dashboard;
