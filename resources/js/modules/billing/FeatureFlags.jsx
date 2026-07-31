import { useState, useEffect, useMemo, useCallback } from 'react';
import { MaterialReactTable } from 'material-react-table';
import api from '@/services/api';
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
import TextField from '@mui/material/TextField';
import FormControlLabel from '@mui/material/FormControlLabel';
import Switch from '@mui/material/Switch';
import Alert from '@mui/material/Alert';
import InboxIcon from '@mui/icons-material/Inbox';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import AddIcon from '@mui/icons-material/Add';
import CloseIcon from '@mui/icons-material/Close';
import LockIcon from '@mui/icons-material/Lock';
import { toast } from 'sonner';

function FlagForm({ flag, onSubmit, onCancel }) {
    const isLocked = flag?.is_locked ?? false;
    const [form, setForm] = useState({
        key: flag?.key || '',
        label: flag?.label || '',
        description: flag?.description || '',
        is_active: flag?.is_active ?? true,
        sort_order: flag?.sort_order ?? 0,
    });
    const [errors, setErrors] = useState({});
    const [submitting, setSubmitting] = useState(false);

    const handleChange = (field) => (e) => {
        const value = field === 'is_active' ? e.target.checked : e.target.value;
        setForm(prev => ({ ...prev, [field]: value }));
        setErrors(prev => ({ ...prev, [field]: undefined }));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setErrors({});
        try {
            await onSubmit(form);
        } catch (err) {
            if (err.response?.status === 422) {
                setErrors(err.response.data.errors || {});
                toast.error(err.response.data.message || 'Please fix the validation errors below');
            } else {
                toast.error(err.response?.data?.message || 'An error occurred');
            }
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <Box component="form" onSubmit={handleSubmit} sx={{ display: 'flex', flexDirection: 'column', gap: 2.5, pt: 1 }}>
            <TextField
                size="small"
                label="Key"
                value={form.key}
                onChange={handleChange('key')}
                disabled={isLocked}
                error={!!errors.key}
                helperText={isLocked
                    ? 'Locked flag — key cannot be changed.'
                    : (errors.key?.[0] ?? 'Lowercase letters, numbers, underscores.')}
                required
                fullWidth
            />
            <TextField
                size="small"
                label="Label"
                value={form.label}
                onChange={handleChange('label')}
                error={!!errors.label}
                helperText={errors.label?.[0]}
                required
                fullWidth
            />
            <TextField
                size="small"
                label="Description"
                value={form.description}
                onChange={handleChange('description')}
                error={!!errors.description}
                helperText={errors.description?.[0]}
                multiline
                minRows={2}
                fullWidth
            />
            <Box sx={{ display: 'flex', gap: 3, alignItems: 'center' }}>
                <FormControlLabel
                    control={<Switch size="small" checked={form.is_active} onChange={handleChange('is_active')} />}
                    label="Active"
                />
                <TextField
                    size="small"
                    label="Sort Order"
                    type="number"
                    value={form.sort_order}
                    onChange={handleChange('sort_order')}
                    inputProps={{ min: 0 }}
                    error={!!errors.sort_order}
                    helperText={errors.sort_order?.[0]}
                />
            </Box>
            {isLocked && (
                <Alert severity="info" icon={<LockIcon fontSize="inherit" />}>
                    System flag. The key is referenced in code and cannot be renamed or deleted.
                </Alert>
            )}
            <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 1.5, pt: 1 }}>
                <Button variant="outlined" size="small" onClick={onCancel} disabled={submitting}>
                    Cancel
                </Button>
                <Button type="submit" variant="contained" size="small" disabled={submitting}>
                    {flag ? 'Save Changes' : 'Add Flag'}
                </Button>
            </Box>
        </Box>
    );
}

export default function FeatureFlags() {
    const [data, setData] = useState([]);
    const [loading, setLoading] = useState(true);
    const [total, setTotal] = useState(0);
    const [error, setError] = useState(null);

    const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 10 });
    const [globalFilter, setGlobalFilter] = useState('');
    const [sorting, setSorting] = useState([]);

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingFlag, setEditingFlag] = useState(null);
    const [confirmDelete, setConfirmDelete] = useState({ open: false, flag: null });

    const fetchFlags = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const params = new URLSearchParams();
            params.set('page', pagination.pageIndex + 1);
            params.set('per_page', pagination.pageSize);

            if (globalFilter) params.set('search', globalFilter);

            const sortMapping = {
                'key': 'key',
                'label': 'label',
                'is_locked': 'is_locked',
                'is_active': 'is_active',
                'sort_order': 'sort_order',
            };
            if (sorting.length > 0) {
                const sortField = sortMapping[sorting[0].id] || 'sort_order';
                params.set('sort', sortField);
                params.set('order', sorting[0].desc ? 'desc' : 'asc');
            }

            const response = await api.get(`/admin/api/feature-flags?${params}`);
            setData(response.data.flags);
            setTotal(response.data.meta?.total ?? response.data.flags.length);
        } catch (err) {
            const message = 'Failed to fetch feature flags';
            toast.error(message);
            setError(message);
        } finally {
            setLoading(false);
        }
    }, [pagination, globalFilter, sorting]);

    useEffect(() => {
        fetchFlags();
    }, [fetchFlags]);

    const openCreate = () => {
        setEditingFlag(null);
        setDialogOpen(true);
    };

    const openEdit = (flag) => {
        setEditingFlag(flag);
        setDialogOpen(true);
    };

    const handleCreate = async (formData) => {
        await api.post('/admin/api/feature-flags', formData);
        toast.success('Feature flag created');
        setDialogOpen(false);
        fetchFlags();
    };

    const handleUpdate = async (formData) => {
        await api.put(`/admin/api/feature-flags/${editingFlag.id}`, formData);
        toast.success('Feature flag updated');
        setDialogOpen(false);
        fetchFlags();
    };

    const handleConfirmDelete = async () => {
        const flag = confirmDelete.flag;
        setConfirmDelete({ open: false, flag: null });
        try {
            await api.delete(`/admin/api/feature-flags/${flag.id}`);
            toast.success('Feature flag deleted');
            fetchFlags();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to delete feature flag');
        }
    };

    const columns = useMemo(() => [
        {
            accessorKey: 'key',
            header: 'Key',
            enableColumnFilter: false,
            Cell: ({ cell }) => (
                <Typography component="code" variant="body2" sx={{
                    bgcolor: 'grey.100', px: 0.75, py: 0.25, borderRadius: 0.5,
                    fontFamily: 'monospace', fontSize: 12, color: '#0f172a',
                }}>
                    {cell.getValue()}
                </Typography>
            ),
        },
        {
            accessorKey: 'label',
            header: 'Label',
            Cell: ({ cell }) => (
                <Typography variant="body2" sx={{ fontWeight: 600, fontSize: 13, color: '#0f172a' }}>
                    {cell.getValue()}
                </Typography>
            ),
        },
        {
            accessorKey: 'description',
            header: 'Description',
            enableColumnFilter: false,
            enableSorting: false,
            Cell: ({ cell }) => {
                const description = cell.getValue();
                return (
                    <Typography variant="body2" sx={{ fontSize: 13, color: '#64748b', maxWidth: '240px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                        {description || '—'}
                    </Typography>
                );
            },
        },
        {
            accessorKey: 'is_locked',
            header: 'Type',
            enableColumnFilter: false,
            Cell: ({ cell }) => {
                const locked = cell.getValue();
                return (
                    <Chip
                        icon={locked ? <LockIcon sx={{ fontSize: 14 }} /> : undefined}
                        label={locked ? 'System' : 'Custom'}
                        size="small"
                        sx={{
                            fontWeight: 600,
                            bgcolor: locked ? '#f1f5f9' : '#dcfce7',
                            color: locked ? '#475569' : '#166534',
                        }}
                    />
                );
            },
        },
        {
            accessorKey: 'is_active',
            header: 'Status',
            enableColumnFilter: false,
            Cell: ({ cell }) => {
                const active = cell.getValue();
                return (
                    <Chip
                        label={active ? 'Active' : 'Inactive'}
                        size="small"
                        sx={{
                            fontWeight: 600,
                            bgcolor: active ? '#dcfce7' : '#f1f5f9',
                            color: active ? '#166534' : '#64748b',
                        }}
                    />
                );
            },
        },
        {
            accessorKey: 'sort_order',
            header: 'Sort',
            enableColumnFilter: false,
            Cell: ({ cell }) => (
                <Typography variant="body2" sx={{ color: '#64748b', fontSize: 13 }}>
                    {cell.getValue()}
                </Typography>
            ),
        },
    ], []);

    return (
        <Box>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 2, alignItems: 'center' }}>
                <Typography variant="subtitle1" sx={{ fontWeight: 600, color: '#0f172a' }}>
                    Feature Flags
                </Typography>
                <Button
                    variant="contained"
                    size="small"
                    onClick={openCreate}
                    startIcon={<AddIcon />}
                    sx={{
                        bgcolor: '#22c55e',
                        '&:hover': { bgcolor: '#16a34a' },
                        fontWeight: 600,
                        fontSize: '13px',
                    }}
                >
                    + New Feature Flag
                </Button>
            </Box>

            {error ? (
                <Box sx={{ p: 2, textAlign: 'center' }}>
                    <Typography color="error">{error}</Typography>
                    <Button variant="outlined" size="small" sx={{ mt: 1 }} onClick={fetchFlags}>
                        Retry
                    </Button>
                </Box>
            ) : (
                <MaterialReactTable
                    columns={columns}
                    data={data}
                    rowCount={total}
                    getRowId={(row) => String(row.id)}
                    state={{
                        isLoading: loading,
                        pagination,
                        globalFilter,
                        sorting,
                    }}
                    onPaginationChange={setPagination}
                    onGlobalFilterChange={setGlobalFilter}
                    onSortingChange={setSorting}
                    enableGlobalFilter
                    enableSorting
                    manualPagination
                    manualSorting
                    enableRowActions
                    positionGlobalFilter="left"
                    renderEmptyRowsFallback={() => (
                        <Box sx={{ textAlign: 'center', py: 6 }}>
                            <InboxIcon sx={{ fontSize: 48, color: 'grey.300', mb: 1 }} />
                            <Typography variant="body1" sx={{ fontWeight: 600, color: 'text.secondary' }}>
                                No feature flags found.
                            </Typography>
                        </Box>
                    )}
                    muiTablePaperProps={{ elevation: 2, sx: { borderRadius: 2 } }}
                    muiTableHeadCellProps={{ sx: { fontWeight: 600, fontSize: '12px', color: 'text.secondary', textTransform: 'uppercase', letterSpacing: '0.05em' } }}
                    initialState={{ density: 'compact' }}
                    localization={{ toolbarSearchPlaceholder: 'Search flags...' }}
                    renderRowActions={({ row }) => {
                        const flag = row.original;
                        return (
                            <Box sx={{ display: 'flex', gap: '0.25rem', alignItems: 'center' }}>
                                <Tooltip title="Edit">
                                    <Box
                                        component="button"
                                        aria-label="Edit"
                                        onClick={() => openEdit(flag)}
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
                                <Tooltip title={flag.is_locked ? 'Locked flag cannot be deleted' : 'Delete'}>
                                    <Box
                                        component="button"
                                        aria-label="Delete"
                                        onClick={() => !flag.is_locked && setConfirmDelete({ open: true, flag })}
                                        sx={{
                                            display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                                            border: 'none', bgcolor: 'transparent', cursor: flag.is_locked ? 'not-allowed' : 'pointer',
                                            p: 0.5, borderRadius: 1, color: 'error.main', opacity: flag.is_locked ? 0.4 : 1,
                                            '&:hover': flag.is_locked ? undefined : { bgcolor: 'action.hover' },
                                        }}
                                    >
                                        <DeleteIcon fontSize="small" />
                                    </Box>
                                </Tooltip>
                            </Box>
                        );
                    }}
                />
            )}

            <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} maxWidth="xs" fullWidth>
                <DialogTitle sx={{ m: 0, p: 2, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    {editingFlag ? 'Edit Feature Flag' : 'New Feature Flag'}
                    <IconButton onClick={() => setDialogOpen(false)} size="small">
                        <CloseIcon fontSize="small" />
                    </IconButton>
                </DialogTitle>
                <DialogContent sx={{ p: 2 }}>
                    <FlagForm
                        flag={editingFlag}
                        onSubmit={editingFlag ? handleUpdate : handleCreate}
                        onCancel={() => setDialogOpen(false)}
                    />
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={confirmDelete.open}
                title="Delete Feature Flag"
                message={`Are you sure you want to delete "${confirmDelete.flag?.label}"?`}
                confirmLabel="Delete"
                onConfirm={handleConfirmDelete}
                onCancel={() => setConfirmDelete({ open: false, flag: null })}
            />
        </Box>
    );
}
