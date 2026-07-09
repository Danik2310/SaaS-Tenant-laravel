import TenantLayout from '@/Layouts/TenantLayout';
import { Head, useForm, router } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Paper from '@mui/material/Paper';
import Typography from '@mui/material/Typography';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableContainer from '@mui/material/TableContainer';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';
import Button from '@mui/material/Button';
import IconButton from '@mui/material/IconButton';
import Tooltip from '@mui/material/Tooltip';
import Dialog from '@mui/material/Dialog';
import DialogTitle from '@mui/material/DialogTitle';
import DialogContent from '@mui/material/DialogContent';
import DialogActions from '@mui/material/DialogActions';
import AddIcon from '@mui/icons-material/Add';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import { useState } from 'react';
import { toast } from 'sonner';
import { FormInput, ButtonPrimary, ButtonSecondary } from '@/Components/FormElements';
import ConfirmDialog from '@/Components/ConfirmDialog';

export default function WarehouseIndex({ warehouses }) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [deleteTarget, setDeleteTarget] = useState(null);
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
        setDeleteTarget(wh);
    };

    const confirmDelete = () => {
        if (!deleteTarget) return;
        router.delete(route('tenant.warehouses.destroy', deleteTarget.id), {
            onSuccess: () => { toast.success('Warehouse deleted'); setDeleteTarget(null); },
            onError: () => { toast.error('Failed to delete'); setDeleteTarget(null); },
        });
    };

    return (
        <TenantLayout>
            <Head title="Warehouses" />

            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 3 }}>
                <Typography variant="h5" sx={{ fontWeight: 700, color: 'text.primary' }}>
                    Warehouses
                </Typography>
                <Button variant="contained" size="small" startIcon={<AddIcon />}
                    onClick={openCreate}
                    sx={{ fontWeight: 600 }}>
                    Add Warehouse
                </Button>
            </Box>

            <Paper sx={{ boxShadow: '0 1px 3px rgba(0,0,0,0.06)' }}>
                <TableContainer>
                    <Table size="small">
                        <TableHead>
                            <TableRow sx={{ bgcolor: 'action.hover' }}>
                                {['Name', 'Location', 'Actions'].map(h => (
                                    <TableCell key={h} sx={{ fontWeight: 700, fontSize: 11, color: 'text.secondary', textTransform: 'uppercase' }}>{h}</TableCell>
                                ))}
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={3} sx={{ textAlign: 'center', py: 5, color: 'text.disabled' }}>
                                        No warehouses found.
                                    </TableCell>
                                </TableRow>
                            ) : data.map((wh) => (
                                <TableRow key={wh.id} sx={{ '&:hover': { bgcolor: 'action.hover' } }}>
                                    <TableCell sx={{ fontWeight: 600, fontSize: 13 }}>{wh.name}</TableCell>
                                    <TableCell sx={{ fontSize: 13 }}>{wh.location || '—'}</TableCell>
                                    <TableCell>
                                        <Tooltip title="Edit">
                                            <IconButton size="small" sx={{ color: 'primary.main' }} onClick={() => openEdit(wh)}>
                                                <EditIcon fontSize="small" />
                                            </IconButton>
                                        </Tooltip>
                                        <Tooltip title="Delete">
                                            <IconButton size="small" sx={{ color: 'error.main' }} onClick={() => handleDelete(wh)}>
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
                    <DialogTitle sx={{ fontWeight: 700, fontSize: 16, color: 'text.primary' }}>
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
                    <DialogActions sx={{ p: 2, borderTop: 1, borderColor: 'divider' }}>
                        <ButtonSecondary onClick={() => setOpen(false)}>Cancel</ButtonSecondary>
                        <ButtonPrimary type="submit" disabled={processing}>
                            {processing ? 'Saving...' : (editing ? 'Update' : 'Create')}
                        </ButtonPrimary>
                    </DialogActions>
                </form>
            </Dialog>

            <ConfirmDialog
                open={!!deleteTarget}
                title="Delete Warehouse"
                message={deleteTarget ? `Delete "${deleteTarget.name}"?` : ''}
                confirmLabel="Delete"
                onConfirm={confirmDelete}
                onCancel={() => setDeleteTarget(null)}
            />
        </TenantLayout>
    );
}
