import React, { useEffect, useState } from 'react';
import Dialog from '@mui/material/Dialog';
import DialogTitle from '@mui/material/DialogTitle';
import DialogContent from '@mui/material/DialogContent';
import DialogActions from '@mui/material/DialogActions';
import Fade from '@mui/material/Fade';
import Button from '@mui/material/Button';
import Typography from '@mui/material/Typography';
import Box from '@mui/material/Box';
import Paper from '@mui/material/Paper';
import Chip from '@mui/material/Chip';
import IconButton from '@mui/material/IconButton';
import Tooltip from '@mui/material/Tooltip';
import CircularProgress from '@mui/material/CircularProgress';
import Alert from '@mui/material/Alert';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import CloseIcon from '@mui/icons-material/Close';
import WarningIcon from '@mui/icons-material/Warning';
import ChangeCircleIcon from '@mui/icons-material/ChangeCircle';
import { alpha, useTheme } from '@mui/material/styles';
import { dialogSx, surfaceMotionSx } from '@/Components/surfaceMotion';
import { iconButtonMotionSx } from '@/Components/tableMotion';
import { toast } from 'sonner';

import api from '../../../services/api';
import { useMoney } from '@/shared/money';

const LIMIT_ROWS = [
    { key: 'max_users', label: 'Users' },
    { key: 'max_warehouses', label: 'Warehouses' },
    { key: 'max_categories', label: 'Categories' },
    { key: 'max_products', label: 'Products' },
];

function formatLimit(value) {
    if (value === -1 || value === null || value === undefined) return 'Unlimited';
    return String(value);
}

export default function ChangePlanModal({ open, tenants, onClose, onChanged }) {
    const theme = useTheme();
    const { formatMoney } = useMoney();
    const [plans, setPlans] = useState([]);
    const [selectedPlanId, setSelectedPlanId] = useState(null);
    const [step, setStep] = useState('select');
    const [loading, setLoading] = useState(false);
    const [submitting, setSubmitting] = useState(false);

    const isBulk = tenants.length > 1;
    const singleTenant = !isBulk && tenants[0];
    const currentPlanId = singleTenant?.plan?.id;

    useEffect(() => {
        if (!open) {
            setStep('select');
            setSelectedPlanId(null);
            return;
        }
        fetchPlans();
    }, [open]);

    const fetchPlans = async () => {
        setLoading(true);
        try {
            const res = await api.get('/admin/api/plans-list');
            setPlans(res.data.plans || []);
        } catch {
            toast.error('Failed to load plans');
        } finally {
            setLoading(false);
        }
    };

    const handleSelectPlan = (planId) => {
        setSelectedPlanId(planId);
    };

    const handleContinue = () => {
        if (!selectedPlanId) return;
        setStep('confirm');
    };

    const handleBack = () => {
        setStep('select');
    };

    const handleConfirm = async () => {
        if (!selectedPlanId) return;
        setSubmitting(true);
        try {
            if (isBulk) {
                await api.post('/admin/api/tenants/bulk', {
                    tenant_ids: tenants.map(t => t.id),
                    action: 'change_plan',
                    payload: { plan_id: selectedPlanId },
                });
                toast.success(`Plan changed for ${tenants.length} tenant(s)`);
            } else {
                await api.put(`/admin/api/tenants/${singleTenant.id}/plan`, { plan_id: selectedPlanId });
                toast.success('Plan changed successfully');
            }
            onChanged?.();
            onClose();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to change plan');
        } finally {
            setSubmitting(false);
        }
    };

    const selectedPlan = plans.find(p => p.id === selectedPlanId);

    return (
        <Dialog
            open={open}
            onClose={submitting ? undefined : onClose}
            maxWidth="sm"
            fullWidth
            className="editorial-dialog"
            TransitionComponent={Fade}
            transitionDuration={180}
            sx={dialogSx}
        >
            <DialogTitle sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', pb: 1, borderBottom: '1px solid', borderColor: 'divider' }}>
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                    {step === 'confirm' ? (
                        <WarningIcon sx={{ color: 'warning.main' }} />
                    ) : (
                        <ChangeCircleIcon sx={{ color: 'primary.main' }} />
                    )}
                    <Typography variant="h6" sx={{ fontWeight: 600 }}>
                        {step === 'confirm' ? 'Confirm Plan Change' : 'Change Plan'}
                    </Typography>
                </Box>
                <Tooltip title="Close">
                    <IconButton onClick={onClose} size="small" disabled={submitting} sx={iconButtonMotionSx}>
                        <CloseIcon />
                    </IconButton>
                </Tooltip>
            </DialogTitle>

            <DialogContent>
                {singleTenant && (
                    <Typography variant="body2" sx={{ mb: 2, color: 'text.secondary' }}>
                        Changing plan for <strong>{singleTenant.name}</strong>
                        {singleTenant.plan_name && (
                            <> — currently on <Chip label={singleTenant.plan_name} size="small" sx={{ bgcolor: 'action.hover', color: 'primary.dark', fontSize: 11, height: 20 }} /></>
                        )}
                    </Typography>
                )}
                {isBulk && (
                    <Typography variant="body2" sx={{ mb: 2, color: 'text.secondary' }}>
                        Changing plan for <strong>{tenants.length} tenant(s)</strong>
                    </Typography>
                )}

                {loading && (
                    <Box sx={{ display: 'flex', justifyContent: 'center', py: 4 }}>
                        <CircularProgress size={28} />
                    </Box>
                )}

                {!loading && plans.length === 0 && (
                    <Box sx={{ bgcolor: 'action.hover', color: 'warning.dark', border: '1px solid', borderColor: 'warning.main', p: 2, borderRadius: 2, fontSize: 13 }}>
                        No plans available. Please create a plan first.
                    </Box>
                )}

                {!loading && plans.length > 0 && step === 'select' && (
                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1.5, ...surfaceMotionSx }}>
                        {plans.map((plan) => {
                            const isCurrent = plan.id === currentPlanId;
                            const isSelected = plan.id === selectedPlanId;
                            return (
                                <Paper
                                    key={plan.id}
                                    variant="outlined"
                                    onClick={() => !isCurrent && handleSelectPlan(plan.id)}
                                    sx={{
                                        p: 2,
                                        borderRadius: 2,
                                        cursor: isCurrent ? 'default' : 'pointer',
                                        border: '1px solid',
                                        borderWidth: isSelected || isCurrent ? 2 : 1,
                                        borderColor: isSelected ? 'primary.main' : 'divider',
                                        bgcolor: isSelected
                                            ? alpha(theme.palette.primary.main, 0.08)
                                            : isCurrent
                                                ? 'background.default'
                                                : 'background.paper',
                                        opacity: isCurrent ? 0.7 : 1,
                                        transition: 'border-color 180ms ease-out, background-color 180ms ease-out',
                                    }}
                                >
                                    <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 1 }}>
                                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                            <Typography variant="subtitle2" sx={{ fontWeight: 700, color: 'text.primary' }}>
                                                {plan.name}
                                            </Typography>
                                            {isCurrent && (
                                                <Chip label="Current" size="small" sx={{ bgcolor: 'action.hover', color: 'primary.dark', fontSize: 10, height: 20 }} />
                                            )}
                                            {isSelected && !isCurrent && (
                                                <CheckCircleIcon sx={{ color: 'success.main', fontSize: 18 }} />
                                            )}
                                        </Box>
                                        <Typography variant="body2" sx={{ fontWeight: 600, color: 'text.primary' }}>
                                            {plan.price > 0 ? `${formatMoney(plan.price)}/mo` : 'Free'}
                                        </Typography>
                                    </Box>

                                    <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 0.5 }}>
                                        {LIMIT_ROWS.map(row => (
                                            <Chip
                                                key={row.key}
                                                label={`${formatLimit(plan[row.key])} ${row.label}`}
                                                size="small"
                                                variant="outlined"
                                                sx={{ fontSize: 11, borderColor: 'divider', color: 'text.secondary' }}
                                            />
                                        ))}
                                    </Box>
                                </Paper>
                            );
                        })}
                    </Box>
                )}

                {!loading && plans.length > 0 && step === 'confirm' && selectedPlan && (
                    <Box>
                        {singleTenant?.plan && LIMIT_ROWS.some(r => singleTenant.plan[r.key] !== selectedPlan[r.key]) && (
                            <Box sx={{ mb: 2 }}>
                                <Typography variant="subtitle2" sx={{ fontWeight: 600, color: 'text.primary', mb: 1 }}>
                                    Plan Comparison
                                </Typography>
                                <Box sx={{ border: '1px solid', borderColor: 'divider', borderRadius: 2, overflow: 'hidden' }}>
                                    {LIMIT_ROWS.map(row => {
                                        const oldVal = singleTenant.plan[row.key];
                                        const newVal = selectedPlan[row.key];
                                        const changed = oldVal !== newVal;
                                        return (
                                            <Box key={row.key} sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', px: 2, py: 1, borderBottom: '1px solid', borderColor: 'divider', bgcolor: changed ? alpha(theme.palette.warning.main, 0.08) : 'transparent' }}>
                                                <Typography variant="body2" sx={{ color: 'text.secondary', fontSize: 13 }}>{row.label}</Typography>
                                                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                                    <Typography variant="body2" sx={{ color: 'text.disabled', fontSize: 13, textDecoration: changed ? 'line-through' : 'none' }}>
                                                        {formatLimit(oldVal)}
                                                    </Typography>
                                                    {changed && (
                                                        <>
                                                            <Typography variant="body2" sx={{ color: 'text.disabled' }}>→</Typography>
                                                            <Typography variant="body2" sx={{ color: 'text.primary', fontWeight: 700, fontSize: 13 }}>
                                                                {formatLimit(newVal)}
                                                            </Typography>
                                                        </>
                                                    )}
                                                </Box>
                                            </Box>
                                        );
                                    })}
                                </Box>
                            </Box>
                        )}

                        <Alert severity="warning" sx={{ borderRadius: 2 }}>
                            <Typography variant="body2" sx={{ fontWeight: 600 }}>
                                Are you sure?
                            </Typography>
                            {isBulk ? (
                                <>
                                    <Typography variant="body2" sx={{ mt: 0.5 }}>
                                        Switch <strong>{tenants.length} tenant(s)</strong> to <strong>{selectedPlan.name}</strong>?
                                    </Typography>
                                    <Box sx={{ maxHeight: 160, overflow: 'auto', mt: 1 }}>
                                        {tenants.map(t => (
                                            <Typography key={t.id} variant="body2" sx={{ py: 0.3, borderBottom: '1px solid', borderColor: 'divider', fontSize: 13 }}>
                                                • {t.name}
                                                {t.plan_name && <Chip label={t.plan_name} size="small" sx={{ ml: 1, bgcolor: 'action.hover', color: 'warning.dark', fontSize: 10, height: 18 }} />}
                                            </Typography>
                                        ))}
                                    </Box>
                                </>
                            ) : (
                                <Typography variant="body2" sx={{ mt: 1 }}>
                                    Switch <strong>{singleTenant?.name}</strong> from{' '}
                                    <Chip label={singleTenant?.plan_name || 'None'} size="small" sx={{ bgcolor: 'action.hover', color: 'warning.dark', fontSize: 11, height: 20 }} />
                                    {' → '}
                                    <Chip label={selectedPlan.name} size="small" sx={{ bgcolor: 'action.hover', color: 'primary.dark', fontSize: 11, height: 20, fontWeight: 700 }} />
                                    ?
                                </Typography>
                            )}
                        </Alert>
                    </Box>
                )}
            </DialogContent>

            <DialogActions sx={{ p: 2, pt: 0, gap: 1 }}>
                {step === 'confirm' ? (
                    <>
                        <Button onClick={handleBack} disabled={submitting} sx={{ color: 'text.secondary', transition: 'background-color 150ms ease-out' }}>
                            Back
                        </Button>
                        <Button
                            onClick={handleConfirm}
                            variant="contained"
                            disabled={submitting}
                            color="warning"
                            sx={{ fontWeight: 700, transition: 'background-color 150ms ease-out' }}
                        >
                            {submitting ? 'Changing...' : 'Confirm Change'}
                        </Button>
                    </>
                ) : (
                    <>
                        <Button onClick={onClose} variant="outlined" sx={{ color: 'text.secondary', borderColor: 'divider', transition: 'background-color 150ms ease-out, border-color 150ms ease-out' }}>
                            Cancel
                        </Button>
                        <Button
                            onClick={handleContinue}
                            variant="contained"
                            disabled={!selectedPlanId || selectedPlanId === currentPlanId}
                            sx={{ fontWeight: 700, transition: 'background-color 150ms ease-out' }}
                        >
                            Continue
                        </Button>
                    </>
                )}
            </DialogActions>
        </Dialog>
    );
}
