import { useState, useEffect, useCallback } from 'react';
import api from '@/services/api';
import { FALLBACK_RATES } from './currencies';

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
        const res = await api.get('/admin/api/exchange-rates');
        const data = res.data || {};
        return {
            ready: true,
            base: data.base || 'USD',
            displayCurrency: data.display_currency || data.base || 'USD',
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

export function formatMoneyValue(value, info = {}) {
    const amount = Number.parseFloat(value);
    if (Number.isNaN(amount)) return '\u2014';

    const base = info.base || DEFAULT_STATE.base;
    const currency = info.displayCurrency || base;
    const rates = info.rates || DEFAULT_STATE.rates;
    const rate = rates[currency] ?? 1;
    const converted = amount * rate;

    try {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(converted);
    } catch {
        return `${currency} ${converted.toFixed(2)}`;
    }
}

export function useMoney() {
    const [state, setState] = useState(DEFAULT_STATE);

    useEffect(() => {
        let active = true;
        loadMoneyInfo().then((info) => {
            if (active) setState(info);
        });
        return () => {
            active = false;
        };
    }, []);

    const formatMoney = useCallback((value) => formatMoneyValue(value, state), [state]);

    return { ...state, formatMoney };
}
