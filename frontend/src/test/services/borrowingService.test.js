import { describe, expect, it, vi } from 'vitest';
import api from '../../services/api';
import { borrowingService } from '../../services/borrowingService';

vi.mock('../../services/api', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}));

describe('borrowingService', () => {
  it('cleans empty string, null, and undefined query params in getAll', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [] } });

    await borrowingService.getAll({
      status: '',
      search: '',
      start_date: null,
      end_date: undefined,
      page: 1,
      limit: 10,
    });

    expect(api.get).toHaveBeenCalledWith('/borrowings', {
      params: {
        page: 1,
        limit: 10,
      },
    });
  });

  it('keeps valid filter params in getAll', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [] } });

    await borrowingService.getAll({
      status: 'dipinjam',
      search: 'laptop',
      start_date: '2026-09-01',
    });

    expect(api.get).toHaveBeenCalledWith('/borrowings', {
      params: {
        status: 'dipinjam',
        search: 'laptop',
        start_date: '2026-09-01',
      },
    });
  });
});
