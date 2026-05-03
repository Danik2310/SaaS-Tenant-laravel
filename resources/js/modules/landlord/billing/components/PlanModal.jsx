import React, { useState, useEffect } from 'react';
import {
    Dialog,
    DialogTitle,
    DialogContent,
    DialogActions,
    Button,
    TextField,
} from '@mui/material';
import { toast } from 'sonner';
import api from '../../../../services/api';

export default function PlanModal({ open, onClose, editingPlan, fetchPlans, setError }) {
    const [form, setForm] = useState({ name: '', price: '', features: '' });

    useEffect(() => {
        if (editingPlan) {
            setForm({
                name: editingPlan.name,
                price: editingPlan.price,
                features: Array.isArray(editingPlan.features) ? editingPlan.features.join(', ') : editingPlan.features,
            });
        } else {
            setForm({ name: '', price: '', features: '' });
        }
    }, [editingPlan, open]);

    const handleSave = async () => {
        // Validación básica
        if (!form.name || !form.price) {
            const message = 'Name and price are required';
            toast.error(message);
            setError(message);
            return;
        }
        if (parseFloat(form.price) < 0) {
            const message = 'Price must be positive';
            toast.error(message);
            setError(message);
            return;
        }
        try {
            if (editingPlan) {
                await api.put(`/admin/api/plans/${editingPlan.id}`, form);
                toast.success('Plan updated successfully');
            } else {
                await api.post('/admin/api/plans', form);
                toast.success('Plan created successfully');
            }
            onClose();
            fetchPlans();
        } catch (err) {
            const message = 'Failed to save plan. Please check your permissions.';
            toast.error(message);
            setError(message);
        }
    };

    return (
        <Dialog open={open} onClose={onClose} maxWidth="sm" fullWidth>
            <DialogTitle>{editingPlan ? 'Edit Plan' : 'Add Plan'}</DialogTitle>
            <DialogContent>
                <TextField
                    fullWidth
                    label="Name"
                    value={form.name}
                    onChange={(e) => setForm({ ...form, name: e.target.value })}
                    sx={{ mb: 2 }}
                />
                <TextField
                    fullWidth
                    label="Price"
                    type="number"
                    value={form.price}
                    onChange={(e) => setForm({ ...form, price: e.target.value })}
                    sx={{ mb: 2 }}
                />
                <TextField
                    fullWidth
                    label="Features (comma-separated)"
                    value={form.features}
                    onChange={(e) => setForm({ ...form, features: e.target.value })}
                />
            </DialogContent>
            <DialogActions>
                <Button onClick={onClose}>Cancel</Button>
                <Button onClick={handleSave} variant="contained">Save</Button>
            </DialogActions>
        </Dialog>
    );
}