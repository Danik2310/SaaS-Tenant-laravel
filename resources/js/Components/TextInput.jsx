import { forwardRef, useEffect, useRef } from 'react';

export default forwardRef(function TextInput(
    { type = 'text', className = '', isFocused = false, invalid = false, ...props },
    ref
) {
    const input = ref ? ref : useRef();

    useEffect(() => {
        if (isFocused) {
            input.current.focus();
        }
    }, []);

    const state = invalid
        ? 'border-red-500 focus:border-red-600 focus:ring-red-500'
        : 'border-gray-200 focus:border-brand-500 focus:ring-brand-500';

    return (
        <input
            {...props}
            type={type}
            className={'rounded-xl px-3.5 py-2.5 shadow-sm ring-1 ring-inset ' + state + ' ' + className}
            ref={input}
        />
    );
});
