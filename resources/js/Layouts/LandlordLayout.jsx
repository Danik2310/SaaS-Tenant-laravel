import React from 'react';

export default function LandlordLayout({ children }) {
    return (
        <div style={{ display: 'flex', minHeight: '100vh' }}>
            {children}
        </div>
    );
}