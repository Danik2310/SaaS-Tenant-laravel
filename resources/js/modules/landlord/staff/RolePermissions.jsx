import React, { useEffect, useState } from 'react';
import { toast } from 'sonner';
import api from '../../../services/api';

export default function RolePermissions() {
    const [tab, setTab] = useState('roles');
    const [roles, setRoles] = useState([]);
    const [permissionsByModule, setPermissionsByModule] = useState({});
    const [loading, setLoading] = useState(true);
    const [showRoleForm, setShowRoleForm] = useState(false);
    const [showPermForm, setShowPermForm] = useState(false);
    const [editingRole, setEditingRole] = useState(null);
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
            const message = err.response?.data?.message || 'Failed to create role';
            toast.error(message);
        }
    };

    const handleUpdateRole = async (data) => {
        try {
            await api.put(`/admin/api/roles/${editingRole.id}`, data);
            toast.success('Role updated successfully');
            setEditingRole(null);
            loadData();
        } catch (err) {
            const message = err.response?.data?.message || 'Failed to update role';
            toast.error(message);
        }
    };

    const handleDeleteRole = async (id, name) => {
        if (!confirm(`Delete role "${name}"? This cannot be undone.`)) return;
        try {
            await api.delete(`/admin/api/roles/${id}`);
            toast.success('Role deleted');
            loadData();
        } catch (err) {
            const message = err.response?.data?.message || 'Failed to delete role';
            toast.error(message);
        }
    };

    const handleCreatePermission = async (data) => {
        try {
            await api.post('/admin/api/permissions', data);
            toast.success('Permission created successfully');
            setShowPermForm(false);
            loadData();
        } catch (err) {
            const message = err.response?.data?.message || 'Failed to create permission';
            toast.error(message);
        }
    };

    const handleUpdatePermission = async (data) => {
        try {
            await api.put(`/admin/api/permissions/${editingRole.id}`, data);
            toast.success('Permission updated successfully');
            setEditingRole(null);
            loadData();
        } catch (err) {
            const message = err.response?.data?.message || 'Failed to update permission';
            toast.error(message);
        }
    };

    const handleDeletePermission = async (id, name) => {
        if (!confirm(`Delete permission "${name}"? This cannot be undone.`)) return;
        try {
            await api.delete(`/admin/api/permissions/${id}`);
            toast.success('Permission deleted');
            loadData();
        } catch (err) {
            const message = err.response?.data?.message || 'Failed to delete permission';
            toast.error(message);
        }
    };

    if (loading) {
        return (
            <div style={{ background: 'white', padding: '30px', borderRadius: '8px', textAlign: 'center', color: '#666' }}>
                Loading...
            </div>
        );
    }

    return (
        <div style={{ background: 'white', borderRadius: '8px', overflow: 'hidden' }}>
            {error && (
                <div style={{ padding: '15px 20px', background: '#fee', color: '#c33', borderBottom: '1px solid #fcc' }}>
                    {error}
                </div>
            )}

            <div style={{
                padding: '20px',
                background: '#f8f9fa',
                borderBottom: '1px solid #dee2e6',
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center',
            }}>
                <div style={{ display: 'flex', gap: '4px' }}>
                    <button
                        onClick={() => setTab('roles')}
                        style={{
                            padding: '8px 20px',
                            background: tab === 'roles' ? '#27ae60' : '#e9ecef',
                            color: tab === 'roles' ? 'white' : '#333',
                            border: 'none',
                            borderRadius: '5px',
                            cursor: 'pointer',
                            fontSize: '14px',
                            fontWeight: tab === 'roles' ? '600' : '400',
                        }}
                    >
                        Roles ({roles.length})
                    </button>
                    <button
                        onClick={() => setTab('permissions')}
                        style={{
                            padding: '8px 20px',
                            background: tab === 'permissions' ? '#27ae60' : '#e9ecef',
                            color: tab === 'permissions' ? 'white' : '#333',
                            border: 'none',
                            borderRadius: '5px',
                            cursor: 'pointer',
                            fontSize: '14px',
                            fontWeight: tab === 'permissions' ? '600' : '400',
                        }}
                    >
                        Permissions ({Object.values(permissionsByModule).flat().length})
                    </button>
                </div>
                {!showRoleForm && !showPermForm && !editingRole && (
                    <button
                        onClick={() => {
                            if (tab === 'roles') setShowRoleForm(true);
                            else setShowPermForm(true);
                        }}
                        style={{
                            padding: '8px 16px',
                            background: '#27ae60',
                            color: 'white',
                            border: 'none',
                            borderRadius: '5px',
                            cursor: 'pointer',
                            fontSize: '14px',
                        }}
                    >
                        + Add {tab === 'roles' ? 'Role' : 'Permission'}
                    </button>
                )}
            </div>

            <div style={{ padding: '20px' }}>
                {tab === 'roles' && (
                    <>
                        {showRoleForm && (
                            <RoleForm
                                onSubmit={handleCreateRole}
                                onCancel={() => setShowRoleForm(false)}
                            />
                        )}
                        {editingRole && (
                            <RoleForm
                                role={editingRole}
                                onSubmit={handleUpdateRole}
                                onCancel={() => setEditingRole(null)}
                            />
                        )}
                        {!showRoleForm && !editingRole && (
                            roles.length === 0 ? (
                                <div style={{ textAlign: 'center', padding: '40px', color: '#999' }}>
                                    No roles found. Create one to get started.
                                </div>
                            ) : (
                                <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                                    <thead>
                                        <tr style={{ borderBottom: '2px solid #dee2e6', background: '#f8f9fa' }}>
                                            <th style={{ padding: '12px', textAlign: 'left', fontWeight: '600' }}>Name</th>
                                            <th style={{ padding: '12px', textAlign: 'left', fontWeight: '600' }}>Description</th>
                                            <th style={{ padding: '12px', textAlign: 'center', fontWeight: '600' }}>Permissions</th>
                                            <th style={{ padding: '12px', textAlign: 'center', fontWeight: '600' }}>Status</th>
                                            <th style={{ padding: '12px', textAlign: 'center', fontWeight: '600' }}>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {roles.map((role, i) => (
                                            <tr
                                                key={role.id}
                                                style={{
                                                    borderBottom: '1px solid #dee2e6',
                                                    background: i % 2 === 0 ? '#fff' : '#f9f9f9',
                                                }}
                                            >
                                                <td style={{ padding: '12px' }}><strong>{role.name}</strong></td>
                                                <td style={{ padding: '12px', color: '#666', fontSize: '13px' }}>{role.description}</td>
                                                <td style={{ padding: '12px', textAlign: 'center' }}>{role.permissions_count}</td>
                                                <td style={{ padding: '12px', textAlign: 'center' }}>
                                                    <span style={{
                                                        padding: '3px 8px',
                                                        borderRadius: '4px',
                                                        fontSize: '12px',
                                                        fontWeight: '600',
                                                        background: role.is_active ? '#e8f5e9' : '#ffebee',
                                                        color: role.is_active ? '#2e7d32' : '#c62828',
                                                    }}>
                                                        {role.is_active ? 'Active' : 'Inactive'}
                                                    </span>
                                                </td>
                                                <td style={{ padding: '12px', textAlign: 'center', whiteSpace: 'nowrap' }}>
                                                    <button
                                                        onClick={() => setEditingRole(role)}
                                                        style={{ padding: '4px 12px', background: '#f39c12', color: 'white', border: 'none', borderRadius: '4px', cursor: 'pointer', fontSize: '12px', marginRight: '6px' }}
                                                    >
                                                        Edit
                                                    </button>
                                                    <button
                                                        onClick={() => handleDeleteRole(role.id, role.name)}
                                                        style={{ padding: '4px 12px', background: '#e74c3c', color: 'white', border: 'none', borderRadius: '4px', cursor: 'pointer', fontSize: '12px' }}
                                                    >
                                                        Delete
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )
                        )}
                    </>
                )}

                {tab === 'permissions' && (
                    <>
                        {showPermForm && (
                            <PermissionForm
                                onSubmit={handleCreatePermission}
                                onCancel={() => setShowPermForm(false)}
                            />
                        )}
                        {editingRole && (
                            <PermissionForm
                                permission={editingRole}
                                onSubmit={handleUpdatePermission}
                                onCancel={() => setEditingRole(null)}
                            />
                        )}
                        {!showPermForm && !editingRole && (
                            Object.keys(permissionsByModule).length === 0 ? (
                                <div style={{ textAlign: 'center', padding: '40px', color: '#999' }}>
                                    No permissions found. Create one to get started.
                                </div>
                            ) : (
                                Object.entries(permissionsByModule).map(([module, perms]) => (
                                    <div key={module} style={{ marginBottom: '25px' }}>
                                        <h4 style={{
                                            margin: '0 0 10px',
                                            fontSize: '14px',
                                            fontWeight: '600',
                                            color: '#333',
                                            borderBottom: '2px solid #e9ecef',
                                            paddingBottom: '6px',
                                        }}>
                                            {module}
                                        </h4>
                                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: '8px' }}>
                                            {perms.map(perm => (
                                                <div
                                                    key={perm.id}
                                                    style={{
                                                        display: 'flex',
                                                        justifyContent: 'space-between',
                                                        alignItems: 'center',
                                                        padding: '10px 14px',
                                                        background: '#f8f9fa',
                                                        borderRadius: '6px',
                                                        border: '1px solid #e9ecef',
                                                    }}
                                                >
                                                    <div>
                                                        <div style={{ fontSize: '13px', fontWeight: '600' }}>{perm.name}</div>
                                                        {perm.description && (
                                                            <div style={{ fontSize: '11px', color: '#666', marginTop: '2px' }}>{perm.description}</div>
                                                        )}
                                                    </div>
                                                    <div style={{ display: 'flex', gap: '4px' }}>
                                                        <button
                                                            onClick={() => setEditingRole(perm)}
                                                            style={{ padding: '3px 10px', background: '#f39c12', color: 'white', border: 'none', borderRadius: '4px', cursor: 'pointer', fontSize: '11px' }}
                                                        >
                                                            Edit
                                                        </button>
                                                        <button
                                                            onClick={() => handleDeletePermission(perm.id, perm.name)}
                                                            style={{ padding: '3px 10px', background: '#e74c3c', color: 'white', border: 'none', borderRadius: '4px', cursor: 'pointer', fontSize: '11px' }}
                                                        >
                                                            Delete
                                                        </button>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ))
                            )
                        )}
                    </>
                )}
            </div>
        </div>
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
        <form onSubmit={handleSubmit} style={{ maxWidth: '500px', marginBottom: '20px' }}>
            <div style={{ marginBottom: '16px' }}>
                <label style={{ display: 'block', marginBottom: '6px', fontWeight: '600', fontSize: '14px' }}>Role Name *</label>
                <input
                    type="text"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    placeholder="e.g., editor"
                    required
                    style={{ width: '100%', padding: '10px', border: '1px solid #dee2e6', borderRadius: '4px', fontSize: '14px', boxSizing: 'border-box' }}
                />
            </div>
            <div style={{ marginBottom: '16px' }}>
                <label style={{ display: 'block', marginBottom: '6px', fontWeight: '600', fontSize: '14px' }}>Description</label>
                <input
                    type="text"
                    value={description}
                    onChange={(e) => setDescription(e.target.value)}
                    placeholder="What this role can do"
                    style={{ width: '100%', padding: '10px', border: '1px solid #dee2e6', borderRadius: '4px', fontSize: '14px', boxSizing: 'border-box' }}
                />
            </div>
            <div style={{ marginBottom: '16px' }}>
                <label style={{ display: 'flex', alignItems: 'center', cursor: 'pointer' }}>
                    <input
                        type="checkbox"
                        checked={isActive}
                        onChange={(e) => setIsActive(e.target.checked)}
                        style={{ marginRight: '8px' }}
                    />
                    Active
                </label>
            </div>
            <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-end' }}>
                <button type="button" onClick={onCancel} style={{ padding: '8px 16px', background: '#6c757d', color: 'white', border: 'none', borderRadius: '4px', cursor: 'pointer', fontSize: '13px' }}>Cancel</button>
                <button type="submit" disabled={loading} style={{ padding: '8px 16px', background: loading ? '#ccc' : '#27ae60', color: 'white', border: 'none', borderRadius: '4px', cursor: loading ? 'not-allowed' : 'pointer', fontSize: '13px' }}>
                    {loading ? 'Saving...' : (role ? 'Update' : 'Create')}
                </button>
            </div>
        </form>
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
        <form onSubmit={handleSubmit} style={{ maxWidth: '500px', marginBottom: '20px' }}>
            <div style={{ marginBottom: '16px' }}>
                <label style={{ display: 'block', marginBottom: '6px', fontWeight: '600', fontSize: '14px' }}>Permission Name *</label>
                <input
                    type="text"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    placeholder="e.g., manage orders"
                    required
                    style={{ width: '100%', padding: '10px', border: '1px solid #dee2e6', borderRadius: '4px', fontSize: '14px', boxSizing: 'border-box' }}
                />
            </div>
            <div style={{ marginBottom: '16px' }}>
                <label style={{ display: 'block', marginBottom: '6px', fontWeight: '600', fontSize: '14px' }}>Module *</label>
                <input
                    type="text"
                    value={module}
                    onChange={(e) => setModule(e.target.value)}
                    placeholder="e.g., orders"
                    required
                    style={{ width: '100%', padding: '10px', border: '1px solid #dee2e6', borderRadius: '4px', fontSize: '14px', boxSizing: 'border-box' }}
                />
            </div>
            <div style={{ marginBottom: '16px' }}>
                <label style={{ display: 'block', marginBottom: '6px', fontWeight: '600', fontSize: '14px' }}>Description</label>
                <input
                    type="text"
                    value={description}
                    onChange={(e) => setDescription(e.target.value)}
                    placeholder="What this permission allows"
                    style={{ width: '100%', padding: '10px', border: '1px solid #dee2e6', borderRadius: '4px', fontSize: '14px', boxSizing: 'border-box' }}
                />
            </div>
            <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-end' }}>
                <button type="button" onClick={onCancel} style={{ padding: '8px 16px', background: '#6c757d', color: 'white', border: 'none', borderRadius: '4px', cursor: 'pointer', fontSize: '13px' }}>Cancel</button>
                <button type="submit" disabled={loading} style={{ padding: '8px 16px', background: loading ? '#ccc' : '#27ae60', color: 'white', border: 'none', borderRadius: '4px', cursor: loading ? 'not-allowed' : 'pointer', fontSize: '13px' }}>
                    {loading ? 'Saving...' : (permission ? 'Update' : 'Create')}
                </button>
            </div>
        </form>
    );
}
