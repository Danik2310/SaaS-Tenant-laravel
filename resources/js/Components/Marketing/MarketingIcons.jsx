export const iconProps = {
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: 1.6,
    strokeLinecap: 'round',
    strokeLinejoin: 'round',
};

export function BoxIcon({ className = 'h-6 w-6' }) {
    return (
        <svg viewBox="0 0 24 24" {...iconProps} className={className} aria-hidden="true">
            <path d="M20 7.5 12 3 4 7.5v9L12 21l8-4.5v-9Z" />
            <path d="M4.5 7.6 12 12l7.5-4.4" />
            <path d="M12 12v9" />
        </svg>
    );
}

export function TagIcon({ className = 'h-6 w-6' }) {
    return (
        <svg viewBox="0 0 24 24" {...iconProps} className={className} aria-hidden="true">
            <path d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
            <path d="M6 6h.008v.008H6V6Z" />
        </svg>
    );
}

export function MapPinIcon({ className = 'h-6 w-6' }) {
    return (
        <svg viewBox="0 0 24 24" {...iconProps} className={className} aria-hidden="true">
            <path d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            <path d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
        </svg>
    );
}

export function ArrowsIcon({ className = 'h-6 w-6' }) {
    return (
        <svg viewBox="0 0 24 24" {...iconProps} className={className} aria-hidden="true">
            <path d="M8 7h12m0 0-3-3m3 3-3 3" />
            <path d="M16 17H4m0 0 3 3m-3-3 3-3" />
        </svg>
    );
}

export function ChartIcon({ className = 'h-6 w-6' }) {
    return (
        <svg viewBox="0 0 24 24" {...iconProps} className={className} aria-hidden="true">
            <path d="M4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75C3 12.504 3.504 12 4.125 12Z" />
            <path d="M9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625Z" />
            <path d="M16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
        </svg>
    );
}

export function ShieldIcon({ className = 'h-6 w-6' }) {
    return (
        <svg viewBox="0 0 24 24" {...iconProps} className={className} aria-hidden="true">
            <path d="M12 3 5 6.5v5c0 4.2 2.9 7.6 7 9.5 4.1-1.9 7-5.3 7-9.5v-5L12 3Z" />
            <path d="m9 11.5 2 2 4-4" />
        </svg>
    );
}

export function TableIcon({ className = 'h-7 w-7' }) {
    return (
        <svg viewBox="0 0 24 24" {...iconProps} className={className} aria-hidden="true">
            <path d="M3 5.25A2.25 2.25 0 0 1 5.25 3h13.5A2.25 2.25 0 0 1 21 5.25v13.5A2.25 2.25 0 0 1 18.75 21H5.25A2.25 2.25 0 0 1 3 18.75V5.25Z" />
            <path d="M3 9.5h18M9.5 9.5V21" />
        </svg>
    );
}

export function PulseIcon({ className = 'h-7 w-7' }) {
    return (
        <svg viewBox="0 0 24 24" {...iconProps} className={className} aria-hidden="true">
            <path d="M2 12h4l2.5-7 4.5 14 2.5-7h6.5" />
        </svg>
    );
}
