import React, { useEffect, useState } from 'react';
import { toast } from 'sonner';
import api from '../../../services/api';
import DataTable from '@/components/DataTable';
import {
    Box, Chip, Typography, TextField, Select, MenuItem,
    FormControl, InputLabel, Button, Stack, Alert, Paper,
    Dialog, DialogTitle, DialogContent, DialogActions,
    Table, TableBody, TableCell, TableRow,
} from '@mui/material';
import FilterListIcon from '@mui/icons-material/FilterList';
import VisibilityIcon from '@mui/icons-material/Visibility';

export default function ActivityLog() {
    const [activities, setActivities] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [selectedActivity, setSelectedActivity] = useState(null);
    const [detailOpen, setDetailOpen] = useState(false);

    const [logNameFilter, setLogNameFilter] = useState('');
    const [search, setSearch] = useState('');
    const [logNames, setLogNames] = useState([]);

    useEffect(() => {
        fetchLogNames();
        fetchActivities();
    }, []);

    const fetchLogNames = async () => {
        try {
            const res = await api.get('/admin/api/activity-logs/log-names');
            setLogNames(res.data.log_names);
        } catch (err) {
            // silently fail
        }
    };

    const fetchActivities = async () => {
        setLoading(true);
        try {
            const params = new URLSearchParams();
            if (logNameFilter) params.append('log_name', logNameFilter);
            if (search) params.append('search', search);
            const res = await api.get(`/admin/api/activity-logs?${params.toString()}`);
            setActivities(res.data.activities);
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

    const handleFilter = () => {
        fetchActivities();
    };

    const handleClear = () => {
        setLogNameFilter('');
        setSearch('');
    };

    useEffect(() => {
        fetchActivities();
    }, [logNameFilter, search]);

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
        { accessorKey: 'causer_type', header: 'Causer' },
        {
            accessorKey: 'created_at_diff',
            header: 'Time',
            Cell: ({ cell }) => (
                <Typography variant="body2" color="text.secondary" sx={{ fontSize: 12 }}>
                    {cell.getValue()}
                </Typography>
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
