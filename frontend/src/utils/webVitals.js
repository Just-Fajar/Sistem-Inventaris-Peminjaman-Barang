import { onCLS, onFID, onFCP, onLCP, onTTFB, onINP } from 'web-vitals';

/**
 * Report Web Vitals to analytics
 * Can be integrated with Google Analytics, custom backend, etc.
 */
function sendToAnalytics({ name, value, id, rating }) {
  // In production, send to your analytics service
  if (import.meta.env.PROD) {
    // Example: Google Analytics
    if (window.gtag) {
      window.gtag('event', name, {
        event_category: 'Web Vitals',
        event_label: id,
        value: Math.round(name === 'CLS' ? value * 1000 : value),
        metric_rating: rating,
        non_interaction: true,
      });
    }
    
    // Example: Send to custom backend
    /*
    fetch('/api/analytics/web-vitals', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name, value, id, rating }),
    });
    */
  } else {
    // In development, log to console
    console.log('Web Vital:', {
      name,
      value: Math.round(name === 'CLS' ? value * 1000 : value),
      rating,
      id,
    });
  }
}

/**
 * Initialize Web Vitals monitoring
 * Tracks Core Web Vitals metrics:
 * - CLS (Cumulative Layout Shift)
 * - FID (First Input Delay) / INP (Interaction to Next Paint)
 * - FCP (First Contentful Paint)
 * - LCP (Largest Contentful Paint)
 * - TTFB (Time to First Byte)
 */
export function initWebVitals() {
  // Core Web Vitals
  onCLS(sendToAnalytics); // Layout stability
  onINP(sendToAnalytics); // Responsiveness (replaces FID in Chrome)
  onFID(sendToAnalytics); // Legacy responsiveness metric
  onLCP(sendToAnalytics); // Loading performance
  
  // Other important metrics
  onFCP(sendToAnalytics); // First paint
  onTTFB(sendToAnalytics); // Server response time
}

/**
 * Performance marks for custom measurements
 */
export const performanceMark = {
  start: (markName) => {
    if (performance && performance.mark) {
      performance.mark(`${markName}-start`);
    }
  },
  
  end: (markName) => {
    if (performance && performance.mark && performance.measure) {
      performance.mark(`${markName}-end`);
      try {
        const measure = performance.measure(
          markName,
          `${markName}-start`,
          `${markName}-end`
        );
        
        if (import.meta.env.DEV) {
          console.log(`Performance: ${markName}`, Math.round(measure.duration), 'ms');
        }
        
        return measure.duration;
      } catch (e) {
        // Marks don't exist, ignore
      }
    }
  },
};

/**
 * Log page load performance
 */
export function logPageLoadPerformance() {
  if (performance && performance.getEntriesByType) {
    window.addEventListener('load', () => {
      setTimeout(() => {
        const navigation = performance.getEntriesByType('navigation')[0];
        
        if (navigation) {
          const metrics = {
            'DNS Lookup': Math.round(navigation.domainLookupEnd - navigation.domainLookupStart),
            'TCP Connection': Math.round(navigation.connectEnd - navigation.connectStart),
            'Request Time': Math.round(navigation.responseStart - navigation.requestStart),
            'Response Time': Math.round(navigation.responseEnd - navigation.responseStart),
            'DOM Processing': Math.round(navigation.domComplete - navigation.domInteractive),
            'Total Load Time': Math.round(navigation.loadEventEnd - navigation.fetchStart),
          };
          
          if (import.meta.env.DEV) {
            console.table(metrics);
          }
          
          // Send to analytics in production
          if (import.meta.env.PROD) {
            // TODO: Send to backend
          }
        }
      }, 0);
    });
  }
}
