import * as Sentry from '@sentry/react';

/**
 * Initialize Sentry for error tracking
 * Only enabled in production
 */
export function initSentry() {
  if (import.meta.env.PROD && import.meta.env.VITE_SENTRY_DSN) {
    Sentry.init({
      dsn: import.meta.env.VITE_SENTRY_DSN,
      integrations: [
        Sentry.browserTracingIntegration(),
        Sentry.replayIntegration({
          maskAllText: true,
          blockAllMedia: true,
        }),
      ],
      // Performance Monitoring
      tracesSampleRate: 1.0, // Capture 100% of transactions for performance monitoring
      // Session Replay
      replaysSessionSampleRate: 0.1, // 10% of sessions
      replaysOnErrorSampleRate: 1.0, // 100% of sessions with errors
      
      // Environment
      environment: import.meta.env.MODE,
      
      // Release tracking
      release: import.meta.env.VITE_APP_VERSION || 'unknown',
      
      // Before send hook - filter sensitive data
      beforeSend(event, hint) {
        // Don't send events in development
        if (import.meta.env.DEV) {
          return null;
        }
        
        // Remove sensitive data
        if (event.request) {
          delete event.request.cookies;
          delete event.request.headers?.Authorization;
        }
        
        return event;
      },
    });
  }
}

/**
 * Manually capture exception
 * @param {Error} error - The error to capture
 * @param {Object} context - Additional context
 */
export function captureException(error, context = {}) {
  if (import.meta.env.PROD) {
    Sentry.captureException(error, {
      extra: context,
    });
  } else {
    console.error('Sentry (dev mode):', error, context);
  }
}

/**
 * Capture a message
 * @param {string} message - The message to capture
 * @param {string} level - Severity level (info, warning, error)
 */
export function captureMessage(message, level = 'info') {
  if (import.meta.env.PROD) {
    Sentry.captureMessage(message, level);
  } else {
    console.log(`Sentry (dev mode) [${level}]:`, message);
  }
}

/**
 * Set user context
 * @param {Object} user - User information
 */
export function setUser(user) {
  if (import.meta.env.PROD) {
    Sentry.setUser({
      id: user.id,
      email: user.email,
      username: user.name,
    });
  }
}

/**
 * Clear user context (on logout)
 */
export function clearUser() {
  if (import.meta.env.PROD) {
    Sentry.setUser(null);
  }
}

export default Sentry;
