import React, { useState, useCallback } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import DashboardIcon from '@mui/icons-material/Dashboard';
import InventoryIcon from '@mui/icons-material/Inventory';
import CategoryIcon from '@mui/icons-material/Category';
import WarehouseIcon from '@mui/icons-material/Warehouse';
import MoveUpIcon from '@mui/icons-material/MoveUp';
import LogoutIcon from '@mui/icons-material/Logout';
import MenuOpenIcon from '@mui/icons-material/MenuOpen';
import GodModeIndicator from '@/Components/GodModeIndicator';

const navItems = [
    { id: 'tenant.dashboard', label: 'Dashboard', icon: <DashboardIcon />, section: 'Main' },
    { id: 'tenant.products.index', label: 'Products', icon: <InventoryIcon />, section: 'Inventory' },
    { id: 'tenant.categories.index', label: 'Categories', icon: <CategoryIcon />, section: 'Inventory' },
    { id: 'tenant.warehouses.index', label: 'Warehouses', icon: <WarehouseIcon />, section: 'Inventory' },
    { id: 'tenant.inventory.index', label: 'Movements', icon: <MoveUpIcon />, section: 'Inventory' },
];

const sectionLabels = { Main: 'Main', Inventory: 'Inventory', System: 'System' };

const TenantLayout = React.memo(function TenantLayout({ children }) {
    const { auth } = usePage().props;
    const [collapsed, setCollapsed] = useState(false);
    const [mobileOpen, setMobileOpen] = useState(false);

    const isActive = (routeName) => {
        try {
            return route().current(routeName + '*');
        } catch {
            return false;
        }
    };

    const handleNav = useCallback((routeName) => {
        router.visit(route(routeName));
        setMobileOpen(false);
    }, []);

    const sidebarWidth = collapsed ? 64 : 260;

    const navContent = (
        <>
            <div style={{
                padding: collapsed ? '16px 12px' : '20px 20px',
                borderBottom: '1px solid #1e293b',
                display: 'flex',
                alignItems: 'center',
                justifyContent: collapsed ? 'center' : 'space-between',
                minHeight: 64,
            }}>
                {!collapsed && (
                    <div>
                        <div style={{ fontWeight: 700, fontSize: '18px', color: 'white' }}>Tenant</div>
                        <div style={{ fontSize: '11px', color: '#64748b' }}>Management Console</div>
                    </div>
                )}
                <button onClick={() => setCollapsed(!collapsed)}
                    aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                    style={{ background: 'none', border: 'none', color: '#64748b', cursor: 'pointer', padding: 4 }}>
                    <MenuOpenIcon sx={{ transform: collapsed ? 'rotate(180deg)' : 'none', transition: '0.2s' }} />
                </button>
            </div>

            <nav style={{ flex: 1, padding: '8px 8px', overflowY: 'auto' }}>
                {Object.entries(
                    navItems.reduce((acc, item) => {
                        if (!acc[item.section]) acc[item.section] = [];
                        acc[item.section].push(item);
                        return acc;
                    }, {})
                ).map(([section, items]) => (
                    <div key={section} style={{ marginBottom: 16 }}>
                        {!collapsed && (
                            <div style={{
                                fontSize: 10, fontWeight: 600, color: '#475569',
                                padding: '0 8px', marginBottom: 4,
                                textTransform: 'uppercase', letterSpacing: '0.08em',
                            }}>
                                {section}
                            </div>
                        )}
                        {items.map((item) => (
                            <button
                                key={item.id}
                                onClick={() => handleNav(item.id)}
                                aria-label={item.label}
                                aria-current={isActive(item.id) ? 'page' : undefined}
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 10,
                                    width: '100%',
                                    padding: collapsed ? '10px' : '10px 12px',
                                    background: isActive(item.id) ? '#3b82f6' : 'transparent',
                                    color: isActive(item.id) ? 'white' : '#94a3b8',
                                    border: 'none',
                                    borderRadius: 8,
                                    cursor: 'pointer',
                                    fontWeight: isActive(item.id) ? 600 : 500,
                                    fontSize: 13,
                                    textAlign: 'left',
                                    marginBottom: 2,
                                    justifyContent: collapsed ? 'center' : 'flex-start',
                                    transition: 'all 0.15s ease',
                                }}
                                onMouseEnter={(e) => {
                                    if (!isActive(item.id)) {
                                        e.currentTarget.style.background = '#1e293b';
                                        e.currentTarget.style.color = '#e2e8f0';
                                    }
                                }}
                                onMouseLeave={(e) => {
                                    if (!isActive(item.id)) {
                                        e.currentTarget.style.background = 'transparent';
                                        e.currentTarget.style.color = '#94a3b8';
                                    }
                                }}
                            >
                                {React.cloneElement(item.icon, { fontSize: 'small' })}
                                {!collapsed && item.label}
                            </button>
                        ))}
                    </div>
                ))}
            </nav>

            <div style={{ padding: collapsed ? '12px' : '16px 20px', borderTop: '1px solid #1e293b' }}>
                {!collapsed && (
                    <>
                        <div style={{ fontSize: 13, fontWeight: 600, color: '#e2e8f0', marginBottom: 4 }}>
                            {auth?.user?.name}
                        </div>
                        <div style={{ fontSize: 11, color: '#64748b', marginBottom: 12 }}>
                            {auth?.user?.email}
                        </div>
                    </>
                )}
                <button
                    onClick={() => router.post(route('admin.logout'))}
                    aria-label="Logout"
                    style={{
                        display: 'flex', alignItems: 'center', gap: 8, width: '100%',
                        padding: collapsed ? '10px' : '10px 12px',
                        background: 'transparent', color: '#ef4444',
                        border: '1px solid #ef444433', borderRadius: 8,
                        cursor: 'pointer', fontSize: 13, fontWeight: 600,
                        justifyContent: collapsed ? 'center' : 'flex-start',
                    }}
                >
                    <LogoutIcon fontSize="small" />
                    {!collapsed && 'Logout'}
                </button>
            </div>
        </>
    );

    return (
        <div style={{ display: 'flex', minHeight: '100vh', background: '#f8fafc' }}>
            {/* Mobile overlay */}
            {mobileOpen && (
                    <div onClick={() => setMobileOpen(false)}
                    style={{ position: 'fixed', inset: 0, backgroundColor: 'rgba(0,0,0,0.5)', zIndex: 998 }} />
            )}

            {/* Mobile sidebar */}
            <aside className="tenant-sidebar-mobile" style={{
                position: 'fixed', top: 0, left: 0, bottom: 0, width: 260,
                background: '#0f172a', zIndex: 999,
                transform: mobileOpen ? 'translateX(0)' : 'translateX(-100%)',
                transition: 'transform 0.3s ease',
                display: 'none',
            }}>
                {navContent}
            </aside>

            {/* Desktop sidebar */}
            <aside className="tenant-sidebar-desktop" style={{
                width: sidebarWidth,
                background: '#0f172a',
                color: 'white',
                minHeight: '100vh',
                display: 'flex',
                flexDirection: 'column',
                transition: 'width 0.2s ease',
                borderRight: '1px solid #1e293b',
            }}>
                {navContent}
            </aside>

            {/* Mobile hamburger */}
            <button
                className="tenant-mobile-toggle"
                onClick={() => setMobileOpen(true)}
                aria-label="Open navigation menu"
                style={{
                    position: 'fixed', top: 12, left: 12, zIndex: 1000,
                    display: 'none',
                    background: '#0f172a', color: 'white', border: 'none',
                    borderRadius: 8, padding: 8, cursor: 'pointer',
                }}
            >
                <MenuOpenIcon />
            </button>

            {/* Main content */}
            <main style={{ flex: 1, minWidth: 0, padding: '16px 32px' }}>
                <div style={{ maxWidth: 1280, margin: '0 auto' }}>
                    {children}
                </div>
            </main>

            <GodModeIndicator />

            <style>{`
                @media (max-width: 900px) {
                    .tenant-sidebar-desktop { display: none !important; }
                    .tenant-sidebar-mobile { display: block !important; }
                    .tenant-mobile-toggle { display: flex !important; }
                    main { padding: 16px !important; }
                }
            `}</style>
        </div>
    );
});

export default TenantLayout;
