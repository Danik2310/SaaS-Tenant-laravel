import TenantLayout from '@/Layouts/TenantLayout';
import { Head, useForm } from '@inertiajs/react';
import Autocomplete from '@mui/material/Autocomplete';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Paper from '@mui/material/Paper';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';

export default function CurrencySettings({ currency, allowed_currencies, base_currency }) {
    const options = allowed_currencies.map((code) => ({ code }));
    const selected = options.find((option) => option.code === currency) ?? { code: currency };
    const { data, setData, put, processing, errors } = useForm({ currency });

    const submit = (event) => {
        event.preventDefault();
        put(route('tenant.currency.settings.update'), { preserveScroll: true });
    };

    return (
        <TenantLayout>
            <Head title="Currency Settings" />

            <Typography variant="h5" sx={{ fontWeight: 700, color: '#0f172a', mb: 3 }}>
                Currency Settings
            </Typography>

            <Paper component="form" onSubmit={submit} sx={{ p: 3, maxWidth: 640, boxShadow: '0 1px 3px rgba(0,0,0,0.06)' }}>
                <Typography sx={{ fontSize: 13, color: '#64748b', mb: 3 }}>
                    Choose the currency shown in this workspace. Billing remains in {base_currency}; your prices and orders are never recalculated.
                </Typography>

                <Autocomplete
                    options={options}
                    value={selected}
                    getOptionLabel={(option) => option.code}
                    isOptionEqualToValue={(option, value) => option.code === value.code}
                    onChange={(_, option) => setData('currency', option?.code ?? '')}
                    renderInput={(params) => {
                        const { ref: inputRef, ...inputProps } = params.inputProps;

                        return (
                            <TextField
                                {...params}
                                label="Dashboard currency"
                                placeholder="Search currency"
                                error={Boolean(errors.currency)}
                                helperText={errors.currency?.[0] ?? 'Only administrator-approved currencies are available.'}
                                inputProps={inputProps}
                                InputProps={{
                                    ...params.InputProps,
                                    ref: (node) => {
                                        params.InputProps.ref(node);
                                        inputRef?.(node);
                                    },
                                }}
                            />
                        );
                    }}
                />

                <Box sx={{ display: 'flex', justifyContent: 'flex-end', mt: 3 }}>
                    <Button type="submit" variant="contained" size="small" disabled={processing}>
                        Save currency
                    </Button>
                </Box>
            </Paper>
        </TenantLayout>
    );
}
