import React from 'react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import Button from '@mui/material/Button';

export default class ErrorBoundary extends React.Component {
  state = { hasError: false, error: null };

  static getDerivedStateFromError(error) {
    return { hasError: true, error };
  }

  componentDidCatch(error, errorInfo) {
    console.error('ErrorBoundary caught:', error, errorInfo);
  }

  render() {
    if (this.state.hasError) {
      const message = this.props.fallbackMessage || 'Something went wrong';

      return (
        <Box sx={{ p: 4, textAlign: 'center' }} role="alert">
          <Typography variant="h6" sx={{ color: 'error.main', mb: 1 }}>
            {message}
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary', mb: 2 }}>
            This section failed to load. Please try refreshing the page.
          </Typography>
          <Button variant="outlined" size="small" onClick={() => window.location.reload()}>
            Reload Page
          </Button>
        </Box>
      );
    }

    return this.props.children;
  }
}
