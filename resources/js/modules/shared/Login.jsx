import React, { useState } from 'react';
import { toast } from 'sonner';
import api from '../../services/api';
import Wordmark from '../../Components/BrandLogo';

const glowStyle = (size, color, opacity, position) => ({
    position: 'absolute',
    ...position,
    width: size,
    height: size,
    borderRadius: '50%',
    backgroundColor: color,
    opacity,
    filter: 'blur(96px)',
    pointerEvents: 'none',
});

const fieldStyle = (focused) => ({
    width: '100%',
    padding: '12px 16px',
    border: focused ? '1px solid #F97316' : '1px solid #E5E7EB',
    borderRadius: '10px',
    fontSize: '14px',
    boxSizing: 'border-box',
    fontFamily: 'inherit',
    outline: 'none',
    boxShadow: focused ? '0 0 0 3px rgba(249,115,22,0.18)' : 'none',
    transition: 'border-color 0.15s ease, box-shadow 0.15s ease',
    backgroundColor: '#FFFFFF',
});

export default function Login() {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(false);
    const [focus, setFocus] = useState({ email: false, password: false });

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setError(null);

        try {
            const response = await api.post('/central/login', {
                email,
                password,
            });

            if (response.data.success) {
                // Redirect to dashboard or refresh to get the updated user
                window.location.href = '/admin/dashboard';
            }
        } catch (err) {
            const message = err.response?.data?.errors?.email?.[0]
                || err.response?.data?.message
                || 'Login failed. Please try again.';
            toast.error(message);
            setError(message);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div
            style={{
                minHeight: '100vh',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                position: 'relative',
                overflow: 'hidden',
                backgroundColor: '#0A0A0A',
                fontFamily: "'DM Sans', sans-serif",
                padding: '16px',
            }}
        >
            <div aria-hidden="true" style={glowStyle('384px', '#F97316', 0.2, { top: '-96px', right: '-96px' })} />
            <div aria-hidden="true" style={glowStyle('320px', '#EA580C', 0.12, { bottom: '-128px', left: '-96px' })} />

            <div
                style={{
                    position: 'relative',
                    background: 'white',
                    padding: '40px 32px',
                    borderRadius: '20px',
                    boxShadow: '0 24px 80px rgba(0, 0, 0, 0.5)',
                    width: '100%',
                    maxWidth: '400px',
                }}
            >
                <div style={{ display: 'flex', justifyContent: 'center' }}>
                    <Wordmark />
                </div>

                <h1
                    style={{
                        textAlign: 'center',
                        margin: '20px 0 4px',
                        color: '#0A0A0A',
                        fontFamily: "'Archivo Black', sans-serif",
                        fontWeight: 400,
                        fontSize: '22px',
                        letterSpacing: '-0.02em',
                    }}
                >
                    Admin
                </h1>
                <p style={{ textAlign: 'center', color: '#6B7280', marginBottom: '28px', fontSize: '14px' }}>
                    Manage the ShoppingLi platform
                </p>

                {error && (
                    <div
                        style={{
                            background: '#FEF2F2',
                            color: '#B91C1C',
                            padding: '12px 15px',
                            borderRadius: '10px',
                            marginBottom: '20px',
                            fontSize: '14px',
                        }}
                    >
                        {error}
                    </div>
                )}

                <form onSubmit={handleSubmit}>
                    <div style={{ marginBottom: '16px' }}>
                        <label style={{ display: 'block', marginBottom: '8px', color: '#0A0A0A', fontWeight: '500', fontSize: '14px' }}>
                            Email
                        </label>
                        <input
                            type="email"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            onFocus={() => setFocus((f) => ({ ...f, email: true }))}
                            onBlur={() => setFocus((f) => ({ ...f, email: false }))}
                            placeholder="admin@example.com"
                            style={fieldStyle(focus.email)}
                            required
                        />
                    </div>

                    <div style={{ marginBottom: '28px' }}>
                        <label style={{ display: 'block', marginBottom: '8px', color: '#0A0A0A', fontWeight: '500', fontSize: '14px' }}>
                            Password
                        </label>
                        <input
                            type="password"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            onFocus={() => setFocus((f) => ({ ...f, password: true }))}
                            onBlur={() => setFocus((f) => ({ ...f, password: false }))}
                            placeholder="Enter your password"
                            style={fieldStyle(focus.password)}
                            required
                        />
                    </div>

                    <button
                        type="submit"
                        disabled={loading}
                        style={{
                            width: '100%',
                            padding: '12px',
                            borderRadius: '999px',
                            backgroundColor: '#F97316',
                            color: 'white',
                            border: 'none',
                            fontSize: '15px',
                            fontWeight: '600',
                            cursor: loading ? 'not-allowed' : 'pointer',
                            transition: 'background-color 0.15s ease',
                            opacity: loading ? 0.7 : 1,
                        }}
                        onMouseEnter={(e) => {
                            if (!loading) e.target.style.backgroundColor = '#EA580C';
                        }}
                        onMouseLeave={(e) => {
                            if (!loading) e.target.style.backgroundColor = '#F97316';
                        }}
                    >
                        {loading ? 'Logging in...' : 'Log In'}
                    </button>
                </form>
            </div>
        </div>
    );
}