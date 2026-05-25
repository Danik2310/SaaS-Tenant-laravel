import React from 'react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import Button from '@mui/material/Button';
import LockIcon from '@mui/icons-material/Lock';
import { usePlan } from '../hooks/usePlan';

function DefaultFallback({ feature }) {
  return (
    <Box sx={{ textAlign: 'center', py: 6, px: 2 }}>
      <LockIcon sx={{ fontSize: 48, color: '#94a3b8', mb: 2 }} />
      <Typography variant="h6" sx={{ color: '#0f172a', mb: 1, fontWeight: 600 }}>
        Premium Feature
      </Typography>
      <Typography variant="body2" sx={{ color: '#64748b', mb: 3, maxWidth: 400, mx: 'auto' }}>
        {feature
          ? `"${feature}" is not available on your current plan. Upgrade to unlock it.`
          : 'This feature is not available on your current plan. Upgrade to unlock it.'}
      </Typography>
      <Button
        variant="contained"
        size="small"
        onClick={() => window.location.href = '/billing'}
        sx={{ bgcolor: '#3b82f6', '&:hover': { bgcolor: '#2563eb' }, fontWeight: 600 }}
      >
        Upgrade Plan
      </Button>
    </Box>
  );
}

export default function PlanGate({ feature, plan, children, fallback, invert }) {
  const planHook = usePlan();

  let allowed = true;

  if (feature) {
    allowed = planHook.hasFeature(feature);
  } else if (plan) {
    allowed = planHook.isAtLeast(plan);
  }

  if (invert) allowed = !allowed;

  if (allowed) return children;

  if (fallback === null) return null;

  if (fallback) {
    const FallbackComponent = fallback;
    return <FallbackComponent feature={feature} plan={plan} />;
  }

  return <DefaultFallback feature={feature} />;
}
