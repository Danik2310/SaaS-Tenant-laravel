import '@testing-library/jest-dom/vitest';
import { afterEach } from 'vitest';
import { cleanup } from '@testing-library/react';

// jsdom has no IntersectionObserver, so any component that reveals itself on
// scroll (`resources/js/Components/Marketing/Reveal.jsx`) throws while mounting.
// The stub reports every observed element as already visible, which is what a
// test wants: the content is in the DOM and no scroll simulation is required.
if (!('IntersectionObserver' in window)) {
    class IntersectionObserverStub {
        constructor(callback) {
            this.callback = callback;
        }

        observe(element) {
            this.callback([{ isIntersecting: true, target: element }], this);
        }

        unobserve() {}

        disconnect() {}

        takeRecords() {
            return [];
        }
    }

    window.IntersectionObserver = IntersectionObserverStub;
}

// jsdom also exposes no matchMedia, and `Reveal` reads
// prefers-reduced-motion while mounting to decide whether to animate at all.
// `matches: false` means "motion is fine", i.e. the default rendering path.
if (typeof window.matchMedia !== 'function') {
    window.matchMedia = (query) => ({
        matches: false,
        media: query,
        onchange: null,
        addEventListener() {},
        removeEventListener() {},
        addListener() {},
        removeListener() {},
        dispatchEvent: () => false,
    });
}

// Clean up after each test
afterEach(() => {
    cleanup();
});