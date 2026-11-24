import React, { useState } from 'react';
import { alpha } from '@mui/material/styles';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import CircularProgress from '@mui/material/CircularProgress';
import IconButton from '@mui/material/IconButton';
import InputAdornment from '@mui/material/InputAdornment';
import TextField from '@mui/material/TextField';
import Tooltip from '@mui/material/Tooltip';
import Typography from '@mui/material/Typography';
import VisibilityIcon from '@mui/icons-material/Visibility';
import VisibilityOffIcon from '@mui/icons-material/VisibilityOff';
import { toast } from 'sonner';
import api from '../../services/api';
import Wordmark from '../../Components/BrandLogo';

export default function Login() {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [showPassword, setShowPassword] = useState(false);
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setError(null);

        try {
            const response = await api.post('/central/login', {
                email,
                password,
            });

            if (response.data.success) {
                window.location.assign('/admin/dashboard');
            }
        } catch (err) {
            const message = err.response?.data?.errors?.email?.[0]
                || err.response?.data?.message
                || 'Login failed. Please try again.';
            toast.error(message);
            setError(message);
        } finally {
            setLoading(false);
        }
    };

    return (
        <Box
            component="main"
            sx={{
                minHeight: '100vh',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                position: 'relative',
                overflow: 'hidden',
                bgcolor: 'ink.main',
                px: 2,
                py: 4,
            }}
        >
            <Box
                aria-hidden="true"
                sx={{
                    position: 'absolute',
                    top: -96,
                    right: -96,
                    width: { xs: 280, sm: 384 },
                    height: { xs: 280, sm: 384 },
                    borderRadius: '50%',
                    bgcolor: (theme) => alpha(theme.palette.primary.main, 0.2),
                    filter: 'blur(96px)',
                    pointerEvents: 'none',
                }}
            />
            <Box
                aria-hidden="true"
                sx={{
                    position: 'absolute',
                    bottom: -128,
                    left: -96,
                    width: { xs: 260, sm: 320 },
                    height: { xs: 260, sm: 320 },
                    borderRadius: '50%',
                    bgcolor: (theme) => alpha(theme.palette.primary.dark, 0.16),
                    filter: 'blur(96px)',
                    pointerEvents: 'none',
                }}
            />

            <Box
                component="section"
                aria-labelledby="admin-login-title"
                sx={{
                    position: 'relative',
                    width: '100%',
                    maxWidth: 420,
                    bgcolor: 'background.paper',
                    px: { xs: 3, sm: 4 },
                    py: { xs: 4, sm: 5 },
                    borderRadius: 2.5,
                    boxShadow: 24,
                }}
            >
                <Box sx={{ display: 'flex', justifyContent: 'center' }}>
                    <Wordmark dark={false} />
                </Box>

                <Typography id="admin-login-title" component="h1" variant="h5" sx={{ textAlign: 'center', mt: 2.5 }}>
                    Admin
                </Typography>
                <Typography variant="body2" color="text.secondary" sx={{ textAlign: 'center', mb: 3.5 }}>
                    Manage the ShoppingLi platform
                </Typography>

                {error && (
                    <Alert severity="error" variant="filled" sx={{ mb: 3, borderRadius: 1 }}>
                        {error}
                    </Alert>
                )}

                <Box component="form" onSubmit={handleSubmit} noValidate sx={{ display: 'flex', flexDirection: 'column', gap: 2.5 }}>
                    <TextField
                        id="admin-email"
                        name="email"
                        label="Email"
                        type="email"
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        placeholder="admin@example.com"
                        autoComplete="username"
                        autoFocus
                        required
                        fullWidth
                        sx={{
                            '& .MuiOutlinedInput-root': {
                                borderRadius: 1.25,
                                transition: 'border-color 150ms ease-out, box-shadow 150ms ease-out',
                                '&:hover .MuiOutlinedInput-notchedOutline': {
                                    borderColor: 'text.secondary',
                                },
                                '&.Mui-focused .MuiOutlinedInput-notchedOutline': {
                                    borderColor: 'primary.main',
                                    borderWidth: 2,
                                },
                            },
                            '& .MuiFormLabel-root.Mui-focused': {
                                color: 'primary.main',
                            },
                        }}
                    />

                    <TextField
                        id="admin-password"
                        name="password"
                        label="Password"
                        type={showPassword ? 'text' : 'password'}
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                        placeholder="Enter your password"
                        autoComplete="current-password"
                        required
                        fullWidth
                        slotProps={{
                            input: {
                                endAdornment: (
                                    <InputAdornment position="end">
                                        <Tooltip title={showPassword ? 'Hide password' : 'Show password'}>
                                            <IconButton
                                                aria-label={showPassword ? 'Hide password' : 'Show password'}
                                                aria-pressed={showPassword}
                                                onClick={() => setShowPassword((visible) => !visible)}
                                                edge="end"
                                                size="small"
                                            >
                                                {showPassword
                                                    ? <VisibilityOffIcon fontSize="small" />
                                                    : <VisibilityIcon fontSize="small" />}
                                            </IconButton>
                                        </Tooltip>
                                    </InputAdornment>
                                ),
                            },
                        }}
                        sx={{
                            '& .MuiOutlinedInput-root': {
                                borderRadius: 1.25,
                                transition: 'border-color 150ms ease-out, box-shadow 150ms ease-out',
                                '&:hover .MuiOutlinedInput-notchedOutline': {
                                    borderColor: 'text.secondary',
                                },
                                '&.Mui-focused .MuiOutlinedInput-notchedOutline': {
                                    borderColor: 'primary.main',
                                    borderWidth: 2,
                                },
                            },
                            '& .MuiFormLabel-root.Mui-focused': {
                                color: 'primary.main',
                            },
                        }}
                    />

                    <Button
                        type="submit"
                        variant="contained"
                        color="primary"
                        size="large"
                        fullWidth
                        disabled={loading}
                        startIcon={loading ? <CircularProgress size={16} color="inherit" /> : null}
                        sx={{ py: 1.25 }}
                    >
                        {loading ? 'Logging in...' : 'Log In'}
                    </Button>
                </Box>
            </Box>
        </Box>
    );
}
