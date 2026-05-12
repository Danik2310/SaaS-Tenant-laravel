import React, { useEffect, useState, useCallback } from 'react';
import api from '@/services/api';
import ExportButton from '@/components/ExportButton';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import Paper from '@mui/material/Paper';
import Chip from '@mui/material/Chip';
import LinearProgress from '@mui/material/LinearProgress';
import Tooltip from '@mui/material/Tooltip';
import Grid from '@mui/material/Grid';
import Avatar from '@mui/material/Avatar';
import Skeleton from '@mui/material/Skeleton';
import TablePagination from '@mui/material/TablePagination';
import StorageIcon from '@mui/icons-material/Storage';
import FiberManualRecordIcon from '@mui/icons-material/FiberManualRecord';
import DnsIcon from '@mui/icons-material/Dns';
import { alpha } from '@mui/material/styles';

function UsageBar({ current, limit, label, color = 'primary.main' }) {
    const hasLimit = limit !== null && limit !== undefined;
    const percentage = hasLimit && limit > 0 ? Math.min((current / limit) * 100, 100) : 0;
    const barColor = percentage >= 90 ? 'error.main' : percentage >= 75 ? 'warning.main' : color;

    return (
        <Box sx={{ mb: 1.5 }}>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 0.5 }}>
                <Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 500 }}>
                    {label}
                </Typography>
                <Typography variant="caption" sx={{ color: 'text.primary', fontWeight: 600 }}>
                    {current.toLocaleString()}
                    {hasLimit
                        ? ` / ${limit === 2147483647 ? '∞' : limit.toLocaleString()}`
                        : ''}
                </Typography>
            </Box>
            {hasLimit && limit > 0 && (
                <Tooltip title={`${percentage.toFixed(0)}% used`}>
                    <LinearProgress
                        variant="determinate"
                        value={percentage}
                        sx={{
                            height: 8,
                            borderRadius: 4,
                            bgcolor: 'grey.100',
                            '& .MuiLinearProgress-bar': {
                                bgcolor: barColor,
                                borderRadius: 4,
                            },
                        }}
                    />
                </Tooltip>
            )}
            {!hasLimit && (
                <LinearProgress
                    variant="determinate"
                    value={0}
                    sx={{ height: 8, borderRadius: 4, bgcolor: 'grey.100' }}
                />
            )}
        </Box>
    );
}

function TenantUsageCard({ metric, limits }) {
    const usage = metric?.usage;
    const tenant = metric?.tenant || {};
    const planLimits = metric?.limits || limits || {};

    const statusColor = tenant.status === 'Active' ? 'success' : 'error';

    return (
        <Paper sx={{ p: 3, boxShadow: 1, borderRadius: 1 }}>
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 2, mb: 2 }}>
                <Avatar
                    sx={(theme) => ({
                        bgcolor: alpha(theme.palette.primary.main, 0.08),
                        color: 'primary.dark',
                    })}
                >
                    <DnsIcon />
                </Avatar>
                <Box>
                    <Typography variant="subtitle2" sx={{ fontWeight: 700, color: 'text.primary' }}>
                        {tenant.name || 'Unknown'}
                    </Typography>
                    <Typography variant="caption" sx={{ color: 'text.secondary' }}>
                        {tenant.plan_name || 'No Plan'} · {tenant.email || ''}
                    </Typography>
                </Box>
                <Box sx={{ ml: 'auto' }}>
                    <Chip
                        label={tenant.status || 'N/A'}
                        size="small"
                        color={statusColor}
                        variant={tenant.status === 'Active' ? 'filled' : 'outlined'}
                    />
                </Box>
            </Box>

            {usage ? (
                <>
                    <UsageBar
                        current={usage.users_count}
                        limit={planLimits.max_users}
                        label="Users"
                        color="primary.main"
                    />
                    <UsageBar
                        current={Math.round(usage.storage_kb / 1024)}
                        limit={planLimits.max_storage}
                        label="Storage (MB)"
                        color="secondary.main"
                    />
                    <UsageBar
                        current={Math.round(usage.db_size_kb / 1024)}
                        limit={null}
                        label="Database (MB)"
                        color="warning.main"
                    />
                    <UsageBar
                        current={usage.products_count}
                        limit={null}
                        label="Products"
                        color="success.main"
                    />
                    <UsageBar
                        current={usage.orders_count}
                        limit={null}
                        label="Orders"
                        color="secondary.main"
                    />
                    {usage.collected_at && (
                        <Typography
                            variant="caption"
                            sx={{ color: 'text.disabled', mt: 1, display: 'block' }}
                        >
                            Last updated: {new Date(usage.collected_at).toLocaleString()}
                        </Typography>
                    )}
                </>
            ) : (
                <Typography
                    variant="body2"
                    sx={{ color: 'text.disabled', textAlign: 'center', py: 4 }}
                >
                    No metrics collected yet. Data will appear as this tenant uses the system.
                </Typography>
            )}
        </Paper>
    );
}

export default function ResourceUsage() {
    const [metrics, setMetrics] = useState([]);
    const [loading, setLoading] = useState(true);
    const [total, setTotal] = useState(0);
    const [page, setPage] = useState(0);
    const [rowsPerPage, setRowsPerPage] = useState(25);

    const fetchMetrics = useCallback(async () => {
        setLoading(true);
        try {
            const res = await api.get(
                `/admin/api/resource-usage?page=${page + 1}&per_page=${rowsPerPage}`
            );
            setMetrics(res.data.metrics);
            setTotal(res.data.total);
        } catch (err) {
            console.error('Failed to fetch resource usage:', err);
        } finally {
            setLoading(false);
        }
    }, [page, rowsPerPage]);

    // Initial fetch + auto-polling every 30 seconds
    useEffect(() => {
        fetchMetrics();
        const interval = setInterval(fetchMetrics, 30000);
        return () => clearInterval(interval);
    }, [fetchMetrics]);

    return (
        <Box>
            <Box
                sx={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    mb: 3,
                }}
            >
                <Box>
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5 }}>
                        <Typography variant="h5" sx={{ fontWeight: 700, color: 'text.primary' }}>
                            Resource Usage
                        </Typography>
                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
                            <FiberManualRecordIcon
                                sx={{
                                    fontSize: 10,
                                    color: 'success.main',
                                    animation: 'pulse 2s infinite',
                                    '@keyframes pulse': {
                                        '0%, 100%': { opacity: 1 },
                                        '50%': { opacity: 0.4 },
                                    },
                                }}
                            />
                            <Typography
                                variant="caption"
                                sx={{ color: 'success.main', fontWeight: 600 }}
                            >
                                Live
                            </Typography>
                        </Box>
                    </Box>
                    <Typography variant="body2" sx={{ color: 'text.secondary', mt: 0.5 }}>
                        View resource consumption per tenant against plan limits — auto-refreshes
                        every 30s
                    </Typography>
                </Box>
                <ExportButton resource="tenants" label="Export Data" />
            </Box>

            {loading ? (
                <Grid container spacing={3}>
                    {[1, 2, 3].map((i) => (
                        <Grid item xs={12} sm={6} md={6} lg={4} key={i}>
                            <Skeleton variant="rounded" height={320} />
                        </Grid>
                    ))}
                </Grid>
            ) : metrics.length === 0 ? (
                <Paper sx={{ p: 6, textAlign: 'center', boxShadow: 1 }}>
                    <StorageIcon sx={{ fontSize: 48, color: 'grey.300', mb: 2 }} />
                    <Typography
                        variant="body1"
                        sx={{ fontWeight: 600, color: 'text.secondary', mb: 1 }}
                    >
                        No resource data yet
                    </Typography>
                    <Typography
                        variant="body2"
                        sx={{ color: 'text.disabled', maxWidth: 480, mx: 'auto' }}
                    >
                        Resource usage data is collected automatically in real-time as tenants
                        create users, products, and orders. Data will appear here as soon as
                        activity occurs.
                    </Typography>
                </Paper>
            ) : (
                <>
                    <Grid container spacing={3}>
                        {metrics.map((m) => (
                            <Grid item xs={12} sm={6} md={6} lg={4} key={m.id}>
                                <TenantUsageCard metric={m} />
                            </Grid>
                        ))}
                    </Grid>
                    {total > 0 && (
                        <Paper sx={{ mt: 3, boxShadow: 1 }}>
                            <TablePagination
                                component="div"
                                count={total}
                                page={page}
                                onPageChange={(_, newPage) => setPage(newPage)}
                                rowsPerPage={rowsPerPage}
                                onRowsPerPageChange={(e) => {
                                    setRowsPerPage(parseInt(e.target.value, 10));
                                    setPage(0);
                                }}
                                rowsPerPageOptions={[10, 25, 50]}
                            />
                        </Paper>
                    )}
                </>
            )}
        </Box>
    );
}
