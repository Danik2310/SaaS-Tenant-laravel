import { useState, useCallback } from 'react';
import { toast } from 'sonner';
import api from '../../../../services/api';

export const useBillingData = () => {
    const [plans, setPlans] = useState([]);
    const [paymentMethods, setPaymentMethods] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    const fetchPlans = useCallback(async () => {
        setLoading(true);
        try {
            const res = await api.get('/admin/api/plans');
            setPlans(res.data.plans || []);
            setError(null);
        } catch (err) {
            const message = 'Failed to fetch plans';
            toast.error(message);
            setError(message);
        } finally {
            setLoading(false);
        }
    }, []);

    const fetchPaymentMethods = useCallback(async () => {
        setLoading(true);
        try {
            const res = await api.get('/admin/api/payment-methods');
            setPaymentMethods(res.data.methods || []);
            setError(null);
        } catch (err) {
            const message = 'Failed to fetch payment methods';
            toast.error(message);
            setError(message);
        } finally {
            setLoading(false);
        }
    }, []);

    return {
        plans,
        paymentMethods,
        loading,
        error,
        setError,
        fetchPlans,
        fetchPaymentMethods,
    };
};