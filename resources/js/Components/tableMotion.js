export const tableMotionSx = {
    '@keyframes tableViewIn': {
        from: { opacity: 0, transform: 'translateY(6px)' },
        to: { opacity: 1, transform: 'translateY(0)' },
    },
    '@keyframes tableViewFade': {
        from: { opacity: 0 },
        to: { opacity: 1 },
    },
    animation: 'tableViewIn 220ms cubic-bezier(0.16, 1, 0.3, 1) both',
    '@media (prefers-reduced-motion: reduce)': {
        animation: 'tableViewFade 140ms linear both',
    },
};

export const tableHeadCellSx = {
    fontWeight: 700,
    fontSize: '11px',
    color: 'text.secondary',
    textTransform: 'uppercase',
    letterSpacing: '0.06em',
    py: 1.5,
};

export const tableRowSx = {
    transition: 'background-color 150ms ease-out',
    '&:hover': { bgcolor: 'action.hover' },
};

export const rowMotionTimings = {
    enter: 180,
    exit: 150,
    update: 160,
    pending: 120,
    reduced: 140,
    staggerStep: 60,
    staggerCap: 180,
};

const rowMotionEase = 'cubic-bezier(0.16, 1, 0.3, 1)';

export const rowMotionKeyframes = {
    '@keyframes tableRowEnter': {
        from: { opacity: 0, transform: 'translateY(6px)' },
        to: { opacity: 1, transform: 'translateY(0)' },
    },
    '@keyframes tableRowExit': {
        from: { opacity: 1, transform: 'translateY(0)' },
        to: { opacity: 0, transform: 'translateY(-4px)' },
    },
    '@keyframes tableRowUpdate': {
        '0%, 100%': { opacity: 1, transform: 'translateY(0)' },
        '50%': { opacity: 0.72, transform: 'translateY(2px)' },
    },
    '@keyframes tableRowPending': {
        '0%, 100%': { opacity: 1, transform: 'translateY(0)' },
        '50%': { opacity: 0.68, transform: 'translateY(2px)' },
    },
    '@keyframes tableRowFadeIn': {
        from: { opacity: 0 },
        to: { opacity: 1 },
    },
    '@keyframes tableRowFadeOut': {
        from: { opacity: 1 },
        to: { opacity: 0 },
    },
    '@keyframes tableRowFadeDip': {
        '0%, 100%': { opacity: 1 },
        '50%': { opacity: 0.72 },
    },
};

const rowAnimations = {
    entering: (delay) => `tableRowEnter ${rowMotionTimings.enter}ms ${rowMotionEase} ${delay}ms both`,
    exiting: () => `tableRowExit ${rowMotionTimings.exit}ms ${rowMotionEase} both`,
    updating: () => `tableRowUpdate ${rowMotionTimings.update}ms ${rowMotionEase} both`,
    pending: () => `tableRowPending ${rowMotionTimings.pending}ms ${rowMotionEase} both`,
};

const reducedRowAnimations = {
    entering: (delay) => `tableRowFadeIn ${rowMotionTimings.reduced}ms linear ${Math.min(delay, rowMotionTimings.staggerCap)}ms both`,
    exiting: () => `tableRowFadeOut ${rowMotionTimings.reduced}ms linear both`,
    updating: () => `tableRowFadeDip ${rowMotionTimings.reduced}ms linear both`,
    pending: () => `tableRowFadeDip ${rowMotionTimings.reduced}ms linear both`,
};

export const animatedRowSx = (phase, delay = 0) => {
    if (!phase) return tableRowSx;

    return {
        ...tableRowSx,
        animation: rowAnimations[phase]?.(delay),
        pointerEvents: phase === 'exiting' ? 'none' : undefined,
        '@media (prefers-reduced-motion: reduce)': {
            animation: reducedRowAnimations[phase]?.(delay),
            transform: 'none',
        },
    };
};

export const iconButtonMotionSx = {
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    border: 'none',
    bgcolor: 'transparent',
    cursor: 'pointer',
    p: 0.5,
    borderRadius: 1,
    color: 'text.secondary',
    transition: 'background-color 150ms ease-out, transform 150ms ease-out',
    '&:hover': { bgcolor: 'action.hover', transform: 'translateY(-1px)' },
    '@media (prefers-reduced-motion: reduce)': {
        transition: 'background-color 150ms ease-out',
        '&:hover': { transform: 'none' },
    },
};

export const emptyStateSx = {
    textAlign: 'center',
    py: 6,
};
