import React from 'react';
import DataTable from '@/components/DataTable';
import { Box, Button, Typography, Chip, Tooltip, Switch, FormControlLabel } from '@mui/material';
import RestoreIcon from '@mui/icons-material/Restore';

export default function TenantList({ tenants, onAdd, onDelete, onEdit, onImpersonate, onRowSave, onRestore, showDeleted, onToggleDeleted, rowMenuActions = [] }) {
    const columns = React.useMemo(
        () => [
            { accessorKey: 'id', header: 'ID' },
            { accessorKey: 'name', header: 'Name' },
            { accessorKey: 'email', header: 'Email' },
            { accessorKey: 'domain', header: 'Domain' },
            {
                accessorKey: 'status',
                header: 'Status',
                Cell: ({ cell }) => {
                    const isDeleted = cell.row.is_deleted;
                    if (isDeleted) {
                        return (
                            <Chip label="Deleted" size="small" sx={{ bgcolor: '#f1f5f9', color: '#64748b', fontWeight: 600, fontStyle: 'italic' }} />
                        );
                    }
                    const status = cell.getValue();
                    return (
                        <Tooltip title={status === 'Active' ? 'Tenant is active and operational' : 'Tenant is suspended and cannot access the system'}>
                            <Chip
                                label={status}
                                size="small"
                                sx={{
                                    bgcolor: status === 'Active' ? '#dcfce7' : '#fee2e2',
                                    color: status === 'Active' ? '#166534' : '#991b1b',
                                    fontWeight: 600,
                                }}
                            />
                        </Tooltip>
                    );
                },
            },
            {
                accessorKey: 'created_at',
                header: 'Created',
                Cell: ({ cell }) => (
                    <Typography variant="body2" sx={{ color: '#64748b', fontSize: 13 }}>
                        {new Date(cell.getValue()).toLocaleDateString()}
                    </Typography>
                ),
            },
        ],
        []
    );

    return (
        <Box>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 2, alignItems: 'center' }}>
                <Typography variant="subtitle1" sx={{ fontWeight: 600, color: '#0f172a' }}>
                    Tenant Management
                </Typography>
                <Box sx={{ display: 'flex', gap: 1, alignItems: 'center' }}>
                    <FormControlLabel
                        control={<Switch size="small" checked={showDeleted} onChange={onToggleDeleted} />}
                        label={<Typography variant="caption" sx={{ color: '#64748b' }}>Show deleted</Typography>}
                        sx={{ mr: 0 }}
                    />
                    <Button
                        variant="contained"
                        size="small"
                        onClick={onAdd}
                        sx={{
                            bgcolor: '#22c55e',
                            '&:hover': { bgcolor: '#16a34a' },
                            fontWeight: 600,
                            fontSize: '13px',
                        }}
                    >
                        + New Tenant
                    </Button>
                </Box>
            </Box>

            <DataTable
                columns={columns}
                data={tenants}
                onEdit={onEdit}
                onDelete={onDelete}
                onImpersonate={onImpersonate}
                onRowSave={onRowSave}
                rowMenuActions={rowMenuActions}
                emptyMessage={showDeleted ? 'No deleted tenants found.' : 'No tenants found. Create one to get started.'}
            />
        </Box>
    );
}
