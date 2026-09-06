import { useEffect, useRef } from 'react';

/**
 * Custom hook for infinite scrolling using IntersectionObserver.
 * 
 * @param {Object} options
 * @param {Function} options.onLoadMore - Function to invoke when sentinel enters viewport
 * @param {boolean} [options.hasMore=false] - Whether more items/pages are available to load
 * @param {boolean} [options.loading=false] - Loading state flag to prevent concurrent requests
 * @param {string} [options.rootMargin='200px'] - Pre-load margin before reaching the bottom
 * @param {number|number[]} [options.threshold=0] - Visibility threshold (0 to 1)
 * @returns {{ sentinelRef: React.RefObject<HTMLElement> }} Ref to attach to the sentinel DOM node
 */
export function useInfiniteScroll({
  onLoadMore,
  hasMore = false,
  loading = false,
  rootMargin = '200px',
  threshold = 0,
}) {
  const sentinelRef = useRef(null);
  const onLoadMoreRef = useRef(onLoadMore);

  // Keep callback reference updated without retriggering effect
  useEffect(() => {
    onLoadMoreRef.current = onLoadMore;
  }, [onLoadMore]);

  useEffect(() => {
    const sentinel = sentinelRef.current;
    if (!sentinel) return;
    if (typeof IntersectionObserver === 'undefined') return;

    // Do not attach observer if no more items or currently loading
    if (!hasMore || loading) return;

    const observer = new IntersectionObserver(
      (entries) => {
        const [entry] = entries;
        if (entry && entry.isIntersecting) {
          onLoadMoreRef.current?.();
        }
      },
      { rootMargin, threshold }
    );

    observer.observe(sentinel);

    return () => {
      observer.disconnect();
    };
  }, [hasMore, loading, rootMargin, threshold]);

  return { sentinelRef };
}

export default useInfiniteScroll;
