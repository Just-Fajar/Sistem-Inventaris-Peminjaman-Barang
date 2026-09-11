import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ThemeProvider } from '../../contexts/ThemeContext';
import Header from '../../components/Header';
import Input from '../../components/common/Input';
import Select from '../../components/common/Select';
import CategoryList from '../../pages/CategoryList';
import ItemList from '../../pages/ItemList';
import UserList from '../../pages/UserList';
import { authService } from '../../services/authService';
import { categoryService } from '../../services/categoryService';
import { itemService } from '../../services/itemService';
import { userService } from '../../services/userService';

vi.mock('../../services/authService', () => ({
  authService: {
    isAdmin: vi.fn(),
    getCurrentUser: vi.fn(),
    logout: vi.fn(),
  },
}));

vi.mock('../../services/itemService', () => ({
  itemService: {
    getAll: vi.fn(),
    getById: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('../../services/categoryService', () => ({
  categoryService: {
    getAll: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('../../services/userService', () => ({
  userService: {
    getAll: vi.fn(),
    delete: vi.fn(),
  },
}));

describe('Accessibility (A11y) & SEO Elements', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    authService.getCurrentUser.mockReturnValue({
      id: 1,
      name: 'Admin User',
      email: 'admin@example.com',
      role: 'admin',
    });
    authService.isAdmin.mockReturnValue(true);
    itemService.getAll.mockResolvedValue({
      data: [],
      last_page: 1,
    });
    categoryService.getAll.mockResolvedValue({
      data: [
        {
          id: 1,
          name: 'Elektronik',
          description: 'Peralatan elektronik',
          items_count: 5,
        },
      ],
    });
    userService.getAll.mockResolvedValue({
      data: [],
      last_page: 1,
    });
  });

  describe('Header Component', () => {
    it('renders notification button with accessible aria-label', () => {
      render(
        <ThemeProvider>
          <MemoryRouter>
            <Header onToggleSidebar={vi.fn()} />
          </MemoryRouter>
        </ThemeProvider>
      );

      const notifButton = screen.getByRole('button', { name: 'Notifikasi' });
      expect(notifButton).toBeInTheDocument();
    });

    it('renders user menu button with accessible aria-label', () => {
      render(
        <ThemeProvider>
          <MemoryRouter>
            <Header onToggleSidebar={vi.fn()} />
          </MemoryRouter>
        </ThemeProvider>
      );

      const userMenuButton = screen.getByRole('button', { name: 'Menu profil pengguna' });
      expect(userMenuButton).toBeInTheDocument();
    });
  });

  describe('ItemList Component', () => {
    it('renders search input and filter selects with accessible aria-labels', async () => {
      render(
        <MemoryRouter>
          <ItemList />
        </MemoryRouter>
      );

      await waitFor(() => {
        expect(screen.getByRole('textbox', { name: 'Cari barang berdasarkan nama atau kode' })).toBeInTheDocument();
      });

      expect(screen.getByRole('combobox', { name: 'Filter Kategori' })).toBeInTheDocument();
      expect(screen.getByRole('combobox', { name: 'Filter Kondisi' })).toBeInTheDocument();
    });
  });

  describe('CategoryList Component', () => {
    it('renders search input and action buttons with accessible aria-labels', async () => {
      render(
        <MemoryRouter>
          <CategoryList />
        </MemoryRouter>
      );

      await waitFor(() => {
        expect(screen.getByRole('textbox', { name: 'Cari kategori' })).toBeInTheDocument();
      });

      expect(screen.getByRole('button', { name: 'Edit kategori' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Hapus kategori' })).toBeInTheDocument();
    });
  });

  describe('UserList Component', () => {
    it('renders search input and role filter select with accessible aria-labels', async () => {
      render(
        <MemoryRouter>
          <UserList />
        </MemoryRouter>
      );

      await waitFor(() => {
        expect(screen.getByRole('textbox', { name: 'Cari nama atau email' })).toBeInTheDocument();
      });

      expect(screen.getByRole('combobox', { name: 'Filter Role' })).toBeInTheDocument();
    });
  });

  describe('Reusable Common Form Components', () => {
    it('associates label and input with id/htmlFor and aria-label', () => {
      render(
        <Input
          id="test-input"
          label="Nama Barang"
          placeholder="Masukkan nama..."
        />
      );

      const input = screen.getByRole('textbox', { name: 'Nama Barang' });
      expect(input).toBeInTheDocument();
      expect(input).toHaveAttribute('id', 'test-input');
    });

    it('associates label and select with id/htmlFor and aria-label', () => {
      render(
        <Select
          id="test-select"
          label="Pilih Kategori"
          options={[{ value: '1', label: 'Elektronik' }]}
        />
      );

      const select = screen.getByRole('combobox', { name: 'Pilih Kategori' });
      expect(select).toBeInTheDocument();
      expect(select).toHaveAttribute('id', 'test-select');
    });
  });
});
