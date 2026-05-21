import React, { useEffect, useState } from 'react';
import { toast } from 'sonner';
import api from '../../../services/api';
import Box from '@mui/material/Box';
import Paper from '@mui/material/Paper';
import Typography from '@mui/material/Typography';
import TextField from '@mui/material/TextField';
import Button from '@mui/material/Button';
import Switch from '@mui/material/Switch';
import FormControlLabel from '@mui/material/FormControlLabel';
import Select from '@mui/material/Select';
import MenuItem from '@mui/material/MenuItem';
import FormControl from '@mui/material/FormControl';
import InputLabel from '@mui/material/InputLabel';
import CircularProgress from '@mui/material/CircularProgress';
import Alert from '@mui/material/Alert';
import Grid from '@mui/material/Grid';
import Divider from '@mui/material/Divider';
import SaveIcon from '@mui/icons-material/Save';

const settingFields = [
    { key: 'app_name', label: 'Application Name', type: 'text' },
    { key: 'app_description', label: 'Application Description', type: 'text' },
    { key: 'support_email', label: 'Support Email', type: 'email' },
    { key: 'currency', label: 'Currency', type: 'text' },
    { key: 'tenant_db_prefix', label: 'Tenant Database Prefix', type: 'text' },
    { key: 'allow_registration', label: 'Allow Registration', type: 'boolean' },
    { key: 'maintenance_mode', label: 'Maintenance Mode', type: 'boolean' },
    { key: 'default_plan_id', label: 'Default Plan ID', type: 'number' },
];

export default function Settings() {
    const [settings, setSettings] = useState({});
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);

    useEffect(() => {
        fetchSettings();
    }, []);

    const fetchSettings = async () => {
        setLoading(true);
        try {
            const res = await api.get('/admin/api/settings');
            const settingMap = {};
            res.data.settings.forEach((s) => {
                settingMap[s.key] = s.value;
            });
            setSettings(settingMap);
            setError(null);
        } catch (err) {
            const message = 'Failed to load settings';
            toast.error(message);
            setError(message);
        } finally {
            setLoading(false);
        }
    };

    const handleChange = (key, value) => {
        setSettings((prev) => ({ ...prev, [key]: value }));
    };

    const handleSave = async () => {
        setSaving(true);
        try {
            const payload = Object.entries(settings).map(([key, value]) => ({
                key,
                value: String(value),
            }));
            await api.put('/admin/api/settings', { settings: payload });
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
        <Box sx={{ maxWidth: 800, mx: 'auto' }}>
            {error && (
                <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>
            )}

            <Paper sx={{ p: 4, borderRadius: 2, boxShadow: '0 1px 3px rgba(0,0,0,0.06)' }}>
                <Typography variant="h5" sx={{ fontWeight: 700, mb: 1 }}>
                    System Settings
                </Typography>
                <Typography variant="body2" color="text.secondary" sx={{ mb: 4 }}>
                    Configure global system-wide settings for your SaaS platform.
                </Typography>

                <Grid container spacing={3}>
                    {settingFields.map((field) => (
                        <Grid item xs={12} sm={field.type === 'boolean' ? 6 : 6} key={field.key}>
                            {field.type === 'boolean' ? (
                                <FormControlLabel
                                    control={
                                        <Switch
                                            checked={settings[field.key] === 'true'}
                                            onChange={(e) => handleChange(field.key, e.target.checked ? 'true' : 'false')}
                                        />
                                    }
                                    label={field.label}
                                />
                            ) : (
                                <TextField
                                    label={field.label}
                                    value={settings[field.key] || ''}
                                    onChange={(e) => handleChange(field.key, e.target.value)}
                                    fullWidth
                                    size="small"
                                    type={field.type}
                                />
                            )}
                        </Grid>
                    ))}
                </Grid>

                <Divider sx={{ my: 4 }} />

                <Box sx={{ display: 'flex', justifyContent: 'flex-end' }}>
                    <Button
                        variant="contained"
                        startIcon={<SaveIcon />}
                        onClick={handleSave}
                        disabled={saving}
                    >
                        {saving ? 'Saving...' : 'Save All Settings'}
                    </Button>
                </Box>
            </Paper>
        </Box>
    );
}
