import ConnectedAccountsCard from '@/Components/ConnectedAccountsCard';
import TenantLayout from '@/Layouts/TenantLayout';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Paper from '@mui/material/Paper';
import Typography from '@mui/material/Typography';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';
import { Head, Link } from '@inertiajs/react';

export default function Edit({ mustVerifyEmail, status, connectedAccounts = [], hasPassword = true, canConnect = true, canManageBilling = false }) {
    return (
        <TenantLayout>
            <Head title="Personal Information" />

            <Button
                component={Link}
                href={route('tenant.settings.index')}
                startIcon={<ArrowBackIcon />}
                size="small"
                sx={{ mb: 2 }}
            >
                Settings
            </Button>

            <Typography component="h1" variant="h5" sx={{ fontWeight: 700, color: 'text.primary', mb: 3 }}>
                Personal Information
            </Typography>

            <Paper variant="outlined" data-testid="personal-information-card" sx={{ p: 3, maxWidth: 720, borderColor: 'divider', boxShadow: 1 }}>
                <UpdateProfileInformationForm
                    mustVerifyEmail={mustVerifyEmail}
                    status={status}
                />
            </Paper>

            {canManageBilling && (
                <Box sx={{ mt: 3, maxWidth: 720 }} data-testid="billing-settings-link-card">
                    <Paper variant="outlined" sx={{ p: 3, borderColor: 'divider', boxShadow: 1 }}>
                        <Typography component="h2" variant="subtitle1" sx={{ fontWeight: 600, mb: 0.5 }}>
                            Billing information
                        </Typography>
                        <Typography variant="body2" color="text.secondary">
                            Billing details and payment cards are managed separately from your personal profile.
                        </Typography>
                        <Button
                            component={Link}
                            href={route('tenant.billing.settings')}
                            size="small"
                            sx={{ mt: 1.5 }}
                            data-testid="billing-settings-link"
                        >
                            Open billing settings
                        </Button>
                    </Paper>
                </Box>
            )}

            <Box sx={{ mt: 3, maxWidth: 720 }}>
                <ConnectedAccountsCard
                    accounts={connectedAccounts}
                    hasPassword={hasPassword}
                    canConnect={canConnect}
                    setPasswordHref={route('tenant.settings.security')}
                />
            </Box>
        </TenantLayout>
    );
}
