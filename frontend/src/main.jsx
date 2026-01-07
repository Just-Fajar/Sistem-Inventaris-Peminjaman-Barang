import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import App from './App.jsx'
import { AuthProvider, NotificationProvider } from './contexts'
import './i18n'; // Import i18n configuration
import './index.css'
import { initSentry } from './utils/errorTracking'
import { initWebVitals, logPageLoadPerformance } from './utils/webVitals'

// Initialize error tracking
initSentry();

// Initialize performance monitoring
if (import.meta.env.PROD) {
  initWebVitals();
  logPageLoadPerformance();
}

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <AuthProvider>
      <NotificationProvider>
        <App />
      </NotificationProvider>
    </AuthProvider>
  </StrictMode>,
)
