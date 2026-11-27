import React, { useState, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import axios from 'axios';
import { toast } from 'sonner';
import { FormCard, FormInput, ButtonPrimary, ButtonSecondary, FormActions } from '@/Components/AdminFormElements';
import api from '../../services/api';
import { useMoney } from '@/shared/money';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { useTheme } from '@mui/material/styles';

const defaultsFrom = (tenant) => ({
    name: tenant?.name || '',
    email: tenant?.email || '',
    domain: tenant?.domain || '',
    plan_id: tenant?.plan?.id != null ? String(tenant.plan.id) : '',
    company_name: tenant?.company_name || '',
    first_name: tenant?.first_name || '',
    last_name: tenant?.last_name || '',
    phone: tenant?.phone || '',
    address_line1: tenant?.address_line1 || '',
    address_line2: tenant?.address_line2 || '',
    city: tenant?.city || '',
    state: tenant?.state || '',
    postal_code: tenant?.postal_code || '',
    country: tenant?.country || '',
});

export default function TenantForm({ tenant = null, onSubmit, onCancel }) {
    const theme = useTheme();
    const { formatMoney } = useMoney();
    const { data, setData, reset } = useForm(defaultsFrom(tenant));
    const [plans, setPlans] = useState([]);
    const [plansLoading, setPlansLoading] = useState(false);
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        const controller = new AbortController();

        const fetchPlans = async () => {
            setPlansLoading(true);
            try {
                const res = await api.get('/admin/api/plans-list', { signal: controller.signal });
                if (controller.signal.aborted) return;
                setPlans(res.data.plans || []);
            } catch (err) {
                // An abort means the form is gone; it is not a failure.
                if (controller.signal.aborted || axios.isCancel(err)) return;
                toast.error('Failed to load plans');
            } finally {
                // An abort means this request no longer owns the loading state.
                if (!controller.signal.aborted) setPlansLoading(false);
            }
        };

        fetchPlans();

        return () => controller.abort();
    }, []);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setError(null);

        try {
            const payload = {
                name: data.name,
                email: data.email,
                company_name: data.company_name || null,
                first_name: data.first_name || null,
                last_name: data.last_name || null,
                phone: data.phone || null,
                address_line1: data.address_line1 || null,
                address_line2: data.address_line2 || null,
                city: data.city || null,
                state: data.state || null,
                postal_code: data.postal_code || null,
                country: data.country || null,
            };
            if (tenant) {
                payload.id = tenant.id;
                if (data.plan_id) {
                    payload.plan_id = Number(data.plan_id);
                }
            } else {
                payload.domain = data.domain;
                if (data.plan_id) {
                    const selectedPlan = plans.find(p => p.id === Number(data.plan_id));
                    if (selectedPlan) {
                        payload.plan = selectedPlan.slug;
                    }
                }
            }
            await onSubmit(payload);
            if (!tenant) {
                reset();
            }
        } catch (err) {
            const message = err.message || (tenant ? 'Failed to update tenant' : 'Failed to create tenant');
            toast.error(message);
            setError(message);
        } finally {
            setLoading(false);
        }
    };

    return (
        <FormCard
            title={tenant ? 'Edit Tenant' : 'Create New Tenant'}
            subtitle={tenant ? `ID: ${tenant.id}` : 'Add a new tenant to the platform'}
            onClose={onCancel}
        >
            {error && (
                <Alert
                    severity="error"
                    sx={{
                        mb: 2,
                        borderRadius: 2,
                        fontSize: 13,
                        bgcolor: 'background.default',
                        color: 'error.main',
                        border: '1px solid',
                        borderColor: 'error.main',
                    }}
                >
                    {error}
                </Alert>
            )}

            <form onSubmit={handleSubmit}>
                <FormInput label="Tenant Name" required>
                    <input
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        placeholder="e.g., Acme Corp"
                        required
                    />
                </FormInput>

                <FormInput label="Email" required hint="Primary contact email for this tenant">
                    <input
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        placeholder="admin@tenant.com"
                        required
                    />
                </FormInput>

                {!tenant && (
                    <FormInput label="Domain" required hint="Unique domain for tenant access, e.g., acme.sasapp">
                        <input
                            type="text"
                            value={data.domain}
                            onChange={(e) => setData('domain', e.target.value)}
                            placeholder="acme.sasapp"
                            required
                        />
                    </FormInput>
                )}

                {tenant && (
                    <Box sx={{ mb: 2, p: 1.5, bgcolor: 'background.default', border: '1px solid', borderColor: 'divider', borderRadius: 2, fontSize: 13 }}>
                        <Box component="strong" sx={{ color: 'text.primary' }}>Domain:</Box>{' '}
                        <Box component="code" sx={{ color: 'primary.main', fontFamily: 'monospace' }}>{tenant.domain}</Box>
                        <Typography variant="caption" sx={{ display: 'block', mt: 0.5, color: 'text.disabled' }}>
                            Domain cannot be changed after creation.
                        </Typography>
                    </Box>
                )}

                <FormInput label="Plan" hint={tenant ? "Change the tenant's subscription plan" : 'Assign a plan to this tenant (optional)'}>
                    <select
                        value={data.plan_id}
                        onChange={(e) => setData('plan_id', e.target.value)}
                        disabled={plansLoading}
                        style={{
                            width: '100%',
                            padding: '10px 12px',
                            border: `1px solid ${theme.palette.divider}`,
                            borderRadius: '8px',
                            fontSize: '14px',
                            color: theme.palette.text.primary,
                            background: plansLoading ? theme.palette.action.hover : theme.palette.background.paper,
                            cursor: plansLoading ? 'not-allowed' : 'pointer',
                            outline: 'none',
                            boxSizing: 'border-box',
                            transition: 'border-color 150ms ease-out, box-shadow 150ms ease-out',
                        }}
                    >
                        {plansLoading ? (
                            <option value="">Loading plans...</option>
                        ) : (
                            <>
                                <option value="">No Plan</option>
                                {plans.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name} {p.price > 0 ? `(${formatMoney(p.price)}/mo)` : '(Free)'}
                                    </option>
                                ))}
                            </>
                        )}
                    </select>
                </FormInput>

                <Box sx={{ my: 2.5, p: 1.75, bgcolor: 'background.default', border: '1px solid', borderColor: 'divider', borderRadius: 2 }}>
                    <Typography variant="subtitle2" sx={{ color: 'text.primary', fontSize: 14, mb: 0.5 }}>Business & Contact Information</Typography>
                    <Typography variant="caption" sx={{ color: 'text.disabled' }}>Optional details about the tenant company and primary contact.</Typography>
                </Box>

                <Box sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr', sm: '1fr 1fr' }, gap: 2 }}>
                    <FormInput label="Company Name" hint="Legal or trading name of the company">
                        <input type="text" value={data.company_name} onChange={(e) => setData('company_name', e.target.value)} placeholder="e.g., Acme Corp LLC" />
                    </FormInput>
                    <FormInput label="Phone" hint="Primary contact phone number">
                        <input type="text" value={data.phone} onChange={(e) => setData('phone', e.target.value)} placeholder="+1 555 123 4567" />
                    </FormInput>
                    <FormInput label="First Name" hint="Primary contact's first name">
                        <input type="text" value={data.first_name} onChange={(e) => setData('first_name', e.target.value)} placeholder="Jane" />
                    </FormInput>
                    <FormInput label="Last Name" hint="Primary contact's last name">
                        <input type="text" value={data.last_name} onChange={(e) => setData('last_name', e.target.value)} placeholder="Doe" />
                    </FormInput>
                </Box>

                <FormInput label="Address Line 1" hint="Street address (e.g., 123 Main St)">
                    <input type="text" value={data.address_line1} onChange={(e) => setData('address_line1', e.target.value)} placeholder="123 Main St" />
                </FormInput>

                <FormInput label="Address Line 2" hint="Apartment, suite, unit, etc. (optional)">
                    <input type="text" value={data.address_line2} onChange={(e) => setData('address_line2', e.target.value)} placeholder="Suite 400" />
                </FormInput>

                <Box sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr', sm: 'repeat(3, 1fr)' }, gap: 2 }}>
                    <FormInput label="City" hint="City or locality">
                        <input type="text" value={data.city} onChange={(e) => setData('city', e.target.value)} placeholder="Springfield" />
                    </FormInput>
                    <FormInput label="State / Province" hint="State, province, or region">
                        <input type="text" value={data.state} onChange={(e) => setData('state', e.target.value)} placeholder="IL" />
                    </FormInput>
                    <FormInput label="Postal Code" hint="ZIP / postal code">
                        <input type="text" value={data.postal_code} onChange={(e) => setData('postal_code', e.target.value)} placeholder="62701" />
                    </FormInput>
                </Box>

                <FormInput label="Country" hint="Country of the registered address">
                    <input type="text" value={data.country} onChange={(e) => setData('country', e.target.value)} placeholder="United States" />
                </FormInput>

                <FormActions>
                    <ButtonSecondary onClick={onCancel}>Cancel</ButtonSecondary>
                    <ButtonPrimary type="submit" disabled={loading}>
                        {loading ? (tenant ? 'Updating...' : 'Creating...') : tenant ? 'Update Tenant' : 'Create Tenant'}
                    </ButtonPrimary>
                </FormActions>
            </form>
        </FormCard>
    );
}
