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
import { useTheme } from '@mui/material/styles';
import PeopleIcon from '@mui/icons-material/People';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import BlockIcon from '@mui/icons-material/Block';
import DeleteIcon from '@mui/icons-material/Delete';
import GroupIcon from '@mui/icons-material/Group';
import AssignmentIcon from '@mui/icons-material/Assignment';
import {
    AreaChart,
    Area,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
    PieChart,
    Pie,
    Cell,
} from 'recharts';

const StatCard = ({ title, value, icon, color, subtitle, trend }) => {
    const muiTheme = useTheme();
    return (
        <Card sx={{
            height: '100%',
            boxShadow: '0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04)',
            borderLeft: `4px solid ${color}`,
            borderRadius: '8px',
            transition: 'box-shadow 0.2s ease, transform 0.2s ease',
            '&:hover': {
                boxShadow: `0 8px 25px -5px ${color}33, 0 4px 10px -4px ${color}22`,
                transform: 'translateY(-2px)',
            },
        }}>
            <CardContent sx={{ display: 'flex', alignItems: 'center', gap: 2, p: 3 }}>
                <Avatar sx={{ bgcolor: `${color}15`, color, width: 48, height: 48 }}>
                    {icon}
                </Avatar>
                <Box>
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                        <Typography variant="h4" sx={{ fontWeight: 700, color: 'text.primary', lineHeight: 1.2 }}>
                            {value}
                        </Typography>
                        {trend && (
                            <Box sx={{
                                display: 'flex',
                                alignItems: 'center',
                                color: trend.direction === 'up' ? 'success.main' : 'error.main',
                                fontSize: '12px',
                                fontWeight: 600,
                            }}>
                                {trend.direction === 'up' ? '\u2191' : '\u2193'} {trend.percentage}%
                            </Box>
                        )}
                    </Box>
                    <Typography variant="body2" color="text.secondary">
                        {title}
                    </Typography>
                    {subtitle && (
                        <Typography variant="caption" color="text.secondary" sx={{ display: 'block', mt: 0.5 }}>
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
    const canViewStats = permissions.includes('manage tenants');
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
                    You need the <strong>manage tenants</strong> permission to view dashboard statistics.
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
    const pieRadius = isMobile ? 60 : 80;

    const COLORS = [theme.palette.success.light, theme.palette.error.light, theme.palette.text.secondary];

    const paginatedTenants = recentTenants.slice(page * rowsPerPage, page * rowsPerPage + rowsPerPage);

    const handleChangePage = (event, newPage) => {
        setPage(newPage);
    };

    return (
        <Box>
            <Typography variant="h5" sx={{ fontWeight: 700, color: 'text.primary', mb: 3 }}>
                Dashboard Overview
            </Typography>

            <Grid container spacing={2} sx={{ mb: 4 }}>
                <Grid item xs={6} sm={6} md={4} lg={2.4}>
                    <StatCard
                        title="Total Tenants"
                        value={stats?.total_tenants ?? 0}
                        icon={<PeopleIcon />}
                        color={theme.palette.primary.light}
                    />
                </Grid>
                <Grid item xs={6} sm={6} md={4} lg={2.4}>
                    <StatCard
                        title="Active Tenants"
                        value={stats?.active_tenants ?? 0}
                        icon={<CheckCircleIcon />}
                        color={theme.palette.success.light}
                    />
                </Grid>
                <Grid item xs={6} sm={6} md={4} lg={2.4}>
                    <StatCard
                        title="Suspended"
                        value={stats?.suspended_tenants ?? 0}
                        icon={<BlockIcon />}
                        color={theme.palette.error.light}
                    />
                </Grid>
                <Grid item xs={6} sm={6} md={4} lg={2.4}>
                    <StatCard
                        title="Deleted"
                        value={stats?.deleted_tenants ?? 0}
                        icon={<DeleteIcon />}
                        color={theme.palette.text.secondary}
                    />
                </Grid>
                <Grid item xs={6} sm={6} md={4} lg={2.4}>
                    <StatCard
                        title="Staff"
                        value={stats?.total_staff ?? 0}
                        icon={<GroupIcon />}
                        color={theme.palette.warning.light}
                        subtitle={`${stats?.active_staff ?? 0} active`}
                    />
                </Grid>
                <Grid item xs={12} sm={6} md={4} lg={2.4}>
                    <StatCard
                        title="Plans"
                        value={stats?.total_plans ?? 0}
                        icon={<AssignmentIcon />}
                        color={theme.palette.secondary.light}
                    />
                </Grid>
            </Grid>

            <Grid container spacing={3} sx={{ mb: 4 }}>
                <Grid item xs={12} sm={12} md={8} lg={8}>
                    <Paper sx={{
                        p: 3,
                        boxShadow: '0 1px 3px rgba(0,0,0,0.06)',
                        borderTop: `3px solid ${theme.palette.primary.light}`,
                        borderRadius: '8px',
                    }}>
                        <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 2, color: 'text.primary', display: 'flex', alignItems: 'center', gap: 1 }}>
                            <Box sx={{ width: 8, height: 8, borderRadius: '50%', bgcolor: theme.palette.primary.light }} />
                            Tenant Growth
                        </Typography>
                        {tenantsByMonth.length === 0 ? (
                            <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'center', height: chartHeight }}>
                                <Typography variant="body2" color="text.secondary">
                                    No data yet
                                </Typography>
                            </Box>
                        ) : (
                            <ResponsiveContainer width="100%" height={chartHeight}>
                                <AreaChart data={formattedMonthlyData} margin={{ top: 10, right: 10, left: -20, bottom: 0 }}>
                                    <defs>
                                        <linearGradient id="colorTenants" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="5%" stopColor={theme.palette.primary.light} stopOpacity={0.3} />
                                            <stop offset="95%" stopColor={theme.palette.primary.light} stopOpacity={0.02} />
                                        </linearGradient>
                                    </defs>
                                    <CartesianGrid strokeDasharray="3 3" stroke={theme.palette.background.default} />
                                    <XAxis dataKey="name" tick={{ fontSize: 11 }} stroke={theme.palette.text.disabled} />
                                    <YAxis allowDecimals={false} tick={{ fontSize: 11 }} stroke={theme.palette.text.disabled} />
                                    <Tooltip
                                        contentStyle={{
                                            backgroundColor: '#fff',
                                            border: `1px solid ${theme.palette.divider}`,
                                            borderRadius: '8px',
                                            boxShadow: '0 4px 12px rgba(0,0,0,0.08)',
                                            fontSize: '13px',
                                        }}
                                    />
                                    <Area
                                        type="monotone"
                                        dataKey="Tenants"
                                        stroke={theme.palette.primary.light}
                                        strokeWidth={2}
                                        fill="url(#colorTenants)"
                                    />
                                </AreaChart>
                            </ResponsiveContainer>
                        )}
                    </Paper>
                </Grid>
                <Grid item xs={12} sm={12} md={4} lg={4}>
                    <Paper sx={{
                        p: 3,
                        boxShadow: '0 1px 3px rgba(0,0,0,0.06)',
                        borderTop: `3px solid ${theme.palette.success.light}`,
                        borderRadius: '8px',
                        height: '100%',
                    }}>
                        <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 1, color: 'text.primary', display: 'flex', alignItems: 'center', gap: 1 }}>
                            <Box sx={{ width: 8, height: 8, borderRadius: '50%', bgcolor: theme.palette.success.light }} />
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
                                <ResponsiveContainer width="100%" height={chartHeight * 0.7}>
                                    <PieChart>
                                        <Pie
                                            data={statusDistribution}
                                            cx="50%"
                                            cy="50%"
                                            innerRadius={pieRadius * 0.65}
                                            outerRadius={pieRadius}
                                            paddingAngle={4}
                                            dataKey="value"
                                            label={({ name, percent }) => `${name} ${(percent * 100).toFixed(0)}%`}
                                        >
                                            {statusDistribution.map((_, index) => (
                                                <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                                            ))}
                                        </Pie>
                                        <Tooltip />
                                    </PieChart>
                                </ResponsiveContainer>
                                <Box sx={{ display: 'flex', gap: 3, mt: 1 }}>
                                    {statusDistribution.map((item, index) => (
                                        <Box key={item.name} sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                            <Box sx={{ width: 10, height: 10, borderRadius: '50%', bgcolor: COLORS[index] }} />
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
                boxShadow: '0 1px 3px rgba(0,0,0,0.06)',
                borderTop: `3px solid ${theme.palette.secondary.light}`,
                borderRadius: '8px',
                overflow: 'hidden',
            }}>
                <Box sx={{ p: 3, pb: 1, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <Typography variant="subtitle1" sx={{ fontWeight: 700, color: 'text.primary', display: 'flex', alignItems: 'center', gap: 1 }}>
                        <Box sx={{ width: 8, height: 8, borderRadius: '50%', bgcolor: theme.palette.secondary.light }} />
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
                                            <Typography variant="body2" sx={{ fontFamily: 'monospace', fontSize: 12, color: '#475569' }}>
                                                {tenant.domain}
                                            </Typography>
                                        </TableCell>
                                        <TableCell>
                                            <Chip
                                                label={tenant.status}
                                                size="small"
                                                sx={{
                                                    bgcolor: tenant.status === 'Active' ? '#dcfce7' : tenant.status === 'Suspended' ? '#fee2e2' : '#f1f5f9',
                                                    color: tenant.status === 'Active' ? '#166534' : tenant.status === 'Suspended' ? '#991b1b' : '#64748b',
                                                    fontWeight: 600,
                                                    height: 24,
                                                    fontSize: 12,
                                                    fontStyle: tenant.status === 'Deleted' ? 'italic' : 'normal',
                                                }}
                                            />
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
                    sx={{ borderTop: `1px solid ${theme.palette.background.default}`, bgcolor: 'background.default' }}
                />
            </Paper>
        </Box>
    );
}
