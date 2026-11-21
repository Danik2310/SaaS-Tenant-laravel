import TenantLayout from '@/Layouts/TenantLayout';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import Button from '@mui/material/Button';
import Paper from '@mui/material/Paper';
import Typography from '@mui/material/Typography';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';
import { Head, Link } from '@inertiajs/react';

export default function Edit({ mustVerifyEmail, status }) {
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
        </TenantLayout>
    );
}
