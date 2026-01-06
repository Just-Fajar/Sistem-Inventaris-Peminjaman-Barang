import {
    ArcElement,
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Title,
    Tooltip,
} from 'chart.js';
import { useEffect, useState } from 'react';
import { Bar, Doughnut, Line } from 'react-chartjs-2';
import { borrowingService } from '../services/borrowingService';
import { itemService } from '../services/itemService';

ChartJS.register(
  CategoryScale,
  LinearScale,
  BarElement,
  ArcElement,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend
);

function Reports() {
  const [activeTab, setActiveTab] = useState('borrowing');
  const [loading, setLoading] = useState(true);
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');
  
  // Data states
  const [borrowingStats, setBorrowingStats] = useState(null);
  const [itemStats, setItemStats] = useState(null);
  const [overdueStats, setOverdueStats] = useState(null);

  useEffect(() => {
    loadReportData();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [activeTab, startDate, endDate]);

  const loadReportData = async () => {
    setLoading(true);
    try {
      const params = {
        start_date: startDate,
        end_date: endDate,
      };

      if (activeTab === 'borrowing') {
        const response = await borrowingService.getAll(params);
        processBorrowingStats(response.data.data || response.data);
      } else if (activeTab === 'items') {
        const response = await itemService.getAll({ all: true });
        processItemStats(response.data.data || response.data);
      } else if (activeTab === 'overdue') {
        const response = await borrowingService.getAll({ ...params, status: 'overdue' });
        processOverdueStats(response.data.data || response.data);
      }
    } catch {
      alert('Gagal memuat data laporan');
    } finally {
      setLoading(false);
    }
  };

  const processBorrowingStats = (data) => {
    const statusCounts = data.reduce((acc, item) => {
      const status = item.status;
      acc[status] = (acc[status] || 0) + 1;
      return acc;
    }, {});

    const monthCounts = data.reduce((acc, item) => {
      const month = new Date(item.borrow_date).toLocaleDateString('id-ID', { month: 'short' });
      acc[month] = (acc[month] || 0) + 1;
      return acc;
    }, {});

    setBorrowingStats({
      total: data.length,
      statusCounts,
      monthCounts,
      data,
    });
  };

  const processItemStats = (data) => {
    const categoryCounts = data.reduce((acc, item) => {
      const category = item.category?.name || 'Tanpa Kategori';
      acc[category] = (acc[category] || 0) + 1;
      return acc;
    }, {});

    const conditionCounts = data.reduce((acc, item) => {
      const condition = item.condition;
      acc[condition] = (acc[condition] || 0) + 1;
      return acc;
    }, {});

    setItemStats({
      total: data.length,
      totalStock: data.reduce((sum, item) => sum + item.stock, 0),
      available: data.reduce((sum, item) => sum + item.available_stock, 0),
      categoryCounts,
      conditionCounts,
      data,
    });
  };

  const processOverdueStats = (data) => {
    setOverdueStats({
      total: data.length,
      data,
    });
  };

  const tabs = [
    { id: 'borrowing', label: 'Laporan Peminjaman', icon: '📋' },
    { id: 'items', label: 'Laporan Barang', icon: '📦' },
    { id: 'overdue', label: 'Laporan Keterlambatan', icon: '⚠️' },
  ];

  return (
    <div>
      {/* Header */}
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">Laporan</h1>
        <p className="text-gray-600 mt-1">Analisis dan statistik sistem inventaris</p>
      </div>

      {/* Tabs */}
      <div className="bg-white rounded-lg shadow-sm mb-6">
        <div className="border-b border-gray-200">
          <nav className="flex -mb-px">
            {tabs.map((tab) => (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                className={`px-6 py-3 text-sm font-medium border-b-2 transition-colors ${
                  activeTab === tab.id
                    ? 'border-blue-500 text-blue-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                }`}
              >
                <span className="mr-2">{tab.icon}</span>
                {tab.label}
              </button>
            ))}
          </nav>
        </div>

        {/* Filters */}
        <div className="p-4 border-b border-gray-200">
          <div className="flex flex-wrap items-end gap-4">
            <div className="flex-1 min-w-50">
              <label className="block text-sm font-medium text-gray-700 mb-1">Dari Tanggal</label>
              <input
                type="date"
                value={startDate}
                onChange={(e) => setStartDate(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
            </div>
            <div className="flex-1 min-w-50">
              <label className="block text-sm font-medium text-gray-700 mb-1">Sampai Tanggal</label>
              <input
                type="date"
                value={endDate}
                onChange={(e) => setEndDate(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
            </div>
            <button
              onClick={loadReportData}
              className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
            >
              Terapkan Filter
            </button>
            <button
              onClick={() => {
                setStartDate('');
                setEndDate('');
              }}
              className="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
            >
              Reset
            </button>
            <button
              className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center space-x-2"
              title="Export (Coming Soon)"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              <span>Export</span>
            </button>
          </div>
        </div>
      </div>

      {/* Content */}
      {loading ? (
        <div className="bg-white rounded-lg shadow-sm p-12 text-center">
          <div className="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
          <p className="mt-4 text-gray-600">Memuat data...</p>
        </div>
      ) : (
        <>
          {activeTab === 'borrowing' && borrowingStats && <BorrowingReport stats={borrowingStats} />}
          {activeTab === 'items' && itemStats && <ItemReport stats={itemStats} />}
          {activeTab === 'overdue' && overdueStats && <OverdueReport stats={overdueStats} />}
        </>
      )}
    </div>
  );
}

// Borrowing Report Component
function BorrowingReport({ stats }) {
  const statusChartData = {
    labels: Object.keys(stats.statusCounts),
    datasets: [
      {
        label: 'Jumlah Peminjaman',
        data: Object.values(stats.statusCounts),
        backgroundColor: ['#FCD34D', '#60A5FA', '#34D399', '#F87171'],
      },
    ],
  };

  const monthChartData = {
    labels: Object.keys(stats.monthCounts),
    datasets: [
      {
        label: 'Peminjaman per Bulan',
        data: Object.values(stats.monthCounts),
        borderColor: '#3B82F6',
        backgroundColor: 'rgba(59, 130, 246, 0.1)',
        tension: 0.4,
      },
    ],
  };

  return (
    <div className="space-y-6">
      {/* Summary Cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div className="bg-white rounded-lg shadow-sm p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-gray-600">Total Peminjaman</p>
              <p className="text-2xl font-bold text-gray-900 mt-1">{stats.total}</p>
            </div>
            <div className="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
              <svg className="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
          </div>
        </div>
        {Object.entries(stats.statusCounts).map(([status, count]) => (
          <div key={status} className="bg-white rounded-lg shadow-sm p-6">
            <p className="text-sm text-gray-600 capitalize">{status}</p>
            <p className="text-2xl font-bold text-gray-900 mt-1">{count}</p>
          </div>
        ))}
      </div>

      {/* Charts */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="bg-white rounded-lg shadow-sm p-6">
          <h3 className="text-lg font-semibold text-gray-900 mb-4">Status Peminjaman</h3>
          <Doughnut data={statusChartData} />
        </div>
        <div className="bg-white rounded-lg shadow-sm p-6">
          <h3 className="text-lg font-semibold text-gray-900 mb-4">Tren Peminjaman</h3>
          <Line data={monthChartData} />
        </div>
      </div>
    </div>
  );
}

// Item Report Component
function ItemReport({ stats }) {
  const categoryChartData = {
    labels: Object.keys(stats.categoryCounts),
    datasets: [
      {
        label: 'Jumlah Barang',
        data: Object.values(stats.categoryCounts),
        backgroundColor: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6'],
      },
    ],
  };

  const conditionChartData = {
    labels: Object.keys(stats.conditionCounts),
    datasets: [
      {
        label: 'Kondisi Barang',
        data: Object.values(stats.conditionCounts),
        backgroundColor: ['#10B981', '#F59E0B', '#EF4444'],
      },
    ],
  };

  return (
    <div className="space-y-6">
      {/* Summary Cards */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div className="bg-white rounded-lg shadow-sm p-6">
          <p className="text-sm text-gray-600">Total Barang</p>
          <p className="text-2xl font-bold text-gray-900 mt-1">{stats.total}</p>
        </div>
        <div className="bg-white rounded-lg shadow-sm p-6">
          <p className="text-sm text-gray-600">Total Stok</p>
          <p className="text-2xl font-bold text-gray-900 mt-1">{stats.totalStock}</p>
        </div>
        <div className="bg-white rounded-lg shadow-sm p-6">
          <p className="text-sm text-gray-600">Stok Tersedia</p>
          <p className="text-2xl font-bold text-green-600 mt-1">{stats.available}</p>
        </div>
      </div>

      {/* Charts */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="bg-white rounded-lg shadow-sm p-6">
          <h3 className="text-lg font-semibold text-gray-900 mb-4">Barang per Kategori</h3>
          <Bar data={categoryChartData} />
        </div>
        <div className="bg-white rounded-lg shadow-sm p-6">
          <h3 className="text-lg font-semibold text-gray-900 mb-4">Kondisi Barang</h3>
          <Doughnut data={conditionChartData} />
        </div>
      </div>
    </div>
  );
}

// Overdue Report Component
function OverdueReport({ stats }) {
  return (
    <div className="space-y-6">
      <div className="bg-red-50 border border-red-200 rounded-lg p-6">
        <div className="flex items-center">
          <div className="shrink-0">
            <svg className="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          </div>
          <div className="ml-4">
            <h3 className="text-lg font-semibold text-red-900">Total Keterlambatan</h3>
            <p className="text-3xl font-bold text-red-600 mt-1">{stats.total} Peminjaman</p>
          </div>
        </div>
      </div>

      {stats.total > 0 ? (
        <div className="bg-white rounded-lg shadow-sm overflow-hidden">
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kode</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Peminjam</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Barang</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jatuh Tempo</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Terlambat</th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {stats.data.map((borrowing) => {
                  const daysOverdue = Math.floor(
                    (new Date() - new Date(borrowing.due_date)) / (1000 * 60 * 60 * 24)
                  );
                  return (
                    <tr key={borrowing.id}>
                      <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        {borrowing.code}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                        {borrowing.user?.name}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                        {borrowing.item?.name}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                        {new Date(borrowing.due_date).toLocaleDateString('id-ID')}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <span className="text-sm font-medium text-red-600">{daysOverdue} hari</span>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      ) : (
        <div className="bg-white rounded-lg shadow-sm p-12 text-center">
          <svg className="mx-auto h-12 w-12 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <p className="mt-4 text-gray-600">Tidak ada peminjaman yang terlambat</p>
        </div>
      )}
    </div>
  );
}

export default Reports;
