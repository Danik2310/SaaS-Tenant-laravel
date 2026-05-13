import React, { useEffect, useState, useCallback, useMemo } from 'react';
import { toast } from 'sonner';
import api from '../../../services/api';
import DataTable from '@/components/DataTable';
import { FormCard, FormInput, ButtonPrimary, ButtonSecondary, FormActions } from '@/components/FormElements';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import Chip from '@mui/material/Chip';
import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Checkbox from '@mui/material/Checkbox';
import FormControlLabel from '@mui/material/FormControlLabel';
import Tooltip from '@mui/material/Tooltip';
import CircularProgress from '@mui/material/CircularProgress';

export default function RolePermissions() {
    const [tab, setTab] = useState('roles');
    const [roles, setRoles] = useState([]);
    const [permissionsByModule, setPermissionsByModule] = useState({});
    const [loading, setLoading] = useState(true);
    const [showRoleForm, setShowRoleForm] = useState(false);
    const [editingItem, setEditingItem] = useState(null);
    const [error, setError] = useState(null);

    useEffect(() => {
        loadData();
    }, []);

    const loadData = useCallback(async () => {
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
    }, []);

    const handleCreateRole = useCallback(async (data) => {
        try {
            const res = await api.post('/admin/api/roles', data);
            toast.success('Role created successfully');
            setShowRoleForm(false);
            setRoles(prev => [...prev, res.data.role]);
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to create role');
        }
    }, []);

    const handleUpdateRole = useCallback(async (data) => {
        try {
            const res = await api.put(`/admin/api/roles/${editingItem.id}`, data);
            toast.success('Role updated successfully');
            setEditingItem(null);
            setRoles(prev => prev.map(r => r.id === editingItem.id ? res.data.role : r));
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to update role');
        }
    }, [editingItem]);

    const handleDeleteRole = useCallback(async (row) => {
        if (!confirm(`Delete role "${row.name}"? This cannot be undone.`)) return;
        try {
            await api.delete(`/admin/api/roles/${row.id}`);
            toast.success('Role deleted');
            setRoles(prev => prev.filter(r => r.id !== row.id));
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to delete role');
        }
    }, []);

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
                    color={cell.getValue() ? 'success' : 'error'}
                />
            ),
        },
    ];

    const flatPermissions = useMemo(() =>
        Object.entries(permissionsByModule).flatMap(([module, perms]) =>
            perms.map((p) => ({ ...p, module }))
        ),
        [permissionsByModule]
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
                    color={cell.getValue() ? 'success' : 'error'}
                />
            ),
        },
    ];

    const handleSwitchTab = useCallback((newTab) => {
        setTab(newTab);
        if (newTab === 'permissions') {
            setShowRoleForm(false);
            setEditingItem(null);
        }
    }, []);

    if (loading) {
        return (
            <Box sx={{ textAlign: 'center', py: 5 }}>
                <CircularProgress size={32} />
                <Typography variant="body2" color="text.secondary" sx={{ mt: 1 }}>
                    Loading roles and permissions...
                </Typography>
            </Box>
        );
    }

    return (
        <>
            {error && (
                <Alert severity="error" sx={{ mb: 2 }}>
                    {error}
                </Alert>
            )}

            <Paper sx={{ p: 2, mb: 2 }}>
                <Stack direction="row" justifyContent="space-between" alignItems="center" flexWrap="wrap" spacing={1.5}>
                    <Stack direction="row" spacing={1}>
                        {!showRoleForm && !editingItem && (
                            <Button
                                size="small"
                                onClick={() => handleSwitchTab('roles')}
                                sx={{
                                    bgcolor: tab === 'roles' ? 'primary.main' : 'grey.100',
                                    color: tab === 'roles' ? 'common.white' : 'text.secondary',
                                    '&:hover': {
                                        bgcolor: tab === 'roles' ? 'primary.dark' : 'grey.200',
                                    },
                                    textTransform: 'none',
                                    fontWeight: tab === 'roles' ? 600 : 500,
                                }}
                            >
                                Roles ({roles.length})
                            </Button>
                        )}
                        <Button
                            size="small"
                            onClick={() => handleSwitchTab('permissions')}
                            sx={{
                                bgcolor: tab === 'permissions' ? 'primary.main' : 'grey.100',
                                color: tab === 'permissions' ? 'common.white' : 'text.secondary',
                                '&:hover': {
                                    bgcolor: tab === 'permissions' ? 'primary.dark' : 'grey.200',
                                },
                                textTransform: 'none',
                                fontWeight: tab === 'permissions' ? 600 : 500,
                            }}
                        >
                            Permissions ({flatPermissions.length})
                        </Button>
                    </Stack>
                    {tab === 'roles' && !showRoleForm && !editingItem && (
                        <Button
                            variant="contained"
                            color="success"
                            size="small"
                            onClick={() => setShowRoleForm(true)}
                            sx={{ textTransform: 'none', fontWeight: 600 }}
                        >
                            + Add Role
                        </Button>
                    )}
                </Stack>
            </Paper>

            {tab === 'roles' && (
                <>
                    {(showRoleForm || editingItem) && (
                        <RoleForm
                            role={editingItem}
                            permissionsByModule={permissionsByModule}
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

            {tab === 'permissions' && (
                <DataTable
                    columns={permColumns}
                    data={flatPermissions}
                    emptyMessage="No permissions found."
                />
            )}
        </>
    );
}

function RoleForm({ role = null, onSubmit, onCancel, permissionsByModule = {} }) {
    const [name, setName] = useState(role?.name || '');
    const [description, setDescription] = useState(role?.description || '');
    const [isActive, setIsActive] = useState(role?.is_active ?? true);
    const [selectedPermissionIds, setSelectedPermissionIds] = useState(
        () => role?.permission_ids || []
    );
    const [loading, setLoading] = useState(false);

    const togglePermission = (permId) => {
        setSelectedPermissionIds((prev) =>
            prev.includes(permId)
                ? prev.filter((id) => id !== permId)
                : [...prev, permId]
        );
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        try {
            await onSubmit({
                name,
                description,
                is_active: isActive,
                permissions: selectedPermissionIds,
            });
        } finally {
            setLoading(false);
        }
    };

    const moduleEntries = Object.entries(permissionsByModule);

    return (
        <FormCard
            title={role ? 'Edit Role' : 'Create Role'}
            subtitle="Define roles and their access level"
            onClose={onCancel}
        >
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
                <Box sx={{ mb: 2 }}>
                    <FormControlLabel
                        control={
                            <Checkbox
                                checked={isActive}
                                onChange={(e) => setIsActive(e.target.checked)}
                                size="small"
                            />
                        }
                        label="Active"
                    />
                </Box>

                <Box sx={{ mb: 2 }}>
                    <Typography variant="subtitle2" sx={{ fontWeight: 600, mb: 1 }}>
                        Permissions
                    </Typography>
                    {moduleEntries.length === 0 ? (
                        <Typography variant="body2" color="text.secondary" sx={{ fontStyle: 'italic' }}>
                            No permissions available. Create permissions first.
                        </Typography>
                    ) : (
                        moduleEntries.map(([module, permissions]) => (
                            <Box key={module} sx={{ mb: 2 }}>
                                <Typography
                                    variant="caption"
                                    sx={{
                                        display: 'block',
                                        fontWeight: 700,
                                        textTransform: 'uppercase',
                                        color: 'text.secondary',
                                        mb: 0.5,
                                        fontSize: '0.75rem',
                                        letterSpacing: '0.05em',
                                    }}
                                >
                                    {module}
                                </Typography>
                                <Box sx={{ pl: 1 }}>
                                    {permissions.length === 0 ? (
                                        <Typography variant="caption" color="text.disabled">
                                            No permissions in this module
                                        </Typography>
                                    ) : (
                                        permissions.map((perm) => (
                                            <FormControlLabel
                                                key={perm.id}
                                                control={
                                                    <Checkbox
                                                        size="small"
                                                        checked={selectedPermissionIds.includes(perm.id)}
                                                        onChange={() => togglePermission(perm.id)}
                                                    />
                                                }
                                                label={
                                                    perm.description ? (
                                                        <Tooltip title={perm.description} arrow placement="right">
                                                            <Typography variant="body2" sx={{ cursor: 'help' }}>
                                                                {perm.name}
                                                            </Typography>
                                                        </Tooltip>
                                                    ) : (
                                                        <Typography variant="body2">{perm.name}</Typography>
                                                    )
                                                }
                                                sx={{ mx: 0, width: '100%' }}
                                            />
                                        ))
                                    )}
                                </Box>
                            </Box>
                        ))
                    )}
                </Box>

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
