import React, { useState } from 'react';
import {
    Button,
    Menu,
    MenuItem,
    ListItemIcon,
    ListItemText,
    CircularProgress,
} from '@mui/material';
import FileDownloadIcon from '@mui/icons-material/FileDownload';
import DescriptionIcon from '@mui/icons-material/Description';
import TableChartIcon from '@mui/icons-material/TableChart';
import api from '../services/api';
import { toast } from 'sonner';

export default function ExportButton({
    resource,
    filters = {},
    disabled = false,
    label = 'Export',
}) {
    const [anchorEl, setAnchorEl] = useState(null);
    const [loading, setLoading] = useState(null);

    const handleOpen = (e) => setAnchorEl(e.currentTarget);
    const handleClose = () => setAnchorEl(null);

    const handleExport = async (format) => {
        setLoading(format);
        handleClose();
        try {
            const res = await api.post(`/admin/api/export/${resource}`, {
                format,
                filters,
            });
            const { filename } = res.data.data;
            const downloadUrl = `/admin/api/export/download/${filename}`;
            window.open(downloadUrl, '_blank');
            toast.success(`Exporting ${resource} as ${format.toUpperCase()}`);
        } catch (err) {
            toast.error(err.response?.data?.message || 'Export failed');
        } finally {
            setLoading(null);
        }
    };

    return (
        <>
            <Button
                size="small"
                variant="outlined"
                startIcon={loading ? <CircularProgress size={16} /> : <FileDownloadIcon />}
                onClick={handleOpen}
                disabled={disabled || !!loading}
                sx={{ fontSize: '13px', color: '#475569', borderColor: '#cbd5e1' }}
            >
                {loading ? `Exporting ${loading.toUpperCase()}...` : label}
            </Button>
            <Menu anchorEl={anchorEl} open={Boolean(anchorEl)} onClose={handleClose}>
                <MenuItem onClick={() => handleExport('csv')}>
                    <ListItemIcon>
                        <DescriptionIcon fontSize="small" />
                    </ListItemIcon>
                    <ListItemText>Export as CSV</ListItemText>
                </MenuItem>
                <MenuItem onClick={() => handleExport('xlsx')}>
                    <ListItemIcon>
                        <TableChartIcon fontSize="small" />
                    </ListItemIcon>
                    <ListItemText>Export as Excel (XLSX)</ListItemText>
                </MenuItem>
            </Menu>
        </>
    );
}
