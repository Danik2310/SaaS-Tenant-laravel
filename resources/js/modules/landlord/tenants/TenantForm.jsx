import React, { useState, useEffect } from 'react';
import { toast } from 'sonner';

export default function TenantForm({ tenant = null, onSubmit, onCancel }) {
    const [name, setName] = useState(tenant?.name || '');
    const [email, setEmail] = useState(tenant?.email || '');
    const [domain, setDomain] = useState(tenant?.domain || '');
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        // reset fields if tenant prop changes (e.g. editing a different record)
        setName(tenant?.name || '');
        setEmail(tenant?.email || '');
        setDomain(tenant?.domain || '');
        setError(null);
    }, [tenant]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setError(null);

        try {
            const payload = { name, email };
            if (tenant) {
                // include id so update handler can build URL correctly
                payload.id = tenant.id;
            } else {
                payload.domain = domain;
            }
            await onSubmit(payload);
            toast.success(tenant ? 'Tenant updated successfully' : 'Tenant created successfully');
            if (!tenant) {
                setName('');
                setEmail('');
                setDomain('');
            }
        } catch (err) {
            const message = err.message || (tenant ? 'Failed to update tenant' : 'Failed to create tenant');
            toast.error(message);
            setError(message);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div
            style={{
                background: 'white',
                padding: '30px',
                borderRadius: '8px',
                boxShadow: '0 2px 8px rgba(0, 0, 0, 0.1)',
                maxWidth: '500px',
            }}
        >
            <h3 style={{ marginTop: 0, marginBottom: '20px', color: '#333' }}>
                {tenant ? (
                    <>
                        Edit Tenant: <code style={{ fontSize: '12px', background: '#f5f5f5', padding: '2px 6px', borderRadius: '3px' }}>{tenant.id}</code>
                    </>
                ) : (
                    'Create New Tenant'
                )}
            </h3>

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
                    <label
                        style={{
                            display: 'block',
                            marginBottom: '8px',
                            fontWeight: '600',
                            color: '#333',
                            fontSize: '14px',
                        }}
                    >
                        Tenant Name *
                    </label>
                    <input
                        type="text"
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        placeholder="e.g., Acme Corp"
                        style={{
                            width: '100%',
                            padding: '10px 12px',
                            border: '1px solid #ddd',
                            borderRadius: '5px',
                            fontSize: '14px',
                            boxSizing: 'border-box',
                            fontFamily: 'inherit',
                        }}
                        required
                    />
                </div>

                <div style={{ marginBottom: '20px' }}>
                    <label
                        style={{
                            display: 'block',
                            marginBottom: '8px',
                            fontWeight: '600',
                            color: '#333',
                            fontSize: '14px',
                        }}
                    >
                        Email *
                    </label>
                    <input
                        type="email"
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        placeholder="admin@tenant.com"
                        style={{
                            width: '100%',
                            padding: '10px 12px',
                            border: '1px solid #ddd',
                            borderRadius: '5px',
                            fontSize: '14px',
                            boxSizing: 'border-box',
                            fontFamily: 'inherit',
                        }}
                        required
                    />
                </div>

                {!tenant && (
                    <div style={{ marginBottom: '30px' }}>
                        <label
                            style={{
                                display: 'block',
                                marginBottom: '8px',
                                fontWeight: '600',
                                color: '#333',
                                fontSize: '14px',
                            }}
                        >
                            Domain *
                        </label>
                        <input
                            type="text"
                            value={domain}
                            onChange={(e) => setDomain(e.target.value)}
                            placeholder="acme.localhost (or acme.yourdomain.com)"
                            style={{
                                width: '100%',
                                padding: '10px 12px',
                                border: '1px solid #ddd',
                                borderRadius: '5px',
                                fontSize: '14px',
                                boxSizing: 'border-box',
                                fontFamily: 'inherit',
                            }}
                            required
                        />
                        <p style={{ fontSize: '12px', color: '#999', margin: '8px 0 0 0' }}>
                            This will be the unique identifier for the tenant's environment
                        </p>
                    </div>
                )}

                {tenant && (
                    <div style={{ marginBottom: '20px', padding: '12px', background: '#f9f9f9', borderRadius: '5px', fontSize: '13px' }}>
                        <strong>Domain:</strong> <code style={{ color: '#667eea' }}>{tenant.domain}</code>
                        <p style={{ margin: '5px 0 0 0', color: '#666', fontSize: '12px' }}>
                            Note: Domain cannot be changed after creation
                        </p>
                    </div>
                )}

                <div style={{ display: 'flex', gap: '10px', justifyContent: 'flex-end' }}>
                    <button
                        type="button"
                        onClick={onCancel}
                        style={{
                            padding: '10px 20px',
                            background: '#ddd',
                            color: '#333',
                            border: 'none',
                            borderRadius: '5px',
                            cursor: 'pointer',
                            fontSize: '14px',
                            fontWeight: '600',
                        }}
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        disabled={loading}
                        style={{
                            padding: '10px 20px',
                            background: loading ? '#ccc' : tenant ? '#3498db' : '#27ae60',
                            color: 'white',
                            border: 'none',
                            borderRadius: '5px',
                            cursor: loading ? 'not-allowed' : 'pointer',
                            fontSize: '14px',
                            fontWeight: '600',
                        }}
                    >
                        {loading ? (tenant ? 'Updating...' : 'Creating...') : tenant ? 'Update Tenant' : 'Create Tenant'}
                    </button>
                </div>
            </form>
        </div>
    );
}