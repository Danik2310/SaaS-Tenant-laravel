import { useState } from 'react';
import LinkIcon from '@mui/icons-material/Link';
import LinkOffIcon from '@mui/icons-material/LinkOff';
import Avatar from '@mui/material/Avatar';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogContentText from '@mui/material/DialogContentText';
import DialogTitle from '@mui/material/DialogTitle';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { Link, useForm } from '@inertiajs/react';

export default function ConnectedAccountsCard({
    accounts = [],
    hasPassword = true,
    canConnect = true,
    setPasswordHref = null,
    provider = 'google',
}) {
    const [confirming, setConfirming] = useState(null);
    const { delete: destroy, processing } = useForm({});
    const { post: connect, processing: connecting } = useForm({});

    const busy = processing || connecting;

    const confirmDisconnect = () => {
        if (confirming === null) {
            return;
        }

        destroy(route('tenant.settings.connected-accounts.destroy', confirming), {
            preserveScroll: true,
            onFinish: () => setConfirming(null),
        });
    };

    const startConnect = () => {
        connect(route('tenant.settings.connected-accounts.connect', provider), {
            preserveScroll: true,
        });
    };

    const confirmingAccount = accounts.find((account) => account.provider === confirming);

    return (
        <Paper
            component="section"
            variant="outlined"
            aria-labelledby="connected-accounts-heading"
            data-testid="connected-accounts-card"
            sx={{ p: 3, borderColor: 'divider', boxShadow: 1 }}
        >
            <Typography component="h2" variant="subtitle1" id="connected-accounts-heading" sx={{ fontWeight: 600, color: 'text.primary' }}>
                Connected sign-in methods
            </Typography>
            <Typography variant="body2" color="text.secondary" sx={{ mt: 0.5 }}>
                Changing your profile email does not change the Google account you use to sign in.
            </Typography>

            {accounts.length === 0 ? (
                <Stack direction="row" spacing={1.5} alignItems="center" justifyContent="space-between" sx={{ mt: 2 }}>
                    <Typography variant="body2" color="text.secondary">
                        No external accounts connected.
                    </Typography>
                    {canConnect && (
                        <Button
                            size="small"
                            color="primary"
                            startIcon={<LinkIcon />}
                            disabled={!hasPassword || busy}
                            onClick={startConnect}
                            data-testid={`connect-${provider}`}
                            sx={{ flexShrink: 0 }}
                        >
                            Connect
                        </Button>
                    )}
                </Stack>
            ) : (
                <Stack spacing={1.5} sx={{ mt: 2 }}>
                    {accounts.map((account) => (
                        <Stack
                            key={`${account.provider}:${account.email}`}
                            direction="row"
                            spacing={1.5}
                            alignItems="center"
                            justifyContent="space-between"
                        >
                            <Stack direction="row" spacing={1.5} alignItems="center" sx={{ minWidth: 0 }}>
                                <Avatar sx={{ width: 32, height: 32, bgcolor: 'action.hover', color: 'primary.main', fontSize: 14 }}>
                                    {account.label.charAt(0)}
                                </Avatar>
                                <Box sx={{ minWidth: 0 }}>
                                    <Typography variant="body2" sx={{ fontWeight: 600, color: 'text.primary' }}>
                                        {account.label}
                                    </Typography>
                                    <Typography variant="body2" color="text.secondary" sx={{ overflowWrap: 'anywhere' }}>
                                        {account.email}
                                    </Typography>
                                </Box>
                            </Stack>
                            <Button
                                size="small"
                                color="error"
                                startIcon={<LinkOffIcon />}
                                disabled={!hasPassword || busy}
                                onClick={() => setConfirming(account.provider)}
                                data-testid={`disconnect-${account.provider}`}
                                sx={{ flexShrink: 0 }}
                            >
                                Disconnect
                            </Button>
                        </Stack>
                    ))}
                </Stack>
            )}

            {!hasPassword && (
                <Box data-testid="passwordless-oauth-warning" sx={{ mt: 2 }}>
                    <Typography variant="body2" color="text.secondary">
                        You haven't set a password yet. Set one before connecting or disconnecting Google.
                    </Typography>
                    {setPasswordHref && (
                        <Button component={Link} href={setPasswordHref} size="small" sx={{ mt: 0.5, p: 0 }}>
                            Set a password first
                        </Button>
                    )}
                </Box>
            )}

            <Dialog
                open={confirming !== null}
                onClose={() => setConfirming(null)}
                maxWidth="xs"
                fullWidth
                aria-labelledby="disconnect-dialog-title"
            >
                <DialogTitle id="disconnect-dialog-title">Disconnect {confirmingAccount?.label ?? 'account'}?</DialogTitle>
                <DialogContent>
                    <DialogContentText>
                        This sign-in method will no longer open this workspace. Your password will keep working.
                    </DialogContentText>
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setConfirming(null)} disabled={busy}>Cancel</Button>
                    <Button onClick={confirmDisconnect} color="error" disabled={busy} data-testid="confirm-disconnect">
                        Disconnect
                    </Button>
                </DialogActions>
            </Dialog>
        </Paper>
    );
}
