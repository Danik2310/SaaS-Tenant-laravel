import React from 'react';
import GenericTable from '../../../components/GenericTable';
import { Chip, Tooltip } from '@mui/material';

export default function TenantList({ tenants, onDelete, onEdit, onImpersonate, onRowSave, rowMenuActions }) {
    const columns = React.useMemo(
        () => [
            { accessorKey: 'id', header: 'Tenant ID' },
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
                            color={cell.getValue() === 'Active' ? 'success' : 'error'}
                            size="small"
                        />
                    </Tooltip>
                ),
            },
            {
                accessorKey: 'created_at',
                header: 'Created',
                Cell: ({ cell }) => new Date(cell.getValue()).toLocaleDateString(),
            },
        ],
        []
    );

    const handleRowSave = async (original, values) => {
        if (onRowSave) {
            await onRowSave(original, values);
        }
    };

    return (
        <GenericTable
            columns={columns}
            data={tenants}
            onEdit={onEdit}
            onDelete={onDelete}
            onImpersonate={onImpersonate}
            onRowSave={handleRowSave}
            rowMenuActions={rowMenuActions}
        />
    );
}

TenantList.defaultProps = {
    onEdit: () => {},
    onImpersonate: () => {},
    rowMenuActions: [],
};

