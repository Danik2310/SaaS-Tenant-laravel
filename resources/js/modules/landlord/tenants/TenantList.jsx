import React from 'react';
import DataTable from '@/components/DataTable';
import { Chip, Tooltip, Typography } from '@mui/material';

export default function TenantList({ tenants, onDelete, onEdit, onImpersonate, onRowSave, rowMenuActions = [] }) {
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
    );
}
