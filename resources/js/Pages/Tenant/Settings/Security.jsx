import ConnectedAccountsCard from '@/Components/ConnectedAccountsCard';
import TenantLayout from '@/Layouts/TenantLayout';
import Alert from '@mui/material/Alert';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Paper from '@mui/material/Paper';
import Typography from '@mui/material/Typography';
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm';
import { Head, Link } from '@inertiajs/react';

export default function SecuritySettings({
    connectedAccounts = [],
    hasPassword = true,
    canConnect = true,
    status = null,
    error = null,
}) {
    return (
        <TenantLayout>
            <Head title="Security Settings" />

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
                Password &amp; Security
            </Typography>

            {status && (
                <Alert severity="success" sx={{ mb: 3, maxWidth: 720 }} data-testid="security-status">
                    {status}
                </Alert>
            )}

            {error && (
                <Alert severity="error" sx={{ mb: 3, maxWidth: 720 }} data-testid="security-error">
                    {error}
                </Alert>
            )}

            <Box sx={{ mb: 3, maxWidth: 720 }}>
                <ConnectedAccountsCard
                    accounts={connectedAccounts}
                    hasPassword={hasPassword}
                    canConnect={canConnect}
                />
            </Box>

            <Paper
                variant="outlined"
                data-testid="security-password-card"
                sx={{ p: 3, maxWidth: 720, mb: 3, borderColor: 'divider', boxShadow: 1 }}
            >
                <UpdatePasswordForm />
            </Paper>

            <Paper
                variant="outlined"
                data-testid="security-delete-card"
                sx={{ p: 3, maxWidth: 720, borderColor: 'error.light', boxShadow: 1 }}
            >
                <DeleteUserForm />
            </Paper>
        </TenantLayout>
    );
}
