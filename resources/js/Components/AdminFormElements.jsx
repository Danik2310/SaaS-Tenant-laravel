import React from 'react';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import IconButton from '@mui/material/IconButton';
import Tooltip from '@mui/material/Tooltip';
import Typography from '@mui/material/Typography';
import CloseIcon from '@mui/icons-material/Close';
import { alpha, useTheme } from '@mui/material/styles';
import { surfaceMotionSx } from './surfaceMotion';

const buttonSx = {
    px: 2.5,
    py: 1,
    borderRadius: 2,
    fontWeight: 700,
    transition: 'background-color 150ms ease-out, border-color 150ms ease-out',
};

export function FormCard({ title, subtitle, children, onClose }) {
    return (
        <Box sx={{
            p: 3,
            mb: 2,
            bgcolor: 'background.paper',
            border: '1px solid',
            borderColor: 'divider',
            borderRadius: 2,
            boxShadow: 'none',
            overflow: 'hidden',
            ...surfaceMotionSx,
        }}
        >
            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 2, mb: 2.5 }}>
                <Box>
                    <Typography variant="subtitle1" sx={{ fontWeight: 700, color: 'text.primary' }}>
                        {title}
                    </Typography>
                    {subtitle && (
                        <Typography variant="caption" sx={{ display: 'block', mt: 0.5, color: 'text.secondary' }}>
                            {subtitle}
                        </Typography>
                    )}
                </Box>
                {onClose && (
                    <Tooltip title="Close">
                        <IconButton
                            size="small"
                            aria-label="Close"
                            onClick={onClose}
                            sx={{
                                color: 'text.disabled',
                                transition: 'background-color 150ms ease-out, color 150ms ease-out',
                                '&:hover': { color: 'text.primary', bgcolor: 'action.hover' },
                            }}
                        >
                            <CloseIcon fontSize="small" />
                        </IconButton>
                    </Tooltip>
                )}
            </Box>
            {children}
        </Box>
    );
}

export function FormInput({ label, required, hint, children, error }) {
    const theme = useTheme();
    const borderColor = error ? theme.palette.error.main : theme.palette.divider;
    const focusRing = `0 0 0 3px ${alpha(theme.palette.primary.main, 0.12)}`;

    return (
        <Box sx={{ mb: 2 }}>
            <Box component="label" sx={{ display: 'block', mb: 0.75, fontSize: 13, fontWeight: 600, color: 'text.secondary' }}>
                {label}
                {required && <Box component="span" sx={{ ml: 0.5, color: 'error.main' }}>*</Box>}
            </Box>
            {React.isValidElement(children) && React.cloneElement(children, {
                style: {
                    width: '100%',
                    padding: '10px 12px',
                    border: `1px solid ${borderColor}`,
                    borderRadius: 8,
                    fontSize: 14,
                    boxSizing: 'border-box',
                    fontFamily: 'inherit',
                    outline: 'none',
                    backgroundColor: theme.palette.background.paper,
                    color: theme.palette.text.primary,
                    transition: 'border-color 150ms ease-out, box-shadow 150ms ease-out',
                    ...children.props?.style,
                },
                onFocus: (event) => {
                    event.target.style.borderColor = theme.palette.primary.main;
                    event.target.style.boxShadow = focusRing;
                    children.props?.onFocus?.(event);
                },
                onBlur: (event) => {
                    if (!error) {
                        event.target.style.borderColor = borderColor;
                        event.target.style.boxShadow = 'none';
                    }
                    children.props?.onBlur?.(event);
                },
            })}
            {hint && !error && (
                <Typography variant="caption" sx={{ display: 'block', mt: 0.5, color: 'text.disabled' }}>
                    {hint}
                </Typography>
            )}
            {error && (
                <Typography variant="caption" sx={{ display: 'block', mt: 0.5, color: 'error.main' }}>
                    {error}
                </Typography>
            )}
        </Box>
    );
}

export function FormActions({ children }) {
    return (
        <Box sx={{ display: 'flex', gap: 1, justifyContent: 'flex-end', pt: 2, mt: 1, borderTop: '1px solid', borderColor: 'divider' }}>
            {children}
        </Box>
    );
}

export function ButtonPrimary({ children, onClick, disabled, type = 'button' }) {
    return (
        <Button
            type={type}
            variant="contained"
            onClick={onClick}
            disabled={disabled}
            sx={{
                ...buttonSx,
                bgcolor: 'primary.main',
                color: 'primary.contrastText',
                '&:hover': { bgcolor: 'primary.dark' },
                '&.Mui-disabled': { bgcolor: 'action.disabledBackground', color: 'text.disabled' },
            }}
        >
            {children}
        </Button>
    );
}

export function ButtonSecondary({ children, onClick, disabled, type = 'button' }) {
    return (
        <Button
            type={type}
            variant="outlined"
            onClick={onClick}
            disabled={disabled}
            sx={{
                ...buttonSx,
                bgcolor: 'background.paper',
                color: 'text.secondary',
                borderColor: 'divider',
                '&:hover': { bgcolor: 'action.hover', borderColor: 'text.disabled' },
                '&.Mui-disabled': { bgcolor: 'action.disabledBackground', color: 'text.disabled', borderColor: 'divider' },
            }}
        >
            {children}
        </Button>
    );
}

export function ButtonSuccess({ children, onClick, disabled, type = 'button' }) {
    return (
        <Button
            type={type}
            variant="contained"
            onClick={onClick}
            disabled={disabled}
            sx={{
                ...buttonSx,
                bgcolor: 'success.main',
                color: 'success.contrastText',
                '&:hover': { bgcolor: 'success.dark' },
                '&.Mui-disabled': { bgcolor: 'action.disabledBackground', color: 'text.disabled' },
            }}
        >
            {children}
        </Button>
    );
}

export function ButtonDanger({ children, onClick, disabled, type = 'button' }) {
    return (
        <Button
            type={type}
            variant="contained"
            onClick={onClick}
            disabled={disabled}
            sx={{
                ...buttonSx,
                bgcolor: 'error.main',
                color: 'error.contrastText',
                '&:hover': { bgcolor: 'error.dark' },
                '&.Mui-disabled': { bgcolor: 'action.disabledBackground', color: 'text.disabled' },
            }}
        >
            {children}
        </Button>
    );
}

export function SelectInput({ label, required, children, ...props }) {
    return (
        <FormInput label={label} required={required}>
            <select {...props} style={{ appearance: 'none', paddingRight: 32, ...props.style }}>
                {children}
            </select>
        </FormInput>
    );
}

export function TextAreaInput({ label, required, hint, children, ...props }) {
    return (
        <FormInput label={label} required={required} hint={hint}>
            <textarea rows={3} style={{ resize: 'vertical', minHeight: 80, ...props.style }} {...props}>
                {children}
            </textarea>
        </FormInput>
    );
}

export function CheckboxInput({ label, checked, onChange }) {
    const theme = useTheme();

    return (
        <Box component="label" sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 1, cursor: 'pointer' }}>
            <input
                type="checkbox"
                checked={checked}
                onChange={onChange}
                style={{ width: 16, height: 16, accentColor: theme.palette.primary.main, cursor: 'pointer' }}
            />
            <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                {label}
            </Typography>
        </Box>
    );
}

export function RadioInput({ label, checked, onChange, disabled = false }) {
    const theme = useTheme();

    return (
        <Box
            component="label"
            sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 1, cursor: disabled ? 'not-allowed' : 'pointer', opacity: disabled ? 0.6 : 1 }}
        >
            <input
                type="radio"
                name="admin-form-role"
                checked={checked}
                onChange={onChange}
                disabled={disabled}
                style={{ width: 16, height: 16, accentColor: theme.palette.primary.main, cursor: disabled ? 'not-allowed' : 'pointer' }}
            />
            <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                {label}
            </Typography>
        </Box>
    );
}
