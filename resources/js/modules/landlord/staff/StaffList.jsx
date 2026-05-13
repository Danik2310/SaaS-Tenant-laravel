import React, { useEffect, useState } from 'react';
import { toast } from 'sonner';
import api from '../../../services/api';
import StaffForm from './StaffForm';
import DataTable from '@/components/DataTable';
import Typography from '@mui/material/Typography';
import Chip from '@mui/material/Chip';
import Box from '@mui/material/Box';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Button from '@mui/material/Button';
import Alert from '@mui/material/Alert';
import CircularProgress from '@mui/material/CircularProgress';

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
        } finally {
            setLoading(false);
        }
    };

    const handleCreateStaff = async (data) => {
        try {
            const res = await api.post('/admin/api/staff', data);
            toast.success('Staff member created successfully');
            setShowForm(false);
            setStaff(prev => [...prev, res.data.staff]);
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to create staff');
        }
    };

    const handleUpdateStaff = async (data) => {
        try {
            const res = await api.put(`/admin/api/staff/${editingStaff.id}`, data);
            toast.success('Staff member updated successfully');
            setEditingStaff(null);
            setStaff(prev => prev.map(s => s.id === editingStaff.id ? res.data.staff : s));
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to update staff');
        }
    };

    const handleDeleteStaff = async (row) => {
        if (!window.confirm(`Are you sure you want to delete ${row.name}? This action can be undone.`)) {
            return;
        }
        try {
            await api.delete(`/admin/api/staff/${row.id}`);
            toast.success('Staff member deleted successfully');
            setStaff(prev => prev.filter(s => s.id !== row.id));
        } catch (err) {
            toast.error('Failed to delete staff');
        }
    };

    const handleToggleStatus = async (row) => {
        try {
            await api.patch(`/admin/api/staff/${row.id}/toggle-status`);
            toast.success(`Staff member ${row.is_active ? 'deactivated' : 'activated'} successfully`);
            setStaff(prev => prev.map(s => s.id === row.id ? { ...s, is_active: !s.is_active } : s));
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to toggle status');
        }
    };

    const handleEditClick = async (row) => {
        try {
            const response = await api.get(`/admin/api/staff/${row.id}`);
            setEditingStaff(response.data.staff);
        } catch (err) {
            toast.error('Failed to load staff details');
        }
    };

    // Show the form when creating or editing
    if (showForm || editingStaff) {
        return (
            <StaffForm
                staff={editingStaff}
                onSubmit={editingStaff ? handleUpdateStaff : handleCreateStaff}
                onCancel={() => { setEditingStaff(null); setShowForm(false); }}
            />
        );
    }

    // Loading state
    if (loading) {
        return (
            <Box sx={{ textAlign: 'center', py: 5 }}>
                <CircularProgress size={32} />
                <Typography variant="body2" color="text.secondary" sx={{ mt: 1 }}>
                    Loading staff members...
                </Typography>
            </Box>
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
                    return (
                        <Typography variant="body2" color="text.disabled" sx={{ fontSize: 13 }}>
                            No roles
                        </Typography>
                    );
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
                    return (
                        <Typography variant="body2" color="text.disabled" sx={{ fontSize: 13 }}>
                            None
                        </Typography>
                    );
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
                    color={cell.getValue() ? 'success' : 'error'}
                />
            ),
        },
    ];

    return (
        <>
            {error && (
                <Alert severity="error" sx={{ mb: 2 }}>
                    {error}
                </Alert>
            )}

            <Paper sx={{ p: 2, mb: 2 }}>
                <Stack direction="row" justifyContent="space-between" alignItems="center" flexWrap="wrap" spacing={1.5}>
                    <Typography variant="h6" sx={{ fontWeight: 600, fontSize: '1rem' }}>
                        Staff Management
                    </Typography>
                    <Button
                        variant="contained"
                        color="success"
                        size="small"
                        onClick={() => setShowForm(true)}
                        sx={{ textTransform: 'none', fontWeight: 600 }}
                    >
                        + Create
                    </Button>
                </Stack>
            </Paper>

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
