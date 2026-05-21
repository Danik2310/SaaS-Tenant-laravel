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
import FilterListIcon from '@mui/icons-material/FilterList';

const statusColors = {
    active: '#22c55e',
    pending: '#f59e0b',
    cancelled: '#ef4444',
    expired: '#64748b',
};

export default function Subscriptions() {
    const [subscriptions, setSubscriptions] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const [statusFilter, setStatusFilter] = useState('');
    const [search, setSearch] = useState('');

    useEffect(() => {
        fetchSubscriptions();
    }, []);

    const fetchSubscriptions = async () => {
        setLoading(true);
        try {
            const params = new URLSearchParams();
            if (statusFilter) params.append('status', statusFilter);
            if (search) params.append('search', search);
            const res = await api.get(`/admin/api/subscriptions?${params.toString()}`);
            setSubscriptions(res.data.subscriptions);
            setError(null);
        } catch (err) {
            const message = 'Failed to fetch subscriptions';
            toast.error(message);
            setError(message);
        } finally {
            setLoading(false);
        }
    };

    const handleFilter = () => {
        fetchSubscriptions();
    };

    const handleClear = () => {
        setStatusFilter('');
        setSearch('');
        fetchSubscriptions();
    };

    useEffect(() => {
        fetchSubscriptions();
    }, [statusFilter, search]);

    const columns = [
        { accessorKey: 'id', header: 'ID' },
        {
            accessorKey: 'tenant_name',
            header: 'Tenant',
            Cell: ({ cell, row }) => (
                <Box>
                    <Typography variant="body2" sx={{ fontWeight: 600, fontSize: 13 }}>
                        {cell.getValue()}
                    </Typography>
                    <Typography variant="caption" color="text.secondary">
                        {row.original.tenant_id}
                    </Typography>
                </Box>
            ),
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
                    <Button variant="outlined" size="small" onClick={handleClear} startIcon={<FilterListIcon />}>
                        Clear
                    </Button>
                </Stack>
            </Paper>

            {error && (
                <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>
            )}

            <DataTable
                columns={columns}
                data={subscriptions}
                loading={loading}
                emptyMessage="No subscriptions found."
            />
        </Box>
    );
}
