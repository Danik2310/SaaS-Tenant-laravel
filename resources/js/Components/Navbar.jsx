import React from 'react';

export default function Navbar({ user, view, setView }) {
    return (
        <aside style={{ width: '240px', background: '#1f2937', color: 'white', minHeight: '100vh', padding: '20px' }}>
            <div style={{ marginBottom: '30px' }}>
                <div style={{ fontSize: '14px', opacity: 0.8 }}>Signed in as</div>
                <div style={{ fontWeight: '700', marginTop: '6px' }}>{user?.name}</div>
            </div>

            <nav style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                <button onClick={() => setView('tenants')} style={navButtonStyle(view === 'tenants')}>Tenant Management</button>
                <button onClick={() => setView('staff')} style={navButtonStyle(view === 'staff')}>Staff Management</button>
                <button onClick={() => setView('roles')} style={navButtonStyle(view === 'roles')}>Roles & Permissions</button>
                <button onClick={() => setView('plans')} style={navButtonStyle(view === 'plans')}>Infrastructure and Global Configuration</button>
                <button onClick={() => setView('impersonate')} style={navButtonStyle(view === 'impersonate')}>God Mode (Impersonation)</button>
            </nav>

            <div style={{ marginTop: 'auto', fontSize: '12px', opacity: 0.7 }}>
                <div>Version: 0.1</div>
            </div>
        </aside>
    );
}

const navButtonStyle = (active) => ({
    textAlign: 'left',
    padding: '10px 12px',
    background: active ? '#374151' : 'transparent',
    color: 'white',
    border: 'none',
    borderRadius: '6px',
    cursor: 'pointer',
    fontWeight: 600,
});