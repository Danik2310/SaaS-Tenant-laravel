import React, { useEffect, useState } from 'react';
import { toast } from 'sonner';
import api from '../../../services/api';
import StaffForm from './StaffForm';

export default function StaffList() {
    const [staff, setStaff] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showForm, setShowForm] = useState(false);
    const [editingStaff, setEditingStaff] = useState(null);
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(null);

    useEffect(() => {
        fetchStaff();
    }, []);

    const fetchStaff = async () => {
        setLoading(true);
        try {
            const response = await api.get('/admin/api/staff');
            setStaff(response.data.staff);
            setError(null);
        } catch (err) {
            const message = 'Failed to fetch staff';
            toast.error(message);
            setError(message);
            console.error(err);
        } finally {
            setLoading(false);
        }
    };

    const handleCreateStaff = async (data) => {
        try {
            await api.post('/admin/api/staff', data);
            const message = 'Staff member created successfully';
            toast.success(message);
            setShowForm(false);
            setSuccess(message);
            fetchStaff();
            setTimeout(() => setSuccess(null), 3000);
        } catch (err) {
            const message = err.response?.data?.message || 'Failed to create staff';
            toast.error(message);
            setError(message);
        }
    };

    const handleUpdateStaff = async (data) => {
        try {
            await api.put(`/admin/api/staff/${editingStaff.id}`, data);
            const message = 'Staff member updated successfully';
            toast.success(message);
            setEditingStaff(null);
            setSuccess(message);
            fetchStaff();
            setTimeout(() => setSuccess(null), 3000);
        } catch (err) {
            const message = err.response?.data?.message || 'Failed to update staff';
            toast.error(message);
            setError(message);
        }
    };

    const handleDeleteStaff = async (id, name) => {
        if (!window.confirm(`Are you sure you want to delete ${name}? This action can be undone.`)) {
            return;
        }
        try {
            await api.delete(`/admin/api/staff/${id}`);
            const message = 'Staff member deleted successfully';
            toast.success(message);
            setSuccess(message);
            fetchStaff();
            setTimeout(() => setSuccess(null), 3000);
        } catch (err) {
            const message = 'Failed to delete staff';
            toast.error(message);
            setError(message);
        }
    };

    const handleToggleStatus = async (id, name, currentStatus) => {
        try {
            await api.patch(`/admin/api/staff/${id}/toggle-status`);
            const message = `Staff member ${currentStatus ? 'deactivated' : 'activated'} successfully`;
            toast.success(message);
            setSuccess(message);
            fetchStaff();
            setTimeout(() => setSuccess(null), 3000);
        } catch (err) {
            const message = err.response?.data?.message || 'Failed to toggle status';
            toast.error(message);
            setError(message);
        }
    };

    const handleEditClick = async (member) => {
        try {
            // Fetch complete staff details including role objects with IDs
            const response = await api.get(`/admin/api/staff/${member.id}`);
            setEditingStaff(response.data.staff); // Extract the staff object from response
        } catch (err) {
            const message = 'Failed to load staff details';
            toast.error(message);
            setError(message);
        }
    };

    const handleRestoreStaff = async (id, name) => {
        try {
            await api.patch(`/admin/api/staff/${id}/restore`);
            const message = 'Staff member restored successfully';
            toast.success(message);
            setSuccess(message);
            fetchStaff();
            setTimeout(() => setSuccess(null), 3000);
        } catch (err) {
            const message = 'Failed to restore staff';
            toast.error(message);
            setError(message);
        }
    };

    return (
        <div style={{ background: 'white', borderRadius: '8px', overflow: 'hidden' }}>
            {/* Header */}
            <div style={{
                padding: '20px',
                background: '#f8f9fa',
                borderBottom: '1px solid #dee2e6',
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center',
            }}>
                <h3 style={{ margin: 0, fontSize: '18px', fontWeight: '600' }}>
                    Staff Management
                </h3>
                {!showForm && !editingStaff && (
                    <button
                        onClick={() => setShowForm(true)}
                        style={{
                            padding: '10px 20px',
                            background: '#27ae60',
                            color: 'white',
                            border: 'none',
                            borderRadius: '5px',
                            cursor: 'pointer',
                            fontSize: '14px',
                        }}
                    >
                        + Add Staff Member
                    </button>
                )}
            </div>

            {/* Messages */}
            {error && (
                <div style={{
                    padding: '15px 20px',
                    background: '#fee',
                    color: '#c33',
                    borderBottom: '1px solid #fcc',
                    margin: 0,
                }}>
                    {error}
                </div>
            )}
            {success && (
                <div style={{
                    padding: '15px 20px',
                    background: '#efe',
                    color: '#3c3',
                    borderBottom: '1px solid #cfc',
                    margin: 0,
                }}>
                    ✓ {success}
                </div>
            )}

            {/* Content */}
            <div style={{ padding: '20px' }}>
                {showForm ? (
                    <StaffForm
                        onSubmit={handleCreateStaff}
                        onCancel={() => setShowForm(false)}
                    />
                ) : editingStaff ? (
                    <StaffForm
                        staff={editingStaff}
                        onSubmit={handleUpdateStaff}
                        onCancel={() => setEditingStaff(null)}
                    />
                ) : loading ? (
                    <div style={{ textAlign: 'center', padding: '40px', color: '#666' }}>
                        Loading staff...
                    </div>
                ) : staff.length === 0 ? (
                    <div style={{ textAlign: 'center', padding: '40px', color: '#999' }}>
                        No staff members found. Create one to get started.
                    </div>
                ) : (
                    <table style={{
                        width: '100%',
                        borderCollapse: 'collapse',
                    }}>
                        <thead>
                            <tr style={{ borderBottom: '2px solid #dee2e6', background: '#f8f9fa' }}>
                                <th style={{ padding: '12px', textAlign: 'left', fontWeight: '600' }}>Name</th>
                                <th style={{ padding: '12px', textAlign: 'left', fontWeight: '600' }}>Email</th>
                                <th style={{ padding: '12px', textAlign: 'left', fontWeight: '600' }}>Roles</th>
                                <th style={{ padding: '12px', textAlign: 'left', fontWeight: '600' }}>Permission(s)</th>
                                <th style={{ padding: '12px', textAlign: 'left', fontWeight: '600' }}>Status</th>
                                <th style={{ padding: '12px', textAlign: 'left', fontWeight: '600' }}>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {staff.map((member, index) => (
                                <tr
                                    key={member.id}
                                    style={{
                                        borderBottom: '1px solid #dee2e6',
                                        background: index % 2 === 0 ? '#fff' : '#f9f9f9',
                                    }}
                                >
                                    <td style={{ padding: '12px' }}>
                                        <strong>{member.name}</strong>
                                    </td>
                                    <td style={{ padding: '12px' }}>{member.email}</td>
                                    <td style={{ padding: '12px' }}>
                                        {member.roles.length === 0 ? (
                                            <span style={{ color: '#999', fontSize: '14px' }}>No roles</span>
                                        ) : (
                                            <span style={{ fontSize: '13px' }}>
                                                {member.roles.join(', ')}
                                            </span>
                                        )}
                                    </td>
                                    <td style={{ padding: '12px' }}>
                                        {member.permissions && member.permissions.length > 0 ? (
                                            <span style={{ fontSize: '13px' }}>
                                                {member.permissions.join(', ')}
                                            </span>
                                        ) : (
                                            <span style={{ color: '#999', fontSize: '14px' }}>None</span>
                                        )}
                                    </td>
                                    <td style={{ padding: '12px' }}>
                                        <span style={{
                                            display: 'inline-block',
                                            padding: '4px 8px',
                                            borderRadius: '4px',
                                            fontSize: '12px',
                                            fontWeight: '600',
                                            background: member.is_active ? '#e8f5e9' : '#ffebee',
                                            color: member.is_active ? '#2e7d32' : '#c62828',
                                        }}>
                                            {member.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </td>
                                    <td style={{ padding: '12px', whiteSpace: 'nowrap' }}>
                                        <button
                                            onClick={() => handleEditClick(member)}
                                            style={{
                                                padding: '6px 12px',
                                                background: '#f39c12',
                                                color: 'white',
                                                border: 'none',
                                                borderRadius: '4px',
                                                cursor: 'pointer',
                                                fontSize: '12px',
                                                marginRight: '8px',
                                            }}
                                        >
                                            Edit
                                        </button>
                                        <button
                                            onClick={() => handleToggleStatus(member.id, member.name, member.is_active)}
                                            style={{
                                                padding: '6px 12px',
                                                background: member.is_active ? '#e74c3c' : '#27ae60',
                                                color: 'white',
                                                border: 'none',
                                                borderRadius: '4px',
                                                cursor: 'pointer',
                                                fontSize: '12px',
                                                marginRight: '8px',
                                            }}
                                        >
                                            {member.is_active ? 'Deactivate' : 'Activate'}
                                        </button>
                                        <button
                                            onClick={() => handleDeleteStaff(member.id, member.name)}
                                            style={{
                                                padding: '6px 12px',
                                                background: '#c0392b',
                                                color: 'white',
                                                border: 'none',
                                                borderRadius: '4px',
                                                cursor: 'pointer',
                                                fontSize: '12px',
                                            }}
                                        >
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </div>
    );
}
