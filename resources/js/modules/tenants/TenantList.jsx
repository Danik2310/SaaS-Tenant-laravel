import { useState, useEffect, useMemo, useCallback, useRef } from 'react';
import { MaterialReactTable } from 'material-react-table';
import axios from 'axios';
import api from '@/services/api';
import { useAuthContext } from '@/context/AuthContext';
import { tableMotionSx, rowMotionKeyframes, animatedRowSx, tableHeadCellSx, iconButtonMotionSx } from '@/Components/tableMotion';
import useAnimatedTableRows from '@/hooks/useAnimatedTableRows';
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
import ScienceIcon from '@mui/icons-material/Science';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import CreditCardIcon from '@mui/icons-material/CreditCard';
import SyncIcon from '@mui/icons-material/Sync';
import { toast } from 'sonner';
import ConfirmDialog from '@/Components/ConfirmDialog';

const formatFilterDate = (value) => {
    if (!value) return '';
    if (typeof value === 'string') return value.slice(0, 10);
    const date = typeof value.toISOString === 'function' ? value : new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    return date.toISOString().slice(0, 10);
};

export default function TenantList({
    refreshTrigger,
    onAdd,
    onDelete,
    onEdit,
    onImpersonate,
    onToggleStatus,
    onActivateTrial,
    onRestore,
    onSelectionChange,
    rowMenuActions = [],
    onRefreshPaymentCard,
    onManagePaymentCard,
    onBulkAction,
    bulkLoading = false,
    pendingRowIds = [],
    initialSearch = '',
    initialShowDeleted = false,
}) {
    const { permissions = [] } = useAuthContext();
    const canCreate = permissions.includes('create tenants');
    const canEdit = permissions.includes('edit tenants');
    const canDelete = permissions.includes('delete tenants');
    const canRestore = permissions.includes('restore tenants');
    const canImpersonate = permissions.includes('impersonate tenants');
    const canManagePaymentCard = permissions.includes('edit payment methods');

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
    const abortRef = useRef(null);

    useEffect(() => {
        const canFetchPlans = ['view plans', 'view subscriptions', 'create tenants', 'edit tenants']
            .some(p => permissions.includes(p));
        if (!canFetchPlans) return;
        api.get('/admin/api/plans-list').then(r => {
            setPlans(r.data.plans ?? []);
        }).catch(() => {});
    }, [permissions]);

    const fetchTenants = useCallback(async () => {
        // A newer request (or unmount) supersedes the previous one.
        abortRef.current?.abort();
        const controller = new AbortController();
        abortRef.current = controller;

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
                    if (f.value[0]) params.set('date_from', formatFilterDate(f.value[0]));
                    if (f.value[1]) params.set('date_to', formatFilterDate(f.value[1]));
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

            const response = await api.get(`/admin/api/tenants?${params}`, { signal: controller.signal });
            if (controller.signal.aborted) return;
            setData(response.data.tenants);
            setTotal(response.data.total);
        } catch (err) {
            // Aborted requests are superseded or unmounted, not failures.
            if (controller.signal.aborted || axios.isCancel(err)) return;
            const message = 'Failed to fetch tenants';
            toast.error(message);
            setError(message);
        } finally {
            // A newer request owns the loading state by now.
            if (!controller.signal.aborted) setLoading(false);
        }
    }, [showDeleted, pagination, globalFilter, columnFilters, sorting]);

    useEffect(() => {
        fetchTenants();
        return () => abortRef.current?.abort();
    }, [fetchTenants, refreshTrigger]);

    const animatedRows = useAnimatedTableRows(data, (row) => String(row.id), pendingRowIds);

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
        const destructive = action === 'delete' || action === 'suspend' || action === 'start_trial';
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
                <Typography variant="body2" sx={{ fontFamily: 'monospace', fontSize: 13, color: 'text.secondary', fontWeight: 600 }}>
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
                        <Chip label="Deleted" size="small" sx={{ bgcolor: 'background.default', color: 'text.secondary', fontWeight: 600, fontStyle: 'italic' }} />
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
                                sx={{ bgcolor: 'warning.main', color: 'warning.contrastText', fontWeight: 600 }}
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
                                bgcolor: status === 'Active' ? 'success.main' : 'error.main',
                                color: status === 'Active' ? 'success.contrastText' : 'error.contrastText',
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
                        <Typography variant="body2" sx={{ color: 'text.disabled', fontSize: 13 }}>
                            {isDeleted ? '—' : 'No Plan'}
                        </Typography>
                    );
                }
                return (
                    <Chip
                        icon={<WorkspacePremiumIcon sx={{ fontSize: 14 }} />}
                        label={planName}
                        size="small"
                        sx={{ bgcolor: 'action.hover', color: 'primary.dark', fontWeight: 600, border: '1px solid', borderColor: 'primary.light' }}
                    />
                );
            },
        },
        {
            accessorKey: 'payment_card',
            header: 'Payment card',
            enableColumnFilter: false,
            enableSorting: false,
            Cell: ({ cell, row }) => {
                const card = cell.getValue() || {};
                const isDeleted = row.original.is_deleted;

                let label = '\u2014';
                if (!isDeleted) {
                    if (!card.linked) label = 'Not linked';
                    else if (!card.last4) label = 'No card';
                    else label = `${card.brand || 'Card'} \u2022\u2022\u2022\u2022 ${card.last4}`;
                }

                const expiry = card.exp_month && card.exp_year
                    ? `${String(card.exp_month).padStart(2, '0')}/${card.exp_year}`
                    : '';
                const detail = isDeleted
                    ? 'Tenant deleted'
                    : !card.linked
                        ? 'No Stripe customer yet'
                        : !card.last4
                            ? 'No card saved in Stripe'
                            : [
                                'Default saved card',
                                expiry ? `expires ${expiry}` : null,
                                card.card_count > 1 ? `${card.card_count} cards on file` : null,
                                card.synced_at ? `synced ${new Date(card.synced_at).toLocaleString()}` : null,
                            ].filter(Boolean).join(' \u00b7 ');

                return (
                    <Tooltip title={detail}>
                        <Box data-testid="tenant-payment-card" sx={{ display: 'inline-flex', flexDirection: 'column', gap: 0.25 }}>
                            <Typography variant="body2" sx={{ fontSize: 13, color: isDeleted || !card.last4 ? 'text.disabled' : 'text.primary', fontWeight: card.last4 ? 600 : 400 }}>
                                {label}
                            </Typography>
                            {!isDeleted && card.linked && card.last4 && card.card_count > 1 && (
                                <Typography variant="caption" sx={{ fontSize: 11, color: 'text.secondary' }}>
                                    +{card.card_count - 1} more
                                </Typography>
                            )}
                        </Box>
                    </Tooltip>
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
                const planName = row.original.subscription_plan_name;
                const durationMonths = row.original.subscription_duration_months;
                const endsAt = cell.getValue();
                const endsDate = endsAt ? new Date(endsAt) : null;
                const validEnd = endsDate && !Number.isNaN(endsDate.getTime()) ? endsDate : null;
                const isExpired = validEnd && validEnd.getTime() < Date.now();
                const planInfo = planName ? (typeof durationMonths === 'number' ? `${planName} \u00b7 ${durationMonths} mo` : planName) : '';
                const formatDate = (date) => date.toLocaleString(undefined, {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                });

                if (isDeleted || !hasActive) {
                    return (
                        <Typography variant="body2" sx={{ color: 'text.disabled', fontSize: 13 }}>
                            {'\u2014'}
                        </Typography>
                    );
                }
                if (!validEnd) {
                    return (
                        <Tooltip title={`Subscription has no end date - ongoing${planInfo ? ` (${planInfo})` : ''}`}>
                            <Box>
                                <Typography variant="body2" sx={{ color: 'text.disabled', fontSize: 13 }}>
                                    Ongoing
                                </Typography>
                                {planInfo && (
                                    <Typography variant="caption" sx={{ color: 'text.secondary', fontSize: 11, display: 'block' }}>
                                        {planInfo}
                                    </Typography>
                                )}
                            </Box>
                        </Tooltip>
                    );
                }
                return (
                    <Tooltip title={`${isExpired ? 'Subscription expired' : 'Subscription ends'} on ${formatDate(validEnd)}${planInfo ? ` \u00b7 allocated by ${planInfo}` : ''}`}>
                        <Box>
                            <Typography
                                variant="body2"
                                sx={{ color: isExpired ? 'warning.dark' : 'text.primary', fontWeight: isExpired ? 600 : 400, fontSize: 13 }}
                            >
                                {formatDate(validEnd)}
                            </Typography>
                            {planInfo && (
                                <Typography variant="caption" sx={{ color: 'text.secondary', fontSize: 11, display: 'block' }}>
                                    {planInfo}
                                </Typography>
                            )}
                        </Box>
                    </Tooltip>
                );
            },
        },
        {
            accessorKey: 'created_at',
            header: 'Created',
            filterVariant: 'date-range',
            Cell: ({ cell }) => (
                <Typography variant="body2" sx={{ color: 'text.secondary', fontSize: 13 }}>
                    {new Date(cell.getValue()).toLocaleDateString()}
                </Typography>
            ),
        },
    ], [plans]);

    const handleSaveRow = useCallback(async ({ exitEditingMode, row, values }) => {
        try {
            const { name, email } = values;
            await api.put(`/admin/api/tenants/${row.original.id}`, { name, email });
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
            <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 2, alignItems: 'center', flexWrap: 'wrap', gap: 1 }}>
                <Typography variant="subtitle1" sx={{ fontWeight: 600, color: 'text.primary' }}>
                    Tenant Management
                </Typography>
                <Box sx={{ display: 'flex', gap: 1, alignItems: 'center' }}>
                    <FormControlLabel
                        control={<Switch size="small" checked={showDeleted} onChange={handleToggleDeleted} />}
                        label={<Typography variant="caption" sx={{ color: 'text.secondary' }}>Show deleted</Typography>}
                        sx={{ mr: 0 }}
                    />
                    {canCreate && (
                        <Button
                            variant="contained"
                            size="small"
                            onClick={onAdd}
                            sx={{
                                bgcolor: 'primary.main',
                                '&:hover': { bgcolor: 'primary.dark' },
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
                data={animatedRows.rows}
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
                enableRowActions
                getRowId={(row) => String(row.id)}
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
                                {
                                    label: 'Activate', color: 'success', onClick: () => {
                                        const selectedRows = table.getSelectedRowModel().flatRows;
                                        const trialTenants = selectedRows.filter(r => r.original.status === 'Trial').map(r => r.original);
                                        const nonTrialIds = selectedRows.filter(r => r.original.status !== 'Trial').map(r => r.original.id);
                                        if (trialTenants.length > 0 && onActivateTrial) {
                                            onActivateTrial(trialTenants);
                                        }
                                        if (nonTrialIds.length > 0) {
                                            onBulkAction?.('activate', nonTrialIds);
                                        }
                                    },
                                },
                                { label: 'Start Trial', color: 'info', icon: <ScienceIcon sx={{ fontSize: 16 }} />, onClick: () => confirmBulkAction('start_trial') },
                            ] : []),
                            ...(canDelete ? [
                                { label: 'Delete', color: 'error', onClick: () => confirmBulkAction('delete') },
                            ] : []),
                        ];

                    if (bulkButtons.length === 0) return null;

                    return (
                        <Box sx={{ display: 'flex', gap: 1, alignItems: 'center', mr: 2, flexWrap: 'wrap' }}>
                            <Typography variant="body2" sx={{ color: 'text.secondary', fontWeight: 500 }}>
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
                            ...(canEdit ? [{ icon: <BlockIcon fontSize="small" />, label: tenant.status === 'Active' ? 'Suspend' : 'Activate', onClick: () => tenant.status === 'Trial' && onActivateTrial ? onActivateTrial([tenant]) : onToggleStatus(tenant) }] : []),
                            ...(canManagePaymentCard ? [
                                {
                                    icon: <SyncIcon fontSize="small" />,
                                    label: 'Refresh payment card',
                                    testId: 'tenant-card-refresh',
                                    disabled: !tenant.payment_card?.linked,
                                    onClick: () => onRefreshPaymentCard?.(tenant),
                                },
                                {
                                    icon: <CreditCardIcon fontSize="small" />,
                                    label: 'Manage payment card',
                                    testId: 'tenant-card-manage',
                                    disabled: !tenant.payment_card?.linked,
                                    onClick: () => onManagePaymentCard?.(tenant),
                                },
                            ] : []),
                            ...(canDelete ? [{ icon: <DeleteIcon fontSize="small" />, label: 'Delete', onClick: () => handleRowDelete(row) }] : []),
                          ];

                    return (
                        <Box sx={{ display: 'flex', gap: '0.25rem', alignItems: 'center' }}>
                            {primaryActions.map((action, i) => (
                                <Tooltip key={i} title={action.label}>
                                    <Box
                                        component="button"
                                        data-testid={action.testId}
                                        disabled={action.disabled}
                                        onClick={(e) => { e.stopPropagation(); if (!action.disabled) action.onClick(); }}
                                        sx={{
                                            ...iconButtonMotionSx,
                                            cursor: action.disabled ? 'not-allowed' : 'pointer',
                                            opacity: action.disabled ? 0.4 : 1,
                                            ...(action.disabled ? { '&:hover': {} } : {}),
                                        }}
                                    >
                                        {action.icon}
                                    </Box>
                                </Tooltip>
                            ))}
                            {!tenant.is_deleted && (
                                <Tooltip title="More actions">
                                    <span>
                                        <IconButton
                                            size="small"
                                            aria-label="More actions"
                                            onClick={(e) => { e.stopPropagation(); setActionMenuAnchor(e.currentTarget); setActionMenuRow(tenant); }}
                                            sx={{
                                                ...iconButtonMotionSx,
                                                color: canImpersonate ? 'text.secondary' : 'text.disabled',
                                                ...(canImpersonate ? {} : { cursor: 'default', '&:hover': {} }),
                                            }}
                                            disabled={!canImpersonate}
                                        >
                                            <MoreVertIcon fontSize="small" />
                                        </IconButton>
                                    </span>
                                </Tooltip>
                            )}
                        </Box>
                    );
                }}
                renderEmptyRowsFallback={({ table }) => (
                    <Box sx={{ textAlign: 'center', py: 6 }}>
                        <InboxIcon sx={{ fontSize: 48, color: 'text.disabled', mb: 1 }} />
                        <Typography variant="body1" sx={{ fontWeight: 600, color: 'text.secondary' }}>
                            {showDeleted ? 'No deleted tenants found.' : 'No tenants found. Create one to get started.'}
                        </Typography>
                    </Box>
                )}
                muiTablePaperProps={{ elevation: 0, sx: { borderRadius: 2, border: '1px solid', borderColor: 'divider', boxShadow: 'none', ...tableMotionSx, ...rowMotionKeyframes } }}
                muiTableHeadCellProps={{ sx: tableHeadCellSx }}
                muiTableBodyRowProps={({ row }) => {
                    const key = String(row.original.id);
                    const phase = animatedRows.phases[key];
                    return {
                        'data-row-phase': phase || undefined,
                        sx: animatedRowSx(phase, animatedRows.delays[key]),
                    };
                }}
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
                    const canStartTrial = canEdit && actionMenuRow.status === 'Active';
                    return [
                        <MenuItem key="impersonate" onClick={() => { setActionMenuAnchor(null); onImpersonate(actionMenuRow); }}>
                            <ListItemIcon><LoginIcon fontSize="small" /></ListItemIcon>
                            <ListItemText>Impersonate</ListItemText>
                        </MenuItem>,
                        ...canStartTrial ? [
                            <MenuItem key="start-trial" onClick={() => { setActionMenuAnchor(null); onBulkAction('start_trial', [actionMenuRow.id]); }}>
                                <ListItemIcon><ScienceIcon fontSize="small" /></ListItemIcon>
                                <ListItemText>Start Trial</ListItemText>
                            </MenuItem>,
                        ] : [],
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
                title={pendingBulkAction === 'delete' ? 'Delete Tenants' : pendingBulkAction === 'start_trial' ? 'Start Trial' : 'Suspend Tenants'}
                message={pendingBulkAction === 'start_trial'
                    ? 'This will switch the selected tenant(s) to the Trial plan with full access for the configured trial period. Their current plan will be replaced. Continue?'
                    : `This will ${pendingBulkAction} the selected tenant(s). This action can be undone later. Continue?`}
                confirmLabel={pendingBulkAction === 'delete' ? 'Delete' : pendingBulkAction === 'start_trial' ? 'Start Trial' : 'Suspend'}
                onConfirm={handleConfirmBulkAction}
                onCancel={() => setPendingBulkAction(null)}
            />
        </Box>
    );
}
