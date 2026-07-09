import React, { useEffect, useState } from 'react';
import { PlansTab, PaymentMethodsTab } from './components';
import { useBillingData } from './hooks';
import { toast } from 'sonner';

export default function Plans({ user }) {
    const [tabValue, setTabValue] = useState('plans');
    const { plans, paymentMethods, error, setError, fetchPlans, fetchPaymentMethods } = useBillingData();

    useEffect(() => {
        fetchPlans();
        fetchPaymentMethods();
    }, [fetchPlans, fetchPaymentMethods]);

    return (
        <div style={{ minHeight: '100vh', background: '#f8fafc', display: 'flex' }}>
            <div style={{ flex: 1 }}>
                <header style={{
                    background: 'white',
                    padding: '16px 32px',
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    borderBottom: '1px solid #e2e8f0',
                    boxShadow: '0 1px 2px rgba(0,0,0,0.04)',
                }}>
                    <div>
                        <h1 style={{ margin: 0, fontSize: '20px', fontWeight: 700, color: '#0f172a' }}>
                            Infrastructure & Plans
                        </h1>
                        <p style={{ margin: '4px 0 0 0', fontSize: '13px', color: '#64748b' }}>
                            {user?.name}
                        </p>
                    </div>
                </header>

                <main style={{ padding: '24px 32px' }}>
                    <div style={{ maxWidth: '1280px', margin: '0 auto' }}>
                        {error && (
                            <div style={{
                                background: '#fef2f2',
                                color: '#dc2626',
                                padding: '12px 16px',
                                borderRadius: '6px',
                                marginBottom: '20px',
                                border: '1px solid #fecaca',
                            }}>
                                {error}
                            </div>
                        )}

                        <div style={{ marginBottom: '20px' }}>
                            <div style={{
                                display: 'flex',
                                gap: '4px',
                                background: '#f1f5f9',
                                padding: '4px',
                                borderRadius: '8px',
                                width: 'fit-content',
                            }}>
                                <button
                                    onClick={() => setTabValue('plans')}
                                    style={{
                                        padding: '8px 20px',
                                        background: tabValue === 'plans' ? 'white' : 'transparent',
                                        color: tabValue === 'plans' ? '#0f172a' : '#64748b',
                                        border: 'none',
                                        borderRadius: '6px',
                                        cursor: 'pointer',
                                        fontSize: '13px',
                                        fontWeight: 600,
                                        boxShadow: tabValue === 'plans' ? '0 1px 2px rgba(0,0,0,0.05)' : 'none',
                                        transition: 'all 0.15s ease',
                                    }}
                                >
                                    Subscription Plans
                                </button>
                                <button
                                    onClick={() => setTabValue('payment-methods')}
                                    style={{
                                        padding: '8px 20px',
                                        background: tabValue === 'payment-methods' ? 'white' : 'transparent',
                                        color: tabValue === 'payment-methods' ? '#0f172a' : '#64748b',
                                        border: 'none',
                                        borderRadius: '6px',
                                        cursor: 'pointer',
                                        fontSize: '13px',
                                        fontWeight: 600,
                                        boxShadow: tabValue === 'payment-methods' ? '0 1px 2px rgba(0,0,0,0.05)' : 'none',
                                        transition: 'all 0.15s ease',
                                    }}
                                >
                                    Payment Methods
                                </button>
                            </div>
                        </div>

                        {tabValue === 'plans' && (
                            <PlansTab
                                plans={plans}
                                fetchPlans={fetchPlans}
                                setError={setError}
                            />
                        )}

                        {tabValue === 'payment-methods' && (
                            <PaymentMethodsTab
                                paymentMethods={paymentMethods}
                                fetchPaymentMethods={fetchPaymentMethods}
                                setError={setError}
                            />
                        )}
                    </div>
                </main>
            </div>
        </div>
    );
}
