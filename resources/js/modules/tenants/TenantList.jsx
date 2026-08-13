import { useState, useEffect, useMemo, useCallback } from 'react';
import { MaterialReactTable } from 'material-react-table';
import api from '@/services/api';
import { useAuthContext } from '@/context/AuthContext';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Typography from '@mui/material/Typography';
import Chip from '@mui/material/Chip';
import Tooltip from '@mui/material/Tooltip';
import Switch from '@mui/material/Switch';
import FormControlLabel from '@mui/material/FormControlLabel';
import Menu from '@mui/material/Menu';
import MenuItem from '@mui/material/MenuItem';
import ListItemIcon from '@mui/material/ListItemIcon';
import ListItemText from '@mui/material/ListItemText';
import IconButton from '@mui/material/IconButton';
import WorkspacePremiumIcon from '@mui/icons-material/WorkspacePremium';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import LoginIcon from '@mui/icons-material/Login';
import BlockIcon from '@mui/icons-material/Block';
import RestoreIcon from '@mui/icons-material/Restore';
import MoreVertIcon from '@mui/icons-material/MoreVert';
import InboxIcon from '@mui/icons-material/Inbox';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import { toast } from 'sonner';
import ConfirmDialog from '@/Components/ConfirmDialog';

export default function TenantList({
    refreshTrigger,
    onAdd,
    onDelete,
    onEdit,
    onImpersonate,
    onToggleStatus,
    onRestore,
    onSelectionChange,
    rowMenuActions = [],
    onBulkAction,
    bulkLoading = false,
    initialSearch = '',
    initialShowDeleted = false,
}) {
    const { permissions = [] } = useAuthContext();
    const canCreate = permissions.includes('create tenants');
    const canEdit = permissions.includes('edit tenants');
    const canDelete = permissions.includes('delete tenants');
    const canRestore = permissions.includes('restore tenants');
    const canImpersonate = permissions.includes('impersonate tenants');

    const [data, setData] = useState([]);
    const [loading, setLoading] = useState(true);
    const [total, setTotal] = useState(0);
    const [error, setError] = useState(null);
    const [showDeleted, setShowDeleted] = useState(initialShowDeleted);
    const [plans, setPlans] = useState([]);

    const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 5 });
    const [globalFilter, setGlobalFilter] = useState(initialSearch);
    const [columnFilters, setColumnFilters] = useState([]);
    const [sorting, setSorting] = useState([]);
    const [rowSelection, setRowSelection] = useState({});
    const [actionMenuAnchor, setActionMenuAnchor] = useState(null);
    const [actionMenuRow, setActionMenuRow] = useState(null);
    const [pendingBulkAction, setPendingBulkAction] = useState(null);

    useEffect(() => {
        const canFetchPlans = ['view plans', 'view subscriptions', 'create tenants', 'edit tenants']
            .some(p => permissions.includes(p));
        if (!canFetchPlans) return;
        api.get('/admin/api/plans-list').then(r => {
            setPlans(r.data.plans ?? []);
        }).catch(() => {});
    }, [permissions]);

    const fetchTenants = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const params = new URLSearchParams();
            if (showDeleted) params.set('trashed', '1');
            params.set('page', pagination.pageIndex + 1);
            params.set('per_page', pagination.pageSize);

            if (globalFilter) params.set('search', globalFilter);

            columnFilters.forEach(f => {
                if (f.id === 'status' && f.value) {
                    params.set('status', f.value);
                }
                if (f.id === 'plan_name' && f.value) {
                    params.set('plan_name', f.value);
                }
                if (f.id === 'created_at' && Array.isArray(f.value)) {
                    if (f.value[0]) params.set('date_from', f.value[0]);
                    if (f.value[1]) params.set('date_to', f.value[1]);
                }
            });

            const sortMapping = {
                'reference_id': 'reference_id',
                'name': 'name',
                'email': 'email',
                'status': 'status',
                'plan_name': 'plan_id',
                'created_at': 'created_at',
            };
            if (sorting.length > 0) {
                const sortField = sortMapping[sorting[0].id] || 'created_at';
                params.set('sort', sortField);
                params.set('order', sorting[0].desc ? 'desc' : 'asc');
            }

            const response = await api.get(`/admin/api/tenants?${params}`);
            setData(response.data.tenants);
            setTotal(response.data.total);
        } catch (err) {
            const message = 'Failed to fetch tenants';
            toast.error(message);
            setError(message);
        } finally {
            setLoading(false);
        }
    }, [showDeleted, pagination, globalFilter, columnFilters, sorting]);

    useEffect(() => {
        fetchTenants();
    }, [fetchTenants, refreshTrigger]);

    const selectedIds = useMemo(() =>
        new Set(Object.keys(rowSelection).filter(k => rowSelection[k])),
        [rowSelection]
    );

    const handleBulkActionWithRefresh = useCallback(async (action, payload) => {
        if (onBulkAction) {
            await onBulkAction(action, Array.from(selectedIds), payload);
        }
    }, [onBulkAction, selectedIds]);

    const confirmBulkAction = useCallback((action) => {
        const destructive = action === 'delete' || action === 'suspend';
        if (destructive) {
            setPendingBulkAction(action);
        } else {
            handleBulkActionWithRefresh(action);
        }
    }, [handleBulkActionWithRefresh]);

    const handleConfirmBulkAction = useCallback(async () => {
        if (pendingBulkAction) {
            await handleBulkActionWithRefresh(pendingBulkAction);
            setPendingBulkAction(null);
        }
    }, [pendingBulkAction, handleBulkActionWithRefresh]);

    const handleToggleDeleted = useCallback(() => {
        setShowDeleted(prev => !prev);
        setPagination(prev => ({ ...prev, pageIndex: 0 }));
        setRowSelection({});
    }, []);

    useEffect(() => {
        if (onSelectionChange) {
            onSelectionChange(selectedIds);
        }
    }, [selectedIds, onSelectionChange]);

    const columns = useMemo(() => [
        {
            accessorKey: 'reference_id',
            header: 'ID',
            enableColumnFilter: false,
            Cell: ({ cell }) => (
                <Typography variant="body2" sx={{ fontFamily: 'monospace', fontSize: 13, color: '#64748b', fontWeight: 600 }}>
                    {cell.getValue() || cell.row.original.id}
                </Typography>
            ),
        },
        {
            accessorKey: 'name',
            header: 'Name',
            enableColumnFilter: false,
        },
        {
            accessorKey: 'email',
            header: 'Email',
            enableColumnFilter: false,
        },
        {
            accessorKey: 'domain',
            header: 'Domain',
            enableColumnFilter: false,
            enableSorting: false,
        },
        {
            accessorKey: 'status',
            header: 'Status',
            filterVariant: 'select',
            filterSelectOptions: [
                { text: 'Active', value: 'Active' },
                { text: 'Trial', value: 'Trial' },
                { text: 'Suspended', value: 'Suspended' },
            ],
            Cell: ({ cell, row }) => {
                const isDeleted = row.original.is_deleted;
                if (isDeleted) {
                    return (
                        <Chip label="Deleted" size="small" sx={{ bgcolor: '#f1f5f9', color: '#64748b', fontWeight: 600, fontStyle: 'italic' }} />
                    );
                }
                const status = cell.getValue();
                if (status === 'Trial') {
                    const endsAt = row.original.trial_ends_at;
                    const endsDate = endsAt ? new Date(endsAt) : null;
                    const validEnd = endsDate && !Number.isNaN(endsDate.getTime()) ? endsDate : null;
                    const daysLeft = validEnd ? Math.ceil((validEnd.getTime() - Date.now()) / 86400000) : null;
                    const until = validEnd
                        ? validEnd.toLocaleString(undefined, {
                              year: 'numeric',
                              month: 'short',
                              day: 'numeric',
                              hour: '2-digit',
                              minute: '2-digit',
                          })
                        : '';
                    const title = row.original.trial_has_expired
                        ? 'Trial has ended - pending suspension'
                        : `Trial - temporary full access${until ? ` until ${until}` : ''}`;
                    return (
                        <Tooltip title={title}>
                            <Chip
                                label={status + (daysLeft !== null && daysLeft >= 0 ? ` \u00b7 ${daysLeft}d left` : '')}
                                size="small"
                                sx={{ bgcolor: '#fef9c3', color: '#854d0e', fontWeight: 600 }}
                            />
                        </Tooltip>
                    );
                }
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
            accessorKey: 'plan_name',
            header: 'Plan',
            filterVariant: 'select',
            filterSelectOptions: plans.map(p => ({ text: p.name, value: p.name })),
            Cell: ({ cell, row }) => {
                const planName = cell.getValue();
                const isDeleted = row.original.is_deleted;
                if (!planName || isDeleted) {
                    return (
                        <Typography variant="body2" sx={{ color: '#94a3b8', fontSize: 13 }}>
                            {isDeleted ? '—' : 'No Plan'}
                        </Typography>
                    );
                }
                return (
                    <Chip
                        icon={<WorkspacePremiumIcon sx={{ fontSize: 14 }} />}
                        label={planName}
                        size="small"
                        sx={{ bgcolor: '#f0f9ff', color: '#0369a1', fontWeight: 600, border: '1px solid #bae6fd' }}
                    />
                );
            },
        },
        {
            accessorKey: 'subscription_ends_at',
            header: 'Subscription Ends',
            enableColumnFilter: false,
            enableSorting: false,
            Cell: ({ cell, row }) => {
                const isDeleted = row.original.is_deleted;
                const hasActive = row.original.has_active_subscription;
                const endsAt = cell.getValue();
                const endsDate = endsAt ? new Date(endsAt) : null;
                const validEnd = endsDate && !Number.isNaN(endsDate.getTime()) ? endsDate : null;
                const isExpired = validEnd && validEnd.getTime() < Date.now();

                if (isDeleted || !hasActive) {
                    return (
                        <Typography variant="body2" sx={{ color: '#94a3b8', fontSize: 13 }}>
                            {'\u2014'}
                        </Typography>
                    );
                }
                if (!validEnd) {
                    return (
                        <Tooltip title="Subscription has no end date - ongoing">
                            <Typography variant="body2" sx={{ color: '#94a3b8', fontSize: 13 }}>
                                Ongoing
                            </Typography>
                        </Tooltip>
                    );
                }
                return (
                    <Tooltip title={`${isExpired ? 'Subscription expired' : 'Subscription ends'} on ${validEnd.toLocaleString(undefined, {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                    })}`}>
                        <Typography
                            variant="body2"
                            sx={{ color: isExpired ? '#b45309' : '#334155', fontWeight: isExpired ? 600 : 400, fontSize: 13 }}
                        >
                            {validEnd.toLocaleString(undefined, {
                                year: 'numeric',
                                month: 'short',
                                day: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit',
                            })}
                        </Typography>
                    </Tooltip>
                );
            },
        },
        {
            accessorKey: 'created_at',
            header: 'Created',
            filterVariant: 'date-range',
            Cell: ({ cell }) => (
                <Typography variant="body2" sx={{ color: '#64748b', fontSize: 13 }}>
                    {new Date(cell.getValue()).toLocaleDateString()}
                </Typography>
            ),
        },
    ], [plans]);

    const handleSaveRow = useCallback(async ({ exitEditingMode, row, values }) => {
        try {
            await api.put(`/admin/api/tenants/${row.original.id}`, values);
            toast.success('Tenant updated successfully');
            exitEditingMode();
            fetchTenants();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to update tenant');
        }
    }, [fetchTenants]);

    const handleRowDelete = useCallback((row) => {
        onDelete(row.original);
    }, [onDelete]);

    if (error) {
        return (
            <Box sx={{ p: 2, textAlign: 'center' }}>
                <Typography color="error">{error}</Typography>
                <Button variant="outlined" size="small" sx={{ mt: 1 }} onClick={fetchTenants}>
                    Retry
                </Button>
            </Box>
        );
    }

    return (
        <Box>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 2, alignItems: 'center' }}>
                <Typography variant="subtitle1" sx={{ fontWeight: 600, color: '#0f172a' }}>
                    Tenant Management
                </Typography>
                <Box sx={{ display: 'flex', gap: 1, alignItems: 'center' }}>
                    <FormControlLabel
                        control={<Switch size="small" checked={showDeleted} onChange={handleToggleDeleted} />}
                        label={<Typography variant="caption" sx={{ color: '#64748b' }}>Show deleted</Typography>}
                        sx={{ mr: 0 }}
                    />
                    {canCreate && (
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
                    )}
                </Box>
            </Box>

            <MaterialReactTable
                columns={columns}
                data={data}
                rowCount={total}
                state={{
                    isLoading: loading,
                    pagination,
                    globalFilter,
                    columnFilters,
                    sorting,
                    rowSelection,
                }}
                onPaginationChange={setPagination}
                onGlobalFilterChange={setGlobalFilter}
                onColumnFiltersChange={setColumnFilters}
                onSortingChange={setSorting}
                onRowSelectionChange={setRowSelection}
                enableRowSelection
                getRowId={(originalRow) => originalRow.id}
                enableGlobalFilter
                enableColumnFilters
                enableSorting
                enableEditing={canEdit}
                manualFiltering
                manualPagination
                manualSorting
                positionGlobalFilter="left"
                positionToolbarAlertBanner="top"
                renderTopToolbarCustomActions={({ table }) => {
                    const selected = table.getSelectedRowModel().flatRows;
                    if (selected.length === 0) return null;

                    const bulkButtons = showDeleted
                        ? (canRestore ? [
                            { label: 'Restore', color: 'success', icon: <CheckCircleIcon />, onClick: () => handleBulkActionWithRefresh('restore') },
                        ] : [])
                        : [
                            ...(canEdit ? [
                                { label: 'Suspend', color: 'error', onClick: () => confirmBulkAction('suspend') },
                                { label: 'Activate', color: 'success', onClick: () => handleBulkActionWithRefresh('activate') },
                            ] : []),
                            ...(canDelete ? [
                                { label: 'Delete', color: 'error', onClick: () => confirmBulkAction('delete') },
                            ] : []),
                        ];

                    if (bulkButtons.length === 0) return null;

                    return (
                        <Box sx={{ display: 'flex', gap: 1, alignItems: 'center', mr: 2 }}>
                            <Typography variant="body2" sx={{ color: '#64748b', fontWeight: 500 }}>
                                {selected.length} selected
                            </Typography>
                            {bulkButtons.map((btn, i) => (
                                <Button
                                    key={i}
                                    size="small"
                                    variant="outlined"
                                    color={btn.color}
                                    startIcon={btn.icon}
                                    onClick={btn.onClick}
                                    disabled={bulkLoading}
                                >
                                    {btn.label}
                                </Button>
                            ))}
                        </Box>
                    );
                }}
                renderRowActions={({ row }) => {
                    const tenant = row.original;

                    const primaryActions = tenant.is_deleted
                        ? (canRestore ? [{ icon: <RestoreIcon fontSize="small" />, label: 'Restore', onClick: () => onRestore(tenant.id) }] : [])
                        : [
                            ...(canEdit ? [{ icon: <EditIcon fontSize="small" />, label: 'Edit', onClick: () => onEdit(tenant) }] : []),
                            ...(canEdit ? [{ icon: <BlockIcon fontSize="small" />, label: tenant.status === 'Active' ? 'Suspend' : 'Activate', onClick: () => onToggleStatus(tenant) }] : []),
                            ...(canDelete ? [{ icon: <DeleteIcon fontSize="small" />, label: 'Delete', onClick: () => handleRowDelete(row) }] : []),
                          ];

                    return (
                        <Box sx={{ display: 'flex', gap: '0.25rem', alignItems: 'center' }}>
                            {primaryActions.map((action, i) => (
                                <Tooltip key={i} title={action.label}>
                                    <Box
                                        component="button"
                                        onClick={(e) => { e.stopPropagation(); action.onClick(); }}
                                        sx={{
                                            display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                                            border: 'none', bgcolor: 'transparent', cursor: 'pointer',
                                            p: 0.5, borderRadius: 1, color: 'text.secondary',
                                            '&:hover': { bgcolor: 'action.hover' },
                                        }}
                                    >
                                        {action.icon}
                                    </Box>
                                </Tooltip>
                            ))}
                            {!tenant.is_deleted && (
                                <IconButton
                                    size="small"
                                    onClick={(e) => { e.stopPropagation(); setActionMenuAnchor(e.currentTarget); setActionMenuRow(tenant); }}
                                    sx={{
                                        color: canImpersonate ? 'text.secondary' : 'text.disabled',
                                        ...(canImpersonate ? { '&:hover': { bgcolor: 'action.hover' } } : {}),
                                    }}
                                    disabled={!canImpersonate}
                                >
                                    <MoreVertIcon fontSize="small" />
                                </IconButton>
                            )}
                        </Box>
                    );
                }}
                renderEmptyRowsFallback={({ table }) => (
                    <Box sx={{ textAlign: 'center', py: 6 }}>
                        <InboxIcon sx={{ fontSize: 48, color: 'grey.300', mb: 1 }} />
                        <Typography variant="body1" sx={{ fontWeight: 600, color: 'text.secondary' }}>
                            {showDeleted ? 'No deleted tenants found.' : 'No tenants found. Create one to get started.'}
                        </Typography>
                    </Box>
                )}
                muiTablePaperProps={{ elevation: 2, sx: { borderRadius: 2 } }}
                muiTableHeadCellProps={{ sx: { fontWeight: 600, fontSize: '12px', color: 'text.secondary', textTransform: 'uppercase', letterSpacing: '0.05em' } }}
                initialState={{ density: 'compact' }}
                localization={{ toolbarSearchPlaceholder: 'Search by name, email, domain, or ID...' }}
            />

            <Menu
                anchorEl={actionMenuAnchor}
                open={!!actionMenuAnchor}
                onClose={() => { setActionMenuAnchor(null); setActionMenuRow(null); }}
                onClick={() => { setActionMenuAnchor(null); setActionMenuRow(null); }}
                transformOrigin={{ horizontal: 'right', vertical: 'top' }}
                anchorOrigin={{ horizontal: 'right', vertical: 'bottom' }}
            >
                {actionMenuRow && !actionMenuRow.is_deleted && (() => {
                    const extras = typeof rowMenuActions === 'function' ? rowMenuActions(actionMenuRow) : rowMenuActions;
                    const items = extras.filter(a => !a.divider);
                    return [
                        <MenuItem key="impersonate" onClick={() => { setActionMenuAnchor(null); onImpersonate(actionMenuRow); }}>
                            <ListItemIcon><LoginIcon fontSize="small" /></ListItemIcon>
                            <ListItemText>Impersonate</ListItemText>
                        </MenuItem>,
                        ...items.map((action, i) => (
                            <MenuItem key={`extra-${i}`} onClick={() => { setActionMenuAnchor(null); action.onClick?.(actionMenuRow); }}>
                                <ListItemIcon>{action.icon}</ListItemIcon>
                                <ListItemText>{action.label}</ListItemText>
                            </MenuItem>
                        )),
                    ];
                })()}
            </Menu>

            <ConfirmDialog
                open={!!pendingBulkAction}
                title={pendingBulkAction === 'delete' ? 'Delete Tenants' : 'Suspend Tenants'}
                message={`This will ${pendingBulkAction} the selected tenant(s). This action can be undone later. Continue?`}
                confirmLabel={pendingBulkAction === 'delete' ? 'Delete' : 'Suspend'}
                onConfirm={handleConfirmBulkAction}
                onCancel={() => setPendingBulkAction(null)}
            />
        </Box>
    );
}
