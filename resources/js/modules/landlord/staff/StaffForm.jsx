import React, { useState, useEffect } from 'react';
import api from '../../../services/api';

export default function StaffForm({ staff = null, onSubmit, onCancel }) {
    const [formData, setFormData] = useState({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        roles: [],
        direct_permissions: [], // Add direct permissions
        is_active: true,
    });
    const [errors, setErrors] = useState({});
    const [roles, setRoles] = useState([]);
    const [permissions, setPermissions] = useState([]); // Add permissions state
    const [loading, setLoading] = useState(false);
    const [rolesLoading, setRolesLoading] = useState(true);
    const [permissionsLoading, setPermissionsLoading] = useState(true); // Add permissions loading

    useEffect(() => {
        if (staff) {
            setFormData({
                name: staff.name,
                email: staff.email,
                password: '',
                password_confirmation: '',
                roles: (staff.roles || []).length > 0
                    ? [(staff.roles || []).map(role => {
                        // Extract ID if role is an object, otherwise use the value as-is
                        if (typeof role === 'object') return role.id;
                        return role;
                    })[0]] // Take first role for single selection
                    : [],
                direct_permissions: (staff.direct_permissions || []).map(perm => {
                    // Extract ID if permission is an object, otherwise use the value as-is
                    if (typeof perm === 'object') return perm.id;
                    return perm;
                }),
                is_active: staff.is_active,
            });
        }
        fetchRoles();
        fetchPermissions(); // Add permissions fetch
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

    const fetchPermissions = async () => {
        try {
            // Get all permissions from the API
            const response = await api.get('/admin/api/staff/get-roles');
            // Extract permissions from roles or get them separately if needed
            const allPermissions = [];
            response.data.roles.forEach(role => {
                if (role.permissions) {
                    role.permissions.forEach(perm => {
                        if (!allPermissions.find(p => p.id === perm.id)) {
                            allPermissions.push({
                                id: perm.id,
                                name: perm.name,
                                description: perm.description || perm.name,
                                module: perm.module || 'General'
                            });
                        }
                    });
                }
            });
            setPermissions(allPermissions);
        } catch (err) {
            console.error('Failed to fetch permissions:', err);
        } finally {
            setPermissionsLoading(false);
        }
    };

    const handleChange = (e) => {
        const { name, value, type, checked } = e.target;
        setFormData(prev => ({
            ...prev,
            [name]: type === 'checkbox' ? checked : value,
        }));
        if (errors[name]) {
            setErrors(prev => {
                const newErrors = { ...prev };
                delete newErrors[name];
                return newErrors;
            });
        }
    };

    const handleRoleChange = (roleId) => {
        setFormData(prev => ({
            ...prev,
            roles: [roleId], // Single selection - replace array with single role ID
        }));
    };

    const handlePermissionChange = (permissionId) => {
        setFormData(prev => {
            const direct_permissions = prev.direct_permissions.includes(permissionId)
                ? prev.direct_permissions.filter(id => id !== permissionId)
                : [...prev.direct_permissions, permissionId];
            return { ...prev, direct_permissions };
        });
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        // Validate that a role is selected
        if (formData.roles.length === 0) {
            setErrors({ roles: ['Please select a role'] });
            setLoading(false);
            return;
        }

        const submitData = {
            name: formData.name,
            email: formData.email,
            roles: formData.roles, // Always array of role IDs (single item now)
            direct_permissions: formData.direct_permissions, // Add direct permissions
            is_active: formData.is_active,
        };

        // Solo incluir contraseña si es creación o si se proporciona
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
        <div style={{
            background: '#f8f9fa',
            padding: '30px',
            borderRadius: '8px',
            maxWidth: '600px',
            margin: '0 auto',
        }}>
            <h3 style={{ marginTop: 0, marginBottom: '20px' }}>
                {staff ? 'Edit Staff Member' : 'Create New Staff Member'}
            </h3>

            <form onSubmit={handleSubmit}>
                {/* Name */}
                <div style={{ marginBottom: '20px' }}>
                    <label style={{
                        display: 'block',
                        marginBottom: '8px',
                        fontWeight: '600',
                        fontSize: '14px',
                    }}>
                        Full Name *
                    </label>
                    <input
                        type="text"
                        name="name"
                        value={formData.name}
                        onChange={handleChange}
                        placeholder="John Doe"
                        required
                        style={{
                            width: '100%',
                            padding: '10px',
                            border: `2px solid ${errors.name ? '#e74c3c' : '#dee2e6'}`,
                            borderRadius: '4px',
                            fontSize: '14px',
                            boxSizing: 'border-box',
                        }}
                    />
                    {errors.name && (
                        <span style={{ color: '#e74c3c', fontSize: '12px', marginTop: '4px', display: 'block' }}>
                            {errors.name[0]}
                        </span>
                    )}
                </div>

                {/* Email */}
                <div style={{ marginBottom: '20px' }}>
                    <label style={{
                        display: 'block',
                        marginBottom: '8px',
                        fontWeight: '600',
                        fontSize: '14px',
                    }}>
                        Email *
                    </label>
                    <input
                        type="email"
                        name="email"
                        value={formData.email}
                        onChange={handleChange}
                        placeholder="john@example.com"
                        required
                        style={{
                            width: '100%',
                            padding: '10px',
                            border: `2px solid ${errors.email ? '#e74c3c' : '#dee2e6'}`,
                            borderRadius: '4px',
                            fontSize: '14px',
                            boxSizing: 'border-box',
                        }}
                    />
                    {errors.email && (
                        <span style={{ color: '#e74c3c', fontSize: '12px', marginTop: '4px', display: 'block' }}>
                            {errors.email[0]}
                        </span>
                    )}
                </div>

                {/* Password */}
                <div style={{ marginBottom: '20px' }}>
                    <label style={{
                        display: 'block',
                        marginBottom: '8px',
                        fontWeight: '600',
                        fontSize: '14px',
                    }}>
                        Password {!staff && '*'}
                    </label>
                    <input
                        type="password"
                        name="password"
                        value={formData.password}
                        onChange={handleChange}
                        placeholder={staff ? 'Leave empty to keep current password' : 'Enter password'}
                        required={!staff}
                        style={{
                            width: '100%',
                            padding: '10px',
                            border: `2px solid ${errors.password ? '#e74c3c' : '#dee2e6'}`,
                            borderRadius: '4px',
                            fontSize: '14px',
                            boxSizing: 'border-box',
                        }}
                    />
                    {errors.password && (
                        <span style={{ color: '#e74c3c', fontSize: '12px', marginTop: '4px', display: 'block' }}>
                            {errors.password[0]}
                        </span>
                    )}
                    <small style={{ display: 'block', marginTop: '8px', color: '#666' }}>
                        Password must be at least 8 characters, include uppercase, lowercase, numbers, and symbols.
                    </small>
                </div>

                {/* Roles */}
                <div style={{ marginBottom: '20px' }}>
                    <label style={{
                        display: 'block',
                        marginBottom: '12px',
                        fontWeight: '600',
                        fontSize: '14px',
                    }}>
                        Select Role *
                    </label>
                    {rolesLoading ? (
                        <p style={{ color: '#666' }}>Loading roles...</p>
                    ) : roles.length === 0 ? (
                        <p style={{ color: '#999' }}>No roles available</p>
                    ) : (
                        <div style={{
                            border: '1px solid #dee2e6',
                            borderRadius: '4px',
                            padding: '12px',
                            background: '#fff',
                            maxHeight: '200px',
                            overflowY: 'auto',
                        }}>
                            {roles.map(role => (
                                <div key={role.id} style={{ marginBottom: '10px', display: 'flex', alignItems: 'flex-start' }}>
                                    <input
                                        type="radio"
                                        id={`role-${role.id}`}
                                        name="selectedRole"
                                        checked={formData.roles.includes(role.id)}
                                        onChange={() => handleRoleChange(role.id)}
                                        style={{ marginRight: '10px', marginTop: '4px', cursor: 'pointer' }}
                                    />
                                    <label htmlFor={`role-${role.id}`} style={{ cursor: 'pointer', flex: 1 }}>
                                        <strong style={{ display: 'block', marginBottom: '4px' }}>{role.name}</strong>
                                        <small style={{ color: '#666', display: 'block' }}>
                                            {role.description}
                                        </small>
                                    </label>
                                </div>
                            ))}
                        </div>
                    )}
                    {errors.roles && (
                        <span style={{ color: '#e74c3c', fontSize: '12px', marginTop: '4px', display: 'block' }}>
                            {errors.roles[0]}
                        </span>
                    )}
                </div>

                {/* Direct Permissions */}
                <div style={{ marginBottom: '20px' }}>
                    <label style={{
                        display: 'block',
                        marginBottom: '12px',
                        fontWeight: '600',
                        fontSize: '14px',
                    }}>
                        Direct Permissions (Optional)
                    </label>
                    {permissionsLoading ? (
                        <p style={{ color: '#666' }}>Loading permissions...</p>
                    ) : permissions.length === 0 ? (
                        <p style={{ color: '#999' }}>No permissions available</p>
                    ) : (
                        <div style={{
                            border: '1px solid #dee2e6',
                            borderRadius: '4px',
                            padding: '12px',
                            background: '#fff',
                            maxHeight: '300px',
                            overflowY: 'auto',
                        }}>
                            {/* Group permissions by module */}
                            {Object.entries(
                                permissions.reduce((groups, perm) => {
                                    const module = perm.module || 'General';
                                    if (!groups[module]) groups[module] = [];
                                    groups[module].push(perm);
                                    return groups;
                                }, {})
                            ).map(([module, modulePerms]) => (
                                <div key={module} style={{ marginBottom: '15px' }}>
                                    <h5 style={{
                                        margin: '0 0 8px 0',
                                        fontSize: '14px',
                                        fontWeight: '600',
                                        color: '#495057',
                                        borderBottom: '1px solid #e9ecef',
                                        paddingBottom: '4px'
                                    }}>
                                        {module}
                                    </h5>
                                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '8px' }}>
                                        {modulePerms.map(permission => (
                                            <label key={permission.id} style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                cursor: 'pointer',
                                                fontSize: '13px',
                                                padding: '4px 0'
                                            }}>
                                                <input
                                                    type="checkbox"
                                                    checked={formData.direct_permissions.includes(permission.id)}
                                                    onChange={() => handlePermissionChange(permission.id)}
                                                    style={{ marginRight: '8px', cursor: 'pointer' }}
                                                />
                                                <span style={{ flex: 1 }}>
                                                    <strong>{permission.name}</strong>
                                                    {permission.description && permission.description !== permission.name && (
                                                        <small style={{ display: 'block', color: '#666', marginTop: '2px' }}>
                                                            {permission.description}
                                                        </small>
                                                    )}
                                                </span>
                                            </label>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                    <small style={{ display: 'block', marginTop: '8px', color: '#666' }}>
                        Direct permissions are additional permissions beyond those granted by the selected role.
                    </small>
                </div>

                {/* Active Status */}
                <div style={{ marginBottom: '20px' }}>
                    <label style={{ display: 'flex', alignItems: 'center', cursor: 'pointer' }}>
                        <input
                            type="checkbox"
                            name="is_active"
                            checked={formData.is_active}
                            onChange={handleChange}
                            style={{ marginRight: '10px', cursor: 'pointer' }}
                        />
                        <span style={{ fontSize: '14px' }}>Active</span>
                    </label>
                </div>

                {/* Buttons */}
                <div style={{
                    display: 'flex',
                    gap: '10px',
                    justifyContent: 'flex-end',
                    paddingTop: '20px',
                    borderTop: '1px solid #dee2e6',
                }}>
                    <button
                        type="button"
                        onClick={onCancel}
                        style={{
                            padding: '10px 20px',
                            background: '#6c757d',
                            color: 'white',
                            border: 'none',
                            borderRadius: '4px',
                            cursor: 'pointer',
                            fontSize: '14px',
                            fontWeight: '600',
                        }}
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        disabled={loading}
                        style={{
                            padding: '10px 20px',
                            background: loading ? '#ccc' : '#27ae60',
                            color: 'white',
                            border: 'none',
                            borderRadius: '4px',
                            cursor: loading ? 'not-allowed' : 'pointer',
                            fontSize: '14px',
                            fontWeight: '600',
                        }}
                    >
                        {loading ? 'Saving...' : (staff ? 'Update' : 'Create')}
                    </button>
                </div>
            </form>
        </div>
    );
}
