import React, { useState, useEffect } from 'react';
import { toast } from 'sonner';
import api from '../../../services/api';
import ConfirmDialog from '@/Components/ConfirmDialog';
import { alpha, useTheme } from '@mui/material/styles';

export default function StaffPermissions({ staffId, staffName, onClose, onUpdate, embedded = false }) {
    const theme = useTheme();
    const c = {
        surface: theme.palette.background.default,
        paper: theme.palette.background.paper,
        text: theme.palette.text.primary,
        muted: theme.palette.text.secondary,
        faint: theme.palette.text.disabled,
        divider: theme.palette.divider,
        primary: theme.palette.primary.main,
        success: theme.palette.success.main,
        warning: theme.palette.warning.main,
        error: theme.palette.error.main,
        errorSoft: alpha(theme.palette.error.main, 0.08),
        successSoft: alpha(theme.palette.success.main, 0.08),
        disabled: theme.palette.action.disabledBackground,
        white: theme.palette.common.white,
    };

    const [staff, setStaff] = useState(null);
    const [loading, setLoading] = useState(true);
    const [updating, setUpdating] = useState(false);
    const [selectedRoles, setSelectedRoles] = useState([]);
    const [initialRoleIds, setInitialRoleIds] = useState([]);
    const [availableRoles, setAvailableRoles] = useState([]);
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(null);
    const [isSelf, setIsSelf] = useState(false);
    const [isLastSuperAdmin, setIsLastSuperAdmin] = useState(false);
    const [showReloginWarning, setShowReloginWarning] = useState(false);

    useEffect(() => {
        fetchStaffDetails();
    }, [staffId]);

    const fetchStaffDetails = async () => {
        setLoading(true);
        try {
            const response = await api.get(`/admin/api/staff/${staffId}`);
            setStaff(response.data.staff);
            setAvailableRoles(response.data.available_roles);
            setIsSelf(response.data.is_self === true);
            setIsLastSuperAdmin(response.data.is_last_super_admin === true);
            const availableRoleList = response.data.available_roles || [];
            const currentRoleNames = response.data.staff.roles || [];
            const initialIds = availableRoleList
                .filter(r => currentRoleNames.includes(r.name))
                .map(r => r.id);
            setInitialRoleIds(initialIds);
            setSelectedRoles(initialIds);
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

    const rolesLocked = isLastSuperAdmin || (isSelf && (staff?.roles || []).includes('super-admin'));
    const rolesLockedHint = isSelf && (staff?.roles || []).includes('super-admin')
        ? 'You cannot change the roles of your own Administrator account.'
        : 'This is the last Administrator account; its role cannot be changed.';

    const toggleRole = (roleId) => {
        if (rolesLocked) return;
        setSelectedRoles(prev =>
            prev.includes(roleId)
                ? []
                : [roleId]
        );
    };

    const rolesActuallyChanged = () => {
        const current = [...selectedRoles].sort();
        const original = [...initialRoleIds].sort();
        return JSON.stringify(current) !== JSON.stringify(original);
    };

    const handleSaveRoles = () => {
        if (isSelf && !rolesLocked && rolesActuallyChanged()) {
            setShowReloginWarning(true);
            return;
        }
        performSaveRoles();
    };

    const performSaveRoles = async () => {
        setUpdating(true);
        setError(null);
        try {
            const response = await api.post(`/admin/api/staff/${staffId}/roles`, {
                role_ids: selectedRoles,
            });
            setSuccess('Roles updated successfully');
            toast.success('Roles updated successfully');
            onUpdate();
            setTimeout(() => setSuccess(null), 3000);
            if (response.data.relogin_required) {
                window.location.href = '/central/login';
            }
        } catch (err) {
            const message = err.response?.data?.message || 'Failed to update roles';
            toast.error(message);
            setError(message);
        } finally {
            setUpdating(false);
        }
    };

    const handleConfirmRelogin = () => {
        setShowReloginWarning(false);
        performSaveRoles();
    };

    if (loading) {
        return (
            <div style={{
                background: c.surface,
                padding: '40px',
                borderRadius: '8px',
                textAlign: 'center',
                color: c.muted,
            }}>
                Loading staff permissions...
            </div>
        );
    }

    if (!staff) {
        return (
            <div style={{
                background: c.surface,
                padding: '40px',
                borderRadius: '8px',
                textAlign: 'center',
                color: c.error,
            }}>
                Failed to load staff details
            </div>
        );
    }

    const buttonTransition = 'background-color 150ms ease-out';

    return (
        <div style={{
            background: embedded ? 'transparent' : c.surface,
            padding: embedded ? '0' : '30px',
            borderRadius: '8px',
        }}>
            {/* Header */}
            {!embedded && (
                <div style={{ marginBottom: '20px' }}>
                    <button
                        onClick={onClose}
                        style={{
                            background: c.muted,
                            color: c.white,
                            border: 'none',
                            padding: '8px 16px',
                            borderRadius: '6px',
                            cursor: 'pointer',
                            fontSize: '14px',
                            marginBottom: '15px',
                            transition: buttonTransition,
                        }}
                    >
                        ← Back
                    </button>
                    <h3 style={{ margin: '0 0 10px 0', color: c.text }}>
                        Manage Roles for {staffName}
                    </h3>
                    <p style={{ margin: 0, color: c.muted, fontSize: '14px' }}>
                        Email: {staff.email}
                    </p>
                </div>
            )}

            {/* Messages */}
            {error && (
                <div style={{
                    padding: '12px 16px',
                    background: c.errorSoft,
                    color: c.error,
                    borderRadius: '6px',
                    marginBottom: '15px',
                    fontSize: '14px',
                }}>
                    {error}
                </div>
            )}
            {success && (
                <div style={{
                    padding: '12px 16px',
                    background: c.successSoft,
                    color: c.success,
                    borderRadius: '6px',
                    marginBottom: '15px',
                    fontSize: '14px',
                }}>
                    ✓ {success}
                </div>
            )}

            {/* Roles */}
            <div>
                <div style={{
                    background: c.paper,
                    border: `1px solid ${c.divider}`,
                    borderRadius: '8px',
                    padding: '15px',
                    marginBottom: '20px',
                    maxHeight: '400px',
                    overflowY: 'auto',
                }}>
                    {availableRoles.length === 0 ? (
                        <p style={{ color: c.faint, margin: 0 }}>No roles available</p>
                    ) : (
                        availableRoles.map(role => (
                            <div
                                key={role.id}
                                style={{
                                    padding: '12px',
                                    borderBottom: `1px solid ${c.divider}`,
                                    display: 'flex',
                                    alignItems: 'flex-start',
                                    gap: '12px',
                                    opacity: rolesLocked ? 0.6 : 1,
                                    transition: 'background-color 150ms ease-out',
                                }}
                            >
                                <input
                                    type="radio"
                                    name={`roles-${staffId}`}
                                    id={`role-${role.id}`}
                                    checked={selectedRoles.includes(role.id)}
                                    onChange={() => toggleRole(role.id)}
                                    disabled={rolesLocked}
                                    style={{
                                        cursor: rolesLocked ? 'not-allowed' : 'pointer',
                                        marginTop: '4px',
                                        width: '18px',
                                        height: '18px',
                                        accentColor: c.primary,
                                    }}
                                />
                                <label htmlFor={`role-${role.id}`} style={{ flex: 1, cursor: rolesLocked ? 'not-allowed' : 'pointer' }}>
                                    <div style={{
                                        fontWeight: '600',
                                        marginBottom: '4px',
                                        color: c.text,
                                    }}>
                                        {role.name}
                                    </div>
                                    {role.description && (
                                        <div style={{
                                            fontSize: '13px',
                                            color: c.muted,
                                            marginBottom: '8px',
                                        }}>
                                            {role.description}
                                        </div>
                                    )}
                                    {(role.permissions && role.permissions.length > 0) && (
                                        <div style={{
                                            fontSize: '12px',
                                            color: c.faint,
                                        }}>
                                            Includes {role.permissions.length} permission{role.permissions.length !== 1 ? 's' : ''}
                                        </div>
                                    )}
                                </label>
                            </div>
                        ))
                    )}
                </div>

                {rolesLocked && rolesLockedHint && (
                    <p style={{ margin: '0 0 16px', fontSize: '12px', color: c.warning }}>{rolesLockedHint}</p>
                )}
                {!rolesLocked && (
                    <p style={{ margin: '0 0 16px', fontSize: '12px', color: c.faint }}>
                        Select one role. Click the selected role again to remove it.
                    </p>
                )}

                <div style={{
                    display: 'flex',
                    gap: '10px',
                    justifyContent: 'flex-end',
                }}>
                    <button
                        onClick={onClose}
                        style={{
                            padding: '10px 20px',
                            background: c.muted,
                            color: c.white,
                            border: 'none',
                            borderRadius: '6px',
                            cursor: 'pointer',
                            fontSize: '14px',
                            fontWeight: '600',
                            transition: buttonTransition,
                        }}
                    >
                        Cancel
                    </button>
                    <button
                        onClick={handleSaveRoles}
                        disabled={updating || rolesLocked}
                        style={{
                            padding: '10px 20px',
                            background: updating || rolesLocked ? c.disabled : c.success,
                            color: updating || rolesLocked ? c.faint : c.white,
                            border: 'none',
                            borderRadius: '6px',
                            cursor: updating || rolesLocked ? 'not-allowed' : 'pointer',
                            fontSize: '14px',
                            fontWeight: '600',
                            transition: buttonTransition,
                        }}
                    >
                        {updating ? 'Saving...' : 'Save Roles'}
                    </button>
                </div>
            </div>

            <ConfirmDialog
                open={showReloginWarning}
                title="Change your own role"
                message="Your session will close if you save this change. You will need to sign in again for your new role to take effect."
                confirmLabel="Continue"
                cancelLabel="Cancel"
                onConfirm={handleConfirmRelogin}
                onCancel={() => setShowReloginWarning(false)}
            />
        </div>
    );
}
