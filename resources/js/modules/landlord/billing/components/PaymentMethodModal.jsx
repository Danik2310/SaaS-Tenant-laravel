import React, { useState, useEffect } from 'react';
import {
    Dialog,
    DialogTitle,
    DialogContent,
    DialogActions,
    Button,
    TextField,
    FormControl,
    InputLabel,
    Select,
    MenuItem,
    FormControlLabel,
    Switch,
    CircularProgress,
    Typography,
    InputAdornment,
    IconButton,
} from '@mui/material';
import Visibility from '@mui/icons-material/Visibility';
import VisibilityOff from '@mui/icons-material/VisibilityOff';
import { toast } from 'sonner';
import api from '../../../../services/api';

export default function PaymentMethodModal({ open, onClose, editingPayment, fetchPaymentMethods, setError }) {
    const [form, setForm] = useState({ name: '', provider: '', api_key: '', secret_key: '', mode: 'test', active: true });
    const [loading, setLoading] = useState(false);
    const [fieldErrors, setFieldErrors] = useState({});
    const [showApiKey, setShowApiKey] = useState(false);
    const [showSecretKey, setShowSecretKey] = useState(false);

    useEffect(() => {
        setShowApiKey(false);
        setShowSecretKey(false);

        if (editingPayment) {
            setForm({
                name: editingPayment.name,
                provider: editingPayment.provider,
                api_key: editingPayment.api_key,
                secret_key: editingPayment.secret_key,
                mode: editingPayment.mode,
                active: editingPayment.active,
            });
        } else {
            setForm({ name: '', provider: '', api_key: '', secret_key: '', mode: 'test', active: true });
        }
    }, [editingPayment, open]);

    const handleToggleApiKeyVisibility = () => {
        setShowApiKey((prev) => !prev);
    };

    const handleToggleSecretKeyVisibility = () => {
        setShowSecretKey((prev) => !prev);
    };

    const handleSave = async () => {
        // Reset field errors
        setFieldErrors({});

        // Validación básica
        const errors = {};

        // Name validation
        if (!form.name || form.name.trim().length === 0) {
            errors.name = 'Name is required';
        } else if (form.name.trim().length < 3) {
            errors.name = 'Name must be at least 3 characters';
        } else if (form.name.length > 255) {
            errors.name = 'Name must be less than 255 characters';
        }

        // Provider validation
        if (!form.provider) {
            errors.provider = 'Provider is required';
        } else if (!['stripe', 'paypal', 'other'].includes(form.provider)) {
            errors.provider = 'Invalid provider selected';
        }

        // API Key validation
        if (!form.api_key || form.api_key.trim().length === 0) {
            errors.api_key = 'API Key is required';
        } else if (form.api_key.length < 10) {
            errors.api_key = 'API Key must be at least 10 characters';
        } else {
            // Provider-specific validation
            if (form.provider === 'stripe' && !form.api_key.startsWith('pk_')) {
                errors.api_key = 'Stripe API keys should start with "pk_"';
            } else if (form.provider === 'paypal' && !form.api_key.startsWith('A')) {
                errors.api_key = 'PayPal API keys typically start with "A"';
            }
        }

        // Secret Key validation
        if (!form.secret_key || form.secret_key.trim().length === 0) {
            errors.secret_key = 'Secret Key is required';
        } else if (form.secret_key.length < 10) {
            errors.secret_key = 'Secret Key must be at least 10 characters';
        } else {
            // Provider-specific validation
            if (form.provider === 'stripe' && !form.secret_key.startsWith('sk_')) {
                errors.secret_key = 'Stripe secret keys should start with "sk_"';
            }
        }

        // Mode validation
        if (!form.mode || !['test', 'live'].includes(form.mode)) {
            errors.mode = 'Invalid mode selected';
        }

        // Check if there are any errors
        if (Object.keys(errors).length > 0) {
            setFieldErrors(errors);
            setError('Please fix the validation errors below');
            return;
        }

        setLoading(true);
        try {
            if (editingPayment) {
                await api.put(`/admin/api/payment-methods/${editingPayment.id}`, form);
                toast.success('Payment method updated successfully');
            } else {
                await api.post('/admin/api/payment-methods', form);
                toast.success('Payment method created successfully');
            }
            onClose();
            fetchPaymentMethods();
            setError(null); // Clear any previous errors
        } catch (err) {
            const errorResponse = err.response?.data;
            if (errorResponse?.errors) {
                // Handle validation errors from backend
                setFieldErrors(errorResponse.errors);
                setError('Please fix the validation errors below');
            } else {
                const errorMessage = errorResponse?.message || errorResponse?.error || 'Failed to save payment method. Please check your permissions.';
                toast.error(errorMessage);
                setError(errorMessage);
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <Dialog open={open} onClose={onClose} maxWidth="sm" fullWidth>
            <DialogTitle>{editingPayment ? 'Edit Payment Method' : 'Add Payment Method'}</DialogTitle>
            <DialogContent>
                <TextField
                    fullWidth
                    label="Name"
                    value={form.name}
                    onChange={(e) => setForm({ ...form, name: e.target.value })}
                    sx={{ mb: 2 }}
                    error={!!fieldErrors.name}
                    helperText={fieldErrors.name}
                    required
                />
                <FormControl fullWidth sx={{ mb: 2 }} error={!!fieldErrors.provider}>
                    <InputLabel>Provider</InputLabel>
                    <Select
                        value={form.provider}
                        onChange={(e) => setForm({ ...form, provider: e.target.value })}
                    >
                        <MenuItem value="stripe">Stripe</MenuItem>
                        <MenuItem value="paypal">PayPal</MenuItem>
                        <MenuItem value="other">Other</MenuItem>
                    </Select>
                    {fieldErrors.provider && (
                        <Typography variant="caption" color="error" sx={{ mt: 1, ml: 2 }}>
                            {fieldErrors.provider}
                        </Typography>
                    )}
                </FormControl>
                <TextField
                    fullWidth
                    label="API Key"
                    value={form.api_key}
                    onChange={(e) => setForm({ ...form, api_key: e.target.value })}
                    sx={{ mb: 2 }}
                    error={!!fieldErrors.api_key}
                    helperText={fieldErrors.api_key}
                    required
                    type={showApiKey ? 'text' : 'password'}
                    InputProps={{
                        endAdornment: (
                            <InputAdornment position="end">
                                <IconButton
                                    aria-label={showApiKey ? 'Hide API key' : 'Show API key'}
                                    onClick={handleToggleApiKeyVisibility}
                                    edge="end"
                                >
                                    {showApiKey ? <VisibilityOff /> : <Visibility />}
                                </IconButton>
                            </InputAdornment>
                        ),
                    }}
                />
                <TextField
                    fullWidth
                    label="Secret Key"
                    value={form.secret_key}
                    onChange={(e) => setForm({ ...form, secret_key: e.target.value })}
                    sx={{ mb: 2 }}
                    error={!!fieldErrors.secret_key}
                    helperText={fieldErrors.secret_key}
                    required
                    type={showSecretKey ? 'text' : 'password'}
                    InputProps={{
                        endAdornment: (
                            <InputAdornment position="end">
                                <IconButton
                                    aria-label={showSecretKey ? 'Hide secret key' : 'Show secret key'}
                                    onClick={handleToggleSecretKeyVisibility}
                                    edge="end"
                                >
                                    {showSecretKey ? <VisibilityOff /> : <Visibility />}
                                </IconButton>
                            </InputAdornment>
                        ),
                    }}
                />
                <FormControl fullWidth error={!!fieldErrors.mode}>
                    <InputLabel>Mode</InputLabel>
                    <Select
                        value={form.mode}
                        onChange={(e) => setForm({ ...form, mode: e.target.value })}
                    >
                        <MenuItem value="test">Test</MenuItem>
                        <MenuItem value="live">Live</MenuItem>
                    </Select>
                    {fieldErrors.mode && (
                        <Typography variant="caption" color="error" sx={{ mt: 1, ml: 2 }}>
                            {fieldErrors.mode}
                        </Typography>
                    )}
                </FormControl>
                <FormControlLabel
                    control={
                        <Switch
                            checked={form.active}
                            onChange={(e) => setForm({ ...form, active: e.target.checked })}
                        />
                    }
                    label="Active"
                    sx={{ mt: 2 }}
                />
            </DialogContent>
            <DialogActions>
                <Button onClick={onClose} disabled={loading}>Cancel</Button>
                <Button onClick={handleSave} variant="contained" disabled={loading}>
                    {loading ? <CircularProgress size={20} /> : 'Save'}
                </Button>
            </DialogActions>
        </Dialog>
    );
}