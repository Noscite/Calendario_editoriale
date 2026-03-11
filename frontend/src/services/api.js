import axios from 'axios';

const api = axios.create({
  baseURL: '/api',
  headers: { 'Content-Type': 'application/json' },
});

// Client dedicato per auth/refresh per evitare loop negli interceptor
const authApi = axios.create({
  baseURL: '/api',
  headers: { 'Content-Type': 'application/json' },
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config;
    
    // Se 401 e non è già un retry e non è la chiamata refresh stessa
    if (error.response?.status === 401 && !originalRequest._retry && !originalRequest.url?.includes('/auth/refresh')) {
      originalRequest._retry = true;
      
      try {
        // Usa authApi per evitare loop
        const refreshResponse = await authApi.post('/auth/refresh', {}, {
          headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
        });
        const newToken = refreshResponse.data.access_token;
        
        localStorage.setItem('token', newToken);
        originalRequest.headers.Authorization = `Bearer ${newToken}`;
        
        return api(originalRequest);
      } catch (refreshError) {
        // Refresh fallito, vai al login
        localStorage.removeItem('token');
        window.location.href = '/login';
        return Promise.reject(refreshError);
      }
    }
    
    return Promise.reject(error);
  }
);

export default api;

export const auth = {
  login: (email, password) => api.post('/auth/login', new URLSearchParams({ username: email, password }), {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
  }),
  register: (data) => api.post('/auth/register', data),
  me: () => api.get('/auth/me'),
  forgotPassword: (email) => api.post('/auth/forgot-password', { email }),
  resetPassword: (data) => api.post('/auth/reset-password', data),
  validateInvitation: (token) => api.get(`/auth/invitation/${token}`),
  acceptInvitation: (token, data) => api.post(`/auth/invitation/${token}/accept`, data),
};

export const brands = {
  list: () => api.get('/brands/'),
  get: (id) => api.get(`/brands/${id}`),
  create: (data) => api.post('/brands/', data),
  update: (id, data) => api.put(`/brands/${id}`, data),
  delete: (id) => api.delete(`/brands/${id}`),
};

export const projects = {
  list: (brandId) => api.get('/projects/', { params: { brand_id: brandId } }),
  get: (id) => api.get(`/projects/${id}`),
  create: (data) => api.post('/projects/', data),
  update: (id, data) => api.put(`/projects/${id}`, data),
  delete: (id) => api.delete(`/projects/${id}`),
  // Editions
  addEdition: (id, data) => api.post(`/projects/${id}/editions`, data),
  historyContext: (id) => api.get(`/projects/${id}/history-context`),
};

export const posts = {
  list: (projectId) => api.get(`/posts/project/${projectId}`),
  listByProject: (projectId) => api.get(`/posts/project/${projectId}`),
  get: (id) => api.get(`/posts/${id}`),
  update: (id, data) => api.patch(`/posts/${id}`, data),
  delete: (id) => api.delete(`/posts/${id}`),
  createManual: (data) => api.post('/posts/manual', data),
  generateAI: (data) => api.post('/posts/generate-ai', data),
  batchDelete: (postIds) => api.post('/posts/batch-delete', { post_ids: postIds }),
  generateImage: (postId, data) => api.post(`/posts/${postId}/generate-image`, data),
  schedule: (postId, data) => api.post(`/posts/${postId}/schedule`, data),
  publish: (postId) => api.post(`/posts/${postId}/publish`),
};

export const generation = {
  generate: (data) => api.post('/generate/', data),
  generatePersonas: (projectId) => api.post(`/generate/personas/${projectId}`),
  confirmPersonas: (projectId, personas) => api.post(`/generate/personas/${projectId}/confirm`, { personas }),
  deletePersona: (projectId, index) => api.delete(`/generate/personas/${projectId}/${index}`),
  regeneratePersona: (projectId, index) => api.post(`/generate/personas/${projectId}/${index}/regenerate`),
  addPersona: (projectId) => api.post(`/generate/personas/${projectId}/add`),
  generateCalendar: (projectId) => api.post(`/generate/calendar/${projectId}`),
};

export const exportApi = {
  excel: (projectId) => api.get(`/export/excel/${projectId}`, { responseType: 'blob' }),
  pdf: (projectId) => api.get(`/export/pdf/${projectId}`, { responseType: 'blob' }),
};

export const social = {
  getConnections: (brandId) => api.get(`/social/connections/${brandId}`),
  disconnect: (connectionId) => api.delete(`/social/disconnect/${connectionId}`),
  getAuthUrl: (brandId, platform) => api.get(`/social/authorize/${platform}?brand_id=${brandId}`),
};

export const documents = {
  list: (brandId) => api.get(`/documents/list/${brandId}`),
  upload: (brandId, formData) => api.post(`/documents/upload/${brandId}`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  }),
  delete: (docId) => api.delete(`/documents/${docId}`),
};

export const subscriptions = {
  getPlans: () => api.get('/subscriptions/plans'),
  getCurrentSubscription: () => api.get('/subscriptions/current'),
  subscribe: (planId) => api.post('/subscriptions/subscribe', { plan_id: planId }),
  cancel: () => api.post('/subscriptions/cancel'),
  getUsage: () => api.get('/subscriptions/usage/my'),
};

export const help = {
  categories: () => api.get('/help/categories'),
  articles: (params) => api.get('/help/articles', { params }),
  article: (slug) => api.get(`/help/articles/${slug}`),
  search: (q) => api.get('/help/search', { params: { q } }),
};

export const support = {
  chat: (message, history, context) =>
    api.post('/support/chat', {
      message,
      conversation_history: history,
      context,
    }),
};

export const invitations = {
  list: () => api.get('/admin/invitations'),
  invite: (data) => api.post('/admin/invitations', data),
  revoke: (id) => api.delete(`/admin/invitations/${id}`),
};
