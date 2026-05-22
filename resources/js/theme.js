import { createTheme } from '@mui/material/styles';

const theme = createTheme({
    palette: {
        primary: {
            main: '#2563EB',
            light: '#3B82F6',
            dark: '#1D4ED8',
            contrastText: '#FFFFFF',
        },
        secondary: {
            main: '#7C3AED',
            light: '#8B5CF6',
            dark: '#6D28D9',
            contrastText: '#FFFFFF',
        },
        success: {
            main: '#059669',
            light: '#10B981',
            dark: '#047857',
            contrastText: '#FFFFFF',
        },
        warning: {
            main: '#D97706',
            light: '#F59E0B',
            dark: '#B45309',
            contrastText: '#FFFFFF',
        },
        error: {
            main: '#DC2626',
            light: '#EF4444',
            dark: '#B91C1C',
            contrastText: '#FFFFFF',
        },
        background: {
            default: '#F8FAFC',
            paper: '#FFFFFF',
        },
        text: {
            primary: '#0F172A',
            secondary: '#64748B',
            disabled: '#CBD5E1',
        },
        divider: '#E2E8F0',
    },
    shape: {
        borderRadius: 8,
    },
    typography: {
        fontFamily: '"Inter", "Roboto", "Helvetica", "Arial", sans-serif',
        h4: {
            fontWeight: 600,
        },
        h5: {
            fontWeight: 600,
        },
        h6: {
            fontWeight: 600,
        },
        subtitle1: {
            fontWeight: 500,
        },
    },
    components: {
        MuiButton: {
            styleOverrides: {
                root: {
                    textTransform: 'none',
                    fontWeight: 500,
                },
            },
        },
        MuiPaper: {
            styleOverrides: {
                root: {
                    backgroundImage: 'none',
                },
            },
        },
        MuiTableHead: {
            styleOverrides: {
                root: {
                    '& .MuiTableCell-head': {
                        fontWeight: 600,
                        backgroundColor: '#F8FAFC',
                    },
                },
            },
        },
        MuiChip: {
            styleOverrides: {
                root: {
                    fontWeight: 500,
                },
            },
        },
    },
});

export default theme;
