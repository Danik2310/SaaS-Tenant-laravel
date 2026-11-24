import React, { useState } from 'react';
import { alpha, useTheme } from '@mui/material/styles';
import useMediaQuery from '@mui/material/useMediaQuery';
import Box from '@mui/material/Box';
import Drawer from '@mui/material/Drawer';
import IconButton from '@mui/material/IconButton';
import Tooltip from '@mui/material/Tooltip';
import Typography from '@mui/material/Typography';
import MenuIcon from '@mui/icons-material/Menu';
import DashboardIcon from '@mui/icons-material/Dashboard';
import PeopleIcon from '@mui/icons-material/People';
import GroupIcon from '@mui/icons-material/Group';
import SecurityIcon from '@mui/icons-material/Security';
import StorageIcon from '@mui/icons-material/Storage';
import VisibilityIcon from '@mui/icons-material/Visibility';
import PersonIcon from '@mui/icons-material/Person';
import CreditCardIcon from '@mui/icons-material/CreditCard';
import AccountBalanceIcon from '@mui/icons-material/AccountBalance';
import HistoryIcon from '@mui/icons-material/History';
import SettingsIcon from '@mui/icons-material/Settings';
import MonitorHeartIcon from '@mui/icons-material/MonitorHeart';
import Wordmark from '@/Components/BrandLogo';
import { useAuthContext } from '../context/AuthContext';

const permissionMap = {
    overview: null,
    tenants: ['view tenants'],
    'resource-usage': ['view tenants'],
    staff: ['view staff'],
    roles: ['view roles', 'view permissions'],
    subscriptions: ['view subscriptions'],
    'payment-methods': ['view payment methods'],
    plans: ['view plans'],
    settings: ['view settings'],
    activity: ['view activity logs'],
    impersonate: ['impersonate tenants'],
    profile: null,
};

const views = [
    { id: 'overview', label: 'Overview', icon: <DashboardIcon />, section: 'Main' },
    { id: 'tenants', label: 'Tenant Management', icon: <PeopleIcon />, section: 'Main' },
    { id: 'resource-usage', label: 'Resource Usage', icon: <MonitorHeartIcon />, section: 'Main' },
    { id: 'staff', label: 'Staff Management', icon: <GroupIcon />, section: 'Administration' },
    { id: 'roles', label: 'Roles & Permissions', icon: <SecurityIcon />, section: 'Administration' },
    { id: 'subscriptions', label: 'Subscriptions', icon: <CreditCardIcon />, section: 'Billing' },
    { id: 'payment-methods', label: 'Payment Methods', icon: <AccountBalanceIcon />, section: 'Billing' },
    { id: 'plans', label: 'Infrastructure & Plans', icon: <StorageIcon />, section: 'Configuration' },
    { id: 'settings', label: 'System Settings', icon: <SettingsIcon />, section: 'Configuration' },
    { id: 'activity', label: 'Activity Logs', icon: <HistoryIcon />, section: 'Tools' },
    { id: 'impersonate', label: 'God Mode', icon: <VisibilityIcon />, section: 'Tools' },
    { id: 'profile', label: 'My Profile', icon: <PersonIcon />, section: 'Account' },
];

export default function Navbar({ view, setView }) {
    const { user, permissions = [] } = useAuthContext();
    const theme = useTheme();
    const isDesktop = useMediaQuery(theme.breakpoints.up('md'));
    const [mobileOpen, setMobileOpen] = useState(false);

    const line = alpha(theme.palette.common.white, 0.08);
    const hover = alpha(theme.palette.common.white, 0.06);
    const label = alpha(theme.palette.common.white, 0.38);
    const muted = alpha(theme.palette.common.white, 0.52);

    const filtered = views.filter((item) => {
        const required = permissionMap[item.id];
        if (!required) return true;
        return required.some((permission) => permissions.includes(permission));
    });

    const grouped = filtered.reduce((acc, item) => {
        if (!acc[item.section]) acc[item.section] = [];
        acc[item.section].push(item);
        return acc;
    }, {});

    const handleSelect = (id) => {
        setView(id);
        setMobileOpen(false);
    };

    const navContent = (
        <Box
            sx={{
                height: '100%',
                display: 'flex',
                flexDirection: 'column',
                bgcolor: 'ink.main',
                color: 'common.white',
            }}
        >
            <Box sx={{ px: 2.5, py: 2.25, borderBottom: '1px solid', borderColor: line }}>
                <Wordmark />
                <Typography variant="caption" sx={{ display: 'block', mt: 0.75, color: muted }}>
                    Management Console
                </Typography>
            </Box>

            <Box sx={{ px: 2.5, py: 1.5 }}>
                <Typography
                    variant="caption"
                    sx={{ color: label, textTransform: 'uppercase', letterSpacing: '0.08em' }}
                >
                    Signed in as
                </Typography>
                <Typography variant="body2" noWrap sx={{ mt: 0.25, fontWeight: 600 }}>
                    {user?.name || 'Admin'}
                </Typography>
            </Box>

            <Box
                component="nav"
                aria-label="Admin navigation"
                sx={{
                    flex: 1,
                    overflowY: 'auto',
                    px: 1.5,
                    py: 1,
                    scrollbarWidth: 'none',
                    '&::-webkit-scrollbar': { display: 'none' },
                }}
            >
                {Object.entries(grouped).map(([section, items]) => (
                    <Box key={section} sx={{ mb: 2.5 }}>
                        <Typography
                            variant="caption"
                            sx={{
                                display: 'block',
                                px: 1,
                                mb: 0.75,
                                color: label,
                                textTransform: 'uppercase',
                                letterSpacing: '0.08em',
                            }}
                        >
                            {section}
                        </Typography>
                        {items.map((item) => {
                            const active = view === item.id;
                            return (
                                <Box
                                    key={item.id}
                                    component="button"
                                    type="button"
                                    onClick={() => handleSelect(item.id)}
                                    aria-current={active ? 'page' : undefined}
                                    aria-label={item.label}
                                    data-testid={`nav-${item.id}`}
                                    sx={{
                                        width: '100%',
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 1.25,
                                        px: 1.5,
                                        py: 1.25,
                                        mb: 0.25,
                                        appearance: 'none',
                                        border: 0,
                                        borderRadius: 1,
                                        font: 'inherit',
                                        textAlign: 'left',
                                        cursor: 'pointer',
                                        color: active ? 'common.white' : muted,
                                        bgcolor: active ? 'primary.main' : 'transparent',
                                        transition: 'background-color 150ms ease-out, color 150ms ease-out',
                                        '&:hover': {
                                            bgcolor: active ? 'primary.dark' : hover,
                                            color: 'common.white',
                                        },
                                        '&:focus-visible': {
                                            outline: 'none',
                                            boxShadow: `0 0 0 2px ${theme.palette.primary.main}`,
                                        },
                                    }}
                                >
                                    {React.cloneElement(item.icon, { fontSize: 'small', sx: { fontSize: 18 } })}
                                    <Typography component="span" variant="body2" sx={{ fontWeight: active ? 700 : 500 }}>
                                        {item.label}
                                    </Typography>
                                </Box>
                            );
                        })}
                    </Box>
                ))}
            </Box>

            <Box sx={{ px: 2.5, py: 1.5, borderTop: '1px solid', borderColor: line }}>
                <Typography variant="caption" sx={{ color: alpha(theme.palette.common.white, 0.3) }}>
                    ShoppingLi Ops · v0.2
                </Typography>
            </Box>
        </Box>
    );

    return (
        <>
            {!isDesktop && (
                <Tooltip title="Open navigation">
                    <IconButton
                        aria-label="Open navigation"
                        onClick={() => setMobileOpen(true)}
                        sx={{
                            position: 'fixed',
                            top: 12,
                            left: 12,
                            zIndex: 1300,
                            bgcolor: 'ink.main',
                            color: 'common.white',
                            '&:hover': { bgcolor: 'ink.soft' },
                        }}
                    >
                        <MenuIcon />
                    </IconButton>
                </Tooltip>
            )}

            <Box
                component="aside"
                sx={{ width: 260, flexShrink: 0, height: '100vh', display: { xs: 'none', md: 'block' } }}
            >
                {navContent}
            </Box>

            <Drawer
                open={mobileOpen}
                onClose={() => setMobileOpen(false)}
                ModalProps={{ keepMounted: true }}
                PaperProps={{ sx: { width: 280, bgcolor: 'ink.main' } }}
                sx={{ display: { md: 'none' } }}
            >
                {navContent}
            </Drawer>
        </>
    );
}
