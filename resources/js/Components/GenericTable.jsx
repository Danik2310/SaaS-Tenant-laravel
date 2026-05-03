import React from 'react';
import { MaterialReactTable } from 'material-react-table';
import { Box, IconButton, Menu, MenuItem, ListItemIcon, ListItemText } from '@mui/material';
import Edit from '@mui/icons-material/Edit';
import Delete from '@mui/icons-material/Delete';
import LoginIcon from '@mui/icons-material/Login';
import MoreVertIcon from '@mui/icons-material/MoreVert';

export default function GenericTable({
    columns,
    data,
    onEdit,
    onDelete,
    onImpersonate,
    onRowSave,
    rowMenuActions = [],
    ...props
}) {
    const renderRowActions = ({ row }) => {
        // state per row for menu anchor
        const [anchorEl, setAnchorEl] = React.useState(null);
        const open = Boolean(anchorEl);
        const handleMenuOpen = (e) => setAnchorEl(e.currentTarget);
        const handleMenuClose = () => setAnchorEl(null);

        return (
            <Box sx={{ display: 'flex', gap: '0.5rem', alignItems: 'center' }}>
                {onEdit && (
                    <IconButton color="primary" onClick={() => onEdit(row.original)}>
                        <Edit />
                    </IconButton>
                )}
                {onDelete && (
                    <IconButton color="error" onClick={() => onDelete(row.original.id)}>
                        <Delete />
                    </IconButton>
                )}
                {onImpersonate && (
                    <IconButton color="secondary" onClick={() => onImpersonate(row.original)}>
                        <LoginIcon />
                    </IconButton>
                )}
                {rowMenuActions && rowMenuActions.length > 0 && (
                    <>
                        <IconButton size="small" onClick={handleMenuOpen}>
                            <MoreVertIcon />
                        </IconButton>
                        <Menu anchorEl={anchorEl} open={open} onClose={handleMenuClose}>
                            {(typeof rowMenuActions === 'function' ? rowMenuActions(row.original) : rowMenuActions).map((action, idx) => (
                                <MenuItem
                                    key={idx}
                                    onClick={() => {
                                        action.onClick(row.original);
                                        handleMenuClose();
                                    }}
                                >
                                    {action.icon && <ListItemIcon>{action.icon}</ListItemIcon>}
                                    <ListItemText>{action.label}</ListItemText>
                                </MenuItem>
                            ))}
                        </Menu>
                    </>
                )}
            </Box>
        );
    };

    // handler for editing row save
    const handleSaveRow = async ({ exitEditingMode, row, values }) => {
        if (onRowSave) {
            await onRowSave(row.original, values);
        }
        exitEditingMode();
    };

    return (
        <MaterialReactTable
            columns={columns}
            data={data}
            renderRowActions={renderRowActions}
            editingMode="row"
            enableEditing={!!onRowSave}
            onEditingRowSave={handleSaveRow}
            enableColumnActions={false}
            enableColumnFilters={false}
            muiTablePaperProps={{ elevation: 2 }}
            initialState={{ density: 'compact' }}
            localization={{ toolbarSearchPlaceholder: 'Buscar...' }}
            {...props}
        />
    );
}