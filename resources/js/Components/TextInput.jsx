import { forwardRef, useEffect, useRef } from 'react';

export default forwardRef(function TextInput({ type = 'text', className = '', isFocused = false, ...props }, ref) {
    const input = ref ? ref : useRef();

    useEffect(() => {
        if (isFocused) {
            input.current.focus();
        }
    }, []);

    return (
        <input
            {...props}
            type={type}
            className={
                'border-gray-200 focus:border-brand-500 focus:ring-brand-500 rounded-xl shadow-sm px-3.5 py-2.5 ' +
                className
            }
            ref={input}
        />
    );
});