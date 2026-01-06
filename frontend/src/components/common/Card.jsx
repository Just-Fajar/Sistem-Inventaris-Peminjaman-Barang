import PropTypes from 'prop-types';

function Card({ children, title, subtitle, className = '', padding = true, ...props }) {
  return (
    <div className={`bg-white rounded-lg shadow-md ${className}`} {...props}>
      {(title || subtitle) && (
        <div className={`border-b border-gray-200 ${padding ? 'px-6 py-4' : ''}`}>
          {title && <h3 className="text-lg font-semibold text-gray-900">{title}</h3>}
          {subtitle && <p className="text-sm text-gray-600 mt-1">{subtitle}</p>}
        </div>
      )}
      <div className={padding ? 'p-6' : ''}>
        {children}
      </div>
    </div>
  );
}

Card.propTypes = {
  children: PropTypes.node.isRequired,
  title: PropTypes.string,
  subtitle: PropTypes.string,
  className: PropTypes.string,
  padding: PropTypes.bool,
};

export default Card;
