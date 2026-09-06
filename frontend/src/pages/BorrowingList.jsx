import { useCallback, useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { useDebounce } from '../hooks/useDebounce';
import { useInfiniteScroll } from '../hooks/useInfiniteScroll';
import { authService } from '../services/authService';
import { borrowingService } from '../services/borrowingService';

function BorrowingList() {
  const [borrowings, setBorrowings] = useState([]);
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebounce(search, 400);
  const [statusFilter, setStatusFilter] = useState('');
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [displayMode, setDisplayMode] = useState('pagination'); // 'pagination' | 'infinite'
  const isAdmin = authService.isAdmin();

  // Track filter changes to reset page to 1
  const prevFiltersRef = useRef({
    search: debouncedSearch,
    status: statusFilter,
    startDate,
    endDate,
  });

  const loadBorrowings = useCallback(
    async (pageToLoad, isAppend = false) => {
      try {
        if (isAppend) {
          setLoadingMore(true);
        } else {
          setLoading(true);
        }

        const params = {
          page: pageToLoad,
          search: debouncedSearch,
          status: statusFilter,
          start_date: startDate,
          end_date: endDate,
        };

        const response = await borrowingService.getAll(params);
        const data = response.data?.data || response.data || [];
        const lastPage = response.data?.last_page || response.last_page || 1;

        setTotalPages(lastPage);

        if (isAppend) {
          setBorrowings((prev) => [...prev, ...data]);
        } else {
          setBorrowings(data);
        }
      } catch (error) {
        console.error('Failed to load borrowings:', error);
        if (!isAppend) {
          alert('Gagal memuat data peminjaman');
        }
      } finally {
        setLoading(false);
        setLoadingMore(false);
      }
    },
    [debouncedSearch, statusFilter, startDate, endDate]
  );

  useEffect(() => {
    const prev = prevFiltersRef.current;
    const filtersChanged =
      prev.search !== debouncedSearch ||
      prev.status !== statusFilter ||
      prev.startDate !== startDate ||
      prev.endDate !== endDate;

    if (filtersChanged) {
      prevFiltersRef.current = {
        search: debouncedSearch,
        status: statusFilter,
        startDate,
        endDate,
      };

      if (currentPage !== 1) {
        setCurrentPage(1);
        return;
      }
    }

    const isAppend = displayMode === 'infinite' && currentPage > 1 && !filtersChanged;
    loadBorrowings(currentPage, isAppend);
  }, [currentPage, debouncedSearch, statusFilter, startDate, endDate, displayMode, loadBorrowings]);

  const handleModeChange = (mode) => {
    if (mode === displayMode) return;
    setDisplayMode(mode);
    setCurrentPage(1);
  };

  const handleLoadMore = useCallback(() => {
    if (displayMode === 'infinite' && !loading && !loadingMore && currentPage < totalPages) {
      setCurrentPage((prev) => prev + 1);
    }
  }, [displayMode, loading, loadingMore, currentPage, totalPages]);

  const { sentinelRef } = useInfiniteScroll({
    onLoadMore: handleLoadMore,
    hasMore: displayMode === 'infinite' && currentPage < totalPages,
    loading: loading || loadingMore,
    rootMargin: '250px',
  });

  const handleApprove = async (id) => {
    if (!confirm('Approve peminjaman ini?')) return;

    try {
      await borrowingService.approve(id);
      loadBorrowings(1, false);
      setCurrentPage(1);
    } catch (error) {
      alert('Gagal approve peminjaman: ' + (error.response?.data?.message || 'Terjadi kesalahan'));
    }
  };

  const getStatusBadge = (borrowing) => {
    if (borrowing.status === 'returned') {
      return 'bg-green-100 text-green-800';
    } else if (borrowing.is_overdue) {
      return 'bg-red-100 text-red-800';
    } else if (borrowing.status === 'pending') {
      return 'bg-yellow-100 text-yellow-800';
    } else {
      return 'bg-blue-100 text-blue-800';
    }
  };

  const getStatusText = (borrowing) => {
    if (borrowing.status === 'returned') return 'Dikembalikan';
    if (borrowing.is_overdue) return 'Terlambat';
    if (borrowing.status === 'pending') return 'Pending';
    if (borrowing.status === 'approved') return 'Dipinjam';
    return borrowing.status;
  };

  return (
    <div>
      {/* Header */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Manajemen Peminjaman</h1>
          <p className="text-gray-600 mt-1">Kelola peminjaman barang inventaris</p>
        </div>

        <div className="flex flex-wrap items-center gap-3">
          {/* Dual-Mode Toggle */}
          <div className="inline-flex bg-gray-100 p-1 rounded-lg border border-gray-200 text-xs font-medium">
            <button
              type="button"
              onClick={() => handleModeChange('pagination')}
              className={`px-3 py-1.5 rounded-md transition-all flex items-center space-x-1.5 ${
                displayMode === 'pagination'
                  ? 'bg-white text-gray-900 shadow-sm font-semibold'
                  : 'text-gray-600 hover:text-gray-900'
              }`}
              title="Mode Halaman Tradisional"
            >
              <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              <span>Halaman</span>
            </button>
            <button
              type="button"
              onClick={() => handleModeChange('infinite')}
              className={`px-3 py-1.5 rounded-md transition-all flex items-center space-x-1.5 ${
                displayMode === 'infinite'
                  ? 'bg-white text-blue-600 shadow-sm font-semibold'
                  : 'text-gray-600 hover:text-gray-900'
              }`}
              title="Mode Scroll Otomatis (Infinite Scroll)"
            >
              <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 14l-7 7m0 0l-7-7m7 7V3" />
              </svg>
              <span>Scroll Otomatis</span>
            </button>
          </div>

          <Link
            to="/borrowings/create"
            className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center space-x-2 text-sm font-medium shadow-sm"
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
            </svg>
            <span>Pinjam Barang</span>
          </Link>
        </div>
      </div>

      {/* Filters */}
      <div className="bg-white rounded-lg shadow-sm p-4 mb-6">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          {/* Search */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Cari</label>
            <input
              type="text"
              placeholder="Kode/User/Barang..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>

          {/* Status Filter */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option value="">Semua Status</option>
              <option value="pending">Pending</option>
              <option value="approved">Dipinjam</option>
              <option value="returned">Dikembalikan</option>
              <option value="overdue">Terlambat</option>
            </select>
          </div>

          {/* Start Date */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Dari Tanggal</label>
            <input
              type="date"
              value={startDate}
              onChange={(e) => setStartDate(e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>

          {/* End Date */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Sampai Tanggal</label>
            <input
              type="date"
              value={endDate}
              onChange={(e) => setEndDate(e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
        </div>
      </div>

      {/* Table */}
      <div className="bg-white rounded-lg shadow-sm overflow-hidden">
        {loading ? (
          <div className="text-center py-12">
            <div className="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
            <p className="mt-4 text-gray-600">Memuat data...</p>
          </div>
        ) : borrowings.length === 0 ? (
          <div className="text-center py-12">
            <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <p className="mt-4 text-gray-600">Tidak ada data peminjaman</p>
          </div>
        ) : (
          <>
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Peminjam</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Barang</th>
                    <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tgl Pinjam</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jatuh Tempo</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tgl Kembali</th>
                    <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {borrowings.map((borrowing) => (
                    <tr key={borrowing.id} className="hover:bg-gray-50">
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="text-sm font-medium text-gray-900">{borrowing.code}</div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="text-sm text-gray-900">{borrowing.user?.name}</div>
                        <div className="text-sm text-gray-500">{borrowing.user?.email}</div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="text-sm text-gray-900">{borrowing.item?.name}</div>
                        <div className="text-sm text-gray-500">{borrowing.item?.code}</div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-center">
                        <span className="text-sm font-medium text-gray-900">{borrowing.quantity}</span>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                        {new Date(borrowing.borrow_date).toLocaleDateString('id-ID')}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                        {new Date(borrowing.due_date).toLocaleDateString('id-ID')}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                        {borrowing.return_date ? new Date(borrowing.return_date).toLocaleDateString('id-ID') : '-'}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-center">
                        <span className={`inline-block px-3 py-1 text-xs font-semibold rounded-full ${getStatusBadge(borrowing)}`}>
                          {getStatusText(borrowing)}
                        </span>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                        <Link
                          to={`/borrowings/${borrowing.id}`}
                          className="text-blue-600 hover:text-blue-900 mr-3"
                          title="Detail"
                        >
                          <svg className="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                          </svg>
                        </Link>
                        {borrowing.status === 'approved' && (
                          <Link
                            to={`/borrowings/${borrowing.id}/return`}
                            className="text-green-600 hover:text-green-900 mr-3"
                            title="Kembalikan"
                          >
                            <svg className="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                            </svg>
                          </Link>
                        )}
                        {isAdmin && borrowing.status === 'pending' && (
                          <button
                            onClick={() => handleApprove(borrowing.id)}
                            className="text-purple-600 hover:text-purple-900"
                            title="Approve"
                          >
                            <svg className="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                          </button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Mode Infinite Scroll: Sentinel / Footer Notice */}
            {displayMode === 'infinite' && (
              <div className="bg-white px-6 py-4 border-t border-gray-200">
                {currentPage < totalPages ? (
                  <div ref={sentinelRef} className="flex flex-col items-center justify-center py-2">
                    {loadingMore ? (
                      <div className="flex items-center space-x-2 text-blue-600 text-sm font-medium">
                        <div className="inline-block animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600"></div>
                        <span>Memuat lebih banyak peminjaman...</span>
                      </div>
                    ) : (
                      <span className="text-xs text-gray-400">Gulir ke bawah untuk memuat lebih banyak...</span>
                    )}
                  </div>
                ) : (
                  <p className="text-center text-sm text-gray-500 py-1 font-medium">
                    Semua peminjaman telah ditampilkan ({borrowings.length} data)
                  </p>
                )}
              </div>
            )}

            {/* Mode Pagination: Traditional Numbered Buttons */}
            {displayMode === 'pagination' && totalPages > 1 && (
              <div className="px-6 py-4 border-t border-gray-200">
                <div className="flex items-center justify-between">
                  <div className="text-sm text-gray-600">
                    Halaman <span className="font-medium">{currentPage}</span> dari <span className="font-medium">{totalPages}</span>
                  </div>
                  <div className="flex space-x-2">
                    <button
                      onClick={() => setCurrentPage(currentPage - 1)}
                      disabled={currentPage === 1}
                      className="px-3 py-1 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed text-sm"
                    >
                      Previous
                    </button>
                    <button
                      onClick={() => setCurrentPage(currentPage + 1)}
                      disabled={currentPage === totalPages}
                      className="px-3 py-1 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed text-sm"
                    >
                      Next
                    </button>
                  </div>
                </div>
              </div>
            )}
          </>
        )}
      </div>
    </div>
  );
}

export default BorrowingList;
