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
import MenuItem from '@mui/material/MenuItem';
import Menu from '@mui/material/Menu';
import ListItemIcon from '@mui/material/ListItemIcon';
import ListItemText from '@mui/material/ListItemText';
import FormControl from '@mui/material/FormControl';
import InputLabel from '@mui/material/InputLabel';
import Select from '@mui/material/Select';
import FormHelperText from '@mui/material/FormHelperText';
import InboxIcon from '@mui/icons-material/Inbox';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import AddIcon from '@mui/icons-material/Add';
import CloseIcon from '@mui/icons-material/Close';
import MoreVertIcon from '@mui/icons-material/MoreVert';
import { toast } from 'sonner';

const STATUS_OPTIONS = [
    { text: 'Active', value: 'active' },
    { text: 'Inactive', value: 'inactive' },
];

function slugify(text) {
    return text.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
}

function PlanForm({ plan, onSubmit, onCancel }) {
    const [form, setForm] = useState({
        name: plan?.name || '',
        slug: plan?.slug || '',
        price: plan?.price || '',
        status: plan?.status || 'active',
        duration_months: plan?.duration_months || '',
        max_users: plan?.max_users || '',
        max_storage: plan?.max_storage || '',
        max_warehouses: plan?.max_warehouses || '',
        max_categories: plan?.max_categories || '',
        max_products: plan?.max_products || '',
        features: Array.isArray(plan?.features) ? plan.features.join(', ') : (plan?.features || ''),
    });
    const [errors, setErrors] = useState({});
    const [submitting, setSubmitting] = useState(false);

    const handleChange = (field) => (e) => {
        const value = e.target.value;
        setForm(prev => {
            const next = { ...prev, [field]: value };
            if (field === 'name' && !plan) {
                next.slug = slugify(value);
            }
            return next;
        });
        setErrors(prev => ({ ...prev, [field]: undefined }));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setErrors({});
        try {
            const payload = {
                ...form,
                slug: form.slug || slugify(form.name),
                price: parseFloat(form.price),
            };
            if (form.duration_months) payload.duration_months = parseInt(form.duration_months, 10);
            if (form.max_users) payload.max_users = parseInt(form.max_users, 10);
            if (form.max_storage) payload.max_storage = parseInt(form.max_storage, 10);
            if (form.max_warehouses) payload.max_warehouses = parseInt(form.max_warehouses, 10);
            if (form.max_categories) payload.max_categories = parseInt(form.max_categories, 10);
            if (form.max_products) payload.max_products = parseInt(form.max_products, 10);
            await onSubmit(payload);
        } catch (err) {
            if (err.response?.status === 422) {
                setErrors(err.response.data.errors || {});
            } else {
                toast.error(err.response?.data?.message || 'An error occurred');
            }
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <Box component="form" onSubmit={handleSubmit} sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
            <Box sx={{ display: 'flex', gap: 2 }}>
                <TextField
                    size="small"
                    label="Plan Name"
                    value={form.name}
                    onChange={handleChange('name')}
                    error={!!errors.name}
                    helperText={errors.name?.[0]}
                    required
                    autoFocus
                    fullWidth
                />
                <TextField
                    size="small"
                    label="Slug"
                    value={form.slug}
                    onChange={handleChange('slug')}
                    error={!!errors.slug}
                    helperText={errors.slug?.[0]}
                    required
                    fullWidth
                />
            </Box>

            <Box sx={{ display: 'flex', gap: 2 }}>
                <TextField
                    size="small"
                    label="Price"
                    type="number"
                    value={form.price}
                    onChange={handleChange('price')}
                    inputProps={{ min: 0, step: 0.01 }}
                    InputProps={{ startAdornment: <Typography variant="body2" sx={{ mr: 0.5 }}>$</Typography> }}
                    error={!!errors.price}
                    helperText={errors.price?.[0]}
                    required
                    fullWidth
                />
                <FormControl size="small" fullWidth error={!!errors.status}>
                    <InputLabel>Status</InputLabel>
                    <Select value={form.status} label="Status" onChange={handleChange('status')}>
                        {STATUS_OPTIONS.map(opt => (
                            <MenuItem key={opt.value} value={opt.value}>{opt.text}</MenuItem>
                        ))}
                    </Select>
                    {errors.status && <FormHelperText>{errors.status[0]}</FormHelperText>}
                </FormControl>
                <TextField
                    size="small"
                    label="Duration (months)"
                    type="number"
                    value={form.duration_months}
                    onChange={handleChange('duration_months')}
                    inputProps={{ min: 1 }}
                    error={!!errors.duration_months}
                    helperText={errors.duration_months?.[0]}
                    fullWidth
                />
            </Box>

            <Typography variant="caption" sx={{ color: '#64748b', fontWeight: 600, mt: 1 }}>Resource Limits</Typography>
            <Box sx={{ display: 'flex', gap: 2 }}>
                <TextField size="small" label="Max Users" type="number" value={form.max_users} onChange={handleChange('max_users')} inputProps={{ min: 1 }} error={!!errors.max_users} helperText={errors.max_users?.[0]} fullWidth />
                <TextField size="small" label="Max Storage (MB)" type="number" value={form.max_storage} onChange={handleChange('max_storage')} inputProps={{ min: 0 }} error={!!errors.max_storage} helperText={errors.max_storage?.[0]} fullWidth />
                <TextField size="small" label="Max Warehouses" type="number" value={form.max_warehouses} onChange={handleChange('max_warehouses')} inputProps={{ min: 1 }} error={!!errors.max_warehouses} helperText={errors.max_warehouses?.[0]} fullWidth />
            </Box>
            <Box sx={{ display: 'flex', gap: 2 }}>
                <TextField size="small" label="Max Categories" type="number" value={form.max_categories} onChange={handleChange('max_categories')} inputProps={{ min: 1 }} error={!!errors.max_categories} helperText={errors.max_categories?.[0]} fullWidth />
                <TextField size="small" label="Max Products" type="number" value={form.max_products} onChange={handleChange('max_products')} inputProps={{ min: 1 }} error={!!errors.max_products} helperText={errors.max_products?.[0]} fullWidth />
                <Box sx={{ flex: 1 }} />
            </Box>

            <TextField
                size="small"
                label="Features (comma-separated)"
                value={form.features}
                onChange={handleChange('features')}
                error={!!errors.features}
                helperText={errors.features?.[0]}
                placeholder="warehouses, categories, products, api_access"
                fullWidth
            />

            <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 1, mt: 1 }}>
                <Button variant="outlined" size="small" onClick={onCancel} disabled={submitting}>
                    Cancel
                </Button>
                <Button type="submit" variant="contained" size="small" disabled={submitting}>
                    {plan ? 'Save Changes' : 'Create Plan'}
                </Button>
            </Box>
        </Box>
    );
}

export default function Plans() {
    const [data, setData] = useState([]);
    const [loading, setLoading] = useState(true);
    const [total, setTotal] = useState(0);
    const [error, setError] = useState(null);

    const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 5 });
    const [globalFilter, setGlobalFilter] = useState('');
    const [sorting, setSorting] = useState([]);

    const [showCreateDialog, setShowCreateDialog] = useState(false);
    const [editingPlan, setEditingPlan] = useState(null);
    const [confirmDelete, setConfirmDelete] = useState({ open: false, plan: null });
    const [actionMenuAnchor, setActionMenuAnchor] = useState(null);
    const [actionMenuRow, setActionMenuRow] = useState(null);

    const fetchPlans = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const params = new URLSearchParams();
            params.set('page', pagination.pageIndex + 1);
            params.set('per_page', pagination.pageSize);

            if (globalFilter) params.set('search', globalFilter);

            const sortMapping = {
                'name': 'name',
                'price': 'price',
                'status': 'status',
                'created_at': 'created_at',
            };
            if (sorting.length > 0) {
                const sortField = sortMapping[sorting[0].id] || 'created_at';
                params.set('sort', sortField);
                params.set('order', sorting[0].desc ? 'desc' : 'asc');
            }

            const response = await api.get(`/admin/api/plans?${params}`);
            setData(response.data.plans);
            setTotal(response.data.meta?.total ?? response.data.plans.length);
        } catch (err) {
            const message = 'Failed to fetch plans';
            toast.error(message);
            setError(message);
        } finally {
            setLoading(false);
        }
    }, [pagination, globalFilter, sorting]);

    useEffect(() => {
        fetchPlans();
    }, [fetchPlans]);

    const handleCreatePlan = async (formData) => {
        await api.post('/admin/api/plans', formData);
        toast.success('Plan created successfully');
        setShowCreateDialog(false);
        fetchPlans();
    };

    const handleEditPlan = async (formData) => {
        await api.put(`/admin/api/plans/${editingPlan.id}`, formData);
        toast.success('Plan updated successfully');
        setEditingPlan(null);
        fetchPlans();
    };

    const handleConfirmDelete = async () => {
        const plan = confirmDelete.plan;
        setConfirmDelete({ open: false, plan: null });
        try {
            await api.delete(`/admin/api/plans/${plan.id}`);
            toast.success('Plan deleted successfully');
            fetchPlans();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to delete plan');
        }
    };

    const columns = useMemo(() => [
        {
            accessorKey: 'name',
            header: 'Name',
            Cell: ({ cell }) => (
                <Typography variant="body2" sx={{ fontWeight: 600, fontSize: 13, color: '#0f172a' }}>
                    {cell.getValue()}
                </Typography>
            ),
        },
        {
            accessorKey: 'price',
            header: 'Price',
            enableColumnFilter: false,
            Cell: ({ cell }) => (
                <Typography variant="body2" sx={{ fontWeight: 600, fontSize: 13, color: '#166534' }}>
                    ${parseFloat(cell.getValue()).toFixed(2)}
                </Typography>
            ),
        },
        {
            accessorKey: 'status',
            header: 'Status',
            filterVariant: 'select',
            filterSelectOptions: STATUS_OPTIONS,
            Cell: ({ cell }) => {
                const status = cell.getValue();
                const s = status === 'active'
                    ? { bgcolor: '#dcfce7', color: '#166534' }
                    : { bgcolor: '#f1f5f9', color: '#64748b' };
                return (
                    <Chip label={status} size="small" sx={{ fontWeight: 600, textTransform: 'capitalize', ...s }} />
                );
            },
        },
        {
            accessorKey: 'duration_months',
            header: 'Duration',
            enableColumnFilter: false,
            Cell: ({ cell }) => {
                const months = cell.getValue();
                return (
                    <Typography variant="body2" sx={{ color: '#64748b', fontSize: 13, fontWeight: 500 }}>
                        {months ? `${months} mo` : '—'}
                    </Typography>
                );
            },
        },
        {
            accessorKey: 'max_users',
            header: 'Users',
            enableColumnFilter: false,
            Cell: ({ cell }) => (
                <Typography variant="body2" sx={{ color: '#64748b', fontSize: 13 }}>
                    {cell.getValue() ?? 'Unlimited'}
                </Typography>
            ),
        },
        {
            accessorKey: 'features',
            header: 'Features',
            enableColumnFilter: false,
            enableSorting: false,
            Cell: ({ cell }) => {
                const features = cell.getValue();
                if (!features || !features.length) {
                    return <Typography variant="body2" sx={{ color: '#94a3b8', fontSize: 13 }}>None</Typography>;
                }
                return (
                    <Typography variant="body2" sx={{ fontSize: 13, maxWidth: '200px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                        {features.join(', ')}
                    </Typography>
                );
            },
        },
        {
            accessorKey: 'created_at',
            header: 'Created',
            enableColumnFilter: false,
            Cell: ({ cell }) => (
                <Typography variant="body2" sx={{ color: '#64748b', fontSize: 13 }}>
                    {cell.getValue() ? new Date(cell.getValue()).toLocaleDateString() : '—'}
                </Typography>
            ),
        },
    ], []);

    return (
        <Box>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 2, alignItems: 'center' }}>
                <Typography variant="subtitle1" sx={{ fontWeight: 600, color: '#0f172a' }}>
                    Plans
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
                    + New Plan
                </Button>
            </Box>

            {error ? (
                <Box sx={{ p: 2, textAlign: 'center' }}>
                    <Typography color="error">{error}</Typography>
                    <Button variant="outlined" size="small" sx={{ mt: 1 }} onClick={fetchPlans}>
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
                        sorting,
                    }}
                    onPaginationChange={setPagination}
                    onGlobalFilterChange={setGlobalFilter}
                    onSortingChange={setSorting}
                    enableGlobalFilter
                    enableSorting
                    manualPagination
                    manualSorting
                    positionGlobalFilter="left"
                    renderEmptyRowsFallback={() => (
                        <Box sx={{ textAlign: 'center', py: 6 }}>
                            <InboxIcon sx={{ fontSize: 48, color: 'grey.300', mb: 1 }} />
                            <Typography variant="body1" sx={{ fontWeight: 600, color: 'text.secondary' }}>
                                No plans found. Create one to get started.
                            </Typography>
                        </Box>
                    )}
                    muiTablePaperProps={{ elevation: 2, sx: { borderRadius: 2 } }}
                    muiTableHeadCellProps={{ sx: { fontWeight: 600, fontSize: '12px', color: 'text.secondary', textTransform: 'uppercase', letterSpacing: '0.05em' } }}
                    initialState={{ density: 'compact' }}
                    localization={{ toolbarSearchPlaceholder: 'Search plans...' }}
                    renderRowActions={({ row }) => {
                        const plan = row.original;
                        return (
                            <Box sx={{ display: 'flex', gap: '0.25rem', alignItems: 'center' }}>
                                <Tooltip title="Edit">
                                    <Box
                                        component="button"
                                        onClick={(e) => { e.stopPropagation(); setEditingPlan(plan); }}
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
                                        onClick={(e) => { e.stopPropagation(); setConfirmDelete({ open: true, plan }); }}
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
                                <IconButton
                                    size="small"
                                    onClick={(e) => { e.stopPropagation(); setActionMenuAnchor(e.currentTarget); setActionMenuRow(plan); }}
                                >
                                    <MoreVertIcon fontSize="small" />
                                </IconButton>
                            </Box>
                        );
                    }}
                />
            )}

            {/* Create Plan Dialog */}
            <Dialog open={showCreateDialog} onClose={() => setShowCreateDialog(false)} maxWidth="sm" fullWidth>
                <DialogTitle sx={{ m: 0, p: 2, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    Create New Plan
                    <IconButton onClick={() => setShowCreateDialog(false)} size="small">
                        <CloseIcon fontSize="small" />
                    </IconButton>
                </DialogTitle>
                <DialogContent sx={{ p: 2 }}>
                    <PlanForm
                        onSubmit={handleCreatePlan}
                        onCancel={() => setShowCreateDialog(false)}
                    />
                </DialogContent>
            </Dialog>

            {/* Edit Plan Dialog */}
            <Dialog open={!!editingPlan} onClose={() => setEditingPlan(null)} maxWidth="sm" fullWidth>
                <DialogTitle sx={{ m: 0, p: 2, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    Edit Plan
                    <IconButton onClick={() => setEditingPlan(null)} size="small">
                        <CloseIcon fontSize="small" />
                    </IconButton>
                </DialogTitle>
                <DialogContent sx={{ p: 2 }}>
                    {editingPlan && (
                        <PlanForm
                            plan={editingPlan}
                            onSubmit={handleEditPlan}
                            onCancel={() => setEditingPlan(null)}
                        />
                    )}
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={confirmDelete.open}
                title="Delete Plan"
                message={`Are you sure you want to delete "${confirmDelete.plan?.name}"? Tenants on this plan must be reassigned first.`}
                confirmLabel="Delete"
                onConfirm={handleConfirmDelete}
                onCancel={() => setConfirmDelete({ open: false, plan: null })}
            />

            <Menu
                anchorEl={actionMenuAnchor}
                open={!!actionMenuAnchor}
                onClose={() => { setActionMenuAnchor(null); setActionMenuRow(null); }}
                onClick={() => { setActionMenuAnchor(null); setActionMenuRow(null); }}
                transformOrigin={{ horizontal: 'right', vertical: 'top' }}
                anchorOrigin={{ horizontal: 'right', vertical: 'bottom' }}
            >
                {actionMenuRow && [
                    <MenuItem key="edit" onClick={() => setEditingPlan(actionMenuRow)}>
                        <ListItemIcon><EditIcon fontSize="small" /></ListItemIcon>
                        <ListItemText>Edit</ListItemText>
                    </MenuItem>,
                    <MenuItem key="delete" onClick={() => setConfirmDelete({ open: true, plan: actionMenuRow })}>
                        <ListItemIcon><DeleteIcon fontSize="small" /></ListItemIcon>
                        <ListItemText>Delete</ListItemText>
                    </MenuItem>,
                ]}
            </Menu>
        </Box>
    );
}
