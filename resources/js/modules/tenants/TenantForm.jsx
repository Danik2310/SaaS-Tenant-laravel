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
    const [companyName, setCompanyName] = useState(tenant?.company_name || '');
    const [firstName, setFirstName] = useState(tenant?.first_name || '');
    const [lastName, setLastName] = useState(tenant?.last_name || '');
    const [phone, setPhone] = useState(tenant?.phone || '');
    const [addressLine1, setAddressLine1] = useState(tenant?.address_line1 || '');
    const [addressLine2, setAddressLine2] = useState(tenant?.address_line2 || '');
    const [city, setCity] = useState(tenant?.city || '');
    const [state, setState] = useState(tenant?.state || '');
    const [postalCode, setPostalCode] = useState(tenant?.postal_code || '');
    const [country, setCountry] = useState(tenant?.country || '');
    const [plans, setPlans] = useState([]);
    const [plansLoading, setPlansLoading] = useState(false);
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        setName(tenant?.name || '');
        setEmail(tenant?.email || '');
        setDomain(tenant?.domain || '');
        setPlanId(tenant?.plan?.id || '');
        setCompanyName(tenant?.company_name || '');
        setFirstName(tenant?.first_name || '');
        setLastName(tenant?.last_name || '');
        setPhone(tenant?.phone || '');
        setAddressLine1(tenant?.address_line1 || '');
        setAddressLine2(tenant?.address_line2 || '');
        setCity(tenant?.city || '');
        setState(tenant?.state || '');
        setPostalCode(tenant?.postal_code || '');
        setCountry(tenant?.country || '');
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
            const payload = {
                name,
                email,
                company_name: companyName || null,
                first_name: firstName || null,
                last_name: lastName || null,
                phone: phone || null,
                address_line1: addressLine1 || null,
                address_line2: addressLine2 || null,
                city: city || null,
                state: state || null,
                postal_code: postalCode || null,
                country: country || null,
            };
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
                setCompanyName('');
                setFirstName('');
                setLastName('');
                setPhone('');
                setAddressLine1('');
                setAddressLine2('');
                setCity('');
                setState('');
                setPostalCode('');
                setCountry('');
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

                <div style={{ margin: '20px 0 16px', padding: '14px 16px', background: '#f8fafc', borderRadius: '6px', border: '1px solid #e2e8f0' }}>
                    <strong style={{ color: '#334155', fontSize: '14px', display: 'block', marginBottom: '4px' }}>Business & Contact Information</strong>
                    <p style={{ margin: '0', color: '#94a3b8', fontSize: '12px' }}>Optional details about the tenant company and primary contact.</p>
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0 16px' }}>
                    <FormInput label="Company Name" hint="Legal or trading name of the company">
                        <input type="text" value={companyName} onChange={(e) => setCompanyName(e.target.value)} placeholder="e.g., Acme Corp LLC" />
                    </FormInput>
                    <FormInput label="Phone" hint="Primary contact phone number">
                        <input type="text" value={phone} onChange={(e) => setPhone(e.target.value)} placeholder="+1 555 123 4567" />
                    </FormInput>
                    <FormInput label="First Name" hint="Primary contact's first name">
                        <input type="text" value={firstName} onChange={(e) => setFirstName(e.target.value)} placeholder="Jane" />
                    </FormInput>
                    <FormInput label="Last Name" hint="Primary contact's last name">
                        <input type="text" value={lastName} onChange={(e) => setLastName(e.target.value)} placeholder="Doe" />
                    </FormInput>
                </div>

                <FormInput label="Address Line 1" hint="Street address (e.g., 123 Main St)">
                    <input type="text" value={addressLine1} onChange={(e) => setAddressLine1(e.target.value)} placeholder="123 Main St" />
                </FormInput>

                <FormInput label="Address Line 2" hint="Apartment, suite, unit, etc. (optional)">
                    <input type="text" value={addressLine2} onChange={(e) => setAddressLine2(e.target.value)} placeholder="Suite 400" />
                </FormInput>

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '0 16px' }}>
                    <FormInput label="City" hint="City or locality">
                        <input type="text" value={city} onChange={(e) => setCity(e.target.value)} placeholder="Springfield" />
                    </FormInput>
                    <FormInput label="State / Province" hint="State, province, or region">
                        <input type="text" value={state} onChange={(e) => setState(e.target.value)} placeholder="IL" />
                    </FormInput>
                    <FormInput label="Postal Code" hint="ZIP / postal code">
                        <input type="text" value={postalCode} onChange={(e) => setPostalCode(e.target.value)} placeholder="62701" />
                    </FormInput>
                </div>

                <FormInput label="Country" hint="Country of the registered address">
                    <input type="text" value={country} onChange={(e) => setCountry(e.target.value)} placeholder="United States" />
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
