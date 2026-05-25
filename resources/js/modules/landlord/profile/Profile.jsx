import React, { useState, useEffect } from 'react';
import { toast } from 'sonner';
import api from '../../../services/api';
import Box from '@mui/material/Box';
import Paper from '@mui/material/Paper';
import Typography from '@mui/material/Typography';
import TextField from '@mui/material/TextField';
import Button from '@mui/material/Button';
import Divider from '@mui/material/Divider';
import Avatar from '@mui/material/Avatar';
import CircularProgress from '@mui/material/CircularProgress';
import Alert from '@mui/material/Alert';
import PersonIcon from '@mui/icons-material/Person';
import { useAuthContext } from '../../../context/AuthContext';

export default function Profile() {
    const { user } = useAuthContext();
    const [profile, setProfile] = useState(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);

    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [currentPassword, setCurrentPassword] = useState('');
    const [newPassword, setNewPassword] = useState('');
    const [newPasswordConfirmation, setNewPasswordConfirmation] = useState('');

    useEffect(() => {
        fetchProfile();
    }, []);

    const fetchProfile = async () => {
        try {
            const res = await api.get('/admin/api/profile');
            setProfile(res.data.profile);
            setName(res.data.profile.name);
            setEmail(res.data.profile.email);
        } catch (err) {
            toast.error('Failed to load profile');
        } finally {
            setLoading(false);
        }
    };

    const handleUpdateProfile = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            const res = await api.put('/admin/api/profile', { name, email });
            toast.success('Profile updated successfully');
            setProfile(res.data.profile);
        } catch (err) {
            const message = err.response?.data?.errors
                ? Object.values(err.response.data.errors).flat().join(', ')
                : err.response?.data?.message || 'Failed to update profile';
            toast.error(message);
        } finally {
            setSaving(false);
        }
    };

    const handleUpdatePassword = async (e) => {
        e.preventDefault();
        if (newPassword !== newPasswordConfirmation) {
            toast.error('Passwords do not match');
            return;
        }
        setSaving(true);
        try {
            await api.put('/admin/api/profile/password', {
                current_password: currentPassword,
                new_password: newPassword,
                new_password_confirmation: newPasswordConfirmation,
            });
            toast.success('Password updated successfully');
            setCurrentPassword('');
            setNewPassword('');
            setNewPasswordConfirmation('');
        } catch (err) {
            const message = err.response?.data?.errors
                ? Object.values(err.response.data.errors).flat().join(', ')
                : err.response?.data?.message || 'Failed to update password';
            toast.error(message);
        } finally {
            setSaving(false);
        }
    };

    if (loading) {
        return (
            <Box sx={{ display: 'flex', justifyContent: 'center', py: 8 }}>
                <CircularProgress />
            </Box>
        );
    }

    return (
        <Box sx={{ maxWidth: 720, mx: 'auto' }}>
            <Paper sx={{ p: 4, mb: 3, borderRadius: 2, boxShadow: '0 1px 3px rgba(0,0,0,0.06)' }}>
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 3, mb: 4 }}>
                    <Avatar sx={{ width: 64, height: 64, bgcolor: '#3b82f6' }}>
                        <PersonIcon sx={{ fontSize: 32 }} />
                    </Avatar>
                    <Box>
                        <Typography variant="h5" sx={{ fontWeight: 700 }}>
                            {profile?.name}
                        </Typography>
                        <Typography variant="body2" color="text.secondary">
                            {profile?.email}
                        </Typography>
                        <Typography variant="caption" color="text.secondary">
                            Member since {profile?.created_at}
                        </Typography>
                    </Box>
                </Box>

                <Typography variant="subtitle1" sx={{ fontWeight: 600, mb: 2 }}>Profile Information</Typography>
                <Box component="form" onSubmit={handleUpdateProfile} sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                    <TextField
                        label="Name"
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        required
                        fullWidth
                        size="small"
                    />
                    <TextField
                        label="Email"
                        type="email"
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        required
                        fullWidth
                        size="small"
                    />
                    <Box sx={{ display: 'flex', justifyContent: 'flex-end' }}>
                        <Button type="submit" variant="contained" disabled={saving}>
                            {saving ? 'Saving...' : 'Save Changes'}
                        </Button>
                    </Box>
                </Box>
            </Paper>

            <Paper sx={{ p: 4, borderRadius: 2, boxShadow: '0 1px 3px rgba(0,0,0,0.06)' }}>
                <Typography variant="subtitle1" sx={{ fontWeight: 600, mb: 2 }}>Change Password</Typography>
                <Box component="form" onSubmit={handleUpdatePassword} sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                    <TextField
                        label="Current Password"
                        type="password"
                        value={currentPassword}
                        onChange={(e) => setCurrentPassword(e.target.value)}
                        required
                        fullWidth
                        size="small"
                    />
                    <TextField
                        label="New Password"
                        type="password"
                        value={newPassword}
                        onChange={(e) => setNewPassword(e.target.value)}
                        required
                        fullWidth
                        size="small"
                        inputProps={{ minLength: 8 }}
                    />
                    <TextField
                        label="Confirm New Password"
                        type="password"
                        value={newPasswordConfirmation}
                        onChange={(e) => setNewPasswordConfirmation(e.target.value)}
                        required
                        fullWidth
                        size="small"
                        error={newPasswordConfirmation.length > 0 && newPassword !== newPasswordConfirmation}
                        helperText={newPasswordConfirmation.length > 0 && newPassword !== newPasswordConfirmation ? 'Passwords do not match' : ''}
                    />
                    <Box sx={{ display: 'flex', justifyContent: 'flex-end' }}>
                        <Button type="submit" variant="contained" disabled={saving}>
                            {saving ? 'Updating...' : 'Update Password'}
                        </Button>
                    </Box>
                </Box>
            </Paper>
        </Box>
    );
}
