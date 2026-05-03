// resources/js/modules/landlord/modals/MigrationModal.jsx
import React, { useState } from 'react';
import {
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Button,
  Typography,
} from '@mui/material';
import { toast } from 'sonner';
import api from '../../../services/api';

export default function MigrationModal({ tenant, onClose }) {
  const [output, setOutput] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const runMigrations = async () => {
    if (!tenant) return;

    setLoading(true);
    setError(null);
    setOutput('');

    try {
      const res = await api.post(`/admin/api/tenants/${tenant.id}/migrate`);
      setOutput(res.data.output || res.data.message || 'Done');
      toast.success('Migrations completed successfully');
    } catch (err) {
      const message = 'Failed to run migrations';
      toast.error(message);
      setError(message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog open={!!tenant} onClose={onClose} maxWidth="lg" fullWidth>
      <DialogTitle>Run Migrations</DialogTitle>
      <DialogContent>
        <Typography mb={2}>
          Press the button below to run migrations for this tenant.
        </Typography>

        {loading && <Typography>Running migrations...</Typography>}
        {error && <Typography color="error">{error}</Typography>}
        {output && (
          <pre style={{ background: '#f4f4f4', padding: '10px', maxHeight: 400, overflow: 'auto' }}>
            {output}
          </pre>
        )}
      </DialogContent>

      <DialogActions>
        <Button onClick={onClose}>Close</Button>
        <Button onClick={runMigrations} variant="contained" disabled={loading}>
          Run migrations
        </Button>
      </DialogActions>
    </Dialog>
  );
}