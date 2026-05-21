import React, { useState, useEffect } from 'react';
import Dialog from '@mui/material/Dialog';
import DialogTitle from '@mui/material/DialogTitle';
import DialogContent from '@mui/material/DialogContent';
import DialogActions from '@mui/material/DialogActions';
import Button from '@mui/material/Button';
import { toast } from 'sonner';
import api from '../../../../services/api';
import { FormInput, ButtonPrimary, ButtonSecondary, FormActions, SelectInput, CheckboxInput } from '@/Components/FormElements';
import VisibilityIcon from '@mui/icons-material/Visibility';
import VisibilityOffIcon from '@mui/icons-material/VisibilityOff';

function ToggleInputWithButton({ type, value, onChange, placeholder, show, onToggle, ariaLabel, style, onFocus, onBlur }) {
    return (
        <div style={{ position: 'relative' }}>
            <input
                type={type}
                value={value}
                onChange={onChange}
                placeholder={placeholder}
                onFocus={onFocus}
                onBlur={onBlur}
                style={{
                    width: '100%',
                    paddingRight: '40px',
                    backgroundColor: 'transparent',
                    border: 'none',
                    outline: 'none',
                    font: 'inherit',
                    ...style,
                }}
            />
            <button
                type="button"
                onClick={onToggle}
                aria-label={ariaLabel}
                style={{
                    position: 'absolute',
                    right: '8px',
                    top: '50%',
                    transform: 'translateY(-50%)',
                    background: 'none',
                    border: 'none',
                    cursor: 'pointer',
                    color: '#64748b',
                    padding: '4px',
                }}
            >
                {show ? <VisibilityOffIcon fontSize="small" /> : <VisibilityIcon fontSize="small" />}
            </button>
        </div>
    );
}

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

    const handleSave = async () => {
        setFieldErrors({});

        const errors = {};

        if (!form.name || form.name.trim().length === 0) {
            errors.name = 'Name is required';
        } else if (form.name.trim().length < 3) {
            errors.name = 'Name must be at least 3 characters';
        }

        if (!form.provider) {
            errors.provider = 'Provider is required';
        }

        if (!form.api_key || form.api_key.trim().length === 0) {
            errors.api_key = 'API Key is required';
        } else if (form.api_key.length < 10) {
            errors.api_key = 'API Key must be at least 10 characters';
        }

        if (!form.secret_key || form.secret_key.trim().length === 0) {
            errors.secret_key = 'Secret Key is required';
        } else if (form.secret_key.length < 10) {
            errors.secret_key = 'Secret Key must be at least 10 characters';
        }

        if (!form.mode || !['test', 'live'].includes(form.mode)) {
            errors.mode = 'Invalid mode selected';
        }

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
            setError(null);
        } catch (err) {
            const errorResponse = err.response?.data;
            if (errorResponse?.errors) {
                setFieldErrors(errorResponse.errors);
                setError('Please fix the validation errors below');
            } else {
                const errorMessage = errorResponse?.message || errorResponse?.error || 'Failed to save payment method.';
                toast.error(errorMessage);
                setError(errorMessage);
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <Dialog open={open} onClose={onClose} maxWidth="sm" fullWidth>
            <DialogTitle sx={{ fontWeight: 700, fontSize: '16px', color: '#0f172a', borderBottom: '1px solid #e2e8f0', pb: 1 }}>
                {editingPayment ? 'Edit Payment Method' : 'Add Payment Method'}
            </DialogTitle>
            <DialogContent sx={{ pt: 2, pb: 1 }}>
                <FormInput label="Name" required error={fieldErrors.name}>
                    <input
                        type="text"
                        value={form.name}
                        onChange={(e) => setForm({ ...form, name: e.target.value })}
                        placeholder="e.g., Stripe Production"
                    />
                </FormInput>

                <FormInput label="Provider" required error={fieldErrors.provider}>
                    <SelectInput
                        value={form.provider}
                        onChange={(e) => setForm({ ...form, provider: e.target.value })}
                    >
                        <option value="">Select a provider...</option>
                        <option value="stripe">Stripe</option>
                        <option value="paypal">PayPal</option>
                        <option value="other">Other</option>
                    </SelectInput>
                </FormInput>

                <FormInput label="API Key" required error={fieldErrors.api_key} hint="Public key (starts with pk_ for Stripe)">
                    <ToggleInputWithButton
                        type={showApiKey ? 'text' : 'password'}
                        value={form.api_key}
                        onChange={(e) => setForm({ ...form, api_key: e.target.value })}
                        placeholder="pk_test_..."
                        show={showApiKey}
                        onToggle={() => setShowApiKey(!showApiKey)}
                        ariaLabel={showApiKey ? 'Ocultar clave API' : 'Mostrar clave API'}
                    />
                </FormInput>

                <FormInput label="Secret Key" required error={fieldErrors.secret_key} hint="Private key (starts with sk_ for Stripe)">
                    <ToggleInputWithButton
                        type={showSecretKey ? 'text' : 'password'}
                        value={form.secret_key}
                        onChange={(e) => setForm({ ...form, secret_key: e.target.value })}
                        placeholder="sk_test_..."
                        show={showSecretKey}
                        onToggle={() => setShowSecretKey(!showSecretKey)}
                        ariaLabel={showSecretKey ? 'Ocultar clave secreta' : 'Mostrar clave secreta'}
                    />
                </FormInput>

                <FormInput label="Mode" required error={fieldErrors.mode}>
                    <SelectInput
                        value={form.mode}
                        onChange={(e) => setForm({ ...form, mode: e.target.value })}
                    >
                        <option value="test">Test (Sandbox)</option>
                        <option value="live">Live (Production)</option>
                    </SelectInput>
                </FormInput>

                <FormInput label="Status">
                    <CheckboxInput
                        label="Active - Enable this payment method for tenants"
                        checked={form.active}
                        onChange={(e) => setForm({ ...form, active: e.target.checked })}
                    />
                </FormInput>
            </DialogContent>
            <DialogActions sx={{ p: 2, borderTop: '1px solid #f1f5f9' }}>
                <ButtonSecondary onClick={onClose} disabled={loading}>
                    Cancel
                </ButtonSecondary>
                <ButtonPrimary onClick={handleSave} disabled={loading}>
                    {loading ? 'Saving...' : (editingPayment ? 'Update' : 'Create')}
                </ButtonPrimary>
            </DialogActions>
        </Dialog>
    );
}
