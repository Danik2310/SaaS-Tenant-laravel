import { useEffect, useMemo, useRef, useState } from 'react';
import usePrefersReducedMotion from './usePrefersReducedMotion';
import { rowMotionTimings } from '@/Components/tableMotion';

const rowKey = (row, getRowId, index) => {
    const key = getRowId(row);
    return key === undefined || key === null ? `index:${index}` : String(key);
};

const rowSignature = (row) => {
    try {
        return JSON.stringify(row);
    } catch {
        return String(row);
    }
};

export default function useAnimatedTableRows(rows, getRowId, pendingIds = []) {
    const prefersReducedMotion = usePrefersReducedMotion();
    const getRowIdRef = useRef(getRowId);
    getRowIdRef.current = getRowId;

    const [displayRows, setDisplayRows] = useState(rows);
    const [phases, setPhases] = useState({});
    const [delays, setDelays] = useState({});

    const rowsRef = useRef(rows);
    const phasesRef = useRef({});
    const timersRef = useRef(new Map());
    const mountedRef = useRef(true);

    const keyFor = (row, index = 0) => rowKey(row, getRowIdRef.current, index);

    const clearTimer = (key) => {
        const timer = timersRef.current.get(key);
        if (timer) {
            clearTimeout(timer);
            timersRef.current.delete(key);
        }
    };

    const commitPhases = (next) => {
        phasesRef.current = next;
        setPhases(next);
    };

    useEffect(() => () => {
        mountedRef.current = false;
        timersRef.current.forEach((timer) => clearTimeout(timer));
        timersRef.current.clear();
    }, []);

    useEffect(() => {
        mountedRef.current = true;

        const incomingRows = Array.isArray(rows) ? rows : [];
        const currentRows = rowsRef.current || [];
        const currentPhases = phasesRef.current;
        const incomingById = new Map(incomingRows.map((row, index) => [keyFor(row, index), { row, signature: rowSignature(row) }]));
        const currentKeys = new Set(currentRows.map((row, index) => keyFor(row, index)));

        const nextPhases = {};
        const nextDelays = {};
        const merged = [];
        let enteringCount = 0;

        const schedule = (key, phase, duration, delay = 0) => {
            clearTimer(key);
            const effectiveDelay = prefersReducedMotion
                ? Math.min(delay, rowMotionTimings.staggerCap)
                : delay;
            const timer = setTimeout(() => {
                timersRef.current.delete(key);
                if (!mountedRef.current) return;

                if (phase === 'exiting') {
                    const remaining = rowsRef.current.filter((row) => keyFor(row) !== key);
                    rowsRef.current = remaining;
                    setDisplayRows(remaining);
                }

                const remainingPhases = { ...phasesRef.current };
                delete remainingPhases[key];
                commitPhases(remainingPhases);

                setDelays((current) => {
                    const next = { ...current };
                    delete next[key];
                    return next;
                });
            }, effectiveDelay + (prefersReducedMotion ? rowMotionTimings.reduced : duration));
            timersRef.current.set(key, timer);
        };

        currentRows.forEach((row, index) => {
            const key = keyFor(row, index);
            const incoming = incomingById.get(key);

            if (!incoming) {
                merged.push(row);
                nextPhases[key] = 'exiting';
                if (!timersRef.current.has(key)) {
                    schedule(key, 'exiting', rowMotionTimings.exit);
                }
                return;
            }

            merged.push(incoming.row);

            if (currentPhases[key] === 'exiting') {
                nextPhases[key] = 'entering';
                schedule(key, 'entering', rowMotionTimings.enter);
            } else if (incoming.signature !== rowSignature(row)) {
                nextPhases[key] = 'updating';
                schedule(key, 'updating', rowMotionTimings.update);
            }
        });

        incomingRows.forEach((row, index) => {
            const key = keyFor(row, index);
            if (currentKeys.has(key)) return;

            const delay = Math.min(enteringCount * rowMotionTimings.staggerStep, rowMotionTimings.staggerCap);
            enteringCount += 1;
            merged.push(row);
            nextPhases[key] = 'entering';
            nextDelays[key] = delay;
            schedule(key, 'entering', rowMotionTimings.enter, delay);
        });

        timersRef.current.forEach((_, key) => {
            if (!(key in nextPhases)) clearTimer(key);
        });

        rowsRef.current = merged;
        setDisplayRows(merged);
        commitPhases(nextPhases);
        setDelays(nextDelays);
    }, [rows, prefersReducedMotion]);

    const visiblePhases = useMemo(() => {
        const next = { ...phases };
        (pendingIds || []).forEach((id) => {
            const key = String(id);
            if (next[key] !== 'exiting') next[key] = 'pending';
        });
        return next;
    }, [phases, pendingIds]);

    return { rows: displayRows, phases: visiblePhases, delays };
}
