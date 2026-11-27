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
