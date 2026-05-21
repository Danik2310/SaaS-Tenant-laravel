import React, { useState } from 'react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import Button from '@mui/material/Button';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableContainer from '@mui/material/TableContainer';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';
import Paper from '@mui/material/Paper';
import Chip from '@mui/material/Chip';
import { toast } from 'sonner';
import api from '../../../../services/api';
import DataTable from '@/Components/DataTable';
import PlanModal from './PlanModal';

export default function PlansTab({ plans, fetchPlans, setError }) {
    const [planModalOpen, setPlanModalOpen] = useState(false);
    const [editingPlan, setEditingPlan] = useState(null);

    const handleEditPlan = (row) => {
        setEditingPlan(row);
        setPlanModalOpen(true);
    };

    const handleDeletePlan = async (row) => {
        if (!confirm('Are you sure you want to delete this plan?')) return;
        try {
            await api.delete(`/admin/api/plans/${row.id}`);
            fetchPlans();
            toast.success('Plan deleted successfully');
        } catch (err) {
            const message = 'Failed to delete plan';
            toast.error(message);
            setError(message);
        }
    };

    const columns = [
        { accessorKey: 'name', header: 'Name' },
        {
            accessorKey: 'price',
            header: 'Price',
            Cell: ({ cell }) => (
                <Typography variant="body2" sx={{ fontWeight: 600, fontSize: 13 }}>
                    ${Number(cell.getValue()).toFixed(2)}
                </Typography>
            ),
        },
        {
            accessorKey: 'max_users',
            header: 'Max Users',
            Cell: ({ cell }) => (
                <Chip
                    label={cell.getValue() ? `${cell.getValue()} users` : 'Unlimited'}
                    size="small"
                    variant="outlined"
                    sx={{ fontWeight: 500, fontSize: 12 }}
                />
            ),
        },
        {
            accessorKey: 'features',
            header: 'Features',
            Cell: ({ cell }) => {
                const features = cell.getValue();
                if (!features) return <Typography variant="body2" sx={{ color: '#94a3b8', fontSize: 13 }}>None</Typography>;
                const list = Array.isArray(features) ? features : [];
                return (
                    <Typography variant="body2" sx={{ fontSize: 13, maxWidth: '300px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                        {list.join(', ')}
                    </Typography>
                );
            },
        },
    ];

    return (
        <Box>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 2, alignItems: 'center' }}>
                <Typography variant="subtitle1" sx={{ fontWeight: 600, color: '#0f172a' }}>
                    Subscription Plans
                </Typography>
                <Button
                    variant="contained"
                    size="small"
                    onClick={() => {
                        setEditingPlan(null);
                        setPlanModalOpen(true);
                    }}
                    sx={{
                        bgcolor: '#22c55e',
                        '&:hover': { bgcolor: '#16a34a' },
                        fontWeight: 600,
                        fontSize: '13px',
                    }}
                >
                    Add Plan
                </Button>
            </Box>

            <DataTable
                columns={columns}
                data={plans}
                onEdit={handleEditPlan}
                onDelete={handleDeletePlan}
                emptyMessage="No plans configured yet."
            />

            <PlanModal
                open={planModalOpen}
                onClose={() => setPlanModalOpen(false)}
                editingPlan={editingPlan}
                fetchPlans={fetchPlans}
                setError={setError}
            />
        </Box>
    );
}
