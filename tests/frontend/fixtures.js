export const mockPlans = [
  { id: 1, name: 'Free', slug: 'free', price: 0, max_users: 5, max_products: 50, max_warehouses: 1, max_categories: 10 },
  { id: 2, name: 'Pro', slug: 'pro', price: 29, max_users: 50, max_products: 500, max_warehouses: 5, max_categories: 50 },
  { id: 3, name: 'Enterprise', slug: 'enterprise', price: 99, max_users: -1, max_products: -1, max_warehouses: -1, max_categories: -1 },
];

export const mockTenant = {
  id: 'tenant-1',
  name: 'Acme Corp',
  plan: { id: 1, name: 'Free', max_users: 5, max_products: 50, max_warehouses: 1, max_categories: 10 },
  plan_name: 'Free',
};

export const mockBulkTenants = [
  { id: 't1', name: 'Alpha', plan_name: 'Free' },
  { id: 't2', name: 'Beta', plan_name: 'Free' },
  { id: 't3', name: 'Gamma', plan_name: 'Pro' },
];

export const mockUser = {
  id: 1,
  name: 'Admin User',
  email: 'admin@example.com',
};

export const mockPermissions = ['manage_tenants', 'manage_users', 'manage_plans'];

export const mockPaymentMethod = {
  id: 1,
  name: 'Test Payment',
  provider: 'stripe',
  api_key: 'sk_test_123',
  secret_key: 'sk_live_456',
  mode: 'live',
  active: false,
};

export const mockPaymentMethods = [
  { id: 1, name: 'Stripe Test', provider: 'stripe', mode: 'test', active: true },
  { id: 2, name: 'PayPal Live', provider: 'paypal', mode: 'live', active: false },
];
