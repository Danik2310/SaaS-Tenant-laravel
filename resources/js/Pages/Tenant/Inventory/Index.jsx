import TenantLayout from '@/Layouts/TenantLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
    Box, Paper, Typography, Button, Table, TableBody, TableCell,
    TableContainer, TableHead, TableRow, TablePagination, Chip,
} from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import { useState } from 'react';

export default function InventoryIndex({ movements }) {
    const data = movements.data || movements;

    const handlePageChange = (_, newPage) => {
        router.get(movements.path + '?page=' + (newPage + 1));
    };

    return (
        <TenantLayout>
            <Head title="Inventory Movements" />

            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 3 }}>
                <Typography variant="h5" sx={{ fontWeight: 700, color: '#0f172a' }}>
                    Inventory Movements
                </Typography>
                <Link href={route('tenant.inventory.create')}>
                    <Button variant="contained" size="small" startIcon={<AddIcon />}
                        sx={{ bgcolor: '#22c55e', '&:hover': { bgcolor: '#16a34a' }, fontWeight: 600 }}>
                        Record Movement
                    </Button>
                </Link>
            </Box>

            <Paper sx={{ boxShadow: '0 1px 3px rgba(0,0,0,0.06)' }}>
                <TableContainer>
                    <Table size="small">
                        <TableHead>
                            <TableRow sx={{ bgcolor: '#f8fafc' }}>
                                {['Product', 'Warehouse', 'Type', 'Quantity', 'Reason', 'Date'].map(h => (
                                    <TableCell key={h} sx={{ fontWeight: 700, fontSize: 11, color: '#64748b', textTransform: 'uppercase' }}>{h}</TableCell>
                                ))}
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} sx={{ textAlign: 'center', py: 5, color: '#94a3b8' }}>
                                        No movements recorded yet.
                                    </TableCell>
                                </TableRow>
                            ) : data.map((m) => (
                                <TableRow key={m.id} sx={{ '&:hover': { bgcolor: '#f8fafc' } }}>
                                    <TableCell sx={{ fontWeight: 600, fontSize: 13 }}>{m.product?.name || '—'}</TableCell>
                                    <TableCell sx={{ fontSize: 13 }}>{m.warehouse?.name || '—'}</TableCell>
                                    <TableCell>
                                        <Chip label={m.type} size="small"
                                            sx={{
                                                bgcolor: m.type === 'in' ? '#dcfce7' : m.type === 'out' ? '#fee2e2' : '#fef9c3',
                                                color: m.type === 'in' ? '#166534' : m.type === 'out' ? '#991b1b' : '#854d0e',
                                                fontWeight: 600, height: 24, fontSize: 12,
                                            }} />
                                    </TableCell>
                                    <TableCell sx={{ fontSize: 13, fontWeight: 600 }}>{m.quantity}</TableCell>
                                    <TableCell sx={{ fontSize: 13, color: '#64748b' }}>{m.reason || '—'}</TableCell>
                                    <TableCell sx={{ fontSize: 13, color: '#64748b' }}>
                                        {m.created_at ? new Date(m.created_at).toLocaleDateString() : '—'}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </TableContainer>
                {movements.last_page > 1 && (
                    <TablePagination component="div" count={movements.total}
                        page={movements.current_page - 1} onPageChange={handlePageChange}
                        rowsPerPage={movements.per_page} rowsPerPageOptions={[movements.per_page]} />
                )}
            </Paper>
        </TenantLayout>
    );
}
