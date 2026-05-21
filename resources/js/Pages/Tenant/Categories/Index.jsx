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

export default function CategoryIndex({ categories }) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const data = categories.data || categories;

    const { data: formData, setData, errors, post, put, reset, processing } = useForm({
        name: '',
        parent_id: '',
    });

    const openCreate = () => {
        setEditing(null);
        reset();
        setOpen(true);
    };

    const openEdit = (cat) => {
        setEditing(cat);
        setData({ name: cat.name, parent_id: cat.parent_id || '' });
        setOpen(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editing) {
            put(route('tenant.categories.update', editing.id), {
                onSuccess: () => { setOpen(false); toast.success('Category updated'); },
                onError: () => toast.error('Failed to update category'),
            });
        } else {
            post(route('tenant.categories.store'), {
                onSuccess: () => { setOpen(false); toast.success('Category created'); },
                onError: () => toast.error('Failed to create category'),
            });
        }
    };

    const handleDelete = (cat) => {
        if (!confirm(`Delete "${cat.name}"?`)) return;
        router.delete(route('tenant.categories.destroy', cat.id), {
            onSuccess: () => toast.success('Category deleted'),
            onError: (err) => toast.error(err?.message || 'Failed to delete'),
        });
    };

    const allCategories = data;

    return (
        <TenantLayout>
            <Head title="Categories" />

            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 3 }}>
                <Typography variant="h5" sx={{ fontWeight: 700, color: '#0f172a' }}>
                    Categories
                </Typography>
                <Button variant="contained" size="small" startIcon={<AddIcon />}
                    onClick={openCreate}
                    sx={{ bgcolor: '#22c55e', '&:hover': { bgcolor: '#16a34a' }, fontWeight: 600 }}>
                    Add Category
                </Button>
            </Box>

            <Paper sx={{ boxShadow: '0 1px 3px rgba(0,0,0,0.06)' }}>
                <TableContainer>
                    <Table size="small">
                        <TableHead>
                            <TableRow sx={{ bgcolor: '#f8fafc' }}>
                                {['Name', 'Parent', 'Products', 'Actions'].map(h => (
                                    <TableCell key={h} sx={{ fontWeight: 700, fontSize: 11, color: '#64748b', textTransform: 'uppercase' }}>{h}</TableCell>
                                ))}
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={4} sx={{ textAlign: 'center', py: 5, color: '#94a3b8' }}>
                                        No categories found.
                                    </TableCell>
                                </TableRow>
                            ) : data.map((cat) => (
                                <TableRow key={cat.id} sx={{ '&:hover': { bgcolor: '#f8fafc' } }}>
                                    <TableCell sx={{ fontWeight: 600, fontSize: 13 }}>{cat.name}</TableCell>
                                    <TableCell sx={{ fontSize: 13 }}>{cat.parent?.name || '—'}</TableCell>
                                    <TableCell sx={{ fontSize: 13 }}>{cat.products_count ?? 0}</TableCell>
                                    <TableCell>
                                        <Tooltip title="Edit">
                                            <IconButton size="small" sx={{ color: '#3b82f6' }} onClick={() => openEdit(cat)}>
                                                <EditIcon fontSize="small" />
                                            </IconButton>
                                        </Tooltip>
                                        <Tooltip title="Delete">
                                            <IconButton size="small" sx={{ color: '#ef4444' }} onClick={() => handleDelete(cat)}>
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
                        {editing ? 'Edit Category' : 'Add Category'}
                    </DialogTitle>
                    <DialogContent>
                        <FormInput label="Name" required error={errors.name}>
                            <input type="text" value={formData.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="Category name" />
                        </FormInput>
                        <FormInput label="Parent Category" error={errors.parent_id}>
                            <select value={formData.parent_id}
                                onChange={(e) => setData('parent_id', e.target.value)}
                                style={{ width: '100%', padding: '10px 12px', border: '1px solid #e2e8f0', borderRadius: 6, fontSize: 14, fontFamily: 'inherit' }}>
                                <option value="">None (top level)</option>
                                {allCategories.filter(c => !editing || c.id !== editing.id).map((cat) => (
                                    <option key={cat.id} value={cat.id}>{cat.name}</option>
                                ))}
                            </select>
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
