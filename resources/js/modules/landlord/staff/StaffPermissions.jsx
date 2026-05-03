import React, { useState, useEffect } from 'react';
import { toast } from 'sonner';
import api from '../../../services/api';

export default function StaffPermissions({ staffId, staffName, onClose, onUpdate }) {
    const [staff, setStaff] = useState(null);
    const [loading, setLoading] = useState(true);
    const [updating, setUpdating] = useState(false);
    const [selectedRoles, setSelectedRoles] = useState([]);
    const [selectedPermissions, setSelectedPermissions] = useState([]);
    const [availableRoles, setAvailableRoles] = useState([]);
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(null);
    const [tab, setTab] = useState('roles');

    useEffect(() => {
        fetchStaffDetails();
    }, [staffId]);

    const fetchStaffDetails = async () => {
        try {
            const response = await api.get(`/admin/api/staff/${staffId}`);
            setStaff(response.data.staff);
            setAvailableRoles(response.data.available_roles);
            setSelectedRoles(response.data.staff.roles.map(r => r.id));
            setSelectedPermissions(response.data.staff.direct_permissions);
            setError(null);
        } catch (err) {
            const message = 'Failed to load staff details';
            toast.error(message);
            setError(message);
            console.error(err);
        } finally {
            setLoading(false);
        }
    };

    const toggleRole = (roleId) => {
        setSelectedRoles(prev =>
            prev.includes(roleId)
                ? prev.filter(id => id !== roleId)
                : [...prev, roleId]
        );
    };

    const togglePermission = (permissionId) => {
        setSelectedPermissions(prev =>
            prev.includes(permissionId)
                ? prev.filter(id => id !== permissionId)
                : [...prev, permissionId]
        );
    };

    const handleSaveRoles = async () => {
        setUpdating(true);
        try {
            await api.post(`/admin/api/staff/${staffId}/roles`, {
                role_ids: selectedRoles,
            });
            const message = 'Roles updated successfully';
            toast.success(message);
            setSuccess(message);
            onUpdate();
            setTimeout(() => setSuccess(null), 3000);
        } catch (err) {
            const message = err.response?.data?.message || 'Failed to update roles';
            toast.error(message);
            setError(message);
        } finally {
            setUpdating(false);
        }
    };

    const handleSavePermissions = async () => {
        setUpdating(true);
        try {
            await api.post(`/admin/api/staff/${staffId}/permissions`, {
                permission_ids: selectedPermissions,
            });
            const message = 'Permissions updated successfully';
            toast.success(message);
            setSuccess(message);
            onUpdate();
            setTimeout(() => setSuccess(null), 3000);
        } catch (err) {
            const message = err.response?.data?.message || 'Failed to update permissions';
            toast.error(message);
            setError(message);
        } finally {
            setUpdating(false);
        }
    };

    if (loading) {
        return (
            <div style={{
                background: '#f8f9fa',
                padding: '40px',
                borderRadius: '8px',
                textAlign: 'center',
                color: '#666',
            }}>
                Loading staff permissions...
            </div>
        );
    }

    if (!staff) {
        return (
            <div style={{
                background: '#f8f9fa',
                padding: '40px',
                borderRadius: '8px',
                textAlign: 'center',
                color: '#c33',
            }}>
                Failed to load staff details
            </div>
        );
    }

    return (
        <div style={{
            background: '#f8f9fa',
            padding: '30px',
            borderRadius: '8px',
        }}>
            {/* Header */}
            <div style={{ marginBottom: '20px' }}>
                <button
                    onClick={onClose}
                    style={{
                        background: '#6c757d',
                        color: 'white',
                        border: 'none',
                        padding: '8px 16px',
                        borderRadius: '4px',
                        cursor: 'pointer',
                        fontSize: '14px',
                        marginBottom: '15px',
                    }}
                >
                    ← Back
                </button>
                <h3 style={{ margin: '0 0 10px 0' }}>
                    Manage Permissions for {staffName}
                </h3>
                <p style={{ margin: 0, color: '#666', fontSize: '14px' }}>
                    Email: {staff.email}
                </p>
            </div>

            {/* Messages */}
            {error && (
                <div style={{
                    padding: '12px 16px',
                    background: '#fee',
                    color: '#c33',
                    borderRadius: '4px',
                    marginBottom: '15px',
                    fontSize: '14px',
                }}>
                    {error}
                </div>
            )}
            {success && (
                <div style={{
                    padding: '12px 16px',
                    background: '#efe',
                    color: '#3c3',
                    borderRadius: '4px',
                    marginBottom: '15px',
                    fontSize: '14px',
                }}>
                    ✓ {success}
                </div>
            )}

            {/* Tabs */}
            <div style={{
                display: 'flex',
                gap: '10px',
                borderBottom: '2px solid #dee2e6',
                marginBottom: '20px',
            }}>
                <button
                    onClick={() => setTab('roles')}
                    style={{
                        padding: '12px 20px',
                        background: tab === 'roles' ? '#2c3e50' : 'transparent',
                        color: tab === 'roles' ? 'white' : '#666',
                        border: 'none',
                        cursor: 'pointer',
                        fontSize: '14px',
                        fontWeight: '600',
                        borderBottom: tab === 'roles' ? '2px solid #27ae60' : 'none',
                        marginBottom: '-2px',
                    }}
                >
                    Roles ({selectedRoles.length})
                </button>
                <button
                    onClick={() => setTab('permissions')}
                    style={{
                        padding: '12px 20px',
                        background: tab === 'permissions' ? '#2c3e50' : 'transparent',
                        color: tab === 'permissions' ? 'white' : '#666',
                        border: 'none',
                        cursor: 'pointer',
                        fontSize: '14px',
                        fontWeight: '600',
                        borderBottom: tab === 'permissions' ? '2px solid #27ae60' : 'none',
                        marginBottom: '-2px',
                    }}
                >
                    Direct Permissions ({selectedPermissions.length})
                </button>
            </div>

            {/* Roles Tab */}
            {tab === 'roles' && (
                <div>
                    <div style={{
                        background: 'white',
                        border: '1px solid #dee2e6',
                        borderRadius: '4px',
                        padding: '15px',
                        marginBottom: '20px',
                        maxHeight: '400px',
                        overflowY: 'auto',
                    }}>
                        {availableRoles.length === 0 ? (
                            <p style={{ color: '#999', margin: 0 }}>No roles available</p>
                        ) : (
                            availableRoles.map(role => (
                                <div
                                    key={role.id}
                                    style={{
                                        padding: '12px',
                                        borderBottom: '1px solid #f0f0f0',
                                        display: 'flex',
                                        alignItems: 'flex-start',
                                        gap: '12px',
                                    }}
                                >
                                    <input
                                        type="checkbox"
                                        id={`role-${role.id}`}
                                        checked={selectedRoles.includes(role.id)}
                                        onChange={() => toggleRole(role.id)}
                                        style={{
                                            cursor: 'pointer',
                                            marginTop: '4px',
                                            width: '18px',
                                            height: '18px',
                                        }}
                                    />
                                    <label htmlFor={`role-${role.id}`} style={{ flex: 1, cursor: 'pointer' }}>
                                        <div style={{
                                            fontWeight: '600',
                                            marginBottom: '4px',
                                            color: '#222',
                                        }}>
                                            {role.name}
                                        </div>
                                        {role.description && (
                                            <div style={{
                                                fontSize: '13px',
                                                color: '#666',
                                                marginBottom: '8px',
                                            }}>
                                                {role.description}
                                            </div>
                                        )}
                                        {role.permissions_count > 0 && (
                                            <div style={{
                                                fontSize: '12px',
                                                color: '#999',
                                            }}>
                                                Includes {role.permissions_count} permission{role.permissions_count !== 1 ? 's' : ''}
                                            </div>
                                        )}
                                    </label>
                                </div>
                            ))
                        )}
                    </div>

                    <div style={{
                        display: 'flex',
                        gap: '10px',
                        justifyContent: 'flex-end',
                    }}>
                        <button
                            onClick={onClose}
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
                            onClick={handleSaveRoles}
                            disabled={updating}
                            style={{
                                padding: '10px 20px',
                                background: updating ? '#ccc' : '#27ae60',
                                color: 'white',
                                border: 'none',
                                borderRadius: '4px',
                                cursor: updating ? 'not-allowed' : 'pointer',
                                fontSize: '14px',
                                fontWeight: '600',
                            }}
                        >
                            {updating ? 'Saving...' : 'Save Roles'}
                        </button>
                    </div>
                </div>
            )}

            {/* Permissions Tab */}
            {tab === 'permissions' && (
                <div>
                    <div style={{
                        background: 'white',
                        border: '1px solid #dee2e6',
                        borderRadius: '4px',
                        padding: '15px',
                        marginBottom: '20px',
                        maxHeight: '400px',
                        overflowY: 'auto',
                    }}>
                        {availableRoles.length === 0 ? (
                            <p style={{ color: '#999', margin: 0 }}>No permissions available</p>
                        ) : (
                            availableRoles.flatMap(role =>
                                role.permissions.map(permission => (
                                    <div
                                        key={permission.id}
                                        style={{
                                            padding: '12px',
                                            borderBottom: '1px solid #f0f0f0',
                                            display: 'flex',
                                            alignItems: 'flex-start',
                                            gap: '12px',
                                        }}
                                    >
                                        <input
                                            type="checkbox"
                                            id={`perm-${permission.id}`}
                                            checked={selectedPermissions.includes(permission.id)}
                                            onChange={() => togglePermission(permission.id)}
                                            style={{
                                                cursor: 'pointer',
                                                marginTop: '4px',
                                                width: '18px',
                                                height: '18px',
                                            }}
                                        />
                                        <label htmlFor={`perm-${permission.id}`} style={{ flex: 1, cursor: 'pointer' }}>
                                            <div style={{
                                                fontWeight: '600',
                                                marginBottom: '4px',
                                                color: '#222',
                                            }}>
                                                {permission.name}
                                            </div>
                                            {permission.description && (
                                                <div style={{
                                                    fontSize: '13px',
                                                    color: '#666',
                                                    marginBottom: '4px',
                                                }}>
                                                    {permission.description}
                                                </div>
                                            )}
                                            {permission.module && (
                                                <div style={{
                                                    fontSize: '12px',
                                                    color: '#999',
                                                    display: 'inline-block',
                                                    background: '#f0f0f0',
                                                    padding: '2px 6px',
                                                    borderRadius: '3px',
                                                }}>
                                                    {permission.module}
                                                </div>
                                            )}
                                        </label>
                                    </div>
                                ))
                            )
                        )}
                    </div>

                    <div style={{
                        display: 'flex',
                        gap: '10px',
                        justifyContent: 'flex-end',
                    }}>
                        <button
                            onClick={onClose}
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
                            onClick={handleSavePermissions}
                            disabled={updating}
                            style={{
                                padding: '10px 20px',
                                background: updating ? '#ccc' : '#27ae60',
                                color: 'white',
                                border: 'none',
                                borderRadius: '4px',
                                cursor: updating ? 'not-allowed' : 'pointer',
                                fontSize: '14px',
                                fontWeight: '600',
                            }}
                        >
                            {updating ? 'Saving...' : 'Save Permissions'}
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
