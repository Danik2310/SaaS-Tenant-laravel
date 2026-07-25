import { useState, useEffect, useMemo, useCallback } from 'react';
import { MaterialReactTable } from 'material-react-table';
import api from '../../../services/api';
import StaffForm from './StaffForm';
import StaffPermissions from './StaffPermissions';
import ConfirmDialog from '@/Components/ConfirmDialog';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Dialog from '@mui/material/Dialog';
import DialogContent from '@mui/material/DialogContent';
import DialogTitle from '@mui/material/DialogTitle';
import IconButton from '@mui/material/IconButton';
import Typography from '@mui/material/Typography';
import Chip from '@mui/material/Chip';
import Tooltip from '@mui/material/Tooltip';
import InboxIcon from '@mui/icons-material/Inbox';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import BlockIcon from '@mui/icons-material/Block';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import AdminPanelSettingsIcon from '@mui/icons-material/AdminPanelSettings';
import CloseIcon from '@mui/icons-material/Close';
import { toast } from 'sonner';

export default function StaffList() {
    const [data, setData] = useState([]);
    const [loading, setLoading] = useState(true);
    const [total, setTotal] = useState(0);
    const [error, setError] = useState(null);
    const [confirmDelete, setConfirmDelete] = useState({ open: false, staff: null });

    const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 5 });
    const [globalFilter, setGlobalFilter] = useState('');
    const [columnFilters, setColumnFilters] = useState([]);
    const [sorting, setSorting] = useState([]);

    const [showCreateDialog, setShowCreateDialog] = useState(false);
    const [editingStaff, setEditingStaff] = useState(null);
    const [permissionsTarget, setPermissionsTarget] = useState(null);

    const fetchStaff = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const params = new URLSearchParams();
            params.set('page', pagination.pageIndex + 1);
            params.set('per_page', pagination.pageSize);

            if (globalFilter) params.set('search', globalFilter);

            columnFilters.forEach(f => {
                if (f.id === 'is_active' && f.value !== undefined && f.value !== null) {
                    params.set('is_active', f.value);
                }
            });

            const sortMapping = {
                'name': 'name',
                'email': 'email',
                'is_active': 'is_active',
                'created_at': 'created_at',
            };
            if (sorting.length > 0) {
                const sortField = sortMapping[sorting[0].id] || 'created_at';
                params.set('sort', sortField);
                params.set('order', sorting[0].desc ? 'desc' : 'asc');
            }

            const response = await api.get(`/admin/api/staff?${params}`);
            setData(response.data.staff);
            setTotal(response.data.total);
        } catch (err) {
            const message = 'Failed to fetch staff';
            toast.error(message);
            setError(message);
        } finally {
            setLoading(false);
        }
    }, [pagination, globalFilter, columnFilters, sorting]);

    useEffect(() => {
        fetchStaff();
    }, [fetchStaff]);

    const handleCreateStaff = async (formData) => {
        try {
            await api.post('/admin/api/staff', formData);
            toast.success('Staff member created successfully');
            setShowCreateDialog(false);
            fetchStaff();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to create staff');
        }
    };

    const handleUpdateStaff = async (formData) => {
        try {
            await api.put(`/admin/api/staff/${editingStaff.id}`, formData);
            toast.success('Staff member updated successfully');
            setEditingStaff(null);
            fetchStaff();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to update staff');
        }
    };

    const handleConfirmDelete = async () => {
        const row = confirmDelete.staff;
        setConfirmDelete({ open: false, staff: null });
        try {
            await api.delete(`/admin/api/staff/${row.id}`);
            toast.success('Staff member deleted successfully');
            fetchStaff();
        } catch (err) {
            toast.error('Failed to delete staff');
        }
    };

    const handleToggleStatus = async (row) => {
        try {
            await api.patch(`/admin/api/staff/${row.id}/toggle-status`);
            toast.success(`Staff member ${row.is_active ? 'deactivated' : 'activated'} successfully`);
            fetchStaff();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to toggle status');
        }
    };

    const handleEditClick = async (row) => {
        try {
            const response = await api.get(`/admin/api/staff/${row.id}`);
            setEditingStaff(response.data.staff);
        } catch (err) {
            toast.error('Failed to load staff details');
        }
    };

    const handlePermissionsClick = (row) => {
        setPermissionsTarget(row);
    };

    const columns = useMemo(() => [
        {
            accessorKey: 'name',
            header: 'Name',
            enableColumnFilter: false,
        },
        {
            accessorKey: 'email',
            header: 'Email',
            enableColumnFilter: false,
        },
        {
            accessorKey: 'roles',
            header: 'Roles',
            enableColumnFilter: false,
            enableSorting: false,
            Cell: ({ cell }) => {
                const roles = cell.getValue();
                if (!roles || roles.length === 0) {
                    return (
                        <Typography variant="body2" sx={{ color: '#94a3b8', fontSize: 13 }}>
                            No roles
                        </Typography>
                    );
                }
                return (
                    <Typography variant="body2" sx={{ fontSize: 13 }}>
                        {roles.join(', ')}
                    </Typography>
                );
            },
        },
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
                    <Tooltip title={isActive ? 'Staff member is active' : 'Staff member is inactive'}>
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
            accessorKey: 'created_at',
            header: 'Created',
            filterVariant: 'date-range',
            Cell: ({ cell }) => (
                <Typography variant="body2" sx={{ color: '#64748b', fontSize: 13 }}>
                    {new Date(cell.getValue()).toLocaleDateString()}
                </Typography>
            ),
        },
        {
            id: 'actions',
            header: 'Actions',
            enableColumnFilter: false,
            enableSorting: false,
            enableGlobalFilter: false,
            size: 120,
            Cell: ({ row }) => (
                <Box sx={{ display: 'flex', gap: '0.25rem', alignItems: 'center' }}>
                    <Tooltip title="Edit">
                        <Box
                            component="button"
                            onClick={(e) => { e.stopPropagation(); handleEditClick(row.original); }}
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
                    <Tooltip title="Manage Permissions">
                        <Box
                            component="button"
                            onClick={(e) => { e.stopPropagation(); handlePermissionsClick(row.original); }}
                            sx={{
                                display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                                border: 'none', bgcolor: 'transparent', cursor: 'pointer',
                                p: 0.5, borderRadius: 1, color: 'text.secondary',
                                '&:hover': { bgcolor: 'action.hover' },
                            }}
                        >
                            <AdminPanelSettingsIcon fontSize="small" />
                        </Box>
                    </Tooltip>
                    <Tooltip title={row.original.is_active ? 'Deactivate' : 'Activate'}>
                        <Box
                            component="button"
                            onClick={(e) => { e.stopPropagation(); handleToggleStatus(row.original); }}
                            sx={{
                                display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                                border: 'none', bgcolor: 'transparent', cursor: 'pointer',
                                p: 0.5, borderRadius: 1, color: row.original.is_active ? 'error.main' : 'success.main',
                                '&:hover': { bgcolor: 'action.hover' },
                            }}
                        >
                            {row.original.is_active ? <BlockIcon fontSize="small" /> : <CheckCircleIcon fontSize="small" />}
                        </Box>
                    </Tooltip>
                    <Tooltip title="Delete">
                        <Box
                            component="button"
                            onClick={(e) => { e.stopPropagation(); setConfirmDelete({ open: true, staff: row.original }); }}
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

    return (
        <Box>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 2, alignItems: 'center' }}>
                <Typography variant="subtitle1" sx={{ fontWeight: 600, color: '#0f172a' }}>
                    Staff Management
                </Typography>
                <Button
                    variant="contained"
                    size="small"
                    onClick={() => setShowCreateDialog(true)}
                    sx={{
                        bgcolor: '#22c55e',
                        '&:hover': { bgcolor: '#16a34a' },
                        fontWeight: 600,
                        fontSize: '13px',
                    }}
                >
                    + Create
                </Button>
            </Box>

            {error ? (
                <Box sx={{ p: 2, textAlign: 'center' }}>
                    <Typography color="error">{error}</Typography>
                    <Button variant="outlined" size="small" sx={{ mt: 1 }} onClick={fetchStaff}>
                        Retry
                    </Button>
                </Box>
            ) : (
                <MaterialReactTable
                    columns={columns}
                    data={data}
                    rowCount={total}
                    state={{
                        isLoading: loading,
                        pagination,
                        globalFilter,
                        columnFilters,
                        sorting,
                    }}
                    onPaginationChange={setPagination}
                    onGlobalFilterChange={setGlobalFilter}
                    onColumnFiltersChange={setColumnFilters}
                    onSortingChange={setSorting}
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
                                No staff members found. Create one to get started.
                            </Typography>
                        </Box>
                    )}
                    muiTablePaperProps={{ elevation: 2, sx: { borderRadius: 2 } }}
                    muiTableHeadCellProps={{ sx: { fontWeight: 600, fontSize: '12px', color: 'text.secondary', textTransform: 'uppercase', letterSpacing: '0.05em' } }}
                    initialState={{ density: 'compact' }}
                    localization={{ toolbarSearchPlaceholder: 'Search by name or email...' }}
                />
            )}

            {/* Create Dialog */}
            <Dialog open={showCreateDialog} onClose={() => setShowCreateDialog(false)} maxWidth="sm" fullWidth>
                <DialogTitle sx={{ m: 0, p: 2, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    Create Staff Member
                    <IconButton onClick={() => setShowCreateDialog(false)} size="small">
                        <CloseIcon fontSize="small" />
                    </IconButton>
                </DialogTitle>
                <DialogContent sx={{ p: 2 }}>
                    <StaffForm
                        onSubmit={handleCreateStaff}
                        onCancel={() => setShowCreateDialog(false)}
                        embedded
                    />
                </DialogContent>
            </Dialog>

            {/* Edit Dialog */}
            <Dialog open={!!editingStaff} onClose={() => setEditingStaff(null)} maxWidth="sm" fullWidth>
                <DialogTitle sx={{ m: 0, p: 2, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    Edit Staff Member
                    <IconButton onClick={() => setEditingStaff(null)} size="small">
                        <CloseIcon fontSize="small" />
                    </IconButton>
                </DialogTitle>
                <DialogContent sx={{ p: 2 }}>
                    {editingStaff && (
                        <StaffForm
                            staff={editingStaff}
                            onSubmit={handleUpdateStaff}
                            onCancel={() => setEditingStaff(null)}
                            embedded
                        />
                    )}
                </DialogContent>
            </Dialog>

            {/* Manage Permissions Dialog */}
            <Dialog open={!!permissionsTarget} onClose={() => setPermissionsTarget(null)} maxWidth="sm" fullWidth>
                <DialogTitle sx={{ m: 0, p: 2, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    Manage Permissions — {permissionsTarget?.name}
                    <IconButton onClick={() => setPermissionsTarget(null)} size="small">
                        <CloseIcon fontSize="small" />
                    </IconButton>
                </DialogTitle>
                <DialogContent sx={{ p: 2 }}>
                    {permissionsTarget && (
                        <StaffPermissions
                            staffId={permissionsTarget.id}
                            staffName={permissionsTarget.name}
                            onClose={() => setPermissionsTarget(null)}
                            onUpdate={() => { fetchStaff(); }}
                            embedded
                        />
                    )}
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={confirmDelete.open}
                title="Delete Staff Member"
                message={`Are you sure you want to delete ${confirmDelete.staff?.name}? This action can be undone.`}
                confirmLabel="Delete"
                onConfirm={handleConfirmDelete}
                onCancel={() => setConfirmDelete({ open: false, staff: null })}
            />
        </Box>
    );
}
