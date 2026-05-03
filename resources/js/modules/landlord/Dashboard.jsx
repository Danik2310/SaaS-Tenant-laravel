import React, { useEffect, useState } from 'react';
import api from '../../services/api';
import TenantList from './tenants/TenantList';
import TenantForm from './tenants/TenantForm';
import Navbar from '../../components/Navbar';
import Modal from '../../components/Modal';
import Staff from './staff/StaffList';
import Plans from './billing/Plans';
import BlockIcon from '@mui/icons-material/Block';
import LanguageIcon from '@mui/icons-material/Language';
import StorageIcon from '@mui/icons-material/Storage';
import SyncIcon from '@mui/icons-material/Sync';
import {
    Dialog,
    DialogTitle,
    DialogContent,
    DialogActions,
    IconButton,
    Avatar,
    Chip,
    Button,
    Typography,
    Box,
    Grid,
    Link
} from '@mui/material';
import { toast } from 'sonner';
import CloseIcon from '@mui/icons-material/Close';
import PersonIcon from '@mui/icons-material/Person';
import LaunchIcon from '@mui/icons-material/Launch';
import DatabaseModal from './modals/DatabaseModal';
import MigrationModal from './modals/MigrationModal';
import DomainModal from './modals/DomainModal';

export default function Dashboard({ user, setUser }) {
    const [tenants, setTenants] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showForm, setShowForm] = useState(false);
    const [editingTenant, setEditingTenant] = useState(null);
    const [view, setView] = useState('tenants');
    const [error, setError] = useState(null);

    // UI states for extra actions
    const [dbModalOpen, setDbModalOpen] = useState(false);
    const [dbInfo, setDbInfo] = useState(null);
    const [migrationModalOpen, setMigrationModalOpen] = useState(false);
    const [migrationOutput, setMigrationOutput] = useState('');
    const [domainModalOpen, setDomainModalOpen] = useState(false);
    const [domainInfo, setDomainInfo] = useState(null);

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
            await api.post('/admin/logout');
            window.location.href = '/admin/login';
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
        <div style={{ minHeight: '100vh', background: '#f5f5f5', fontFamily: 'sans-serif', display: 'flex' }}>
            <Navbar user={user} view={view} setView={setView} />
            <div style={{ flex: 1 }}>
                {/* Header */}
                <header
                    style={{
                        background: '#2c3e50',
                        color: 'white',
                        padding: '20px 30px',
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center',
                    }}
                >
                    <div>
                        <h1 style={{ margin: 0, fontSize: '24px' }}>SaaS Admin Dashboard</h1>
                        <p style={{ margin: '5px 0 0 0', fontSize: '14px', opacity: 0.9 }}>
                            Welcome, {user?.name}
                        </p>
                    </div>
                    <button
                        onClick={handleLogout}
                        style={{
                            padding: '10px 20px',
                            background: '#e74c3c',
                            color: 'white',
                            border: 'none',
                            borderRadius: '5px',
                            cursor: 'pointer',
                            fontSize: '14px',
                            fontWeight: '600',
                        }}
                    >
                        Logout
                    </button>
                </header>

                {/* Main Content */}
                <main style={{ padding: '30px' }}>
                    <div style={{ maxWidth: '1200px', margin: '0 auto' }}>
                    {error && (
                        <div
                            style={{
                                background: '#fee',
                                color: '#c33',
                                padding: '15px 20px',
                                borderRadius: '5px',
                                marginBottom: '20px',
                            }}
                        >
                            {error}
                        </div>
                    )}

                    {/* Controls */}
                    <div
                        style={{
                            display: 'flex',
                            justifyContent: 'space-between',
                            alignItems: 'center',
                            marginBottom: '30px',
                        }}
                    >
                        <h2 style={{ margin: 0, fontSize: '20px', color: '#333' }}>
                            {view === 'tenants' && 'Manage Tenants'}
                            {view === 'staff' && 'Staff Management'}
                            {view === 'plans' && 'Plans & Settings'}
                            {view === 'impersonate' && 'Impersonation (God Mode)'}
                        </h2>
                        {view === 'tenants' && (
                            <div style={{ display: 'flex', gap: '10px' }}>
                                <button
                                    onClick={() => setShowForm(!showForm)}
                                    style={{
                                        padding: '12px 25px',
                                        background: '#27ae60',
                                        color: 'white',
                                        border: 'none',
                                        borderRadius: '5px',
                                        cursor: 'pointer',
                                        fontSize: '14px',
                                        fontWeight: '600',
                                    }}
                                >
                                    {showForm ? 'Cancel' : '+ New Tenant'}
                                </button>
                            </div>
                        )}
                    </div>

                    {/* Tenant Management Tools */}
                    {view === 'tenants' && !showForm && !editingTenant && (
                        <div style={{
                            background: 'white',
                            padding: '20px',
                            borderRadius: '8px',
                            marginBottom: '20px',
                            border: '1px solid #e1e1e1'
                        }}>
                            <h3 style={{ margin: '0 0 15px 0', fontSize: '16px', color: '#333' }}>
                                🛠️ Tenant Management Tools
                            </h3>
                            <div style={{
                                display: 'grid',
                                gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
                                gap: '15px'
                            }}>
                                <div style={{
                                    padding: '15px',
                                    border: '1px solid #ddd',
                                    borderRadius: '6px',
                                    textAlign: 'center',
                                    background: '#f9f9f9'
                                }}>
                                    <BlockIcon style={{ fontSize: '24px', color: '#e74c3c', marginBottom: '8px' }} />
                                    <div style={{ fontSize: '14px', fontWeight: '600', color: '#333' }}>
                                        Suspend/Activate
                                    </div>
                                    <div style={{ fontSize: '12px', color: '#666', marginTop: '4px' }}>
                                        Toggle tenant active status (dynamic label)
                                    </div>
                                </div>
                                <div style={{
                                    padding: '15px',
                                    border: '1px solid #ddd',
                                    borderRadius: '6px',
                                    textAlign: 'center',
                                    background: '#f9f9f9'
                                }}>
                                    <LanguageIcon style={{ fontSize: '24px', color: '#3498db', marginBottom: '8px' }} />
                                    <div style={{ fontSize: '14px', fontWeight: '600', color: '#333' }}>
                                        Domain Management
                                    </div>
                                    <div style={{ fontSize: '12px', color: '#666', marginTop: '4px' }}>
                                        View and manage domains
                                    </div>
                                </div>
                                <div style={{
                                    padding: '15px',
                                    border: '1px solid #ddd',
                                    borderRadius: '6px',
                                    textAlign: 'center',
                                    background: '#f9f9f9'
                                }}>
                                    <StorageIcon style={{ fontSize: '24px', color: '#27ae60', marginBottom: '8px' }} />
                                    <div style={{ fontSize: '14px', fontWeight: '600', color: '#333' }}>
                                        Database Info
                                    </div>
                                    <div style={{ fontSize: '12px', color: '#666', marginTop: '4px' }}>
                                        View database credentials
                                    </div>
                                </div>
                                <div style={{
                                    padding: '15px',
                                    border: '1px solid #ddd',
                                    borderRadius: '6px',
                                    textAlign: 'center',
                                    background: '#f9f9f9'
                                }}>
                                    <SyncIcon style={{ fontSize: '24px', color: '#f39c12', marginBottom: '8px' }} />
                                    <div style={{ fontSize: '14px', fontWeight: '600', color: '#333' }}>
                                        Run Migrations
                                    </div>
                                    <div style={{ fontSize: '12px', color: '#666', marginTop: '4px' }}>
                                        Execute database migrations
                                    </div>
                                </div>
                            </div>
                            <div style={{
                                marginTop: '15px',
                                padding: '10px',
                                background: '#fff3cd',
                                border: '1px solid #ffeaa7',
                                borderRadius: '4px',
                                fontSize: '13px',
                                color: '#856404'
                            }}>
                                💡 <strong>Tip:</strong> Use the menu button (⋮) in each tenant row to access these management tools.
                            </div>
                        </div>
                    )}

                    {/* Forms and List */}
                    {view === 'staff' ? (
                        <Staff />
                    ) : view === 'plans' ? (
                        <Plans />
                    ) : view === 'impersonate' ? (
                        <div style={{ background: 'white', padding: '30px', borderRadius: '8px' }}>
                            <h3>Impersonation</h3>
                            <p>Select a tenant and click "Impersonate" to jump to their domain.</p>
                        </div>
                    ) : (editingTenant || showForm) ? (
                        <TenantForm
                            tenant={editingTenant}
                            onSubmit={editingTenant ? handleUpdateTenant : handleCreateTenant}
                            onCancel={() => {
                                setEditingTenant(null);
                                setShowForm(false);
                            }}
                        />
                    ) : (
                        <>
                            {loading ? (
                                <div style={{ textAlign: 'center', padding: '40px', color: '#666' }}>
                                    Loading tenants...
                                </div>
                            ) : (
                                <TenantList
                                    tenants={tenants}
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
                        </>
                    )}
                </div>

                {/* Extra modals */}
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
