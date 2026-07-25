import TenantLayout from '@/Layouts/TenantLayout';
import { Head, Link, router } from '@inertiajs/react';
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
import IconButton from '@mui/material/IconButton';
import Tooltip from '@mui/material/Tooltip';
import AddIcon from '@mui/icons-material/Add';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import { useState } from 'react';
import { toast } from 'sonner';
import ConfirmDialog from '@/Components/ConfirmDialog';

export default function ProductIndex({ products }) {
    const [page, setPage] = useState(0);
    const [rowsPerPage] = useState(5);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const data = products.data || products;

    const handleDelete = (product) => {
        setDeleteTarget(product);
    };

    const confirmDelete = () => {
        if (!deleteTarget) return;
        router.delete(route('tenant.products.destroy', deleteTarget.id), {
            onSuccess: () => { toast.success('Product deleted'); setDeleteTarget(null); },
            onError: () => { toast.error('Failed to delete product'); setDeleteTarget(null); },
        });
    };

    const handlePageChange = (_, newPage) => {
        router.get(products.path + '?page=' + (newPage + 1));
    };

    return (
        <TenantLayout>
            <Head title="Products" />

            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 3 }}>
                <Typography variant="h5" sx={{ fontWeight: 700, color: 'text.primary' }}>
                    Products
                </Typography>
                <Link href={route('tenant.products.create')}>
                    <Button variant="contained" size="small" startIcon={<AddIcon />}
                        sx={{ fontWeight: 600 }}>
                        Add Product
                    </Button>
                </Link>
            </Box>

            <Paper sx={{ boxShadow: '0 1px 3px rgba(0,0,0,0.06)' }}>
                <TableContainer>
                    <Table size="small">
                        <TableHead>
                            <TableRow sx={{ bgcolor: 'action.hover' }}>
                                {['Name', 'SKU', 'Category', 'Price', 'Cost', 'Status', 'Actions'].map(h => (
                                    <TableCell key={h} sx={{ fontWeight: 700, fontSize: 11, color: 'text.secondary', textTransform: 'uppercase' }}>
                                        {h}
                                    </TableCell>
                                ))}
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={7} sx={{ textAlign: 'center', py: 5, color: 'text.disabled' }}>
                                        No products found.
                                    </TableCell>
                                </TableRow>
                            ) : data.map((product) => (
                                <TableRow key={product.id} sx={{ '&:hover': { bgcolor: 'action.hover' } }}>
                                    <TableCell sx={{ fontWeight: 600, fontSize: 13 }}>{product.name}</TableCell>
                                    <TableCell><code style={{ background: 'var(--mui-palette-action-hover, #f1f5f9)', padding: '2px 6px', borderRadius: 3, fontSize: 12 }}>{product.sku}</code></TableCell>
                                    <TableCell sx={{ fontSize: 13 }}>{product.category?.name || '—'}</TableCell>
                                    <TableCell sx={{ fontSize: 13 }}>${Number(product.price).toFixed(2)}</TableCell>
                                    <TableCell sx={{ fontSize: 13 }}>{product.cost ? `$${Number(product.cost).toFixed(2)}` : '—'}</TableCell>
                                    <TableCell>
                                        <Chip label={product.active ? 'Active' : 'Inactive'} size="small"
                                            sx={{
                                                bgcolor: product.active ? 'success.light' : 'error.light',
                                                color: product.active ? 'success.dark' : 'error.dark',
                                                fontWeight: 600, height: 24, fontSize: 12,
                                            }} />
                                    </TableCell>
                                    <TableCell>
                                        <Tooltip title="Edit">
                                            <IconButton size="small" sx={{ color: 'primary.main' }}
                                                component={Link} href={route('tenant.products.edit', product.id)}>
                                                <EditIcon fontSize="small" />
                                            </IconButton>
                                        </Tooltip>
                                        <Tooltip title="Delete">
                                            <IconButton size="small" sx={{ color: 'error.main' }} onClick={() => handleDelete(product)}>
                                                <DeleteIcon fontSize="small" />
                                            </IconButton>
                                        </Tooltip>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </TableContainer>
                {products.last_page > 1 && (
                    <TablePagination component="div" count={products.total} page={products.current_page - 1}
                        onPageChange={handlePageChange} rowsPerPage={products.per_page}
                        rowsPerPageOptions={[products.per_page]} />
                )}
            </Paper>

            <ConfirmDialog
                open={!!deleteTarget}
                title="Delete Product"
                message={deleteTarget ? `Delete "${deleteTarget.name}"? This can be undone.` : ''}
                confirmLabel="Delete"
                onConfirm={confirmDelete}
                onCancel={() => setDeleteTarget(null)}
            />
        </TenantLayout>
    );
}
