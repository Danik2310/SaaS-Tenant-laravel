import React, { useEffect, useState } from 'react';
import Dialog from '@mui/material/Dialog';
import DialogTitle from '@mui/material/DialogTitle';
import DialogContent from '@mui/material/DialogContent';
import DialogActions from '@mui/material/DialogActions';
import Button from '@mui/material/Button';
import Typography from '@mui/material/Typography';
import Box from '@mui/material/Box';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Chip from '@mui/material/Chip';
import IconButton from '@mui/material/IconButton';
import Tooltip from '@mui/material/Tooltip';
import CircularProgress from '@mui/material/CircularProgress';
import CloseIcon from '@mui/icons-material/Close';
import StorageIcon from '@mui/icons-material/Storage';
import DnsIcon from '@mui/icons-material/Dns';
import RouterIcon from '@mui/icons-material/Router';
import NumbersIcon from '@mui/icons-material/Numbers';
import ContentCopyIcon from '@mui/icons-material/ContentCopy';
import { toast } from 'sonner';

import api from '../../../services/api';

function InfoRow({ icon, label, value, copyable }) {
    const [copied, setCopied] = useState(false);
    const handleCopy = async () => {
        try {
            await navigator.clipboard.writeText(value);
            setCopied(true);
            toast.success(`${label} copied`);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            toast.error('Failed to copy');
        }
    };

    return (
        <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', py: 1, borderBottom: '1px solid #f1f5f9' }}>
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                {icon}
                <Box>
                    <Typography variant="caption" sx={{ color: '#94a3b8', fontSize: 11 }}>{label}</Typography>
                    <Typography variant="body2" sx={{ color: '#0f172a', fontFamily: value?.includes('.') || value?.includes(':') ? 'monospace' : 'inherit', fontWeight: 500 }}>
                        {value}
                    </Typography>
                </Box>
            </Box>
            {copyable && value && (
                <Tooltip title={copied ? 'Copied!' : 'Copy'}>
                    <IconButton size="small" onClick={handleCopy} sx={{ color: copied ? '#22c55e' : '#94a3b8' }}>
                        <ContentCopyIcon sx={{ fontSize: 16 }} />
                    </IconButton>
                </Tooltip>
            )}
        </Box>
    );
}

export default function DatabaseModal({ tenant, onClose }) {
    const [dbInfo, setDbInfo] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    useEffect(() => {
        if (!tenant) return;

        const fetchDb = async () => {
            setLoading(true);
            setError(null);

            try {
                const res = await api.get(`/admin/api/tenants/${tenant.id}/database`);
                setDbInfo(res.data.database);
            } catch (err) {
                const message = 'Failed to fetch database info';
                toast.error(message);
                setError(message);
            } finally {
                setLoading(false);
            }
        };

        fetchDb();
    }, [tenant]);

    return (
        <Dialog open={!!tenant} onClose={onClose} maxWidth="sm" fullWidth sx={{ '& .MuiDialog-paper': { borderRadius: 2 } }}>
            <DialogTitle sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', pb: 1 }}>
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                    <StorageIcon sx={{ color: '#22c55e' }} />
                    <Typography variant="h6" sx={{ fontWeight: 600 }}>Database Info</Typography>
                    {tenant && <Chip label={tenant.name} size="small" sx={{ bgcolor: '#f1f5f9', color: '#475569', fontSize: 12 }} />}
                </Box>
                <IconButton onClick={onClose} size="small"><CloseIcon /></IconButton>
            </DialogTitle>

            <DialogContent>
                {loading && (
                    <Box sx={{ display: 'flex', justifyContent: 'center', py: 4 }}><CircularProgress size={28} /></Box>
                )}
                {error && (
                    <Box sx={{ bgcolor: '#fef2f2', color: '#dc2626', p: 2, borderRadius: 1, fontSize: 13 }}>{error}</Box>
                )}
                {!loading && !error && dbInfo && (
                    <Card variant="outlined" sx={{ borderRadius: 2, mt: 1 }}>
                        <CardContent sx={{ p: 2, '&:last-child': { pb: 2 } }}>
                            <InfoRow icon={<StorageIcon sx={{ fontSize: 18, color: '#3b82f6' }} />} label="Database Name" value={dbInfo.name} copyable />
                            <InfoRow icon={<DnsIcon sx={{ fontSize: 18, color: '#8b5cf6' }} />} label="Connection" value={dbInfo.connection} />
                            <InfoRow icon={<RouterIcon sx={{ fontSize: 18, color: '#f59e0b' }} />} label="Host" value={dbInfo.host} copyable />
                            <InfoRow icon={<NumbersIcon sx={{ fontSize: 18, color: '#ef4444' }} />} label="Port" value={dbInfo.port} copyable />
                        </CardContent>
                    </Card>
                )}
                {!loading && !error && !dbInfo && (
                    <Typography sx={{ color: '#94a3b8', py: 2, textAlign: 'center' }}>No database information available.</Typography>
                )}
            </DialogContent>

            <DialogActions sx={{ p: 2, pt: 0 }}>
                <Button onClick={onClose} variant="outlined" sx={{ color: '#64748b', borderColor: '#cbd5e1' }}>Close</Button>
            </DialogActions>
        </Dialog>
    );
}