import React, { useState, useEffect } from 'react';
import api from '../../../services/api';
import { FormCard, FormInput, ButtonPrimary, ButtonSecondary, FormActions, CheckboxInput, RadioInput } from '@/Components/AdminFormElements';
import ConfirmDialog from '@/Components/ConfirmDialog';
import { toast } from 'sonner';
import { useTheme } from '@mui/material/styles';

const PASSWORD_RULES = [
    { key: 'length', label: 'At least 8 characters', test: (p) => p.length >= 8 },
    { key: 'uppercase', label: 'At least one uppercase letter (A-Z)', test: (p) => /[A-Z]/.test(p) },
    { key: 'lowercase', label: 'At least one lowercase letter (a-z)', test: (p) => /[a-z]/.test(p) },
    { key: 'number', label: 'At least one number (0-9)', test: (p) => /\d/.test(p) },
    { key: 'symbol', label: 'At least one symbol (!@#$%...)', test: (p) => /[^A-Za-z0-9]/.test(p) },
];

const STRENGTH_LEVELS = [
    { max: 1, tone: 'error' },
    { max: 3, tone: 'warning' },
    { max: 4, tone: 'successLight' },
    { max: 5, tone: 'success' },
];

const getPasswordStrength = (password, theme) => {
    const checks = PASSWORD_RULES.map((rule) => ({ ...rule, met: rule.test(password) }));
    const score = checks.filter((c) => c.met).length;
    const level = STRENGTH_LEVELS.find((s) => score <= s.max) || STRENGTH_LEVELS[STRENGTH_LEVELS.length - 1];
    const colors = {
        error: theme.palette.error.main,
        warning: theme.palette.warning.main,
        successLight: theme.palette.success.light,
        success: theme.palette.success.main,
    };

    return {
        checks,
        score,
        percent: (score / PASSWORD_RULES.length) * 100,
        color: colors[level.tone],
    };
};

function PasswordStrengthMeter({ password }) {
    const theme = useTheme();
    const { checks, percent, color } = getPasswordStrength(password, theme);

    return (
        <div style={{ marginTop: '6px' }}>
            <div
                role="progressbar"
                aria-label="Password strength"
                aria-valuemin={0}
                aria-valuemax={PASSWORD_RULES.length}
                aria-valuenow={checks.filter((c) => c.met).length}
                style={{ height: '6px', borderRadius: '9999px', background: theme.palette.divider, overflow: 'hidden' }}
            >
                <div
                    style={{
                        height: '100%',
                        width: `${percent}%`,
                        background: color,
                        borderRadius: '9999px',
                        transition: 'background-color 150ms ease-out',
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
                            color: check.met ? theme.palette.success.main : theme.palette.text.disabled,
                            transition: 'color 150ms ease-out',
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

export default function StaffForm({ staff = null, onSubmit, onCancel, embedded = false, isSelf = false, rolesLocked = false, rolesLockedHint = '' }) {
    const theme = useTheme();
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
    const [rolesTouched, setRolesTouched] = useState(false);
    const [initialRoleIds, setInitialRoleIds] = useState([]);
    const [showReloginWarning, setShowReloginWarning] = useState(false);

    useEffect(() => {
        setRolesTouched(false);
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
        const initialIds = roles
            .filter((role) => staffRoleNames.includes(role.name))
            .map((role) => role.id);
        setInitialRoleIds(initialIds);
        if (staffRoleNames.length > 0) {
            setFormData((prev) => ({
                ...prev,
                roles: initialIds,
            }));
        }
    }, [roles]); // eslint-disable-line react-hooks/exhaustive-deps

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
        if (rolesLocked) return;
        setRolesTouched(true);
        setFormData((prev) => ({
            ...prev,
            roles: prev.roles.includes(roleId) ? [] : [roleId],
        }));
    };

    const rolesActuallyChanged = () => {
        if (!staff) return false;
        const current = [...formData.roles].sort();
        const original = [...initialRoleIds].sort();
        return JSON.stringify(current) !== JSON.stringify(original);
    };

    const handleSubmit = (e) => {
        e.preventDefault();

        if (staff && isSelf && !rolesLocked && rolesTouched && rolesActuallyChanged()) {
            setShowReloginWarning(true);
            return;
        }

        performSubmit();
    };

    const performSubmit = async () => {
        setLoading(true);
        setErrors({});

        const submitData = {
            name: formData.name,
            email: formData.email,
            is_active: formData.is_active,
        };

        if (rolesTouched) {
            submitData.roles = formData.roles;
        }

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

    const handleConfirmRelogin = () => {
        setShowReloginWarning(false);
        performSubmit();
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

            <div style={{ marginBottom: '16px' }}>
                <label style={{ display: 'block', marginBottom: '6px', fontWeight: 600, fontSize: '13px', color: theme.palette.text.secondary }}>
                    Role
                </label>
                {errors?.roles?.[0] ? (
                    <p style={{ margin: '4px 0 0', fontSize: '12px', color: theme.palette.error.main }}>{errors.roles[0]}</p>
                ) : null}
                <div style={{ border: `1px solid ${theme.palette.divider}`, borderRadius: '8px', padding: '12px', background: theme.palette.background.default, maxHeight: '200px', overflowY: 'auto' }}>
                    {rolesLoading ? (
                        <p style={{ color: theme.palette.text.disabled, fontSize: '13px', margin: 0 }}>Loading roles...</p>
                    ) : roles.length === 0 ? (
                        <p style={{ color: theme.palette.text.disabled, fontSize: '13px', margin: 0 }}>No roles available</p>
                    ) : (
                        roles.map((role) => (
                            <RadioInput
                                key={role.id}
                                label={`${role.name} — ${role.description || 'No description'}`}
                                checked={formData.roles.includes(role.id)}
                                onChange={() => handleRoleChange(role.id)}
                                disabled={rolesLocked}
                            />
                        ))
                    )}
                </div>
                {rolesLocked && rolesLockedHint ? (
                    <p style={{ margin: '6px 0 0', fontSize: '12px', color: theme.palette.warning.main }}>{rolesLockedHint}</p>
                ) : (
                    <p style={{ margin: '6px 0 0', fontSize: '12px', color: theme.palette.text.disabled }}>
                        Select one role. Click the selected role again to remove it.
                    </p>
                )}
            </div>

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

    const reloginDialog = (
        <ConfirmDialog
            open={showReloginWarning}
            title="Change your own role"
            message="Your session will close if you save this change. You will need to sign in again for your new role to take effect."
            confirmLabel="Continue"
            cancelLabel="Cancel"
            onConfirm={handleConfirmRelogin}
            onCancel={() => setShowReloginWarning(false)}
        />
    );

    if (embedded) {
        return (
            <>
                {formContent}
                {reloginDialog}
            </>
        );
    }

    return (
        <>
            <FormCard
                title={staff ? 'Edit Staff Member' : 'Create Staff Member'}
                subtitle={staff ? 'Update staff member details and permissions' : 'Add a new administrator to the platform'}
                onClose={onCancel}
            >
                {formContent}
            </FormCard>
            {reloginDialog}
        </>
    );
}