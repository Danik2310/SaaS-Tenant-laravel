import { useState, useMemo } from 'react';
import { MaterialReactTable } from 'material-react-table';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import Button from '@mui/material/Button';
import Tooltip from '@mui/material/Tooltip';
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
import InboxIcon from '@mui/icons-material/Inbox';
import { toast } from 'sonner';
import api from '../../../services/api';
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

    const columns = useMemo(() => [
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
    ], []);

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

            <MaterialReactTable
                columns={columns}
                data={paymentMethods}
                enableRowActions
                enableGlobalFilter
                enableSorting
                positionGlobalFilter="left"
                renderEmptyRowsFallback={() => (
                    <Box sx={{ textAlign: 'center', py: 6 }}>
                        <InboxIcon sx={{ fontSize: 48, color: 'grey.300', mb: 1 }} />
                        <Typography variant="body1" sx={{ fontWeight: 600, color: 'text.secondary' }}>
                            No payment methods configured yet.
                        </Typography>
                    </Box>
                )}
                renderRowActions={({ row }) => (
                    <Box sx={{ display: 'flex', gap: '0.25rem', alignItems: 'center' }}>
                        <Tooltip title="Edit">
                            <Box component="button" onClick={() => handleEditPayment(row.original)} sx={iconButtonSx}>
                                <EditIcon fontSize="small" />
                            </Box>
                        </Tooltip>
                        <Tooltip title="Delete">
                            <Box component="button" onClick={() => handleDeletePayment(row.original)} sx={{ ...iconButtonSx, color: 'error.main' }}>
                                <DeleteIcon fontSize="small" />
                            </Box>
                        </Tooltip>
                    </Box>
                )}
                muiTablePaperProps={{ elevation: 2, sx: { borderRadius: 2 } }}
                muiTableHeadCellProps={{ sx: { fontWeight: 600, fontSize: '12px', color: 'text.secondary', textTransform: 'uppercase', letterSpacing: '0.05em' } }}
                initialState={{ density: 'compact' }}
                localization={{ toolbarSearchPlaceholder: 'Search payment methods...' }}
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

const iconButtonSx = {
    display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
    border: 'none', bgcolor: 'transparent', cursor: 'pointer',
    p: 0.5, borderRadius: 1, color: 'text.secondary',
    '&:hover': { bgcolor: 'action.hover' },
};
