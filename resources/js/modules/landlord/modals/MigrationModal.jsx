import React, { useState } from 'react';
import {
    Dialog,
    DialogTitle,
    DialogContent,
    DialogActions,
    Button,
    Typography,
    Box,
    Chip,
    IconButton,
    CircularProgress,
} from '@mui/material';
import CloseIcon from '@mui/icons-material/Close';
import SyncIcon from '@mui/icons-material/Sync';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import ErrorIcon from '@mui/icons-material/Error';
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
        <Dialog open={!!tenant} onClose={onClose} maxWidth="md" fullWidth sx={{ '& .MuiDialog-paper': { borderRadius: 2 } }}>
            <DialogTitle sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', pb: 1 }}>
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                    <SyncIcon sx={{ color: '#f59e0b' }} />
                    <Typography variant="h6" sx={{ fontWeight: 600 }}>Run Migrations</Typography>
                    {tenant && <Chip label={tenant.name} size="small" sx={{ bgcolor: '#f1f5f9', color: '#475569', fontSize: 12 }} />}
                </Box>
                <IconButton onClick={onClose} size="small"><CloseIcon /></IconButton>
            </DialogTitle>

            <DialogContent>
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 2, p: 2, bgcolor: '#fffbeb', borderRadius: 1, border: '1px solid #fde68a' }}>
                    <SyncIcon sx={{ fontSize: 18, color: '#d97706' }} />
                    <Typography variant="body2" sx={{ color: '#92400e' }}>
                        Execute pending database migrations for this tenant.
                    </Typography>
                </Box>

                {loading && (
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, py: 2 }}>
                        <CircularProgress size={20} />
                        <Typography variant="body2" sx={{ color: '#64748b' }}>Running migrations...</Typography>
                    </Box>
                )}

                {success && output && (
                    <Box>
                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 1 }}>
                            <CheckCircleIcon sx={{ fontSize: 18, color: '#22c55e' }} />
                            <Typography variant="body2" sx={{ color: '#166534', fontWeight: 600 }}>Migrations completed successfully</Typography>
                        </Box>
                        <Box
                            component="pre"
                            sx={{
                                bgcolor: '#0f172a',
                                color: '#e2e8f0',
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
                            <ErrorIcon sx={{ fontSize: 18, color: '#ef4444' }} />
                            <Typography variant="body2" sx={{ color: '#991b1b', fontWeight: 600 }}>{error}</Typography>
                        </Box>
                        {output && (
                            <Box
                                component="pre"
                                sx={{
                                    bgcolor: '#fef2f2',
                                    color: '#991b1b',
                                    p: 2,
                                    borderRadius: 1.5,
                                    fontSize: 12,
                                    fontFamily: 'monospace',
                                    maxHeight: 350,
                                    overflow: 'auto',
                                    lineHeight: 1.6,
                                    border: '1px solid #fecaca',
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
                            sx={{ color: '#64748b' }}
                        >
                            Clear output
                        </Button>
                    )}
                </Box>
                <Box sx={{ display: 'flex', gap: 1 }}>
                    <Button onClick={onClose} variant="outlined" sx={{ color: '#64748b', borderColor: '#cbd5e1' }}>Close</Button>
                    <Button
                        onClick={runMigrations}
                        variant="contained"
                        disabled={loading}
                        startIcon={loading ? <CircularProgress size={16} sx={{ color: 'white' }} /> : <SyncIcon />}
                    >
                        {loading ? 'Running...' : 'Run Migrations'}
                    </Button>
                </Box>
            </DialogActions>
        </Dialog>
    );
}