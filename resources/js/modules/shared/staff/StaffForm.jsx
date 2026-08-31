import React, { useState, useEffect } from 'react';
import api from '../../../services/api';
import { FormCard, FormInput, ButtonPrimary, ButtonSecondary, FormActions, CheckboxInput } from '@/Components/FormElements';
import { toast } from 'sonner';

const PASSWORD_RULES = [
    { key: 'length', label: 'At least 8 characters', test: (p) => p.length >= 8 },
    { key: 'uppercase', label: 'At least one uppercase letter (A–Z)', test: (p) => /[A-Z]/.test(p) },
    { key: 'lowercase', label: 'At least one lowercase letter (a–z)', test: (p) => /[a-z]/.test(p) },
    { key: 'number', label: 'At least one number (0–9)', test: (p) => /\d/.test(p) },
    { key: 'symbol', label: 'At least one symbol (!@#$%…)', test: (p) => /[^A-Za-z0-9]/.test(p) },
];

const STRENGTH_COLORS = [
    { max: 1, background: '#ef4444' },
    { max: 3, background: '#f59e0b' },
    { max: 4, background: '#84cc16' },
    { max: 5, background: '#22c55e' },
];

const getPasswordStrength = (password) => {
    const checks = PASSWORD_RULES.map((rule) => ({ ...rule, met: rule.test(password) }));
    const score = checks.filter((c) => c.met).length;
    const meter = STRENGTH_COLORS.find((s) => score <= s.max) || STRENGTH_COLORS[STRENGTH_COLORS.length - 1];
    return { checks, score, percent: (score / PASSWORD_RULES.length) * 100, color: meter.background };
};

function PasswordStrengthMeter({ password }) {
    const { checks, percent, color } = getPasswordStrength(password);

    return (
        <div style={{ marginTop: '6px' }}>
            <div
                role="progressbar"
                aria-label="Password strength"
                aria-valuemin={0}
                aria-valuemax={PASSWORD_RULES.length}
                aria-valuenow={checks.filter((c) => c.met).length}
                style={{ height: '6px', borderRadius: '9999px', background: '#e2e8f0', overflow: 'hidden' }}
            >
                <div
                    style={{
                        height: '100%',
                        width: `${percent}%`,
                        background: color,
                        borderRadius: '9999px',
                        transition: 'width 0.2s ease, background-color 0.2s ease',
                    }}
                />
            </div>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '4px 12px', marginTop: '6px' }}>
                {checks.map((check) => (
                    <span
                        key={check.key}
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: '4px',
                            fontSize: '12px',
                            color: check.met ? '#16a34a' : '#94a3b8',
                        }}
                    >
                        <span style={{ fontSize: '11px', lineHeight: 1 }}>{check.met ? '✓' : '○'}</span>
                        {check.label}
                    </span>
                ))}
            </div>
        </div>
    );
}

export default function StaffForm({ staff = null, onSubmit, onCancel, embedded = false }) {
    const [formData, setFormData] = useState({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        roles: [],
        is_active: true,
    });
    const [errors, setErrors] = useState({});
    const [roles, setRoles] = useState([]);
    const [loading, setLoading] = useState(false);
    const [rolesLoading, setRolesLoading] = useState(true);
    const [staffRoleNames, setStaffRoleNames] = useState([]);

    useEffect(() => {
        if (staff) {
            setStaffRoleNames((staff.roles || []).map((role) => (typeof role === 'object' ? role.name : role)));
            setFormData({
                name: staff.name,
                email: staff.email,
                password: '',
                password_confirmation: '',
                roles: [],
                is_active: staff.is_active,
            });
        } else {
            setStaffRoleNames([]);
            setFormData({
                name: '',
                email: '',
                password: '',
                password_confirmation: '',
                roles: [],
                is_active: true,
            });
        }
        fetchRoles();
    }, [staff]);

    useEffect(() => {
        if (staffRoleNames.length > 0) {
            setFormData((prev) => ({
                ...prev,
                roles: roles
                    .filter((role) => staffRoleNames.includes(role.name))
                    .map((role) => role.id),
            }));
        }
    }, [roles]);

    const fetchRoles = async () => {
        try {
            const response = await api.get('/admin/api/staff/get-roles');
            setRoles(response.data.roles);
        } catch (err) {
            console.error('Failed to fetch roles:', err);
        } finally {
            setRolesLoading(false);
        }
    };

    const handleChange = (e) => {
        const { name, value, type, checked } = e.target;
        setFormData((prev) => ({
            ...prev,
            [name]: type === 'checkbox' ? checked : value,
        }));
        if (errors[name]) {
            setErrors((prev) => {
                const newErrors = { ...prev };
                delete newErrors[name];
                return newErrors;
            });
        }
    };

    const handleRoleChange = (roleId) => {
        setFormData((prev) => ({
            ...prev,
            roles: prev.roles.includes(roleId) ? prev.roles.filter((id) => id !== roleId) : [...prev.roles, roleId],
        }));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        const submitData = {
            name: formData.name,
            email: formData.email,
            roles: formData.roles,
            is_active: formData.is_active,
        };

        if (!staff || formData.password) {
            submitData.password = formData.password;
        }

        try {
            await onSubmit(submitData);
        } catch (err) {
            if (err.response?.data?.errors) {
                setErrors(err.response.data.errors);
            }
        } finally {
            setLoading(false);
        }
    };

    const formContent = (
        <form onSubmit={handleSubmit}>
            <FormInput label="Full Name" required error={errors?.name?.[0]}>
                <input
                    type="text"
                    name="name"
                    value={formData.name}
                    onChange={handleChange}
                    placeholder="John Doe"
                    required
                />
            </FormInput>

            <FormInput label="Email" required error={errors?.email?.[0]}>
                <input
                    type="email"
                    name="email"
                    value={formData.email}
                    onChange={handleChange}
                    placeholder="john@example.com"
                    required
                />
            </FormInput>

            <FormInput
                label="Password"
                required={!staff}
                hint={staff ? 'Leave empty to keep current password' : 'Min. 8 characters, mix of upper/lower, numbers, symbols'}
                error={errors?.password?.[0]}
            >
                <input
                    type="password"
                    name="password"
                    value={formData.password}
                    onChange={handleChange}
                    placeholder={staff ? 'Leave empty to keep current password' : 'Enter password'}
                    required={!staff}
                />
            </FormInput>
            <PasswordStrengthMeter password={formData.password} />

            <FormInput label="Roles">
                <div style={{ border: '1px solid #e2e8f0', borderRadius: '6px', padding: '12px', background: '#f8fafc', maxHeight: '200px', overflowY: 'auto' }}>
                    {rolesLoading ? (
                        <p style={{ color: '#94a3b8', fontSize: '13px', margin: 0 }}>Loading roles...</p>
                    ) : roles.length === 0 ? (
                        <p style={{ color: '#94a3b8', fontSize: '13px', margin: 0 }}>No roles available</p>
                    ) : (
                        roles.map((role) => (
                            <CheckboxInput
                                key={role.id}
                                label={`${role.name} — ${role.description || 'No description'}`}
                                checked={formData.roles.includes(role.id)}
                                onChange={() => handleRoleChange(role.id)}
                            />
                        ))
                    )}
                </div>
            </FormInput>

            <div style={{ marginBottom: '16px' }}>
                <CheckboxInput
                    label="Active"
                    checked={formData.is_active}
                    onChange={(e) => setFormData((prev) => ({ ...prev, is_active: e.target.checked }))}
                />
            </div>

            <FormActions>
                <ButtonSecondary onClick={onCancel}>Cancel</ButtonSecondary>
                <ButtonPrimary type="submit" disabled={loading}>
                    {loading ? 'Saving...' : staff ? 'Update' : 'Create'}
                </ButtonPrimary>
            </FormActions>
        </form>
    );

    if (embedded) {
        return formContent;
    }

    return (
        <FormCard
            title={staff ? 'Edit Staff Member' : 'Create Staff Member'}
            subtitle={staff ? 'Update staff member details and permissions' : 'Add a new administrator to the platform'}
            onClose={onCancel}
        >
            {formContent}
        </FormCard>
    );
}
