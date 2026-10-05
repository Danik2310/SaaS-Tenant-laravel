import { cloneElement, isValidElement } from 'react';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';

/**
 * A labelled form row that owns the hint/error ids so the control gets a
 * correct aria-describedby / aria-invalid pairing. The control is passed as
 * `children` and cloned with those attributes, keeping a single source of
 * truth for the ids.
 */
export default function FormField({ label, htmlFor, hint, error, required = false, row = false, children }) {
    const hintId = hint ? `${htmlFor}-hint` : undefined;
    const errorId = error ? `${htmlFor}-error` : undefined;
    const describedBy = [hintId, errorId].filter(Boolean).join(' ') || undefined;

    const control = isValidElement(children)
        ? cloneElement(children, {
              'aria-invalid': error ? 'true' : undefined,
              'aria-describedby': describedBy,
          })
        : children;

    // `!` on the label/error colour because InputLabel and InputError concatenate
    // their own gray/red after ours — same specificity, so without it the winner
    // would depend on Tailwind's output order.
    const labelTone = row ? '!text-white' : '';
    const hintTone = row ? 'text-white/50' : 'text-gray-500';
    const errorTone = row ? '!text-red-300' : '';

    if (row) {
        return (
            <div className="grid gap-x-6 gap-y-1 border-t border-white/10 pt-5 sm:grid-cols-[minmax(0,13rem)_minmax(0,1fr)]">
                <div className="sm:pt-2.5">
                    <div className="flex items-baseline gap-1">
                        <InputLabel htmlFor={htmlFor} value={label} className={labelTone} />

                        {required && (
                            <span aria-hidden="true" className={row ? 'text-white' : 'text-brand-700'}>
                                *
                            </span>
                        )}
                    </div>

                    {hint && (
                        <p id={hintId} className={`mt-1 text-xs leading-relaxed ${hintTone}`}>
                            {hint}
                        </p>
                    )}
                </div>

                <div>
                    {control}

                    <InputError id={errorId} message={error} className={`mt-1 ${errorTone}`} />
                </div>
            </div>
        );
    }

    return (
        <div className="space-y-1.5">
            <div className="flex items-baseline gap-1">
                <InputLabel htmlFor={htmlFor} value={label} />

                {required && (
                    <span aria-hidden="true" className="text-brand-700">
                        *
                    </span>
                )}
            </div>

            {control}

            {hint && (
                <p id={hintId} className="text-xs text-gray-500">
                    {hint}
                </p>
            )}

            <InputError id={errorId} message={error} className="mt-1" />
        </div>
    );
}