import axios from 'axios';

const api = axios.create({
  baseURL: '/api',
  headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
});

// Client dedicato per auth/refresh per evitare loop negli interceptor
const authApi = axios.create({
  baseURL: '/api',
  headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
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

    // Feature gated durante il trial → emette evento globale e
    // non rilancia l'errore (lo gestisce il modal lato UI).
    if (
      error.response?.status === 403 &&
      error.response?.data?.error?.code === 'FEATURE_DISABLED_DURING_TRIAL'
    ) {
      const feature = error.response.data.error.feature ?? null;
      if (typeof window !== 'undefined') {
        window.dispatchEvent(
          new CustomEvent('kalendarium:feature-gated', { detail: { feature } })
        );
      }
      return Promise.resolve({ data: { _featureGated: true, feature } });
    }

    // Subscription inattiva → redirect alla pagina dedicata.
    if (
      error.response?.status === 402 &&
      error.response?.data?.error?.code === 'SUBSCRIPTION_INACTIVE' &&
      typeof window !== 'undefined' &&
      !window.location.pathname.startsWith('/subscription/inactive')
    ) {
      window.location.href = '/subscription/inactive';
      return Promise.reject(error);
    }

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
  completeness: (id) => api.get(`/brands/${id}/completeness`),
  mcpServers: {
    list:   (brandId)              => api.get(`/brands/${brandId}/mcp-servers`),
    create: (brandId, data)        => api.post(`/brands/${brandId}/mcp-servers`, data),
    delete: (brandId, mcpId)       => api.delete(`/brands/${brandId}/mcp-servers/${mcpId}`),
  },
};

export const deontological = {
  options: () => api.get('/deontological-options'),
};

export const campaigns = {
  list: () => api.get('/campaigns'),
  get: (id) => api.get(`/campaigns/${id}`),
  create: (data) => api.post('/campaigns', data),
  update: (id, data) => api.put(`/campaigns/${id}`, data),
  delete: (id) => api.delete(`/campaigns/${id}`),
  attachments: {
    list:   (campaignId)              => api.get(`/campaigns/${campaignId}/attachments`),
    upload: (campaignId, formData)    => api.post(`/campaigns/${campaignId}/attachments`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),
    delete: (campaignId, attachmentId) => api.delete(`/campaigns/${campaignId}/attachments/${attachmentId}`),
  },
  mcpServers: {
    list:   (campaignId)         => api.get(`/campaigns/${campaignId}/mcp-servers`),
    create: (campaignId, data)   => api.post(`/campaigns/${campaignId}/mcp-servers`, data),
    delete: (campaignId, mcpId)  => api.delete(`/campaigns/${campaignId}/mcp-servers/${mcpId}`),
  },
};

export const projects = {
  list: (brandId) => api.get('/projects/', { params: { brand_id: brandId } }),
  get: (id) => api.get(`/projects/${id}`),
  create: (data) => api.post('/projects/', data),
  update: (id, data) => api.put(`/projects/${id}`, data),
  patch: (id, data) => api.patch(`/projects/${id}`, data),
  delete: (id) => api.delete(`/projects/${id}`),
  // Editions
  addEdition: (id, data) => api.post(`/projects/${id}/editions`, data),
  historyContext: (id) => api.get(`/projects/${id}/history-context`),
  // Wizard PR-2 — AI personas evaluation + pillar promote
  evaluatePersonas:        (id)         => api.post(`/projects/${id}/evaluate-personas`),
  personasStatus:          (id)         => api.get(`/projects/${id}/personas-status`),
  forceRegeneratePersonas: (id)         => api.post(`/projects/${id}/force-regenerate-personas`),
  confirmPersonas:         (id)         => api.post(`/projects/${id}/confirm-personas`),
  promotePillarsToBrand:   (id, pillars) => api.post(`/projects/${id}/promote-pillars-to-brand`, { pillars }),
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
  preflight: (projectId) => api.get(`/generate/preflight/${projectId}`),
  status: (projectId) => api.get(`/projects/${projectId}/generation-status`),
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

export const auditApi = {
  // Brand audits (per-brand)
  start:      (brandId, sectorData = null) => api.post(`/brands/${brandId}/audit`, sectorData ?? {}),
  latest:     (brandId) => api.get(`/brands/${brandId}/audit/latest`),
  history:    (brandId) => api.get(`/brands/${brandId}/audits`),
  show:       (auditId) => api.get(`/audits/${auditId}`),

  // Prospect audits (utente autenticato, no brand)
  prospectStart:   (data) => api.post('/audit/prospect', data),
  prospectShow:    (id)   => api.get(`/audit/prospect/${id}`),
  prospectList:    ()     => api.get('/audit/prospects'),
  prospectShare:   (id)   => api.post(`/audit/prospect/${id}/share`),
  prospectRerun:   (id)   => api.post(`/audit/prospect/${id}/rerun`),
  prospectDelete:  (id)   => api.delete(`/audit/prospect/${id}`),

  // Admin — audit cross-org + standalone (solo superadmin)
  adminList:         (params = {}) => api.get('/admin/audits',            { params }),
  standaloneStart:   (data)        => api.post('/admin/audit-url',         data),
  standaloneList:    (params = {}) => api.get('/admin/audits-standalone',  { params }),
  standaloneDownloadPdf: (auditId) => api.get(`/admin/audit-url/${auditId}/pdf`,  { responseType: 'blob' }),

  // PDF per audit brand (utente autenticato)
  downloadPdf: (auditId) => api.get(`/audits/${auditId}/pdf`, { responseType: 'blob' }),

  // Share link
  generateShareLink:      (auditId) => api.post(`/audits/${auditId}/share`),
  generateShareLinkAdmin: (auditId) => api.post(`/admin/audit-url/${auditId}/share`),

  // Rilevamento settore automatico
  detectSector:         (data)    => api.post('/admin/audit-detect-sector',      data),
  detectSectorForBrand: (brandId) => api.post(`/brands/${brandId}/audit-detect-sector`),
};

export const reviewsApi = {
  list:           (params = {}) => api.get('/reviews',           { params }),
  get:            (id)          => api.get(`/reviews/${id}`),
  generateDraft:  (id, data)    => api.post(`/reviews/${id}/draft`, data),
  updateDraft:    (id, replyId, body) => api.patch(`/reviews/${id}/replies/${replyId}`, { body }),
  approveDraft:   (id, replyId) => api.post(`/reviews/${id}/replies/${replyId}/approve`),
  cancelDraft:    (id, replyId) => api.post(`/reviews/${id}/replies/${replyId}/cancel`),
  ignoreReview:   (id)          => api.post(`/reviews/${id}/ignore`),
  getQuota:       ()            => api.get('/reviews/quota'),
};
