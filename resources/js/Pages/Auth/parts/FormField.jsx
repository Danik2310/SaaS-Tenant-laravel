import { cloneElement, isValidElement } from 'react';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';

/**
 * A labelled form row that owns the hint/error ids so the control gets a
 * correct aria-describedby / aria-invalid pairing. The control is passed as
 * `children` and cloned with those attributes, keeping a single source of
 * truth for the ids.
 */
export default function FormField({ label, htmlFor, hint, error, required = false, children }) {
    const hintId = hint ? `${htmlFor}-hint` : undefined;
    const errorId = error ? `${htmlFor}-error` : undefined;
    const describedBy = [hintId, errorId].filter(Boolean).join(' ') || undefined;

    const control = isValidElement(children)
        ? cloneElement(children, {
              'aria-invalid': error ? 'true' : undefined,
              'aria-describedby': describedBy,
          })
        : children;

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