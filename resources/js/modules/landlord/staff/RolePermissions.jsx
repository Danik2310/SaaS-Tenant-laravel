import React, { useEffect, useState } from 'react';
import { toast } from 'sonner';
import api from '../../../services/api';
import DataTable from '@/components/DataTable';
import { FormCard, FormInput, ButtonPrimary, ButtonSecondary, FormActions, CheckboxInput } from '@/components/FormElements';
import { Chip } from '@mui/material';

export default function RolePermissions() {
    const [tab, setTab] = useState('roles');
    const [roles, setRoles] = useState([]);
    const [permissionsByModule, setPermissionsByModule] = useState({});
    const [loading, setLoading] = useState(true);
    const [showRoleForm, setShowRoleForm] = useState(false);
    const [showPermForm, setShowPermForm] = useState(false);
    const [editingItem, setEditingItem] = useState(null);
    const [error, setError] = useState(null);

    useEffect(() => {
        loadData();
    }, []);

    const loadData = async () => {
        setLoading(true);
        try {
            const [rolesRes, permsRes] = await Promise.all([
                api.get('/admin/api/roles'),
                api.get('/admin/api/permissions'),
            ]);
            setRoles(rolesRes.data.roles || []);
            setPermissionsByModule(permsRes.data.permissions || {});
        } catch (err) {
            const message = 'Failed to load roles and permissions';
            toast.error(message);
            setError(message);
        } finally {
            setLoading(false);
        }
    };

    const handleCreateRole = async (data) => {
        try {
            await api.post('/admin/api/roles', data);
            toast.success('Role created successfully');
            setShowRoleForm(false);
            loadData();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to create role');
        }
    };

    const handleUpdateRole = async (data) => {
        try {
            await api.put(`/admin/api/roles/${editingItem.id}`, data);
            toast.success('Role updated successfully');
            setEditingItem(null);
            loadData();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to update role');
        }
    };

    const handleDeleteRole = async (row) => {
        if (!confirm(`Delete role "${row.name}"? This cannot be undone.`)) return;
        try {
            await api.delete(`/admin/api/roles/${row.id}`);
            toast.success('Role deleted');
            loadData();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to delete role');
        }
    };

    const handleCreatePermission = async (data) => {
        try {
            await api.post('/admin/api/permissions', data);
            toast.success('Permission created successfully');
            setShowPermForm(false);
            loadData();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to create permission');
        }
    };

    const handleUpdatePermission = async (data) => {
        try {
            await api.put(`/admin/api/permissions/${editingItem.id}`, data);
            toast.success('Permission updated successfully');
            setEditingItem(null);
            loadData();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to update permission');
        }
    };

    const handleDeletePermission = async (row) => {
        if (!confirm(`Delete permission "${row.name}"? This cannot be undone.`)) return;
        try {
            await api.delete(`/admin/api/permissions/${row.id}`);
            toast.success('Permission deleted');
            loadData();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to delete permission');
        }
    };

    const roleColumns = [
        { accessorKey: 'name', header: 'Name' },
        { accessorKey: 'description', header: 'Description' },
        { accessorKey: 'permissions_count', header: 'Permissions' },
        {
            accessorKey: 'is_active',
            header: 'Status',
            Cell: ({ cell }) => (
                <Chip
                    label={cell.getValue() ? 'Active' : 'Inactive'}
                    size="small"
                    sx={{
                        bgcolor: cell.getValue() ? '#dcfce7' : '#fee2e2',
                        color: cell.getValue() ? '#166534' : '#991b1b',
                        fontWeight: 600,
                    }}
                />
            ),
        },
    ];

    const flatPermissions = Object.entries(permissionsByModule).flatMap(([module, perms]) =>
        perms.map((p) => ({ ...p, module }))
    );

    const permColumns = [
        { accessorKey: 'name', header: 'Permission' },
        { accessorKey: 'module', header: 'Module' },
        {
            accessorKey: 'is_active',
            header: 'Status',
            Cell: ({ cell }) => (
                <Chip
                    label={cell.getValue() ? 'Active' : 'Inactive'}
                    size="small"
                    sx={{
                        bgcolor: cell.getValue() ? '#dcfce7' : '#fee2e2',
                        color: cell.getValue() ? '#166534' : '#991b1b',
                        fontWeight: 600,
                    }}
                />
            ),
        },
    ];

    return (
        <>
            {error && (
                <div style={{ background: '#fef2f2', color: '#dc2626', padding: '12px 16px', borderRadius: '6px', marginBottom: '16px', border: '1px solid #fecaca' }}>
                    {error}
                </div>
            )}

            <div style={{ background: 'white', padding: '16px', borderRadius: '8px', marginBottom: '16px', boxShadow: '0 1px 3px rgba(0,0,0,0.06)' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '12px' }}>
                    <div style={{ display: 'flex', gap: '8px' }}>
                        <button
                            onClick={() => setTab('roles')}
                            style={{
                                padding: '8px 16px',
                                background: tab === 'roles' ? '#3b82f6' : '#f1f5f9',
                                color: tab === 'roles' ? 'white' : '#475569',
                                border: 'none',
                                borderRadius: '6px',
                                cursor: 'pointer',
                                fontSize: '13px',
                                fontWeight: tab === 'roles' ? 600 : 500,
                            }}
                        >
                            Roles ({roles.length})
                        </button>
                        <button
                            onClick={() => setTab('permissions')}
                            style={{
                                padding: '8px 16px',
                                background: tab === 'permissions' ? '#3b82f6' : '#f1f5f9',
                                color: tab === 'permissions' ? 'white' : '#475569',
                                border: 'none',
                                borderRadius: '6px',
                                cursor: 'pointer',
                                fontSize: '13px',
                                fontWeight: tab === 'permissions' ? 600 : 500,
                            }}
                        >
                            Permissions ({flatPermissions.length})
                        </button>
                    </div>
                    {!showRoleForm && !showPermForm && !editingItem && (
                        <button
                            onClick={() => {
                                if (tab === 'roles') setShowRoleForm(true);
                                else setShowPermForm(true);
                            }}
                            style={{
                                padding: '8px 16px',
                                background: '#22c55e',
                                color: 'white',
                                border: 'none',
                                borderRadius: '6px',
                                cursor: 'pointer',
                                fontSize: '13px',
                                fontWeight: 600,
                            }}
                        >
                            + Add {tab === 'roles' ? 'Role' : 'Permission'}
                        </button>
                    )}
                </div>
            </div>

            {loading && (
                <div style={{ textAlign: 'center', padding: '40px', color: '#94a3b8' }}>Loading...</div>
            )}

            {!loading && tab === 'roles' && (
                <>
                    {(showRoleForm || editingItem) && (
                        <RoleForm
                            role={editingItem}
                            onSubmit={editingItem ? handleUpdateRole : handleCreateRole}
                            onCancel={() => { setEditingItem(null); setShowRoleForm(false); }}
                        />
                    )}
                    {!showRoleForm && !editingItem && (
                        <DataTable
                            columns={roleColumns}
                            data={roles}
                            onEdit={(row) => setEditingItem(row)}
                            onDelete={handleDeleteRole}
                            emptyMessage="No roles found. Create one to get started."
                        />
                    )}
                </>
            )}

            {!loading && tab === 'permissions' && (
                <>
                    {(showPermForm || editingItem) && (
                        <PermissionForm
                            permission={editingItem}
                            onSubmit={editingItem ? handleUpdatePermission : handleCreatePermission}
                            onCancel={() => { setEditingItem(null); setShowPermForm(false); }}
                        />
                    )}
                    {!showPermForm && !editingItem && (
                        <DataTable
                            columns={permColumns}
                            data={flatPermissions}
                            onEdit={(row) => setEditingItem(row)}
                            onDelete={handleDeletePermission}
                            emptyMessage="No permissions found. Create one to get started."
                        />
                    )}
                </>
            )}
        </>
    );
}

function RoleForm({ role = null, onSubmit, onCancel }) {
    const [name, setName] = useState(role?.name || '');
    const [description, setDescription] = useState(role?.description || '');
    const [isActive, setIsActive] = useState(role?.is_active ?? true);
    const [loading, setLoading] = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        try {
            await onSubmit({ name, description, is_active: isActive });
        } finally {
            setLoading(false);
        }
    };

    return (
        <FormCard title={role ? 'Edit Role' : 'Create Role'} subtitle="Define roles and their access level" onClose={onCancel}>
            <form onSubmit={handleSubmit}>
                <FormInput label="Role Name" required>
                    <input
                        type="text"
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        placeholder="e.g., editor"
                        required
                    />
                </FormInput>
                <FormInput label="Description">
                    <input
                        type="text"
                        value={description}
                        onChange={(e) => setDescription(e.target.value)}
                        placeholder="What this role can do"
                    />
                </FormInput>
                <div style={{ marginBottom: '16px' }}>
                    <CheckboxInput
                        label="Active"
                        checked={isActive}
                        onChange={(e) => setIsActive(e.target.checked)}
                    />
                </div>
                <FormActions>
                    <ButtonSecondary onClick={onCancel}>Cancel</ButtonSecondary>
                    <ButtonPrimary type="submit" disabled={loading}>
                        {loading ? 'Saving...' : (role ? 'Update' : 'Create')}
                    </ButtonPrimary>
                </FormActions>
            </form>
        </FormCard>
    );
}

function PermissionForm({ permission = null, onSubmit, onCancel }) {
    const [name, setName] = useState(permission?.name || '');
    const [description, setDescription] = useState(permission?.description || '');
    const [module, setModule] = useState(permission?.module || '');
    const [loading, setLoading] = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        try {
            await onSubmit({ name, description, module });
        } finally {
            setLoading(false);
        }
    };

    return (
        <FormCard title={permission ? 'Edit Permission' : 'Create Permission'} subtitle="Define a granular permission" onClose={onCancel}>
            <form onSubmit={handleSubmit}>
                <FormInput label="Permission Name" required>
                    <input
                        type="text"
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        placeholder="e.g., manage orders"
                        required
                    />
                </FormInput>
                <FormInput label="Module" required hint="e.g., orders, tenants, staff">
                    <input
                        type="text"
                        value={module}
                        onChange={(e) => setModule(e.target.value)}
                        placeholder="e.g., orders"
                        required
                    />
                </FormInput>
                <FormInput label="Description">
                    <input
                        type="text"
                        value={description}
                        onChange={(e) => setDescription(e.target.value)}
                        placeholder="What this permission allows"
                    />
                </FormInput>
                <FormActions>
                    <ButtonSecondary onClick={onCancel}>Cancel</ButtonSecondary>
                    <ButtonPrimary type="submit" disabled={loading}>
                        {loading ? 'Saving...' : (permission ? 'Update' : 'Create')}
                    </ButtonPrimary>
                </FormActions>
            </form>
        </FormCard>
    );
}
