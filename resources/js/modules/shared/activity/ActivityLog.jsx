import React, { useEffect, useState } from 'react';
import { toast } from 'sonner';
import api from '../../../services/api';
import DataTable from '@/Components/DataTable';
import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';
import Typography from '@mui/material/Typography';
import TextField from '@mui/material/TextField';
import Select from '@mui/material/Select';
import MenuItem from '@mui/material/MenuItem';
import FormControl from '@mui/material/FormControl';
import InputLabel from '@mui/material/InputLabel';
import Button from '@mui/material/Button';
import Stack from '@mui/material/Stack';
import Alert from '@mui/material/Alert';
import Paper from '@mui/material/Paper';
import Dialog from '@mui/material/Dialog';
import DialogTitle from '@mui/material/DialogTitle';
import DialogContent from '@mui/material/DialogContent';
import DialogActions from '@mui/material/DialogActions';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableRow from '@mui/material/TableRow';
import Tooltip from '@mui/material/Tooltip';
import FilterListIcon from '@mui/icons-material/FilterList';
import RefreshIcon from '@mui/icons-material/Refresh';
import PersonIcon from '@mui/icons-material/Person';

export default function ActivityLog() {
    const [activities, setActivities] = useState([]);
    const [meta, setMeta] = useState({ current_page: 1, last_page: 1, per_page: 5, total: 0 });
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [selectedActivity, setSelectedActivity] = useState(null);
    const [detailOpen, setDetailOpen] = useState(false);

    const [logNameFilter, setLogNameFilter] = useState('');
    const [search, setSearch] = useState('');
    const [logNames, setLogNames] = useState([]);
    const [causerId, setCauserId] = useState('');
    const [causers, setCausers] = useState([]);
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');

    const fetchLogNames = async () => {
        try {
            const res = await api.get('/admin/api/activity-logs/log-names');
            setLogNames(res.data.log_names);
        } catch (err) {
            // silently fail
        }
    };

    const fetchCausers = async () => {
        try {
            const res = await api.get('/admin/api/activity-logs/causers');
            setCausers(res.data.causers);
        } catch (err) {
            // silently fail
        }
    };

    const fetchActivities = async (page = 1) => {
        setLoading(true);
        try {
            const params = new URLSearchParams();
            params.append('page', page);
            if (logNameFilter) params.append('log_name', logNameFilter);
            if (search) params.append('search', search);
            if (causerId) params.append('causer_id', causerId);
            if (dateFrom) params.append('date_from', dateFrom);
            if (dateTo) params.append('date_to', dateTo);
            const res = await api.get(`/admin/api/activity-logs?${params.toString()}`);
            setActivities(res.data.activities);
            setMeta(res.data.meta);
            setError(null);
        } catch (err) {
            const message = 'Failed to fetch activity logs';
            toast.error(message);
            setError(message);
        } finally {
            setLoading(false);
        }
    };

    const handleViewDetails = async (row) => {
        try {
            const res = await api.get(`/admin/api/activity-logs/${row.id}`);
            setSelectedActivity(res.data.activity);
            setDetailOpen(true);
        } catch (err) {
            toast.error('Failed to load activity details');
        }
    };

    const handleClear = () => {
        setLogNameFilter('');
        setSearch('');
        setCauserId('');
        setDateFrom('');
        setDateTo('');
    };

    useEffect(() => {
        fetchLogNames();
        fetchCausers();
        fetchActivities(1);
    }, []);

    useEffect(() => {
        fetchActivities(1);
    }, [logNameFilter, search, causerId, dateFrom, dateTo]);

    const handlePageChange = (newPage) => {
        fetchActivities(newPage + 1);
    };

    const handleRowsPerPageChange = (newPerPage) => {
        fetchActivities(1);
    };

    const columns = [
        { accessorKey: 'id', header: 'ID' },
        { accessorKey: 'description', header: 'Description' },
        {
            accessorKey: 'log_name',
            header: 'Log',
            Cell: ({ cell }) => (
                <Chip
                    label={cell.getValue()}
                    size="small"
                    sx={{ fontWeight: 600, bgcolor: '#f1f5f9', color: '#475569', textTransform: 'capitalize' }}
                />
            ),
        },
        { accessorKey: 'subject_type', header: 'Subject' },
        {
            accessorKey: 'causer_type',
            header: 'Causer',
            Cell: ({ cell }) => (
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
                    <PersonIcon sx={{ fontSize: 14, color: '#94a3b8' }} />
                    <Typography variant="body2" sx={{ fontSize: 13 }}>
                        {cell.getValue() || 'System'}
                    </Typography>
                </Box>
            ),
        },
        {
            accessorKey: 'created_at_diff',
            header: 'Time',
            Cell: ({ cell }) => (
                <Tooltip title={cell.row.created_at}>
                    <Typography variant="body2" color="text.secondary" sx={{ fontSize: 12 }}>
                        {cell.getValue()}
                    </Typography>
                </Tooltip>
            ),
        },
    ];

    return (
        <Box>
            <Paper sx={{ p: 2, mb: 3, borderRadius: 2, boxShadow: '0 1px 3px rgba(0,0,0,0.06)' }}>
                <Stack direction="row" spacing={2} alignItems="center" flexWrap="wrap">
                    <FormControl size="small" sx={{ minWidth: 160 }}>
                        <InputLabel>Log Type</InputLabel>
                        <Select
                            value={logNameFilter}
                            label="Log Type"
                            onChange={(e) => setLogNameFilter(e.target.value)}
                        >
                            <MenuItem value="">All</MenuItem>
                            {logNames.map((name) => (
                                <MenuItem key={name} value={name}>{name}</MenuItem>
                            ))}
                        </Select>
                    </FormControl>
                    <FormControl size="small" sx={{ minWidth: 180 }}>
                        <InputLabel>Admin User</InputLabel>
                        <Select
                            value={causerId}
                            label="Admin User"
                            onChange={(e) => setCauserId(e.target.value)}
                        >
                            <MenuItem value="">All</MenuItem>
                            {causers.map((u) => (
                                <MenuItem key={u.id} value={u.id}>{u.name}</MenuItem>
                            ))}
                        </Select>
                    </FormControl>
                    <TextField
                        size="small"
                        type="date"
                        label="From"
                        value={dateFrom}
                        onChange={(e) => setDateFrom(e.target.value)}
                        InputLabelProps={{ shrink: true }}
                        sx={{ minWidth: 150 }}
                    />
                    <TextField
                        size="small"
                        type="date"
                        label="To"
                        value={dateTo}
                        onChange={(e) => setDateTo(e.target.value)}
                        InputLabelProps={{ shrink: true }}
                        sx={{ minWidth: 150 }}
                    />
                    <TextField
                        size="small"
                        label="Search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        sx={{ minWidth: 200 }}
                    />
                    <Button variant="outlined" size="small" onClick={handleClear} startIcon={<FilterListIcon />}>
                        Clear
                    </Button>
                </Stack>
            </Paper>

            {error && (
                <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>
            )}

            <DataTable
                columns={columns}
                data={activities}
                loading={loading}
                onView={handleViewDetails}
                emptyMessage="No activity logs yet."
                total={meta.total}
                page={meta.current_page - 1}
                rowsPerPage={meta.per_page}
                onPageChange={handlePageChange}
                onRowsPerPageChange={handleRowsPerPageChange}
            />

            <Dialog open={detailOpen} onClose={() => setDetailOpen(false)} maxWidth="md" fullWidth>
                <DialogTitle sx={{ fontWeight: 700 }}>Activity Details</DialogTitle>
                <DialogContent>
                    {selectedActivity && (
                        <Table size="small">
                            <TableBody>
                                <TableRow>
                                    <TableCell sx={{ fontWeight: 600, color: '#475569' }}>ID</TableCell>
                                    <TableCell>{selectedActivity.id}</TableCell>
                                </TableRow>
                                <TableRow>
                                    <TableCell sx={{ fontWeight: 600, color: '#475569' }}>Description</TableCell>
                                    <TableCell>{selectedActivity.description}</TableCell>
                                </TableRow>
                                <TableRow>
                                    <TableCell sx={{ fontWeight: 600, color: '#475569' }}>Log Name</TableCell>
                                    <TableCell>{selectedActivity.log_name}</TableCell>
                                </TableRow>
                                <TableRow>
                                    <TableCell sx={{ fontWeight: 600, color: '#475569' }}>Subject</TableCell>
                                    <TableCell>{selectedActivity.subject_type || 'N/A'} #{selectedActivity.subject_id || 'N/A'}</TableCell>
                                </TableRow>
                                <TableRow>
                                    <TableCell sx={{ fontWeight: 600, color: '#475569' }}>Causer</TableCell>
                                    <TableCell>{selectedActivity.causer_type || 'System'} #{selectedActivity.causer_id || '-'}</TableCell>
                                </TableRow>
                                <TableRow>
                                    <TableCell sx={{ fontWeight: 600, color: '#475569' }}>Created</TableCell>
                                    <TableCell>{selectedActivity.created_at}</TableCell>
                                </TableRow>
                                <TableRow>
                                    <TableCell sx={{ fontWeight: 600, color: '#475569', verticalAlign: 'top' }}>Properties</TableCell>
                                    <TableCell>
                                        <Box sx={{
                                            bgcolor: '#f8fafc',
                                            p: 2,
                                            borderRadius: 1,
                                            fontFamily: 'monospace',
                                            fontSize: 12,
                                            maxHeight: 300,
                                            overflow: 'auto',
                                            whiteSpace: 'pre-wrap',
                                        }}>
                                            {selectedActivity.properties
                                                ? JSON.stringify(selectedActivity.properties, null, 2)
                                                : 'None'}
                                        </Box>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    )}
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setDetailOpen(false)}>Close</Button>
                </DialogActions>
            </Dialog>
        </Box>
    );
}
