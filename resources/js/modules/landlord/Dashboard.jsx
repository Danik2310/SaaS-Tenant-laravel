import React, { useEffect, useState } from 'react';
import api from '../../services/api';
import TenantList from './tenants/TenantList';
import TenantForm from './tenants/TenantForm';
import Navbar from '../../components/Navbar';
import DataTable from '@/components/DataTable';
import Staff from './staff/StaffList';
import Plans from './billing/Plans';
import RolePermissions from './staff/RolePermissions';
import DashboardOverview from './DashboardOverview';
import Profile from './profile/Profile';
import Subscriptions from './subscriptions/Subscriptions';
import ActivityLog from './activity/ActivityLog';
import Settings from './settings/Settings';
import ResourceUsage from './resource-usage/ResourceUsage';
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
import DatabaseModal from './modals/DatabaseModal';
import MigrationModal from './modals/MigrationModal';
import DomainModal from './modals/DomainModal';
import {
    Box,
    Typography,
    Divider,
    Dialog,
    DialogTitle,
    DialogContent,
    DialogActions,
    Button,
    Radio,
    RadioGroup,
    FormControlLabel,
    FormControl,
    FormLabel,
    CircularProgress,
    Switch,
} from '@mui/material';

export default function Dashboard({ user, setUser }) {
    const [tenants, setTenants] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showForm, setShowForm] = useState(false);
    const [editingTenant, setEditingTenant] = useState(null);
    const [view, setView] = useState('overview');
    const [error, setError] = useState(null);

    const [activeModal, setActiveModal] = useState(null);
    const [planChangeTenant, setPlanChangeTenant] = useState(null);
    const [plans, setPlans] = useState([]);
    const [selectedPlanId, setSelectedPlanId] = useState(null);
    const [changingPlan, setChangingPlan] = useState(false);
    const [showDeleted, setShowDeleted] = useState(false);

    const [page, setPage] = useState(0);
    const [rowsPerPage, setRowsPerPage] = useState(25);
    const [total, setTotal] = useState(0);
    const [selectedTenantIds, setSelectedTenantIds] = useState(new Set());
    const [bulkLoading, setBulkLoading] = useState(false);

    useEffect(() => {
        fetchTenants();
    }, [showDeleted, page, rowsPerPage]);

    useEffect(() => {
        if (planChangeTenant) {
            fetchPlans();
            setSelectedPlanId(planChangeTenant.plan?.id || '');
        }
    }, [planChangeTenant]);

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
        // - from TenantForm: payload object includes id, name, email
        // - from inline row save: first arg is original row, second arg is values changed
        let id;
        let data;
        if (values === null) {
            // form submission
            id = tenantOrOriginal.id;
            data = { name: tenantOrOriginal.name, email: tenantOrOriginal.email };
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

    const fetchPlans = async () => {
        try {
            const res = await api.get('/admin/api/plans');
            setPlans(res.data.plans || []);
        } catch {
            toast.error('Failed to load plans');
        }
    };

    const handleChangePlan = async () => {
        if (!planChangeTenant || !selectedPlanId) return;
        setChangingPlan(true);
        try {
            await api.put(`/admin/api/tenants/${planChangeTenant.id}/plan`, { plan_id: selectedPlanId });
            toast.success('Plan changed successfully');
            setPlanChangeTenant(null);
            fetchTenants();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to change plan');
        } finally {
            setChangingPlan(false);
        }
    };

    const handleViewSubscriptions = (tenant) => {
        setView('subscriptions');
    };

    const handleBulkAction = async (action) => {
        const ids = Array.from(selectedTenantIds);
        if (ids.length === 0) return;
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

    const openModal = (type, tenant) => setActiveModal({ type, tenant });

    return (
        <div style={{ minHeight: '100vh', background: '#f8fafc', display: 'flex' }}>
            <Navbar user={user} view={view} setView={setView} />
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

                        {view === 'overview' && <DashboardOverview />}
                        {view === 'staff' && <Staff />}
                        {view === 'roles' && <RolePermissions />}
                        {view === 'plans' && <Plans />}
                        {view === 'resource-usage' && <ResourceUsage />}
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
                        {view === 'subscriptions' && <Subscriptions />}
                        {view === 'activity' && <ActivityLog />}
                        {view === 'settings' && <Settings />}
                        {view === 'profile' && <Profile user={user} />}
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
                                            onClick={() => { setView('tenants'); fetchTenants(); }}
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
                            </>
                        )}
                    </div>

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

                    <Dialog
                        open={!!planChangeTenant}
                        onClose={() => setPlanChangeTenant(null)}
                        maxWidth="xs"
                        fullWidth
                    >
                        <DialogTitle>Change Plan</DialogTitle>
                        <DialogContent>
                            <Typography variant="body2" sx={{ mb: 2, color: '#64748b' }}>
                                Select a new plan for <strong>{planChangeTenant?.name}</strong>
                            </Typography>
                            {plans.length === 0 ? (
                                <CircularProgress size={24} />
                            ) : (
                                <FormControl component="fieldset">
                                    <RadioGroup value={selectedPlanId} onChange={(e) => setSelectedPlanId(Number(e.target.value))}>
                                        {plans.map((p) => (
                                            <FormControlLabel
                                                key={p.id}
                                                value={p.id}
                                                control={<Radio />}
                                                label={
                                                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                                        <Typography variant="body2" sx={{ fontWeight: 600 }}>{p.name}</Typography>
                                                        <Typography variant="caption" sx={{ color: '#94a3b8' }}>
                                                            {p.price > 0 ? `$${p.price}/mo` : 'Free'}
                                                        </Typography>
                                                    </Box>
                                                }
                                            />
                                        ))}
                                    </RadioGroup>
                                </FormControl>
                            )}
                        </DialogContent>
                        <DialogActions>
                            <Button onClick={() => setPlanChangeTenant(null)}>Cancel</Button>
                            <Button onClick={handleChangePlan} variant="contained" disabled={changingPlan || !selectedPlanId}>
                                {changingPlan ? 'Changing...' : 'Change Plan'}
                            </Button>
                        </DialogActions>
                    </Dialog>
                </main>
            </div>
        </div>
    );
}
