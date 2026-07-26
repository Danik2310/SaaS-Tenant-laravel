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
import WorkspacePremiumIcon from '@mui/icons-material/WorkspacePremium';
import CloseIcon from '@mui/icons-material/Close';
import MoreVertIcon from '@mui/icons-material/MoreVert';
import { toast } from 'sonner';

const STATUS_OPTIONS = [
    { text: 'Active', value: 'active' },
    { text: 'Pending', value: 'pending' },
    { text: 'Cancelled', value: 'cancelled' },
    { text: 'Expired', value: 'expired' },
];

function SubscriptionCreateForm({ tenants, plans, onSubmit, onCancel }) {
    const today = new Date().toISOString().split('T')[0];
    const [form, setForm] = useState({
        tenant_id: '',
        plan_id: '',
        status: 'active',
        starts_at: today,
        ends_at: '',
    });
    const [errors, setErrors] = useState({});
    const [submitting, setSubmitting] = useState(false);

    const selectedPlan = plans.find(p => String(p.id) === String(form.plan_id));

    const handleChange = (field) => (e) => {
        const value = e.target.value;
        setForm(prev => {
            const next = { ...prev, [field]: value };
            if (field === 'plan_id' && next.starts_at) {
                const plan = plans.find(p => String(p.id) === String(value));
                if (plan?.duration_months) {
                    const start = new Date(next.starts_at);
                    start.setMonth(start.getMonth() + plan.duration_months);
                    next.ends_at = start.toISOString().split('T')[0];
                } else {
                    next.ends_at = '';
                }
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
                tenant_id: form.tenant_id,
                plan_id: parseInt(form.plan_id, 10),
                status: form.status,
            };
            if (form.starts_at) payload.starts_at = form.starts_at;
            if (form.ends_at) payload.ends_at = form.ends_at;
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
            <FormControl size="small" fullWidth error={!!errors.tenant_id}>
                <InputLabel>Tenant</InputLabel>
                <Select
                    value={form.tenant_id}
                    label="Tenant"
                    onChange={handleChange('tenant_id')}
                >
                    {tenants.map(t => (
                        <MenuItem key={t.id} value={t.id}>{t.name}</MenuItem>
                    ))}
                </Select>
                {errors.tenant_id && <FormHelperText>{errors.tenant_id[0]}</FormHelperText>}
            </FormControl>

            <FormControl size="small" fullWidth error={!!errors.plan_id}>
                <InputLabel>Plan</InputLabel>
                <Select
                    value={form.plan_id}
                    label="Plan"
                    onChange={handleChange('plan_id')}
                >
                    {plans.map(p => (
                        <MenuItem key={p.id} value={p.id}>{p.name}</MenuItem>
                    ))}
                </Select>
                {errors.plan_id && <FormHelperText>{errors.plan_id[0]}</FormHelperText>}
            </FormControl>

            <FormControl size="small" fullWidth error={!!errors.status}>
                <InputLabel>Status</InputLabel>
                <Select
                    value={form.status}
                    label="Status"
                    onChange={handleChange('status')}
                >
                    {STATUS_OPTIONS.map(opt => (
                        <MenuItem key={opt.value} value={opt.value}>{opt.text}</MenuItem>
                    ))}
                </Select>
                {errors.status && <FormHelperText>{errors.status[0]}</FormHelperText>}
            </FormControl>

            <TextField
                size="small"
                label="Start Date"
                type="date"
                value={form.starts_at}
                onChange={handleChange('starts_at')}
                InputLabelProps={{ shrink: true }}
                error={!!errors.starts_at}
                helperText={errors.starts_at?.[0]}
            />

            <TextField
                size="small"
                label="End Date"
                type="date"
                value={form.ends_at}
                onChange={handleChange('ends_at')}
                InputLabelProps={{ shrink: true }}
                error={!!errors.ends_at}
                helperText={errors.ends_at?.[0] || (selectedPlan?.duration_months ? `Auto-calculated: ${selectedPlan.duration_months} months` : '')}
            />

            <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 1, mt: 1 }}>
                <Button variant="outlined" size="small" onClick={onCancel} disabled={submitting}>
                    Cancel
                </Button>
                <Button type="submit" variant="contained" size="small" disabled={submitting}>
                    Create Subscription
                </Button>
            </Box>
        </Box>
    );
}

function SubscriptionEditForm({ subscription, plans, onSubmit, onCancel }) {
    const [form, setForm] = useState({
        plan_id: subscription?.plan_id || '',
        status: subscription?.status || 'active',
    });
    const [errors, setErrors] = useState({});
    const [submitting, setSubmitting] = useState(false);

    const handleChange = (field) => (e) => {
        setForm(prev => ({ ...prev, [field]: e.target.value }));
        setErrors(prev => ({ ...prev, [field]: undefined }));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setErrors({});
        try {
            await onSubmit({
                plan_id: parseInt(form.plan_id, 10),
                status: form.status,
            });
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
            <Typography variant="body2" sx={{ color: '#64748b' }}>
                Editing subscription for <strong>{subscription?.tenant_name}</strong>
            </Typography>

            <FormControl size="small" fullWidth error={!!errors.plan_id}>
                <InputLabel>Plan</InputLabel>
                <Select
                    value={form.plan_id}
                    label="Plan"
                    onChange={handleChange('plan_id')}
                >
                    {plans.map(p => (
                        <MenuItem key={p.id} value={p.id}>{p.name}</MenuItem>
                    ))}
                </Select>
                {errors.plan_id && <FormHelperText>{errors.plan_id[0]}</FormHelperText>}
            </FormControl>

            <FormControl size="small" fullWidth error={!!errors.status}>
                <InputLabel>Status</InputLabel>
                <Select
                    value={form.status}
                    label="Status"
                    onChange={handleChange('status')}
                >
                    {STATUS_OPTIONS.map(opt => (
                        <MenuItem key={opt.value} value={opt.value}>{opt.text}</MenuItem>
                    ))}
                </Select>
                {errors.status && <FormHelperText>{errors.status[0]}</FormHelperText>}
            </FormControl>

            <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 1, mt: 1 }}>
                <Button variant="outlined" size="small" onClick={onCancel} disabled={submitting}>
                    Cancel
                </Button>
                <Button type="submit" variant="contained" size="small" disabled={submitting}>
                    Save Changes
                </Button>
            </Box>
        </Box>
    );
}

export default function Subscriptions({ initialSearch = '' }) {
    const [data, setData] = useState([]);
    const [loading, setLoading] = useState(true);
    const [total, setTotal] = useState(0);
    const [error, setError] = useState(null);

    const [plans, setPlans] = useState([]);
    const [tenants, setTenants] = useState([]);

    const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 5 });
    const [globalFilter, setGlobalFilter] = useState(initialSearch);
    const [columnFilters, setColumnFilters] = useState([]);
    const [sorting, setSorting] = useState([]);

    const [showCreateSubscriptionDialog, setShowCreateSubscriptionDialog] = useState(false);
    const [editingSubscription, setEditingSubscription] = useState(null);
    const [confirmDelete, setConfirmDelete] = useState({ open: false, subscription: null });
    const [actionMenuAnchor, setActionMenuAnchor] = useState(null);
    const [actionMenuRow, setActionMenuRow] = useState(null);

    useEffect(() => {
        api.get('/admin/api/plans-list').then(r => setPlans(r.data.plans ?? [])).catch(() => {});
        api.get('/admin/api/tenants-list').then(r => setTenants(r.data.tenants ?? [])).catch(() => {});
    }, []);

    const fetchSubscriptions = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const params = new URLSearchParams();
            params.set('page', pagination.pageIndex + 1);
            params.set('per_page', pagination.pageSize);

            if (globalFilter) params.set('search', globalFilter);

            columnFilters.forEach(f => {
                if (f.id === 'status' && f.value) {
                    params.set('status', f.value);
                }
                if (f.id === 'plan_name' && f.value) {
                    params.set('plan_name', f.value);
                }
            });

            const sortMapping = {
                'plan_name': 'plan_name',
                'status': 'status',
                'created_at': 'created_at',
            };
            if (sorting.length > 0) {
                const sortField = sortMapping[sorting[0].id] || 'created_at';
                params.set('sort', sortField);
                params.set('order', sorting[0].desc ? 'desc' : 'asc');
            }

            const response = await api.get(`/admin/api/subscriptions?${params}`);
            setData(response.data.subscriptions);
            setTotal(response.data.total);
        } catch (err) {
            const message = 'Failed to fetch subscriptions';
            toast.error(message);
            setError(message);
        } finally {
            setLoading(false);
        }
    }, [pagination, globalFilter, columnFilters, sorting]);

    useEffect(() => {
        fetchSubscriptions();
    }, [fetchSubscriptions]);

    const handleCreateSubscription = async (formData) => {
        await api.post('/admin/api/subscriptions', formData);
        toast.success('Subscription created successfully');
        setShowCreateSubscriptionDialog(false);
        fetchSubscriptions();
    };

    const handleEditSubscription = async (formData) => {
        await api.put(`/admin/api/subscriptions/${editingSubscription.id}`, formData);
        toast.success('Subscription updated successfully');
        setEditingSubscription(null);
        fetchSubscriptions();
    };

    const handleConfirmDelete = async () => {
        const sub = confirmDelete.subscription;
        setConfirmDelete({ open: false, subscription: null });
        try {
            await api.delete(`/admin/api/subscriptions/${sub.id}`);
            toast.success('Subscription deleted successfully');
            fetchSubscriptions();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to delete subscription');
        }
    };

    const columns = useMemo(() => [
        {
            accessorKey: 'plan_name',
            header: 'Plan',
            filterVariant: 'select',
            filterSelectOptions: plans.map(p => ({ text: p.name, value: p.name })),
            Cell: ({ cell }) => {
                const val = cell.getValue();
                return val ? (
                    <Chip
                        icon={<WorkspacePremiumIcon sx={{ fontSize: 14 }} />}
                        label={val}
                        size="small"
                        sx={{ bgcolor: '#f0f9ff', color: '#0369a1', fontWeight: 600, border: '1px solid #bae6fd' }}
                    />
                ) : (
                    <Typography variant="body2" sx={{ color: '#94a3b8', fontSize: 13 }}>No Plan</Typography>
                );
            },
        },
        {
            accessorKey: 'plan_price',
            header: 'Price',
            enableColumnFilter: false,
            enableSorting: false,
            Cell: ({ cell }) => {
                const price = cell.getValue();
                return (
                    <Typography variant="body2" sx={{ fontWeight: 600, fontSize: 13, color: '#166534' }}>
                        ${parseFloat(price).toFixed(2)}
                    </Typography>
                );
            },
        },
        {
            accessorKey: 'status',
            header: 'Status',
            filterVariant: 'select',
            filterSelectOptions: STATUS_OPTIONS,
            Cell: ({ cell }) => {
                const status = cell.getValue();
                const styles = {
                    active:    { bgcolor: '#dcfce7', color: '#166534' },
                    pending:   { bgcolor: '#fef9c3', color: '#854d0e' },
                    cancelled: { bgcolor: '#fee2e2', color: '#991b1b' },
                    expired:   { bgcolor: '#f1f5f9', color: '#64748b' },
                };
                const s = styles[status] || styles.expired;
                return (
                    <Tooltip title={`Status: ${status}`}>
                        <Chip
                            label={status}
                            size="small"
                            sx={{ fontWeight: 600, textTransform: 'capitalize', ...s }}
                        />
                    </Tooltip>
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
    ], [plans]);

    return (
        <Box>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 2, alignItems: 'center' }}>
                <Typography variant="subtitle1" sx={{ fontWeight: 600, color: '#0f172a' }}>
                    Subscriptions
                </Typography>
                <Button
                    variant="contained"
                    size="small"
                    onClick={() => setShowCreateSubscriptionDialog(true)}
                    sx={{
                        bgcolor: '#22c55e',
                        '&:hover': { bgcolor: '#16a34a' },
                        fontWeight: 600,
                        fontSize: '13px',
                    }}
                >
                    + New Subscription
                </Button>
            </Box>

            {error ? (
                <Box sx={{ p: 2, textAlign: 'center' }}>
                    <Typography color="error">{error}</Typography>
                    <Button variant="outlined" size="small" sx={{ mt: 1 }} onClick={fetchSubscriptions}>
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
                                No subscriptions found. Create one to get started.
                            </Typography>
                        </Box>
                    )}
                    muiTablePaperProps={{ elevation: 2, sx: { borderRadius: 2 } }}
                    muiTableHeadCellProps={{ sx: { fontWeight: 600, fontSize: '12px', color: 'text.secondary', textTransform: 'uppercase', letterSpacing: '0.05em' } }}
                    initialState={{ density: 'compact' }}
                    localization={{ toolbarSearchPlaceholder: 'Search by plan name...' }}
                    renderRowActions={({ row }) => {
                        const sub = row.original;
                        return (
                            <Box sx={{ display: 'flex', gap: '0.25rem', alignItems: 'center' }}>
                                <Tooltip title="Edit">
                                    <Box
                                        component="button"
                                        onClick={(e) => { e.stopPropagation(); setEditingSubscription(sub); }}
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
                                        onClick={(e) => { e.stopPropagation(); setConfirmDelete({ open: true, subscription: sub }); }}
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
                                    onClick={(e) => { e.stopPropagation(); setActionMenuAnchor(e.currentTarget); setActionMenuRow(sub); }}
                                >
                                    <MoreVertIcon fontSize="small" />
                                </IconButton>
                            </Box>
                        );
                    }}
                />
            )}

            {/* Create Subscription Dialog */}
            <Dialog open={showCreateSubscriptionDialog} onClose={() => setShowCreateSubscriptionDialog(false)} maxWidth="sm" fullWidth>
                <DialogTitle sx={{ m: 0, p: 2, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    Create New Subscription
                    <IconButton onClick={() => setShowCreateSubscriptionDialog(false)} size="small">
                        <CloseIcon fontSize="small" />
                    </IconButton>
                </DialogTitle>
                <DialogContent sx={{ p: 2 }}>
                    <SubscriptionCreateForm
                        tenants={tenants}
                        plans={plans}
                        onSubmit={handleCreateSubscription}
                        onCancel={() => setShowCreateSubscriptionDialog(false)}
                    />
                </DialogContent>
            </Dialog>

            {/* Edit Subscription Dialog */}
            <Dialog open={!!editingSubscription} onClose={() => setEditingSubscription(null)} maxWidth="sm" fullWidth>
                <DialogTitle sx={{ m: 0, p: 2, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    Edit Subscription
                    <IconButton onClick={() => setEditingSubscription(null)} size="small">
                        <CloseIcon fontSize="small" />
                    </IconButton>
                </DialogTitle>
                <DialogContent sx={{ p: 2 }}>
                    {editingSubscription && (
                        <SubscriptionEditForm
                            subscription={editingSubscription}
                            plans={plans}
                            onSubmit={handleEditSubscription}
                            onCancel={() => setEditingSubscription(null)}
                        />
                    )}
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={confirmDelete.open}
                title="Delete Subscription"
                message={`Are you sure you want to delete this subscription for ${confirmDelete.subscription?.tenant_name}? This action cannot be undone.`}
                confirmLabel="Delete"
                onConfirm={handleConfirmDelete}
                onCancel={() => setConfirmDelete({ open: false, subscription: null })}
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
                    <MenuItem key="edit" onClick={() => setEditingSubscription(actionMenuRow)}>
                        <ListItemIcon><EditIcon fontSize="small" /></ListItemIcon>
                        <ListItemText>Edit</ListItemText>
                    </MenuItem>,
                    <MenuItem key="delete" onClick={() => setConfirmDelete({ open: true, subscription: actionMenuRow })}>
                        <ListItemIcon><DeleteIcon fontSize="small" /></ListItemIcon>
                        <ListItemText>Delete</ListItemText>
                    </MenuItem>,
                ]}
            </Menu>
        </Box>
    );
}
