import api from './api';

export const dashboardService = {
  // Get dashboard data
  getData: async () => {
    const response = await api.get('/dashboard');
    return response.data;
  },
};
