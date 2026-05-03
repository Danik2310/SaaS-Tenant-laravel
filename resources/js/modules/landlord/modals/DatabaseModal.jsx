// resources/js/modules/landlord/modals/DatabaseModal.jsx
import React, { useEffect, useState } from 'react';
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

export default function DatabaseModal({ tenant, onClose }) {
  const [dbInfo, setDbInfo] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (!tenant) return;

    const fetchDb = async () => {
      setLoading(true);
      setError(null);

      try {
        const res = await api.get(`/admin/api/tenants/${tenant.id}/database`);
        setDbInfo(res.data.database);
      } catch (err) {
        const message = 'Failed to fetch database info';
        toast.error(message);
        setError(message);
      } finally {
        setLoading(false);
      }
    };

    fetchDb();
  }, [tenant]);

  return (
    <Dialog open={!!tenant} onClose={onClose} maxWidth="md" fullWidth>
      <DialogTitle>Database Credentials</DialogTitle>
      <DialogContent>
        {loading && <Typography>Loading...</Typography>}
        {error && <Typography color="error">{error}</Typography>}
        {!loading && !error && dbInfo && (
          <pre style={{ background: '#f4f4f4', padding: '10px' }}>
            {JSON.stringify(dbInfo, null, 2)}
          </pre>
        )}
        {!loading && !error && !dbInfo && (
          <Typography>No information available.</Typography>
        )}
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} variant="outlined">Close</Button>
      </DialogActions>
    </Dialog>
  );
}