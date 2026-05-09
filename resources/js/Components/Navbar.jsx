import React from 'react';
import DashboardIcon from '@mui/icons-material/Dashboard';
import PeopleIcon from '@mui/icons-material/People';
import GroupIcon from '@mui/icons-material/Group';
import SecurityIcon from '@mui/icons-material/Security';
import StorageIcon from '@mui/icons-material/Storage';
import VisibilityIcon from '@mui/icons-material/Visibility';
import PersonIcon from '@mui/icons-material/Person';
import CreditCardIcon from '@mui/icons-material/CreditCard';
import HistoryIcon from '@mui/icons-material/History';
import SettingsIcon from '@mui/icons-material/Settings';

const views = [
    { id: 'overview', label: 'Overview', icon: <DashboardIcon />, section: 'Main' },
    { id: 'tenants', label: 'Tenant Management', icon: <PeopleIcon />, section: 'Main' },
    { id: 'staff', label: 'Staff Management', icon: <GroupIcon />, section: 'Administration' },
    { id: 'roles', label: 'Roles & Permissions', icon: <SecurityIcon />, section: 'Administration' },
    { id: 'subscriptions', label: 'Subscriptions', icon: <CreditCardIcon />, section: 'Billing' },
    { id: 'plans', label: 'Infrastructure & Plans', icon: <StorageIcon />, section: 'Configuration' },
    { id: 'settings', label: 'System Settings', icon: <SettingsIcon />, section: 'Configuration' },
    { id: 'activity', label: 'Activity Logs', icon: <HistoryIcon />, section: 'Tools' },
    { id: 'impersonate', label: 'God Mode', icon: <VisibilityIcon />, section: 'Tools' },
    { id: 'profile', label: 'My Profile', icon: <PersonIcon />, section: 'Account' },
];

export default function Navbar({ user, view, setView }) {
    const grouped = views.reduce((acc, v) => {
        if (!acc[v.section]) acc[v.section] = [];
        acc[v.section].push(v);
        return acc;
    }, {});

    return (
        <aside style={{
            width: '260px',
            background: '#0f172a',
            color: 'white',
            minHeight: '100vh',
            display: 'flex',
            flexDirection: 'column',
            padding: '0',
            borderRight: '1px solid #1e293b',
        }}>
            <div style={{ padding: '24px 20px', borderBottom: '1px solid #1e293b' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                    <div style={{
                        width: '40px',
                        height: '40px',
                        borderRadius: '10px',
                        background: 'linear-gradient(135deg, #3b82f6, #8b5cf6)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        fontWeight: 800,
                        fontSize: '18px',
                    }}>
                        S
                    </div>
                    <div>
                        <div style={{ fontWeight: 700, fontSize: '16px' }}>SaaS Admin</div>
                        <div style={{ fontSize: '11px', opacity: 0.6 }}>Management Console</div>
                    </div>
                </div>
            </div>

            <div style={{ padding: '16px 12px 8px' }}>
                <div style={{ fontSize: '11px', fontWeight: 600, color: '#64748b', padding: '0 8px', marginBottom: '8px', textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                    {user?.name}
                </div>
            </div>

            <nav style={{ flex: 1, padding: '8px 12px', overflowY: 'auto' }}>
                {Object.entries(grouped).map(([section, items]) => (
                    <div key={section} style={{ marginBottom: '20px' }}>
                        <div style={{
                            fontSize: '10px',
                            fontWeight: 600,
                            color: '#475569',
                            padding: '0 8px',
                            marginBottom: '6px',
                            textTransform: 'uppercase',
                            letterSpacing: '0.08em',
                        }}>
                            {section}
                        </div>
                        {items.map((item) => (
                            <button
                                key={item.id}
                                onClick={() => setView(item.id)}
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: '10px',
                                    width: '100%',
                                    padding: '10px 12px',
                                    background: view === item.id ? '#3b82f6' : 'transparent',
                                    color: view === item.id ? 'white' : '#94a3b8',
                                    border: 'none',
                                    borderRadius: '8px',
                                    cursor: 'pointer',
                                    fontWeight: view === item.id ? 600 : 500,
                                    fontSize: '13px',
                                    textAlign: 'left',
                                    marginBottom: '2px',
                                    transition: 'all 0.15s ease',
                                }}
                                onMouseEnter={(e) => {
                                    if (view !== item.id) {
                                        e.currentTarget.style.background = '#1e293b';
                                        e.currentTarget.style.color = '#e2e8f0';
                                    }
                                }}
                                onMouseLeave={(e) => {
                                    if (view !== item.id) {
                                        e.currentTarget.style.background = 'transparent';
                                        e.currentTarget.style.color = '#94a3b8';
                                    }
                                }}
                            >
                                {React.cloneElement(item.icon, { fontSize: 'small' })}
                                {item.label}
                            </button>
                        ))}
                    </div>
                ))}
            </nav>

            <div style={{ padding: '16px 20px', borderTop: '1px solid #1e293b', fontSize: '11px', color: '#475569' }}>
                v0.2
            </div>
        </aside>
    );
}
