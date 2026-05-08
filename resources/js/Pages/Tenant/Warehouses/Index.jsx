import TenantLayout from '@/Layouts/TenantLayout';
import { Head, useForm, router } from '@inertiajs/react';
import {
    Box, Paper, Typography, Table, TableBody, TableCell,
    TableContainer, TableHead, TableRow, Button, IconButton, Tooltip,
    Dialog, DialogTitle, DialogContent, DialogActions,
} from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import { useState } from 'react';
import { toast } from 'sonner';
import { FormInput, ButtonPrimary, ButtonSecondary } from '@/Components/FormElements';

export default function WarehouseIndex({ warehouses }) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const data = warehouses.data || warehouses;

    const { data: formData, setData, errors, post, put, reset, processing } = useForm({
        name: '',
        location: '',
    });

    const openCreate = () => {
        setEditing(null);
        reset();
        setOpen(true);
    };

    const openEdit = (wh) => {
        setEditing(wh);
        setData({ name: wh.name, location: wh.location || '' });
        setOpen(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editing) {
            put(route('tenant.warehouses.update', editing.id), {
                onSuccess: () => { setOpen(false); toast.success('Warehouse updated'); },
                onError: () => toast.error('Failed to update warehouse'),
            });
        } else {
            post(route('tenant.warehouses.store'), {
                onSuccess: () => { setOpen(false); toast.success('Warehouse created'); },
                onError: () => toast.error('Failed to create warehouse'),
            });
        }
    };

    const handleDelete = (wh) => {
        if (!confirm(`Delete "${wh.name}"?`)) return;
        router.delete(route('tenant.warehouses.destroy', wh.id), {
            onSuccess: () => toast.success('Warehouse deleted'),
            onError: () => toast.error('Failed to delete'),
        });
    };

    return (
        <TenantLayout>
            <Head title="Warehouses" />

            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 3 }}>
                <Typography variant="h5" sx={{ fontWeight: 700, color: '#0f172a' }}>
                    Warehouses
                </Typography>
                <Button variant="contained" size="small" startIcon={<AddIcon />}
                    onClick={openCreate}
                    sx={{ bgcolor: '#22c55e', '&:hover': { bgcolor: '#16a34a' }, fontWeight: 600 }}>
                    Add Warehouse
                </Button>
            </Box>

            <Paper sx={{ boxShadow: '0 1px 3px rgba(0,0,0,0.06)' }}>
                <TableContainer>
                    <Table size="small">
                        <TableHead>
                            <TableRow sx={{ bgcolor: '#f8fafc' }}>
                                {['Name', 'Location', 'Actions'].map(h => (
                                    <TableCell key={h} sx={{ fontWeight: 700, fontSize: 11, color: '#64748b', textTransform: 'uppercase' }}>{h}</TableCell>
                                ))}
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={3} sx={{ textAlign: 'center', py: 5, color: '#94a3b8' }}>
                                        No warehouses found.
                                    </TableCell>
                                </TableRow>
                            ) : data.map((wh) => (
                                <TableRow key={wh.id} sx={{ '&:hover': { bgcolor: '#f8fafc' } }}>
                                    <TableCell sx={{ fontWeight: 600, fontSize: 13 }}>{wh.name}</TableCell>
                                    <TableCell sx={{ fontSize: 13 }}>{wh.location || '—'}</TableCell>
                                    <TableCell>
                                        <Tooltip title="Edit">
                                            <IconButton size="small" sx={{ color: '#3b82f6' }} onClick={() => openEdit(wh)}>
                                                <EditIcon fontSize="small" />
                                            </IconButton>
                                        </Tooltip>
                                        <Tooltip title="Delete">
                                            <IconButton size="small" sx={{ color: '#ef4444' }} onClick={() => handleDelete(wh)}>
                                                <DeleteIcon fontSize="small" />
                                            </IconButton>
                                        </Tooltip>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </TableContainer>
            </Paper>

            <Dialog open={open} onClose={() => setOpen(false)} maxWidth="sm" fullWidth>
                <form onSubmit={handleSubmit}>
                    <DialogTitle sx={{ fontWeight: 700, fontSize: 16, color: '#0f172a' }}>
                        {editing ? 'Edit Warehouse' : 'Add Warehouse'}
                    </DialogTitle>
                    <DialogContent>
                        <FormInput label="Name" required error={errors.name}>
                            <input type="text" value={formData.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="Warehouse name" />
                        </FormInput>
                        <FormInput label="Location" error={errors.location}>
                            <input type="text" value={formData.location}
                                onChange={(e) => setData('location', e.target.value)}
                                placeholder="e.g., Building A, Floor 2" />
                        </FormInput>
                    </DialogContent>
                    <DialogActions sx={{ p: 2, borderTop: '1px solid #f1f5f9' }}>
                        <ButtonSecondary onClick={() => setOpen(false)}>Cancel</ButtonSecondary>
                        <ButtonPrimary type="submit" disabled={processing}>
                            {processing ? 'Saving...' : (editing ? 'Update' : 'Create')}
                        </ButtonPrimary>
                    </DialogActions>
                </form>
            </Dialog>
        </TenantLayout>
    );
}
