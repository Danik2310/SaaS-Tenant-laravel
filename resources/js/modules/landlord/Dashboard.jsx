import React, { useEffect, useState, Suspense } from 'react';
import api from '../../services/api';
import Navbar from '../../components/Navbar';
import DataTable from '@/Components/DataTable';
import BlockIcon from '@mui/icons-material/Block';
import LanguageIcon from '@mui/icons-material/Language';
import StorageIcon from '@mui/icons-material/Storage';
import SyncIcon from '@mui/icons-material/Sync';
import ChangeCircleIcon from '@mui/icons-material/ChangeCircle';
import VisibilityIcon from '@mui/icons-material/Visibility';
import ReceiptIcon from '@mui/icons-material/Receipt';
import RestoreIcon from '@mui/icons-material/Restore';
import DeleteForeverIcon from '@mui/icons-material/DeleteForever';
import { toast } from 'sonner';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import FormControlLabel from '@mui/material/FormControlLabel';
import Switch from '@mui/material/Switch';
import CircularProgress from '@mui/material/CircularProgress';
import ErrorBoundary from '@/Components/ErrorBoundary';
import { useAuthContext } from '@/context/AuthContext';

const DashboardOverview = React.lazy(() => import('./DashboardOverview'));
const TenantList = React.lazy(() => import('./tenants/TenantList'));
const TenantForm = React.lazy(() => import('./tenants/TenantForm'));
const Staff = React.lazy(() => import('./staff/StaffList'));
const Plans = React.lazy(() => import('./billing/Plans'));
const RolePermissions = React.lazy(() => import('./staff/RolePermissions'));
const Profile = React.lazy(() => import('./profile/Profile'));
const Subscriptions = React.lazy(() => import('./subscriptions/Subscriptions'));
const ActivityLog = React.lazy(() => import('./activity/ActivityLog'));
const Settings = React.lazy(() => import('./settings/Settings'));
const ResourceUsage = React.lazy(() => import('./resource-usage/ResourceUsage'));
const DatabaseModal = React.lazy(() => import('./modals/DatabaseModal'));
const MigrationModal = React.lazy(() => import('./modals/MigrationModal'));
const DomainModal = React.lazy(() => import('./modals/DomainModal'));
const ChangePlanModal = React.lazy(() => import('./modals/ChangePlanModal'));

export default function Dashboard() {
    const { user, permissions = [] } = useAuthContext();
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
    const [rowsPerPage, setRowsPerPage] = useState(25);
    const [total, setTotal] = useState(0);
    const [selectedTenantIds, setSelectedTenantIds] = useState(new Set());
    const [bulkLoading, setBulkLoading] = useState(false);
    const [bulkPlanChangeOpen, setBulkPlanChangeOpen] = useState(false);

    useEffect(() => {
        if (permissions.includes('manage tenants')) {
            fetchTenants();
        } else {
            setLoading(false);
        }
    }, [showDeleted, page, rowsPerPage, permissions]);

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
        } catch (err) {
            const message = err.response?.data?.message || 'Failed to create tenant';
            toast.error(message);
            setError(message);
        }
    };

    const handleDeleteTenant = async (tenant) => {
        if (window.confirm('This will soft-delete the tenant. The data will be preserved and can be restored later. Continue?')) {
            try {
                await api.delete(`/admin/api/tenants/${tenant.id}`);
                toast.success('Tenant soft-deleted successfully');
                fetchTenants();
            } catch (err) {
                const message = 'Failed to delete tenant';
                toast.error(message);
                setError(message);
            }
        }
    };

    const handleRestoreTenant = async (id) => {
        if (window.confirm('Restore this tenant? It will be reactivated.')) {
            try {
                await api.patch(`/admin/api/tenants/${id}/restore`);
                toast.success('Tenant restored successfully');
                fetchTenants();
            } catch (err) {
                const message = 'Failed to restore tenant';
                toast.error(message);
                setError(message);
            }
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
            // inline edit from table
            id = tenantOrOriginal.id;
            data = values;
        }

        try {
            await api.put(`/admin/api/tenants/${id}`, data);
            setEditingTenant(null);
            fetchTenants();
        } catch (err) {
            const message = err.response?.data?.message || 'Failed to update tenant';
            toast.error(message);
            setError(message);
        }
    };

    const handleImpersonateTenant = async (tenant) => {
        if (!confirm(`Impersonate tenant ${tenant.id} (${tenant.domain})?`)) return;
        try {
            const res = await api.post('/admin/api/impersonate', { tenant_id: tenant.id });
            const domain = res.data.domain;
            if (domain) {
                // Redirect to tenant domain root
                window.location.href = `http://${domain}`;
            } else {
                const message = 'No domain available for tenant';
                toast.error(message);
                setError(message);
            }
        } catch (err) {
            const message = err.response?.data?.message || 'Failed to impersonate tenant';
            toast.error(message);
            setError(message);
        }
    };

    // --- new handlers for extended actions ---
    const handleToggleActive = async (tenant) => {
        // Optimistic update
        const newStatus = tenant.status === 'Active' ? 'Suspended' : 'Active';
        setTenants(prevTenants =>
            prevTenants.map(t =>
                t.id === tenant.id ? { ...t, status: newStatus } : t
            )
        );

        try {
            await api.put(`/admin/api/tenants/${tenant.id}`, { status: newStatus });
            // Fetch fresh data to ensure consistency
            fetchTenants();
            toast.success('Tenant status updated successfully');
        } catch (err) {
            // Revert on error
            setTenants(prevTenants =>
                prevTenants.map(t =>
                    t.id === tenant.id ? { ...t, status: tenant.status } : t
                )
            );
            const message = err.response?.data?.message || 'Failed to update tenant status';
            toast.error(message);
            setError(message);
        }
    };

    const handleViewSubscriptions = (tenant) => {
        setView('subscriptions');
    };

    const handleBulkAction = async (action) => {
        const ids = Array.from(selectedTenantIds);
        if (ids.length === 0) return;

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
        } catch (err) {
            toast.error(err.response?.data?.message || `Bulk ${action} failed`);
        } finally {
            setBulkLoading(false);
        }
    };

    const handleViewTenant = async (tenantId) => {
        setView('tenants');
        try {
            const res = await api.get(`/admin/api/tenants/${tenantId}`);
            openModal('domain', res.data.tenant);
        } catch (err) {
            const status = err.response?.status;
            const message = status === 404
                ? `Tenant #${tenantId} no longer exists. It may have been permanently deleted.`
                : err.response?.data?.message || 'Failed to load tenant details';
            toast.error(message);
            setError(message);
        }
    };

    const openModal = (type, tenant) => setActiveModal({ type, tenant });

    return (
        <div style={{ minHeight: '100vh', background: '#f8fafc', display: 'flex' }}>
            <Navbar view={view} setView={setView} />
            <div style={{ flex: 1 }}>
                <header
                    style={{
                        background: '#ffffff',
                        padding: '16px 32px',
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center',
                        borderBottom: '1px solid #e2e8f0',
                        boxShadow: '0 1px 2px rgba(0,0,0,0.04)',
                    }}
                >
                    <div>
                        <h1 style={{ margin: 0, fontSize: '20px', fontWeight: 700, color: '#0f172a' }}>
                            {view === 'overview' && 'Dashboard Overview'}
                            {view === 'resource-usage' && 'Resource Usage'}
                            {view === 'tenants' && 'Tenant Management'}
                            {view === 'staff' && 'Staff Management'}
                            {view === 'roles' && 'Roles & Permissions'}
                            {view === 'subscriptions' && 'Subscriptions'}
                            {view === 'plans' && 'Infrastructure & Plans'}
                            {view === 'activity' && 'Activity Logs'}
                            {view === 'settings' && 'System Settings'}
                            {view === 'profile' && 'My Profile'}
                            {view === 'impersonate' && 'God Mode'}
                        </h1>
                        <p style={{ margin: '4px 0 0 0', fontSize: '13px', color: '#64748b' }}>
                            {user?.name}
                        </p>
                    </div>
                    {(showForm || editingTenant) && (
                        <button
                            onClick={() => { setEditingTenant(null); setShowForm(false); }}
                            style={{
                                padding: '8px 20px',
                                background: '#f1f5f9',
                                color: '#334155',
                                border: '1px solid #e2e8f0',
                                borderRadius: '6px',
                                cursor: 'pointer',
                                fontSize: '13px',
                                fontWeight: 600,
                            }}
                        >
                            Cancel
                        </button>
                    )}
                    <button
                        onClick={handleLogout}
                        style={{
                            padding: '8px 20px',
                            background: '#ef4444',
                            color: 'white',
                            border: 'none',
                            borderRadius: '6px',
                            cursor: 'pointer',
                            fontSize: '13px',
                            fontWeight: 600,
                        }}
                    >
                        Logout
                    </button>
                </header>

                <main style={{ padding: '24px 32px' }}>
                    <div style={{ maxWidth: '1280px', margin: '0 auto' }}>
                        <Suspense fallback={<div style={{ display: 'flex', justifyContent: 'center', padding: '64px 0' }}><CircularProgress /></div>}>
                        {error && (
                            <div
                                style={{
                                    background: '#fef2f2',
                                    color: '#dc2626',
                                    padding: '12px 16px',
                                    borderRadius: '6px',
                                    marginBottom: '20px',
                                    border: '1px solid #fecaca',
                                }}
                            >
                                {error}
                            </div>
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
                        {view === 'tenants' && !showForm && !editingTenant && (
                            <TenantList
                                tenants={tenants.filter(t => showDeleted ? true : !t.is_deleted)}
                                onAdd={() => setShowForm(true)}
                                onDelete={handleDeleteTenant}
                                onEdit={handleEditTenant}
                                onImpersonate={handleImpersonateTenant}
                                onRestore={handleRestoreTenant}
                                showDeleted={showDeleted}
                                onToggleDeleted={() => { setShowDeleted(s => !s); setPage(0); }}
                                onRowSave={async (original, values) => {
                                    try {
                                        await api.put(`/admin/api/tenants/${original.id}`, values);
                                        fetchTenants();
                                    } catch (err) {
                                        setError(err.response?.data?.message || 'Failed to update tenant');
                                    }
                                }}
                                rowMenuActions={(tenant) => {
                                    if (tenant.is_deleted) {
                                        return [
                                            { label: 'Restore', icon: <RestoreIcon fontSize="small" />, onClick: () => handleRestoreTenant(tenant.id) },
                                        ];
                                    }
                                    return [
                                        { divider: true },
                                        { label: tenant.status === 'Active' ? 'Suspend' : 'Activate', icon: <BlockIcon fontSize="small" />, onClick: handleToggleActive },
                                        { label: 'Change Plan', icon: <ChangeCircleIcon fontSize="small" />, onClick: () => setPlanChangeTenant(tenant) },
                                        { divider: true },
                                        { label: 'View Details', icon: <VisibilityIcon fontSize="small" />, onClick: () => openModal('domain', tenant) },
                                        { label: 'View Subscriptions', icon: <ReceiptIcon fontSize="small" />, onClick: () => handleViewSubscriptions(tenant) },
                                        { divider: true },
                                        { label: 'DB Info', icon: <StorageIcon fontSize="small" />, onClick: () => openModal('database', tenant) },
                                        { label: 'Run Migrations', icon: <SyncIcon fontSize="small" />, onClick: () => openModal('migration', tenant) },
                                    ];
                                }}
                                loading={loading}
                                total={total}
                                page={page}
                                rowsPerPage={rowsPerPage}
                                onPageChange={(newPage) => { setSelectedTenantIds(new Set()); handlePageChange(newPage); }}
                                onRowsPerPageChange={(newRowsPerPage) => { setSelectedTenantIds(new Set()); handleRowsPerPageChange(newRowsPerPage); }}
                                selectedIds={selectedTenantIds}
                                onSelectionChange={setSelectedTenantIds}
                                onBulkAction={handleBulkAction}
                            />
                        )}
                        <ErrorBoundary fallbackMessage="Subscriptions failed to load">
                            {view === 'subscriptions' && <Subscriptions onViewTenant={handleViewTenant} />}
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
                                    <div>
                                        <Typography variant="subtitle1" sx={{ fontWeight: 600, color: '#0f172a' }}>
                                            Impersonate Tenant
                                        </Typography>
                                        <Typography variant="body2" sx={{ color: '#64748b', mt: 0.5 }}>
                                            Select a tenant to impersonate. You will be redirected to their domain.
                                        </Typography>
                                    </div>
                                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                                        <FormControlLabel
                                            control={<Switch size="small" checked={showDeleted} onChange={() => setShowDeleted(s => !s)} />}
                                            label={<Typography variant="caption" sx={{ color: '#64748b' }}>Show deleted</Typography>}
                                            sx={{ mr: 0 }}
                                        />
                                        <button
                                            onClick={() => { setView('tenants'); if (permissions.includes('manage tenants')) fetchTenants(); }}
                                            style={{
                                                padding: '8px 16px',
                                                background: '#f1f5f9',
                                                color: '#334155',
                                                border: '1px solid #e2e8f0',
                                                borderRadius: '6px',
                                                cursor: 'pointer',
                                                fontSize: '13px',
                                                fontWeight: 600,
                                            }}
                                        >
                                            Back to Tenants
                                        </button>
                                    </Box>
                                </Box>
                                {permissions.includes('manage tenants') ? (
                                    <DataTable
                                        columns={[
                                            { accessorKey: 'id', header: 'ID' },
                                            { accessorKey: 'name', header: 'Name' },
                                            {
                                                accessorKey: 'domain',
                                                header: 'Domain',
                                                Cell: ({ cell }) => (
                                                    <code style={{ background: '#f1f5f9', padding: '2px 6px', borderRadius: '3px', fontSize: '13px', fontFamily: 'monospace' }}>
                                                        {cell.getValue()}
                                                    </code>
                                                ),
                                            },
                                            {
                                                accessorKey: 'status',
                                                header: 'Status',
                                                Cell: ({ cell }) => {
                                                    const status = cell.getValue();
                                                    const chipStyle = status === 'Active'
                                                        ? { background: '#dcfce7', color: '#166534' }
                                                        : status === 'Suspended'
                                                            ? { background: '#fee2e2', color: '#991b1b' }
                                                            : { background: '#f1f5f9', color: '#64748b', fontStyle: 'italic' };
                                                    return (
                                                        <span style={{
                                                            display: 'inline-block',
                                                            padding: '4px 8px',
                                                            borderRadius: '4px',
                                                            fontSize: '12px',
                                                            fontWeight: 600,
                                                            ...chipStyle,
                                                        }}>
                                                            {status}
                                                        </span>
                                                    );
                                                },
                                            },
                                        ]}
                                        data={tenants.filter(t => showDeleted ? true : t.status === 'Active')}
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
                                        You need the <strong>manage tenants</strong> permission to list and impersonate tenants.
                                    </Alert>
                                )}
                            </>
                        )}
                        </Suspense>
                    </div>

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
                            />

                        <ChangePlanModal
                            open={!!planChangeTenant}
                            tenants={planChangeTenant ? [planChangeTenant] : []}
                            onClose={() => setPlanChangeTenant(null)}
                            onChanged={fetchTenants}
                        />

                        <ChangePlanModal
                            open={bulkPlanChangeOpen}
                            tenants={tenants.filter(t => selectedTenantIds.has(t.id))}
                            onClose={() => setBulkPlanChangeOpen(false)}
                            onChanged={() => {
                                setBulkPlanChangeOpen(false);
                                setSelectedTenantIds(new Set());
                                fetchTenants();
                            }}
                        />
                    </Suspense>
                </main>
            </div>
        </div>
    );
}
