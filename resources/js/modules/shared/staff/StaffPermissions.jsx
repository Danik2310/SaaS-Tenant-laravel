import React, { useState, useEffect } from 'react';
import { toast } from 'sonner';
import api from '../../../services/api';

export default function StaffPermissions({ staffId, staffName, onClose, onUpdate, embedded = false }) {
    const [staff, setStaff] = useState(null);
    const [loading, setLoading] = useState(true);
    const [updating, setUpdating] = useState(false);
    const [selectedRoles, setSelectedRoles] = useState([]);
    const [availableRoles, setAvailableRoles] = useState([]);
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(null);

    useEffect(() => {
        fetchStaffDetails();
    }, [staffId]);

    const fetchStaffDetails = async () => {
        try {
            const response = await api.get(`/admin/api/staff/${staffId}`);
            setStaff(response.data.staff);
            setAvailableRoles(response.data.available_roles);
            const availableRoleList = response.data.available_roles || [];
            const currentRoleNames = response.data.staff.roles || [];
            setSelectedRoles(
                availableRoleList
                    .filter(r => currentRoleNames.includes(r.name))
                    .map(r => r.id)
            );
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
            background: embedded ? 'transparent' : '#f8f9fa',
            padding: embedded ? '0' : '30px',
            borderRadius: '8px',
        }}>
            {/* Header */}
            {!embedded && (
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
                        Manage Roles for {staffName}
                    </h3>
                    <p style={{ margin: 0, color: '#666', fontSize: '14px' }}>
                        Email: {staff.email}
                    </p>
                </div>
            )}

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

            {/* Roles */}
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
        </div>
    );
}
