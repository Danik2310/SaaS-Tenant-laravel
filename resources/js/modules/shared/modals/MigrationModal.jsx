import React, { useState } from 'react';
import Dialog from '@mui/material/Dialog';
import DialogTitle from '@mui/material/DialogTitle';
import DialogContent from '@mui/material/DialogContent';
import DialogActions from '@mui/material/DialogActions';
import Fade from '@mui/material/Fade';
import Button from '@mui/material/Button';
import Typography from '@mui/material/Typography';
import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';
import IconButton from '@mui/material/IconButton';
import Tooltip from '@mui/material/Tooltip';
import CircularProgress from '@mui/material/CircularProgress';
import CloseIcon from '@mui/icons-material/Close';
import SyncIcon from '@mui/icons-material/Sync';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import ErrorIcon from '@mui/icons-material/Error';
import { dialogSx, surfaceMotionSx } from '@/Components/surfaceMotion';
import { iconButtonMotionSx } from '@/Components/tableMotion';
import { toast } from 'sonner';
import api from '../../../services/api';

export default function MigrationModal({ tenant, onClose }) {
    const [output, setOutput] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(false);

    const runMigrations = async () => {
        if (!tenant) return;

        setLoading(true);
        setError(null);
        setOutput('');
        setSuccess(false);

        try {
            const res = await api.post(`/admin/api/tenants/${tenant.id}/migrate`);
            setOutput(res.data.output || res.data.message || 'Done');
            setSuccess(true);
            toast.success('Migrations completed successfully');
        } catch (err) {
            const message = err.response?.data?.message || 'Failed to run migrations';
            toast.error(message);
            setError(message);
            setOutput(err.response?.data?.output || '');
        } finally {
            setLoading(false);
        }
    };

    return (
        <Dialog
            open={!!tenant}
            onClose={onClose}
            maxWidth="md"
            fullWidth
            className="editorial-dialog"
            TransitionComponent={Fade}
            transitionDuration={180}
            sx={dialogSx}
        >
            <DialogTitle sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', pb: 1, borderBottom: '1px solid', borderColor: 'divider' }}>
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                    <SyncIcon sx={{ color: 'warning.main' }} />
                    <Typography variant="h6" sx={{ fontWeight: 700 }}>Run Migrations</Typography>
                    {tenant && <Chip label={tenant.name} size="small" sx={{ bgcolor: 'action.hover', color: 'text.secondary', fontSize: 12 }} />}
                </Box>
                <Tooltip title="Close"><IconButton onClick={onClose} size="small" sx={iconButtonMotionSx}><CloseIcon /></IconButton></Tooltip>
            </DialogTitle>

            <DialogContent>
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 2, p: 2, bgcolor: 'action.hover', borderRadius: 2, border: '1px solid', borderColor: 'warning.main', ...surfaceMotionSx }}>
                    <SyncIcon sx={{ fontSize: 18, color: 'warning.main' }} />
                    <Typography variant="body2" sx={{ color: 'warning.dark' }}>
                        Execute pending database migrations for this tenant.
                    </Typography>
                </Box>

                {loading && (
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, py: 2 }}>
                        <CircularProgress size={20} />
                        <Typography variant="body2" sx={{ color: 'text.secondary' }}>Running migrations...</Typography>
                    </Box>
                )}

                {success && output && (
                    <Box>
                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 1 }}>
                            <CheckCircleIcon sx={{ fontSize: 18, color: 'success.main' }} />
                            <Typography variant="body2" sx={{ color: 'success.dark', fontWeight: 700 }}>Migrations completed successfully</Typography>
                        </Box>
                        <Box
                            component="pre"
                            sx={{
                                bgcolor: 'ink.main',
                                color: 'common.white',
                                p: 2,
                                borderRadius: 1.5,
                                fontSize: 12,
                                fontFamily: 'monospace',
                                maxHeight: 350,
                                overflow: 'auto',
                                lineHeight: 1.6,
                            }}
                        >
                            {output}
                        </Box>
                    </Box>
                )}

                {error && (
                    <Box>
                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 1 }}>
                            <ErrorIcon sx={{ fontSize: 18, color: 'error.main' }} />
                            <Typography variant="body2" sx={{ color: 'error.dark', fontWeight: 700 }}>{error}</Typography>
                        </Box>
                        {output && (
                            <Box
                                component="pre"
                                sx={{
                                    bgcolor: 'background.default',
                                    color: 'error.dark',
                                    p: 2,
                                    borderRadius: 1.5,
                                    fontSize: 12,
                                    fontFamily: 'monospace',
                                    maxHeight: 350,
                                    overflow: 'auto',
                                    lineHeight: 1.6,
                                    border: '1px solid',
                                    borderColor: 'error.main',
                                }}
                            >
                                {output}
                            </Box>
                        )}
                    </Box>
                )}
            </DialogContent>

            <DialogActions sx={{ p: 2, pt: 0, justifyContent: 'space-between' }}>
                <Box>
                    {output && (
                        <Button
                            size="small"
                            onClick={() => { setOutput(''); setSuccess(false); setError(null); }}
                            sx={{ color: 'text.secondary', transition: 'background-color 150ms ease-out' }}
                        >
                            Clear output
                        </Button>
                    )}
                </Box>
                <Box sx={{ display: 'flex', gap: 1 }}>
                    <Button onClick={onClose} variant="outlined" sx={{ color: 'text.secondary', borderColor: 'divider', transition: 'background-color 150ms ease-out, border-color 150ms ease-out' }}>Close</Button>
                    <Button
                        onClick={runMigrations}
                        variant="contained"
                        disabled={loading}
                        startIcon={loading ? <CircularProgress size={16} sx={{ color: 'common.white' }} /> : <SyncIcon />}
                        sx={{ fontWeight: 700, transition: 'background-color 150ms ease-out' }}
                    >
                        {loading ? 'Running...' : 'Run Migrations'}
                    </Button>
                </Box>
            </DialogActions>
        </Dialog>
    );
}