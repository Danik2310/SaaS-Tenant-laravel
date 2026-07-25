import React, { useState, useCallback } from 'react';
import { useTheme } from '@mui/material/styles';
import Box from '@mui/material/Box';
import Paper from '@mui/material/Paper';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableContainer from '@mui/material/TableContainer';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';
import TablePagination from '@mui/material/TablePagination';
import IconButton from '@mui/material/IconButton';
import Tooltip from '@mui/material/Tooltip';
import Typography from '@mui/material/Typography';
import Divider from '@mui/material/Divider';
import Checkbox from '@mui/material/Checkbox';
import Skeleton from '@mui/material/Skeleton';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import LoginIcon from '@mui/icons-material/Login';
import VisibilityIcon from '@mui/icons-material/Visibility';
import BlockIcon from '@mui/icons-material/Block';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import MoreVertIcon from '@mui/icons-material/MoreVert';
import RefreshIcon from '@mui/icons-material/Refresh';
import InboxIcon from '@mui/icons-material/Inbox';
import Menu from '@mui/material/Menu';
import MenuItem from '@mui/material/MenuItem';
import ListItemIcon from '@mui/material/ListItemIcon';
import ListItemText from '@mui/material/ListItemText';

function RowSkeleton({ columns }) {
    return (
        <TableRow>
            {columns.map((col, i) => (
                <TableCell key={i} sx={{ py: 1.5 }}>
                    <Skeleton variant="text" width={col.accessorKey === 'id' ? 40 : col.accessorKey === 'status' ? 60 : 120} />
                </TableCell>
            ))}
            <TableCell sx={{ py: 1.5 }}>
                <Box sx={{ display: 'flex', gap: 0.5 }}>
                    <Skeleton variant="circular" width={28} height={28} />
                    <Skeleton variant="circular" width={28} height={28} />
                </Box>
            </TableCell>
        </TableRow>
    );
}

const DataTable = React.memo(function DataTable({
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
    defaultRowsPerPage = 5,

    // Server-side pagination
    total,
    page,
    rowsPerPage,
    onPageChange,
    onRowsPerPageChange,

    // Selection
    enableSelection = false,
    onSelectionChange,
    selectedIds = new Set(),

    // Loading & state
    loading = false,
    lastUpdated,
    onRefresh,
}) {
    const theme = useTheme();
    const [internalPage, setInternalPage] = useState(0);
    const [internalRowsPerPage, setInternalRowsPerPage] = useState(defaultRowsPerPage);
    const [menuAnchor, setMenuAnchor] = useState(null);
    const [menuRow, setMenuRow] = useState(null);

    const isServerSide = total !== undefined;

    const currentPage = isServerSide ? page : internalPage;
    const currentRowsPerPage = isServerSide ? rowsPerPage : internalRowsPerPage;

    const handleChangePage = useCallback((event, newPage) => {
        if (isServerSide) {
            onPageChange(newPage);
        } else {
            setInternalPage(newPage);
        }
    }, [isServerSide, onPageChange]);

    const handleChangeRowsPerPage = useCallback((event) => {
        const value = parseInt(event.target.value, 10);
        if (isServerSide) {
            onRowsPerPageChange(value);
        } else {
            setInternalRowsPerPage(value);
            setInternalPage(0);
        }
    }, [isServerSide, onRowsPerPageChange]);

    const displayData = isServerSide
        ? data
        : data.slice(currentPage * currentRowsPerPage, currentPage * currentRowsPerPage + currentRowsPerPage);

    const handleMenuOpen = useCallback((event, row) => {
        setMenuAnchor(event.currentTarget);
        setMenuRow(row);
    }, []);

    const handleMenuClose = useCallback(() => {
        setMenuAnchor(null);
        setMenuRow(null);
    }, []);

    const handleSelectAll = useCallback((checked) => {
        if (checked) {
            const allIds = new Set(displayData.map((r) => r.id));
            onSelectionChange(allIds);
        } else {
            onSelectionChange(new Set());
        }
    }, [displayData, onSelectionChange]);

    const handleSelectRow = useCallback((id, checked) => {
        const next = new Set(selectedIds);
        if (checked) {
            next.add(id);
        } else {
            next.delete(id);
        }
        onSelectionChange(next);
    }, [selectedIds, onSelectionChange]);

    const allDisplayedSelected = displayData.length > 0 && displayData.every((r) => selectedIds.has(r.id));
    const someDisplayedSelected = displayData.some((r) => selectedIds.has(r.id));

    const renderCell = (row, column) => {
        if (column.Cell) {
            return <column.Cell cell={{ getValue: () => row[column.accessorKey], row }} />;
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
                                <IconButton size="small" sx={{ color: 'primary.main' }} onClick={() => onEdit(row)}>
                                    <EditIcon fontSize="small" />
                                </IconButton>
                            </Tooltip>
                        );
                    }
                    if (action.type === 'toggle') {
                        return (
                            <Tooltip key={idx} title={row.is_active ? 'Deactivate' : 'Activate'}>
                                <IconButton size="small" sx={{ color: action.isActive ? 'error.main' : 'success.main' }} onClick={() => onToggleStatus(row)}>
                                    {action.isActive ? <BlockIcon fontSize="small" /> : <CheckCircleIcon fontSize="small" />}
                                </IconButton>
                            </Tooltip>
                        );
                    }
                    if (action.type === 'view') {
                        return (
                            <Tooltip key={idx} title="View">
                                <IconButton size="small" sx={{ color: 'text.secondary' }} onClick={() => onView(row)}>
                                    <VisibilityIcon fontSize="small" />
                                </IconButton>
                            </Tooltip>
                        );
                    }
                    if (action.type === 'impersonate') {
                        return (
                            <Tooltip key={idx} title="Impersonate">
                                <IconButton size="small" sx={{ color: 'secondary.main' }} onClick={() => onImpersonate(row)}>
                                    <LoginIcon fontSize="small" />
                                </IconButton>
                            </Tooltip>
                        );
                    }
                    if (action.type === 'menu') {
                        const menuActions = typeof rowMenuActions === 'function' ? rowMenuActions(row) : rowMenuActions;
                        return (
                            <React.Fragment key={idx}>
                                <Tooltip title="More actions">
                                <IconButton size="small" sx={{ color: 'text.secondary' }} onClick={(e) => handleMenuOpen(e, row)}>
                                    <MoreVertIcon fontSize="small" />
                                </IconButton>
                                </Tooltip>
                                <Menu anchorEl={menuAnchor} open={Boolean(menuAnchor) && menuRow?.id === row.id} onClose={handleMenuClose}>
                                    {menuActions.map((action, aIdx) =>
                                        action.divider ? (
                                            <Divider key={aIdx} sx={{ my: 0.5 }} />
                                        ) : (
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
                                        )
                                    )}
                                </Menu>
                            </React.Fragment>
                        );
                    }
                    if (action.type === 'delete') {
                        return (
                            <Tooltip key={idx} title="Delete">
                                <IconButton size="small" sx={{ color: 'error.main' }} onClick={() => onDelete(row)}>
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

    const colCount = columns.length + (enableSelection ? 1 : 0) + (
        onEdit || onDelete || onImpersonate || onView || onToggleStatus || rowMenuActions.length > 0 ? 1 : 0
    );

    return (
        <Paper sx={{ boxShadow: '0 1px 3px rgba(0,0,0,0.06)' }}>
            {(onRefresh || lastUpdated) && (
                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'flex-end', px: 2, pt: 1, gap: 1 }}>
                    {lastUpdated && (
                        <Typography variant="caption" sx={{ color: 'text.disabled' }}>
                            Data as of {lastUpdated}
                        </Typography>
                    )}
                    {onRefresh && (
                        <Tooltip title="Refresh">
                            <IconButton size="small" sx={{ color: 'text.secondary' }} onClick={onRefresh}>
                                <RefreshIcon fontSize="small" />
                            </IconButton>
                        </Tooltip>
                    )}
                </Box>
            )}
            <TableContainer>
                <Table size="small">
                    <TableHead>
                        <TableRow sx={{ borderBottom: '1px solid', borderColor: 'grey.100', bgcolor: 'grey.50' }}>
                            {enableSelection && (
                                <TableCell sx={{ width: 48, py: 1.5 }}>
                                    <Checkbox
                                        size="small"
                                        checked={allDisplayedSelected}
                                        indeterminate={someDisplayedSelected && !allDisplayedSelected}
                                        onChange={(e) => handleSelectAll(e.target.checked)}
                                    />
                                </TableCell>
                            )}
                            {columns.map((col) => (
                                <TableCell
                                    key={col.accessorKey}
                                    sx={{
                                        fontWeight: 600,
                                        fontSize: '12px',
                                        color: 'text.secondary',
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
                                        color: 'text.secondary',
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
                        {loading ? (
                            Array.from({ length: 5 }).map((_, i) => (
                                <RowSkeleton key={i} columns={columns} />
                            ))
                        ) : displayData.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={colCount} sx={{ textAlign: 'center', py: 6 }}>
                                    <InboxIcon sx={{ fontSize: 48, color: 'grey.300', mb: 1 }} />
                                    <Typography variant="body1" sx={{ fontWeight: 600, color: 'text.secondary' }}>
                                        {emptyMessage}
                                    </Typography>
                                </TableCell>
                            </TableRow>
                        ) : (
                            displayData.map((row, idx) => (
                                <TableRow
                                    key={row.id ?? idx}
                                    sx={{
                                        borderBottom: '1px solid',
                                        borderColor: 'grey.100',
                                        '&:last-child td, &:last-child th': { border: 0 },
                                        '&:hover': { bgcolor: 'grey.50' },
                                    }}
                                >
                                    {enableSelection && (
                                        <TableCell sx={{ width: 48, py: 1.5 }}>
                                            <Checkbox
                                                size="small"
                                                checked={selectedIds.has(row.id)}
                                                onChange={(e) => handleSelectRow(row.id, e.target.checked)}
                                            />
                                        </TableCell>
                                    )}
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
                count={isServerSide ? total : data.length}
                page={isServerSide ? currentPage : currentPage}
                onPageChange={handleChangePage}
                rowsPerPage={currentRowsPerPage}
                onRowsPerPageChange={handleChangeRowsPerPage}
                rowsPerPageOptions={rowsPerPageOptions}
                sx={{ borderTop: '1px solid', borderColor: 'grey.100' }}
            />
        </Paper>
    );
});

export default DataTable;
