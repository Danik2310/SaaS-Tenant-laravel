import React from 'react';

export function FormCard({ title, subtitle, children, onClose }) {
    return (
        <div style={{ background: 'white', padding: '24px', borderRadius: '8px', marginBottom: '16px', boxShadow: '0 1px 3px rgba(0,0,0,0.06)' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '20px' }}>
                <div>
                    <h3 style={{ margin: 0, fontSize: '16px', fontWeight: 600, color: '#0f172a' }}>{title}</h3>
                    {subtitle && <p style={{ margin: '4px 0 0', fontSize: '13px', color: '#64748b' }}>{subtitle}</p>}
                </div>
                {onClose && (
                    <button onClick={onClose} style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#94a3b8', fontSize: '20px', padding: '0 4px', lineHeight: 1 }}>×</button>
                )}
            </div>
            {children}
        </div>
    );
}

export function FormInput({ label, required, hint, children, error }) {
    return (
        <div style={{ marginBottom: '16px' }}>
            <label style={{ display: 'block', marginBottom: '6px', fontWeight: 500, fontSize: '13px', color: '#334155' }}>
                {label} {required && <span style={{ color: '#ef4444' }}>*</span>}
            </label>
            {React.isValidElement(children) && React.cloneElement(children, {
                style: {
                    width: '100%',
                    padding: '10px 12px',
                    border: `1px solid ${error ? '#ef4444' : '#e2e8f0'}`,
                    borderRadius: '6px',
                    fontSize: '14px',
                    boxSizing: 'border-box',
                    fontFamily: 'inherit',
                    outline: 'none',
                    transition: 'border-color 0.15s ease, box-shadow 0.15s ease',
                    backgroundColor: '#fff',
                    ...children.props?.style,
                },
                onFocus: (e) => {
                    e.target.style.borderColor = '#3b82f6';
                    e.target.style.boxShadow = '0 0 0 3px rgba(59,130,246,0.1)';
                    children.props?.onFocus?.(e);
                },
                onBlur: (e) => {
                    if (!error) {
                        e.target.style.borderColor = '#e2e8f0';
                        e.target.style.boxShadow = 'none';
                    }
                    children.props?.onBlur?.(e);
                },
            })}
            {hint && !error && <p style={{ margin: '4px 0 0', fontSize: '12px', color: '#94a3b8' }}>{hint}</p>}
            {error && <p style={{ margin: '4px 0 0', fontSize: '12px', color: '#ef4444' }}>{error}</p>}
        </div>
    );
}

export function FormActions({ children }) {
    return (
        <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-end', paddingTop: '16px', borderTop: '1px solid #f1f5f9', marginTop: '8px' }}>
            {children}
        </div>
    );
}

export const btnBase = {
    padding: '8px 20px',
    border: 'none',
    borderRadius: '6px',
    cursor: 'pointer',
    fontSize: '13px',
    fontWeight: 600,
    transition: 'background-color 0.15s ease',
};

export function ButtonPrimary({ children, onClick, disabled, type = 'button' }) {
    return (
        <button
            type={type}
            onClick={onClick}
            disabled={disabled}
            style={{
                ...btnBase,
                background: disabled ? '#94a3b8' : '#3b82f6',
                color: 'white',
                cursor: disabled ? 'not-allowed' : 'pointer',
            }}
        >
            {children}
        </button>
    );
}

export function ButtonSecondary({ children, onClick, disabled, type = 'button' }) {
    return (
        <button
            type={type}
            onClick={onClick}
            disabled={disabled}
            style={{
                ...btnBase,
                background: '#f1f5f9',
                color: '#475569',
                border: '1px solid #e2e8f0',
                cursor: disabled ? 'not-allowed' : 'pointer',
            }}
        >
            {children}
        </button>
    );
}

export function ButtonSuccess({ children, onClick, disabled, type = 'button' }) {
    return (
        <button
            type={type}
            onClick={onClick}
            disabled={disabled}
            style={{
                ...btnBase,
                background: disabled ? '#94a3b8' : '#22c55e',
                color: 'white',
                cursor: disabled ? 'not-allowed' : 'pointer',
            }}
        >
            {children}
        </button>
    );
}

export function ButtonDanger({ children, onClick, disabled, type = 'button' }) {
    return (
        <button
            type={type}
            onClick={onClick}
            disabled={disabled}
            style={{
                ...btnBase,
                background: disabled ? '#94a3b8' : '#ef4444',
                color: 'white',
                cursor: disabled ? 'not-allowed' : 'pointer',
            }}
        >
            {children}
        </button>
    );
}

export function SelectInput({ label, required, children, ...props }) {
    return (
        <FormInput label={label} required={required}>
            <select
                style={{
                    appearance: 'none',
                    backgroundImage: `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8L1 3h10z'/%3E%3C/svg%3E")`,
                    backgroundRepeat: 'no-repeat',
                    backgroundPosition: 'right 12px center',
                    paddingRight: '32px',
                }}
                {...props}
            >
                {children}
            </select>
        </FormInput>
    );
}

export function TextAreaInput({ label, required, hint, children, ...props }) {
    return (
        <FormInput label={label} required={required} hint={hint}>
            <textarea
                rows={3}
                style={{
                    resize: 'vertical',
                    minHeight: '80px',
                }}
                {...props}
            >
                {children}
            </textarea>
        </FormInput>
    );
}

export function CheckboxInput({ label, checked, onChange }) {
    return (
        <label style={{ display: 'flex', alignItems: 'center', cursor: 'pointer', gap: '8px', marginBottom: '8px' }}>
            <input
                type="checkbox"
                checked={checked}
                onChange={onChange}
                style={{ width: '16px', height: '16px', accentColor: '#3b82f6', cursor: 'pointer' }}
            />
            <span style={{ fontSize: '14px', color: '#334155' }}>{label}</span>
        </label>
    );
}
