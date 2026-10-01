export function LogoMark({ size = 36 }) {
    return (
        <svg viewBox="0 0 24 24" width={size} height={size} fill="none" aria-hidden="true">
            <rect width="24" height="24" rx="6" fill="#F97316" />
            <path
                d="M6 8h12l1 10a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L6 8Z"
                fill="#fff"
            />
            <path d="M9 8a3 3 0 0 1 6 0" stroke="#171717" strokeWidth="1.6" strokeLinecap="round" />
            <path d="M9 13v1.5M15 13v1.5" stroke="#171717" strokeWidth="1.6" strokeLinecap="round" />
        </svg>
    );
}

export default function Wordmark({ dark = true, size = 36 }) {
    return (
        <span style={{ display: 'inline-flex', alignItems: 'center', gap: '0.625rem' }}>
            <LogoMark size={size} />
            <span
                style={{
                    fontFamily: "'Archivo Black', sans-serif",
                    fontSize: `${Math.round((size / 36) * 20)}px`,
                    letterSpacing: '-0.02em',
                    color: dark ? '#FFFFFF' : '#0A0A0A',
                }}
            >
                Shopping<span style={{ color: '#F97316' }}>Li</span>
            </span>
        </span>
    );
}