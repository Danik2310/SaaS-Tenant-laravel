import React, { useEffect, useState } from 'react';
import { toast } from 'sonner';
import api from '../../../services/api';
import {
    currenciesForScope,
    filterCurrencies,
    loadCurrencyCatalog,
    resetCurrencyCatalog,
} from '../../../shared/currencies';
import { resetMoneyCache } from '../../../shared/money';
import Alert from '@mui/material/Alert';
import Autocomplete from '@mui/material/Autocomplete';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Checkbox from '@mui/material/Checkbox';
import Chip from '@mui/material/Chip';
import CircularProgress from '@mui/material/CircularProgress';
import Divider from '@mui/material/Divider';
import FormControl from '@mui/material/FormControl';
import FormControlLabel from '@mui/material/FormControlLabel';
import FormGroup from '@mui/material/FormGroup';
import FormHelperText from '@mui/material/FormHelperText';
import Grid from '@mui/material/Grid';
import InputLabel from '@mui/material/InputLabel';
import MenuItem from '@mui/material/MenuItem';
import Paper from '@mui/material/Paper';
import Select from '@mui/material/Select';
import Switch from '@mui/material/Switch';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import SaveIcon from '@mui/icons-material/Save';
import { surfaceMotionSx } from '@/Components/surfaceMotion';

const settingFields = [
    { key: 'app_name', label: 'Application Name' },
    { key: 'app_description', label: 'Application Description' },
    { key: 'support_email', label: 'Support Email', type: 'email' },
    { key: 'tenant_db_prefix', label: 'Tenant Database Prefix' },
    { key: 'allow_registration', label: 'Allow Registration', type: 'boolean' },
    { key: 'maintenance_mode', label: 'Maintenance Mode', type: 'boolean' },
    { key: 'default_plan_id', label: 'Default Plan ID', type: 'number' },
];

const SCOPES = [
    { value: 'admin_dashboard', label: 'Admin dashboard' },
    { value: 'public_plans', label: 'Public plans' },
    { value: 'tenant_dashboards', label: 'Tenant dashboards' },
];

const parseCodes = (value) => {
    try {
        const parsed = typeof value === 'string' ? JSON.parse(value) : value;
        return Array.isArray(parsed) ? parsed.filter((code) => typeof code === 'string') : [];
    } catch {
        return [];
    }
};

const optionFor = (code, options) => options.find((option) => option.code === code) ?? { code };

function CurrencyAutocomplete({ label, value, onChange, options, multiple = false, helperText }) {
    const selected = multiple
        ? (value || []).map((code) => optionFor(code, options))
        : (value ? optionFor(value, options) : null);

    return (
        <Autocomplete
            multiple={multiple}
            options={options}
            value={selected}
            onChange={(_, next) => {
                if (multiple) {
                    onChange((next || []).map((option) => option.code));
                } else {
                    onChange(next?.code ?? '');
                }
            }}
            getOptionLabel={(option) => (option.name ? `${option.code} — ${option.name}` : option.code || '')}
            isOptionEqualToValue={(option, selectedOption) => option.code === selectedOption.code}
            filterOptions={(optionsList, { inputValue }) => filterCurrencies(optionsList, inputValue)}
            renderTags={(tags, getTagProps) => tags.map((tag, index) => (
                <Chip label={tag.code} size="small" {...getTagProps({ index })} key={tag.code} />
            ))}
            renderInput={(params) => {
                const { ref: inputRef, ...inputProps } = params.inputProps;

                const assignRef = (ref, node) => {
                    if (typeof ref === 'function') {
                        ref(node);
                    } else if (ref && typeof ref === 'object') {
                        ref.current = node;
                    }
                };

                return (
                    <TextField
                        {...params}
                        label={label}
                        size="small"
                        helperText={helperText}
                        inputProps={inputProps}
                        InputProps={{
                            ...params.InputProps,
                            ref: (node) => {
                                assignRef(params.InputProps.ref, node);
                                assignRef(inputRef, node);
                            },
                        }}
                    />
                );
            }}
        />
    );
}

export default function Settings() {
    const [settings, setSettings] = useState({});
    const [catalog, setCatalog] = useState(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);

    useEffect(() => {
        let active = true;

        Promise.all([api.get('/admin/api/settings'), loadCurrencyCatalog()])
            .then(([settingsRes, catalogRes]) => {
                if (!active) return;

                const values = {};
                settingsRes.data.settings.forEach((setting) => {
                    values[setting.key] = setting.value;
                });

                const currency = catalogRes.settings || {};
                setCatalog(catalogRes);
                setSettings({
                    ...values,
                    currency_mode: values.currency_mode || currency.mode || 'preset',
                    currency_preset: values.currency_preset || currency.preset || 'global_common',
                    enabled_currencies: values.enabled_currencies
                        ? parseCodes(values.enabled_currencies)
                        : (currency.enabled_currencies?.length ? currency.enabled_currencies : ['USD']),
                    currency_scope: values.currency_scope
                        ? parseCodes(values.currency_scope)
                        : (currency.scopes?.length ? currency.scopes : SCOPES.map((scope) => scope.value)),
                    currency_admin: values.currency_admin || currency.admin_currency || catalogRes.base,
                    currency_public: values.currency_public || currency.public_currency || catalogRes.base,
                    tenant_currency_mode: values.tenant_currency_mode || currency.tenant_mode || 'preset',
                    tenant_currency_preset: values.tenant_currency_preset || currency.tenant_preset || 'global_common',
                    tenant_currencies: values.tenant_currencies
                        ? parseCodes(values.tenant_currencies)
                        : (currency.tenant_currencies?.length ? currency.tenant_currencies : ['USD']),
                    currency: values.currency_admin || currency.admin_currency || catalogRes.base,
                });
                setError(null);
            })
            .catch(() => {
                if (!active) return;
                const message = 'Failed to load settings';
                toast.error(message);
                setError(message);
            })
            .finally(() => {
                if (active) setLoading(false);
            });

        return () => {
            active = false;
        };
    }, []);

    const handleChange = (key, value) => setSettings((prev) => ({ ...prev, [key]: value }));

    const currencyOptions = currenciesForScope(catalog, null);
    const tenantOptions = currenciesForScope(catalog, 'tenant');
    const publicOptions = currenciesForScope(catalog, 'public');
    const presets = Object.entries(catalog?.presets || {}).map(([slug, preset]) => ({
        value: slug,
        label: preset.name || slug,
        codes: preset.currencies || [],
    }));

    const applyPreset = (presetKey, codesKey) => {
        const preset = presets.find((item) => item.value === settings[presetKey]);
        if (!preset) return;

        const codes = preset.value === 'all_supported' && preset.codes.length === 0
            ? currencyOptions.map((currency) => currency.code)
            : preset.codes;

        handleChange(codesKey, codes);
        handleChange(presetKey === 'currency_preset' ? 'currency_mode' : 'tenant_currency_mode', 'preset');
    };

    const handleSave = async () => {
        setSaving(true);
        try {
            const payload = Object.entries(settings).map(([key, value]) => ({
                key,
                value: Array.isArray(value) ? JSON.stringify(value) : String(value),
            }));
            await api.put('/admin/api/settings', { settings: payload });
            resetMoneyCache();
            resetCurrencyCatalog();
            toast.success('Settings saved successfully');
        } catch (err) {
            const message = err.response?.data?.message || 'Failed to save settings';
            toast.error(message);
        } finally {
            setSaving(false);
        }
    };

    if (loading) {
        return (
            <Box sx={{ display: 'flex', justifyContent: 'center', py: 8 }}>
                <CircularProgress />
            </Box>
        );
    }

    return (
        <Box sx={{ maxWidth: 1000, mx: 'auto' }}>
            {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}

            <Paper sx={{ p: 4, border: '1px solid', borderColor: 'divider', borderRadius: 2, boxShadow: 'none', ...surfaceMotionSx }}>
                <Typography variant="h5" sx={{ fontWeight: 700, mb: 1 }}>System Settings</Typography>
                <Typography variant="body2" color="text.secondary" sx={{ mb: 4 }}>
                    Configure global settings and the currencies available across the platform.
                </Typography>

                <Grid container spacing={3}>
                    {settingFields.map((field) => (
                        <Grid item xs={12} sm={6} key={field.key}>
                            {field.type === 'boolean' ? (
                                <FormControlLabel
                                    control={
                                        <Switch
                                            checked={settings[field.key] === 'true'}
                                            onChange={(event) => handleChange(field.key, event.target.checked ? 'true' : 'false')}
                                        />
                                    }
                                    label={field.label}
                                />
                            ) : (
                                <TextField
                                    label={field.label}
                                    value={settings[field.key] || ''}
                                    onChange={(event) => handleChange(field.key, event.target.value)}
                                    fullWidth
                                    size="small"
                                    type={field.type || 'text'}
                                />
                            )}
                        </Grid>
                    ))}
                </Grid>

                <Divider sx={{ my: 4 }} />

                <Typography variant="h6" sx={{ fontWeight: 700, mb: 1 }}>Currency Settings</Typography>
                <Typography variant="body2" color="text.secondary" sx={{ mb: 3 }}>
                    Base currency is {catalog?.base || 'USD'}. Plan prices are stored in the base currency; other values are display-only.
                </Typography>

                <Grid container spacing={3}>
                    <Grid item xs={12} md={6}>
                        <FormControl size="small" fullWidth>
                            <InputLabel>Currency preset</InputLabel>
                            <Select
                                label="Currency preset"
                                value={settings.currency_preset || 'custom'}
                                onChange={(event) => {
                                    handleChange('currency_preset', event.target.value);
                                    handleChange('currency_mode', event.target.value === 'custom' ? 'custom' : 'preset');
                                }}
                            >
                                {presets.map((preset) => (
                                    <MenuItem key={preset.value} value={preset.value}>{preset.label}</MenuItem>
                                ))}
                            </Select>
                            <FormHelperText>Choose a shortcut, then fine-tune the list below.</FormHelperText>
                        </FormControl>
                    </Grid>

                    <Grid item xs={12} md={6}>
                        <Box sx={{ display: 'flex', alignItems: 'center', height: '100%', gap: 1 }}>
                            <Button
                                size="small"
                                variant="outlined"
                                disabled={settings.currency_preset === 'custom'}
                                onClick={() => applyPreset('currency_preset', 'enabled_currencies')}
                            >
                                Apply preset
                            </Button>
                            <Button
                                size="small"
                                variant="text"
                                onClick={() => handleChange('currency_mode', 'custom')}
                                disabled={settings.currency_mode === 'custom'}
                            >
                                Customize list
                            </Button>
                        </Box>
                    </Grid>

                    <Grid item xs={12} md={6}>
                        <CurrencyAutocomplete
                            label="Enabled currencies"
                            multiple
                            options={currencyOptions}
                            value={settings.enabled_currencies || []}
                            onChange={(codes) => handleChange('enabled_currencies', codes)}
                            helperText="Search by code or name. These currencies can receive plan price overrides."
                        />
                    </Grid>

                    <Grid item xs={12} md={6}>
                        <Typography sx={{ fontSize: 14, fontWeight: 600, color: 'text.secondary', mb: 1 }}>Apply to</Typography>
                        <FormGroup row>
                            {SCOPES.map((scope) => (
                                <FormControlLabel
                                    key={scope.value}
                                    control={
                                        <Checkbox
                                            size="small"
                                            checked={(settings.currency_scope || []).includes(scope.value)}
                                            onChange={(event) => {
                                                const current = settings.currency_scope || [];
                                                handleChange(
                                                    'currency_scope',
                                                    event.target.checked
                                                        ? [...current, scope.value]
                                                        : current.filter((value) => value !== scope.value),
                                                );
                                            }}
                                        />
                                    }
                                    label={scope.label}
                                />
                            ))}
                        </FormGroup>
                        <FormHelperText>Controls where the enabled currencies are shown.</FormHelperText>
                    </Grid>

                    <Grid item xs={12} md={6}>
                        <CurrencyAutocomplete
                            label="Admin display currency"
                            options={currencyOptions}
                            value={settings.currency_admin || ''}
                            onChange={(code) => handleChange('currency_admin', code)}
                            helperText="One currency for the admin dashboard."
                        />
                    </Grid>

                    <Grid item xs={12} md={6}>
                        <CurrencyAutocomplete
                            label="Public pricing currency"
                            options={publicOptions}
                            value={settings.currency_public || ''}
                            onChange={(code) => handleChange('currency_public', code)}
                            helperText="Exactly one currency is shown on the public pricing page."
                        />
                    </Grid>
                </Grid>

                <Divider sx={{ my: 4 }} />

                <Typography variant="h6" sx={{ fontWeight: 700, mb: 1 }}>Tenant Currency Whitelist</Typography>
                <Typography variant="body2" color="text.secondary" sx={{ mb: 3 }}>
                    Tenants may only select from these currencies. They cannot edit currencies, rates or plan prices.
                </Typography>

                <Grid container spacing={3}>
                    <Grid item xs={12} md={6}>
                        <FormControl size="small" fullWidth>
                            <InputLabel>Tenant preset</InputLabel>
                            <Select
                                label="Tenant preset"
                                value={settings.tenant_currency_preset || 'custom'}
                                onChange={(event) => {
                                    handleChange('tenant_currency_preset', event.target.value);
                                    handleChange('tenant_currency_mode', event.target.value === 'custom' ? 'custom' : 'preset');
                                }}
                            >
                                {presets.map((preset) => (
                                    <MenuItem key={preset.value} value={preset.value}>{preset.label}</MenuItem>
                                ))}
                            </Select>
                        </FormControl>
                    </Grid>

                    <Grid item xs={12} md={6}>
                        <Box sx={{ display: 'flex', alignItems: 'center', height: '100%', gap: 1 }}>
                            <Button
                                size="small"
                                variant="outlined"
                                disabled={settings.tenant_currency_preset === 'custom'}
                                onClick={() => applyPreset('tenant_currency_preset', 'tenant_currencies')}
                            >
                                Apply preset
                            </Button>
                            <Button
                                size="small"
                                variant="text"
                                onClick={() => handleChange('tenant_currency_mode', 'custom')}
                                disabled={settings.tenant_currency_mode === 'custom'}
                            >
                                Customize list
                            </Button>
                        </Box>
                    </Grid>

                    <Grid item xs={12}>
                        <CurrencyAutocomplete
                            label="Candidates available to tenants"
                            multiple
                            options={tenantOptions}
                            value={settings.tenant_currencies || []}
                            onChange={(codes) => handleChange('tenant_currencies', codes)}
                            helperText="Only active currencies flagged for tenant use appear here."
                        />
                    </Grid>
                </Grid>

                <Box sx={{ display: 'flex', justifyContent: 'flex-end', mt: 4 }}>
                    <Button variant="contained" startIcon={<SaveIcon />} onClick={handleSave} disabled={saving} sx={{ transition: 'background-color 150ms ease-out' }}>
                        {saving ? 'Saving...' : 'Save All Settings'}
                    </Button>
                </Box>
            </Paper>
        </Box>
    );
}
