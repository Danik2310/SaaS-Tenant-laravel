import React, { useState, useEffect } from 'react';
import api from '../../../services/api';
import { FormCard, FormInput, ButtonPrimary, ButtonSecondary, FormActions, CheckboxInput } from '@/Components/FormElements';
import { toast } from 'sonner';

export default function StaffForm({ staff = null, onSubmit, onCancel }) {
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

    useEffect(() => {
        if (staff) {
            setFormData({
                name: staff.name,
                email: staff.email,
                password: '',
                password_confirmation: '',
                roles: (staff.roles || []).map((role) => (typeof role === 'object' ? role.id : role)),
                is_active: staff.is_active,
            });
        } else {
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

    return (
        <FormCard
            title={staff ? 'Edit Staff Member' : 'Create Staff Member'}
            subtitle={staff ? 'Update staff member details and permissions' : 'Add a new administrator to the platform'}
            onClose={onCancel}
        >
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
        </FormCard>
    );
}
