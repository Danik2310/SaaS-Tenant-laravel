import { useMemo } from 'react';
import { MaterialReactTable } from 'material-react-table';
import { Head, router } from '@inertiajs/react';
import TenantLayout from '@/Layouts/TenantLayout';
import Box from '@mui/material/Box';
import Paper from '@mui/material/Paper';
import Typography from '@mui/material/Typography';
import Chip from '@mui/material/Chip';
import Tooltip from '@mui/material/Tooltip';
import IconButton from '@mui/material/IconButton';
import FormControl from '@mui/material/FormControl';
import InputLabel from '@mui/material/InputLabel';
import Select from '@mui/material/Select';
import MenuItem from '@mui/material/MenuItem';
import InboxIcon from '@mui/icons-material/Inbox';
import VisibilityIcon from '@mui/icons-material/Visibility';

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

function formatDate(value) {
    if (!value) return '\u2014';
    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatAmount(amount, currency) {
    const value = Number(amount);
    if (Number.isNaN(value)) return amount ?? '\u2014';
    try {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency || 'USD',
        }).format(value);
    } catch {
        return `${value.toFixed(2)} ${currency || 'USD'}`;
    }
}

export default function Payments({ payments, filters }) {
    const rows = payments?.data ?? [];
    const meta = payments?.meta ?? {};
    const currentPage = meta.current_page ?? 1;
    const lastPage = meta.last_page ?? 1;
    const total = meta.total ?? 0;
    const perPage = meta.per_page ?? 15;
    const status = filters?.status ?? '';

    const columns = useMemo(() => [
        {
            accessorKey: 'paid_at',
            header: 'Date',
            size: 130,
            Cell: ({ cell }) => formatDate(cell.getValue()),
        },
        { accessorKey: 'plan_name', header: 'Plan', size: 160 },
        {
            accessorKey: 'amount',
            header: 'Amount',
            size: 130,
            Cell: ({ row }) => formatAmount(row.original.amount, row.original.currency),
        },
        {
            accessorKey: 'method',
            header: 'Method',
            size: 130,
            Cell: ({ cell }) => {
                const value = cell.getValue();
                if (!value) return '\u2014';
                return String(value).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
            },
        },
        { accessorKey: 'reference', header: 'Reference', size: 220 },
        {
            accessorKey: 'status',
            header: 'Status',
            size: 120,
            Cell: ({ cell }) => {
                const value = cell.getValue();
                return (
                    <Chip
                        label={value}
                        size="small"
                        sx={{
                            fontWeight: 600,
                            textTransform: 'capitalize',
                            fontSize: 11,
                            ...(STATUS_STYLES[value] ?? {}),
                        }}
                    />
                );
            },
        },
    ], []);

    const handleStatusChange = (event) => {
        const value = event.target.value;
        router.get(
            route('billing.payments.index'),
            { status: value || undefined, page: 1 },
            { preserveState: true, replace: true, preserveScroll: true }
        );
    };

    const handlePageChange = (_, newPage) => {
        router.get(
            route('billing.payments.index'),
            { status: status || undefined, page: newPage + 1 },
            { preserveState: true, replace: true, preserveScroll: true }
        );
    };

    return (
        <TenantLayout>
            <Head title="Payments" />

            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 3, flexWrap: 'wrap', gap: 2 }}>
                <Box>
                    <Typography variant="h5" sx={{ fontWeight: 700, color: 'text.primary' }}>
                        Payment History
                    </Typography>
                    <Typography variant="body2" color="text.secondary">
                        Your subscription payments and receipts.
                    </Typography>
                </Box>

                <FormControl size="small" sx={{ minWidth: 160 }}>
                    <InputLabel id="payment-status-label">Status</InputLabel>
                    <Select
                        labelId="payment-status-label"
                        label="Status"
                        value={status}
                        onChange={handleStatusChange}
                        inputProps={{ 'data-testid': 'status-filter' }}
                    >
                        <MenuItem value="">All Statuses</MenuItem>
                        {STATUS_OPTIONS.map((opt) => (
                            <MenuItem key={opt.value} value={opt.value}>{opt.label}</MenuItem>
                        ))}
                    </Select>
                </FormControl>
            </Box>

            <MaterialReactTable
                columns={columns}
                data={rows}
                rowCount={total}
                state={{
                    pagination: { pageIndex: currentPage - 1, pageSize: perPage },
                }}
                onPaginationChange={handlePageChange}
                enableRowActions
                manualPagination
                enableColumnActions={false}
                enableColumnFilters={false}
                enableSorting={false}
                enableGlobalFilter={false}
                positionToolbarAlertBanner="bottom"
                renderEmptyRowsFallback={() => (
                    <Box sx={{ textAlign: 'center', py: 6 }}>
                        <InboxIcon sx={{ fontSize: 48, color: 'grey.300', mb: 1 }} />
                        <Typography variant="body1" sx={{ fontWeight: 600, color: 'text.secondary' }}>
                            No payments found.
                        </Typography>
                    </Box>
                )}
                renderRowActions={({ row }) => (
                    <Tooltip title={row.original.reference || 'Payment'}>
                        <IconButton
                            size="small"
                            data-testid="view-payment-btn"
                            sx={{ color: 'primary.main' }}
                        >
                            <VisibilityIcon fontSize="small" />
                        </IconButton>
                    </Tooltip>
                )}
                muiTablePaperProps={{ elevation: 2, sx: { borderRadius: 2 } }}
                muiTableHeadCellProps={{
                    sx: {
                        fontWeight: 600,
                        fontSize: '12px',
                        color: 'text.secondary',
                        textTransform: 'uppercase',
                        letterSpacing: '0.05em',
                    },
                }}
                initialState={{ density: 'compact' }}
            />

            {lastPage > 1 && (
                <Paper sx={{ display: 'flex', justifyContent: 'flex-end', mt: 2, p: 0 }}>
                    <Box sx={{ p: 1, display: 'flex', alignItems: 'center', gap: 1 }}>
                        <Typography variant="body2" color="text.secondary">
                            Page {currentPage} of {lastPage}
                        </Typography>
                        <Typography
                            component="button"
                            variant="body2"
                            data-testid="prev-page"
                            disabled={currentPage <= 1}
                            onClick={() => currentPage > 1 && handlePageChange(null, currentPage - 2)}
                            sx={{
                                border: 'none', bgcolor: 'transparent', cursor: currentPage <= 1 ? 'default' : 'pointer',
                                color: currentPage <= 1 ? 'text.disabled' : 'primary.main', fontWeight: 600, fontSize: 13,
                            }}
                        >
                            Prev
                        </Typography>
                        <Typography
                            component="button"
                            variant="body2"
                            data-testid="next-page"
                            disabled={currentPage >= lastPage}
                            onClick={() => currentPage < lastPage && handlePageChange(null, currentPage)}
                            sx={{
                                border: 'none', bgcolor: 'transparent', cursor: currentPage >= lastPage ? 'default' : 'pointer',
                                color: currentPage >= lastPage ? 'text.disabled' : 'primary.main', fontWeight: 600, fontSize: 13,
                            }}
                        >
                            Next
                        </Typography>
                    </Box>
                </Paper>
            )}
        </TenantLayout>
    );
}
