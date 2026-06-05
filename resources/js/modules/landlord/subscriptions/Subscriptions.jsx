import React, { useEffect, useState } from 'react';
import { toast } from 'sonner';
import api from '../../../services/api';
import DataTable from '@/Components/DataTable';
import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';
import Typography from '@mui/material/Typography';
import TextField from '@mui/material/TextField';
import Select from '@mui/material/Select';
import MenuItem from '@mui/material/MenuItem';
import FormControl from '@mui/material/FormControl';
import InputLabel from '@mui/material/InputLabel';
import Button from '@mui/material/Button';
import Stack from '@mui/material/Stack';
import Paper from '@mui/material/Paper';
import Alert from '@mui/material/Alert';
import Link from '@mui/material/Link';
import Tooltip from '@mui/material/Tooltip';
import FilterListIcon from '@mui/icons-material/FilterList';
import ClearIcon from '@mui/icons-material/Clear';
import LaunchIcon from '@mui/icons-material/Launch';
import WarningAmberIcon from '@mui/icons-material/WarningAmber';
import ErrorOutlineIcon from '@mui/icons-material/ErrorOutline';

const statusColors = {
    active: '#22c55e',
    pending: '#f59e0b',
    cancelled: '#ef4444',
    expired: '#64748b',
};

export default function Subscriptions({ onViewTenant, initialSearch = '' }) {
    const [subscriptions, setSubscriptions] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const [statusFilter, setStatusFilter] = useState('');
    const [search, setSearch] = useState(initialSearch);

    const [total, setTotal] = useState(0);
    const [page, setPage] = useState(0);
    const [rowsPerPage, setRowsPerPage] = useState(25);

    const fetchSubscriptions = async (customPage, customPerPage) => {
        setLoading(true);
        try {
            const p = customPage ?? page;
            const rpp = customPerPage ?? rowsPerPage;
            const params = new URLSearchParams();
            if (statusFilter) params.append('status', statusFilter);
            if (search) params.append('search', search);
            if (p > 0) params.append('page', p + 1);
            params.append('per_page', rpp);
            const res = await api.get(`/admin/api/subscriptions?${params.toString()}`);
            setSubscriptions(res.data.subscriptions);
            setTotal(res.data.meta.total);
            setError(null);
        } catch (err) {
            const message = 'Failed to fetch subscriptions';
            toast.error(message);
            setError(message);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchSubscriptions(page, rowsPerPage);
    }, [page, rowsPerPage]);

    const handleFilter = () => {
        if (page !== 0) {
            setPage(0);
        } else {
            fetchSubscriptions(0, rowsPerPage);
        }
    };

    const handleClear = () => {
        setStatusFilter('');
        setSearch('');
        if (page !== 0) {
            setPage(0);
        } else {
            fetchSubscriptions(0, rowsPerPage);
        }
    };

    const columns = [
        { accessorKey: 'id', header: 'ID' },
        {
            id: 'tenant',
            header: 'Tenant',
            Cell: ({ cell }) => {
                const tenantName = cell.row.tenant_name;
                const tenantStatus = cell.row.tenant_status;
                const tenantId = cell.row.tenant_id;

                if (tenantStatus === 'missing') {
                    return (
                        <Box>
                            <Stack direction="row" spacing={1} alignItems="center">
                                <Typography variant="body2" sx={{ fontSize: 13, color: '#94a3b8', fontStyle: 'italic', fontWeight: 400 }}>
                                    {tenantName}
                                </Typography>
                                <Tooltip title="The tenant record no longer exists in the database">
                                    <Chip
                                        icon={<ErrorOutlineIcon sx={{ fontSize: 14 }} />}
                                        label="Missing"
                                        size="small"
                                        variant="outlined"
                                        color="error"
                                        sx={{ height: 20, '& .MuiChip-label': { fontSize: 11, fontWeight: 600, px: 0.5 } }}
                                    />
                                </Tooltip>
                            </Stack>
                            <Typography variant="caption" color="#94a3b8" sx={{ display: 'block', mt: 0.25 }}>
                                {tenantId}
                            </Typography>
                        </Box>
                    );
                }

                if (tenantStatus === 'deleted') {
                    return (
                        <Box>
                            <Stack direction="row" spacing={1} alignItems="center">
                                <Typography variant="body2" sx={{ fontSize: 13, color: '#d97706', fontWeight: 500, textDecoration: 'line-through' }}>
                                    {tenantName}
                                </Typography>
                                <Tooltip title="This tenant has been soft-deleted">
                                    <Chip
                                        icon={<WarningAmberIcon sx={{ fontSize: 14 }} />}
                                        label="Deleted"
                                        size="small"
                                        variant="outlined"
                                        color="warning"
                                        sx={{ height: 20, '& .MuiChip-label': { fontSize: 11, fontWeight: 600, px: 0.5 } }}
                                    />
                                </Tooltip>
                            </Stack>
                            <Stack direction="row" spacing={0.5} alignItems="center" sx={{ mt: 0.25 }}>
                                <Typography variant="caption" color="text.secondary">
                                    {tenantId}
                                </Typography>
                                <Link
                                    component="button"
                                    variant="caption"
                                    underline="hover"
                                    onClick={() => onViewTenant?.(tenantId)}
                                    sx={{ color: '#d97706', fontWeight: 500, fontSize: 11 }}
                                >
                                    View details
                                </Link>
                            </Stack>
                        </Box>
                    );
                }

                return (
                    <Box>
                        <Link
                            component="button"
                            variant="body2"
                            underline="hover"
                            onClick={() => onViewTenant?.(tenantId)}
                            sx={{ fontWeight: 600, fontSize: 13, color: '#3b82f6', textAlign: 'left' }}
                        >
                            {tenantName}
                            <LaunchIcon sx={{ fontSize: 13, ml: 0.5, verticalAlign: 'middle' }} />
                        </Link>
                        <Typography variant="caption" color="text.secondary" sx={{ display: 'block', mt: 0.25 }}>
                            {tenantId}
                        </Typography>
                    </Box>
                );
            },
        },
        {
            accessorKey: 'plan_name',
            header: 'Plan',
            Cell: ({ cell }) => (
                <Chip
                    label={cell.getValue()}
                    size="small"
                    sx={{ fontWeight: 600, bgcolor: '#eef2ff', color: '#4f46e5' }}
                />
            ),
        },
        { accessorKey: 'starts_at', header: 'Start Date' },
        { accessorKey: 'ends_at', header: 'End Date' },
        {
            accessorKey: 'status',
            header: 'Status',
            Cell: ({ cell }) => {
                const color = statusColors[cell.getValue()] || '#64748b';
                return (
                    <Chip
                        label={cell.getValue()}
                        size="small"
                        sx={{
                            fontWeight: 600,
                            bgcolor: `${color}18`,
                            color,
                            textTransform: 'capitalize',
                        }}
                    />
                );
            },
        },
        { accessorKey: 'created_at', header: 'Created' },
    ];

    return (
        <Box>
            <Paper sx={{ p: 2, mb: 3, borderRadius: 2, boxShadow: '0 1px 3px rgba(0,0,0,0.06)' }}>
                <Stack direction="row" spacing={2} alignItems="center" flexWrap="wrap">
                    <FormControl size="small" sx={{ minWidth: 160 }}>
                        <InputLabel>Status</InputLabel>
                        <Select
                            value={statusFilter}
                            label="Status"
                            onChange={(e) => setStatusFilter(e.target.value)}
                        >
                            <MenuItem value="">All</MenuItem>
                            <MenuItem value="active">Active</MenuItem>
                            <MenuItem value="pending">Pending</MenuItem>
                            <MenuItem value="cancelled">Cancelled</MenuItem>
                            <MenuItem value="expired">Expired</MenuItem>
                        </Select>
                    </FormControl>
                    <TextField
                        size="small"
                        label="Search tenant"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        sx={{ minWidth: 200 }}
                    />
                    <Button variant="contained" size="small" onClick={handleFilter} startIcon={<FilterListIcon />}>
                        Apply
                    </Button>
                    <Button variant="outlined" size="small" onClick={handleClear} startIcon={<ClearIcon />}>
                        Clear
                    </Button>
                </Stack>
            </Paper>

            {error && (
                <Alert
                    severity="error"
                    sx={{ mb: 2 }}
                    action={
                        <Button size="small" color="inherit" onClick={() => fetchSubscriptions(page, rowsPerPage)}>
                            Retry
                        </Button>
                    }
                >
                    {error}
                </Alert>
            )}

            <DataTable
                columns={columns}
                data={subscriptions}
                loading={loading}
                emptyMessage="No subscriptions found."
                total={total}
                page={page}
                rowsPerPage={rowsPerPage}
                onPageChange={(newPage) => setPage(newPage)}
                onRowsPerPageChange={(newRowsPerPage) => {
                    setRowsPerPage(newRowsPerPage);
                    setPage(0);
                }}
            />
        </Box>
    );
}
