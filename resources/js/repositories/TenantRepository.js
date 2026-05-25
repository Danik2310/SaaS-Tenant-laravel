import api from '../services/api';

export const fetchTenants = ({ page = 1, perPage = 25, trashed = false } = {}) => {
  const params = new URLSearchParams();
  if (trashed) params.set('trashed', '1');
  params.set('page', page);
  params.set('per_page', perPage);
  return api.get(`/admin/api/tenants?${params}`);
};

export const fetchTenant = (id) => api.get(`/admin/api/tenants/${id}`);

export const createTenant = (data) => api.post('/admin/api/tenants', data);

export const updateTenant = (id, data) => api.put(`/admin/api/tenants/${id}`, data);

export const deleteTenant = (id) => api.delete(`/admin/api/tenants/${id}`);

export const restoreTenant = (id) => api.patch(`/admin/api/tenants/${id}/restore`);

export const impersonateTenant = (tenantId) => api.post('/admin/api/impersonate', { tenant_id: tenantId });

export const performBulkAction = (tenantIds, action) =>
  api.post('/admin/api/tenants/bulk', { tenant_ids: tenantIds, action });

export const changeTenantPlan = (id, planId) =>
  api.put(`/admin/api/tenants/${id}/plan`, { plan_id: planId });

export const fetchTenantDatabase = (id) => api.get(`/admin/api/tenants/${id}/database`);

export const migrateTenant = (id) => api.post(`/admin/api/tenants/${id}/migrate`);
