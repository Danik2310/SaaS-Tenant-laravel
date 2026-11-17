import TenantLayout from '@/Layouts/TenantLayout';
import { Head, router } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Paper from '@mui/material/Paper';
import Typography from '@mui/material/Typography';
import Button from '@mui/material/Button';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableContainer from '@mui/material/TableContainer';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';
import TablePagination from '@mui/material/TablePagination';
import Chip from '@mui/material/Chip';

const levelColors = {
    critical: { bg: '#fee2e2', fg: '#991b1b' },
    warning: { bg: '#fef9c3', fg: '#854d0e' },
    info: { bg: '#e0f2fe', fg: '#075985' },
};

export default function NotificationsIndex({ notifications }) {
    const items = notifications.data ?? notifications;

    const handlePageChange = (_, newPage) => {
        router.get(notifications.path + '?page=' + (newPage + 1));
    };

    return (
        <TenantLayout>
            <Head title="Notifications" />

            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 3 }}>
                <Typography variant="h5" sx={{ fontWeight: 700, color: '#0f172a' }}>
                    Notifications
                </Typography>
                <Box sx={{ display: 'flex', gap: 1 }}>
                    <Button size="small" onClick={() => router.visit(route('tenant.notifications.settings'))}>
                        Settings
                    </Button>
                    <Button
                        size="small"
                        variant="contained"
                        onClick={() => router.post(route('tenant.notifications.read-all'))}
                    >
                        Mark all read
                    </Button>
                </Box>
            </Box>

            <Paper sx={{ boxShadow: '0 1px 3px rgba(0,0,0,0.06)' }}>
                <TableContainer>
                    <Table size="small">
                        <TableHead>
                            <TableRow sx={{ bgcolor: '#f8fafc' }}>
                                {['Notification', 'Level', 'Received'].map((h) => (
                                    <TableCell key={h} sx={{ fontWeight: 700, fontSize: 11, color: '#64748b', textTransform: 'uppercase' }}>
                                        {h}
                                    </TableCell>
                                ))}
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {items.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={3} sx={{ textAlign: 'center', py: 5, color: '#94a3b8' }}>
                                        You're all caught up.
                                    </TableCell>
                                </TableRow>
                            ) : items.map((item) => {
                                const colors = levelColors[item.level] ?? levelColors.info;
                                return (
                                    <TableRow
                                        key={item.id}
                                        sx={{
                                            cursor: 'pointer',
                                            bgcolor: item.read_at ? 'transparent' : '#f8fafc',
                                            '&:hover': { bgcolor: '#f1f5f9' },
                                        }}
                                        onClick={() => router.patch(route('tenant.notifications.read', item.id), {}, {
                                            preserveScroll: true,
                                            onSuccess: () => {
                                                if (item.data?.action_url) router.visit(item.data.action_url);
                                            },
                                        })}
                                    >
                                        <TableCell>
                                            <Typography sx={{ fontWeight: item.read_at ? 500 : 700, fontSize: 13 }}>
                                                {item.title}
                                            </Typography>
                                            {item.body && (
                                                <Typography sx={{ fontSize: 12, color: '#64748b' }}>{item.body}</Typography>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <Chip
                                                label={item.level}
                                                size="small"
                                                sx={{ bgcolor: colors.bg, color: colors.fg, fontWeight: 700, height: 24, fontSize: 11 }}
                                            />
                                        </TableCell>
                                        <TableCell sx={{ fontSize: 12, color: '#64748b' }}>
                                            {item.created_at ? new Date(item.created_at).toLocaleString() : '—'}
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                </TableContainer>
                {notifications.last_page > 1 && (
                    <TablePagination
                        component="div"
                        count={notifications.total}
                        page={notifications.current_page - 1}
                        onPageChange={handlePageChange}
                        rowsPerPage={notifications.per_page}
                        rowsPerPageOptions={[notifications.per_page]}
                    />
                )}
            </Paper>
        </TenantLayout>
    );
}
