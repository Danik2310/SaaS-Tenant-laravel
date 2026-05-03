import React, { useState } from 'react';
import {
    Box,
    Typography,
    Button,
    Table,
    TableBody,
    TableCell,
    TableContainer,
    TableHead,
    TableRow,
    Paper,
    IconButton,
} from '@mui/material';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import AddIcon from '@mui/icons-material/Add';
import { toast } from 'sonner';
import api from '../../../../services/api';
import PlanModal from './PlanModal';

export default function PlansTab({ plans, fetchPlans, setError }) {
    const [planModalOpen, setPlanModalOpen] = useState(false);
    const [editingPlan, setEditingPlan] = useState(null);

    const handleEditPlan = (plan) => {
        setEditingPlan(plan);
        setPlanModalOpen(true);
    };

    const handleDeletePlan = async (id) => {
        if (confirm('Are you sure you want to delete this plan?')) {
            try {
                await api.delete(`/admin/api/plans/${id}`);
                fetchPlans();
                toast.success('Plan deleted successfully');
            } catch (err) {
                const message = 'Failed to delete plan';
                toast.error(message);
                setError(message);
            }
        }
    };

    return (
        <Box>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 2 }}>
                <Typography variant="h6">Manage Subscription Plans</Typography>
                <Button
                    variant="contained"
                    startIcon={<AddIcon />}
                    onClick={() => {
                        setEditingPlan(null);
                        setPlanModalOpen(true);
                    }}
                >
                    Add Plan
                </Button>
            </Box>

            <TableContainer component={Paper}>
                <Table>
                    <TableHead>
                        <TableRow>
                            <TableCell>Name</TableCell>
                            <TableCell>Price</TableCell>
                            <TableCell>Features</TableCell>
                            <TableCell>Actions</TableCell>
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {plans.map((plan) => (
                            <TableRow key={plan.id}>
                                <TableCell>{plan.name}</TableCell>
                                <TableCell>${plan.price}</TableCell>
                                <TableCell>{Array.isArray(plan.features) ? plan.features.join(', ') : plan.features}</TableCell>
                                <TableCell>
                                    <IconButton onClick={() => handleEditPlan(plan)}>
                                        <EditIcon />
                                    </IconButton>
                                    <IconButton onClick={() => handleDeletePlan(plan.id)}>
                                        <DeleteIcon />
                                    </IconButton>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </TableContainer>

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