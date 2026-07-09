import React, { useState } from 'react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import Button from '@mui/material/Button';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableContainer from '@mui/material/TableContainer';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';
import Paper from '@mui/material/Paper';
import IconButton from '@mui/material/IconButton';
import Chip from '@mui/material/Chip';
import Dialog from '@mui/material/Dialog';
import DialogTitle from '@mui/material/DialogTitle';
import DialogContent from '@mui/material/DialogContent';
import DialogContentText from '@mui/material/DialogContentText';
import DialogActions from '@mui/material/DialogActions';
import CircularProgress from '@mui/material/CircularProgress';
import Switch from '@mui/material/Switch';
import FormControlLabel from '@mui/material/FormControlLabel';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import { toast } from 'sonner';
import api from '../../../services/api';
import DataTable from '@/Components/DataTable';
import PaymentMethodModal from './PaymentMethodModal';

export default function PaymentMethodsTab({ paymentMethods, fetchPaymentMethods, setError }) {
    const [paymentModalOpen, setPaymentModalOpen] = useState(false);
    const [editingPayment, setEditingPayment] = useState(null);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [deletingPayment, setDeletingPayment] = useState(null);
    const [deleting, setDeleting] = useState(false);
    const [toggling, setToggling] = useState(null);

    const handleEditPayment = async (row) => {
        try {
            const response = await api.get(`/admin/api/payment-methods/${row.id}`);
            setEditingPayment(response.data.method);
            setPaymentModalOpen(true);
        } catch (error) {
            toast.error('Failed to load payment method details');
            setError('Failed to load payment method details');
        }
    };

    const handleDeletePayment = (row) => {
        setDeletingPayment(row);
        setDeleteDialogOpen(true);
    };

    const handleToggleActive = async (row) => {
        setToggling(row.id);
        try {
            await api.patch(`/admin/api/payment-methods/${row.id}/toggle-active`);
            fetchPaymentMethods();
            toast.success('Payment method status updated');
        } catch (error) {
            toast.error('Failed to update payment method status');
            setError('Failed to update payment method status');
        } finally {
            setToggling(null);
        }
    };

    const confirmDeletePayment = async () => {
        if (!deletingPayment) return;

        setDeleting(true);
        try {
            await api.delete(`/admin/api/payment-methods/${deletingPayment.id}`);
            fetchPaymentMethods();
            toast.success('Payment method deleted successfully');
            setError(null);
        } catch (err) {
            const errorMessage = err.response?.data?.message || err.response?.data?.error || 'Failed to delete payment method';
            toast.error(errorMessage);
            setError(errorMessage);
        } finally {
            setDeleting(false);
            setDeleteDialogOpen(false);
            setDeletingPayment(null);
        }
    };

    const columns = [
        { accessorKey: 'name', header: 'Name' },
        { accessorKey: 'provider', header: 'Provider' },
        {
            accessorKey: 'mode',
            header: 'Mode',
            Cell: ({ cell }) => (
                <Chip
                    label={cell.getValue()}
                    size="small"
                    color={cell.getValue() === 'live' ? 'success' : 'warning'}
                    sx={{ fontWeight: 600, fontSize: 12 }}
                />
            ),
        },
        {
            accessorKey: 'active',
            header: 'Status',
            Cell: ({ cell }) => {
                const row = cell.row || {};
                return (
                <FormControlLabel
                    control={
                        <Switch
                            checked={cell.getValue()}
                            onChange={() => handleToggleActive(row)}
                            disabled={toggling === row.id}
                            size="small"
                            sx={{
                                '& .MuiSwitch-switchBase.Mui-checked': {
                                    color: '#22c55e',
                                    '&:hover': { bgcolor: '#22c55e1a' },
                                },
                                '& .MuiSwitch-switchBase.Mui-checked + .MuiSwitch-track': {
                                    bgcolor: '#22c55e',
                                },
                            }}
                        />
                    }
                    label={cell.getValue() ? 'Active' : 'Inactive'}
                    slotProps={{ typography: { fontSize: 13 } }}
                />
            )},
        },
    ];

    return (
        <Box>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 2, alignItems: 'center' }}>
                <Typography variant="subtitle1" sx={{ fontWeight: 600, color: '#0f172a' }}>
                    Payment Gateways
                </Typography>
                <Button
                    variant="contained"
                    size="small"
                    onClick={() => {
                        setEditingPayment(null);
                        setPaymentModalOpen(true);
                    }}
                    sx={{
                        bgcolor: '#22c55e',
                        '&:hover': { bgcolor: '#16a34a' },
                        fontWeight: 600,
                        fontSize: '13px',
                    }}
                >
                    Add Method
                </Button>
            </Box>

            <DataTable
                columns={columns}
                data={paymentMethods}
                onEdit={handleEditPayment}
                onDelete={handleDeletePayment}
                emptyMessage="No payment methods configured yet."
            />

            <Dialog
                open={deleteDialogOpen}
                onClose={() => { setDeleteDialogOpen(false); setDeletingPayment(null); }}
            >
                <DialogTitle>Delete Payment Method</DialogTitle>
                <DialogContent>
                    <DialogContentText>
                        Are you sure you want to delete the payment method "{deletingPayment?.name}"?
                        This action cannot be undone and may affect existing subscriptions.
                    </DialogContentText>
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => { setDeleteDialogOpen(false); setDeletingPayment(null); }} disabled={deleting}>
                        Cancel
                    </Button>
                    <Button
                        onClick={confirmDeletePayment}
                        color="error"
                        variant="contained"
                        disabled={deleting}
                        startIcon={deleting ? <CircularProgress size={16} /> : null}
                    >
                        {deleting ? 'Deleting...' : 'Delete'}
                    </Button>
                </DialogActions>
            </Dialog>

            <PaymentMethodModal
                open={paymentModalOpen}
                onClose={() => setPaymentModalOpen(false)}
                editingPayment={editingPayment}
                fetchPaymentMethods={fetchPaymentMethods}
                setError={setError}
            />
        </Box>
    );
}
