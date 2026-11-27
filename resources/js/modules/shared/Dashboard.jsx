import { useState, useEffect, useCallback, Suspense, lazy } from 'react';
import api from '@/services/api';
import { deleteTenant } from '@/repositories/TenantRepository';
import Navbar from '@/Components/Navbar';
import DataTable from '@/Components/DataTable';
import BlockIcon from '@mui/icons-material/Block';

import StorageIcon from '@mui/icons-material/Storage';
import SyncIcon from '@mui/icons-material/Sync';
import ChangeCircleIcon from '@mui/icons-material/ChangeCircle';
import VisibilityIcon from '@mui/icons-material/Visibility';
import ReceiptIcon from '@mui/icons-material/Receipt';
import RestoreIcon from '@mui/icons-material/Restore';
import DeleteForeverIcon from '@mui/icons-material/DeleteForever';
import { toast } from 'sonner';
import { alpha, useTheme } from '@mui/material/styles';
import Alert from '@mui/material/Alert';
import Avatar from '@mui/material/Avatar';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Chip from '@mui/material/Chip';
import Typography from '@mui/material/Typography';
import LogoutIcon from '@mui/icons-material/Logout';
import FormControlLabel from '@mui/material/FormControlLabel';
import Switch from '@mui/material/Switch';
import CircularProgress from '@mui/material/CircularProgress';
import ErrorBoundary from '@/Components/ErrorBoundary';
import { useAuthContext } from '@/context/AuthContext';
import ConfirmDialog from '@/Components/ConfirmDialog';

const DashboardOverview = lazy(() => import('./DashboardOverview'));
const TenantList = lazy(() => import('@/modules/tenants/TenantList'));
const TenantForm = lazy(() => import('@/modules/tenants/TenantForm'));
const Staff = lazy(() => import('@/modules/shared/staff/StaffList'));
const Plans = lazy(() => import('@/modules/billing/Plans'));
const RolePermissions = lazy(() => import('@/modules/shared/staff/RolePermissions'));
const Profile = lazy(() => import('@/modules/shared/profile/Profile'));
const Subscriptions = lazy(() => import('@/modules/billing/Subscriptions'));
const PaymentMethods = lazy(() => import('@/modules/billing/components/PaymentMethodsTab'));
const ActivityLog = lazy(() => import('@/modules/shared/activity/ActivityLog'));
const Settings = lazy(() => import('@/modules/shared/settings/Settings'));
const ResourceUsage = lazy(() => import('@/modules/billing/ResourceUsage'));
const DatabaseModal = lazy(() => import('@/modules/shared/modals/DatabaseModal'));
const MigrationModal = lazy(() => import('@/modules/shared/modals/MigrationModal'));
const DomainModal = lazy(() => import('@/modules/shared/modals/DomainModal'));
const ChangePlanModal = lazy(() => import('@/modules/billing/modals/ChangePlanModal'));
const ActivateTrialModal = lazy(() => import('@/modules/billing/modals/ActivateTrialModal'));

const VIEW_TITLES = {
    overview: 'Dashboard Overview',
    'resource-usage': 'Resource Usage',
    tenants: 'Tenant Management',
    staff: 'Staff Management',
    roles: 'Roles & Permissions',
    subscriptions: 'Subscriptions',
    'payment-methods': 'Payment Methods',
    plans: 'Infrastructure & Plans',
    activity: 'Activity Logs',
    settings: 'System Settings',
    profile: 'My Profile',
    impersonate: 'God Mode',
};

const viewMotionSx = {
    '@keyframes dashboardViewIn': {
        from: { opacity: 0, transform: 'translateY(8px)' },
        to: { opacity: 1, transform: 'translateY(0)' },
    },
    '@keyframes dashboardViewFade': {
        from: { opacity: 0 },
        to: { opacity: 1 },
    },
    animation: 'dashboardViewIn 220ms cubic-bezier(0.16, 1, 0.3, 1) both',
    '@media (prefers-reduced-motion: reduce)': {
        animation: 'dashboardViewFade 140ms linear both',
    },
};

export default function Dashboard() {
    const { user, permissions = [] } = useAuthContext();
    const theme = useTheme();
    const [tenants, setTenants] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showForm, setShowForm] = useState(false);
    const [editingTenant, setEditingTenant] = useState(null);
    const [view, setView] = useState('overview');
    const [error, setError] = useState(null);

    const [activeModal, setActiveModal] = useState(null);
    const [planChangeTenant, setPlanChangeTenant] = useState(null);
    const [showDeleted, setShowDeleted] = useState(false);

    const [page, setPage] = useState(0);
    const [rowsPerPage, setRowsPerPage] = useState(5);
    const [total, setTotal] = useState(0);
    const [selectedTenantIds, setSelectedTenantIds] = useState(new Set());
    const [subscriptionSearch, setSubscriptionSearch] = useState('');
    const [tenantSearch, setTenantSearch] = useState('');
    const [showDeletedTenantList, setShowDeletedTenantList] = useState(false);
    const [bulkLoading, setBulkLoading] = useState(false);
    const [refreshTrigger, setRefreshTrigger] = useState(0);
    const incrementRefreshTrigger = useCallback(() => setRefreshTrigger(r => r + 1), []);
    const [bulkPlanChangeOpen, setBulkPlanChangeOpen] = useState(false);
    const [activateTrialTenants, setActivateTrialTenants] = useState(null);
    const [deleteConfirmTenant, setDeleteConfirmTenant] = useState(null);
    const [deleteWorkspaceName, setDeleteWorkspaceName] = useState('');
    const [restoreConfirmId, setRestoreConfirmId] = useState(null);
    const [impersonateConfirmTenant, setImpersonateConfirmTenant] = useState(null);
    const [paymentMethods, setPaymentMethods] = useState([]);

    const fetchPaymentMethods = useCallback(async () => {
        try {
            const response = await api.get('/admin/api/payment-methods');
            setPaymentMethods(response.data.methods || []);
        } catch (err) {
            toast.error('Failed to fetch payment methods');
        }
    }, []);

    useEffect(() => {
        if (permissions.includes('view tenants')) {
            fetchTenants();
        } else {
            setLoading(false);
        }
    }, [showDeleted, page, rowsPerPage, permissions]);

    useEffect(() => {
        if (view === 'payment-methods') {
            fetchPaymentMethods();
        }
    }, [view, fetchPaymentMethods]);

    const fetchTenants = async () => {
        setLoading(true);
        try {
            const params = new URLSearchParams();
            if (showDeleted) params.set('trashed', '1');
            params.set('page', page + 1);
            params.set('per_page', rowsPerPage);
            const response = await api.get(`/admin/api/tenants?${params}`);
            setTenants(response.data.tenants);
            setTotal(response.data.total);
        } catch (err) {
            const message = 'Failed to fetch tenants';
            toast.error(message);
            setError(message);
            console.error(err);
        } finally {
            setLoading(false);
        }
    };

    const handlePageChange = (newPage) => {
        setPage(newPage);
    };

    const handleRowsPerPageChange = (newRowsPerPage) => {
        setRowsPerPage(newRowsPerPage);
        setPage(0);
    };

    const handleLogout = async () => {
        try {
            await api.post('/central/logout');
            window.location.href = '/central/login';
        } catch (err) {
            console.error('Logout error:', err);
        }
    };

    const handleCreateTenant = async (data) => {
        try {
            await api.post('/admin/api/tenants', data);
            toast.success('Tenant created successfully');
            setShowForm(false);
            fetchTenants();
            incrementRefreshTrigger();
        } catch (err) {
            const message = err.response?.data?.message || 'Failed to create tenant';
            toast.error(message);
            setError(message);
        }
    };

    const handleDeleteTenant = async (tenant) => {
        setDeleteConfirmTenant(tenant);
        setDeleteWorkspaceName('');
    };

    const confirmDeleteTenant = async () => {
        if (!deleteConfirmTenant) return;
        if (deleteWorkspaceName.trim() !== deleteConfirmTenant.name) return;

        try {
            await deleteTenant(deleteConfirmTenant.id, deleteWorkspaceName);
            toast.success('Tenant disabled. Data is preserved and it can be restored.');
            fetchTenants();
            incrementRefreshTrigger();
        } catch (err) {
            const message = 'Failed to disable tenant';
            toast.error(message);
            setError(message);
        } finally {
            setDeleteConfirmTenant(null);
            setDeleteWorkspaceName('');
        }
    };

    const handleRestoreTenant = async (id) => {
        setRestoreConfirmId(id);
    };

    const confirmRestoreTenant = async () => {
        if (restoreConfirmId === null) return;
        try {
            await api.patch(`/admin/api/tenants/${restoreConfirmId}/restore`);
            toast.success('Tenant restored successfully');
            fetchTenants();
            incrementRefreshTrigger();
        } catch (err) {
            const message = 'Failed to restore tenant';
            toast.error(message);
            setError(message);
        } finally {
            setRestoreConfirmId(null);
        }
    };

    const handleEditTenant = (tenant) => {
        setEditingTenant(tenant);
    };

    const handleUpdateTenant = async (tenantOrOriginal, values = null) => {
        // support two calling conventions:
        // - from TenantForm: payload object includes id, name, email, plan_id
        // - from inline row save: first arg is original row, second arg is values changed
        let id;
        let data;
        if (values === null) {
            // form submission
            id = tenantOrOriginal.id;
            data = { name: tenantOrOriginal.name, email: tenantOrOriginal.email };
            if (tenantOrOriginal.plan_id) {
                data.plan_id = tenantOrOriginal.plan_id;
            }
        } else {
            // inline edit from table — only send fields the backend accepts
            id = tenantOrOriginal.id;
            const { name, email } = values;
            data = { name, email };
        }

        try {
            await api.put(`/admin/api/tenants/${id}`, data);
            toast.success('Tenant updated successfully');
            setEditingTenant(null);
            fetchTenants();
            incrementRefreshTrigger();
        } catch (err) {
            const message = err.response?.data?.message || 'Failed to update tenant';
            toast.error(message);
            setError(message);
        }
    };

    const handleImpersonateTenant = async (tenant) => {
        setImpersonateConfirmTenant(tenant);
    };

    const confirmImpersonateTenant = async () => {
        if (!impersonateConfirmTenant) return;
        const entryTab = window.open('', '_blank');
        try {
            const res = await api.post('/admin/api/impersonate', { tenant_id: impersonateConfirmTenant.id });
            const enterUrl = res.data.enterUrl;
            if (enterUrl) {
                if (entryTab) {
                    entryTab.location.href = enterUrl;
                } else {
                    window.location.href = enterUrl;
                }
            } else {
                entryTab?.close();
                const message = 'No entry point available for tenant';
                toast.error(message);
                setError(message);
            }
        } catch (err) {
            entryTab?.close();
            const message = err.response?.data?.message || 'Failed to impersonate tenant';
            toast.error(message);
            setError(message);
        } finally {
            setImpersonateConfirmTenant(null);
        }
    };

    const handleToggleActive = async (tenant) => {
        if (tenant.status === 'Trial') {
            handleOpenActivateTrial([tenant]);
            return;
        }
        const newStatus = tenant.status === 'Active' ? 'Suspended' : 'Active';
        try {
            await api.put(`/admin/api/tenants/${tenant.id}`, { status: newStatus });
            fetchTenants();
            incrementRefreshTrigger();
            toast.success('Tenant status updated successfully');
        } catch (err) {
            const message = err.response?.data?.message || 'Failed to update tenant status';
            toast.error(message);
            setError(message);
        }
    };

    const handleOpenActivateTrial = (tenants) => {
        setActivateTrialTenants(tenants);
    };

    const handleSetView = (newView) => {
        setSubscriptionSearch('');
        setTenantSearch('');
        setShowDeletedTenantList(false);
        setView(newView);
    };

    const handleViewSubscriptions = (tenant) => {
        setSubscriptionSearch(tenant?.name ?? '');
        setView('subscriptions');
    };

    const handleRefreshPaymentCard = async (tenant) => {
        try {
            const response = await api.post(`/admin/api/tenants/${tenant.id}/payment-methods/refresh`);
            toast.success(response.data?.message || 'Payment card refreshed.');
            incrementRefreshTrigger();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to refresh payment card');
        }
    };

    const handleManagePaymentCard = async (tenant) => {
        try {
            const response = await api.post(`/admin/api/tenants/${tenant.id}/billing-portal`);
            if (response.data?.url) {
                window.location.assign(response.data.url);
                return;
            }
            toast.error('No Stripe portal URL was returned');
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to open Stripe portal');
        }
    };

    const handleBulkAction = async (action, ids) => {
        if (!ids || ids.length === 0) return;

        if (action === 'change_plan') {
            setBulkPlanChangeOpen(true);
            return;
        }

        setBulkLoading(true);
        try {
            await api.post('/admin/api/tenants/bulk', {
                tenant_ids: ids,
                action,
            });
            toast.success(`Bulk ${action} completed for ${ids.length} tenant(s)`);
            setSelectedTenantIds(new Set());
            fetchTenants();
            incrementRefreshTrigger();
        } catch (err) {
            toast.error(err.response?.data?.message || `Bulk ${action} failed`);
        } finally {
            setBulkLoading(false);
        }
    };

    const handleViewTenant = (tenantId, tenantName = '') => {
        setTenantSearch(tenantName);
        setShowDeletedTenantList(false);
        if (!tenantId) { setView('tenants'); return; }
        api.get(`/admin/api/tenants/${tenantId}`).then(res => {
            const tenant = res.data.tenant;
            if (tenant?.is_deleted) {
                setShowDeletedTenantList(true);
            }
            setView('tenants');
            openModal('domain', tenant);
        }).catch(err => {
            setView('tenants');
            const status = err.response?.status;
            const message = status === 404
                ? `Tenant #${tenantId} no longer exists. It may have been permanently deleted.`
                : err.response?.data?.message || 'Failed to load tenant details';
            toast.error(message);
            setError(message);
        });
    };

    const openModal = (type, tenant) => setActiveModal({ type, tenant });

    const handleTenantUpdated = (tenant) => {
        setActiveModal(prev => (prev ? { ...prev, tenant } : prev));
        fetchTenants();
        incrementRefreshTrigger();
    };

    return (
        <Box sx={{ height: '100vh', overflow: 'hidden', bgcolor: 'background.default', display: 'flex' }}>
            <Navbar view={view} setView={handleSetView} />
            <Box sx={{ flex: 1, overflowY: 'auto', height: '100vh', '&::-webkit-scrollbar': { display: 'none' }, scrollbarWidth: 'none', msOverflowStyle: 'none' }}>
                <Box
                    component="header"
                    sx={{
                        bgcolor: 'background.paper',
                        pl: { xs: 7, sm: 9, md: 4 },
                        pr: { xs: 2, md: 4 },
                        py: { xs: 1.5, md: 2 },
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center',
                        gap: 2,
                        borderBottom: 1,
                        borderColor: 'divider',
                    }}
                >
                    <Box sx={{ minWidth: 0 }}>
                        <Box component="nav" aria-label="Breadcrumb" sx={{ display: 'flex', alignItems: 'center', gap: 0.5, color: 'text.secondary' }}>
                            <Typography
                                variant="caption"
                                component="span"
                                sx={{ textTransform: 'uppercase', letterSpacing: '0.12em', fontSize: 10, fontWeight: 700 }}
                            >
                                ShoppingLi
                            </Typography>
                            <Typography variant="caption" component="span" aria-hidden="true">/</Typography>
                            <Typography
                                variant="caption"
                                component="span"
                                aria-current="page"
                                sx={{ textTransform: 'uppercase', letterSpacing: '0.12em', fontSize: 10, fontWeight: 700 }}
                            >
                                Admin
                            </Typography>
                        </Box>
                        <Typography
                            key={view}
                            variant="h5"
                            component="h1"
                            noWrap
                            sx={{ mt: 0.25, fontSize: { xs: 22, md: 25 }, ...viewMotionSx }}
                        >
                            {VIEW_TITLES[view] ?? 'Dashboard'}
                        </Typography>
                        <Box sx={{ mt: 1, width: 36, height: 2, borderRadius: 2, bgcolor: 'primary.main' }} />
                    </Box>

                    <Box sx={{ display: 'flex', alignItems: 'center', gap: { xs: 1, sm: 2 } }}>
                        <Box sx={{ display: { xs: 'none', sm: 'flex' }, alignItems: 'center', gap: 1.25 }}>
                            <Avatar
                                sx={{
                                    width: 34,
                                    height: 34,
                                    bgcolor: 'primary.main',
                                    color: 'primary.contrastText',
                                    fontSize: 14,
                                    boxShadow: `0 0 0 3px ${alpha(theme.palette.primary.main, 0.14)}`,
                                    transition: 'box-shadow 150ms ease-out',
                                    '&:hover': { boxShadow: `0 0 0 5px ${alpha(theme.palette.primary.main, 0.18)}` },
                                    '@media (prefers-reduced-motion: reduce)': { transition: 'none' },
                                }}
                            >
                                {(user?.name || 'A').trim().charAt(0).toUpperCase()}
                            </Avatar>
                            <Box sx={{ minWidth: 0 }}>
                                <Typography variant="body2" noWrap sx={{ fontWeight: 600 }}>
                                    {user?.name}
                                </Typography>
                                <Typography variant="caption" color="text.secondary" noWrap>
                                    {user?.email}
                                </Typography>
                            </Box>
                        </Box>
                        <Button
                            variant="outlined"
                            color="primary"
                            size="small"
                            startIcon={<LogoutIcon fontSize="small" />}
                            onClick={handleLogout}
                            sx={{
                                borderColor: 'divider',
                                color: 'text.primary',
                                transition: 'border-color 150ms ease-out, background-color 150ms ease-out, transform 150ms ease-out',
                                '&:hover': {
                                    borderColor: 'primary.main',
                                    bgcolor: 'action.hover',
                                    transform: 'translateY(-1px)',
                                },
                                '@media (prefers-reduced-motion: reduce)': {
                                    transition: 'border-color 150ms ease-out, background-color 150ms ease-out',
                                    '&:hover': { transform: 'none' },
                                },
                            }}
                        >
                            Logout
                        </Button>
                    </Box>
                </Box>

                <Box component="main" sx={{ px: { xs: 2, md: 4 }, py: { xs: 2, md: 3 } }}>
                    <Box key={view} sx={{ maxWidth: 1440, mx: 'auto', ...viewMotionSx }}>
                        <Suspense fallback={<Box sx={{ display: 'flex', justifyContent: 'center', py: 8 }}><CircularProgress /></Box>}>
                        {error && (
                            <Alert severity="error" onClose={() => setError(null)} sx={{ mb: 2.5 }}>
                                {error}
                            </Alert>
                        )}

                        <ErrorBoundary fallbackMessage="Overview failed to load">
                            {view === 'overview' && <DashboardOverview />}
                        </ErrorBoundary>
                        <ErrorBoundary fallbackMessage="Staff management failed to load">
                            {view === 'staff' && <Staff />}
                        </ErrorBoundary>
                        <ErrorBoundary fallbackMessage="Roles & permissions failed to load">
                            {view === 'roles' && <RolePermissions />}
                        </ErrorBoundary>
                        <ErrorBoundary fallbackMessage="Plans failed to load">
                            {view === 'plans' && <Plans />}
                        </ErrorBoundary>
                        <ErrorBoundary fallbackMessage="Resource usage failed to load">
                            {view === 'resource-usage' && <ResourceUsage />}
                        </ErrorBoundary>
                        {view === 'tenants' && (showForm || editingTenant) && (
                            <TenantForm
                                tenant={editingTenant}
                                onSubmit={editingTenant ? handleUpdateTenant : handleCreateTenant}
                                onCancel={() => {
                                    setEditingTenant(null);
                                    setShowForm(false);
                                }}
                            />
                        )}
                        <ErrorBoundary fallbackMessage="Tenant management failed to load">
                        {view === 'tenants' && !showForm && !editingTenant && (
                            <TenantList
                                refreshTrigger={refreshTrigger}
                                bulkLoading={bulkLoading}
                                initialSearch={tenantSearch}
                                initialShowDeleted={showDeletedTenantList}
                                onAdd={() => setShowForm(true)}
                                onDelete={handleDeleteTenant}
                                onEdit={handleEditTenant}
                                onImpersonate={handleImpersonateTenant}
                                onRestore={handleRestoreTenant}
                                onSelectionChange={setSelectedTenantIds}
                                onToggleStatus={handleToggleActive}
                                onActivateTrial={handleOpenActivateTrial}
                                onRefreshPaymentCard={handleRefreshPaymentCard}
                                onManagePaymentCard={handleManagePaymentCard}
                                rowMenuActions={(tenant) => {
                                    if (tenant.is_deleted) {
                                        return [
                                            { label: 'Restore', icon: <RestoreIcon fontSize="small" />, onClick: () => handleRestoreTenant(tenant.id) },
                                        ];
                                    }
                                    return [
                                        { divider: true },
                                        { label: tenant.status === 'Active' ? 'Suspend' : 'Activate', icon: <BlockIcon fontSize="small" />, onClick: () => tenant.status === 'Trial' ? handleOpenActivateTrial([tenant]) : handleToggleActive(tenant) },
                                        { label: 'Change Plan', icon: <ChangeCircleIcon fontSize="small" />, onClick: () => setPlanChangeTenant(tenant) },
                                        { divider: true },
                                        { label: 'View Details', icon: <VisibilityIcon fontSize="small" />, onClick: () => openModal('domain', tenant) },
                                        { label: 'View Subscriptions', icon: <ReceiptIcon fontSize="small" />, onClick: () => handleViewSubscriptions(tenant) },
                                        { divider: true },
                                        { label: 'DB Info', icon: <StorageIcon fontSize="small" />, onClick: () => openModal('database', tenant) },
                                        { label: 'Run Migrations', icon: <SyncIcon fontSize="small" />, onClick: () => openModal('migration', tenant) },
                                    ];
                                }}
                                onBulkAction={handleBulkAction}
                            />
                        )}
                        </ErrorBoundary>
                        <ErrorBoundary fallbackMessage="Subscriptions failed to load">
                            {view === 'subscriptions' && <Subscriptions initialSearch={subscriptionSearch} onViewTenant={handleViewTenant} />}
                        </ErrorBoundary>
                        <ErrorBoundary fallbackMessage="Payment methods failed to load">
                            {view === 'payment-methods' && (
                                <PaymentMethods
                                    paymentMethods={paymentMethods}
                                    fetchPaymentMethods={fetchPaymentMethods}
                                    setError={setError}
                                />
                            )}
                        </ErrorBoundary>
                        <ErrorBoundary fallbackMessage="Activity log failed to load">
                            {view === 'activity' && <ActivityLog />}
                        </ErrorBoundary>
                        <ErrorBoundary fallbackMessage="Settings failed to load">
                            {view === 'settings' && <Settings />}
                        </ErrorBoundary>
                        <ErrorBoundary fallbackMessage="Profile failed to load">
                            {view === 'profile' && <Profile />}
                        </ErrorBoundary>
                        {view === 'impersonate' && (
                            <>
                                <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
                                    <Box>
                                        <Typography variant="subtitle1" sx={{ fontWeight: 600, color: 'text.primary' }}>
                                            Impersonate Tenant
                                        </Typography>
                                        <Typography variant="body2" sx={{ color: 'text.secondary', mt: 0.5 }}>
                                            Select a tenant to impersonate. You will be redirected to their domain.
                                        </Typography>
                                    </Box>
                                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                                        <FormControlLabel
                                            control={<Switch size="small" checked={showDeleted} onChange={() => setShowDeleted(s => !s)} />}
                                            label={<Typography variant="caption" sx={{ color: 'text.secondary' }}>Show deleted</Typography>}
                                            sx={{ mr: 0 }}
                                        />
                                        <Button
                                            variant="outlined"
                                            size="small"
                                            onClick={() => { setView('tenants'); if (permissions.includes('view tenants')) fetchTenants(); }}
                                        >
                                            Back to Tenants
                                        </Button>
                                    </Box>
                                </Box>
                                {permissions.includes('view tenants') ? (
                                    <DataTable
                                        columns={[
                                            { accessorKey: 'id', header: 'ID' },
                                            { accessorKey: 'name', header: 'Name' },
                                            {
                                                accessorKey: 'domain',
                                                header: 'Domain',
                                                Cell: ({ cell }) => (
                                                    <Typography
                                                        component="code"
                                                        variant="body2"
                                                        sx={{ bgcolor: 'grey.100', px: 0.75, py: 0.25, borderRadius: 0.5, fontFamily: 'monospace' }}
                                                    >
                                                        {cell.getValue()}
                                                    </Typography>
                                                ),
                                            },
                                            {
                                                accessorKey: 'status',
                                                header: 'Status',
                                                Cell: ({ cell }) => {
                                                    const s = cell.getValue();
                                                    return (
                                                        <Chip
                                                            label={s}
                                                            size="small"
                                                            color={
                                                                s === 'Active' ? 'success'
                                                                : s === 'Trial' ? 'warning'
                                                                : s === 'Suspended' ? 'error'
                                                                : 'default'
                                                            }
                                                            variant={s === 'Active' || s === 'Trial' || s === 'Suspended' ? 'filled' : 'outlined'}
                                                        />
                                                    );
                                                },
                                            },
                                        ]}
                                        data={tenants.filter(t => showDeleted ? true : ['Active', 'Trial'].includes(t.status))}
                                        onImpersonate={handleImpersonateTenant}
                                        emptyMessage={showDeleted ? 'No tenants found.' : 'No active tenants to impersonate.'}
                                        loading={loading}
                                        total={total}
                                        page={page}
                                        rowsPerPage={rowsPerPage}
                                        onPageChange={handlePageChange}
                                        onRowsPerPageChange={handleRowsPerPageChange}
                                    />
                                ) : (
                                    <Alert severity="info">
                                        You need the <strong>view tenants</strong> permission to list and impersonate tenants.
                                    </Alert>
                                )}
                            </>
                        )}
                        </Suspense>
                    </Box>

                    <Suspense fallback={null}>
                        <DatabaseModal
                            tenant={activeModal?.type === 'database' ? activeModal.tenant : null}
                            onClose={() => setActiveModal(null)}
                            />

                        <MigrationModal
                            tenant={activeModal?.type === 'migration' ? activeModal.tenant : null}
                            onClose={() => setActiveModal(null)}
                            />

                        <DomainModal
                            tenant={activeModal?.type === 'domain' ? activeModal.tenant : null}
                            onClose={() => setActiveModal(null)}
                            onImpersonate={handleImpersonateTenant}
                            onViewDatabase={(tenant) => openModal('database', tenant)}
                            onRunMigrations={(tenant) => openModal('migration', tenant)}
                            onRestore={handleRestoreTenant}
                            onTenantUpdated={handleTenantUpdated}
                            />

                        <ChangePlanModal
                            open={!!planChangeTenant}
                            tenants={planChangeTenant ? [planChangeTenant] : []}
                            onClose={() => setPlanChangeTenant(null)}
                            onChanged={() => { fetchTenants(); incrementRefreshTrigger(); }}
                        />

                        <ChangePlanModal
                            open={bulkPlanChangeOpen}
                            tenants={tenants.filter(t => selectedTenantIds.has(t.id))}
                            onClose={() => setBulkPlanChangeOpen(false)}
                            onChanged={() => {
                                setBulkPlanChangeOpen(false);
                                setSelectedTenantIds(new Set());
                                fetchTenants();
                                incrementRefreshTrigger();
                            }}
                        />

                        <ActivateTrialModal
                            open={activateTrialTenants !== null}
                            tenants={activateTrialTenants || []}
                            onClose={() => setActivateTrialTenants(null)}
                            onActivated={() => {
                                setActivateTrialTenants(null);
                                fetchTenants();
                                incrementRefreshTrigger();
                            }}
                        />

                        <ConfirmDialog
                            open={!!deleteConfirmTenant}
                            title="Delete Tenant"
                            message="This will disable the tenant. The data will be preserved and can be restored later."
                            confirmLabel="Delete"
                            confirmationTarget={deleteConfirmTenant?.name}
                            confirmationValue={deleteWorkspaceName}
                            onConfirmationChange={setDeleteWorkspaceName}
                            onConfirm={confirmDeleteTenant}
                            onCancel={() => {
                                setDeleteConfirmTenant(null);
                                setDeleteWorkspaceName('');
                            }}
                        />

                        <ConfirmDialog
                            open={restoreConfirmId !== null}
                            title="Restore Tenant"
                            message="Restore this tenant? It will be reactivated."
                            confirmLabel="Restore"
                            onConfirm={confirmRestoreTenant}
                            onCancel={() => setRestoreConfirmId(null)}
                        />

                        <ConfirmDialog
                            open={!!impersonateConfirmTenant}
                            title="Impersonate Tenant"
                            message={impersonateConfirmTenant ? `Impersonate tenant ${impersonateConfirmTenant.id} (${impersonateConfirmTenant.domain})?` : ''}
                            confirmLabel="Impersonate"
                            onConfirm={confirmImpersonateTenant}
                            onCancel={() => setImpersonateConfirmTenant(null)}
                        />
                    </Suspense>
                </Box>
            </Box>
        </Box>
    );
}
