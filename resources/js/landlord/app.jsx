import React from 'react';
import { createRoot } from 'react-dom/client';
import { ThemeProvider } from '@mui/material/styles';
import { LocalizationProvider } from '@mui/x-date-pickers/LocalizationProvider';
import { AdapterDayjs } from '@mui/x-date-pickers/AdapterDayjs';
import { Toaster } from 'sonner';
import CircularProgress from '@mui/material/CircularProgress';
import theme from '../theme';
import Login from '../modules/shared/Login';
import Dashboard from '../modules/shared/Dashboard';
import { AuthProvider, useAuthContext } from '../context/AuthContext';
import ErrorBoundary from '../Components/ErrorBoundary';

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
            <LocalizationProvider dateAdapter={AdapterDayjs}>
                <AuthProvider>
                    <ErrorBoundary fallbackMessage="Application failed to load">
                        <AppContent />
                    </ErrorBoundary>
                    <Toaster richColors position="top-right" />
                </AuthProvider>
            </LocalizationProvider>
        </ThemeProvider>
    );
}

const rootElement = document.getElementById('landlord-root');
if (rootElement) {
    const root = createRoot(rootElement);
    root.render(<LandlordApp />);
}
