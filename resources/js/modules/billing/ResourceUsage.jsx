import React, { useEffect, useState, useCallback } from 'react';
import api from '@/services/api';
import ExportButton from '@/Components/ExportButton';
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
import Collapse from '@mui/material/Collapse';
import IconButton from '@mui/material/IconButton';
import StorageIcon from '@mui/icons-material/Storage';
import FiberManualRecordIcon from '@mui/icons-material/FiberManualRecord';
import DnsIcon from '@mui/icons-material/Dns';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import ExpandLessIcon from '@mui/icons-material/ExpandLess';
import { alpha } from '@mui/material/styles';
import { AreaChart, Area, ResponsiveContainer, Tooltip as RechartsTooltip } from 'recharts';

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

function Sparkline({ data, dataKey = 'storage_kb', color = '#6366f1' }) {
    if (!data || data.length < 2) {
        return null;
    }

    return (
        <Box sx={{ mt: 1, mb: 1 }}>
            <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 500, display: 'block', mb: 0.5 }}>
                Storage trend (30d)
            </Typography>
            <ResponsiveContainer width="100%" height={60}>
                <AreaChart data={data} margin={{ top: 0, right: 0, bottom: 0, left: 0 }}>
                    <defs>
                        <linearGradient id={`gradient-${dataKey}`} x1="0" y1="0" x2="0" y2="1">
                            <stop offset="5%" stopColor={color} stopOpacity={0.2} />
                            <stop offset="95%" stopColor={color} stopOpacity={0} />
                        </linearGradient>
                    </defs>
                    <RechartsTooltip
                        contentStyle={{ fontSize: 11, padding: '4px 8px', borderRadius: 4 }}
                        formatter={(value) => `${Math.round(value / 1024)} MB`}
                        labelFormatter={(label) => new Date(label).toLocaleDateString()}
                    />
                    <Area
                        type="monotone"
                        dataKey={dataKey}
                        stroke={color}
                        strokeWidth={1.5}
                        fill={`url(#gradient-${dataKey})`}
                        dot={false}
                        activeDot={{ r: 3, strokeWidth: 0 }}
                    />
                </AreaChart>
            </ResponsiveContainer>
        </Box>
    );
}

function UsageSparklineCard({ tenantId }) {
    const [history, setHistory] = useState(null);
    const [open, setOpen] = useState(false);

    const fetchHistory = useCallback(async () => {
        try {
            const res = await api.get(`/admin/api/resource-usage/${tenantId}/history?days=30`);
            setHistory(res.data.history);
        } catch {
            setHistory([]);
        }
    }, [tenantId]);

    useEffect(() => {
        if (open && history === null) {
            fetchHistory();
        }
    }, [open, history, fetchHistory]);

    return (
        <Box>
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
                <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 500 }}>
                    Usage history
                </Typography>
                <IconButton
                    size="small"
                    onClick={() => setOpen(!open)}
                    sx={{ p: 0.2 }}
                >
                    {open ? <ExpandLessIcon fontSize="small" /> : <ExpandMoreIcon fontSize="small" />}
                </IconButton>
            </Box>
            <Collapse in={open}>
                {history === null ? (
                    <Skeleton variant="rounded" height={60} sx={{ mt: 1 }} />
                ) : (
                    <Sparkline data={history} dataKey="storage_kb" color="#6366f1" />
                )}
            </Collapse>
        </Box>
    );
}

function ServerSummaryCard({ server, metrics }) {
    if (!server) {
        return null;
    }

    const totalTenants = metrics?.length || 0;
    const totalUsers = metrics?.reduce((s, m) => s + (m.users_count || 0), 0) || 0;
    const totalStorage = metrics?.reduce((s, m) => s + (m.storage_kb || 0), 0) || 0;

    return (
        <Paper sx={{ p: 3, boxShadow: 1, borderRadius: 1, mb: 3 }}>
            <Typography variant="subtitle2" sx={{ fontWeight: 700, color: 'text.primary', mb: 2 }}>
                Server Overview
            </Typography>
            <Box sx={{ display: 'flex', gap: 4, flexWrap: 'wrap' }}>
                <Box>
                    <Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 500 }}>
                        Tenants shown
                    </Typography>
                    <Typography variant="body2" sx={{ fontWeight: 600, color: 'text.primary' }}>
                        {totalTenants}
                    </Typography>
                </Box>
                <Box>
                    <Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 500 }}>
                        Total users
                    </Typography>
                    <Typography variant="body2" sx={{ fontWeight: 600, color: 'text.primary' }}>
                        {totalUsers.toLocaleString()}
                    </Typography>
                </Box>
                <Box>
                    <Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 500 }}>
                        Total storage
                    </Typography>
                    <Typography variant="body2" sx={{ fontWeight: 600, color: 'text.primary' }}>
                        {Math.round(totalStorage / 1024).toLocaleString()} MB
                    </Typography>
                </Box>
                {server.disk_total_gb != null && (
                    <Box sx={{ flex: 1, minWidth: 200 }}>
                        <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 0.5 }}>
                            <Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 500 }}>
                                {server.disk_label} disk
                            </Typography>
                            <Typography variant="caption" sx={{ color: 'text.primary', fontWeight: 600 }}>
                                {server.disk_used_gb} GB / {server.disk_total_gb} GB ({server.disk_free_gb} GB free)
                            </Typography>
                        </Box>
                        {server.disk_pct != null && (
                            <Tooltip title={`${server.disk_pct.toFixed(0)}% used`}>
                                <LinearProgress
                                    variant="determinate"
                                    value={Math.min(server.disk_pct, 100)}
                                    sx={{
                                        height: 10,
                                        borderRadius: 5,
                                        bgcolor: 'grey.100',
                                        '& .MuiLinearProgress-bar': {
                                            bgcolor: server.disk_pct > 85 ? 'error.main' : server.disk_pct > 65 ? 'warning.main' : 'success.main',
                                            borderRadius: 5,
                                        },
                                    }}
                                />
                            </Tooltip>
                        )}
                    </Box>
                )}
            </Box>
        </Paper>
    );
}

function TenantUsageCard({ metric }) {
    const usage = metric?.usage;
    const tenant = metric?.tenant || {};
    const planLimits = metric?.limits || {};

    const statusColor = tenant.status === 'Active' ? 'success' : 'error';

    const subscriptionChip = () => {
        if (tenant.is_on_trial) {
            return <Chip label="Trial" size="small" color="warning" variant="outlined" sx={{ height: 20, fontSize: 10 }} />;
        }
        if (tenant.subscription_status === 'active') {
            return <Chip label="Active" size="small" color="success" variant="outlined" sx={{ height: 20, fontSize: 10 }} />;
        }
        if (tenant.subscription_status === 'cancelled') {
            return <Chip label="Canceled" size="small" color="error" variant="outlined" sx={{ height: 20, fontSize: 10 }} />;
        }
        return null;
    };

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
                <Box sx={{ minWidth: 0 }}>
                    <Typography variant="subtitle2" sx={{ fontWeight: 700, color: 'text.primary' }}>
                        {tenant.name || 'Unknown'}
                    </Typography>
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5, flexWrap: 'wrap' }}>
                        <Typography variant="caption" sx={{ color: 'text.secondary' }}>
                            {tenant.plan_name || 'No Plan'}
                        </Typography>
                        {subscriptionChip()}
                    </Box>
                    <Typography variant="caption" sx={{ color: 'text.disabled', display: 'block' }}>
                        {tenant.email || ''}
                    </Typography>
                </Box>
                <Box sx={{ ml: 'auto', display: 'flex', gap: 0.5, alignItems: 'center' }}>
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
                        limit={planLimits.max_products}
                        label="Products"
                        color="success.main"
                    />
                    <UsageBar
                        current={usage.orders_count}
                        limit={null}
                        label="Orders"
                        color="secondary.main"
                    />
                    <UsageBar
                        current={usage.warehouses_count}
                        limit={planLimits.max_warehouses}
                        label="Warehouses"
                        color="info.main"
                    />
                    <UsageBar
                        current={usage.categories_count}
                        limit={planLimits.max_categories}
                        label="Categories"
                        color="info.main"
                    />
                    <UsageSparklineCard tenantId={metric.tenant_id} />
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
    const [server, setServer] = useState(null);
    const [loading, setLoading] = useState(true);
    const [total, setTotal] = useState(0);
    const [page, setPage] = useState(0);
    const [rowsPerPage, setRowsPerPage] = useState(5);

    const fetchMetrics = useCallback(async () => {
        setLoading(true);
        try {
            const res = await api.get(
                `/admin/api/resource-usage?page=${page + 1}&per_page=${rowsPerPage}`
            );
            setMetrics(res.data.metrics);
            setTotal(res.data.total);
            if (res.data.server) {
                setServer(res.data.server);
            }
        } catch (err) {
            console.error('Failed to fetch resource usage:', err);
        } finally {
            setLoading(false);
        }
    }, [page, rowsPerPage]);

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
                            <Skeleton variant="rounded" height={480} />
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
                    <ServerSummaryCard server={server} metrics={metrics} />
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
                                rowsPerPageOptions={[5, 10, 25]}
                            />
                        </Paper>
                    )}
                </>
            )}
        </Box>
    );
}
