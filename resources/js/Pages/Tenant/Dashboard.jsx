import TenantLayout from '@/Layouts/TenantLayout';
import { Head } from '@inertiajs/react';
import {
    Box, Paper, Typography, Grid, Card, CardContent, Avatar,
    Table, TableBody, TableCell, TableContainer, TableHead, TableRow,
    Chip,
} from '@mui/material';
import InventoryIcon from '@mui/icons-material/Inventory';
import ShoppingCartIcon from '@mui/icons-material/ShoppingCart';
import PeopleIcon from '@mui/icons-material/People';
import WarningIcon from '@mui/icons-material/Warning';

function StatCard({ title, value, icon, color }) {
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
                    <Typography variant="body2" color="text.secondary">
                        {title}
                    </Typography>
                </Box>
            </CardContent>
        </Card>
    );
}

export default function Dashboard({ stats, recentOrders, recentMovements }) {
    return (
        <TenantLayout>
            <Head title="Dashboard" />

            <Typography variant="h5" sx={{ fontWeight: 700, color: '#0f172a', mb: 3 }}>
                Dashboard
            </Typography>

            <Grid container spacing={2} sx={{ mb: 4 }}>
                <Grid item xs={6} sm={6} md={3}>
                    <StatCard title="Active Products" value={stats.active_products} icon={<InventoryIcon />} color="#3b82f6" />
                </Grid>
                <Grid item xs={6} sm={6} md={3}>
                    <StatCard title="Total Orders" value={stats.total_orders} icon={<ShoppingCartIcon />} color="#22c55e" />
                </Grid>
                <Grid item xs={6} sm={6} md={3}>
                    <StatCard title="Customers" value={stats.total_customers} icon={<PeopleIcon />} color="#f59e0b" />
                </Grid>
                <Grid item xs={6} sm={6} md={3}>
                    <StatCard title="Low Stock Items" value={stats.low_stock_count} icon={<WarningIcon />} color="#ef4444" />
                </Grid>
            </Grid>

            <Grid container spacing={3}>
                <Grid item xs={12} lg={6}>
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
                                    {recentOrders.length === 0 ? (
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

                <Grid item xs={12} lg={6}>
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
                                    {recentMovements.length === 0 ? (
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
