import { useEffect, useRef, useState } from 'react';

export function usePrefersReducedMotion() {
    const [reduced, setReduced] = useState(() =>
        typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches ? true : false
    );

    useEffect(() => {
        const mq = window.matchMedia('(prefers-reduced-motion: reduce)');
        const onChange = (event) => setReduced(event.matches);
        mq.addEventListener('change', onChange);

        return () => mq.removeEventListener('change', onChange);
    }, []);

    return reduced;
}

export default function Reveal({ children, className = '', delay = 0, as: Tag = 'div' }) {
    const ref = useRef(null);
    const reduced = usePrefersReducedMotion();

    useEffect(() => {
        const el = ref.current;

        if (!el || reduced) {
            return undefined;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        el.classList.add('is-visible');
                        observer.disconnect();
                    }
                });
            },
            { threshold: 0.15, rootMargin: '0px 0px -48px 0px' }
        );

        observer.observe(el);

        return () => observer.disconnect();
    }, [reduced]);

    if (reduced) {
        return <Tag className={className}>{children}</Tag>;
    }

    return (
        <Tag
            ref={ref}
            className={`reveal ${className}`}
            style={delay ? { transitionDelay: `${delay}ms` } : undefined}
        >
            {children}
        </Tag>
    );
}
