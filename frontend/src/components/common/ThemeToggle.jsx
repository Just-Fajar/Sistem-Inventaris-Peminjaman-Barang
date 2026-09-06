import { useTheme } from '../../contexts/ThemeContext';

/**
 * ThemeToggle Component
 * Allows user to switch between light, dark, and system themes.
 */
export function ThemeToggle({ className = '' }) {
  const { theme, setTheme } = useTheme();

  return (
    <div
      className={`inline-flex items-center p-1 bg-gray-100 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 ${className}`}
      role="group"
      aria-label="Theme switcher"
    >
      <button
        type="button"
        onClick={() => setTheme('light')}
        aria-label="Light mode"
        title="Mode Terang"
        className={`p-1.5 rounded-md transition-colors flex items-center justify-center ${
          theme === 'light'
            ? 'bg-white dark:bg-gray-700 text-amber-500 shadow-xs font-semibold'
            : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200'
        }`}
      >
        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M12 3v1m0 16v1m9-9h-1M4 9h-1m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z"
          />
        </svg>
      </button>
      <button
        type="button"
        onClick={() => setTheme('dark')}
        aria-label="Dark mode"
        title="Mode Gelap"
        className={`p-1.5 rounded-md transition-colors flex items-center justify-center ${
          theme === 'dark'
            ? 'bg-white dark:bg-gray-700 text-indigo-400 shadow-xs font-semibold'
            : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200'
        }`}
      >
        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"
          />
        </svg>
      </button>
      <button
        type="button"
        onClick={() => setTheme('system')}
        aria-label="System theme"
        title="Ikuti Sistem"
        className={`p-1.5 rounded-md transition-colors flex items-center justify-center ${
          theme === 'system'
            ? 'bg-white dark:bg-gray-700 text-blue-500 shadow-xs font-semibold'
            : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200'
        }`}
      >
        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
          />
        </svg>
      </button>
    </div>
  );
}

export default ThemeToggle;
