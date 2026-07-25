import React, { useEffect, useState, useCallback, useRef } from 'react';
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
import CircularProgress from '@mui/material/CircularProgress';
import StorageIcon from '@mui/icons-material/Storage';
import FiberManualRecordIcon from '@mui/icons-material/FiberManualRecord';
import DnsIcon from '@mui/icons-material/Dns';
import { alpha } from '@mui/material/styles';

const PAGE_SIZE = 5;

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
    const [allMetrics, setAllMetrics] = useState([]);
    const [loading, setLoading] = useState(true);
    const [loadingMore, setLoadingMore] = useState(false);
    const [page, setPage] = useState(0);
    const [hasMore, setHasMore] = useState(true);
    const sentinelRef = useRef(null);

    const fetchPage = useCallback(async (pageNum) => {
        const res = await api.get(
            `/admin/api/resource-usage?page=${pageNum + 1}&per_page=${PAGE_SIZE}`
        );
        return {
            metrics: res.data.metrics,
            lastPage: res.data.last_page,
        };
    }, []);

    const loadInitial = useCallback(async () => {
        setLoading(true);
        try {
            const { metrics, lastPage } = await fetchPage(0);
            setAllMetrics(metrics);
            setPage(0);
            setHasMore(0 < lastPage);
        } catch (err) {
            console.error('Failed to fetch resource usage:', err);
        } finally {
            setLoading(false);
        }
    }, [fetchPage]);

    const loadMore = useCallback(async () => {
        if (loadingMore || !hasMore) return;
        setLoadingMore(true);
        try {
            const nextPage = page + 1;
            const { metrics, lastPage } = await fetchPage(nextPage);
            setAllMetrics((prev) => [...prev, ...metrics]);
            setPage(nextPage);
            setHasMore(nextPage < lastPage);
        } catch (err) {
            console.error('Failed to load more resource usage:', err);
        } finally {
            setLoadingMore(false);
        }
    }, [page, loadingMore, hasMore, fetchPage]);

    // Initial fetch
    useEffect(() => {
        loadInitial();
    }, [loadInitial]);

    // Auto-refresh every 30 seconds (resets to page 0)
    useEffect(() => {
        const interval = setInterval(loadInitial, 30000);
        return () => clearInterval(interval);
    }, [loadInitial]);

    // IntersectionObserver for infinite scroll
    useEffect(() => {
        const sentinel = sentinelRef.current;
        if (!sentinel || !hasMore) return;

        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    loadMore();
                }
            },
            { threshold: 0.1 }
        );

        observer.observe(sentinel);
        return () => observer.disconnect();
    }, [hasMore, loadMore]);

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
            ) : allMetrics.length === 0 ? (
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
                        {allMetrics.map((m) => (
                            <Grid item xs={12} sm={6} md={6} lg={4} key={m.id}>
                                <TenantUsageCard metric={m} />
                            </Grid>
                        ))}
                    </Grid>
                    {hasMore && <Box ref={sentinelRef} sx={{ height: 1 }} />}
                    {loadingMore && (
                        <Box sx={{ display: 'flex', justifyContent: 'center', mt: 3 }}>
                            <CircularProgress size={28} />
                        </Box>
                    )}
                </>
            )}
        </Box>
    );
}
