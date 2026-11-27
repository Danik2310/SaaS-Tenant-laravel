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
import TrendingFlatIcon from '@mui/icons-material/TrendingFlat';
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

const metricMotion = {
    '@keyframes overviewMetricIn': {
        from: { opacity: 0, transform: 'translateY(8px)' },
        to: { opacity: 1, transform: 'translateY(0)' },
    },
    '@keyframes overviewMetricFade': {
        from: { opacity: 0 },
        to: { opacity: 1 },
    },
    animation: 'overviewMetricIn 220ms cubic-bezier(0.16, 1, 0.3, 1) both',
};

const getTrendPresentation = (trend, invertTrend = false) => {
    const direction = trend?.direction ?? 'flat';
    const Icon = direction === 'up' ? TrendingUpIcon : direction === 'down' ? TrendingDownIcon : TrendingFlatIcon;
    const favorable = invertTrend ? direction === 'down' : direction === 'up';
    const color = direction === 'flat' ? 'text.secondary' : favorable ? 'success.main' : 'error.main';
    const text = trend?.percentage === null
        ? (direction === 'up' ? 'new' : '0%')
        : `${trend?.percentage ?? 0}%`;
    return { Icon, color, text };
};

const TrendBadge = ({ trend, invertTrend = false }) => {
    if (!trend) return null;
    const { Icon, color, text } = getTrendPresentation(trend, invertTrend);
    return (
        <Box
            component="span"
            sx={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 0.25,
                color,
                fontSize: 12,
                fontWeight: 700,
                whiteSpace: 'nowrap',
                fontVariantNumeric: 'tabular-nums',
            }}
        >
            <Icon sx={{ fontSize: 13 }} />
            {text}
        </Box>
    );
};

const SectionHeading = ({ index, title, aside }) => (
    <Box
        sx={{
            display: 'flex',
            alignItems: { xs: 'flex-start', sm: 'center' },
            flexWrap: 'wrap',
            columnGap: 1.5,
            rowGap: 0.75,
            mb: 2,
        }}
    >
        <Typography
            component="span"
            sx={{ color: 'primary.main', fontSize: 11, fontWeight: 700, letterSpacing: '0.12em', lineHeight: 1, flexShrink: 0 }}
        >
            {index}
        </Typography>
        <Typography variant="subtitle2" sx={{ color: 'text.primary', fontWeight: 700, minWidth: 0 }}>
            {title}
        </Typography>
        <Box sx={{ flex: '1 1 48px', minWidth: 32, height: '1px', bgcolor: 'divider' }} />
        {aside && <Box sx={{ minWidth: 0, maxWidth: { xs: '100%', sm: 'auto' } }}>{aside}</Box>}
    </Box>
);

const HeroMetric = ({ title, value, icon, color, subtitle, trend, invertTrend = false, testId, index = 0 }) => (
    <Card
        data-testid={testId ? `kpi-${testId}` : undefined}
        sx={{
            position: 'relative',
            minWidth: 0,
            overflow: 'hidden',
            border: '1px solid',
            borderColor: 'divider',
            borderRadius: 2,
            boxShadow: 'none',
            transition: 'box-shadow 180ms ease-out, transform 180ms ease-out',
            '&::before': {
                content: '""',
                position: 'absolute',
                inset: '0 auto 0 0',
                width: 3,
                bgcolor: color,
            },
            '&:hover': { boxShadow: 2, transform: 'translateY(-2px)' },
            ...metricMotion,
            animationDelay: `${index * 40}ms`,
            '@media (prefers-reduced-motion: reduce)': {
                animation: 'overviewMetricFade 140ms linear both',
                animationDelay: '0ms',
                transition: 'box-shadow 180ms ease-out',
                '&:hover': { transform: 'none' },
            },
        }}
    >
        <CardContent sx={{ p: { xs: 1.75, sm: 2, md: 2.5 }, '&:last-child': { pb: { xs: 1.75, sm: 2, md: 2.5 } } }}>
            <Box sx={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: { xs: 1, sm: 1.5 } }}>
                <Box sx={{ minWidth: 0 }}>
                    <Typography
                        variant="overline"
                        sx={{
                            display: 'block',
                            color: 'text.secondary',
                            fontSize: 10,
                            letterSpacing: '0.12em',
                            lineHeight: 1.5,
                            minWidth: 0,
                        }}
                    >
                        {title}
                    </Typography>
                    <Box
                        sx={{
                            display: 'flex',
                            alignItems: 'baseline',
                            columnGap: 0.75,
                            rowGap: 0.5,
                            mt: 0.5,
                            flexWrap: 'wrap',
                            minWidth: 0,
                        }}
                    >
                        <Typography
                            variant="h4"
                            component="span"
                            sx={{
                                fontSize: { xs: 28, sm: 32, md: 38 },
                                lineHeight: 1.1,
                                fontVariantNumeric: 'tabular-nums',
                            }}
                        >
                            {value}
                        </Typography>
                        <TrendBadge trend={trend} invertTrend={invertTrend} />
                    </Box>
                </Box>
                <Avatar
                    sx={{
                        bgcolor: alpha(color, 0.12),
                        color,
                        width: { xs: 32, sm: 34, md: 38 },
                        height: { xs: 32, sm: 34, md: 38 },
                        flexShrink: 0,
                        '& svg': { fontSize: { xs: 18, md: 20 } },
                    }}
                >
                    {icon}
                </Avatar>
            </Box>
            <Typography variant="caption" color="text.secondary" sx={{ display: 'block', mt: 1.25, minWidth: 0, lineHeight: 1.35 }}>
                {trend?.label ?? subtitle}
            </Typography>
        </CardContent>
    </Card>
);

const PulseItem = ({ title, value, icon, color, subtitle, trend, invertTrend = false, testId }) => (
    <Box
        data-testid={testId ? `kpi-${testId}` : undefined}
        sx={{
            display: 'flex',
            alignItems: { xs: 'flex-start', sm: 'center' },
            flexWrap: 'wrap',
            columnGap: { xs: 1, sm: 1.25 },
            rowGap: { xs: 0.5, sm: 0 },
            minWidth: 0,
            px: { xs: 1.5, sm: 1.75 },
            py: 1.5,
            border: '1px solid',
            borderColor: 'divider',
            borderRadius: 1.5,
            bgcolor: 'background.paper',
            transition: 'background-color 150ms ease-out',
            '&:hover': { bgcolor: 'background.default' },
        }}
    >
        <Avatar
            sx={{
                bgcolor: alpha(color, 0.1),
                color,
                width: { xs: 30, sm: 34 },
                height: { xs: 30, sm: 34 },
                flexShrink: 0,
                '& svg': { fontSize: { xs: 17, sm: 20 } },
            }}
        >
            {icon}
        </Avatar>
        <Box sx={{ minWidth: 0, flex: '1 1 120px' }}>
            <Typography
                variant="caption"
                color="text.secondary"
                sx={{ display: 'block', fontSize: 10, textTransform: 'uppercase', letterSpacing: '0.08em', lineHeight: 1.3 }}
            >
                {title}
            </Typography>
            <Box
                sx={{
                    display: 'flex',
                    alignItems: 'baseline',
                    columnGap: 0.75,
                    rowGap: 0.25,
                    flexWrap: 'wrap',
                    minWidth: 0,
                }}
            >
                <Typography
                    component="span"
                    sx={{ fontSize: { xs: 18, sm: 19 }, fontWeight: 700, fontVariantNumeric: 'tabular-nums' }}
                >
                    {value}
                </Typography>
                <TrendBadge trend={trend} invertTrend={invertTrend} />
            </Box>
        </Box>
        {subtitle && (
            <Typography
                variant="caption"
                color="text.secondary"
                sx={{
                    flex: { xs: '1 1 100%', sm: '0 1 auto' },
                    pl: { xs: 5, sm: 0 },
                    minWidth: 0,
                    maxWidth: { sm: 120 },
                    whiteSpace: { xs: 'normal', sm: 'nowrap' },
                    overflow: { sm: 'hidden' },
                    textOverflow: { sm: 'ellipsis' },
                    lineHeight: 1.2,
                }}
            >
                {subtitle}
            </Typography>
        )}
    </Box>
);

const AttentionItem = ({ label, value, color, testId }) => (
    <Box
        data-testid={testId}
        sx={{ display: 'flex', alignItems: { xs: 'flex-start', sm: 'center' }, gap: { xs: 0.75, sm: 1 }, minWidth: 0, flex: '1 1 auto' }}
    >
        <Box sx={{ width: 8, height: 8, borderRadius: '50%', bgcolor: color, flexShrink: 0, mt: { xs: '3px', sm: 0 } }} />
        <Box sx={{ minWidth: 0 }}>
            <Typography component="span" sx={{ fontSize: 18, fontWeight: 700, fontFamily: 'inherit' }}>
                {value}
            </Typography>
            <Typography variant="caption" color="text.secondary" sx={{ display: 'block', lineHeight: 1.2 }}>
                {label}
            </Typography>
        </Box>
    </Box>
);

export default function DashboardOverview() {
    const theme = useTheme();
    const isMobile = useMediaQuery(theme.breakpoints.down('sm'));
    const isTablet = useMediaQuery(theme.breakpoints.down('md'));
    const { permissions = [] } = useAuthContext();
    const canViewStats = permissions.includes('view tenants');
    const [loading, setLoading] = useState(true);
    const [stats, setStats] = useState(null);
    const [trends, setTrends] = useState({});
    const [recentTenants, setRecentTenants] = useState([]);
    const [tenantsByMonth, setTenantsByMonth] = useState([]);
    const [statusDistribution, setStatusDistribution] = useState([]);
    const [page, setPage] = useState(0);
    const [rowsPerPage] = useState(5);
    const [showDeleted, setShowDeleted] = useState(false);
    const chartHeight = isMobile ? 220 : isTablet ? 260 : 300;

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
            setTrends(res.data.trends ?? {});
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
                <Grid container spacing={2} sx={{ mb: 3 }}>
                    {[1, 2, 3].map((i) => (
                        <Grid size={{ xs: 12, sm: 6, md: 4 }} key={i}>
                            <Skeleton variant="rounded" sx={{ height: { xs: 140, sm: 132 } }} />
                        </Grid>
                    ))}
                </Grid>
                <Grid container spacing={1.5} sx={{ mb: 4 }}>
                    {[1, 2, 3].map((i) => (
                        <Grid size={{ xs: 12, sm: 6, md: 4 }} key={i}>
                            <Skeleton variant="rounded" sx={{ height: { xs: 84, sm: 72 } }} />
                        </Grid>
                    ))}
                </Grid>
                <Grid container spacing={3}>
                    <Grid size={{ xs: 12, md: 8 }}>
                        <Skeleton variant="rounded" height={chartHeight + 72} />
                    </Grid>
                    <Grid size={{ xs: 12, md: 4 }}>
                        <Skeleton variant="rounded" height={chartHeight + 72} />
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

    const statusDotColor = {
        Active: theme.palette.success.main,
        Trial: theme.palette.warning.main,
        Suspended: theme.palette.error.main,
        Deleted: theme.palette.text.disabled,
    };

    const getStatusColor = (name, index) => statusDotColor[name] ?? CHART_COLORS[index % CHART_COLORS.length];

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
                backgroundColor: statusDistribution.map((item, index) => getStatusColor(item.name, index)),
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

    const headCellSx = {
        fontWeight: 700,
        fontSize: 11,
        color: 'text.secondary',
        textTransform: 'uppercase',
        letterSpacing: '0.06em',
    };

    const paginatedTenants = recentTenants.slice(page * rowsPerPage, page * rowsPerPage + rowsPerPage);

    const handleChangePage = (event, newPage) => {
        setPage(newPage);
    };

    return (
        <Box>
            <Box
                sx={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: { xs: 'flex-start', sm: 'flex-end' },
                    flexWrap: 'wrap',
                    gap: { xs: 1, md: 2 },
                    mb: 3,
                }}
            >
                <Box sx={{ minWidth: 0 }}>
                    <Typography variant="overline" sx={{ color: 'primary.main', fontSize: 10, letterSpacing: '0.16em' }}>
                        Portfolio snapshot
                    </Typography>
                    <Typography variant="h6" component="h2" sx={{ mt: 0.25, fontSize: { xs: 18, md: 21 } }}>
                        Operations at a glance
                    </Typography>
                </Box>
                <Typography variant="caption" color="text.secondary" sx={{ textAlign: { xs: 'left', sm: 'right' }, flexShrink: 0 }}>
                    {formattedMonthlyData.length > 0
                        ? `${formattedMonthlyData.length}-month view`
                        : 'Awaiting first data point'}
                </Typography>
            </Box>

            <SectionHeading
                index="01"
                title="Portfolio"
                aside={<Typography variant="caption" color="text.secondary">Current workspace health</Typography>}
            />
            <Grid container spacing={2} sx={{ mb: 1.75 }}>
                <Grid size={{ xs: 12, sm: 6, md: 4 }}>
                    <HeroMetric
                        title="Total Tenants"
                        value={stats?.total_tenants ?? 0}
                        icon={<PeopleIcon />}
                        color={theme.palette.primary.main}
                        subtitle="All workspaces"
                        trend={trends.total_tenants}
                        testId="total-tenants"
                        index={0}
                    />
                </Grid>
                <Grid size={{ xs: 12, sm: 6, md: 4 }}>
                    <HeroMetric
                        title="Active Tenants"
                        value={stats?.active_tenants ?? 0}
                        icon={<CheckCircleIcon />}
                        color={theme.palette.success.main}
                        subtitle="Currently serving"
                        trend={trends.active_tenants}
                        testId="active-tenants"
                        index={1}
                    />
                </Grid>
                <Grid size={{ xs: 12, sm: 6, md: 4 }}>
                    <HeroMetric
                        title="Suspended"
                        value={stats?.suspended_tenants ?? 0}
                        icon={<BlockIcon />}
                        color={theme.palette.error.main}
                        subtitle="Needs attention"
                        trend={trends.suspended_tenants}
                        invertTrend
                        testId="suspended-tenants"
                        index={2}
                    />
                </Grid>
            </Grid>

            <Grid container spacing={1.5} sx={{ mb: 1.5 }}>
                <Grid size={{ xs: 12, sm: 6, md: 4 }}>
                    <PulseItem
                        title="Staff"
                        value={stats?.total_staff ?? 0}
                        icon={<GroupIcon />}
                        color={theme.palette.warning.main}
                        subtitle={`${stats?.active_staff ?? 0} active`}
                        trend={trends.total_staff}
                        testId="staff"
                    />
                </Grid>
                <Grid size={{ xs: 12, sm: 6, md: 4 }}>
                    <PulseItem
                        title="Plans"
                        value={stats?.total_plans ?? 0}
                        icon={<AssignmentIcon />}
                        color={theme.palette.secondary.main}
                        subtitle="Configured"
                        trend={trends.total_plans}
                        testId="plans"
                    />
                </Grid>
                <Grid size={{ xs: 12, sm: 6, md: 4 }}>
                    <PulseItem
                        title="Deleted"
                        value={stats?.deleted_tenants ?? 0}
                        icon={<DeleteIcon />}
                        color={theme.palette.text.secondary}
                        subtitle="Data preserved"
                        trend={trends.deleted_tenants}
                        invertTrend
                        testId="deleted-tenants"
                    />
                </Grid>
            </Grid>

            <Paper
                data-testid="kpi-attention"
                sx={{
                    p: { xs: 1.75, sm: 2.25 },
                    border: '1px solid',
                    borderColor: 'divider',
                    borderRadius: 1.5,
                    boxShadow: 'none',
                    display: 'flex',
                    flexDirection: { xs: 'column', sm: 'row' },
                    alignItems: { xs: 'flex-start', sm: 'center' },
                    justifyContent: 'space-between',
                    gap: { xs: 1.5, sm: 2 },
                    flexWrap: 'wrap',
                    mb: 4,
                }}
            >
                <Box>
                    <Typography variant="subtitle1" sx={{ fontWeight: 700, color: 'text.primary' }}>
                        Needs attention
                    </Typography>
                    <Typography variant="caption" color="text.secondary" sx={{ display: 'block', mt: 0.25 }}>
                        Status mix compares current workspace states with 30 days ago.
                    </Typography>
                </Box>
                <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: { xs: 2, sm: 3 }, width: { xs: '100%', sm: 'auto' } }}>
                    <AttentionItem
                        label="In trial"
                        value={stats?.trial_tenants ?? 0}
                        color={theme.palette.warning.main}
                        testId="attention-trial"
                    />
                    <AttentionItem
                        label="Suspended"
                        value={stats?.suspended_tenants ?? 0}
                        color={theme.palette.error.main}
                        testId="attention-suspended"
                    />
                </Box>
            </Paper>

            <SectionHeading
                index="02"
                title="Momentum"
                aside={<Typography variant="caption" color="text.secondary">Growth and distribution</Typography>}
            />
            <Grid container spacing={3} sx={{ mb: 4 }}>
                <Grid size={{ xs: 12, md: 8 }} sx={{ display: 'flex' }}>
                    <Paper sx={{
                        p: { xs: 2, md: 3 },
                        border: '1px solid',
                        borderColor: 'divider',
                        borderRadius: 2,
                        boxShadow: 'none',
                        flex: 1,
                        position: 'relative',
                        overflow: 'hidden',
                        '&::before': { content: '""', position: 'absolute', inset: '0 0 auto', height: 2, bgcolor: 'primary.main' },
                    }}>
                        <Box
                            sx={{
                                display: 'flex',
                                justifyContent: 'space-between',
                                alignItems: { xs: 'flex-start', sm: 'center' },
                                flexWrap: 'wrap',
                                gap: { xs: 1, sm: 2 },
                                mb: 2,
                            }}
                        >
                            <Box sx={{ minWidth: 0 }}>
                                <Typography variant="overline" sx={{ color: 'text.secondary', fontSize: 10, letterSpacing: '0.12em' }}>
                                    Tenant Growth
                                </Typography>
                                <Typography variant="subtitle1" sx={{ fontWeight: 700, color: 'text.primary', mt: 0.25 }}>
                                    New workspaces over time
                                </Typography>
                            </Box>
                            <Box sx={{ textAlign: { xs: 'left', sm: 'right' }, flexShrink: 0 }}>
                                <Typography component="span" sx={{ fontSize: 22, fontWeight: 700, lineHeight: 1, fontVariantNumeric: 'tabular-nums' }}>
                                    {formattedMonthlyData[formattedMonthlyData.length - 1]?.Tenants ?? 0}
                                </Typography>
                                <Typography variant="caption" color="text.secondary" sx={{ display: 'block', mt: 0.25 }}>
                                    new this month
                                </Typography>
                            </Box>
                        </Box>
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
                <Grid size={{ xs: 12, md: 4 }} sx={{ display: 'flex' }}>
                    <Paper sx={{
                        p: { xs: 2, md: 3 },
                        border: '1px solid',
                        borderColor: 'divider',
                        borderRadius: 2,
                        boxShadow: 'none',
                        flex: 1,
                        position: 'relative',
                        overflow: 'hidden',
                        '&::before': { content: '""', position: 'absolute', inset: '0 0 auto', height: 2, bgcolor: 'success.main' },
                    }}>
                        <Typography variant="overline" sx={{ color: 'text.secondary', fontSize: 10, letterSpacing: '0.12em' }}>
                            Tenant Status
                        </Typography>
                        <Typography variant="subtitle1" sx={{ fontWeight: 700, color: 'text.primary', mt: 0.25, mb: 1 }}>
                            Current distribution
                        </Typography>
                        {statusDistribution.every((s) => s.value === 0) ? (
                            <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'center', height: chartHeight }}>
                                <Typography variant="body2" color="text.secondary">
                                    No data yet
                                </Typography>
                            </Box>
                        ) : (
                            <Box
                                sx={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    alignItems: 'center',
                                    gap: { xs: 1.5, lg: 2 },
                                }}
                            >
                                <Box
                                    sx={{
                                        position: 'relative',
                                        flex: '0 0 auto',
                                        width: '100%',
                                        maxWidth: { xs: 260, lg: 240 },
                                        height: chartHeight * 0.7,
                                    }}
                                    role="img"
                                    aria-label="Distribution of tenants by status"
                                >
                                    <Doughnut data={statusChartData} options={statusChartOptions} />
                                    <Box
                                        sx={{
                                            position: 'absolute',
                                            inset: 0,
                                            display: 'flex',
                                            flexDirection: 'column',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            pointerEvents: 'none',
                                        }}
                                    >
                                        <Typography
                                            component="span"
                                            sx={{
                                                fontSize: { xs: 18, lg: 22 },
                                                fontWeight: 700,
                                                lineHeight: 1.1,
                                                fontVariantNumeric: 'tabular-nums',
                                                whiteSpace: 'nowrap',
                                            }}
                                        >
                                            {`${stats?.total_tenants ?? 0} tenants`}
                                        </Typography>
                                    </Box>
                                </Box>
                                <Box
                                    data-testid="tenant-status-legend"
                                    sx={{
                                        width: '100%',
                                        display: 'flex',
                                        flexDirection: 'row',
                                        flexWrap: 'wrap',
                                        alignItems: 'center',
                                        justifyContent: { xs: 'flex-start', lg: 'center' },
                                        columnGap: { xs: 1.5, sm: 2 },
                                        rowGap: 0.75,
                                    }}
                                >
                                    {statusDistribution.map((item, index) => (
                                        <Box
                                            key={item.name}
                                            data-testid={`tenant-status-legend-${item.name.toLowerCase()}`}
                                            sx={{ display: 'flex', alignItems: 'center', gap: 0.75, minWidth: 0 }}
                                        >
                                            <Box sx={{ width: 10, height: 10, borderRadius: '50%', bgcolor: getStatusColor(item.name, index), flexShrink: 0 }} />
                                            <Typography variant="caption" color="text.secondary" sx={{ minWidth: 0 }}>
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

            <SectionHeading
                index="03"
                title="Activity"
                aside={<Typography variant="caption" color="text.secondary">Recently provisioned workspaces</Typography>}
            />
            <Paper sx={{
                border: '1px solid',
                borderColor: 'divider',
                borderRadius: 2,
                boxShadow: 'none',
                overflow: 'hidden',
            }}>
                <Box
                    sx={{
                        p: { xs: 2, md: 3 },
                        pb: 1,
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: { xs: 'flex-start', sm: 'center' },
                        flexWrap: 'wrap',
                        gap: { xs: 1, sm: 2 },
                    }}
                >
                    <Box sx={{ minWidth: 0 }}>
                        <Typography variant="overline" sx={{ color: 'text.secondary', fontSize: 10, letterSpacing: '0.12em' }}>
                            Recent Tenants
                        </Typography>
                        <Typography variant="subtitle1" sx={{ fontWeight: 700, color: 'text.primary', mt: 0.25 }}>
                            Latest workspace activity
                        </Typography>
                    </Box>
                    <FormControlLabel
                        control={<Switch size="small" checked={showDeleted} onChange={() => setShowDeleted(s => !s)} />}
                        label={<Typography variant="caption" sx={{ color: 'text.secondary' }}>Show deleted</Typography>}
                        sx={{ mr: 0, flexShrink: 0 }}
                    />
                </Box>
                <TableContainer sx={{ maxWidth: '100%', overflowX: 'auto' }}>
                    <Table size="small" sx={{ minWidth: 520 }}>
                        <TableHead>
                            <TableRow sx={{ bgcolor: 'background.default', borderBottom: `1px solid ${theme.palette.divider}` }}>
                                <TableCell sx={headCellSx}>Name</TableCell>
                                <TableCell sx={headCellSx}>Domain</TableCell>
                                <TableCell sx={headCellSx}>Status</TableCell>
                                <TableCell sx={headCellSx}>Created</TableCell>
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
                                        data-testid="recent-tenant-row"
                                        sx={{
                                            '&:last-child td, &:last-child th': { border: 0 },
                                            transition: 'background-color 150ms ease-out',
                                            '&:hover': { bgcolor: 'background.default' },
                                        }}
                                    >
                                        <TableCell>
                                            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.25 }}>
                                                <Box
                                                    sx={{
                                                        width: 7,
                                                        height: 7,
                                                        borderRadius: '50%',
                                                        bgcolor: statusDotColor[tenant.status] ?? theme.palette.text.disabled,
                                                        flexShrink: 0,
                                                    }}
                                                />
                                                <Typography variant="body2" sx={{ fontWeight: 600, fontSize: 13 }}>
                                                    {tenant.name}
                                                </Typography>
                                            </Box>
                                        </TableCell>
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
                                                    <Chip label={tenant.status} size="small" sx={statusChipSx.Trial} />
                                                </MuiTooltip>
                                            ) : (
                                                <Chip
                                                    label={tenant.status}
                                                    size="small"
                                                    sx={statusChipSx[tenant.status] ?? statusChipSx.Deleted}
                                                />
                                            )}
                                        </TableCell>
                                        <TableCell sx={{ color: 'text.secondary', fontSize: 13, fontVariantNumeric: 'tabular-nums' }}>
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
