import TenantLayout from '@/Layouts/TenantLayout';
import { Head, usePage } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Paper from '@mui/material/Paper';
import Typography from '@mui/material/Typography';
import Grid from '@mui/material/Grid';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Avatar from '@mui/material/Avatar';
import Tooltip from '@mui/material/Tooltip';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableContainer from '@mui/material/TableContainer';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';
import Chip from '@mui/material/Chip';
import Skeleton from '@mui/material/Skeleton';
import InventoryIcon from '@mui/icons-material/Inventory';
import ShoppingCartIcon from '@mui/icons-material/ShoppingCart';
import PeopleIcon from '@mui/icons-material/People';
import WarningIcon from '@mui/icons-material/Warning';
import TrendingUpIcon from '@mui/icons-material/TrendingUp';
import TrendingDownIcon from '@mui/icons-material/TrendingDown';

function TrendBadge({ value }) {
    if (value === 0) return null;
    const isUp = value > 0;
    return (
        <Tooltip title={`${isUp ? 'Up' : 'Down'} ${Math.abs(value)}% vs last month`}>
            <Box component="span" sx={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 0.25,
                fontSize: 11,
                fontWeight: 600,
                color: isUp ? '#16a34a' : '#dc2626',
                ml: 1,
            }}>
                {isUp ? <TrendingUpIcon sx={{ fontSize: 14 }} /> : <TrendingDownIcon sx={{ fontSize: 14 }} />}
                {Math.abs(value)}%
            </Box>
        </Tooltip>
    );
}

function StatCard({ title, value, icon, color, trend }) {
    return (
        <Card sx={{
            height: '100%',
            boxShadow: '0 1px 3px rgba(0,0,0,0.06)',
            borderLeft: `4px solid ${color}`,
            borderRadius: 2,
        }}>
            <CardContent sx={{ display: 'flex', alignItems: 'center', gap: 2, p: 3 }}>
                <Avatar sx={{ bgcolor: `${color}15`, color, width: 48, height: 48 }}>
                    {icon}
                </Avatar>
                <Box>
                    <Typography variant="h4" sx={{ fontWeight: 700, color: '#0f172a', lineHeight: 1.2 }}>
                        {value}
                    </Typography>
                    <Typography variant="body2" color="text.secondary" sx={{ display: 'flex', alignItems: 'center' }}>
                        {title}
                        {trend !== undefined && <TrendBadge value={trend} />}
                    </Typography>
                </Box>
            </CardContent>
        </Card>
    );
}

function LazyTable({ data, loading, emptyMessage, columns, renderRow }) {
    if (loading) {
        return (
            <Box sx={{ p: 3 }}>
                {[1, 2, 3].map((i) => (
                    <Skeleton key={i} variant="text" sx={{ mb: 1, height: 32 }} />
                ))}
            </Box>
        );
    }

    if (!data || data.length === 0) {
        return (
            <TableRow>
                <TableCell colSpan={columns.length} sx={{ textAlign: 'center', py: 4, color: 'text.secondary' }}>
                    {emptyMessage}
                </TableCell>
            </TableRow>
        );
    }

    return data.map((row) => renderRow(row));
}

export default function Dashboard({ stats, trends, recentOrders, recentMovements }) {
    const page = usePage();
    const ordersLoading = page.props.recentOrders === undefined || typeof page.props.recentOrders === 'function';
    const movementsLoading = page.props.recentMovements === undefined || typeof page.props.recentMovements === 'function';

    return (
        <TenantLayout>
            <Head title="Dashboard" />

            <Typography variant="h5" sx={{ fontWeight: 700, color: '#0f172a', mb: 3 }}>
                Dashboard
            </Typography>

            <Grid container spacing={2} sx={{ mb: 4 }}>
                <Grid item xs={6} sm={6} md={3} lg={3}>
                    <StatCard title="Active Products" value={stats.active_products} icon={<InventoryIcon />} color="#3b82f6" trend={trends?.active_products} />
                </Grid>
                <Grid item xs={6} sm={6} md={3} lg={3}>
                    <StatCard title="Total Orders" value={stats.total_orders} icon={<ShoppingCartIcon />} color="#22c55e" trend={trends?.total_orders} />
                </Grid>
                <Grid item xs={6} sm={6} md={3} lg={3}>
                    <StatCard title="Customers" value={stats.total_customers} icon={<PeopleIcon />} color="#f59e0b" trend={trends?.total_customers} />
                </Grid>
                <Grid item xs={6} sm={6} md={3} lg={3}>
                    <StatCard title="Low Stock Items" value={stats.low_stock_count} icon={<WarningIcon />} color="#ef4444" />
                </Grid>
            </Grid>

            <Grid container spacing={3}>
                <Grid item xs={12} sm={12} md={6} lg={6}>
                    <Paper sx={{ boxShadow: '0 1px 3px rgba(0,0,0,0.06)', borderTop: '3px solid #22c55e', borderRadius: 2 }}>
                        <Box sx={{ p: 3, pb: 1 }}>
                            <Typography variant="subtitle1" sx={{ fontWeight: 700, color: '#0f172a' }}>
                                Recent Orders
                            </Typography>
                        </Box>
                        <TableContainer>
                            <Table size="small">
                                <TableHead>
                                    <TableRow sx={{ bgcolor: '#f8fafc' }}>
                                        <TableCell sx={{ fontWeight: 700, fontSize: 11, color: '#64748b', textTransform: 'uppercase' }}>Customer</TableCell>
                                        <TableCell sx={{ fontWeight: 700, fontSize: 11, color: '#64748b', textTransform: 'uppercase' }}>Total</TableCell>
                                        <TableCell sx={{ fontWeight: 700, fontSize: 11, color: '#64748b', textTransform: 'uppercase' }}>Status</TableCell>
                                        <TableCell sx={{ fontWeight: 700, fontSize: 11, color: '#64748b', textTransform: 'uppercase' }}>Date</TableCell>
                                    </TableRow>
                                </TableHead>
                                <TableBody>
                                    {ordersLoading ? (
                                        <TableRow>
                                            <TableCell colSpan={4} sx={{ p: 0, border: 'none' }}>
                                                <Box sx={{ p: 3 }}>
                                                    {[1, 2, 3].map((i) => (
                                                        <Skeleton key={i} variant="text" sx={{ mb: 1, height: 32 }} />
                                                    ))}
                                                </Box>
                                            </TableCell>
                                        </TableRow>
                                    ) : recentOrders.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={4} sx={{ textAlign: 'center', py: 4, color: 'text.secondary' }}>
                                                No orders yet
                                            </TableCell>
                                        </TableRow>
                                    ) : recentOrders.map((order) => (
                                        <TableRow key={order.id} sx={{ '&:hover': { bgcolor: '#f8fafc' } }}>
                                            <TableCell sx={{ fontWeight: 600, fontSize: 13 }}>{order.customer_name}</TableCell>
                                            <TableCell sx={{ fontSize: 13 }}>${Number(order.total).toFixed(2)}</TableCell>
                                            <TableCell>
                                                <Chip label={order.status} size="small"
                                                    sx={{
                                                        bgcolor: order.status === 'paid' ? '#dcfce7' : order.status === 'pending' ? '#fef9c3' : '#fee2e2',
                                                        color: order.status === 'paid' ? '#166534' : order.status === 'pending' ? '#854d0e' : '#991b1b',
                                                        fontWeight: 600, height: 24, fontSize: 12,
                                                    }} />
                                            </TableCell>
                                            <TableCell sx={{ color: '#64748b', fontSize: 13 }}>{order.created_at}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </TableContainer>
                    </Paper>
                </Grid>

                <Grid item xs={12} sm={12} md={6} lg={6}>
                    <Paper sx={{ boxShadow: '0 1px 3px rgba(0,0,0,0.06)', borderTop: '3px solid #3b82f6', borderRadius: 2 }}>
                        <Box sx={{ p: 3, pb: 1 }}>
                            <Typography variant="subtitle1" sx={{ fontWeight: 700, color: '#0f172a' }}>
                                Recent Inventory Movements
                            </Typography>
                        </Box>
                        <TableContainer>
                            <Table size="small">
                                <TableHead>
                                    <TableRow sx={{ bgcolor: '#f8fafc' }}>
                                        <TableCell sx={{ fontWeight: 700, fontSize: 11, color: '#64748b', textTransform: 'uppercase' }}>Product</TableCell>
                                        <TableCell sx={{ fontWeight: 700, fontSize: 11, color: '#64748b', textTransform: 'uppercase' }}>Warehouse</TableCell>
                                        <TableCell sx={{ fontWeight: 700, fontSize: 11, color: '#64748b', textTransform: 'uppercase' }}>Type</TableCell>
                                        <TableCell sx={{ fontWeight: 700, fontSize: 11, color: '#64748b', textTransform: 'uppercase' }}>Qty</TableCell>
                                    </TableRow>
                                </TableHead>
                                <TableBody>
                                    {movementsLoading ? (
                                        <TableRow>
                                            <TableCell colSpan={4} sx={{ p: 0, border: 'none' }}>
                                                <Box sx={{ p: 3 }}>
                                                    {[1, 2, 3].map((i) => (
                                                        <Skeleton key={i} variant="text" sx={{ mb: 1, height: 32 }} />
                                                    ))}
                                                </Box>
                                            </TableCell>
                                        </TableRow>
                                    ) : recentMovements.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={4} sx={{ textAlign: 'center', py: 4, color: 'text.secondary' }}>
                                                No movements yet
                                            </TableCell>
                                        </TableRow>
                                    ) : recentMovements.map((m) => (
                                        <TableRow key={m.id} sx={{ '&:hover': { bgcolor: '#f8fafc' } }}>
                                            <TableCell sx={{ fontWeight: 600, fontSize: 13 }}>{m.product_name}</TableCell>
                                            <TableCell sx={{ fontSize: 13 }}>{m.warehouse_name}</TableCell>
                                            <TableCell>
                                                <Chip label={m.type} size="small"
                                                    sx={{
                                                        bgcolor: m.type === 'in' ? '#dcfce7' : m.type === 'out' ? '#fee2e2' : '#fef9c3',
                                                        color: m.type === 'in' ? '#166534' : m.type === 'out' ? '#991b1b' : '#854d0e',
                                                        fontWeight: 600, height: 24, fontSize: 12,
                                                    }} />
                                            </TableCell>
                                            <TableCell sx={{ fontSize: 13 }}>{m.quantity}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </TableContainer>
                    </Paper>
                </Grid>
            </Grid>
        </TenantLayout>
    );
}
