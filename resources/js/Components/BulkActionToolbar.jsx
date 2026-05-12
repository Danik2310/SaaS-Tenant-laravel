import React from 'react';
import {
    Box,
    Paper,
    Typography,
    Button,
    Chip,
    Dialog,
    DialogTitle,
    DialogContent,
    DialogActions,
    DialogContentText,
} from '@mui/material';
import BlockIcon from '@mui/icons-material/Block';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import DeleteIcon from '@mui/icons-material/Delete';
import RestoreIcon from '@mui/icons-material/Restore';
import ChangeCircleIcon from '@mui/icons-material/ChangeCircle';
import EventIcon from '@mui/icons-material/Event';

const ACTIONS = {
    suspend: { label: 'Suspend', icon: <BlockIcon fontSize="small" />, color: 'error' },
    activate: { label: 'Activate', icon: <CheckCircleIcon fontSize="small" />, color: 'success' },
    delete: { label: 'Delete', icon: <DeleteIcon fontSize="small" />, color: 'error' },
    restore: { label: 'Restore', icon: <RestoreIcon fontSize="small" />, color: 'success' },
    change_plan: { label: 'Change Plan', icon: <ChangeCircleIcon fontSize="small" />, color: 'primary' },
    extend_trial: { label: 'Extend Trial', icon: <EventIcon fontSize="small" />, color: 'info' },
};

export default function BulkActionToolbar({
    selectedIds,
    onClear,
    onAction,
    loading = false,
}) {
    const count = selectedIds.size;
    const [confirmAction, setConfirmAction] = React.useState(null);

    if (count === 0) return null;

    const handleAction = (action) => {
        setConfirmAction(action);
    };

    const handleConfirm = () => {
        onAction(confirmAction);
        setConfirmAction(null);
    };

    const isDestructive = confirmAction === 'delete' || confirmAction === 'suspend';

    return (
        <>
            <Paper
                sx={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 1.5,
                    px: 2,
                    py: 1,
                    mb: 1,
                    bgcolor: '#f0f9ff',
                    border: '1px solid #bae6fd',
                    borderRadius: '8px',
                }}
            >
                <Chip
                    label={`${count} selected`}
                    size="small"
                    onDelete={onClear}
                    sx={{ fontWeight: 600, bgcolor: '#e0f2fe', color: '#0369a1' }}
                />
                <Typography variant="caption" sx={{ color: '#64748b', mr: 1 }}>
                    —
                </Typography>
                {Object.entries(ACTIONS).map(([key, action]) => (
                    <Button
                        key={key}
                        size="small"
                        variant="outlined"
                        color={action.color}
                        startIcon={action.icon}
                        onClick={() => handleAction(key)}
                        disabled={loading}
                        sx={{ fontSize: '12px', minWidth: 0 }}
                    >
                        {action.label}
                    </Button>
                ))}
            </Paper>

            <Dialog
                open={!!confirmAction}
                onClose={() => setConfirmAction(null)}
                maxWidth="xs"
                fullWidth
            >
                <DialogTitle>
                    {confirmAction ? ACTIONS[confirmAction]?.label : ''}
                </DialogTitle>
                <DialogContent>
                    <DialogContentText>
                        {isDestructive ? (
                            <>This will <strong>{confirmAction}</strong> <strong>{count}</strong> tenant(s). This action can be undone later.</>
                        ) : (
                            <>Apply <strong>{confirmAction}</strong> to <strong>{count}</strong> tenant(s)?</>
                        )}
                    </DialogContentText>
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setConfirmAction(null)}>Cancel</Button>
                    <Button
                        onClick={handleConfirm}
                        variant="contained"
                        color={isDestructive ? 'error' : 'primary'}
                        disabled={loading}
                    >
                        {loading ? 'Processing...' : `Yes, ${confirmAction}`}
                    </Button>
                </DialogActions>
            </Dialog>
        </>
    );
}
