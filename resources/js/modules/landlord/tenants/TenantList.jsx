import React from 'react';
import DataTable from '@/components/DataTable';
import { Box, Button, Typography, Chip, Tooltip } from '@mui/material';

export default function TenantList({ tenants, onAdd, onDelete, onEdit, onImpersonate, onRowSave, rowMenuActions = [] }) {
    const columns = React.useMemo(
        () => [
            { accessorKey: 'id', header: 'ID' },
            { accessorKey: 'name', header: 'Name' },
            { accessorKey: 'email', header: 'Email' },
            { accessorKey: 'domain', header: 'Domain' },
            {
                accessorKey: 'status',
                header: 'Status',
                Cell: ({ cell }) => (
                    <Tooltip title={cell.getValue() === 'Active' ? 'Tenant is active and operational' : 'Tenant is suspended and cannot access the system'}>
                        <Chip
                            label={cell.getValue()}
                            size="small"
                            sx={{
                                bgcolor: cell.getValue() === 'Active' ? '#dcfce7' : '#fee2e2',
                                color: cell.getValue() === 'Active' ? '#166534' : '#991b1b',
                                fontWeight: 600,
                            }}
                        />
                    </Tooltip>
                ),
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

            <DataTable
                columns={columns}
                data={tenants}
                onEdit={onEdit}
                onDelete={onDelete}
                onImpersonate={onImpersonate}
                onRowSave={onRowSave}
                rowMenuActions={rowMenuActions}
                emptyMessage="No tenants found. Create one to get started."
            />
        </Box>
    );
}
