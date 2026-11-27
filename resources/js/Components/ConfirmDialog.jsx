import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogContentText from '@mui/material/DialogContentText';
import DialogTitle from '@mui/material/DialogTitle';
import Fade from '@mui/material/Fade';
import TextField from '@mui/material/TextField';
import { dialogSx } from './surfaceMotion';
import Typography from '@mui/material/Typography';

export default function ConfirmDialog({
  open,
  title,
  message,
  confirmLabel,
  cancelLabel,
  onConfirm,
  onCancel,
  confirmationTarget,
  confirmationValue,
  onConfirmationChange,
  confirmationLabel = 'Workspace name',
}) {
  const requiresTypedConfirmation = confirmationTarget !== undefined && confirmationTarget !== null;
  const typedValue = confirmationValue ?? '';
  const isMatch = requiresTypedConfirmation && typedValue.trim() === confirmationTarget;

  const handleConfirm = () => {
    if (requiresTypedConfirmation && !isMatch) return;
    onConfirm?.();
  };

  return (
    <Dialog
      open={open}
      onClose={onCancel}
      maxWidth="xs"
      fullWidth
      className="editorial-dialog"
      TransitionComponent={Fade}
      transitionDuration={180}
      sx={dialogSx}
    >
      <DialogTitle sx={{ borderBottom: '1px solid', borderColor: 'divider', pb: 1.5 }}>{title}</DialogTitle>
      <DialogContent>
        <DialogContentText component="div">
          {message}
          {requiresTypedConfirmation && (
            <Box sx={{ mt: 2 }}>
              <Typography variant="body2" sx={{ mb: 1 }}>
                Type <strong>{confirmationTarget}</strong> to disable this workspace.
              </Typography>
              <TextField
                fullWidth
                size="small"
                label={confirmationLabel}
                value={typedValue}
                onChange={(event) => onConfirmationChange?.(event.target.value)}
                onPaste={(event) => event.preventDefault()}
                autoFocus
                error={Boolean(typedValue) && !isMatch}
                helperText={Boolean(typedValue) && !isMatch ? 'Workspace name does not match.' : ' '}
                inputProps={{ 'aria-label': confirmationLabel, 'data-testid': 'typed-confirmation-input' }}
              />
            </Box>
          )}
        </DialogContentText>
      </DialogContent>
      <DialogActions sx={{ borderTop: '1px solid', borderColor: 'divider', p: 2 }}>
        <Button onClick={onCancel} sx={{ transition: 'background-color 150ms ease-out' }}>{cancelLabel || 'Cancel'}</Button>
        <Button
          onClick={handleConfirm}
          variant="contained"
          color="error"
          autoFocus={!requiresTypedConfirmation}
          disabled={requiresTypedConfirmation && !isMatch}
          sx={{ fontWeight: 700, transition: 'background-color 150ms ease-out' }}
        >
          {confirmLabel || 'Confirm'}
        </Button>
      </DialogActions>
    </Dialog>
  );
}
