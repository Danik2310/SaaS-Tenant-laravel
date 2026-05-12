import React from 'react';
import { createRoot } from 'react-dom/client';
import { Toaster } from 'sonner';
import Login from '../modules/landlord/Login';
import Dashboard from '../modules/landlord/Dashboard';
import useAuth from '../hooks/useAuth';

export default function LandlordApp() {
    const { user, permissions, loading, setUser } = useAuth();

    if (loading) {
        return (
            <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '100vh' }}>
                <div style={{ fontSize: '18px', color: '#666' }}>Loading...</div>
            </div>
        );
    }

    return user ? (
        <>
            <Dashboard user={user} permissions={permissions} setUser={setUser} />
            <Toaster richColors position="top-right" />
        </>
    ) : (
        <>
            <Login setUser={setUser} />
            <Toaster richColors position="top-right" />
        </>
    );
}

const rootElement = document.getElementById('landlord-root');
if (rootElement) {
    const root = createRoot(rootElement);
    root.render(<LandlordApp />);
}
