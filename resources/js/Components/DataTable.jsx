import React, { useState } from 'react';
import {
    Box,
    Paper,
    Table,
    TableBody,
    TableCell,
    TableContainer,
    TableHead,
    TableRow,
    TablePagination,
    IconButton,
    Tooltip,
    Chip,
    Typography,
} from '@mui/material';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import LoginIcon from '@mui/icons-material/Login';
import VisibilityIcon from '@mui/icons-material/Visibility';
import BlockIcon from '@mui/icons-material/Block';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import MoreVertIcon from '@mui/icons-material/MoreVert';
import Menu from '@mui/material/Menu';
import MenuItem from '@mui/material/MenuItem';
import ListItemIcon from '@mui/material/ListItemIcon';
import ListItemText from '@mui/material/ListItemText';

export default function DataTable({
    columns = [],
    data = [],
    onEdit,
    onDelete,
    onImpersonate,
    onView,
    onToggleStatus,
    rowMenuActions = [],
    emptyMessage = 'No records found',
    rowsPerPageOptions = [5, 10, 25],
    defaultRowsPerPage = 10,
}) {
    const [page, setPage] = useState(0);
    const [rowsPerPage, setRowsPerPage] = useState(defaultRowsPerPage);
    const [menuAnchor, setMenuAnchor] = useState(null);
    const [menuRow, setMenuRow] = useState(null);

    const handleChangePage = (event, newPage) => {
        setPage(newPage);
    };

    const handleChangeRowsPerPage = (event) => {
        setRowsPerPage(parseInt(event.target.value, 10));
        setPage(0);
    };

    const paginatedData = data.slice(page * rowsPerPage, page * rowsPerPage + rowsPerPage);

    const handleMenuOpen = (event, row) => {
        setMenuAnchor(event.currentTarget);
        setMenuRow(row);
    };

    const handleMenuClose = () => {
        setMenuAnchor(null);
        setMenuRow(null);
    };

    const renderCell = (row, column) => {
        if (column.Cell) {
            return <column.Cell cell={{ getValue: () => row[column.accessorKey], row: { original: row } }} />;
        }
        return row[column.accessorKey];
    };

    const renderActions = (row) => {
        const actions = [];
        if (onEdit) actions.push({ type: 'edit' });
        if (onToggleStatus) actions.push({ type: 'toggle', isActive: row.is_active });
        if (onView) actions.push({ type: 'view' });
        if (onImpersonate) actions.push({ type: 'impersonate' });
        const hasMenuActions = rowMenuActions && (typeof rowMenuActions === 'function' ? rowMenuActions(row) : rowMenuActions).length > 0;
        if (hasMenuActions) actions.push({ type: 'menu' });
        if (onDelete) actions.push({ type: 'delete' });

        if (actions.length === 0) return null;

        return (
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 0 }}>
                {actions.map((action, idx) => {
                    if (action.type === 'edit') {
                        return (
                            <Tooltip key={idx} title="Edit">
                                <IconButton size="small" sx={{ color: '#3b82f6' }} onClick={() => onEdit(row)}>
                                    <EditIcon fontSize="small" />
                                </IconButton>
                            </Tooltip>
                        );
                    }
                    if (action.type === 'toggle') {
                        return (
                            <Tooltip key={idx} title={row.is_active ? 'Deactivate' : 'Activate'}>
                                <IconButton size="small" sx={{ color: action.isActive ? '#ef4444' : '#22c55e' }} onClick={() => onToggleStatus(row)}>
                                    {action.isActive ? <BlockIcon fontSize="small" /> : <CheckCircleIcon fontSize="small" />}
                                </IconButton>
                            </Tooltip>
                        );
                    }
                    if (action.type === 'view') {
                        return (
                            <Tooltip key={idx} title="View">
                                <IconButton size="small" sx={{ color: '#64748b' }} onClick={() => onView(row)}>
                                    <VisibilityIcon fontSize="small" />
                                </IconButton>
                            </Tooltip>
                        );
                    }
                    if (action.type === 'impersonate') {
                        return (
                            <Tooltip key={idx} title="Impersonate">
                                <IconButton size="small" sx={{ color: '#8b5cf6' }} onClick={() => onImpersonate(row)}>
                                    <LoginIcon fontSize="small" />
                                </IconButton>
                            </Tooltip>
                        );
                    }
                    if (action.type === 'menu') {
                        const menuActions = typeof rowMenuActions === 'function' ? rowMenuActions(row) : rowMenuActions;
                        return (
                            <React.Fragment key={idx}>
                                <IconButton size="small" sx={{ color: '#64748b' }} onClick={(e) => handleMenuOpen(e, row)}>
                                    <MoreVertIcon fontSize="small" />
                                </IconButton>
                                <Menu anchorEl={menuAnchor} open={Boolean(menuAnchor) && menuRow?.id === row.id} onClose={handleMenuClose}>
                                    {menuActions.map((action, aIdx) => (
                                        <MenuItem
                                            key={aIdx}
                                            onClick={() => {
                                                action.onClick(row);
                                                handleMenuClose();
                                            }}
                                        >
                                            {action.icon && <ListItemIcon>{action.icon}</ListItemIcon>}
                                            <ListItemText primaryTypographyProps={{ fontSize: '13px' }}>{action.label}</ListItemText>
                                        </MenuItem>
                                    ))}
                                </Menu>
                            </React.Fragment>
                        );
                    }
                    if (action.type === 'delete') {
                        return (
                            <Tooltip key={idx} title="Delete">
                                <IconButton size="small" sx={{ color: '#ef4444' }} onClick={() => onDelete(row)}>
                                    <DeleteIcon fontSize="small" />
                                </IconButton>
                            </Tooltip>
                        );
                    }
                    return null;
                })}
            </Box>
        );
    };

    return (
        <Paper sx={{ boxShadow: '0 1px 3px rgba(0,0,0,0.06)' }}>
            <TableContainer>
                <Table size="small">
                    <TableHead>
                        <TableRow sx={{ borderBottom: '1px solid #f1f5f9', bgcolor: '#f8fafc' }}>
                            {columns.map((col) => (
                                <TableCell
                                    key={col.accessorKey}
                                    sx={{
                                        fontWeight: 600,
                                        fontSize: '12px',
                                        color: '#64748b',
                                        textTransform: 'uppercase',
                                        letterSpacing: '0.05em',
                                        py: 1.5,
                                    }}
                                >
                                    {col.header}
                                </TableCell>
                            ))}
                            {(onEdit || onDelete || onImpersonate || onView || onToggleStatus || rowMenuActions.length > 0) && (
                                <TableCell
                                    sx={{
                                        fontWeight: 600,
                                        fontSize: '12px',
                                        color: '#64748b',
                                        textTransform: 'uppercase',
                                        letterSpacing: '0.05em',
                                        py: 1.5,
                                    }}
                                >
                                    Actions
                                </TableCell>
                            )}
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {paginatedData.length === 0 ? (
                            <TableRow>
                                <TableCell
                                    colSpan={columns.length + 1}
                                    sx={{ textAlign: 'center', py: 5, color: '#94a3b8' }}
                                >
                                    <Typography variant="body2">{emptyMessage}</Typography>
                                </TableCell>
                            </TableRow>
                        ) : (
                            paginatedData.map((row, idx) => (
                                <TableRow
                                    key={row.id ?? idx}
                                    sx={{
                                        borderBottom: '1px solid #f1f5f9',
                                        '&:last-child td, &:last-child th': { border: 0 },
                                        '&:hover': { bgcolor: '#f8fafc' },
                                    }}
                                >
                                    {columns.map((col) => (
                                        <TableCell key={col.accessorKey} sx={{ py: 1.5, fontSize: '13px' }}>
                                            {renderCell(row, col)}
                                        </TableCell>
                                    ))}
                                    {(onEdit || onDelete || onImpersonate || onView || onToggleStatus || rowMenuActions.length > 0) && (
                                        <TableCell sx={{ py: 1.5, whiteSpace: 'nowrap' }}>
                                            {renderActions(row)}
                                        </TableCell>
                                    )}
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </TableContainer>
            <TablePagination
                component="div"
                count={data.length}
                page={page}
                onPageChange={handleChangePage}
                rowsPerPage={rowsPerPage}
                onRowsPerPageChange={handleChangeRowsPerPage}
                rowsPerPageOptions={rowsPerPageOptions}
                sx={{ borderTop: '1px solid #f1f5f9' }}
            />
        </Paper>
    );
}
