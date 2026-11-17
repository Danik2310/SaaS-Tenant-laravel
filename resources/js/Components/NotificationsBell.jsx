import React, { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import NotificationsIcon from '@mui/icons-material/Notifications';
import IconButton from '@mui/material/IconButton';
import Tooltip from '@mui/material/Tooltip';
import Badge from '@mui/material/Badge';
import Menu from '@mui/material/Menu';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import Button from '@mui/material/Button';
import Divider from '@mui/material/Divider';

const levelColors = {
    critical: { bg: '#fee2e2', fg: '#991b1b' },
    warning: { bg: '#fef9c3', fg: '#854d0e' },
    info: { bg: '#e0f2fe', fg: '#075985' },
};

/**
 * Live notification bell.
 *
 * The Reverb subscription only refreshes the shared `notifications` prop; it
 * never renders event payloads. That keeps the socket free of tenant content
 * and guarantees the server re-checks the session on every refresh.
 */
export default function NotificationsBell({ notifications: override, channel: channelOverride } = {}) {
    const pageProps = usePage().props;
    const notifications = override ?? pageProps.notifications ?? { unread_count: 0, recent: [] };
    const channel = channelOverride !== undefined ? channelOverride : pageProps.notification_channel;
    const [anchorEl, setAnchorEl] = useState(null);

    useEffect(() => {
        if (!channel || !window.Echo) return undefined;

        const subscription = window.Echo
            .private(channel)
            .listen('.notification.created', () => router.reload({ only: ['notifications'] }));

        return () => {
            subscription?.stop?.();
            window.Echo.leave(channel);
        };
    }, [channel]);

    const items = notifications.recent ?? [];
    const open = (event) => setAnchorEl(event.currentTarget);
    const close = () => setAnchorEl(null);

    const openItem = (item) => {
        close();
        router.patch(route('tenant.notifications.read', item.id), {}, {
            preserveScroll: true,
            onSuccess: () => {
                if (item.data?.action_url) router.visit(item.data.action_url);
            },
        });
    };

    return (
        <>
            <Tooltip title="Notifications">
                <IconButton
                    data-testid="notifications-bell"
                    aria-label="Notifications"
                    onClick={open}
                    size="small"
                    sx={{ color: '#475569' }}
                >
                    <Badge badgeContent={notifications.unread_count ?? 0} color="error" max={99}>
                        <NotificationsIcon fontSize="small" />
                    </Badge>
                </IconButton>
            </Tooltip>

            <Menu
                anchorEl={anchorEl}
                open={Boolean(anchorEl)}
                onClose={close}
                slotProps={{ paper: { sx: { width: 340, maxHeight: 440 } } }}
            >
                <Box sx={{ px: 2, py: 1, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>Notifications</Typography>
                    {notifications.unread_count > 0 && (
                        <Button
                            size="small"
                            data-testid="notifications-mark-all"
                            onClick={() => {
                                close();
                                router.post(route('tenant.notifications.read-all'));
                            }}
                        >
                            Mark all read
                        </Button>
                    )}
                </Box>
                <Divider />

                {items.length === 0 && (
                    <Typography data-testid="notifications-empty" sx={{ px: 2, py: 3, color: '#94a3b8', fontSize: 13 }}>
                        You're all caught up.
                    </Typography>
                )}

                {items.map((item) => {
                    const colors = levelColors[item.level] ?? levelColors.info;
                    return (
                        <Box
                            key={item.id}
                            data-testid={`notification-${item.id}`}
                            component="button"
                            type="button"
                            onClick={() => openItem(item)}
                            sx={{
                                display: 'block', width: '100%', textAlign: 'left',
                                px: 2, py: 1.25, border: 'none', cursor: 'pointer',
                                background: item.read_at ? 'transparent' : '#f8fafc',
                                '&:hover': { bgcolor: '#f1f5f9' },
                            }}
                        >
                            <Typography
                                data-testid={`notification-title-${item.id}`}
                                sx={{ fontSize: 13, fontWeight: item.read_at ? 500 : 700, color: '#0f172a' }}
                            >
                                {item.title}
                            </Typography>
                            {item.body && (
                                <Typography sx={{ fontSize: 12, color: '#64748b', mt: 0.5 }}>{item.body}</Typography>
                            )}
                            <Typography
                                component="span"
                                sx={{
                                    display: 'inline-block', mt: 0.75, px: 0.75, py: 0.1,
                                    borderRadius: 1, fontSize: 10, fontWeight: 700,
                                    bgcolor: colors.bg, color: colors.fg,
                                }}
                            >
                                {item.level}
                            </Typography>
                        </Box>
                    );
                })}

                <Divider />
                <Box sx={{ px: 2, py: 0.75 }}>
                    <Button
                        size="small"
                        data-testid="notifications-view-all"
                        onClick={() => {
                            close();
                            router.visit(route('tenant.notifications.index'));
                        }}
                    >
                        View all
                    </Button>
                </Box>
            </Menu>
        </>
    );
}
