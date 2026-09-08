import { render, screen } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import { describe, expect, it } from 'vitest';
import NotFound from '../../pages/NotFound';

describe('NotFound Page', () => {
  it('renders 404 header and description text', () => {
    render(
      <BrowserRouter>
        <NotFound />
      </BrowserRouter>
    );

    expect(screen.getByText('404')).toBeInTheDocument();
    expect(screen.getByText('Halaman Tidak Ditemukan')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Kembali ke Dashboard/i })).toHaveAttribute('href', '/dashboard');
  });
});
