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
import BlockIcon from '@mui/icons-material/Block';
import LanguageIcon from '@mui/icons-material/Language';
import StorageIcon from '@mui/icons-material/Storage';
import SyncIcon from '@mui/icons-material/Sync';
import { toast } from 'sonner';
import DatabaseModal from './modals/DatabaseModal';
import MigrationModal from './modals/MigrationModal';
import DomainModal from './modals/DomainModal';
import { Box, Typography } from '@mui/material';

export default function Dashboard({ user, setUser }) {
    const [tenants, setTenants] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showForm, setShowForm] = useState(false);
    const [editingTenant, setEditingTenant] = useState(null);
    const [view, setView] = useState('overview');
    const [error, setError] = useState(null);

    const [activeModal, setActiveModal] = useState(null);

    useEffect(() => {
        fetchTenants();
    }, []);

    const fetchTenants = async () => {
        setLoading(true);
        try {
            const response = await api.get('/admin/api/tenants');
            setTenants(response.data.tenants);
        } catch (err) {
            const message = 'Failed to fetch tenants';
            toast.error(message);
            setError(message);
            console.error(err);
        } finally {
            setLoading(false);
        }
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

    const handleDeleteTenant = async (id) => {
        if (window.confirm('Are you sure you want to delete this tenant?')) {
            try {
                await api.delete(`/admin/api/tenants/${id}`);
                toast.success('Tenant deleted successfully');
                fetchTenants();
            } catch (err) {
                const message = 'Failed to delete tenant';
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
                                tenants={tenants}
                                onAdd={() => setShowForm(true)}
                                onDelete={handleDeleteTenant}
                                onEdit={handleEditTenant}
                                onImpersonate={handleImpersonateTenant}
                                onRowSave={async (original, values) => {
                                    try {
                                        await api.put(`/admin/api/tenants/${original.id}`, values);
                                        fetchTenants();
                                    } catch (err) {
                                        setError(err.response?.data?.message || 'Failed to update tenant');
                                    }
                                }}
                                rowMenuActions={(tenant) => [
                                    {
                                        label: tenant.status === 'Active' ? 'Suspend' : 'Activate',
                                        icon: <BlockIcon fontSize="small" />,
                                        onClick: handleToggleActive,
                                    },
                                    {
                                        label: 'View Domains',
                                        icon: <LanguageIcon fontSize="small" />,
                                        onClick: () => openModal('domain', tenant),
                                    },
                                    {
                                        label: 'DB Info',
                                        icon: <StorageIcon fontSize="small" />,
                                        onClick: () => openModal('database', tenant),
                                    },
                                    {
                                        label: 'Run Migrations',
                                        icon: <SyncIcon fontSize="small" />,
                                        onClick: () => openModal('migration', tenant),
                                    },
                                ]}
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
                                            Cell: ({ cell }) => (
                                                <span style={{
                                                    display: 'inline-block',
                                                    padding: '4px 8px',
                                                    borderRadius: '4px',
                                                    fontSize: '12px',
                                                    fontWeight: 600,
                                                    background: cell.getValue() === 'Active' ? '#dcfce7' : '#fee2e2',
                                                    color: cell.getValue() === 'Active' ? '#166534' : '#991b1b',
                                                }}>
                                                    {cell.getValue()}
                                                </span>
                                            ),
                                        },
                                    ]}
                                    data={tenants.filter((t) => t.status === 'Active')}
                                    onImpersonate={handleImpersonateTenant}
                                    emptyMessage="No active tenants to impersonate."
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
                </main>
            </div>
        </div>
    );
}
