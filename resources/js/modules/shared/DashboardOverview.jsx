import React, { useEffect, useState } from 'react';
import api from '../../services/api';
import { useAuthContext } from '../../context/AuthContext';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Paper from '@mui/material/Paper';
import Typography from '@mui/material/Typography';
import Grid from '@mui/material/Grid';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Skeleton from '@mui/material/Skeleton';
import Avatar from '@mui/material/Avatar';
import Chip from '@mui/material/Chip';
import MuiTooltip from '@mui/material/Tooltip';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableContainer from '@mui/material/TableContainer';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';
import TablePagination from '@mui/material/TablePagination';
import Switch from '@mui/material/Switch';
import FormControlLabel from '@mui/material/FormControlLabel';
import useMediaQuery from '@mui/material/useMediaQuery';
import { alpha, useTheme } from '@mui/material/styles';
import PeopleIcon from '@mui/icons-material/People';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import BlockIcon from '@mui/icons-material/Block';
import DeleteIcon from '@mui/icons-material/Delete';
import GroupIcon from '@mui/icons-material/Group';
import AssignmentIcon from '@mui/icons-material/Assignment';
import TrendingUpIcon from '@mui/icons-material/TrendingUp';
import TrendingDownIcon from '@mui/icons-material/TrendingDown';
import {
    Chart as ChartJS,
    ArcElement,
    CategoryScale,
    Filler,
    LinearScale,
    LineElement,
    PointElement,
    Tooltip as ChartTooltip,
} from 'chart.js';
import { Doughnut, Line } from 'react-chartjs-2';

ChartJS.register(ArcElement, CategoryScale, Filler, LinearScale, LineElement, PointElement, ChartTooltip);

const StatCard = ({ title, value, icon, color, subtitle, trend }) => {
    return (
        <Card
            sx={{
                height: '100%',
                boxShadow: 1,
                borderLeft: '4px solid',
                borderColor: color,
                borderRadius: 1,
                transition: 'box-shadow 300ms cubic-bezier(0.22, 1, 0.36, 1), transform 300ms cubic-bezier(0.22, 1, 0.36, 1)',
                '&:hover': {
                    boxShadow: 4,
                    transform: 'translateY(-2px)',
                },
            }}
        >
            <CardContent sx={{ display: 'flex', alignItems: 'center', gap: 2, p: 3 }}>
                <Avatar
                    sx={{
                        bgcolor: alpha(color, 0.12),
                        color,
                        width: 48,
                        height: 48,
                    }}
                >
                    {icon}
                </Avatar>
                <Box sx={{ minWidth: 0 }}>
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.75 }}>
                        <Typography variant="h4" sx={{ color: 'text.primary', lineHeight: 1.2 }}>
                            {value}
                        </Typography>
                        {trend && (
                            <Box
                                component="span"
                                sx={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 0.25,
                                    color: trend.direction === 'up' ? 'success.main' : 'error.main',
                                    fontSize: 12,
                                    fontWeight: 700,
                                }}
                            >
                                {trend.direction === 'up'
                                    ? <TrendingUpIcon sx={{ fontSize: 14 }} />
                                    : <TrendingDownIcon sx={{ fontSize: 14 }} />}
                                {trend.percentage}%
                            </Box>
                        )}
                    </Box>
                    <Typography variant="body2" color="text.secondary">
                        {title}
                    </Typography>
                    {trend && (
                        <Typography variant="caption" color="text.secondary" sx={{ display: 'block', mt: 0.25 }}>
                            vs last month
                        </Typography>
                    )}
                    {subtitle && (
                        <Typography variant="caption" color="text.secondary" sx={{ display: 'block', mt: trend ? 0 : 0.25 }}>
                            {subtitle}
                        </Typography>
                    )}
                </Box>
            </CardContent>
        </Card>
    );
};

export default function DashboardOverview() {
    const theme = useTheme();
    const isMobile = useMediaQuery(theme.breakpoints.down('sm'));
    const isTablet = useMediaQuery(theme.breakpoints.down('md'));
    const { permissions = [] } = useAuthContext();
    const canViewStats = permissions.includes('view tenants');
    const [loading, setLoading] = useState(true);
    const [stats, setStats] = useState(null);
    const [recentTenants, setRecentTenants] = useState([]);
    const [tenantsByMonth, setTenantsByMonth] = useState([]);
    const [statusDistribution, setStatusDistribution] = useState([]);
    const [page, setPage] = useState(0);
    const [rowsPerPage] = useState(5);
    const [showDeleted, setShowDeleted] = useState(false);

    useEffect(() => {
        if (!canViewStats) {
            setLoading(false);
            return;
        }
        fetchStats();
    }, [showDeleted, canViewStats]);

    const fetchStats = async () => {
        try {
            const params = showDeleted ? '?trashed=1' : '';
            const res = await api.get(`/admin/api/dashboard-stats${params}`);
            setStats(res.data.stats);
            setRecentTenants(res.data.recent_tenants);
            setTenantsByMonth(res.data.tenants_by_month);
            setStatusDistribution(res.data.status_distribution);
        } catch (err) {
            console.error('Failed to fetch dashboard stats:', err);
        } finally {
            setLoading(false);
        }
    };

    if (!canViewStats) {
        return (
            <Box>
                <Typography variant="h5" sx={{ fontWeight: 700, color: 'text.primary', mb: 3 }}>
                    Dashboard Overview
                </Typography>
                <Alert severity="info">
                    You need the <strong>view tenants</strong> permission to view dashboard statistics.
                </Alert>
            </Box>
        );
    }

    if (loading) {
        return (
            <Box>
                <Grid container spacing={3} sx={{ mb: 4 }}>
                    {[1, 2, 3, 4, 5].map((i) => (
                        <Grid item xs={12} sm={6} md={4} lg={2.4} key={i}>
                            <Skeleton variant="rounded" height={96} />
                        </Grid>
                    ))}
                </Grid>
                <Grid container spacing={3}>
                    <Grid item xs={12} sm={12} md={8} lg={8}>
                        <Skeleton variant="rounded" height={320} />
                    </Grid>
                    <Grid item xs={12} sm={12} md={4} lg={4}>
                        <Skeleton variant="rounded" height={320} />
                    </Grid>
                </Grid>
            </Box>
        );
    }

    const monthLabels = {
        '01': 'Jan', '02': 'Feb', '03': 'Mar', '04': 'Apr', '05': 'May', '06': 'Jun',
        '07': 'Jul', '08': 'Aug', '09': 'Sep', '10': 'Oct', '11': 'Nov', '12': 'Dec',
    };

    const formattedMonthlyData = tenantsByMonth.map((item) => {
        const [year, month] = item.month.split('-');
        return {
            name: `${monthLabels[month]} ${year.slice(2)}`,
            Tenants: item.count,
        };
    });

    const chartHeight = isMobile ? 220 : isTablet ? 260 : 300;

    const CHART_COLORS = [
        theme.palette.primary.main,
        theme.palette.success.main,
        theme.palette.warning.main,
        theme.palette.error.main,
        theme.palette.secondary.light,
        theme.palette.text.disabled,
    ];

    const statusChipSx = {
        Active: {
            bgcolor: alpha(theme.palette.success.main, 0.12),
            color: theme.palette.success.dark,
            fontWeight: 600,
            height: 24,
            fontSize: 12,
        },
        Trial: {
            bgcolor: alpha(theme.palette.warning.main, 0.16),
            color: theme.palette.warning.dark,
            fontWeight: 600,
            height: 24,
            fontSize: 12,
        },
        Suspended: {
            bgcolor: alpha(theme.palette.error.main, 0.12),
            color: theme.palette.error.dark,
            fontWeight: 600,
            height: 24,
            fontSize: 12,
        },
        Deleted: {
            bgcolor: alpha(theme.palette.text.secondary, 0.1),
            color: theme.palette.text.secondary,
            fontWeight: 600,
            height: 24,
            fontSize: 12,
            fontStyle: 'italic',
        },
    };

    const growthChartData = {
        labels: formattedMonthlyData.map((item) => item.name),
        datasets: [
            {
                label: 'Tenants',
                data: formattedMonthlyData.map((item) => item.Tenants),
                borderColor: theme.palette.primary.main,
                borderWidth: 2,
                pointRadius: 0,
                tension: 0.4,
                fill: true,
                backgroundColor: (context) => {
                    const { ctx, chartArea } = context.chart;
                    if (!chartArea) {
                        return 'transparent';
                    }
                    const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                    gradient.addColorStop(0.05, alpha(theme.palette.primary.main, 0.22));
                    gradient.addColorStop(0.95, alpha(theme.palette.primary.main, 0.02));
                    return gradient;
                },
            },
        ],
    };

    const growthChartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: theme.palette.background.paper,
                titleColor: theme.palette.text.primary,
                bodyColor: theme.palette.text.primary,
                borderColor: theme.palette.divider,
                borderWidth: 1,
                cornerRadius: 8,
                padding: 10,
                displayColors: false,
                titleFont: { size: 13 },
                bodyFont: { size: 13 },
            },
        },
        scales: {
            x: {
                grid: { display: false },
                border: { display: false },
                ticks: { color: theme.palette.text.disabled, font: { size: 11 } },
            },
            y: {
                beginAtZero: true,
                grid: { color: theme.palette.background.default, tickBorderDash: [3, 3] },
                border: { display: false },
                ticks: { color: theme.palette.text.disabled, font: { size: 11 }, precision: 0 },
            },
        },
    };

    const statusChartData = {
        labels: statusDistribution.map((item) => item.name),
        datasets: [
            {
                data: statusDistribution.map((item) => item.value),
                backgroundColor: statusDistribution.map((_, index) => CHART_COLORS[index % CHART_COLORS.length]),
                borderColor: theme.palette.background.paper,
                borderWidth: 4,
            },
        ],
    };

    const statusChartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '65%',
        plugins: {
            legend: { display: false },
            tooltip: {
                displayColors: true,
                padding: 8,
                cornerRadius: 8,
            },
        },
    };

    const paginatedTenants = recentTenants.slice(page * rowsPerPage, page * rowsPerPage + rowsPerPage);

    const handleChangePage = (event, newPage) => {
        setPage(newPage);
    };

    return (
        <Box>
            <Grid container spacing={2} sx={{ mb: 4 }}>
                <Grid item xs={6} sm={6} md={4} lg={2.4}>
                    <StatCard
                        title="Total Tenants"
                        value={stats?.total_tenants ?? 0}
                        icon={<PeopleIcon />}
                        color={theme.palette.primary.main}
                        subtitle="All workspaces"
                    />
                </Grid>
                <Grid item xs={6} sm={6} md={4} lg={2.4}>
                    <StatCard
                        title="Active Tenants"
                        value={stats?.active_tenants ?? 0}
                        icon={<CheckCircleIcon />}
                        color={theme.palette.success.main}
                        subtitle="Currently serving"
                    />
                </Grid>
                <Grid item xs={6} sm={6} md={4} lg={2.4}>
                    <StatCard
                        title="Suspended"
                        value={stats?.suspended_tenants ?? 0}
                        icon={<BlockIcon />}
                        color={theme.palette.error.main}
                        subtitle="Needs attention"
                    />
                </Grid>
                <Grid item xs={6} sm={6} md={4} lg={2.4}>
                    <StatCard
                        title="Deleted"
                        value={stats?.deleted_tenants ?? 0}
                        icon={<DeleteIcon />}
                        color={theme.palette.text.secondary}
                        subtitle="Data preserved"
                    />
                </Grid>
                <Grid item xs={6} sm={6} md={4} lg={2.4}>
                    <StatCard
                        title="Staff"
                        value={stats?.total_staff ?? 0}
                        icon={<GroupIcon />}
                        color={theme.palette.warning.main}
                        subtitle={`${stats?.active_staff ?? 0} active`}
                    />
                </Grid>
                <Grid item xs={12} sm={6} md={4} lg={2.4}>
                    <StatCard
                        title="Plans"
                        value={stats?.total_plans ?? 0}
                        icon={<AssignmentIcon />}
                        color={theme.palette.secondary.main}
                        subtitle="Configured"
                    />
                </Grid>
            </Grid>

            <Grid container spacing={3} sx={{ mb: 4 }}>
                <Grid item xs={12} sm={12} md={8} lg={8}>
                    <Paper sx={{
                        p: 3,
                        boxShadow: 1,
                        borderTop: '3px solid',
                        borderColor: 'primary.main',
                        borderRadius: 1,
                    }}>
                        <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 2, color: 'text.primary', display: 'flex', alignItems: 'center', gap: 1 }}>
                            <Box sx={{ width: 8, height: 8, borderRadius: '50%', bgcolor: 'primary.main' }} />
                            Tenant Growth
                        </Typography>
                        {tenantsByMonth.length === 0 ? (
                            <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'center', height: chartHeight }}>
                                <Typography variant="body2" color="text.secondary">
                                    No data yet
                                </Typography>
                            </Box>
                        ) : (
                            <Box
                                sx={{ position: 'relative', height: chartHeight }}
                                role="img"
                                aria-label="Tenant growth by month"
                            >
                                <Line data={growthChartData} options={growthChartOptions} />
                            </Box>
                        )}
                    </Paper>
                </Grid>
                <Grid item xs={12} sm={12} md={4} lg={4}>
                    <Paper sx={{
                        p: 3,
                        boxShadow: 1,
                        borderTop: '3px solid',
                        borderColor: 'success.main',
                        borderRadius: 1,
                        height: '100%',
                    }}>
                        <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 1, color: 'text.primary', display: 'flex', alignItems: 'center', gap: 1 }}>
                            <Box sx={{ width: 8, height: 8, borderRadius: '50%', bgcolor: 'success.main' }} />
                            Tenant Status
                        </Typography>
                        {statusDistribution.every((s) => s.value === 0) ? (
                            <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'center', height: chartHeight }}>
                                <Typography variant="body2" color="text.secondary">
                                    No data yet
                                </Typography>
                            </Box>
                        ) : (
                            <Box sx={{ display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
                                <Box
                                    sx={{ position: 'relative', width: '100%', height: chartHeight * 0.7 }}
                                    role="img"
                                    aria-label="Distribution of tenants by status"
                                >
                                    <Doughnut data={statusChartData} options={statusChartOptions} />
                                </Box>
                                <Box sx={{ display: 'flex', gap: 3, mt: 1 }}>
                                    {statusDistribution.map((item, index) => (
                                        <Box key={item.name} sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                            <Box sx={{ width: 10, height: 10, borderRadius: '50%', bgcolor: CHART_COLORS[index % CHART_COLORS.length] }} />
                                            <Typography variant="caption" color="text.secondary">
                                                {item.name} ({item.value})
                                            </Typography>
                                        </Box>
                                    ))}
                                </Box>
                            </Box>
                        )}
                    </Paper>
                </Grid>
            </Grid>

            <Paper sx={{
                boxShadow: 1,
                borderTop: '3px solid',
                borderColor: 'secondary.main',
                borderRadius: 1,
                overflow: 'hidden',
            }}>
                <Box sx={{ p: 3, pb: 1, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <Typography variant="subtitle1" sx={{ fontWeight: 700, color: 'text.primary', display: 'flex', alignItems: 'center', gap: 1 }}>
                        <Box sx={{ width: 8, height: 8, borderRadius: '50%', bgcolor: 'secondary.main' }} />
                        Recent Tenants
                    </Typography>
                    <FormControlLabel
                        control={<Switch size="small" checked={showDeleted} onChange={() => setShowDeleted(s => !s)} />}
                        label={<Typography variant="caption" sx={{ color: 'text.secondary' }}>Show deleted</Typography>}
                        sx={{ mr: 0 }}
                    />
                </Box>
                <TableContainer>
                    <Table size="small">
                        <TableHead>
                            <TableRow sx={{ bgcolor: 'background.default', borderBottom: `1px solid ${theme.palette.divider}` }}>
                                <TableCell sx={{ fontWeight: 700, fontSize: '11px', color: 'text.secondary', textTransform: 'uppercase', letterSpacing: '0.04em' }}>Name</TableCell>
                                <TableCell sx={{ fontWeight: 700, fontSize: '11px', color: 'text.secondary', textTransform: 'uppercase', letterSpacing: '0.04em' }}>Domain</TableCell>
                                <TableCell sx={{ fontWeight: 700, fontSize: '11px', color: 'text.secondary', textTransform: 'uppercase', letterSpacing: '0.04em' }}>Status</TableCell>
                                <TableCell sx={{ fontWeight: 700, fontSize: '11px', color: 'text.secondary', textTransform: 'uppercase', letterSpacing: '0.04em' }}>Created</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {paginatedTenants.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={4} sx={{ textAlign: 'center', py: 4, color: 'text.secondary' }}>
                                        No tenants yet
                                    </TableCell>
                                </TableRow>
                            ) : (
                                paginatedTenants.map((tenant) => (
                                    <TableRow
                                        key={tenant.domain}
                                        sx={{
                                            '&:last-child td, &:last-child th': { border: 0 },
                                            transition: 'background-color 0.15s ease',
                                            '&:hover': { bgcolor: 'background.default' },
                                        }}
                                    >
                                        <TableCell sx={{ fontWeight: 600, fontSize: 13 }}>{tenant.name}</TableCell>
                                        <TableCell>
                                            <Typography variant="body2" sx={{ fontFamily: 'monospace', fontSize: 12, color: 'text.secondary' }}>
                                                {tenant.domain}
                                            </Typography>
                                        </TableCell>
                                        <TableCell>
                                            {tenant.status === 'Trial' ? (
                                                <MuiTooltip
                                                    title={tenant.trial_ends_at
                                                        ? `Trial - temporary full access until ${new Date(tenant.trial_ends_at).toLocaleDateString()}`
                                                        : 'Trial - temporary full access'}
                                                >
                                                    <Chip
                                                        label={tenant.status}
                                                        size="small"
                                                        sx={statusChipSx.Trial}
                                                    />
                                                </MuiTooltip>
                                            ) : (
                                                <Chip
                                                    label={tenant.status}
                                                    size="small"
                                                    sx={statusChipSx[tenant.status] ?? statusChipSx.Deleted}
                                                />
                                            )}
                                        </TableCell>
                                        <TableCell sx={{ color: 'text.secondary', fontSize: 13 }}>
                                            {tenant.created_at}
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </TableContainer>
                <TablePagination
                    component="div"
                    count={recentTenants.length}
                    page={page}
                    onPageChange={handleChangePage}
                    rowsPerPage={rowsPerPage}
                    rowsPerPageOptions={[5]}
                    sx={{ borderTop: '1px solid', borderColor: 'divider', bgcolor: 'background.default' }}
                />
            </Paper>
        </Box>
    );
}
