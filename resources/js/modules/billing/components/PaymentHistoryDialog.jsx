import { useState, useEffect, useMemo } from 'react';
import Dialog from '@mui/material/Dialog';
import DialogTitle from '@mui/material/DialogTitle';
import DialogContent from '@mui/material/DialogContent';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import Button from '@mui/material/Button';
import TextField from '@mui/material/TextField';
import FormControl from '@mui/material/FormControl';
import InputLabel from '@mui/material/InputLabel';
import Select from '@mui/material/Select';
import MenuItem from '@mui/material/MenuItem';
import FormHelperText from '@mui/material/FormHelperText';
import Chip from '@mui/material/Chip';
import IconButton from '@mui/material/IconButton';
import CircularProgress from '@mui/material/CircularProgress';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableContainer from '@mui/material/TableContainer';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';
import Paper from '@mui/material/Paper';
import Tooltip from '@mui/material/Tooltip';
import CloseIcon from '@mui/icons-material/Close';
import AddIcon from '@mui/icons-material/Add';
import EditIcon from '@mui/icons-material/Edit';
import AttachMoneyIcon from '@mui/icons-material/AttachMoney';
import FilterListIcon from '@mui/icons-material/FilterList';
import ClearIcon from '@mui/icons-material/Clear';
import { toast } from 'sonner';
import api from '@/services/api';
import { useMoney, currencySymbol } from '@/shared/money';

const METHOD_OPTIONS = [
    { label: 'Stripe', value: 'stripe' },
    { label: 'Bank Transfer', value: 'bank_transfer' },
    { label: 'Cash', value: 'cash' },
    { label: 'Manual', value: 'manual' },
];

const STATUS_OPTIONS = [
    { label: 'Completed', value: 'completed' },
    { label: 'Pending', value: 'pending' },
    { label: 'Failed', value: 'failed' },
    { label: 'Refunded', value: 'refunded' },
];

const STATUS_STYLES = {
    completed: { bgcolor: '#dcfce7', color: '#166534' },
    pending:   { bgcolor: '#fef9c3', color: '#854d0e' },
    failed:    { bgcolor: '#fee2e2', color: '#991b1b' },
    refunded:  { bgcolor: '#f1f5f9', color: '#64748b' },
};

const EMPTY_FILTERS = { status: '', method: '', dateFrom: '', dateTo: '' };

const EMPTY_FORM = {
    amount: '',
    method: 'stripe',
    reference: '',
    status: 'completed',
    paid_at: new Date().toISOString().split('T')[0],
    notes: '',
};

export default function PaymentHistoryDialog({ open, subscription, onClose }) {
    const money = useMoney();
    const { formatMoney } = money;
    const [payments, setPayments] = useState([]);
    const [loading, setLoading] = useState(false);
    const [showForm, setShowForm] = useState(false);
    const [editingPayment, setEditingPayment] = useState(null);
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState({});
    const [filters, setFilters] = useState(EMPTY_FILTERS);
    const [form, setForm] = useState(EMPTY_FORM);
    const [amountTouched, setAmountTouched] = useState(false);

    useEffect(() => {
        if (open && subscription) {
            fetchPayments();
            resetFormState();
        }
    }, [open, subscription]);

    useEffect(() => {
        if (!editingPayment || amountTouched || !money.ready) return;
        setForm(prev => ({ ...prev, amount: String(money.convertFromBase(editingPayment.amount)) }));
    }, [money.ready, money.displayCurrency, editingPayment, amountTouched]);

    const resetFormState = () => {
        setShowForm(false);
        setEditingPayment(null);
        setFilters(EMPTY_FILTERS);
        setForm(EMPTY_FORM);
        setAmountTouched(false);
        setErrors({});
    };

    const fetchPayments = async () => {
        setLoading(true);
        try {
            const res = await api.get(`/admin/api/subscriptions/${subscription.id}/payments`);
            setPayments(res.data.payments);
        } catch {
            toast.error('Failed to load payment history');
        } finally {
            setLoading(false);
        }
    };

    const filteredPayments = useMemo(() => {
        return payments.filter(p => {
            if (filters.status && p.status !== filters.status) return false;
            if (filters.method && p.method !== filters.method) return false;
            if (filters.dateFrom) {
                const paid = p.paid_at ? new Date(p.paid_at) : null;
                if (!paid || paid < new Date(filters.dateFrom)) return false;
            }
            if (filters.dateTo) {
                const paid = p.paid_at ? new Date(p.paid_at) : null;
                const to = new Date(filters.dateTo);
                to.setHours(23, 59, 59, 999);
                if (!paid || paid > to) return false;
            }
            return true;
        });
    }, [payments, filters]);

    const activeFilterCount = useMemo(() => {
        let count = 0;
        if (filters.status) count++;
        if (filters.method) count++;
        if (filters.dateFrom) count++;
        if (filters.dateTo) count++;
        return count;
    }, [filters]);

    const handleFilterChange = (field) => (e) => {
        setFilters(prev => ({ ...prev, [field]: e.target.value }));
    };

    const clearFilters = () => setFilters(EMPTY_FILTERS);

    const handleChange = (field) => (e) => {
        setForm(prev => ({ ...prev, [field]: e.target.value }));
        if (field === 'amount') setAmountTouched(true);
        setErrors(prev => ({ ...prev, [field]: undefined }));
    };

    const handleEdit = (payment) => {
        setEditingPayment(payment);
        setForm({
            amount: String(money.convertFromBase(payment.amount)),
            method: payment.method,
            reference: payment.reference || '',
            status: payment.status,
            paid_at: payment.paid_at ? payment.paid_at.split('T')[0] : '',
            notes: payment.notes || '',
        });
        setAmountTouched(false);
        setShowForm(true);
        setErrors({});
    };

    const handleCancelEdit = () => {
        setEditingPayment(null);
        setForm(EMPTY_FORM);
        setAmountTouched(false);
        setShowForm(false);
        setErrors({});
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setErrors({});
        try {
            const parsed = parseFloat(form.amount);
            const amount = editingPayment && !amountTouched
                ? parseFloat(editingPayment.amount)
                : amountTouched
                    ? money.convertToBase(parsed)
                    : parsed;
            const payload = { ...form, amount };
            if (editingPayment) {
                await api.put(
                    `/admin/api/subscriptions/${subscription.id}/payments/${editingPayment.id}`,
                    payload,
                );
                toast.success('Payment updated successfully');
            } else {
                await api.post(`/admin/api/subscriptions/${subscription.id}/payments`, payload);
                toast.success('Payment recorded successfully');
            }
            handleCancelEdit();
            fetchPayments();
        } catch (err) {
            if (err.response?.status === 422) {
                setErrors(err.response.data.errors || {});
            } else {
                toast.error(err.response?.data?.message || 'Failed to save payment');
            }
        } finally {
            setSubmitting(false);
        }
    };

    const totalPaid = filteredPayments
        .filter(p => p.status === 'completed')
        .reduce((sum, p) => sum + parseFloat(p.amount), 0);

    return (
        <Dialog open={open} onClose={onClose} maxWidth="md" fullWidth>
            <DialogTitle sx={{ m: 0, p: 2, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <Box>
                    <Typography variant="subtitle1" sx={{ fontWeight: 600 }}>
                        Payment History
                    </Typography>
                    {subscription && (
                        <Typography variant="body2" sx={{ color: '#64748b', mt: 0.5 }}>
                            {subscription.tenant_name} — {subscription.plan_name}
                        </Typography>
                    )}
                </Box>
                <IconButton onClick={onClose} size="small">
                    <CloseIcon fontSize="small" />
                </IconButton>
            </DialogTitle>

            <DialogContent sx={{ p: 0 }}>
                {/* Summary bar */}
                <Box sx={{ px: 2, pt: 1, pb: 1, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5 }}>
                        <Chip
                            icon={<AttachMoneyIcon sx={{ fontSize: 16 }} />}
                            label={`Total Paid: ${formatMoney(totalPaid)}`}
                            size="small"
                            sx={{ bgcolor: '#dcfce7', color: '#166534', fontWeight: 600 }}
                        />
                        <Typography variant="body2" sx={{ color: '#64748b' }}>
                            {filteredPayments.length} of {payments.length} payment{payments.length !== 1 ? 's' : ''}
                        </Typography>
                    </Box>
                    {!showForm && (
                        <Button
                            variant="contained"
                            size="small"
                            startIcon={<AddIcon />}
                            onClick={() => { setShowForm(true); setEditingPayment(null); setForm(EMPTY_FORM); }}
                            sx={{ bgcolor: '#22c55e', '&:hover': { bgcolor: '#16a34a' }, fontWeight: 600, fontSize: '13px' }}
                        >
                            Record Payment
                        </Button>
                    )}
                </Box>

                {/* Filter controls */}
                {payments.length > 0 && !showForm && (
                    <Box sx={{ px: 2, pb: 1.5, display: 'flex', gap: 1.5, alignItems: 'center', flexWrap: 'wrap', borderBottom: '1px solid #e2e8f0' }}>
                        <FilterListIcon sx={{ fontSize: 18, color: '#94a3b8' }} />
                        <FormControl size="small" sx={{ minWidth: 120 }}>
                            <InputLabel>Status</InputLabel>
                            <Select value={filters.status} label="Status" onChange={handleFilterChange('status')}>
                                <MenuItem value="">All Statuses</MenuItem>
                                {STATUS_OPTIONS.map(opt => (
                                    <MenuItem key={opt.value} value={opt.value}>{opt.label}</MenuItem>
                                ))}
                            </Select>
                        </FormControl>
                        <FormControl size="small" sx={{ minWidth: 130 }}>
                            <InputLabel>Method</InputLabel>
                            <Select value={filters.method} label="Method" onChange={handleFilterChange('method')}>
                                <MenuItem value="">All Methods</MenuItem>
                                {METHOD_OPTIONS.map(opt => (
                                    <MenuItem key={opt.value} value={opt.value}>{opt.label}</MenuItem>
                                ))}
                            </Select>
                        </FormControl>
                        <TextField
                            size="small"
                            type="date"
                            label="From"
                            value={filters.dateFrom}
                            onChange={handleFilterChange('dateFrom')}
                            InputLabelProps={{ shrink: true }}
                            sx={{ minWidth: 140 }}
                        />
                        <TextField
                            size="small"
                            type="date"
                            label="To"
                            value={filters.dateTo}
                            onChange={handleFilterChange('dateTo')}
                            InputLabelProps={{ shrink: true }}
                            sx={{ minWidth: 140 }}
                        />
                        {activeFilterCount > 0 && (
                            <Button
                                size="small"
                                startIcon={<ClearIcon />}
                                onClick={clearFilters}
                                sx={{ color: '#64748b', textTransform: 'none', fontSize: 12 }}
                            >
                                Clear ({activeFilterCount})
                            </Button>
                        )}
                    </Box>
                )}

                {/* Record / Edit payment form */}
                {showForm && (
                    <Box component="form" onSubmit={handleSubmit} sx={{ px: 2, pb: 2, display: 'flex', flexDirection: 'column', gap: 1.5, borderBottom: '1px solid #e2e8f0' }}>
                        <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                            <Typography variant="subtitle2" sx={{ fontWeight: 600 }}>
                                {editingPayment ? 'Edit Payment' : 'Record Payment'}
                            </Typography>
                            <Button size="small" onClick={handleCancelEdit} sx={{ textTransform: 'none', color: '#64748b' }}>
                                Cancel
                            </Button>
                        </Box>
                        <Box sx={{ display: 'flex', gap: 1.5 }}>
                            <TextField
                                size="small"
                                label="Amount"
                                type="number"
                                value={form.amount}
                                onChange={handleChange('amount')}
                                error={!!errors.amount}
                                helperText={errors.amount?.[0] || (money.displayCurrency !== money.base ? `${money.displayCurrency} — stored in USD.` : undefined)}
                                sx={{ flex: 1 }}
                                inputProps={{ step: '0.01', min: '0.01' }}
                                InputProps={{ startAdornment: <Typography variant="body2" sx={{ mr: 0.5 }}>{currencySymbol(money.displayCurrency)}</Typography> }}
                            />
                            <FormControl size="small" sx={{ flex: 1 }} error={!!errors.method}>
                                <InputLabel>Method</InputLabel>
                                <Select value={form.method} label="Method" onChange={handleChange('method')}>
                                    {METHOD_OPTIONS.map(opt => (
                                        <MenuItem key={opt.value} value={opt.value}>{opt.label}</MenuItem>
                                    ))}
                                </Select>
                                {errors.method && <FormHelperText>{errors.method[0]}</FormHelperText>}
                            </FormControl>
                        </Box>
                        <Box sx={{ display: 'flex', gap: 1.5 }}>
                            <TextField
                                size="small"
                                label="Reference"
                                value={form.reference}
                                onChange={handleChange('reference')}
                                error={!!errors.reference}
                                helperText={errors.reference?.[0]}
                                sx={{ flex: 1 }}
                            />
                            <FormControl size="small" sx={{ flex: 1 }} error={!!errors.status}>
                                <InputLabel>Status</InputLabel>
                                <Select value={form.status} label="Status" onChange={handleChange('status')}>
                                    {STATUS_OPTIONS.map(opt => (
                                        <MenuItem key={opt.value} value={opt.value}>{opt.label}</MenuItem>
                                    ))}
                                </Select>
                                {errors.status && <FormHelperText>{errors.status[0]}</FormHelperText>}
                            </FormControl>
                        </Box>
                        <Box sx={{ display: 'flex', gap: 1.5 }}>
                            <TextField
                                size="small"
                                label="Paid Date"
                                type="date"
                                value={form.paid_at}
                                onChange={handleChange('paid_at')}
                                error={!!errors.paid_at}
                                helperText={errors.paid_at?.[0]}
                                sx={{ flex: 1 }}
                                InputLabelProps={{ shrink: true }}
                            />
                            <TextField
                                size="small"
                                label="Notes"
                                value={form.notes}
                                onChange={handleChange('notes')}
                                error={!!errors.notes}
                                helperText={errors.notes?.[0]}
                                sx={{ flex: 1 }}
                            />
                        </Box>
                        <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 1 }}>
                            <Button variant="outlined" size="small" onClick={handleCancelEdit} disabled={submitting}>
                                Cancel
                            </Button>
                            <Button type="submit" variant="contained" size="small" disabled={submitting}>
                                {submitting ? <CircularProgress size={16} /> : editingPayment ? 'Update Payment' : 'Save Payment'}
                            </Button>
                        </Box>
                    </Box>
                )}

                {/* Payment table */}
                {loading ? (
                    <Box sx={{ display: 'flex', justifyContent: 'center', py: 6 }}>
                        <CircularProgress size={24} />
                    </Box>
                ) : payments.length === 0 ? (
                    <Box sx={{ textAlign: 'center', py: 6 }}>
                        <AttachMoneyIcon sx={{ fontSize: 48, color: 'grey.300', mb: 1 }} />
                        <Typography variant="body1" sx={{ fontWeight: 600, color: 'text.secondary' }}>
                            No payments recorded yet.
                        </Typography>
                        <Typography variant="body2" sx={{ color: '#94a3b8', mt: 0.5 }}>
                            Click "Record Payment" to add the first payment.
                        </Typography>
                    </Box>
                ) : filteredPayments.length === 0 ? (
                    <Box sx={{ textAlign: 'center', py: 6 }}>
                        <FilterListIcon sx={{ fontSize: 48, color: 'grey.300', mb: 1 }} />
                        <Typography variant="body1" sx={{ fontWeight: 600, color: 'text.secondary' }}>
                            No payments match the current filters.
                        </Typography>
                        <Button size="small" onClick={clearFilters} sx={{ mt: 1, textTransform: 'none' }}>
                            Clear all filters
                        </Button>
                    </Box>
                ) : (
                    <TableContainer component={Paper} elevation={0} sx={{ mx: 2, mb: 2, border: '1px solid #e2e8f0', borderRadius: 1 }}>
                        <Table size="small">
                            <TableHead>
                                <TableRow>
                                    <TableCell sx={{ fontWeight: 600, fontSize: '12px', color: 'text.secondary', textTransform: 'uppercase' }}>Date</TableCell>
                                    <TableCell sx={{ fontWeight: 600, fontSize: '12px', color: 'text.secondary', textTransform: 'uppercase' }}>Amount</TableCell>
                                    <TableCell sx={{ fontWeight: 600, fontSize: '12px', color: 'text.secondary', textTransform: 'uppercase' }}>Method</TableCell>
                                    <TableCell sx={{ fontWeight: 600, fontSize: '12px', color: 'text.secondary', textTransform: 'uppercase' }}>Reference</TableCell>
                                    <TableCell sx={{ fontWeight: 600, fontSize: '12px', color: 'text.secondary', textTransform: 'uppercase' }}>Status</TableCell>
                                    <TableCell sx={{ fontWeight: 600, fontSize: '12px', color: 'text.secondary', textTransform: 'uppercase' }}>Notes</TableCell>
                                    <TableCell sx={{ fontWeight: 600, fontSize: '12px', color: 'text.secondary', textTransform: 'uppercase', width: 48 }} />
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {filteredPayments.map(payment => (
                                    <TableRow
                                        key={payment.id}
                                        sx={editingPayment?.id === payment.id ? { bgcolor: '#f0f9ff' } : {}}
                                    >
                                        <TableCell sx={{ fontSize: 13 }}>
                                            {payment.paid_at ? new Date(payment.paid_at).toLocaleDateString() : '\u2014'}
                                        </TableCell>
                                        <TableCell sx={{ fontSize: 13, fontWeight: 600, color: '#166534' }}>
                                            {formatMoney(payment.amount)}
                                        </TableCell>
                                        <TableCell sx={{ fontSize: 13, textTransform: 'capitalize' }}>
                                            {payment.method?.replace('_', ' ')}
                                        </TableCell>
                                        <TableCell sx={{ fontSize: 13, color: '#64748b' }}>
                                            {payment.reference || '\u2014'}
                                        </TableCell>
                                        <TableCell>
                                            <Chip
                                                label={payment.status}
                                                size="small"
                                                sx={{ fontWeight: 600, textTransform: 'capitalize', fontSize: 11, ...STATUS_STYLES[payment.status] }}
                                            />
                                        </TableCell>
                                        <TableCell sx={{ fontSize: 13, color: '#64748b', maxWidth: 150 }}>
                                            {payment.notes || '\u2014'}
                                        </TableCell>
                                        <TableCell>
                                            <Tooltip title="Edit payment">
                                                <IconButton
                                                    size="small"
                                                    data-testid="edit-payment-btn"
                                                    onClick={() => handleEdit(payment)}
                                                    sx={{ color: '#0369a1', '&:hover': { bgcolor: '#e0f2fe' } }}
                                                >
                                                    <EditIcon fontSize="small" />
                                                </IconButton>
                                            </Tooltip>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </TableContainer>
                )}
            </DialogContent>
        </Dialog>
    );
}
