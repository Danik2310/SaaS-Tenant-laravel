const surfaceKeyframes = {
    '@keyframes surfaceRise': {
        from: { opacity: 0, transform: 'translateY(8px)' },
        to: { opacity: 1, transform: 'translateY(0)' },
    },
    '@keyframes surfaceFade': {
        from: { opacity: 0 },
        to: { opacity: 1 },
    },
};

export const surfaceMotionSx = {
    ...surfaceKeyframes,
    animation: 'surfaceRise 220ms cubic-bezier(0.16, 1, 0.3, 1) both',
    '@media (prefers-reduced-motion: reduce)': {
        animation: 'surfaceFade 140ms linear both',
    },
};

export const dialogSx = {
    ...surfaceKeyframes,
    '& .MuiDialog-paper': {
        borderRadius: 2,
        animation: 'surfaceRise 220ms cubic-bezier(0.16, 1, 0.3, 1) both',
        '@media (prefers-reduced-motion: reduce)': {
            animation: 'surfaceFade 140ms linear both',
        },
    },
};
