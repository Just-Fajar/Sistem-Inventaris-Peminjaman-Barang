import PropTypes from 'prop-types';
import { memo } from 'react';

const Card = memo(function Card({ children, title, subtitle, className = '', padding = true, ...props }) {
  return (
    <div className={`bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-lg shadow-sm ${className} transition-colors`} {...props}>
      {(title || subtitle) && (
        <div className={`border-b border-gray-200 dark:border-gray-800 ${padding ? 'px-6 py-4' : ''}`}>
          {title && <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">{title}</h3>}
          {subtitle && <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">{subtitle}</p>}
        </div>
      )}
      <div className={padding ? 'p-6' : ''}>
        {children}
      </div>
    </div>
  );
});

Card.propTypes = {
  children: PropTypes.node.isRequired,
  title: PropTypes.string,
  subtitle: PropTypes.string,
  className: PropTypes.string,
  padding: PropTypes.bool,
};

export default Card;
