import { useCallback, useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import api from '@/services/api';
import { CURRENCIES, FALLBACK_RATES } from './currencies';

const DEFAULT_STATE = {
    ready: false,
    base: 'USD',
    displayCurrency: 'USD',
    rates: FALLBACK_RATES,
    updatedAt: null,
    isLive: false,
};

let cache = null;
let inFlight = null;

async function fetchMoneyInfo() {
    try {
        const res = await api.get('/admin/api/exchange-rates', { bypass403Redirect: true });
        const data = res.data || {};

        return {
            ready: true,
            base: data.base || 'USD',
            displayCurrency: data.display_currency || data.base || 'USD',
            publicCurrency: data.public_currency || data.base || 'USD',
            tenantCurrencies: data.tenant_currencies || [],
            rates: data.rates || FALLBACK_RATES,
            updatedAt: data.updated_at || null,
            isLive: Boolean(data.is_live),
        };
    } catch {
        return { ...DEFAULT_STATE, ready: true };
    }
}

export function loadMoneyInfo() {
    if (cache) return Promise.resolve(cache);
    if (!inFlight) {
        inFlight = fetchMoneyInfo().then((info) => {
            cache = info;
            return info;
        });
    }
    return inFlight;
}

export function resetMoneyCache() {
    cache = null;
    inFlight = null;
}

function rateFor(currency, info = {}) {
    const base = info.base || DEFAULT_STATE.base;
    const code = currency || info.displayCurrency || base;
    const rates = info.rates || DEFAULT_STATE.rates;

    return code === base ? 1 : (rates[code] ?? 1);
}

function decimalsFor(currency) {
    const entry = CURRENCIES.find((item) => item.code === currency);
    if (entry && Number.isInteger(entry.decimal_places)) return entry.decimal_places;

    try {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency }).resolvedOptions().maximumFractionDigits;
    } catch {
        return 2;
    }
}

export function roundMoney(value, currency) {
    const factor = 10 ** decimalsFor(currency);
    return Math.round((Number(value) + Number.EPSILON) * factor) / factor;
}

export function formatMoneyValue(value, info = {}) {
    const amount = Number.parseFloat(value);
    if (Number.isNaN(amount)) return '\u2014';

    const base = info.base || DEFAULT_STATE.base;
    const currency = info.displayCurrency || info.currency || base;
    const converted = amount * rateFor(currency, info);

    try {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency,
            minimumFractionDigits: decimalsFor(currency),
            maximumFractionDigits: decimalsFor(currency),
        }).format(converted);
    } catch {
        return `${currency} ${converted.toFixed(decimalsFor(currency))}`;
    }
}

export function convertFromBase(value, info = {}) {
    const amount = Number.parseFloat(value);
    if (Number.isNaN(amount)) return NaN;

    const base = info.base || DEFAULT_STATE.base;
    const currency = info.displayCurrency || base;

    return roundMoney(amount * rateFor(currency, info), currency);
}

export function convertToBase(value, info = {}) {
    const amount = Number.parseFloat(value);
    if (Number.isNaN(amount)) return NaN;

    const base = info.base || DEFAULT_STATE.base;
    const currency = info.displayCurrency || base;
    const rate = rateFor(currency, info);

    return roundMoney(rate > 0 ? amount / rate : amount, base);
}

export function currencySymbol(code) {
    const entry = CURRENCIES.find((item) => item.code === code);
    return entry ? entry.symbol : code;
}

/**
 * `surface` is one of `admin`, `public` or `tenant`. Public and tenant pages
 * use the currency selected by the administrator; only the admin console loads
 * the rate endpoint (which is authenticated).
 */
function useInertiaProps() {
    try {
        return usePage().props || {};
    } catch {
        // Components rendered outside the Inertia app (tests, Storybook, SSR
        // fallbacks) get the base defaults rather than crashing.
        return {};
    }
}

export function useMoney(surface = 'admin') {
    const shared = useInertiaProps().currency || {};
    const base = shared.base || DEFAULT_STATE.base;
    const adminCurrency = shared.admin || base;
    const publicCurrency = shared.public || base;
    const tenantCurrency = shared.tenant || base;
    const [state, setState] = useState(DEFAULT_STATE);

    useEffect(() => {
        let active = true;

        if (surface === 'admin') {
            loadMoneyInfo().then((info) => {
                if (!active) return;

                setState({
                    ...info,
                    base: info.base || base,
                    displayCurrency: info.displayCurrency || adminCurrency,
                });
            });
        } else {
            setState({
                ready: true,
                base,
                displayCurrency: surface === 'public' ? publicCurrency : tenantCurrency,
                rates: shared.rates || FALLBACK_RATES,
                updatedAt: null,
                isLive: false,
            });
        }

        return () => {
            active = false;
        };
    }, [surface, base, adminCurrency, publicCurrency, tenantCurrency, shared.rates]);

    const formatMoney = useCallback((value) => formatMoneyValue(value, state), [state]);
    const toBase = useCallback((value) => convertToBase(value, state), [state]);
    const fromBase = useCallback((value) => convertFromBase(value, state), [state]);

    return { ...state, formatMoney, convertToBase: toBase, convertFromBase: fromBase };
}
