// resources/js/modules/landlord/modals/DomainModal.jsx
import React from 'react';
import {
    Dialog,
    DialogTitle,
    DialogContent,
    DialogActions,
    IconButton,
    Avatar,
    Chip,
    Button,
    Typography,
    Box,
    Grid,
} from '@mui/material';
import CloseIcon from '@mui/icons-material/Close';
import PersonIcon from '@mui/icons-material/Person';
import LaunchIcon from '@mui/icons-material/Launch';
import StorageIcon from '@mui/icons-material/Storage';
import SyncIcon from '@mui/icons-material/Sync';

export default function DomainModal({ tenant, onClose, onImpersonate, onViewDatabase, onRunMigrations }) {
    if (!tenant) return null;

    return (
        <Dialog
            open={!!tenant}
            onClose={onClose}
            maxWidth="md"
            fullWidth
            sx={{ '& .MuiDialog-paper': { borderRadius: 2 } }}
        >
            <DialogTitle
                sx={{
                    background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                    color: 'white',
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    py: 3,
                    px: 3,
                }}
            >
                <Box>
                    <Typography variant="h6" component="h2" sx={{ fontWeight: 600, mb: 0.5 }}>
                        Customer Information
                    </Typography>
                    <Typography variant="body2" sx={{ opacity: 0.9 }}>
                        Complete tenant and domain details
                    </Typography>
                </Box>
                <IconButton onClick={onClose} sx={{ color: 'white', backgroundColor: 'rgba(255,255,255,0.2)', '&:hover': { backgroundColor: 'rgba(255,255,255,0.3)' } }}>
                    <CloseIcon />
                </IconButton>
            </DialogTitle>

            <DialogContent sx={{ p: 3 }}>
                <Box sx={{ background: 'linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%)', p: 3, borderRadius: 2, mb: 3, border: '1px solid #e1e8ed' }}>
                    <Box sx={{ display: 'flex', alignItems: 'center', mb: 2 }}>
                        <Avatar sx={{ width: 50, height: 50, bgcolor: tenant.status === 'Active' ? 'success.main' : 'error.main', mr: 2 }}>
                            {tenant.name?.charAt(0)?.toUpperCase() || 'T'}
                        </Avatar>
                        <Box>
                            <Typography variant="h6" sx={{ color: '#2c3e50', mb: 0.5 }}>
                                {tenant.name}
                            </Typography>
                            <Typography variant="body2" sx={{ color: '#7f8c8d' }}>
                                ID: {tenant.id}
                            </Typography>
                        </Box>
                    </Box>

                    <Grid container spacing={2}>
                        <Grid item xs={12} sm={6}>
                            <Typography variant="caption" sx={{ color: '#95a5a6', textTransform: 'uppercase', fontWeight: 600 }}>
                                Status
                            </Typography>
                            <Chip
                                label={tenant.status === 'Active' ? '✅ Active' : '⏸️ Suspended'}
                                color={tenant.status === 'Active' ? 'success' : 'error'}
                                size="small"
                                sx={{ mt: 0.5 }}
                            />
                        </Grid>
                        <Grid item xs={12} sm={6}>
                            <Typography variant="caption" sx={{ color: '#95a5a6', textTransform: 'uppercase', fontWeight: 600 }}>
                                Creation Date
                            </Typography>
                            <Typography variant="body2" sx={{ color: '#2c3e50', fontWeight: 500, mt: 0.5 }}>
                                {new Date(tenant.created_at).toLocaleDateString('en-US', {
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric',
                                })}
                            </Typography>
                        </Grid>
                    </Grid>
                </Box>

                <Box sx={{ backgroundColor: 'white', border: '1px solid #e1e8ed', borderRadius: 2, p: 3, mb: 3 }}>
                    <Typography variant="h6" sx={{ color: '#2c3e50', mb: 2 }}>
                        🌐 Domain Information
                    </Typography>

                    <Box sx={{ display: 'flex', alignItems: 'center', mb: 2 }}>
                        <Typography variant="body2" sx={{ color: '#34495e', mr: 1 }}>
                            <strong>Domain:</strong>
                        </Typography>
                        <Box component="code" sx={{ backgroundColor: '#ecf0f1', px: 1, py: 0.5, borderRadius: 1, fontSize: '0.875rem', color: '#2c3e50' }}>
                            {tenant.domain}
                        </Box>
                    </Box>

                    {tenant.domain && (
                        <Button
                            variant="contained"
                            startIcon={<LaunchIcon />}
                            href={`http://${tenant.domain}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            sx={{ backgroundColor: '#3498db', '&:hover': { backgroundColor: '#2980b9' } }}
                        >
                            Visit Website
                        </Button>
                    )}
                </Box>

                <Box sx={{ backgroundColor: '#f8f9fa', border: '1px solid #e9ecef', borderRadius: 2, p: 3 }}>
                    <Typography variant="h6" sx={{ color: '#2c3e50', mb: 2 }}>
                        ⚡ Quick Actions
                    </Typography>

                    <Box sx={{ display: 'flex', gap: 1, flexWrap: 'wrap' }}>
                        <Button
                            variant="contained"
                            startIcon={<PersonIcon />}
                            onClick={() => onImpersonate(tenant)}
                            sx={{ backgroundColor: '#17a2b8', '&:hover': { backgroundColor: '#138496' } }}
                        >
                            Impersonate
                        </Button>

                        <Button
                            variant="contained"
                            startIcon={<StorageIcon />}
                            onClick={() => onViewDatabase(tenant)}
                            sx={{ backgroundColor: '#28a745', '&:hover': { backgroundColor: '#218838' } }}
                        >
                            View Database
                        </Button>

                        <Button
                            variant="contained"
                            startIcon={<SyncIcon />}
                            onClick={() => onRunMigrations(tenant)}
                            sx={{ backgroundColor: '#ffc107', color: '#212529', '&:hover': { backgroundColor: '#e0a800' } }}
                        >
                            Run Migrations
                        </Button>
                    </Box>
                </Box>
            </DialogContent>

            <DialogActions sx={{ p: 3, pt: 0 }}>
                <Button onClick={onClose} variant="outlined" sx={{ color: '#6c757d', borderColor: '#6c757d', '&:hover': { backgroundColor: '#f8f9fa', borderColor: '#5a6268' } }}>
                    Close
                </Button>
            </DialogActions>
        </Dialog>
    );
}