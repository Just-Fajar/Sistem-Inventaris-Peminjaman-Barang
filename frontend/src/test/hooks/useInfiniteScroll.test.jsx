import { act, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import useInfiniteScroll from '../../hooks/useInfiniteScroll';

// Helper component to test useInfiniteScroll
function TestInfiniteScrollComponent({
  onLoadMore,
  hasMore = true,
  loading = false,
  rootMargin = '200px',
  threshold = 0,
}) {
  const { sentinelRef } = useInfiniteScroll({
    onLoadMore,
    hasMore,
    loading,
    rootMargin,
    threshold,
  });

  return (
    <div>
      <div data-testid="content">Content List</div>
      <div data-testid="sentinel" ref={sentinelRef}>
        Loading more...
      </div>
    </div>
  );
}

describe('useInfiniteScroll hook', () => {
  let observerInstances = [];
  let originalIntersectionObserver;

  beforeEach(() => {
    observerInstances = [];
    originalIntersectionObserver = window.IntersectionObserver;

    // Mock IntersectionObserver
    window.IntersectionObserver = vi.fn(function (callback, options) {
      this.callback = callback;
      this.options = options;
      this.observedElements = [];
      this.observe = vi.fn((element) => {
        this.observedElements.push(element);
      });
      this.unobserve = vi.fn((element) => {
        this.observedElements = this.observedElements.filter((el) => el !== element);
      });
      this.disconnect = vi.fn(() => {
        this.observedElements = [];
      });

      // Helper to trigger intersection in tests
      this.triggerIntersect = (isIntersecting = true) => {
        this.callback([{ isIntersecting, target: this.observedElements[0] }]);
      };

      observerInstances.push(this);
    });
  });

  afterEach(() => {
    window.IntersectionObserver = originalIntersectionObserver;
    vi.clearAllMocks();
  });

  it('observes the sentinel element when hasMore is true and not loading', () => {
    const onLoadMore = vi.fn();
    render(<TestInfiniteScrollComponent onLoadMore={onLoadMore} hasMore={true} loading={false} />);

    expect(window.IntersectionObserver).toHaveBeenCalledTimes(1);
    expect(observerInstances[0].observe).toHaveBeenCalledWith(screen.getByTestId('sentinel'));
  });

  it('triggers onLoadMore when sentinel intersects the viewport', () => {
    const onLoadMore = vi.fn();
    render(<TestInfiniteScrollComponent onLoadMore={onLoadMore} hasMore={true} loading={false} />);

    expect(onLoadMore).not.toHaveBeenCalled();

    act(() => {
      observerInstances[0].triggerIntersect(true);
    });

    expect(onLoadMore).toHaveBeenCalledTimes(1);
  });

  it('does not trigger onLoadMore when entry is not intersecting', () => {
    const onLoadMore = vi.fn();
    render(<TestInfiniteScrollComponent onLoadMore={onLoadMore} hasMore={true} loading={false} />);

    act(() => {
      observerInstances[0].triggerIntersect(false);
    });

    expect(onLoadMore).not.toHaveBeenCalled();
  });

  it('does not observe or trigger onLoadMore when loading is true', () => {
    const onLoadMore = vi.fn();
    render(<TestInfiniteScrollComponent onLoadMore={onLoadMore} hasMore={true} loading={true} />);

    expect(observerInstances.length).toBe(0);
    expect(onLoadMore).not.toHaveBeenCalled();
  });

  it('does not observe or trigger onLoadMore when hasMore is false', () => {
    const onLoadMore = vi.fn();
    render(<TestInfiniteScrollComponent onLoadMore={onLoadMore} hasMore={false} loading={false} />);

    expect(observerInstances.length).toBe(0);
    expect(onLoadMore).not.toHaveBeenCalled();
  });

  it('disconnects the observer when component unmounts', () => {
    const onLoadMore = vi.fn();
    const { unmount } = render(
      <TestInfiniteScrollComponent onLoadMore={onLoadMore} hasMore={true} loading={false} />
    );

    const observer = observerInstances[0];
    expect(observer.disconnect).not.toHaveBeenCalled();

    unmount();

    expect(observer.disconnect).toHaveBeenCalledTimes(1);
  });

  it('handles gracefully when IntersectionObserver is not supported', () => {
    delete window.IntersectionObserver;
    const onLoadMore = vi.fn();

    expect(() => {
      render(<TestInfiniteScrollComponent onLoadMore={onLoadMore} hasMore={true} loading={false} />);
    }).not.toThrow();
  });
});
