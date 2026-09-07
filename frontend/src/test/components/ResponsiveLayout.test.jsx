import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Layout from '../../components/Layout';
import { ThemeProvider } from '../../contexts/ThemeContext';
import { authService } from '../../services/authService';

vi.mock('../../services/authService', () => ({
  authService: {
    getCurrentUser: vi.fn(),
    isAdmin: vi.fn(),
    logout: vi.fn(),
  },
}));

describe('Responsive Layout & Mobile Navigation Drawer', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    authService.getCurrentUser.mockReturnValue({
      name: 'Budi Santoso',
      role: 'admin',
    });
    authService.isAdmin.mockReturnValue(true);
  });

  const renderLayout = () => {
    return render(
      <ThemeProvider>
        <MemoryRouter initialEntries={['/dashboard']}>
          <Layout />
        </MemoryRouter>
      </ThemeProvider>
    );
  };

  it('renders hamburger button in header and sidebar with closed state initially on mobile', () => {
    renderLayout();

    const hamburgerBtn = screen.getByTestId('hamburger-button');
    expect(hamburgerBtn).toBeInTheDocument();
    expect(hamburgerBtn).toHaveClass('md:hidden');

    const sidebar = screen.getByTestId('sidebar');
    expect(sidebar).toBeInTheDocument();
    expect(sidebar).toHaveClass('-translate-x-full');
    expect(sidebar).toHaveClass('md:translate-x-0');

    // Backdrop should not be present initially
    expect(screen.queryByTestId('sidebar-backdrop')).not.toBeInTheDocument();
  });

  it('opens sidebar drawer and displays backdrop overlay when hamburger button is clicked', async () => {
    const user = userEvent.setup();
    renderLayout();

    const hamburgerBtn = screen.getByTestId('hamburger-button');
    await user.click(hamburgerBtn);

    const sidebar = screen.getByTestId('sidebar');
    expect(sidebar).toHaveClass('translate-x-0');

    const backdrop = screen.getByTestId('sidebar-backdrop');
    expect(backdrop).toBeInTheDocument();
  });

  it('closes mobile drawer when clicking the close button inside sidebar', async () => {
    const user = userEvent.setup();
    renderLayout();

    const hamburgerBtn = screen.getByTestId('hamburger-button');
    await user.click(hamburgerBtn);

    const closeBtn = screen.getByTestId('sidebar-close-button');
    expect(closeBtn).toBeInTheDocument();

    await user.click(closeBtn);

    const sidebar = screen.getByTestId('sidebar');
    expect(sidebar).toHaveClass('-translate-x-full');
    expect(screen.queryByTestId('sidebar-backdrop')).not.toBeInTheDocument();
  });

  it('closes mobile drawer when clicking the backdrop overlay', async () => {
    const user = userEvent.setup();
    renderLayout();

    // Open sidebar
    const hamburgerBtn = screen.getByTestId('hamburger-button');
    await user.click(hamburgerBtn);

    const backdrop = screen.getByTestId('sidebar-backdrop');
    expect(backdrop).toBeInTheDocument();

    // Click backdrop to dismiss
    await user.click(backdrop);

    const sidebar = screen.getByTestId('sidebar');
    expect(sidebar).toHaveClass('-translate-x-full');
    expect(screen.queryByTestId('sidebar-backdrop')).not.toBeInTheDocument();
  });

  it('automatically closes mobile drawer when a navigation link is clicked', async () => {
    const user = userEvent.setup();
    renderLayout();

    // Open sidebar
    const hamburgerBtn = screen.getByTestId('hamburger-button');
    await user.click(hamburgerBtn);

    const sidebar = screen.getByTestId('sidebar');
    expect(sidebar).toHaveClass('translate-x-0');

    // Click a navigation item, e.g., 'Kategori'
    const categoryLink = screen.getByRole('link', { name: /kategori/i });
    await user.click(categoryLink);

    // Sidebar should close
    expect(sidebar).toHaveClass('-translate-x-full');
    expect(screen.queryByTestId('sidebar-backdrop')).not.toBeInTheDocument();
  });
});
