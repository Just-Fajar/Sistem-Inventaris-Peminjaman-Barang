import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import CategoryList from '../../pages/CategoryList';
import ItemDetail from '../../pages/ItemDetail';
import ItemList from '../../pages/ItemList';
import { authService } from '../../services/authService';
import { categoryService } from '../../services/categoryService';
import { itemService } from '../../services/itemService';

vi.mock('../../services/authService', () => ({
  authService: {
    isAdmin: vi.fn(),
    getCurrentUser: vi.fn(),
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

const mockItemsResponse = {
  data: [
    {
      id: 1,
      code: 'ITM-001',
      name: 'Laptop Dell Latitude',
      description: 'Laptop kantor',
      category: { id: 1, name: 'Elektronik' },
      stock: 10,
      available_stock: 8,
      condition: 'baik',
    },
  ],
  last_page: 1,
};

const mockItemDetailResponse = {
  data: {
    id: 1,
    code: 'ITM-001',
    name: 'Laptop Dell Latitude',
    description: 'Laptop kantor',
    category: { id: 1, name: 'Elektronik' },
    stock: 10,
    available_stock: 8,
    condition: 'baik',
    created_at: '2026-01-01T00:00:00.000000Z',
    updated_at: '2026-01-01T00:00:00.000000Z',
    active_borrowings: [],
  },
};

const mockCategoriesResponse = {
  data: [
    {
      id: 1,
      name: 'Elektronik',
      description: 'Peralatan elektronik',
      items_count: 5,
    },
  ],
};

describe('Role-Based Access Control (RBAC) UI Visibility', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    itemService.getAll.mockResolvedValue(mockItemsResponse);
    itemService.getById.mockResolvedValue(mockItemDetailResponse);
    categoryService.getAll.mockResolvedValue(mockCategoriesResponse);
  });

  describe('ItemList Component', () => {
    it('renders Tambah Barang, Edit, and Hapus buttons for Admin', async () => {
      authService.isAdmin.mockReturnValue(true);

      render(
        <MemoryRouter>
          <ItemList />
        </MemoryRouter>
      );

      await waitFor(() => {
        expect(screen.getByText('Laptop Dell Latitude')).toBeInTheDocument();
      });

      expect(screen.getByRole('link', { name: /Tambah Barang/i })).toBeInTheDocument();
      expect(screen.getByRole('link', { name: 'Edit' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Hapus' })).toBeInTheDocument();
      expect(screen.getByRole('link', { name: 'Detail' })).toBeInTheDocument();
    });

    it('hides Tambah Barang, Edit, and Hapus buttons for Staff, only showing Detail', async () => {
      authService.isAdmin.mockReturnValue(false);

      render(
        <MemoryRouter>
          <ItemList />
        </MemoryRouter>
      );

      await waitFor(() => {
        expect(screen.getByText('Laptop Dell Latitude')).toBeInTheDocument();
      });

      expect(screen.queryByRole('link', { name: /Tambah Barang/i })).not.toBeInTheDocument();
      expect(screen.queryByRole('link', { name: 'Edit' })).not.toBeInTheDocument();
      expect(screen.queryByRole('button', { name: 'Hapus' })).not.toBeInTheDocument();
      expect(screen.getByRole('link', { name: 'Detail' })).toBeInTheDocument();
    });
  });

  describe('CategoryList Component', () => {
    it('renders Tambah Kategori and Aksi column for Admin', async () => {
      authService.isAdmin.mockReturnValue(true);

      render(
        <MemoryRouter>
          <CategoryList />
        </MemoryRouter>
      );

      await waitFor(() => {
        expect(screen.getByText('Elektronik')).toBeInTheDocument();
      });

      expect(screen.getByRole('button', { name: /Tambah Kategori/i })).toBeInTheDocument();
      expect(screen.getByRole('columnheader', { name: 'Aksi' })).toBeInTheDocument();
      expect(screen.getByTitle('Edit')).toBeInTheDocument();
      expect(screen.getByTitle('Hapus')).toBeInTheDocument();
    });

    it('hides Tambah Kategori and Aksi column for Staff', async () => {
      authService.isAdmin.mockReturnValue(false);

      render(
        <MemoryRouter>
          <CategoryList />
        </MemoryRouter>
      );

      await waitFor(() => {
        expect(screen.getByText('Elektronik')).toBeInTheDocument();
      });

      expect(screen.queryByRole('button', { name: /Tambah Kategori/i })).not.toBeInTheDocument();
      expect(screen.queryByRole('columnheader', { name: 'Aksi' })).not.toBeInTheDocument();
      expect(screen.queryByTitle('Edit')).not.toBeInTheDocument();
      expect(screen.queryByTitle('Hapus')).not.toBeInTheDocument();
    });
  });

  describe('ItemDetail Component', () => {
    it('renders Edit button for Admin', async () => {
      authService.isAdmin.mockReturnValue(true);

      render(
        <MemoryRouter initialEntries={['/items/1']}>
          <Routes>
            <Route path="/items/:id" element={<ItemDetail />} />
          </Routes>
        </MemoryRouter>
      );

      await waitFor(() => {
        expect(screen.getByRole('heading', { level: 1, name: 'Laptop Dell Latitude' })).toBeInTheDocument();
      });

      expect(screen.getByRole('link', { name: /Edit/i })).toBeInTheDocument();
    });

    it('hides Edit button for Staff', async () => {
      authService.isAdmin.mockReturnValue(false);

      render(
        <MemoryRouter initialEntries={['/items/1']}>
          <Routes>
            <Route path="/items/:id" element={<ItemDetail />} />
          </Routes>
        </MemoryRouter>
      );

      await waitFor(() => {
        expect(screen.getByRole('heading', { level: 1, name: 'Laptop Dell Latitude' })).toBeInTheDocument();
      });

      expect(screen.queryByRole('link', { name: /Edit/i })).not.toBeInTheDocument();
    });
  });
});
