import api from './api';

export const itemService = {
  // Get all items
  getAll: async (params = {}) => {
    const response = await api.get('/items', { params });
    return response.data;
  },

  // Get single item
  getById: async (id) => {
    const response = await api.get(`/items/${id}`);
    return response.data;
  },

  // Create item
  create: async (data) => {
    const response = await api.post('/items', data, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return response.data;
  },

  // Update item
  update: async (id, data) => {
    const response = await api.post(`/items/${id}`, data, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return response.data;
  },

  // Delete item
  delete: async (id) => {
    const response = await api.delete(`/items/${id}`);
    return response.data;
  },
};
