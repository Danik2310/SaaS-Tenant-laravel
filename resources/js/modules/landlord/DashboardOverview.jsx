import React, { useEffect, useState } from 'react';
import api from '../../services/api';
import {
    Box,
    Paper,
    Typography,
    Grid,
    Card,
    CardContent,
    Skeleton,
    Avatar,
    Chip,
    Table,
    TableBody,
    TableCell,
    TableContainer,
    TableHead,
    TableRow,
} from '@mui/material';
import {
    People as PeopleIcon,
    CheckCircle as CheckCircleIcon,
    Block as BlockIcon,
    Group as GroupIcon,
    Assignment as AssignmentIcon,
} from '@mui/icons-material';
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
    BarChart,
    Bar,
    Legend,
} from 'recharts';

const COLORS = ['#4CAF50', '#f44336'];

const StatCard = ({ title, value, icon, color, subtitle }) => (
    <Card sx={{ height: '100%', boxShadow: '0 2px 8px rgba(0,0,0,0.08)' }}>
        <CardContent sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
            <Avatar sx={{ bgcolor: color, width: 48, height: 48 }}>
                {icon}
            </Avatar>
            <Box>
                <Typography variant="h4" sx={{ fontWeight: 700, color: '#1a202c' }}>
                    {value}
                </Typography>
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

export default function DashboardOverview() {
    const [loading, setLoading] = useState(true);
    const [stats, setStats] = useState(null);
    const [recentTenants, setRecentTenants] = useState([]);
    const [tenantsByMonth, setTenantsByMonth] = useState([]);
    const [statusDistribution, setStatusDistribution] = useState([]);

    useEffect(() => {
        fetchStats();
    }, []);

    const fetchStats = async () => {
        try {
            const res = await api.get('/admin/api/dashboard-stats');
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

    if (loading) {
        return (
            <Box>
                <Grid container spacing={3} sx={{ mb: 4 }}>
                    {[1, 2, 3, 4].map((i) => (
                        <Grid item xs={12} sm={6} md={4} key={i}>
                            <Skeleton variant="rounded" height={100} />
                        </Grid>
                    ))}
                </Grid>
                <Grid container spacing={3}>
                    {[1, 2, 3].map((i) => (
                        <Grid item xs={12} lg={4} key={i}>
                            <Skeleton variant="rounded" height={300} />
                        </Grid>
                    ))}
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

    return (
        <Box>
            <Typography variant="h5" sx={{ fontWeight: 700, color: '#1a202c', mb: 3 }}>
                Dashboard Overview
            </Typography>

            {/* Stats Cards */}
            <Grid container spacing={3} sx={{ mb: 4 }}>
                <Grid item xs={12} sm={6} md={2.4} md={2.4}>
                    <StatCard
                        title="Total Tenants"
                        value={stats?.total_tenants ?? 0}
                        icon={<PeopleIcon />}
                        color="#3f51b5"
                    />
                </Grid>
                <Grid item xs={12} sm={6} md={2.4}>
                    <StatCard
                        title="Active Tenants"
                        value={stats?.active_tenants ?? 0}
                        icon={<CheckCircleIcon />}
                        color="#4CAF50"
                    />
                </Grid>
                <Grid item xs={12} sm={6} md={2.4}>
                    <StatCard
                        title="Suspended"
                        value={stats?.suspended_tenants ?? 0}
                        icon={<BlockIcon />}
                        color="#f44336"
                    />
                </Grid>
                <Grid item xs={12} sm={6} md={2.4}>
                    <StatCard
                        title="Staff Members"
                        value={stats?.total_staff ?? 0}
                        icon={<GroupIcon />}
                        color="#ff9800"
                        subtitle={`${stats?.active_staff ?? 0} active`}
                    />
                </Grid>
                <Grid item xs={12} sm={6} md={2.4}>
                    <StatCard
                        title="Active Plans"
                        value={stats?.total_plans ?? 0}
                        icon={<AssignmentIcon />}
                        color="#9c27b0"
                    />
                </Grid>
            </Grid>

            {/* Charts Row */}
            <Grid container spacing={3} sx={{ mb: 4 }}>
                <Grid item xs={12} lg={8}>
                    <Paper sx={{ p: 3, boxShadow: '0 2px 8px rgba(0,0,0,0.08)' }}>
                        <Typography variant="h6" sx={{ fontWeight: 600, mb: 2, color: '#1a202c' }}>
                            Tenant Growth
                        </Typography>
                        {tenantsByMonth.length === 0 ? (
                            <Typography variant="body2" color="text.secondary" sx={{ py: 6, textAlign: 'center' }}>
                                No data yet
                            </Typography>
                        ) : (
                            <ResponsiveContainer width="100%" height={280}>
                                <AreaChart data={formattedMonthlyData}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="#e0e0e0" />
                                    <XAxis dataKey="name" tick={{ fontSize: 12 }} />
                                    <YAxis allowDecimals={false} tick={{ fontSize: 12 }} />
                                    <Tooltip />
                                    <Area
                                        type="monotone"
                                        dataKey="Tenants"
                                        stroke="#3f51b5"
                                        fill="#3f51b5"
                                        fillOpacity={0.15}
                                    />
                                </AreaChart>
                            </ResponsiveContainer>
                        )}
                    </Paper>
                </Grid>
                <Grid item xs={12} lg={4}>
                    <Paper sx={{ p: 3, boxShadow: '0 2px 8px rgba(0,0,0,0.08)', height: '100%' }}>
                        <Typography variant="h6" sx={{ fontWeight: 600, mb: 2, color: '#1a202c' }}>
                            Tenant Status
                        </Typography>
                        {statusDistribution.every((s) => s.value === 0) ? (
                            <Typography variant="body2" color="text.secondary" sx={{ py: 6, textAlign: 'center' }}>
                                No data yet
                            </Typography>
                        ) : (
                            <Box sx={{ display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
                                <ResponsiveContainer width="100%" height={220}>
                                    <PieChart>
                                        <Pie
                                            data={statusDistribution}
                                            cx="50%"
                                            cy="50%"
                                            innerRadius={50}
                                            outerRadius={80}
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
                            </Box>
                        )}
                    </Paper>
                </Grid>
            </Grid>

            {/* Recent Tenants Table */}
            <Paper sx={{ boxShadow: '0 2px 8px rgba(0,0,0,0.08)' }}>
                <Box sx={{ p: 3, pb: 1 }}>
                    <Typography variant="h6" sx={{ fontWeight: 600, color: '#1a202c' }}>
                        Recent Tenants
                    </Typography>
                </Box>
                <TableContainer>
                    <Table>
                        <TableHead>
                            <TableRow>
                                <TableCell>Name</TableCell>
                                <TableCell>Domain</TableCell>
                                <TableCell>Status</TableCell>
                                <TableCell>Created</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {recentTenants.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={4} sx={{ textAlign: 'center', py: 4, color: 'text.secondary' }}>
                                        No tenants yet
                                    </TableCell>
                                </TableRow>
                            ) : (
                                recentTenants.map((tenant) => (
                                    <TableRow key={tenant.domain}>
                                        <TableCell sx={{ fontWeight: 600 }}>{tenant.name}</TableCell>
                                        <TableCell>
                                            <Typography variant="body2" sx={{ fontFamily: 'monospace', fontSize: 13 }}>
                                                {tenant.domain}
                                            </Typography>
                                        </TableCell>
                                        <TableCell>
                                            <Chip
                                                label={tenant.status}
                                                size="small"
                                                sx={{
                                                    bgcolor: tenant.status === 'Active' ? '#e8f5e9' : '#ffebee',
                                                    color: tenant.status === 'Active' ? '#2e7d32' : '#c62828',
                                                    fontWeight: 600,
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
            </Paper>
        </Box>
    );
}
