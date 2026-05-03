import React, { useState } from 'react';
import {
    Box,
    Typography,
    Button,
    Table,
    TableBody,
    TableCell,
    TableContainer,
    TableHead,
    TableRow,
    Paper,
    IconButton,
    Chip,
    Dialog,
    DialogTitle,
    DialogContent,
    DialogContentText,
    DialogActions,
    CircularProgress,
    Switch,
    FormControlLabel,
} from '@mui/material';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import AddIcon from '@mui/icons-material/Add';
import { toast } from 'sonner';
import api from '../../../../services/api';
import PaymentMethodModal from './PaymentMethodModal';

export default function PaymentMethodsTab({ paymentMethods, fetchPaymentMethods, setError }) {
    const [paymentModalOpen, setPaymentModalOpen] = useState(false);
    const [editingPayment, setEditingPayment] = useState(null);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [deletingPayment, setDeletingPayment] = useState(null);
    const [deleting, setDeleting] = useState(false);
    const [toggling, setToggling] = useState(null);

    const handleEditPayment = async (method) => {
        try {
            const response = await api.get(`/admin/api/payment-methods/${method.id}`);
            setEditingPayment(response.data.method);
            setPaymentModalOpen(true);
        } catch (error) {
            console.error('Error fetching payment method details:', error);
            const message = 'Failed to load payment method details for editing';
            toast.error(message);
            setError(message);
        }
    };

    const handleDeletePayment = (method) => {
        setDeletingPayment(method);
        setDeleteDialogOpen(true);
    };

    const handleToggleActive = async (methodId) => {
        setToggling(methodId);
        try {
            await api.patch(`/admin/api/payment-methods/${methodId}/toggle-active`);
            fetchPaymentMethods();
            toast.success('Payment method status updated');
        } catch (error) {
            console.error('Error toggling payment method status:', error);
            const message = 'Failed to update payment method status';
            toast.error(message);
            setError(message);
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
            setDeleteDialogOpen(false);
            setDeletingPayment(null);
        } catch (err) {
            const errorMessage = err.response?.data?.message || err.response?.data?.error || 'Failed to delete payment method';
            toast.error(errorMessage);
            setError(errorMessage);
        } finally {
            setDeleting(false);
        }
    };

    const cancelDelete = () => {
        setDeleteDialogOpen(false);
        setDeletingPayment(null);
    };

    return (
        <Box>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 2 }}>
                <Typography variant="h6">Configure Payment Gateways</Typography>
                <Button
                    variant="contained"
                    startIcon={<AddIcon />}
                    onClick={() => {
                        setEditingPayment(null);
                        setPaymentModalOpen(true);
                    }}
                >
                    Add Method
                </Button>
            </Box>

            <TableContainer component={Paper}>
                <Table>
                    <TableHead>
                        <TableRow>
                            <TableCell>Name</TableCell>
                            <TableCell>Provider</TableCell>
                            <TableCell>Mode</TableCell>
                            <TableCell>Status</TableCell>
                            <TableCell>Actions</TableCell>
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {paymentMethods.map((method) => (
                            <TableRow key={method.id}>
                                <TableCell>{method.name}</TableCell>
                                <TableCell>{method.provider}</TableCell>
                                <TableCell>
                                    <Chip label={method.mode} color={method.mode === 'live' ? 'success' : 'warning'} />
                                </TableCell>
                                <TableCell>
                                    <FormControlLabel
                                        control={
                                            <Switch
                                                checked={method.active}
                                                onChange={() => handleToggleActive(method.id)}
                                                disabled={toggling === method.id}
                                                color="primary"
                                            />
                                        }
                                        label={method.active ? 'Active' : 'Inactive'}
                                    />
                                </TableCell>
                                <TableCell>
                                    <IconButton onClick={() => handleEditPayment(method)}>
                                        <EditIcon />
                                    </IconButton>
                                    <IconButton onClick={() => handleDeletePayment(method)}>
                                        <DeleteIcon />
                                    </IconButton>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </TableContainer>

            <Dialog
                open={deleteDialogOpen}
                onClose={cancelDelete}
                aria-labelledby="delete-dialog-title"
                aria-describedby="delete-dialog-description"
            >
                <DialogTitle id="delete-dialog-title">
                    Delete Payment Method
                </DialogTitle>
                <DialogContent>
                    <DialogContentText id="delete-dialog-description">
                        Are you sure you want to delete the payment method "{deletingPayment?.name}"?
                        This action cannot be undone and may affect existing subscriptions.
                    </DialogContentText>
                </DialogContent>
                <DialogActions>
                    <Button onClick={cancelDelete} disabled={deleting}>
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