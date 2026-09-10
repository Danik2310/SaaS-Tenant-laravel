import { useEffect, useState } from 'react';
import { usePage, router } from '@inertiajs/react';
import VisibilityIcon from '@mui/icons-material/Visibility';
import CloseIcon from '@mui/icons-material/Close';

const SESSION_KEY = 'god_mode_session';

const storage = {
    get() {
        try {
            return sessionStorage.getItem(SESSION_KEY);
        } catch {
            return null;
        }
    },
    set(value) {
        try {
            sessionStorage.setItem(SESSION_KEY, value);
        } catch {
            // Storage unavailable (e.g. opaque origin) — server prop will cover this render.
        }
    },
    remove() {
        try {
            sessionStorage.removeItem(SESSION_KEY);
        } catch {
            // ignore
        }
    },
};

export default function GodModeIndicator() {
    const { impersonation } = usePage().props;
    const [persisted, setPersisted] = useState(null);

    useEffect(() => {
        if (impersonation?.active === true) {
            const data = {
                admin_name: impersonation.admin_name,
                tenant_name: impersonation.tenant_name,
                tenant_id: impersonation.tenant_id,
                read_only: impersonation.read_only,
            };
            storage.set(JSON.stringify(data));
            setPersisted(data);
        }
    }, [impersonation]);

    useEffect(() => {
        if (impersonation?.active !== true && persisted === null) {
            const stored = storage.get();
            if (stored) {
                try {
                    setPersisted(JSON.parse(stored));
                } catch {
                    storage.remove();
                }
            }
        }
    }, [impersonation, persisted]);

    const display = impersonation?.active === true ? impersonation : persisted;

    if (!display) {
        return null;
    }

    const handleStop = () => {
        storage.remove();
        setPersisted(null);
        router.post(route('god-mode.stop'));
    };

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
                    God Mode — running as {display.admin_name}
                </div>
                <div style={{ fontSize: 12, color: '#92400e' }}>
                    {display.tenant_name}
                </div>
                {display.read_only && (
                    <div style={{ fontSize: 12, color: '#92400e', display: 'flex', alignItems: 'center', gap: 4 }}>
                        <span>Read-only session</span>
                    </div>
                )}
            </div>
            <button
                onClick={handleStop}
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