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

export default function ProductIndex({ products }) {
    const [page, setPage] = useState(0);
    const [rowsPerPage] = useState(15);
    const data = products.data || products;

    const handleDelete = (product) => {
        if (!confirm(`Delete "${product.name}"? This can be undone.`)) return;
        router.delete(route('tenant.products.destroy', product.id), {
            onSuccess: () => toast.success('Product deleted'),
            onError: () => toast.error('Failed to delete product'),
        });
    };

    const handlePageChange = (_, newPage) => {
        router.get(products.path + '?page=' + (newPage + 1));
    };

    return (
        <TenantLayout>
            <Head title="Products" />

            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 3 }}>
                <Typography variant="h5" sx={{ fontWeight: 700, color: '#0f172a' }}>
                    Products
                </Typography>
                <Link href={route('tenant.products.create')}>
                    <Button variant="contained" size="small" startIcon={<AddIcon />}
                        sx={{ bgcolor: '#22c55e', '&:hover': { bgcolor: '#16a34a' }, fontWeight: 600 }}>
                        Add Product
                    </Button>
                </Link>
            </Box>

            <Paper sx={{ boxShadow: '0 1px 3px rgba(0,0,0,0.06)' }}>
                <TableContainer>
                    <Table size="small">
                        <TableHead>
                            <TableRow sx={{ bgcolor: '#f8fafc' }}>
                                {['Name', 'SKU', 'Category', 'Price', 'Cost', 'Status', 'Actions'].map(h => (
                                    <TableCell key={h} sx={{ fontWeight: 700, fontSize: 11, color: '#64748b', textTransform: 'uppercase' }}>
                                        {h}
                                    </TableCell>
                                ))}
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={7} sx={{ textAlign: 'center', py: 5, color: '#94a3b8' }}>
                                        No products found.
                                    </TableCell>
                                </TableRow>
                            ) : data.map((product) => (
                                <TableRow key={product.id} sx={{ '&:hover': { bgcolor: '#f8fafc' } }}>
                                    <TableCell sx={{ fontWeight: 600, fontSize: 13 }}>{product.name}</TableCell>
                                    <TableCell><code style={{ background: '#f1f5f9', padding: '2px 6px', borderRadius: 3, fontSize: 12 }}>{product.sku}</code></TableCell>
                                    <TableCell sx={{ fontSize: 13 }}>{product.category?.name || '—'}</TableCell>
                                    <TableCell sx={{ fontSize: 13 }}>${Number(product.price).toFixed(2)}</TableCell>
                                    <TableCell sx={{ fontSize: 13 }}>{product.cost ? `$${Number(product.cost).toFixed(2)}` : '—'}</TableCell>
                                    <TableCell>
                                        <Chip label={product.active ? 'Active' : 'Inactive'} size="small"
                                            sx={{
                                                bgcolor: product.active ? '#dcfce7' : '#fee2e2',
                                                color: product.active ? '#166534' : '#991b1b',
                                                fontWeight: 600, height: 24, fontSize: 12,
                                            }} />
                                    </TableCell>
                                    <TableCell>
                                        <Tooltip title="Edit">
                                            <IconButton size="small" sx={{ color: '#3b82f6' }}
                                                component={Link} href={route('tenant.products.edit', product.id)}>
                                                <EditIcon fontSize="small" />
                                            </IconButton>
                                        </Tooltip>
                                        <Tooltip title="Delete">
                                            <IconButton size="small" sx={{ color: '#ef4444' }} onClick={() => handleDelete(product)}>
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
        </TenantLayout>
    );
}
