import { useState, useEffect, useMemo, useCallback } from 'react';
import { MaterialReactTable } from 'material-react-table';
import api from '@/services/api';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Typography from '@mui/material/Typography';
import Chip from '@mui/material/Chip';
import Tooltip from '@mui/material/Tooltip';
import InboxIcon from '@mui/icons-material/Inbox';
import VisibilityIcon from '@mui/icons-material/Visibility';
import ReceiptLongIcon from '@mui/icons-material/ReceiptLong';
import { toast } from 'sonner';
import PaymentHistoryDialog from './components/PaymentHistoryDialog';

const STATUS_OPTIONS = [
    { text: 'Active', value: 'active' },
    { text: 'Pending', value: 'pending' },
    { text: 'Cancelled', value: 'cancelled' },
    { text: 'Expired', value: 'expired' },
];

const TENANT_STATUS_STYLES = {
    Active:    { bgcolor: '#f0f9ff', color: '#0369a1', border: '1px solid #bae6fd' },
    Suspended: { bgcolor: '#fee2e2', color: '#991b1b' },
    Trial:     { bgcolor: '#fef9c3', color: '#854d0e' },
    Cancelled: { bgcolor: '#f1f5f9', color: '#64748b' },
    Deleted:   { bgcolor: '#f1f5f9', color: '#64748b', fontStyle: 'italic' },
};

function TenantCell({ cell, row }) {
    const name = cell.getValue();
    const status = row.original.tenant_actual_status;

    if (!status) {
        return (
            <Typography variant="body2" sx={{ color: '#94a3b8', fontSize: 13 }}>
                {name || '\u2014'}
            </Typography>
        );
    }

    if (status === 'Deleted') {
        return (
            <Tooltip title="Tenant deleted — click View to restore">
                <Chip
                    label={`${name || 'Unknown'} \u2014 Deleted`}
                    size="small"
                    sx={{ fontWeight: 600, ...TENANT_STATUS_STYLES.Deleted }}
                />
            </Tooltip>
        );
    }

    return (
        <Tooltip title={`Tenant is ${status}`}>
            <Chip label={name} size="small" sx={{ fontWeight: 600, ...TENANT_STATUS_STYLES[status] }} />
        </Tooltip>
    );
}

export default function Subscriptions({ initialSearch = '', onViewTenant }) {
    const [data, setData] = useState([]);
    const [loading, setLoading] = useState(true);
    const [total, setTotal] = useState(0);
    const [error, setError] = useState(null);

    const [plans, setPlans] = useState([]);

    const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 5 });
    const [globalFilter, setGlobalFilter] = useState(initialSearch);
    const [columnFilters, setColumnFilters] = useState([]);
    const [sorting, setSorting] = useState([]);

    const [paymentDialog, setPaymentDialog] = useState({ open: false, subscription: null });

    useEffect(() => {
        api.get('/admin/api/plans-list').then(r => setPlans(r.data.plans ?? [])).catch(() => {});
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
                if (f.id === 'tenant_name' && f.value) {
                    params.set('tenant_name', f.value);
                }
            });

            const sortMapping = {
                'tenant_name': 'tenant_name',
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

    const columns = useMemo(() => [
        {
            accessorKey: 'tenant_name',
            header: 'Tenant',
            size: 180,
            Cell: TenantCell,
        },
        {
            accessorKey: 'plan_name',
            header: 'Plan',
            filterVariant: 'select',
            filterSelectOptions: plans.map(p => ({ text: p.name, value: p.name })),
            Cell: ({ cell }) => {
                const val = cell.getValue();
                return val ? (
                    <Typography variant="body2" sx={{ fontWeight: 600, fontSize: 13, color: '#0369a1' }}>
                        {val}
                    </Typography>
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
                    {cell.getValue() ? new Date(cell.getValue()).toLocaleDateString() : '\u2014'}
                </Typography>
            ),
        },
    ], [plans]);

    return (
        <Box>
            <Box sx={{ mb: 2 }}>
                <Typography variant="subtitle1" sx={{ fontWeight: 600, color: '#0f172a' }}>
                    Subscriptions
                </Typography>
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
                    enableRowActions
                    manualFiltering
                    manualPagination
                    manualSorting
                    positionGlobalFilter="left"
                    renderEmptyRowsFallback={() => (
                        <Box sx={{ textAlign: 'center', py: 6 }}>
                            <InboxIcon sx={{ fontSize: 48, color: 'grey.300', mb: 1 }} />
                            <Typography variant="body1" sx={{ fontWeight: 600, color: 'text.secondary' }}>
                                No subscriptions found.
                            </Typography>
                        </Box>
                    )}
                    renderRowActions={({ row }) => {
                        const sub = row.original;
                        const canView = sub.tenant_actual_status !== null;
                        return (
                            <Box sx={{ display: 'flex', gap: '0.25rem', alignItems: 'center' }}>
                                <Tooltip title={canView ? (sub.tenant_actual_status === 'Deleted' ? 'View & Restore Tenant' : 'View Tenant') : 'Tenant unavailable'}>
                                    <Box
                                        component="button"
                                        data-testid="view-tenant-btn"
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            if (canView && onViewTenant) onViewTenant(sub.tenant_id);
                                        }}
                                        disabled={!canView}
                                        sx={{
                                            ...iconButtonSx,
                                            color: canView ? '#0369a1' : '#cbd5e1',
                                            cursor: canView ? 'pointer' : 'default',
                                        }}
                                    >
                                        <VisibilityIcon fontSize="small" />
                                    </Box>
                                </Tooltip>
                                <Tooltip title="Payment History">
                                    <Box
                                        component="button"
                                        data-testid="payment-history-btn"
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            setPaymentDialog({ open: true, subscription: sub });
                                        }}
                                        sx={iconButtonSx}
                                    >
                                        <ReceiptLongIcon fontSize="small" />
                                    </Box>
                                </Tooltip>
                            </Box>
                        );
                    }}
                    muiTablePaperProps={{ elevation: 2, sx: { borderRadius: 2 } }}
                    muiTableHeadCellProps={{ sx: { fontWeight: 600, fontSize: '12px', color: 'text.secondary', textTransform: 'uppercase', letterSpacing: '0.05em' } }}
                    initialState={{ density: 'compact' }}
                    localization={{ toolbarSearchPlaceholder: 'Search by plan or tenant...' }}
                />
            )}

            <PaymentHistoryDialog
                open={paymentDialog.open}
                subscription={paymentDialog.subscription}
                onClose={() => setPaymentDialog({ open: false, subscription: null })}
            />
        </Box>
    );
}

const iconButtonSx = {
    display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
    border: 'none', bgcolor: 'transparent', cursor: 'pointer',
    p: 0.5, borderRadius: 1, color: 'text.secondary',
    '&:hover': { bgcolor: 'action.hover' },
};
