import React, { useState } from 'react';
import Dialog from '@mui/material/Dialog';
import DialogTitle from '@mui/material/DialogTitle';
import DialogContent from '@mui/material/DialogContent';
import DialogActions from '@mui/material/DialogActions';
import Fade from '@mui/material/Fade';
import IconButton from '@mui/material/IconButton';
import Avatar from '@mui/material/Avatar';
import Chip from '@mui/material/Chip';
import Button from '@mui/material/Button';
import Typography from '@mui/material/Typography';
import Box from '@mui/material/Box';
import Grid from '@mui/material/Grid';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Tooltip from '@mui/material/Tooltip';
import TextField from '@mui/material/TextField';
import Divider from '@mui/material/Divider';
import CloseIcon from '@mui/icons-material/Close';
import ContentCopyIcon from '@mui/icons-material/ContentCopy';
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutline';
import AddIcon from '@mui/icons-material/Add';
import LaunchIcon from '@mui/icons-material/Launch';
import PersonIcon from '@mui/icons-material/Person';
import StorageIcon from '@mui/icons-material/Storage';
import SyncIcon from '@mui/icons-material/Sync';
import StarIcon from '@mui/icons-material/Star';
import RestoreIcon from '@mui/icons-material/Restore';
import CalendarTodayIcon from '@mui/icons-material/CalendarToday';
import EmailIcon from '@mui/icons-material/Email';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import CancelIcon from '@mui/icons-material/Cancel';
import BusinessIcon from '@mui/icons-material/Business';
import PhoneIcon from '@mui/icons-material/Phone';
import LocationOnIcon from '@mui/icons-material/LocationOn';
import { useAuthContext } from '@/context/AuthContext';
import { alpha, useTheme } from '@mui/material/styles';
import { dialogSx, surfaceMotionSx } from '@/Components/surfaceMotion';
import { iconButtonMotionSx } from '@/Components/tableMotion';
import { toast } from 'sonner';
import api from '@/services/api';

const formatContactName = (tenant) => {
    const parts = [tenant.first_name, tenant.last_name].filter(Boolean);
    return parts.length > 0 ? parts.join(' ') : '—';
};

const formatAddress = (tenant) => {
    const lines = [
        tenant.address_line1,
        tenant.address_line2,
        [tenant.city, tenant.state, tenant.postal_code].filter(Boolean).join(', '),
        tenant.country,
    ].filter(Boolean);

    return lines.length > 0 ? lines.join(', ') : '—';
};

export default function DomainModal({ tenant, onClose, onImpersonate, onViewDatabase, onRunMigrations, onRestore, onTenantUpdated }) {
    if (!tenant) return null;

    const [copiedIndex, setCopiedIndex] = useState(null);
    const [newDomain, setNewDomain] = useState('');
    const [addingDomain, setAddingDomain] = useState(false);
    const [removingDomainId, setRemovingDomainId] = useState(null);
    const theme = useTheme();
    const softPrimary = alpha(theme.palette.primary.main, 0.08);
    const softPrimaryStrong = alpha(theme.palette.primary.main, 0.14);
    const softSuccess = alpha(theme.palette.success.main, 0.08);
    const softSuccessStrong = alpha(theme.palette.success.main, 0.14);
    const softWarning = alpha(theme.palette.warning.main, 0.08);
    const softError = alpha(theme.palette.error.main, 0.08);
    const softErrorStrong = alpha(theme.palette.error.main, 0.14);

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

    const handleAddDomain = async (e) => {
        e.preventDefault();
        const domain = newDomain.trim();
        if (!domain || addingDomain) return;

        setAddingDomain(true);
        try {
            const res = await api.post(`/admin/api/tenants/${tenant.id}/domains`, { domain });
            toast.success('Domain added successfully');
            setNewDomain('');
            onTenantUpdated?.(res.data.tenant);
        } catch (err) {
            const message = err.response?.data?.errors?.domain?.[0]
                || err.response?.data?.message
                || 'Failed to add domain';
            toast.error(message);
        } finally {
            setAddingDomain(false);
        }
    };

    const handleRemoveDomain = async (domain) => {
        if (removingDomainId) return;

        setRemovingDomainId(domain.id);
        try {
            const res = await api.delete(`/admin/api/tenants/${tenant.id}/domains/${domain.id}`);
            toast.success('Domain removed successfully');
            onTenantUpdated?.(res.data.tenant);
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to remove domain');
        } finally {
            setRemovingDomainId(null);
        }
    };

    const isActive = tenant.status === 'Active';
    const isDeleted = tenant.is_deleted || tenant.status === 'Deleted';
    const planName = tenant.plan_name || 'No plan';
    const planSlug = tenant.plan_slug || '';

    const { permissions = [] } = useAuthContext();
    const canImpersonate = permissions.includes('impersonate tenants');
    const canRunMigrations = permissions.includes('edit tenants');
    const canRestore = permissions.includes('restore tenants');
    const canViewDatabase = permissions.includes('view tenants');
    const canEditTenants = permissions.includes('edit tenants');

    const showQuickActions = isDeleted
        ? canRestore
        : canImpersonate || canRunMigrations || canViewDatabase;

    const quickActionSx = (tone, soft) => ({
        borderRadius: 2,
        cursor: 'pointer',
        border: '1px solid',
        borderColor: 'divider',
        transition: 'border-color 180ms ease-out, background-color 180ms ease-out, transform 180ms ease-out',
        '&:hover': { borderColor: tone, bgcolor: soft, transform: 'translateY(-2px)' },
        '@media (prefers-reduced-motion: reduce)': {
            transition: 'border-color 180ms ease-out, background-color 180ms ease-out',
            '&:hover': { borderColor: tone, bgcolor: soft, transform: 'none' },
        },
    });

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
            <DialogTitle sx={{ p: 0 }}>
                <Box
                    sx={{
                        background: `linear-gradient(135deg, ${theme.palette.ink.main} 0%, ${theme.palette.ink.soft} 100%)`,
                        color: 'common.white',
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
                                bgcolor: isDeleted ? 'text.disabled' : isActive ? 'success.main' : 'error.main',
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
                                    label={isDeleted ? 'Deleted' : isActive ? 'Active' : 'Suspended'}
                                    size="small"
                                    sx={{
                                        bgcolor: isDeleted
                                            ? alpha(theme.palette.common.white, 0.16)
                                            : isActive
                                                ? alpha(theme.palette.success.main, 0.2)
                                                : alpha(theme.palette.error.main, 0.2),
                                        color: isDeleted
                                            ? 'common.white'
                                            : isActive
                                                ? 'success.light'
                                                : 'error.light',
                                        fontWeight: 600,
                                        fontSize: 12,
                                        fontStyle: isDeleted ? 'italic' : 'normal',
                                    }}
                                />
                                <Typography variant="caption" sx={{ opacity: 0.7 }}>
                                    ID: {tenant.id}
                                </Typography>
                            </Box>
                        </Box>
                    </Box>
                    <Tooltip title="Close">
                        <IconButton
                            onClick={onClose}
                            sx={{
                                ...iconButtonMotionSx,
                                color: 'common.white',
                                bgcolor: alpha(theme.palette.common.white, 0.15),
                                '&:hover': { bgcolor: alpha(theme.palette.common.white, 0.25), transform: 'none' },
                            }}
                        >
                            <CloseIcon />
                        </IconButton>
                    </Tooltip>
                </Box>
            </DialogTitle>

            <DialogContent sx={{ p: 3 }}>
                <Grid container spacing={3}>
                    <Grid item xs={12} sm={12} md={6} lg={6}>
                        <Card variant="outlined" sx={{ borderRadius: 2, height: '100%', ...surfaceMotionSx }}>
                            <CardContent sx={{ p: 2.5, '&:last-child': { pb: 2.5 } }}>
                                <Typography variant="subtitle2" sx={{ color: 'text.secondary', textTransform: 'uppercase', letterSpacing: 1, fontSize: 11, mb: 2 }}>
                                    Tenant Information
                                </Typography>
                                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1.5 }}>
                                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                        <EmailIcon sx={{ fontSize: 16, color: 'text.disabled' }} />
                                        <Typography variant="body2" sx={{ color: 'text.primary' }}>{tenant.email}</Typography>
                                    </Box>
                                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                        <StarIcon sx={{ fontSize: 16, color: 'text.disabled' }} />
                                        <Typography variant="body2" sx={{ color: 'text.primary' }}>
                                            Plan: <strong>{planName}</strong>
                                            {planSlug && <Chip label={planSlug} size="small" sx={{ ml: 1, height: 20, fontSize: 11, bgcolor: 'divider', color: 'text.secondary' }} />}
                                        </Typography>
                                    </Box>
                                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                        <CalendarTodayIcon sx={{ fontSize: 16, color: 'text.disabled' }} />
                                        <Typography variant="body2" sx={{ color: 'text.primary' }}>
                                            Created: {new Date(tenant.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}
                                        </Typography>
                                    </Box>
                                    {tenant.is_on_trial && (
                                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                            {tenant.trial_has_expired ? (
                                                <CancelIcon sx={{ fontSize: 16, color: 'error.main' }} />
                                            ) : (
                                                <CheckCircleIcon sx={{ fontSize: 16, color: 'success.main' }} />
                                            )}
                                            <Typography variant="body2" sx={{ color: tenant.trial_has_expired ? 'error.main' : 'warning.dark' }}>
                                                Trial {tenant.trial_has_expired ? 'expired' : 'active (temporary full access)'}
                                                {tenant.trial_ends_at && ` — ends ${new Date(tenant.trial_ends_at).toLocaleDateString()}`}
                                            </Typography>
                                        </Box>
                                    )}
                                </Box>
                            </CardContent>
                        </Card>
                    </Grid>

                    <Grid item xs={12} sm={12} md={6} lg={6}>
                        <Card variant="outlined" sx={{ borderRadius: 2, height: '100%', ...surfaceMotionSx }}>
                            <CardContent sx={{ p: 2.5, '&:last-child': { pb: 2.5 } }}>
                                <Typography variant="subtitle2" sx={{ color: 'text.secondary', textTransform: 'uppercase', letterSpacing: 1, fontSize: 11, mb: 2 }}>
                                    Domains
                                </Typography>
                                {(!tenant.all_domains || tenant.all_domains.length === 0) ? (
                                    <Typography variant="body2" sx={{ color: 'text.disabled', fontStyle: 'italic' }}>
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
                                                    bgcolor: 'background.default',
                                                    border: '1px solid',
                                                    borderColor: 'divider',
                                                    borderRadius: 1.5,
                                                    px: 1.5,
                                                    py: 1,
                                                    gap: 1,
                                                }}
                                            >
                                                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, minWidth: 0, flex: 1 }}>
                                                    {d.is_primary && (
                                                        <Tooltip title="Primary domain">
                                                            <StarIcon sx={{ fontSize: 16, color: 'warning.main' }} />
                                                        </Tooltip>
                                                    )}
                                                    <Typography
                                                        component="code"
                                                        sx={{
                                                            fontSize: 13,
                                                            fontFamily: 'monospace',
                                                            color: 'text.primary',
                                                            bgcolor: 'action.hover',
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
                                                            sx={{
                                                                ...iconButtonMotionSx,
                                                                color: copiedIndex === idx ? 'success.main' : 'text.secondary',
                                                                bgcolor: 'action.hover',
                                                                '&:hover': { bgcolor: 'divider', transform: 'translateY(-1px)' },
                                                            }}
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
                                                            sx={{
                                                                ...iconButtonMotionSx,
                                                                color: 'primary.main',
                                                                bgcolor: softPrimary,
                                                                '&:hover': { bgcolor: softPrimaryStrong, transform: 'translateY(-1px)' },
                                                            }}
                                                        >
                                                            <LaunchIcon sx={{ fontSize: 16 }} />
                                                        </IconButton>
                                                    </Tooltip>
                                                    {canEditTenants && !isDeleted && !d.is_primary && (
                                                        <Tooltip title="Remove domain">
                                                            <IconButton
                                                                size="small"
                                                                data-testid={`remove-domain-${d.domain}`}
                                                                disabled={removingDomainId === d.id}
                                                                onClick={() => handleRemoveDomain(d)}
                                                                sx={{
                                                                    ...iconButtonMotionSx,
                                                                    color: 'error.main',
                                                                    bgcolor: softError,
                                                                    '&:hover': { bgcolor: softErrorStrong, transform: 'translateY(-1px)' },
                                                                }}
                                                            >
                                                                <DeleteOutlineIcon sx={{ fontSize: 16 }} />
                                                            </IconButton>
                                                        </Tooltip>
                                                    )}
                                                </Box>
                                            </Box>
                                        ))}
                                    </Box>
                                )}

                                {canEditTenants && !isDeleted && (
                                    <Box
                                        component="form"
                                        onSubmit={handleAddDomain}
                                        sx={{ display: 'flex', gap: 1, mt: 2, alignItems: 'center' }}
                                    >
                                        <TextField
                                            size="small"
                                            fullWidth
                                            placeholder="Add a domain"
                                            value={newDomain}
                                            onChange={(e) => setNewDomain(e.target.value)}
                                            inputProps={{ 'data-testid': 'add-domain-input' }}
                                            sx={{
                                                '& .MuiOutlinedInput-root': {
                                                    borderRadius: 1.5,
                                                    fontSize: 13,
                                                    fontFamily: 'monospace',
                                                },
                                            }}
                                        />
                                        <Tooltip title="Add domain">
                                            <IconButton
                                                type="submit"
                                                data-testid="add-domain-submit"
                                                disabled={addingDomain || !newDomain.trim()}
                                                sx={{
                                                    ...iconButtonMotionSx,
                                                    color: 'success.main',
                                                    bgcolor: softSuccess,
                                                    '&:hover': { bgcolor: softSuccessStrong, transform: 'translateY(-1px)' },
                                                }}
                                            >
                                                <AddIcon />
                                            </IconButton>
                                        </Tooltip>
                                    </Box>
                                )}
                            </CardContent>
                        </Card>
                    </Grid>

                    <Grid item xs={12} sm={12} md={12} lg={12}>
                        <Card variant="outlined" sx={{ borderRadius: 2, height: '100%', ...surfaceMotionSx }}>
                            <CardContent sx={{ p: 2.5, '&:last-child': { pb: 2.5 } }}>
                                <Typography variant="subtitle2" sx={{ color: 'text.secondary', textTransform: 'uppercase', letterSpacing: 1, fontSize: 11, mb: 2 }}>
                                    Personal & Business Information
                                </Typography>
                                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1.5 }}>
                                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                        <BusinessIcon sx={{ fontSize: 16, color: 'text.disabled' }} />
                                        <Typography variant="body2" sx={{ color: 'text.primary' }}>
                                            Company: <strong>{tenant.company_name || '—'}</strong>
                                        </Typography>
                                    </Box>
                                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                        <PersonIcon sx={{ fontSize: 16, color: 'text.disabled' }} />
                                        <Typography variant="body2" sx={{ color: 'text.primary' }}>
                                            Contact: <strong>{formatContactName(tenant)}</strong>
                                        </Typography>
                                    </Box>
                                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                        <PhoneIcon sx={{ fontSize: 16, color: 'text.disabled' }} />
                                        <Typography variant="body2" sx={{ color: 'text.primary' }}>
                                            Phone: <strong>{tenant.phone || '—'}</strong>
                                        </Typography>
                                    </Box>
                                    <Box sx={{ display: 'flex', alignItems: 'flex-start', gap: 1 }}>
                                        <LocationOnIcon sx={{ fontSize: 16, color: 'text.disabled', mt: 0.3 }} />
                                        <Typography variant="body2" sx={{ color: 'text.primary' }}>
                                            Address: <strong>{formatAddress(tenant)}</strong>
                                        </Typography>
                                    </Box>
                                </Box>
                            </CardContent>
                        </Card>
                    </Grid>

                    <Grid item xs={12} sm={12} md={12} lg={12}>
                        <Divider sx={{ my: 0.5 }} />
                        <Typography variant="subtitle2" sx={{ color: 'text.secondary', textTransform: 'uppercase', letterSpacing: 1, fontSize: 11, mb: 2, mt: 1 }}>
                            {isDeleted ? 'Actions' : 'Quick Actions'}
                        </Typography>
                        {showQuickActions ? (
                            <Grid container spacing={2}>
                                {isDeleted ? (
                                    canRestore && (
                                    <Grid item xs={12} sm={4} md={4} lg={4}>
                                        <Card
                                            sx={quickActionSx('success.main', softSuccess)}
                                            onClick={() => onRestore && onRestore(tenant.id)}
                                        >
                                            <CardContent sx={{ textAlign: 'center', py: 2.5, '&:last-child': { pb: 2.5 } }}>
                                                <Avatar sx={{ bgcolor: 'success.main', width: 40, height: 40, mx: 'auto', mb: 1 }}>
                                                    <RestoreIcon />
                                                </Avatar>
                                                <Typography variant="body2" sx={{ fontWeight: 600, color: 'text.primary' }}>
                                                    Restore Tenant
                                                </Typography>
                                                <Typography variant="caption" sx={{ color: 'text.disabled' }}>
                                                    Reactivate this tenant
                                                </Typography>
                                            </CardContent>
                                        </Card>
                                    </Grid>
                                    )
                                ) : (
                                    <>
                                        {canImpersonate && (
                                        <Grid item xs={12} sm={4} md={4} lg={4}>
                                            <Card
                                                sx={quickActionSx('primary.main', softPrimary)}
                                                onClick={() => onImpersonate(tenant)}
                                            >
                                                <CardContent sx={{ textAlign: 'center', py: 2.5, '&:last-child': { pb: 2.5 } }}>
                                                    <Avatar sx={{ bgcolor: 'primary.main', width: 40, height: 40, mx: 'auto', mb: 1 }}>
                                                        <PersonIcon />
                                                    </Avatar>
                                                    <Typography variant="body2" sx={{ fontWeight: 600, color: 'text.primary' }}>
                                                        Impersonate
                                                    </Typography>
                                                    <Typography variant="caption" sx={{ color: 'text.disabled' }}>
                                                        Log in as tenant admin
                                                    </Typography>
                                                </CardContent>
                                            </Card>
                                        </Grid>
                                        )}
                                        {canViewDatabase && (
                                        <Grid item xs={12} sm={4} md={4} lg={4}>
                                            <Card
                                                sx={quickActionSx('success.main', softSuccess)}
                                                onClick={() => onViewDatabase(tenant)}
                                            >
                                                <CardContent sx={{ textAlign: 'center', py: 2.5, '&:last-child': { pb: 2.5 } }}>
                                                    <Avatar sx={{ bgcolor: 'success.main', width: 40, height: 40, mx: 'auto', mb: 1 }}>
                                                        <StorageIcon />
                                                    </Avatar>
                                                    <Typography variant="body2" sx={{ fontWeight: 600, color: 'text.primary' }}>
                                                        Database Info
                                                    </Typography>
                                                    <Typography variant="caption" sx={{ color: 'text.disabled' }}>
                                                        View connection details
                                                    </Typography>
                                                </CardContent>
                                            </Card>
                                        </Grid>
                                        )}
                                        {canRunMigrations && (
                                        <Grid item xs={12} sm={4} md={4} lg={4}>
                                            <Card
                                                sx={quickActionSx('warning.main', softWarning)}
                                                onClick={() => onRunMigrations(tenant)}
                                            >
                                                <CardContent sx={{ textAlign: 'center', py: 2.5, '&:last-child': { pb: 2.5 } }}>
                                                    <Avatar sx={{ bgcolor: 'warning.main', width: 40, height: 40, mx: 'auto', mb: 1 }}>
                                                        <SyncIcon />
                                                    </Avatar>
                                                    <Typography variant="body2" sx={{ fontWeight: 600, color: 'text.primary' }}>
                                                        Run Migrations
                                                    </Typography>
                                                    <Typography variant="caption" sx={{ color: 'text.disabled' }}>
                                                        Execute pending migrations
                                                    </Typography>
                                                </CardContent>
                                            </Card>
                                        </Grid>
                                        )}
                                    </>
                                )}
                            </Grid>
                        ) : (
                            <Typography variant="body2" sx={{ color: 'text.disabled' }}>
                                You don't have permission to perform actions on this tenant.
                            </Typography>
                        )}
                    </Grid>
                </Grid>
            </DialogContent>

            <DialogActions sx={{ p: 2.5, pt: 0, justifyContent: 'flex-end' }}>
                <Button onClick={onClose} variant="outlined" sx={{ color: 'text.secondary', borderColor: 'divider', transition: 'background-color 150ms ease-out, border-color 150ms ease-out', '&:hover': { borderColor: 'text.disabled', bgcolor: 'background.default' } }}>
                    Close
                </Button>
            </DialogActions>
        </Dialog>
    );
}
