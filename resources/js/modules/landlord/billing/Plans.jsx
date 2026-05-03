import React, { useEffect, useState } from 'react';
import {
    Box,
    Tabs,
    Tab,
    Typography,
} from '@mui/material';
import { PlansTab, PaymentMethodsTab } from './components';
import { useBillingData } from './hooks';

export default function Plans() {
    const [tabValue, setTabValue] = useState(0); // 0: Planes, 1: Métodos de Pago
    const { plans, paymentMethods, error, setError, fetchPlans, fetchPaymentMethods } = useBillingData();

    useEffect(() => {
        fetchPlans();
        fetchPaymentMethods();
    }, [fetchPlans, fetchPaymentMethods]);

    return (
        <Box sx={{ p: 3, backgroundColor: 'white', borderRadius: 2 }}>
            <Typography variant="h4" gutterBottom>
                Plans & Payment Settings
            </Typography>

            {error && (
                <Typography color="error" sx={{ mb: 2 }}>
                    {error}
                </Typography>
            )}

            <Tabs value={tabValue} onChange={(e, newValue) => setTabValue(newValue)} sx={{ mb: 3 }}>
                <Tab label="Subscription Plans" />
                <Tab label="Payment Methods" />
            </Tabs>

            {tabValue === 0 && (
                <PlansTab
                    plans={plans}
                    fetchPlans={fetchPlans}
                    setError={setError}
                />
            )}

            {tabValue === 1 && (
                <PaymentMethodsTab
                    paymentMethods={paymentMethods}
                    fetchPaymentMethods={fetchPaymentMethods}
                    setError={setError}
                />
            )}
        </Box>
    );
}
