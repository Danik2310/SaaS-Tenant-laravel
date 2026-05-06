import React, { useState, useEffect } from 'react';
import { toast } from 'sonner';
import api from '../../../../services/api';
import { FormCard, FormInput, ButtonPrimary, ButtonSecondary, FormActions, SelectInput } from '@/components/FormElements';

export default function PlanModal({ open, onClose, editingPlan, fetchPlans, setError }) {
    const [form, setForm] = useState({ name: '', price: '', features: '' });
    const [loading, setLoading] = useState(false);

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
        setLoading(true);
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
        } finally {
            setLoading(false);
        }
    };

    return (
        <FormCard
            title={editingPlan ? 'Edit Plan' : 'Add Plan'}
            subtitle="Define subscription plan details and features"
            onClose={onClose}
        >
            <FormInput label="Plan Name" required>
                <input
                    type="text"
                    value={form.name}
                    onChange={(e) => setForm({ ...form, name: e.target.value })}
                    placeholder="e.g., Pro Plan"
                />
            </FormInput>

            <FormInput label="Price" required hint="Enter amount in USD">
                <input
                    type="number"
                    value={form.price}
                    onChange={(e) => setForm({ ...form, price: e.target.value })}
                    placeholder="29.99"
                    min="0"
                    step="0.01"
                />
            </FormInput>

            <FormInput label="Features" hint="Comma-separated list of features">
                <textarea
                    value={form.features}
                    onChange={(e) => setForm({ ...form, features: e.target.value })}
                    placeholder="Feature 1, Feature 2, Feature 3"
                    rows={3}
                    style={{
                        width: '100%',
                        padding: '10px 12px',
                        border: '1px solid #e2e8f0',
                        borderRadius: '6px',
                        fontSize: '14px',
                        fontFamily: 'inherit',
                        outline: 'none',
                        resize: 'vertical',
                        minHeight: '80px',
                        boxSizing: 'border-box',
                        transition: 'border-color 0.15s ease, box-shadow 0.15s ease',
                    }}
                    onFocus={(e) => {
                        e.target.style.borderColor = '#3b82f6';
                        e.target.style.boxShadow = '0 0 0 3px rgba(59,130,246,0.1)';
                    }}
                    onBlur={(e) => {
                        e.target.style.borderColor = '#e2e8f0';
                        e.target.style.boxShadow = 'none';
                    }}
                />
            </FormInput>

            <FormActions>
                <ButtonSecondary onClick={onClose} disabled={loading}>Cancel</ButtonSecondary>
                <ButtonPrimary onClick={handleSave} disabled={loading}>
                    {loading ? 'Saving...' : (editingPlan ? 'Update' : 'Create')}
                </ButtonPrimary>
            </FormActions>
        </FormCard>
    );
}