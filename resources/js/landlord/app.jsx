import React from 'react';
import { createRoot } from 'react-dom/client';
import { ThemeProvider } from '@mui/material/styles';
import { Toaster } from 'sonner';
import CircularProgress from '@mui/material/CircularProgress';
import theme from '../theme';
import Login from '../modules/landlord/Login';
import Dashboard from '../modules/landlord/Dashboard';
import { AuthProvider, useAuthContext } from '../context/AuthContext';

function AppContent() {
    const { user, loading } = useAuthContext();

    if (loading) {
        return (
            <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '100vh' }}>
                <CircularProgress />
            </div>
        );
    }

    return user ? <Dashboard /> : <Login />;
}

export default function LandlordApp() {
    return (
        <ThemeProvider theme={theme}>
            <AuthProvider>
                <AppContent />
                <Toaster richColors position="top-right" />
            </AuthProvider>
        </ThemeProvider>
    );
}

const rootElement = document.getElementById('landlord-root');
if (rootElement) {
    const root = createRoot(rootElement);
    root.render(<LandlordApp />);
}
