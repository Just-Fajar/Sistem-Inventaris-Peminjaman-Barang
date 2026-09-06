import { act, render, renderHook, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ThemeToggle from '../../components/common/ThemeToggle';
import { ThemeProvider, useTheme } from '../../contexts/ThemeContext';

describe('ThemeContext & ThemeProvider', () => {
  let originalMatchMedia;
  let matchMediaListeners = [];

  beforeEach(() => {
    localStorage.clear();
    document.documentElement.className = '';
    document.documentElement.removeAttribute('data-theme');
    document.documentElement.style.colorScheme = '';

    matchMediaListeners = [];
    originalMatchMedia = window.matchMedia;

    window.matchMedia = vi.fn((query) => ({
      matches: false, // default light in system mock
      media: query,
      onchange: null,
      addListener: vi.fn((listener) => matchMediaListeners.push(listener)),
      removeListener: vi.fn(),
      addEventListener: vi.fn((_, listener) => matchMediaListeners.push(listener)),
      removeEventListener: vi.fn(),
      dispatchEvent: vi.fn(),
    }));
  });

  afterEach(() => {
    window.matchMedia = originalMatchMedia;
    localStorage.clear();
    document.documentElement.className = '';
    vi.clearAllMocks();
  });

  it('throws error when useTheme is used outside ThemeProvider', () => {
    // Suppress console.error for expected thrown error
    const spy = vi.spyOn(console, 'error').mockImplementation(() => {});
    expect(() => renderHook(() => useTheme())).toThrow(
      'useTheme must be used within a ThemeProvider'
    );
    spy.mockRestore();
  });

  it('provides default theme as system and resolves according to matchMedia', () => {
    const { result } = renderHook(() => useTheme(), {
      wrapper: ({ children }) => <ThemeProvider>{children}</ThemeProvider>,
    });

    expect(result.current.theme).toBe('system');
    expect(result.current.resolvedTheme).toBe('light');
    expect(document.documentElement.classList.contains('dark')).toBe(false);
  });

  it('applies dark class and stores in localStorage when theme is set to dark', () => {
    const { result } = renderHook(() => useTheme(), {
      wrapper: ({ children }) => <ThemeProvider>{children}</ThemeProvider>,
    });

    act(() => {
      result.current.setTheme('dark');
    });

    expect(result.current.theme).toBe('dark');
    expect(result.current.resolvedTheme).toBe('dark');
    expect(document.documentElement.classList.contains('dark')).toBe(true);
    expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
    expect(document.documentElement.style.colorScheme).toBe('dark');
    expect(localStorage.getItem('theme')).toBe('dark');
  });

  it('removes dark class when theme is switched from dark to light', () => {
    localStorage.setItem('theme', 'dark');

    const { result } = renderHook(() => useTheme(), {
      wrapper: ({ children }) => <ThemeProvider>{children}</ThemeProvider>,
    });

    expect(result.current.theme).toBe('dark');
    expect(document.documentElement.classList.contains('dark')).toBe(true);

    act(() => {
      result.current.setTheme('light');
    });

    expect(result.current.theme).toBe('light');
    expect(result.current.resolvedTheme).toBe('light');
    expect(document.documentElement.classList.contains('dark')).toBe(false);
    expect(document.documentElement.getAttribute('data-theme')).toBe('light');
    expect(document.documentElement.style.colorScheme).toBe('light');
    expect(localStorage.getItem('theme')).toBe('light');
  });

  it('resolves to dark when theme is system and system prefers dark', () => {
    window.matchMedia = vi.fn((query) => ({
      matches: true,
      media: query,
      addListener: vi.fn(),
      removeListener: vi.fn(),
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
    }));

    const { result } = renderHook(() => useTheme(), {
      wrapper: ({ children }) => <ThemeProvider>{children}</ThemeProvider>,
    });

    expect(result.current.theme).toBe('system');
    expect(result.current.resolvedTheme).toBe('dark');
    expect(document.documentElement.classList.contains('dark')).toBe(true);
  });

  it('allows ThemeToggle to switch themes on button clicks', async () => {
    const user = userEvent.setup();

    function TestApp() {
      const { theme } = useTheme();
      return (
        <div>
          <ThemeToggle />
          <span data-testid="current-theme">{theme}</span>
        </div>
      );
    }

    render(
      <ThemeProvider>
        <TestApp />
      </ThemeProvider>
    );

    const lightBtn = screen.getByRole('button', { name: /light mode/i });
    const darkBtn = screen.getByRole('button', { name: /dark mode/i });
    const systemBtn = screen.getByRole('button', { name: /system theme/i });

    // Switch to dark
    await user.click(darkBtn);
    expect(screen.getByTestId('current-theme')).toHaveTextContent('dark');
    expect(document.documentElement.classList.contains('dark')).toBe(true);

    // Switch to light
    await user.click(lightBtn);
    expect(screen.getByTestId('current-theme')).toHaveTextContent('light');
    expect(document.documentElement.classList.contains('dark')).toBe(false);

    // Switch to system
    await user.click(systemBtn);
    expect(screen.getByTestId('current-theme')).toHaveTextContent('system');
  });
});
