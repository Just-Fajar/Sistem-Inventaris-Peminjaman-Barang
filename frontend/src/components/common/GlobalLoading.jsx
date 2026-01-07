import PropTypes from 'prop-types';

/**
 * GlobalLoading Component
 * Full-screen loading indicator for page transitions or initial loads
 */
function GlobalLoading({ message = 'Memuat...' }) {
  return (
    <div className="fixed inset-0 bg-white bg-opacity-90 flex items-center justify-center z-50">
      <div className="text-center">
        <div className="inline-block animate-spin rounded-full h-16 w-16 border-t-4 border-b-4 border-blue-600 mb-4"></div>
        <p className="text-gray-700 text-lg font-medium">{message}</p>
      </div>
    </div>
  );
}

GlobalLoading.propTypes = {
  message: PropTypes.string,
};

export default GlobalLoading;
