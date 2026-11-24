import { createTheme } from '@mui/material/styles';

// ShoppingLi brand tokens. The public site and admin login already use these
// values through Tailwind/raw CSS; the MUI theme mirrors them so dashboards in
// both the tenant and landlord bundles stop rendering in generic blue/Inter.
const brand = {
  50: '#FFF7ED',
  100: '#FFEDD5',
  200: '#FED7AA',
  300: '#FDBA74',
  400: '#FB923C',
  500: '#F97316',
  600: '#EA580C',
  700: '#C2410C',
  800: '#9A3412',
  900: '#7C2D12',
};

const ink = {
  main: '#0A0A0A',
  soft: '#171717',
};

const fontSans = '"DM Sans", "Inter", "Roboto", "Helvetica", "Arial", sans-serif';
const fontDisplay = '"Archivo Black", "DM Sans", "Inter", sans-serif';

const theme = createTheme({
  palette: {
    primary: {
      main: brand[500],
      light: brand[400],
      dark: brand[600],
      contrastText: '#FFFFFF',
    },
    secondary: {
      main: ink.soft,
      light: '#525252',
      dark: ink.main,
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
      primary: ink.main,
      secondary: '#525252',
      disabled: '#A3A3A3',
    },
    divider: '#E5E5E5',
    // Runtime aliases for components that need the raw brand/ink surfaces.
    brand,
    ink,
  },
  shape: {
    borderRadius: 8,
  },
  typography: {
    fontFamily: fontSans,
    h4: {
      fontFamily: fontDisplay,
      fontWeight: 400,
      letterSpacing: '-0.02em',
    },
    h5: {
      fontFamily: fontDisplay,
      fontWeight: 400,
      letterSpacing: '-0.02em',
    },
    h6: {
      fontFamily: fontDisplay,
      fontWeight: 400,
      letterSpacing: '-0.02em',
    },
    subtitle1: {
      fontFamily: fontSans,
      fontWeight: 600,
    },
    button: {
      fontFamily: fontSans,
      fontWeight: 600,
      letterSpacing: 0,
      textTransform: 'none',
    },
  },
  components: {
    MuiButton: {
      styleOverrides: {
        root: {
          textTransform: 'none',
          fontWeight: 600,
          borderRadius: 8,
        },
        contained: {
          borderRadius: 999,
          boxShadow: 'none',
          '&:hover': {
            boxShadow: 'none',
          },
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
            fontWeight: 700,
            backgroundColor: '#FAFAFA',
            color: '#525252',
          },
        },
      },
    },
    MuiChip: {
      styleOverrides: {
        root: {
          fontWeight: 600,
        },
      },
    },
    MuiTooltip: {
      styleOverrides: {
        tooltip: {
          fontSize: 11,
        },
      },
    },
  },
});

export default theme;
