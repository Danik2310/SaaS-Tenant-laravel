import React, { useState } from 'react';
import {
    Dialog,
    DialogTitle,
    DialogContent,
    DialogActions,
    IconButton,
    Avatar,
    Chip,
    Button,
    Typography,
    Box,
    Grid,
    Card,
    CardContent,
    Tooltip,
    Divider,
} from '@mui/material';
import CloseIcon from '@mui/icons-material/Close';
import ContentCopyIcon from '@mui/icons-material/ContentCopy';
import LaunchIcon from '@mui/icons-material/Launch';
import PersonIcon from '@mui/icons-material/Person';
import StorageIcon from '@mui/icons-material/Storage';
import SyncIcon from '@mui/icons-material/Sync';
import StarIcon from '@mui/icons-material/Star';
import CalendarTodayIcon from '@mui/icons-material/CalendarToday';
import EmailIcon from '@mui/icons-material/Email';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import CancelIcon from '@mui/icons-material/Cancel';
import { toast } from 'sonner';

export default function DomainModal({ tenant, onClose, onImpersonate, onViewDatabase, onRunMigrations }) {
    if (!tenant) return null;

    const [copiedIndex, setCopiedIndex] = useState(null);

    const handleCopy = async (domain, index) => {
        try {
            await navigator.clipboard.writeText(domain);
            setCopiedIndex(index);
            toast.success('Domain copied to clipboard');
            setTimeout(() => setCopiedIndex(null), 2000);
        } catch {
            toast.error('Failed to copy domain');
        }
    };

    const isActive = tenant.status === 'Active';
    const planName = tenant.plan_name || 'No plan';
    const planSlug = tenant.plan_slug || '';

    return (
        <Dialog
            open={!!tenant}
            onClose={onClose}
            maxWidth="md"
            fullWidth
            sx={{ '& .MuiDialog-paper': { borderRadius: 3 } }}
        >
            <DialogTitle sx={{ p: 0 }}>
                <Box
                    sx={{
                        background: 'linear-gradient(135deg, #1e293b 0%, #334155 100%)',
                        color: 'white',
                        px: 3,
                        py: 3,
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'flex-start',
                    }}
                >
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                        <Avatar
                            sx={{
                                width: 56,
                                height: 56,
                                bgcolor: isActive ? '#22c55e' : '#ef4444',
                                fontSize: 24,
                                fontWeight: 700,
                            }}
                        >
                            {tenant.name?.charAt(0)?.toUpperCase() || 'T'}
                        </Avatar>
                        <Box>
                            <Typography variant="h6" sx={{ fontWeight: 700, mb: 0.5 }}>
                                {tenant.name}
                            </Typography>
                            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, flexWrap: 'wrap' }}>
                                <Chip
                                    label={isActive ? 'Active' : 'Suspended'}
                                    size="small"
                                    sx={{
                                        bgcolor: isActive ? 'rgba(34,197,94,0.2)' : 'rgba(239,68,68,0.2)',
                                        color: isActive ? '#86efac' : '#fca5a5',
                                        fontWeight: 600,
                                        fontSize: 12,
                                    }}
                                />
                                <Typography variant="caption" sx={{ opacity: 0.7 }}>
                                    ID: {tenant.id}
                                </Typography>
                            </Box>
                        </Box>
                    </Box>
                    <IconButton onClick={onClose} sx={{ color: 'white', bgcolor: 'rgba(255,255,255,0.15)', '&:hover': { bgcolor: 'rgba(255,255,255,0.25)' } }}>
                        <CloseIcon />
                    </IconButton>
                </Box>
            </DialogTitle>

            <DialogContent sx={{ p: 3 }}>
                <Grid container spacing={3}>
                    <Grid item xs={12} md={6}>
                        <Card variant="outlined" sx={{ borderRadius: 2, height: '100%' }}>
                            <CardContent sx={{ p: 2.5, '&:last-child': { pb: 2.5 } }}>
                                <Typography variant="subtitle2" sx={{ color: '#64748b', textTransform: 'uppercase', letterSpacing: 1, fontSize: 11, mb: 2 }}>
                                    Tenant Information
                                </Typography>
                                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1.5 }}>
                                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                        <EmailIcon sx={{ fontSize: 16, color: '#94a3b8' }} />
                                        <Typography variant="body2" sx={{ color: '#334155' }}>{tenant.email}</Typography>
                                    </Box>
                                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                        <StarIcon sx={{ fontSize: 16, color: '#94a3b8' }} />
                                        <Typography variant="body2" sx={{ color: '#334155' }}>
                                            Plan: <strong>{planName}</strong>
                                            {planSlug && <Chip label={planSlug} size="small" sx={{ ml: 1, height: 20, fontSize: 11, bgcolor: '#e2e8f0', color: '#475569' }} />}
                                        </Typography>
                                    </Box>
                                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                        <CalendarTodayIcon sx={{ fontSize: 16, color: '#94a3b8' }} />
                                        <Typography variant="body2" sx={{ color: '#334155' }}>
                                            Created: {new Date(tenant.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}
                                        </Typography>
                                    </Box>
                                    {tenant.is_on_trial && (
                                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                            {tenant.trial_has_expired ? (
                                                <CancelIcon sx={{ fontSize: 16, color: '#ef4444' }} />
                                            ) : (
                                                <CheckCircleIcon sx={{ fontSize: 16, color: '#22c55e' }} />
                                            )}
                                            <Typography variant="body2" sx={{ color: tenant.trial_has_expired ? '#ef4444' : '#22c55e' }}>
                                                Trial {tenant.trial_has_expired ? 'expired' : 'active'}
                                                {tenant.trial_ends_at && ` — ends ${new Date(tenant.trial_ends_at).toLocaleDateString()}`}
                                            </Typography>
                                        </Box>
                                    )}
                                </Box>
                            </CardContent>
                        </Card>
                    </Grid>

                    <Grid item xs={12} md={6}>
                        <Card variant="outlined" sx={{ borderRadius: 2, height: '100%' }}>
                            <CardContent sx={{ p: 2.5, '&:last-child': { pb: 2.5 } }}>
                                <Typography variant="subtitle2" sx={{ color: '#64748b', textTransform: 'uppercase', letterSpacing: 1, fontSize: 11, mb: 2 }}>
                                    Domains
                                </Typography>
                                {(!tenant.all_domains || tenant.all_domains.length === 0) ? (
                                    <Typography variant="body2" sx={{ color: '#94a3b8', fontStyle: 'italic' }}>
                                        No domains configured
                                    </Typography>
                                ) : (
                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        {tenant.all_domains.map((d, idx) => (
                                            <Box
                                                key={idx}
                                                sx={{
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    justifyContent: 'space-between',
                                                    bgcolor: '#f8fafc',
                                                    border: '1px solid #e2e8f0',
                                                    borderRadius: 1.5,
                                                    px: 1.5,
                                                    py: 1,
                                                    gap: 1,
                                                }}
                                            >
                                                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, minWidth: 0, flex: 1 }}>
                                                    {d.is_primary && (
                                                        <Tooltip title="Primary domain">
                                                            <StarIcon sx={{ fontSize: 16, color: '#f59e0b' }} />
                                                        </Tooltip>
                                                    )}
                                                    <Typography
                                                        component="code"
                                                        sx={{
                                                            fontSize: 13,
                                                            fontFamily: 'monospace',
                                                            color: '#0f172a',
                                                            bgcolor: '#f1f5f9',
                                                            px: 1,
                                                            py: 0.3,
                                                            borderRadius: 0.5,
                                                            overflow: 'hidden',
                                                            textOverflow: 'ellipsis',
                                                            whiteSpace: 'nowrap',
                                                        }}
                                                    >
                                                        {d.domain}
                                                    </Typography>
                                                </Box>
                                                <Box sx={{ display: 'flex', gap: 0.5, flexShrink: 0 }}>
                                                    <Tooltip title={copiedIndex === idx ? 'Copied!' : 'Copy domain'}>
                                                        <IconButton
                                                            size="small"
                                                            onClick={() => handleCopy(d.domain, idx)}
                                                            sx={{ color: copiedIndex === idx ? '#22c55e' : '#64748b', bgcolor: '#f1f5f9', '&:hover': { bgcolor: '#e2e8f0' } }}
                                                        >
                                                            <ContentCopyIcon sx={{ fontSize: 16 }} />
                                                        </IconButton>
                                                    </Tooltip>
                                                    <Tooltip title="Open in new tab">
                                                        <IconButton
                                                            size="small"
                                                            href={`http://${d.domain}`}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            sx={{ color: '#3b82f6', bgcolor: '#eff6ff', '&:hover': { bgcolor: '#dbeafe' } }}
                                                        >
                                                            <LaunchIcon sx={{ fontSize: 16 }} />
                                                        </IconButton>
                                                    </Tooltip>
                                                </Box>
                                            </Box>
                                        ))}
                                    </Box>
                                )}
                            </CardContent>
                        </Card>
                    </Grid>

                    <Grid item xs={12}>
                        <Divider sx={{ my: 0.5 }} />
                        <Typography variant="subtitle2" sx={{ color: '#64748b', textTransform: 'uppercase', letterSpacing: 1, fontSize: 11, mb: 2, mt: 1 }}>
                            Quick Actions
                        </Typography>
                        <Grid container spacing={2}>
                            <Grid item xs={12} sm={4}>
                                <Card
                                    sx={{
                                        borderRadius: 2,
                                        cursor: 'pointer',
                                        transition: 'all 0.2s',
                                        border: '1px solid #e2e8f0',
                                        '&:hover': { borderColor: '#a78bfa', bgcolor: '#f5f3ff', transform: 'translateY(-2px)' },
                                    }}
                                    onClick={() => onImpersonate(tenant)}
                                >
                                    <CardContent sx={{ textAlign: 'center', py: 2.5, '&:last-child': { pb: 2.5 } }}>
                                        <Avatar sx={{ bgcolor: '#8b5cf6', width: 40, height: 40, mx: 'auto', mb: 1 }}>
                                            <PersonIcon />
                                        </Avatar>
                                        <Typography variant="body2" sx={{ fontWeight: 600, color: '#1e293b' }}>
                                            Impersonate
                                        </Typography>
                                        <Typography variant="caption" sx={{ color: '#94a3b8' }}>
                                            Log in as tenant admin
                                        </Typography>
                                    </CardContent>
                                </Card>
                            </Grid>
                            <Grid item xs={12} sm={4}>
                                <Card
                                    sx={{
                                        borderRadius: 2,
                                        cursor: 'pointer',
                                        transition: 'all 0.2s',
                                        border: '1px solid #e2e8f0',
                                        '&:hover': { borderColor: '#22c55e', bgcolor: '#f0fdf4', transform: 'translateY(-2px)' },
                                    }}
                                    onClick={() => onViewDatabase(tenant)}
                                >
                                    <CardContent sx={{ textAlign: 'center', py: 2.5, '&:last-child': { pb: 2.5 } }}>
                                        <Avatar sx={{ bgcolor: '#22c55e', width: 40, height: 40, mx: 'auto', mb: 1 }}>
                                            <StorageIcon />
                                        </Avatar>
                                        <Typography variant="body2" sx={{ fontWeight: 600, color: '#1e293b' }}>
                                            Database Info
                                        </Typography>
                                        <Typography variant="caption" sx={{ color: '#94a3b8' }}>
                                            View connection details
                                        </Typography>
                                    </CardContent>
                                </Card>
                            </Grid>
                            <Grid item xs={12} sm={4}>
                                <Card
                                    sx={{
                                        borderRadius: 2,
                                        cursor: 'pointer',
                                        transition: 'all 0.2s',
                                        border: '1px solid #e2e8f0',
                                        '&:hover': { borderColor: '#f59e0b', bgcolor: '#fffbeb', transform: 'translateY(-2px)' },
                                    }}
                                    onClick={() => onRunMigrations(tenant)}
                                >
                                    <CardContent sx={{ textAlign: 'center', py: 2.5, '&:last-child': { pb: 2.5 } }}>
                                        <Avatar sx={{ bgcolor: '#f59e0b', width: 40, height: 40, mx: 'auto', mb: 1 }}>
                                            <SyncIcon />
                                        </Avatar>
                                        <Typography variant="body2" sx={{ fontWeight: 600, color: '#1e293b' }}>
                                            Run Migrations
                                        </Typography>
                                        <Typography variant="caption" sx={{ color: '#94a3b8' }}>
                                            Execute pending migrations
                                        </Typography>
                                    </CardContent>
                                </Card>
                            </Grid>
                        </Grid>
                    </Grid>
                </Grid>
            </DialogContent>

            <DialogActions sx={{ p: 2.5, pt: 0, justifyContent: 'flex-end' }}>
                <Button onClick={onClose} variant="outlined" sx={{ color: '#64748b', borderColor: '#cbd5e1', '&:hover': { borderColor: '#94a3b8', bgcolor: '#f8fafc' } }}>
                    Close
                </Button>
            </DialogActions>
        </Dialog>
    );
}
