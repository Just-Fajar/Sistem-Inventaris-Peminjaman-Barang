import api from './api';

export const borrowingService = {
  // Get all borrowings
  getAll: async (params = {}) => {
    const response = await api.get('/borrowings', { params });
    return response.data;
  },

  // Get single borrowing
  getById: async (id) => {
    const response = await api.get(`/borrowings/${id}`);
    return response.data;
  },

  // Create borrowing
  create: async (data) => {
    const response = await api.post('/borrowings', data);
    return response.data;
  },

  // Update borrowing
  update: async (id, data) => {
    const response = await api.put(`/borrowings/${id}`, data);
    return response.data;
  },

  // Return borrowing
  returnItem: async (id) => {
    const response = await api.post(`/borrowings/${id}/return`);
    return response.data;
  },

  // Approve borrowing
  approve: async (id) => {
    const response = await api.post(`/borrowings/${id}/approve`);
    return response.data;
  },

  // Delete borrowing
  delete: async (id) => {
    const response = await api.delete(`/borrowings/${id}`);
    return response.data;
  },
};
