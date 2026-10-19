import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Scan glob patterns for pathological brace expansion before they reach the
 * glob engine.
 *
 * The glob engine (fast-glob → micromatch → braces@3.0.3) is vulnerable to a
 * stack-exhaustion denial of service: a pattern with many brace groups
 * (`{a,b}{a,b}{a,b}…`, whether nested *or* sequential) causes exponential
 * backtracking while parsing. See docs/security/known-advisories.md (JS-0001).
 *
 * This toolchain only ever runs at build time with these author-written
 * patterns, so there is no untrusted input to sanitize — the guard is
 * defense-in-depth: it fails the build loudly if a future contributor
 * accidentally writes a pattern with enough expansion to hang it. Braces used
 * in the wild (Tailwind's own defaults, e.g. `*.{js,jsx,ts,tsx}`) sit far
 * below the limits.
 *
 * @param {string[]} patterns
 */
function assertGlobsAreSane(patterns) {
    const MAX_BRACE_DEPTH = 8;
    const MAX_BRACE_GROUPS = 8;

    for (const pattern of patterns) {
        let depth = 0;
        let groups = 0;

        for (const char of pattern) {
            if (char === '{') {
                depth += 1;
                groups += 1;

                if (depth > MAX_BRACE_DEPTH) {
                    throw new Error(
                        `Refusing to load Tailwind: content pattern "${pattern}" nests braces ` +
                            `more than ${MAX_BRACE_DEPTH} levels deep. This can exhaust the ` +
                            'stack in the glob engine (GHSA-vfj7-8cjw-p6xm). ' +
                            'See docs/security/known-advisories.md.'
                    );
                }

                if (groups > MAX_BRACE_GROUPS) {
                    throw new Error(
                        `Refusing to load Tailwind: content pattern "${pattern}" contains more ` +
                            `than ${MAX_BRACE_GROUPS} brace groups. This can exhaust the stack ` +
                            'in the glob engine (GHSA-vfj7-8cjw-p6xm). ' +
                            'See docs/security/known-advisories.md.'
                    );
                }
            } else if (char === '}') {
                depth = Math.max(0, depth - 1);
            }
        }
    }
}

const content = [
    './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
    './storage/framework/views/*.php',
    './resources/views/**/*.blade.php',
    './resources/js/**/*.jsx',
    './resources/js/**/*.js',
];

assertGlobsAreSane(content);

/** @type {import('tailwindcss').Config} */
export default {
    content,

    theme: {
        extend: {
            colors: {
                brand: {
                    50: '#FFF7ED',
                    100: '#FFEDD5',
                    200: '#FED7AA',
                    300: '#FDBA74',
                    400: '#FB923C',
                    500: '#F97316',
                    600: '#EA580C',
                    700: '#C2410C',
                    800: '#9A3412',
                    900: '#7C2D12',
                },
                ink: {
                    DEFAULT: '#0A0A0A',
                    soft: '#171717',
                },
            },
            fontFamily: {
                sans: ['DM Sans', ...defaultTheme.fontFamily.sans],
                display: ['Archivo Black', 'DM Sans', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
