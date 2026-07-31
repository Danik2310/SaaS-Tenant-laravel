import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import Button from '@mui/material/Button';
import Typography from '@mui/material/Typography';
import Box from '@mui/material/Box';
import Paper from '@mui/material/Paper';
import Grid from '@mui/material/Grid';

export default function Upgrade({ currentPlan, plans }) {
    const featureDefinitions = usePage().props.feature_definitions ?? {};

    const labelFor = (key) => featureDefinitions[key]?.label ?? key;

    return (
        <AuthenticatedLayout
            header={<Typography variant="h6">Upgrade Plan</Typography>}
        >
            <Head title="Upgrade Plan" />

            <Box sx={{ py: 4 }}>
                <Typography variant="h4" sx={{ fontWeight: 700, mb: 1 }}>
                    Upgrade Your Plan
                </Typography>
                <Typography variant="body1" color="text.secondary" sx={{ mb: 4 }}>
                    Choose the plan that best fits your needs.
                    {currentPlan && (
                        <> You are currently on the <strong>{currentPlan.name}</strong> plan.</>
                    )}
                </Typography>

                <Grid container spacing={3}>
                    {(plans ?? []).map((plan) => (
                        <Grid item xs={12} sm={6} md={4} key={plan.id}>
                            <Paper sx={{
                                p: 3,
                                border: currentPlan?.id === plan.id ? '2px solid' : '1px solid',
                                borderColor: currentPlan?.id === plan.id ? 'primary.main' : 'divider',
                                borderRadius: 2,
                                height: '100%',
                                display: 'flex',
                                flexDirection: 'column',
                            }}>
                                <Typography variant="h5" sx={{ fontWeight: 700, mb: 1 }}>
                                    {plan.name}
                                </Typography>
                                <Typography variant="h3" sx={{ fontWeight: 700, mb: 2 }}>
                                    ${plan.price}<Typography variant="caption" color="text.secondary">/mo</Typography>
                                </Typography>

                                {plan.features && plan.features.length > 0 && (
                                    <Box sx={{ mb: 2, flex: 1 }}>
                                        {plan.features.map((feature) => (
                                            <Typography key={feature} variant="body2" sx={{ mb: 0.5 }}>
                                                {labelFor(feature)}
                                            </Typography>
                                        ))}
                                    </Box>
                                )}

                                <Button
                                    variant={currentPlan?.id === plan.id ? 'outlined' : 'contained'}
                                    disabled={currentPlan?.id === plan.id}
                                    fullWidth
                                    component={Link}
                                    href={currentPlan?.id === plan.id ? '#' : '#contact'}
                                >
                                    {currentPlan?.id === plan.id ? 'Current Plan' : 'Contact Sales'}
                                </Button>
                            </Paper>
                        </Grid>
                    ))}
                </Grid>
            </Box>
        </AuthenticatedLayout>
    );
}
