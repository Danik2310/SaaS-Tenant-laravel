import TenantLayout from '@/Layouts/TenantLayout';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import CreditCardIcon from '@mui/icons-material/CreditCard';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Chip from '@mui/material/Chip';
import Divider from '@mui/material/Divider';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import { Head, Link, router, useForm } from '@inertiajs/react';

const SUBSCRIPTION_STATUS_COLORS = {
    active: 'success',
    trial: 'info',
    pending: 'warning',
    cancelled: 'default',
    expired: 'default',
};

const TEXT_FIELDS = [
    { name: 'company_name', label: 'Company name', testId: 'billing-company-input' },
    { name: 'first_name', label: 'First name', testId: 'billing-first-name-input' },
    { name: 'last_name', label: 'Last name', testId: 'billing-last-name-input' },
    { name: 'phone', label: 'Phone', testId: 'billing-phone-input' },
    { name: 'address_line1', label: 'Address line 1', testId: 'billing-address1-input' },
    { name: 'address_line2', label: 'Address line 2', testId: 'billing-address2-input' },
    { name: 'city', label: 'City', testId: 'billing-city-input' },
    { name: 'state', label: 'State / province', testId: 'billing-state-input' },
    { name: 'postal_code', label: 'Postal code', testId: 'billing-postal-code-input' },
    { name: 'country', label: 'Country', testId: 'billing-country-input' },
    { name: 'tax_id', label: 'Tax ID', testId: 'billing-tax-id-input' },
];

export default function BillingSettings({
    billing = {},
    subscription = null,
    cards = [],
    cardsEnabled = false,
    cardError = null,
    portalResult = null,
    status = null,
    error = null,
}) {
    const form = useForm({
        billing_email: billing.billing_email ?? '',
        company_name: billing.company_name ?? '',
        first_name: billing.first_name ?? '',
        last_name: billing.last_name ?? '',
        phone: billing.phone ?? '',
        address_line1: billing.address_line1 ?? '',
        address_line2: billing.address_line2 ?? '',
        city: billing.city ?? '',
        state: billing.state ?? '',
        postal_code: billing.postal_code ?? '',
        country: billing.country ?? '',
        tax_id: billing.tax_id ?? '',
    });

    const submit = (event) => {
        event.preventDefault();
        form.put(route('tenant.settings.billing.update'), { preserveScroll: true });
    };

    const openPortal = () => {
        router.post(route('tenant.settings.billing.portal'), {}, { preserveScroll: true });
    };

    return (
        <TenantLayout>
            <Head title="Billing" />

            <Button
                component={Link}
                href={route('tenant.settings.index')}
                startIcon={<ArrowBackIcon />}
                size="small"
                sx={{ mb: 2 }}
            >
                Settings
            </Button>

            <Typography component="h1" variant="h5" sx={{ fontWeight: 700, color: 'text.primary', mb: 1 }}>
                Billing
            </Typography>
            <Typography variant="body2" color="text.secondary" sx={{ mb: 3 }}>
                Manage billing details and payment cards for this workspace.
            </Typography>

            <Stack spacing={2} sx={{ maxWidth: 760 }}>
                {(status || portalResult === 'success') && (
                    <Alert severity="success" data-testid="billing-status-alert">
                        {status || 'Card settings updated in Stripe. Your masked card list is up to date.'}
                    </Alert>
                )}

                {error && (
                    <Alert severity="error" data-testid="billing-error-alert">{error}</Alert>
                )}

                {cardError && (
                    <Alert severity="warning" data-testid="billing-card-error-alert">{cardError}</Alert>
                )}

                {portalResult === 'cancelled' && (
                    <Alert severity="info" data-testid="billing-portal-cancelled-alert">
                        Card management was cancelled. No changes were made.
                    </Alert>
                )}

                {subscription && (
                    <Paper variant="outlined" data-testid="billing-subscription-card" sx={{ p: 3, borderColor: 'divider', boxShadow: 1 }}>
                        <Stack direction="row" spacing={2} alignItems="center" justifyContent="space-between">
                            <Box>
                                <Typography variant="caption" color="text.secondary" sx={{ textTransform: 'uppercase', letterSpacing: 1 }}>
                                    Current plan
                                </Typography>
                                <Typography component="h2" variant="subtitle1" sx={{ fontWeight: 600 }} data-testid="billing-subscription-plan">
                                    {subscription.plan_name}
                                </Typography>
                            </Box>
                            <Chip
                                label={subscription.status}
                                color={SUBSCRIPTION_STATUS_COLORS[subscription.status] ?? 'default'}
                                size="small"
                                data-testid="billing-subscription-status"
                            />
                        </Stack>
                        <Divider sx={{ my: 2 }} />
                        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={{ xs: 0.5, sm: 4 }}>
                            <Typography variant="body2" color="text.secondary">
                                Started {subscription.starts_at ?? '—'}
                            </Typography>
                            <Typography variant="body2" color="text.secondary" data-testid="billing-subscription-end">
                                {subscription.ends_at ? `Ends ${subscription.ends_at}` : 'No scheduled end date'}
                            </Typography>
                        </Stack>
                    </Paper>
                )}

                <Paper variant="outlined" data-testid="billing-details-card" sx={{ p: 3, borderColor: 'divider', boxShadow: 1 }}>
                    <Typography component="h2" variant="subtitle1" sx={{ fontWeight: 600, mb: 2 }}>
                        Billing details
                    </Typography>

                    <Box component="form" onSubmit={submit} noValidate>
                        <Stack spacing={2}>
                            <TextField
                                required
                                fullWidth
                                size="small"
                                type="email"
                                label="Billing email"
                                inputProps={{ 'data-testid': 'billing-email-input' }}
                                value={form.data.billing_email}
                                onChange={(event) => form.setData('billing_email', event.target.value)}
                                error={Boolean(form.errors.billing_email)}
                                helperText={form.errors.billing_email || 'Receipts and card-change confirmations are sent here.'}
                            />

                            <Box
                                sx={{
                                    display: 'grid',
                                    gap: 2,
                                    gridTemplateColumns: { xs: '1fr', sm: 'repeat(2, minmax(0, 1fr))' },
                                }}
                            >
                                {TEXT_FIELDS.map((field) => (
                                    <TextField
                                        key={field.name}
                                        fullWidth
                                        size="small"
                                        label={field.label}
                                        inputProps={{ 'data-testid': field.testId }}
                                        value={form.data[field.name]}
                                        onChange={(event) => form.setData(field.name, event.target.value)}
                                        error={Boolean(form.errors[field.name])}
                                        helperText={form.errors[field.name]}
                                    />
                                ))}
                            </Box>

                            <Box>
                                <Button type="submit" variant="contained" disabled={form.processing} data-testid="billing-save-button">
                                    Save billing details
                                </Button>
                            </Box>
                        </Stack>
                    </Box>
                </Paper>

                <Paper variant="outlined" data-testid="billing-cards-card" sx={{ p: 3, borderColor: 'divider', boxShadow: 1 }}>
                    <Stack direction="row" spacing={2} alignItems="center" sx={{ mb: 1 }}>
                        <CreditCardIcon color="primary" />
                        <Typography component="h2" variant="subtitle1" sx={{ fontWeight: 600 }}>
                            Payment cards
                        </Typography>
                    </Stack>
                    <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
                        Cards are added and removed in Stripe's secure portal. We store only masked details and never see your full card number or CVC.
                    </Typography>

                    <Divider sx={{ mb: 2 }} />

                    {cards.length > 0 ? (
                        <Stack spacing={1.5} data-testid="saved-cards-list">
                            {cards.map((card) => (
                                <Stack
                                    key={card.id}
                                    direction="row"
                                    alignItems="center"
                                    justifyContent="space-between"
                                    spacing={2}
                                    data-testid="saved-card"
                                    sx={{ p: 1.5, border: 1, borderColor: 'divider', borderRadius: 1 }}
                                >
                                    <Box>
                                        <Typography sx={{ fontWeight: 600 }}>
                                            {card.brand} ending in {card.last4}
                                        </Typography>
                                        <Typography variant="body2" color="text.secondary">
                                            Expires {String(card.exp_month).padStart(2, '0')}/{card.exp_year}
                                        </Typography>
                                    </Box>
                                    {card.is_default && <Chip label="Default" size="small" color="primary" variant="outlined" />}
                                </Stack>
                            ))}
                        </Stack>
                    ) : (
                        <Typography variant="body2" color="text.secondary" data-testid="no-cards-message">
                            No saved cards yet.
                        </Typography>
                    )}

                    <Box sx={{ mt: 3 }}>
                        <Button
                            variant="outlined"
                            onClick={openPortal}
                            disabled={!cardsEnabled}
                            data-testid="manage-cards-button"
                        >
                            Manage cards in Stripe
                        </Button>
                        {!cardsEnabled && (
                            <Typography variant="caption" color="text.secondary" sx={{ display: 'block', mt: 1 }}>
                                Card management is not configured for this workspace.
                            </Typography>
                        )}
                    </Box>
                </Paper>
            </Stack>
        </TenantLayout>
    );
}
