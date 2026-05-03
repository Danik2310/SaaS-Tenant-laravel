import React, { useEffect, useState } from 'react';
import { toast } from 'sonner';
import api from '../../../services/api';
import StaffForm from './StaffForm';
import DataTable from '@/components/DataTable';
import { Chip, Typography } from '@mui/material';

export default function StaffList() {
    const [staff, setStaff] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showForm, setShowForm] = useState(false);
    const [editingStaff, setEditingStaff] = useState(null);
    const [error, setError] = useState(null);

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
            toast.success('Staff member created successfully');
            setShowForm(false);
            fetchStaff();
        } catch (err) {
            const message = err.response?.data?.message || 'Failed to create staff';
            toast.error(message);
            setError(message);
        }
    };

    const handleUpdateStaff = async (data) => {
        try {
            await api.put(`/admin/api/staff/${editingStaff.id}`, data);
            toast.success('Staff member updated successfully');
            setEditingStaff(null);
            fetchStaff();
        } catch (err) {
            const message = err.response?.data?.message || 'Failed to update staff';
            toast.error(message);
            setError(message);
        }
    };

    const handleDeleteStaff = async (row) => {
        if (!window.confirm(`Are you sure you want to delete ${row.name}? This action can be undone.`)) {
            return;
        }
        try {
            await api.delete(`/admin/api/staff/${row.id}`);
            toast.success('Staff member deleted successfully');
            fetchStaff();
        } catch (err) {
            const message = 'Failed to delete staff';
            toast.error(message);
            setError(message);
        }
    };

    const handleToggleStatus = async (row) => {
        try {
            await api.patch(`/admin/api/staff/${row.id}/toggle-status`);
            toast.success(`Staff member ${row.is_active ? 'deactivated' : 'activated'} successfully`);
            fetchStaff();
        } catch (err) {
            const message = err.response?.data?.message || 'Failed to toggle status';
            toast.error(message);
            setError(message);
        }
    };

    const handleEditClick = async (row) => {
        try {
            const response = await api.get(`/admin/api/staff/${row.id}`);
            setEditingStaff(response.data.staff);
        } catch (err) {
            const message = 'Failed to load staff details';
            toast.error(message);
            setError(message);
        }
    };

    if (showForm || editingStaff) {
        return (
            <StaffForm
                staff={editingStaff}
                onSubmit={editingStaff ? handleUpdateStaff : handleCreateStaff}
                onCancel={() => { setEditingStaff(null); setShowForm(false); }}
            />
        );
    }

    const columns = [
        { accessorKey: 'name', header: 'Name' },
        { accessorKey: 'email', header: 'Email' },
        {
            accessorKey: 'roles',
            header: 'Roles',
            Cell: ({ cell }) => {
                const roles = cell.getValue();
                if (!roles || roles.length === 0) {
                    return <Typography variant="body2" sx={{ color: '#94a3b8', fontSize: 13 }}>No roles</Typography>;
                }
                return (
                    <Typography variant="body2" sx={{ fontSize: 13 }}>
                        {roles.join(', ')}
                    </Typography>
                );
            },
        },
        {
            accessorKey: 'permissions',
            header: 'Permissions',
            Cell: ({ cell }) => {
                const perms = cell.getValue();
                if (!perms || perms.length === 0) {
                    return <Typography variant="body2" sx={{ color: '#94a3b8', fontSize: 13 }}>None</Typography>;
                }
                return (
                    <Typography variant="body2" sx={{ fontSize: 13 }}>
                        {perms.join(', ')}
                    </Typography>
                );
            },
        },
        {
            accessorKey: 'is_active',
            header: 'Status',
            Cell: ({ cell }) => (
                <Chip
                    label={cell.getValue() ? 'Active' : 'Inactive'}
                    size="small"
                    sx={{
                        bgcolor: cell.getValue() ? '#dcfce7' : '#fee2e2',
                        color: cell.getValue() ? '#166534' : '#991b1b',
                        fontWeight: 600,
                    }}
                />
            ),
        },
    ];

    return (
        <>
            {error && (
                <div style={{ background: '#fef2f2', color: '#dc2626', padding: '12px 16px', borderRadius: '6px', marginBottom: '16px', border: '1px solid #fecaca' }}>
                    {error}
                </div>
            )}
            <DataTable
                columns={columns}
                data={staff}
                onEdit={handleEditClick}
                onDelete={handleDeleteStaff}
                onToggleStatus={handleToggleStatus}
                emptyMessage="No staff members found. Create one to get started."
            />
        </>
    );
}
