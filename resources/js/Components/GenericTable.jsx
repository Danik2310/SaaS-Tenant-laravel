import React from 'react';
import { MaterialReactTable } from 'material-react-table';
import Box from '@mui/material/Box';
import IconButton from '@mui/material/IconButton';
import Tooltip from '@mui/material/Tooltip';
import Menu from '@mui/material/Menu';
import MenuItem from '@mui/material/MenuItem';
import ListItemIcon from '@mui/material/ListItemIcon';
import ListItemText from '@mui/material/ListItemText';
import Edit from '@mui/icons-material/Edit';
import Delete from '@mui/icons-material/Delete';
import LoginIcon from '@mui/icons-material/Login';
import MoreVertIcon from '@mui/icons-material/MoreVert';

const GenericTable = React.memo(function GenericTable({
    columns,
    data,
    onEdit,
    onDelete,
    onImpersonate,
    onRowSave,
    rowMenuActions = [],
    ...props
}) {
    const [menuAnchor, setMenuAnchor] = React.useState({});

    const handleMenuOpen = (rowId, e) => {
        setMenuAnchor((prev) => ({ ...prev, [rowId]: e.currentTarget }));
    };

    const handleMenuClose = (rowId) => {
        setMenuAnchor((prev) => {
            const next = { ...prev };
            delete next[rowId];
            return next;
        });
    };

    const renderRowActions = ({ row }) => {
        const rowId = row.id;
        const open = Boolean(menuAnchor[rowId]);

        return (
            <Box sx={{ display: 'flex', gap: '0.5rem', alignItems: 'center' }}>
                {onEdit && (
                    <Tooltip title="Edit">
                        <IconButton color="primary" onClick={() => onEdit(row.original)}>
                            <Edit />
                        </IconButton>
                    </Tooltip>
                )}
                {onDelete && (
                    <Tooltip title="Delete">
                        <IconButton color="error" onClick={() => onDelete(row.original.id)}>
                            <Delete />
                        </IconButton>
                    </Tooltip>
                )}
                {onImpersonate && (
                    <Tooltip title="Impersonate">
                        <IconButton color="secondary" onClick={() => onImpersonate(row.original)}>
                            <LoginIcon />
                        </IconButton>
                    </Tooltip>
                )}
                {rowMenuActions && rowMenuActions.length > 0 && (
                    <>
                        <Tooltip title="More actions">
                            <IconButton size="small" onClick={(e) => handleMenuOpen(rowId, e)}>
                                <MoreVertIcon />
                            </IconButton>
                        </Tooltip>
                        <Menu anchorEl={menuAnchor[rowId]} open={open} onClose={() => handleMenuClose(rowId)}>
                            {(typeof rowMenuActions === 'function' ? rowMenuActions(row.original) : rowMenuActions).map((action, idx) => (
                                <MenuItem
                                    key={idx}
                                    onClick={() => {
                                        action.onClick(row.original);
                                        handleMenuClose(rowId);
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
});

export default GenericTable;
