import React, { useState } from 'react';
import { toast } from 'sonner';
import api from '../../services/api';

export default function Login() {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(false);

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
            const message = err.response?.data?.message || 'Login failed. Please try again.';
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
                background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                fontFamily: 'sans-serif',
            }}
        >
            <div
                style={{
                    background: 'white',
                    padding: '40px',
                    borderRadius: '10px',
                    boxShadow: '0 10px 40px rgba(0, 0, 0, 0.2)',
                    width: '100%',
                    maxWidth: '400px',
                }}
            >
                <h1 style={{ textAlign: 'center', marginBottom: '10px', color: '#333' }}>
                    Admin Panel
                </h1>
                <p style={{ textAlign: 'center', color: '#999', marginBottom: '30px' }}>
                    SaaS Platform Administration
                </p>

                {error && (
                    <div
                        style={{
                            background: '#fee',
                            color: '#c33',
                            padding: '12px 15px',
                            borderRadius: '5px',
                            marginBottom: '20px',
                            fontSize: '14px',
                        }}
                    >
                        {error}
                    </div>
                )}

                <form onSubmit={handleSubmit}>
                    <div style={{ marginBottom: '20px' }}>
                        <label style={{ display: 'block', marginBottom: '8px', color: '#333', fontWeight: '500' }}>
                            Email
                        </label>
                        <input
                            type="email"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            placeholder="admin@example.com"
                            style={{
                                width: '100%',
                                padding: '12px 15px',
                                border: '1px solid #ddd',
                                borderRadius: '5px',
                                fontSize: '14px',
                                boxSizing: 'border-box',
                                fontFamily: 'inherit',
                            }}
                            required
                        />
                    </div>

                    <div style={{ marginBottom: '30px' }}>
                        <label style={{ display: 'block', marginBottom: '8px', color: '#333', fontWeight: '500' }}>
                            Password
                        </label>
                        <input
                            type="password"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            placeholder="Enter your password"
                            style={{
                                width: '100%',
                                padding: '12px 15px',
                                border: '1px solid #ddd',
                                borderRadius: '5px',
                                fontSize: '14px',
                                boxSizing: 'border-box',
                                fontFamily: 'inherit',
                            }}
                            required
                        />
                    </div>

                    <button
                        type="submit"
                        disabled={loading}
                        style={{
                            width: '100%',
                            padding: '12px',
                            background: loading ? '#ccc' : '#667eea',
                            color: 'white',
                            border: 'none',
                            borderRadius: '5px',
                            fontSize: '16px',
                            fontWeight: '600',
                            cursor: loading ? 'not-allowed' : 'pointer',
                            transition: 'background 0.3s',
                        }}
                        onMouseEnter={(e) => {
                            if (!loading) e.target.style.background = '#5568d3';
                        }}
                        onMouseLeave={(e) => {
                            if (!loading) e.target.style.background = '#667eea';
                        }}
                    >
                        {loading ? 'Logging in...' : 'Log In'}
                    </button>
                </form>

                
            </div>
        </div>
    );
}
