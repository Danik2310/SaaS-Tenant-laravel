import React, { useEffect, useState } from 'react';
import api from '@/services/api';
import DataTable from '@/components/DataTable';
import ExportButton from '@/components/ExportButton';
import {
    Box,
    Typography,
    Paper,
    Chip,
    LinearProgress,
    Tooltip,
    Grid,
    Card,
    CardContent,
    Avatar,
    Skeleton,
} from '@mui/material';
import StorageIcon from '@mui/icons-material/Storage';
import PeopleIcon from '@mui/icons-material/People';
import InventoryIcon from '@mui/icons-material/Inventory';
import ShoppingCartIcon from '@mui/icons-material/ShoppingCart';
import DnsIcon from '@mui/icons-material/Dns';

function UsageBar({ current, limit, label, color = '#3b82f6' }) {
    const hasLimit = limit !== null && limit !== undefined;
    const percentage = hasLimit && limit > 0 ? Math.min((current / limit) * 100, 100) : 0;
    const barColor = percentage >= 90 ? '#ef4444' : percentage >= 75 ? '#f59e0b' : color;

    return (
        <Box sx={{ mb: 1.5 }}>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 0.5 }}>
                <Typography variant="caption" sx={{ color: '#64748b', fontWeight: 500 }}>{label}</Typography>
                <Typography variant="caption" sx={{ color: '#475569', fontWeight: 600 }}>
                    {current.toLocaleString()}
                    {hasLimit ? ` / ${limit === 2147483647 ? '∞' : limit.toLocaleString()}` : ''}
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
                            bgcolor: '#f1f5f9',
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
                    sx={{ height: 8, borderRadius: 4, bgcolor: '#f1f5f9' }}
                />
            )}
        </Box>
    );
}

function TenantUsageCard({ metric, limits }) {
    const usage = metric?.usage;
    const tenant = metric?.tenant || {};
    const planLimits = metric?.limits || limits || {};

    return (
        <Paper sx={{ p: 3, boxShadow: '0 1px 3px rgba(0,0,0,0.06)', borderRadius: '8px' }}>
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 2, mb: 2 }}>
                <Avatar sx={{ bgcolor: '#e0f2fe', color: '#0369a1' }}>
                    <DnsIcon />
                </Avatar>
                <Box>
                    <Typography variant="subtitle2" sx={{ fontWeight: 700, color: '#0f172a' }}>
                        {tenant.name || 'Unknown'}
                    </Typography>
                    <Typography variant="caption" sx={{ color: '#64748b' }}>
                        {tenant.plan_name || 'No Plan'} · {tenant.email || ''}
                    </Typography>
                </Box>
                <Box sx={{ ml: 'auto' }}>
                    <Chip
                        label={tenant.status || 'N/A'}
                        size="small"
                        sx={{
                            bgcolor: tenant.status === 'Active' ? '#dcfce7' : '#fee2e2',
                            color: tenant.status === 'Active' ? '#166534' : '#991b1b',
                            fontWeight: 600,
                        }}
                    />
                </Box>
            </Box>

            {usage ? (
                <>
                    <UsageBar
                        current={usage.users_count}
                        limit={planLimits.max_users}
                        label="Users"
                        color="#3b82f6"
                    />
                    <UsageBar
                        current={Math.round(usage.storage_kb / 1024)}
                        limit={planLimits.max_storage}
                        label="Storage (MB)"
                        color="#8b5cf6"
                    />
                    <UsageBar
                        current={Math.round(usage.db_size_kb / 1024)}
                        limit={null}
                        label="Database (MB)"
                        color="#f59e0b"
                    />
                    <UsageBar
                        current={usage.products_count}
                        limit={null}
                        label="Products"
                        color="#22c55e"
                    />
                    <UsageBar
                        current={usage.orders_count}
                        limit={null}
                        label="Orders"
                        color="#ec4899"
                    />
                    {usage.collected_at && (
                        <Typography variant="caption" sx={{ color: '#94a3b8', mt: 1, display: 'block' }}>
                            Last updated: {new Date(usage.collected_at).toLocaleString()}
                        </Typography>
                    )}
                </>
            ) : (
                <Typography variant="body2" sx={{ color: '#94a3b8', textAlign: 'center', py: 4 }}>
                    No metrics collected yet. Run the metrics collection command.
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

    useEffect(() => {
        fetchMetrics();
    }, [page, rowsPerPage]);

    const fetchMetrics = async () => {
        setLoading(true);
        try {
            const res = await api.get(`/admin/api/resource-usage?page=${page + 1}&per_page=${rowsPerPage}`);
            setMetrics(res.data.metrics);
            setTotal(res.data.total);
        } catch (err) {
            console.error('Failed to fetch resource usage:', err);
        } finally {
            setLoading(false);
        }
    };

    return (
        <Box>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 3 }}>
                <Box>
                    <Typography variant="h5" sx={{ fontWeight: 700, color: '#0f172a' }}>
                        Resource Usage
                    </Typography>
                    <Typography variant="body2" sx={{ color: '#64748b', mt: 0.5 }}>
                        View resource consumption per tenant against plan limits
                    </Typography>
                </Box>
                <ExportButton resource="tenants" label="Export Data" />
            </Box>

            {loading ? (
                <Grid container spacing={3}>
                    {[1, 2, 3].map((i) => (
                        <Grid item xs={12} md={6} lg={4} key={i}>
                            <Skeleton variant="rounded" height={320} />
                        </Grid>
                    ))}
                </Grid>
            ) : metrics.length === 0 ? (
                <Paper sx={{ p: 6, textAlign: 'center', boxShadow: '0 1px 3px rgba(0,0,0,0.06)' }}>
                    <StorageIcon sx={{ fontSize: 48, color: '#cbd5e1', mb: 2 }} />
                    <Typography variant="body1" sx={{ fontWeight: 600, color: '#64748b', mb: 1 }}>
                        No resource data collected yet
                    </Typography>
                    <Typography variant="body2" sx={{ color: '#94a3b8' }}>
                        Run <code style={{ background: '#f1f5f9', padding: '2px 6px', borderRadius: 3 }}>php artisan tenants:collect-metrics</code> from the command line to collect usage data.
                    </Typography>
                </Paper>
            ) : (
                <Grid container spacing={3}>
                    {metrics.map((m) => (
                        <Grid item xs={12} md={6} lg={4} key={m.id}>
                            <TenantUsageCard metric={m} />
                        </Grid>
                    ))}
                </Grid>
            )}

            {total > rowsPerPage && (
                <Box sx={{ display: 'flex', justifyContent: 'center', mt: 3 }}>
                    <DataTable
                        columns={[]}
                        data={[]}
                        total={total}
                        page={page}
                        rowsPerPage={rowsPerPage}
                        onPageChange={setPage}
                        onRowsPerPageChange={(n) => { setRowsPerPage(n); setPage(0); }}
                        loading={false}
                    />
                </Box>
            )}
        </Box>
    );
}
