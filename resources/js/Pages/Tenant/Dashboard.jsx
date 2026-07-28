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
import { useTheme } from '@mui/material/styles';
import InventoryIcon from '@mui/icons-material/Inventory';
import ShoppingCartIcon from '@mui/icons-material/ShoppingCart';
import PeopleIcon from '@mui/icons-material/People';
import WarningIcon from '@mui/icons-material/Warning';
import TrendingUpIcon from '@mui/icons-material/TrendingUp';
import TrendingDownIcon from '@mui/icons-material/TrendingDown';

function TrendBadge({ value }) {
    if (value === 0) return null;
    const isUp = value > 0;
    const { palette } = useTheme();
    return (
        <Tooltip title={`${isUp ? 'Up' : 'Down'} ${Math.abs(value)}% vs last month`}>
            <Box component="span" sx={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 0.25,
                fontSize: 11,
                fontWeight: 600,
                color: isUp ? palette.success.main : palette.error.main,
                ml: 1,
            }}>
                {isUp ? <TrendingUpIcon sx={{ fontSize: 14 }} /> : <TrendingDownIcon sx={{ fontSize: 14 }} />}
                {Math.abs(value)}%
            </Box>
        </Tooltip>
    );
}

function StatCard({ title, value, icon, color, trend }) {
    const { palette } = useTheme();
    const textPrimary = palette.mode === 'dark' ? '#e2e8f0' : '#0f172a';
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
                    <Typography variant="h4" sx={{ fontWeight: 700, color: textPrimary, lineHeight: 1.2 }}>
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
    const { palette } = useTheme();
    const ordersLoading = page.props.recentOrders === undefined || typeof page.props.recentOrders === 'function';
    const movementsLoading = page.props.recentMovements === undefined || typeof page.props.recentMovements === 'function';
    const textPrimary = palette.mode === 'dark' ? '#e2e8f0' : '#0f172a';
    const lightBg = palette.mode === 'dark' ? palette.action.hover : '#f8fafc';
    const hoverBg = palette.mode === 'dark' ? palette.action.selected : '#f8fafc';
    const textMuted = palette.text.secondary;

    return (
        <TenantLayout>
            <Head title="Dashboard" />

            <Typography variant="h5" sx={{ fontWeight: 700, color: textPrimary, mb: 3 }}>
                Dashboard
            </Typography>

            <Grid container spacing={2} sx={{ mb: 4 }}>
                <Grid item xs={6} sm={6} md={3} lg={3}>
                    <StatCard title="Active Products" value={stats.active_products} icon={<InventoryIcon />} color={palette.primary.main} trend={trends?.active_products} />
                </Grid>
                <Grid item xs={6} sm={6} md={3} lg={3}>
                    <StatCard title="Total Orders" value={stats.total_orders} icon={<ShoppingCartIcon />} color={palette.success.main} trend={trends?.total_orders} />
                </Grid>
                <Grid item xs={6} sm={6} md={3} lg={3}>
                    <StatCard title="Customers" value={stats.total_customers} icon={<PeopleIcon />} color={palette.warning.main} trend={trends?.total_customers} />
                </Grid>
                <Grid item xs={6} sm={6} md={3} lg={3}>
                    <StatCard title="Low Stock Items" value={stats.low_stock_count} icon={<WarningIcon />} color={palette.error.main} />
                </Grid>
            </Grid>

            <Grid container spacing={3}>
                <Grid item xs={12} sm={12} md={6} lg={6}>
                    <Paper sx={{ boxShadow: '0 1px 3px rgba(0,0,0,0.06)', borderTop: `3px solid ${palette.success.main}`, borderRadius: 2 }}>
                        <Box sx={{ p: 3, pb: 1 }}>
                            <Typography variant="subtitle1" sx={{ fontWeight: 700, color: textPrimary }}>
                                Recent Orders
                            </Typography>
                        </Box>
                        <TableContainer>
                            <Table size="small">
                                <TableHead>
                                    <TableRow sx={{ bgcolor: lightBg }}>
                                        <TableCell sx={{ fontWeight: 700, fontSize: 11, color: textMuted, textTransform: 'uppercase' }}>Customer</TableCell>
                                        <TableCell sx={{ fontWeight: 700, fontSize: 11, color: textMuted, textTransform: 'uppercase' }}>Total</TableCell>
                                        <TableCell sx={{ fontWeight: 700, fontSize: 11, color: textMuted, textTransform: 'uppercase' }}>Status</TableCell>
                                        <TableCell sx={{ fontWeight: 700, fontSize: 11, color: textMuted, textTransform: 'uppercase' }}>Date</TableCell>
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
                                        <TableRow key={order.id} sx={{ '&:hover': { bgcolor: hoverBg } }}>
                                            <TableCell sx={{ fontWeight: 600, fontSize: 13 }}>{order.customer_name}</TableCell>
                                            <TableCell sx={{ fontSize: 13 }}>${Number(order.total).toFixed(2)}</TableCell>
                                            <TableCell>
                                                <Chip label={order.status} size="small"
                                                    sx={{
                                                        bgcolor: order.status === 'paid' ? palette.success.light : order.status === 'pending' ? palette.warning.light : palette.error.light,
                                                        color: order.status === 'paid' ? palette.success.dark : order.status === 'pending' ? palette.warning.dark : palette.error.dark,
                                                        fontWeight: 600, height: 24, fontSize: 12,
                                                    }}
                                                />
                                            </TableCell>
                                            <TableCell sx={{ color: textMuted, fontSize: 13 }}>{order.created_at}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </TableContainer>
                    </Paper>
                </Grid>

                <Grid item xs={12} sm={12} md={6} lg={6}>
                    <Paper sx={{ boxShadow: '0 1px 3px rgba(0,0,0,0.06)', borderTop: `3px solid ${palette.primary.main}`, borderRadius: 2 }}>
                        <Box sx={{ p: 3, pb: 1 }}>
                            <Typography variant="subtitle1" sx={{ fontWeight: 700, color: textPrimary }}>
                                Recent Inventory Movements
                            </Typography>
                        </Box>
                        <TableContainer>
                            <Table size="small">
                                <TableHead>
                                    <TableRow sx={{ bgcolor: lightBg }}>
                                        <TableCell sx={{ fontWeight: 700, fontSize: 11, color: textMuted, textTransform: 'uppercase' }}>Product</TableCell>
                                        <TableCell sx={{ fontWeight: 700, fontSize: 11, color: textMuted, textTransform: 'uppercase' }}>Warehouse</TableCell>
                                        <TableCell sx={{ fontWeight: 700, fontSize: 11, color: textMuted, textTransform: 'uppercase' }}>Type</TableCell>
                                        <TableCell sx={{ fontWeight: 700, fontSize: 11, color: textMuted, textTransform: 'uppercase' }}>Qty</TableCell>
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
                                        <TableRow key={m.id} sx={{ '&:hover': { bgcolor: hoverBg } }}>
                                            <TableCell sx={{ fontWeight: 600, fontSize: 13 }}>{m.product_name}</TableCell>
                                            <TableCell sx={{ fontSize: 13 }}>{m.warehouse_name}</TableCell>
                                            <TableCell>
                                                <Chip label={m.type} size="small"
                                                    sx={{
                                                        bgcolor: m.type === 'in' ? palette.success.light : m.type === 'out' ? palette.error.light : palette.warning.light,
                                                        color: m.type === 'in' ? palette.success.dark : m.type === 'out' ? palette.error.dark : palette.warning.dark,
                                                        fontWeight: 600, height: 24, fontSize: 12,
                                                    }}
                                                />
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
