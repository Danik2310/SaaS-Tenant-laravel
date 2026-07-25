import React, { useEffect, useState, useCallback, useMemo } from 'react';
import { MaterialReactTable } from 'material-react-table';
import { toast } from 'sonner';
import api from '../../../services/api';
import { FormInput, ButtonPrimary, ButtonSecondary, FormActions, CheckboxInput } from '@/Components/FormElements';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import Chip from '@mui/material/Chip';
import Button from '@mui/material/Button';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Checkbox from '@mui/material/Checkbox';
import FormControlLabel from '@mui/material/FormControlLabel';
import Tooltip from '@mui/material/Tooltip';
import CircularProgress from '@mui/material/CircularProgress';
import Dialog from '@mui/material/Dialog';
import DialogContent from '@mui/material/DialogContent';
import DialogTitle from '@mui/material/DialogTitle';
import IconButton from '@mui/material/IconButton';
import InboxIcon from '@mui/icons-material/Inbox';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import CloseIcon from '@mui/icons-material/Close';
import ConfirmDialog from '@/Components/ConfirmDialog';

export default function RolePermissions() {
    const [tab, setTab] = useState('roles');

    const [roles, setRoles] = useState([]);
    const [rolesLoading, setRolesLoading] = useState(true);
    const [rolesTotal, setRolesTotal] = useState(0);
    const [rolesError, setRolesError] = useState(null);
    const [rolesPagination, setRolesPagination] = useState({ pageIndex: 0, pageSize: 5 });
    const [rolesGlobalFilter, setRolesGlobalFilter] = useState('');
    const [rolesColumnFilters, setRolesColumnFilters] = useState([]);
    const [rolesSorting, setRolesSorting] = useState([]);

    const [permissions, setPermissions] = useState([]);
    const [permissionsLoading, setPermissionsLoading] = useState(true);
    const [permissionsTotal, setPermissionsTotal] = useState(0);
    const [permissionsError, setPermissionsError] = useState(null);
    const [permissionsPagination, setPermissionsPagination] = useState({ pageIndex: 0, pageSize: 5 });
    const [permissionsGlobalFilter, setPermissionsGlobalFilter] = useState('');
    const [permissionsColumnFilters, setPermissionsColumnFilters] = useState([]);
    const [permissionsSorting, setPermissionsSorting] = useState([]);

    const [showRoleDialog, setShowRoleDialog] = useState(false);
    const [editingRole, setEditingRole] = useState(null);
    const [deleteTargetRole, setDeleteTargetRole] = useState(null);

    const [showPermissionDialog, setShowPermissionDialog] = useState(false);
    const [editingPermission, setEditingPermission] = useState(null);
    const [deleteTargetPermission, setDeleteTargetPermission] = useState(null);

    const fetchRoles = useCallback(async () => {
        setRolesLoading(true);
        setRolesError(null);
        try {
            const params = new URLSearchParams();
            params.set('page', rolesPagination.pageIndex + 1);
            params.set('per_page', rolesPagination.pageSize);

            if (rolesGlobalFilter) params.set('search', rolesGlobalFilter);

            rolesColumnFilters.forEach(f => {
                if (f.id === 'is_active' && f.value !== undefined && f.value !== null) {
                    params.set('is_active', f.value);
                }
            });

            const sortMapping = { 'name': 'name', 'description': 'description', 'is_active': 'is_active', 'created_at': 'created_at' };
            if (rolesSorting.length > 0) {
                const sortField = sortMapping[rolesSorting[0].id] || 'name';
                params.set('sort', sortField);
                params.set('order', rolesSorting[0].desc ? 'desc' : 'asc');
            }

            const response = await api.get(`/admin/api/roles?${params}`);
            setRoles(response.data.roles);
            setRolesTotal(response.data.total);
        } catch (err) {
            const message = 'Failed to load roles';
            toast.error(message);
            setRolesError(message);
        } finally {
            setRolesLoading(false);
        }
    }, [rolesPagination, rolesGlobalFilter, rolesColumnFilters, rolesSorting]);

    const fetchPermissions = useCallback(async () => {
        setPermissionsLoading(true);
        setPermissionsError(null);
        try {
            const params = new URLSearchParams();
            params.set('page', permissionsPagination.pageIndex + 1);
            params.set('per_page', permissionsPagination.pageSize);

            if (permissionsGlobalFilter) params.set('search', permissionsGlobalFilter);

            permissionsColumnFilters.forEach(f => {
                if (f.id === 'is_active' && f.value !== undefined && f.value !== null) {
                    params.set('is_active', f.value);
                }
                if (f.id === 'module' && f.value !== undefined && f.value !== null) {
                    params.set('module', f.value);
                }
            });

            const sortMapping = { 'name': 'name', 'module': 'module', 'is_active': 'is_active', 'created_at': 'created_at' };
            if (permissionsSorting.length > 0) {
                const sortField = sortMapping[permissionsSorting[0].id] || 'module';
                params.set('sort', sortField);
                params.set('order', permissionsSorting[0].desc ? 'desc' : 'asc');
            }

            const response = await api.get(`/admin/api/permissions?${params}`);
            setPermissions(response.data.permissions);
            setPermissionsTotal(response.data.total);
        } catch (err) {
            const message = 'Failed to load permissions';
            toast.error(message);
            setPermissionsError(message);
        } finally {
            setPermissionsLoading(false);
        }
    }, [permissionsPagination, permissionsGlobalFilter, permissionsColumnFilters, permissionsSorting]);

    useEffect(() => {
        if (tab === 'roles') fetchRoles();
    }, [tab, fetchRoles]);

    useEffect(() => {
        if (tab === 'permissions') fetchPermissions();
    }, [tab, fetchPermissions]);

    const handleCreateRole = async (data) => {
        try {
            await api.post('/admin/api/roles', data);
            toast.success('Role created successfully');
            setShowRoleDialog(false);
            fetchRoles();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to create role');
        }
    };

    const handleUpdateRole = async (data) => {
        try {
            await api.put(`/admin/api/roles/${editingRole.id}`, data);
            toast.success('Role updated successfully');
            setEditingRole(null);
            fetchRoles();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to update role');
        }
    };

    const confirmDeleteRole = async () => {
        const row = deleteTargetRole;
        setDeleteTargetRole(null);
        try {
            await api.delete(`/admin/api/roles/${row.id}`);
            toast.success('Role deleted');
            fetchRoles();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to delete role');
        }
    };

    const handleCreatePermission = async (data) => {
        try {
            await api.post('/admin/api/permissions', data);
            toast.success('Permission created successfully');
            setShowPermissionDialog(false);
            fetchPermissions();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to create permission');
        }
    };

    const handleUpdatePermission = async (data) => {
        try {
            await api.put(`/admin/api/permissions/${editingPermission.id}`, data);
            toast.success('Permission updated successfully');
            setEditingPermission(null);
            fetchPermissions();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to update permission');
        }
    };

    const confirmDeletePermission = async () => {
        const row = deleteTargetPermission;
        setDeleteTargetPermission(null);
        try {
            await api.delete(`/admin/api/permissions/${row.id}`);
            toast.success('Permission deleted');
            fetchPermissions();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to delete permission');
        }
    };

    const uniqueModules = useMemo(() => {
        const modules = [...new Set(permissions.map(p => p.module).filter(Boolean))];
        return modules.sort();
    }, [permissions]);

    const roleColumns = useMemo(() => [
        { accessorKey: 'name', header: 'Name', enableColumnFilter: false },
        { accessorKey: 'description', header: 'Description', enableColumnFilter: false, enableSorting: false },
        {
            accessorKey: 'permissions_count',
            header: 'Permissions',
            enableColumnFilter: false,
            enableSorting: false,
            Cell: ({ cell }) => (
                <Typography variant="body2" sx={{ fontSize: 13, color: '#64748b' }}>
                    {cell.getValue()} permissions
                </Typography>
            ),
        },
        {
            accessorKey: 'is_active',
            header: 'Status',
            filterVariant: 'select',
            filterSelectOptions: [
                { text: 'Active', value: 'true' },
                { text: 'Inactive', value: 'false' },
            ],
            Cell: ({ cell }) => {
                const isActive = cell.getValue();
                return (
                    <Tooltip title={isActive ? 'Role is active' : 'Role is inactive'}>
                        <Chip
                            label={isActive ? 'Active' : 'Inactive'}
                            size="small"
                            sx={{
                                bgcolor: isActive ? '#dcfce7' : '#fee2e2',
                                color: isActive ? '#166534' : '#991b1b',
                                fontWeight: 600,
                            }}
                        />
                    </Tooltip>
                );
            },
        },
        {
            id: 'actions',
            header: 'Actions',
            enableColumnFilter: false,
            enableSorting: false,
            enableGlobalFilter: false,
            size: 80,
            Cell: ({ row }) => (
                <Box sx={{ display: 'flex', gap: '0.25rem', alignItems: 'center' }}>
                    <Tooltip title="Edit">
                        <Box
                            component="button"
                            onClick={(e) => { e.stopPropagation(); setEditingRole(row.original); }}
                            sx={{
                                display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                                border: 'none', bgcolor: 'transparent', cursor: 'pointer',
                                p: 0.5, borderRadius: 1, color: 'text.secondary',
                                '&:hover': { bgcolor: 'action.hover' },
                            }}
                        >
                            <EditIcon fontSize="small" />
                        </Box>
                    </Tooltip>
                    <Tooltip title="Delete">
                        <Box
                            component="button"
                            onClick={(e) => { e.stopPropagation(); setDeleteTargetRole(row.original); }}
                            sx={{
                                display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                                border: 'none', bgcolor: 'transparent', cursor: 'pointer',
                                p: 0.5, borderRadius: 1, color: 'error.main',
                                '&:hover': { bgcolor: 'action.hover' },
                            }}
                        >
                            <DeleteIcon fontSize="small" />
                        </Box>
                    </Tooltip>
                </Box>
            ),
        },
    ], []);

    const permColumns = useMemo(() => [
        { accessorKey: 'name', header: 'Permission', enableColumnFilter: false },
        {
            accessorKey: 'module',
            header: 'Module',
            filterVariant: 'select',
            filterSelectOptions: uniqueModules.map(m => ({ text: m, value: m })),
        },
        {
            accessorKey: 'is_active',
            header: 'Status',
            filterVariant: 'select',
            filterSelectOptions: [
                { text: 'Active', value: 'true' },
                { text: 'Inactive', value: 'false' },
            ],
            Cell: ({ cell }) => {
                const isActive = cell.getValue();
                return (
                    <Tooltip title={isActive ? 'Permission is active' : 'Permission is inactive'}>
                        <Chip
                            label={isActive ? 'Active' : 'Inactive'}
                            size="small"
                            sx={{
                                bgcolor: isActive ? '#dcfce7' : '#fee2e2',
                                color: isActive ? '#166534' : '#991b1b',
                                fontWeight: 600,
                            }}
                        />
                    </Tooltip>
                );
            },
        },
        {
            id: 'actions',
            header: 'Actions',
            enableColumnFilter: false,
            enableSorting: false,
            enableGlobalFilter: false,
            size: 80,
            Cell: ({ row }) => (
                <Box sx={{ display: 'flex', gap: '0.25rem', alignItems: 'center' }}>
                    <Tooltip title="Edit">
                        <Box
                            component="button"
                            onClick={(e) => { e.stopPropagation(); setEditingPermission(row.original); }}
                            sx={{
                                display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                                border: 'none', bgcolor: 'transparent', cursor: 'pointer',
                                p: 0.5, borderRadius: 1, color: 'text.secondary',
                                '&:hover': { bgcolor: 'action.hover' },
                            }}
                        >
                            <EditIcon fontSize="small" />
                        </Box>
                    </Tooltip>
                    <Tooltip title="Delete">
                        <Box
                            component="button"
                            onClick={(e) => { e.stopPropagation(); setDeleteTargetPermission(row.original); }}
                            sx={{
                                display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                                border: 'none', bgcolor: 'transparent', cursor: 'pointer',
                                p: 0.5, borderRadius: 1, color: 'error.main',
                                '&:hover': { bgcolor: 'action.hover' },
                            }}
                        >
                            <DeleteIcon fontSize="small" />
                        </Box>
                    </Tooltip>
                </Box>
            ),
        },
    ], [uniqueModules]);

    return (
        <>
            <Paper sx={{ p: 2, mb: 2 }}>
                <Stack direction="row" justifyContent="space-between" alignItems="center" flexWrap="wrap" spacing={1.5}>
                    <Stack direction="row" spacing={1}>
                        <Button
                            size="small"
                            onClick={() => setTab('roles')}
                            sx={{
                                bgcolor: tab === 'roles' ? 'primary.main' : 'grey.100',
                                color: tab === 'roles' ? 'common.white' : 'text.secondary',
                                '&:hover': { bgcolor: tab === 'roles' ? 'primary.dark' : 'grey.200' },
                                textTransform: 'none',
                                fontWeight: tab === 'roles' ? 600 : 500,
                            }}
                        >
                            Roles
                        </Button>
                        <Button
                            size="small"
                            onClick={() => setTab('permissions')}
                            sx={{
                                bgcolor: tab === 'permissions' ? 'primary.main' : 'grey.100',
                                color: tab === 'permissions' ? 'common.white' : 'text.secondary',
                                '&:hover': { bgcolor: tab === 'permissions' ? 'primary.dark' : 'grey.200' },
                                textTransform: 'none',
                                fontWeight: tab === 'permissions' ? 600 : 500,
                            }}
                        >
                            Permissions
                        </Button>
                    </Stack>
                    <Button
                        variant="contained"
                        color="success"
                        size="small"
                        onClick={() => tab === 'roles' ? setShowRoleDialog(true) : setShowPermissionDialog(true)}
                        sx={{ textTransform: 'none', fontWeight: 600 }}
                    >
                        + {tab === 'roles' ? 'Add Role' : 'Add Permission'}
                    </Button>
                </Stack>
            </Paper>

            {tab === 'roles' && (
                rolesError ? (
                    <Box sx={{ p: 2, textAlign: 'center' }}>
                        <Typography color="error">{rolesError}</Typography>
                        <Button variant="outlined" size="small" sx={{ mt: 1 }} onClick={fetchRoles}>Retry</Button>
                    </Box>
                ) : (
                    <MaterialReactTable
                        columns={roleColumns}
                        data={roles}
                        rowCount={rolesTotal}
                        state={{
                            isLoading: rolesLoading,
                            pagination: rolesPagination,
                            globalFilter: rolesGlobalFilter,
                            columnFilters: rolesColumnFilters,
                            sorting: rolesSorting,
                        }}
                        onPaginationChange={setRolesPagination}
                        onGlobalFilterChange={setRolesGlobalFilter}
                        onColumnFiltersChange={setRolesColumnFilters}
                        onSortingChange={setRolesSorting}
                        enableGlobalFilter
                        enableColumnFilters
                        enableSorting
                        manualFiltering
                        manualPagination
                        manualSorting
                        positionGlobalFilter="left"
                        renderEmptyRowsFallback={() => (
                            <Box sx={{ textAlign: 'center', py: 6 }}>
                                <InboxIcon sx={{ fontSize: 48, color: 'grey.300', mb: 1 }} />
                                <Typography variant="body1" sx={{ fontWeight: 600, color: 'text.secondary' }}>
                                    No roles found. Create one to get started.
                                </Typography>
                            </Box>
                        )}
                        muiTablePaperProps={{ elevation: 2, sx: { borderRadius: 2 } }}
                        muiTableHeadCellProps={{ sx: { fontWeight: 600, fontSize: '12px', color: 'text.secondary', textTransform: 'uppercase', letterSpacing: '0.05em' } }}
                        initialState={{ density: 'compact' }}
                        localization={{ toolbarSearchPlaceholder: 'Search roles...' }}
                    />
                )
            )}

            {tab === 'permissions' && (
                permissionsError ? (
                    <Box sx={{ p: 2, textAlign: 'center' }}>
                        <Typography color="error">{permissionsError}</Typography>
                        <Button variant="outlined" size="small" sx={{ mt: 1 }} onClick={fetchPermissions}>Retry</Button>
                    </Box>
                ) : (
                    <MaterialReactTable
                        columns={permColumns}
                        data={permissions}
                        rowCount={permissionsTotal}
                        state={{
                            isLoading: permissionsLoading,
                            pagination: permissionsPagination,
                            globalFilter: permissionsGlobalFilter,
                            columnFilters: permissionsColumnFilters,
                            sorting: permissionsSorting,
                        }}
                        onPaginationChange={setPermissionsPagination}
                        onGlobalFilterChange={setPermissionsGlobalFilter}
                        onColumnFiltersChange={setPermissionsColumnFilters}
                        onSortingChange={setPermissionsSorting}
                        enableGlobalFilter
                        enableColumnFilters
                        enableSorting
                        manualFiltering
                        manualPagination
                        manualSorting
                        positionGlobalFilter="left"
                        renderEmptyRowsFallback={() => (
                            <Box sx={{ textAlign: 'center', py: 6 }}>
                                <InboxIcon sx={{ fontSize: 48, color: 'grey.300', mb: 1 }} />
                                <Typography variant="body1" sx={{ fontWeight: 600, color: 'text.secondary' }}>
                                    No permissions found. Create one to get started.
                                </Typography>
                            </Box>
                        )}
                        muiTablePaperProps={{ elevation: 2, sx: { borderRadius: 2 } }}
                        muiTableHeadCellProps={{ sx: { fontWeight: 600, fontSize: '12px', color: 'text.secondary', textTransform: 'uppercase', letterSpacing: '0.05em' } }}
                        initialState={{ density: 'compact' }}
                        localization={{ toolbarSearchPlaceholder: 'Search permissions...' }}
                    />
                )
            )}

            {/* Create Role Dialog */}
            <Dialog open={showRoleDialog} onClose={() => setShowRoleDialog(false)} maxWidth="sm" fullWidth>
                <DialogTitle sx={{ m: 0, p: 2, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    Create Role
                    <IconButton onClick={() => setShowRoleDialog(false)} size="small"><CloseIcon fontSize="small" /></IconButton>
                </DialogTitle>
                <DialogContent sx={{ p: 2 }}>
                    <RoleForm onSubmit={handleCreateRole} onCancel={() => setShowRoleDialog(false)} embedded />
                </DialogContent>
            </Dialog>

            {/* Edit Role Dialog */}
            <Dialog open={!!editingRole} onClose={() => setEditingRole(null)} maxWidth="sm" fullWidth>
                <DialogTitle sx={{ m: 0, p: 2, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    Edit Role
                    <IconButton onClick={() => setEditingRole(null)} size="small"><CloseIcon fontSize="small" /></IconButton>
                </DialogTitle>
                <DialogContent sx={{ p: 2 }}>
                    {editingRole && (
                        <RoleForm role={editingRole} onSubmit={handleUpdateRole} onCancel={() => setEditingRole(null)} embedded />
                    )}
                </DialogContent>
            </Dialog>

            {/* Create Permission Dialog */}
            <Dialog open={showPermissionDialog} onClose={() => setShowPermissionDialog(false)} maxWidth="sm" fullWidth>
                <DialogTitle sx={{ m: 0, p: 2, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    Create Permission
                    <IconButton onClick={() => setShowPermissionDialog(false)} size="small"><CloseIcon fontSize="small" /></IconButton>
                </DialogTitle>
                <DialogContent sx={{ p: 2 }}>
                    <PermissionForm onSubmit={handleCreatePermission} onCancel={() => setShowPermissionDialog(false)} embedded />
                </DialogContent>
            </Dialog>

            {/* Edit Permission Dialog */}
            <Dialog open={!!editingPermission} onClose={() => setEditingPermission(null)} maxWidth="sm" fullWidth>
                <DialogTitle sx={{ m: 0, p: 2, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    Edit Permission
                    <IconButton onClick={() => setEditingPermission(null)} size="small"><CloseIcon fontSize="small" /></IconButton>
                </DialogTitle>
                <DialogContent sx={{ p: 2 }}>
                    {editingPermission && (
                        <PermissionForm permission={editingPermission} onSubmit={handleUpdatePermission} onCancel={() => setEditingPermission(null)} embedded />
                    )}
                </DialogContent>
            </Dialog>

            {/* Delete Role Confirm */}
            <ConfirmDialog
                open={!!deleteTargetRole}
                title="Delete Role"
                message={deleteTargetRole ? `Delete role "${deleteTargetRole.name}"? This cannot be undone.` : ''}
                confirmLabel="Delete"
                onConfirm={confirmDeleteRole}
                onCancel={() => setDeleteTargetRole(null)}
            />

            {/* Delete Permission Confirm */}
            <ConfirmDialog
                open={!!deleteTargetPermission}
                title="Delete Permission"
                message={deleteTargetPermission ? `Delete permission "${deleteTargetPermission.name}"? This cannot be undone.` : ''}
                confirmLabel="Delete"
                onConfirm={confirmDeletePermission}
                onCancel={() => setDeleteTargetPermission(null)}
            />
        </>
    );
}

function RoleForm({ role = null, onSubmit, onCancel, embedded = false }) {
    const [permissionsByModule, setPermissionsByModule] = useState({});
    const [permsLoading, setPermsLoading] = useState(true);
    const [name, setName] = useState(role?.name || '');
    const [description, setDescription] = useState(role?.description || '');
    const [isActive, setIsActive] = useState(role?.is_active ?? true);
    const [selectedPermissionIds, setSelectedPermissionIds] = useState(
        () => role?.permission_ids || []
    );
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        const fetchPerms = async () => {
            try {
                const res = await api.get('/admin/api/permissions?per_page=100');
                const grouped = {};
                (res.data.permissions || []).forEach(p => {
                    if (!grouped[p.module]) grouped[p.module] = [];
                    grouped[p.module].push(p);
                });
                setPermissionsByModule(grouped);
            } catch {
                toast.error('Failed to load permissions');
            } finally {
                setPermsLoading(false);
            }
        };
        fetchPerms();
    }, []);

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

    const formContent = (
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
                {permsLoading ? (
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, py: 2 }}>
                        <CircularProgress size={16} />
                        <Typography variant="body2" color="text.secondary">Loading permissions...</Typography>
                    </Box>
                ) : moduleEntries.length === 0 ? (
                    <Typography variant="body2" color="text.secondary" sx={{ fontStyle: 'italic' }}>
                        No permissions available. Create permissions first.
                    </Typography>
                ) : (
                    moduleEntries.map(([module, permissions]) => (
                        <Box key={module} sx={{ mb: 2 }}>
                            <Typography
                                variant="caption"
                                sx={{
                                    display: 'block', fontWeight: 700, textTransform: 'uppercase',
                                    color: 'text.secondary', mb: 0.5, fontSize: '0.75rem', letterSpacing: '0.05em',
                                }}
                            >
                                {module}
                            </Typography>
                            <Box sx={{ pl: 1 }}>
                                {permissions.map((perm) => (
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
                                ))}
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
    );

    if (embedded) return formContent;

    return (
        <Box sx={{ background: '#f8f9fa', p: 3, borderRadius: '8px' }}>
            <Typography variant="h6" sx={{ mb: 2 }}>{role ? 'Edit Role' : 'Create Role'}</Typography>
            {formContent}
        </Box>
    );
}

function PermissionForm({ permission = null, onSubmit, onCancel, embedded = false }) {
    const [name, setName] = useState(permission?.name || '');
    const [module, setModule] = useState(permission?.module || '');
    const [description, setDescription] = useState(permission?.description || '');
    const [loading, setLoading] = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        try {
            await onSubmit({ name, module, description });
        } finally {
            setLoading(false);
        }
    };

    const formContent = (
        <form onSubmit={handleSubmit}>
            <FormInput label="Permission Name" required>
                <input
                    type="text"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    placeholder="e.g., manage tenants"
                    required
                />
            </FormInput>
            <FormInput label="Module" required>
                <input
                    type="text"
                    value={module}
                    onChange={(e) => setModule(e.target.value)}
                    placeholder="e.g., Tenant"
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
    );

    if (embedded) return formContent;

    return (
        <Box sx={{ background: '#f8f9fa', p: 3, borderRadius: '8px' }}>
            <Typography variant="h6" sx={{ mb: 2 }}>{permission ? 'Edit Permission' : 'Create Permission'}</Typography>
            {formContent}
        </Box>
    );
}
