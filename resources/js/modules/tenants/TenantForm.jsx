import React, { useState, useEffect } from 'react';
import { toast } from 'sonner';
import { FormCard, FormInput, ButtonPrimary, ButtonSecondary, FormActions } from '@/Components/FormElements';
import api from '../../services/api';
import { useMoney } from '@/shared/money';

export default function TenantForm({ tenant = null, onSubmit, onCancel }) {
    const { formatMoney } = useMoney();
    const [name, setName] = useState(tenant?.name || '');
    const [email, setEmail] = useState(tenant?.email || '');
    const [domain, setDomain] = useState(tenant?.domain || '');
    const [planId, setPlanId] = useState(tenant?.plan?.id || '');
    const [plans, setPlans] = useState([]);
    const [plansLoading, setPlansLoading] = useState(false);
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        setName(tenant?.name || '');
        setEmail(tenant?.email || '');
        setDomain(tenant?.domain || '');
        setPlanId(tenant?.plan?.id || '');
        setError(null);
    }, [tenant]);

    useEffect(() => {
        fetchPlans();
    }, []);

    const fetchPlans = async () => {
        setPlansLoading(true);
        try {
            const res = await api.get('/admin/api/plans-list');
            setPlans(res.data.plans || []);
        } catch {
            toast.error('Failed to load plans');
        } finally {
            setPlansLoading(false);
        }
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setError(null);

        try {
            const payload = { name, email };
            if (tenant) {
                payload.id = tenant.id;
                if (planId) {
                    payload.plan_id = Number(planId);
                }
            } else {
                payload.domain = domain;
                if (planId) {
                    const selectedPlan = plans.find(p => p.id === Number(planId));
                    if (selectedPlan) {
                        payload.plan = selectedPlan.slug;
                    }
                }
            }
            await onSubmit(payload);
            if (!tenant) {
                setName('');
                setEmail('');
                setDomain('');
                setPlanId('');
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
                <div style={{ background: '#fef2f2', color: '#dc2626', padding: '12px 16px', borderRadius: '6px', marginBottom: '16px', border: '1px solid #fecaca', fontSize: '13px' }}>
                    {error}
                </div>
            )}

            <form onSubmit={handleSubmit}>
                <FormInput label="Tenant Name" required>
                    <input
                        type="text"
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        placeholder="e.g., Acme Corp"
                        required
                    />
                </FormInput>

                <FormInput label="Email" required hint="Primary contact email for this tenant">
                    <input
                        type="email"
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        placeholder="admin@tenant.com"
                        required
                    />
                </FormInput>

                {!tenant && (
                    <FormInput label="Domain" required hint="Unique domain for tenant access, e.g., acme.localhost">
                        <input
                            type="text"
                            value={domain}
                            onChange={(e) => setDomain(e.target.value)}
                            placeholder="acme.localhost"
                            required
                        />
                    </FormInput>
                )}

                {tenant && (
                    <div style={{ marginBottom: '16px', padding: '12px', background: '#f8fafc', borderRadius: '6px', fontSize: '13px', border: '1px solid #f1f5f9' }}>
                        <strong style={{ color: '#334155' }}>Domain:</strong>{' '}
                        <code style={{ color: '#3b82f6', fontFamily: 'monospace' }}>{tenant.domain}</code>
                        <p style={{ margin: '4px 0 0', color: '#94a3b8', fontSize: '12px' }}>
                            Domain cannot be changed after creation.
                        </p>
                    </div>
                )}

                <FormInput label="Plan" hint={tenant ? "Change the tenant's subscription plan" : "Assign a plan to this tenant (optional)"}>
                    <select
                        value={planId}
                        onChange={(e) => setPlanId(e.target.value)}
                        disabled={plansLoading}
                        style={{
                            width: '100%',
                            padding: '10px 12px',
                            border: '1px solid #d1d5db',
                            borderRadius: '6px',
                            fontSize: '14px',
                            color: '#0f172a',
                            background: plansLoading ? '#f1f5f9' : '#ffffff',
                            cursor: plansLoading ? 'not-allowed' : 'pointer',
                            outline: 'none',
                            boxSizing: 'border-box',
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
