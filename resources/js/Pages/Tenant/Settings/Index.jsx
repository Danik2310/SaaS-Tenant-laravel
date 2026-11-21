import TenantLayout from '@/Layouts/TenantLayout';
import ArrowForwardIcon from '@mui/icons-material/ArrowForward';
import LockOutlinedIcon from '@mui/icons-material/LockOutlined';
import PersonOutlineIcon from '@mui/icons-material/PersonOutline';
import Avatar from '@mui/material/Avatar';
import Box from '@mui/material/Box';
import Card from '@mui/material/Card';
import CardActionArea from '@mui/material/CardActionArea';
import Chip from '@mui/material/Chip';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { Head, Link, usePage } from '@inertiajs/react';

export default function SettingsIndex() {
    const user = usePage().props.auth?.user;
    const accountCards = [
        {
            title: 'Personal information',
            description: 'Update your name and email address, review connected sign-in methods, and manage email verification.',
            href: route('profile.edit'),
            icon: <PersonOutlineIcon />,
            testId: 'settings-personal-card',
        },
        {
            title: 'Security',
            description: 'Change your password or permanently delete your personal account.',
            href: route('tenant.settings.security'),
            icon: <LockOutlinedIcon />,
            testId: 'settings-security-card',
        },
    ];
    const initials = (user?.name ?? 'U')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0].toUpperCase())
        .join('');

    return (
        <TenantLayout>
            <Head title="Settings" />

            <Box component="section" aria-labelledby="settings-heading">
                <Stack direction="row" spacing={2} alignItems="center" sx={{ mb: 4 }}>
                    <Avatar sx={{ bgcolor: 'primary.main', color: 'primary.contrastText' }}>
                        {initials}
                    </Avatar>
                    <Box>
                        <Typography component="h1" variant="h5" id="settings-heading" sx={{ fontWeight: 700, color: 'text.primary' }}>
                            Settings
                        </Typography>
                        <Typography variant="body2" color="text.secondary">
                            Manage your personal account and sign-in security.
                        </Typography>
                    </Box>
                </Stack>

                <Typography variant="subtitle1" sx={{ fontWeight: 600, color: 'text.primary', mb: 1.5 }}>
                    Your account
                </Typography>

                <Box
                    sx={{
                        display: 'grid',
                        gap: 2,
                        gridTemplateColumns: { xs: '1fr', md: 'repeat(2, minmax(0, 1fr))' },
                    }}
                >
                    {accountCards.map((card) => (
                        <Card key={card.title} variant="outlined" sx={{ borderColor: 'divider', bgcolor: 'background.paper' }}>
                            <CardActionArea
                                component={Link}
                                href={card.href}
                                data-testid={card.testId}
                                sx={{ p: 3, height: '100%', display: 'flex', alignItems: 'flex-start', gap: 2 }}
                            >
                                <Avatar
                                    variant="rounded"
                                    sx={{ bgcolor: 'action.hover', color: 'primary.main', '& .MuiSvgIcon-root': { fontSize: 22 } }}
                                >
                                    {card.icon}
                                </Avatar>
                                <Box sx={{ flex: 1, minWidth: 0 }}>
                                    <Stack direction="row" alignItems="center" spacing={1} sx={{ mb: 0.5 }}>
                                        <Typography sx={{ fontWeight: 600, color: 'text.primary' }}>
                                            {card.title}
                                        </Typography>
                                        <Chip label="Available" size="small" color="success" variant="outlined" />
                                    </Stack>
                                    <Typography variant="body2" color="text.secondary">
                                        {card.description}
                                    </Typography>
                                </Box>
                                <ArrowForwardIcon sx={{ color: 'text.disabled', alignSelf: 'center' }} />
                            </CardActionArea>
                        </Card>
                    ))}
                </Box>

                <Typography variant="body2" color="text.secondary" sx={{ mt: 3 }}>
                    Workspace details, primary domains and database information will be added here in upcoming releases.
                </Typography>
            </Box>
        </TenantLayout>
    );
}
