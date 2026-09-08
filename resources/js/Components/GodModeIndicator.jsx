import { usePage, router } from '@inertiajs/react';
import VisibilityIcon from '@mui/icons-material/Visibility';
import CloseIcon from '@mui/icons-material/Close';

export default function GodModeIndicator() {
    const { impersonation } = usePage().props;

    if (impersonation?.active !== true) {
        return null;
    }

    return (
        <div
            data-testid="god-mode-indicator"
            style={{
                position: 'fixed',
                bottom: 20,
                right: 20,
                zIndex: 1300,
                display: 'flex',
                alignItems: 'center',
                gap: 12,
                background: '#fef3c7',
                border: '1px solid #f59e0b',
                borderRadius: 999,
                padding: '8px 14px',
                boxShadow: '0 4px 14px rgba(0,0,0,0.18)',
                cursor: 'default',
            }}
        >
            <VisibilityIcon
                data-testid="god-mode-eye"
                style={{ color: '#ef4444', animation: 'god-mode-blink 1.2s ease-in-out infinite' }}
            />
            <div style={{ lineHeight: 1.3 }}>
                <div style={{ fontWeight: 700, fontSize: 13, color: '#78350f' }}>
                    God Mode — {impersonation?.tenant_name}
                </div>
                {impersonation?.read_only && (
                    <div style={{ fontSize: 12, color: '#92400e', display: 'flex', alignItems: 'center', gap: 4 }}>
                        <span>Read-only session</span>
                    </div>
                )}
            </div>
            <button
                onClick={() => router.post(route('god-mode.stop'))}
                aria-label="Return to Admin"
                title="Return to Admin"
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 4,
                    background: '#b45309',
                    color: 'white',
                    border: 'none',
                    borderRadius: 999,
                    padding: '6px 12px',
                    cursor: 'pointer',
                    fontSize: 12,
                    fontWeight: 600,
                }}
            >
                <CloseIcon style={{ fontSize: 15 }} />
                Return to Admin
            </button>
            <style>{`
                @keyframes god-mode-blink {
                    0%, 100% { opacity: 1; }
                    50% { opacity: 0.2; }
                }
            `}</style>
        </div>
    );
}
