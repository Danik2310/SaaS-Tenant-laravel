import Checkbox from '@mui/material/Checkbox';
import FormControlLabel from '@mui/material/FormControlLabel';
import FormGroup from '@mui/material/FormGroup';
import FormHelperText from '@mui/material/FormHelperText';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';

export default function FeatureFlagPicker({ definitions = {}, value = [], onChange, error }) {
    const handleToggle = (key) => {
        const next = value.includes(key) ? value.filter(k => k !== key) : [...value, key];
        onChange(next);
    };

    const entries = Object.entries(definitions || {}).filter(([, def]) => def?.is_active !== false);

    return (
        <Box>
            <FormGroup sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr', sm: '1fr 1fr' }, gap: 0.5 }}>
                {entries.map(([key, def]) => (
                    <FormControlLabel
                        key={key}
                        control={
                            <Checkbox
                                checked={value.includes(key)}
                                onChange={() => handleToggle(key)}
                                size="small"
                            />
                        }
                        label={
                            <Box>
                                <Typography variant="body2" sx={{ fontWeight: 600, color: '#0f172a', fontSize: 13 }}>
                                    {def?.label ?? key}
                                </Typography>
                                {def?.description && (
                                    <Typography variant="caption" sx={{ color: '#64748b', fontSize: 11 }}>
                                        {def.description}
                                    </Typography>
                                )}
                            </Box>
                        }
                    />
                ))}
            </FormGroup>
            {error && <FormHelperText error>{error}</FormHelperText>}
        </Box>
    );
}
