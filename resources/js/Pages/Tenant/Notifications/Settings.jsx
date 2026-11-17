import TenantLayout from '@/Layouts/TenantLayout';
import { Head, useForm } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Paper from '@mui/material/Paper';
import Typography from '@mui/material/Typography';
import TextField from '@mui/material/TextField';
import Button from '@mui/material/Button';

export default function NotificationsSettings({ settings }) {
    const { data, setData, put, processing, errors } = useForm({
        low_stock_threshold: settings.low_stock_threshold,
        large_order_threshold: settings.large_order_threshold,
        spike_multiplier: settings.spike_multiplier,
    });

    const submit = (event) => {
        event.preventDefault();
        put(route('tenant.notifications.settings.update'), { preserveScroll: true });
    };

    return (
        <TenantLayout>
            <Head title="Notification Settings" />

            <Typography variant="h5" sx={{ fontWeight: 700, color: '#0f172a', mb: 3 }}>
                Notification Settings
            </Typography>

            <Paper component="form" onSubmit={submit} sx={{ p: 3, maxWidth: 640, boxShadow: '0 1px 3px rgba(0,0,0,0.06)' }}>
                <Typography sx={{ fontSize: 13, color: '#64748b', mb: 3 }}>
                    Individual products can override the low-stock threshold on their edit screen.
                </Typography>

                <Box sx={{ display: 'grid', gap: 2 }}>
                    <TextField
                        label="Low stock threshold (units)"
                        type="number"
                        size="small"
                        value={data.low_stock_threshold}
                        error={Boolean(errors.low_stock_threshold)}
                        helperText={errors.low_stock_threshold}
                        onChange={(e) => setData('low_stock_threshold', e.target.value)}
                    />
                    <TextField
                        label="Large order threshold (0 disables)"
                        type="number"
                        size="small"
                        value={data.large_order_threshold}
                        error={Boolean(errors.large_order_threshold)}
                        helperText={errors.large_order_threshold}
                        onChange={(e) => setData('large_order_threshold', e.target.value)}
                    />
                    <TextField
                        label="Unusual order multiplier"
                        type="number"
                        size="small"
                        value={data.spike_multiplier}
                        error={Boolean(errors.spike_multiplier)}
                        helperText={errors.spike_multiplier ?? 'Alert when an order exceeds this multiple of the 30-day average.'}
                        onChange={(e) => setData('spike_multiplier', e.target.value)}
                    />
                </Box>

                <Button type="submit" variant="contained" disabled={processing} sx={{ mt: 3 }}>
                    Save settings
                </Button>
            </Paper>
        </TenantLayout>
    );
}
