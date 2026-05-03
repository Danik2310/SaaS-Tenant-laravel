import React from 'react';
import { Link } from '@inertiajs/react';
import BlockIcon from '@mui/icons-material/Block';
import HomeIcon from '@mui/icons-material/Home';

export default function Unauthorized({ message }) {
    return (
        <div style={{
            minHeight: '100vh',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            background: '#f8fafc',
            padding: '24px',
        }}>
            <div style={{
                background: 'white',
                borderRadius: '12px',
                boxShadow: '0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06)',
                padding: '48px',
                maxWidth: '480px',
                width: '100%',
                textAlign: 'center',
            }}>
                <div style={{
                    width: '80px',
                    height: '80px',
                    borderRadius: '50%',
                    background: '#fef2f2',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    margin: '0 auto 24px',
                }}>
                    <BlockIcon sx={{ fontSize: 40, color: '#ef4444' }} />
                </div>

                <h1 style={{
                    margin: '0 0 8px',
                    fontSize: '24px',
                    fontWeight: 700,
                    color: '#0f172a',
                }}>
                    403 - Access Denied
                </h1>

                <p style={{
                    margin: '0 0 32px',
                    fontSize: '15px',
                    color: '#64748b',
                    lineHeight: 1.6,
                }}>
                    {message || 'You do not have permission to access this page. Please contact your administrator if you believe this is an error.'}
                </p>

                <Link
                    href="/admin/dashboard"
                    style={{
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: '8px',
                        padding: '12px 28px',
                        background: '#3b82f6',
                        color: 'white',
                        border: 'none',
                        borderRadius: '8px',
                        cursor: 'pointer',
                        fontSize: '14px',
                        fontWeight: 600,
                        textDecoration: 'none',
                        transition: 'background 0.15s ease',
                    }}
                    onMouseEnter={(e) => e.currentTarget.style.background = '#2563eb'}
                    onMouseLeave={(e) => e.currentTarget.style.background = '#3b82f6'}
                >
                    <HomeIcon fontSize="small" />
                    Return to Dashboard
                </Link>
            </div>
        </div>
    );
}
