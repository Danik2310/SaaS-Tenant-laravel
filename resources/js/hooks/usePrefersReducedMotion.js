import { useEffect, useState } from 'react';

export default function usePrefersReducedMotion() {
    const [prefersReduced, setPrefersReduced] = useState(false);

    useEffect(() => {
        if (typeof window.matchMedia !== 'function') {
            return undefined;
        }

        const query = window.matchMedia('(prefers-reduced-motion: reduce)');

        const sync = () => setPrefersReduced(query.matches);

        sync();

        if (typeof query.addEventListener === 'function') {
            query.addEventListener('change', sync);

            return () => query.removeEventListener('change', sync);
        }

        query.addListener(sync);

        return () => query.removeListener(sync);
    }, []);

    return prefersReduced;
}
