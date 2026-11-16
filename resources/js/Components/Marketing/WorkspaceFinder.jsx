import { useForm } from '@inertiajs/react';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';

/**
 * Workspace address lookup used by the marketing hero and the central `/login`
 * screen.
 *
 * `tone` follows the surface it sits on: `dark` in the hero's ink field,
 * `light` inside the white auth card.
 *
 * The address is resolved only on submit. Looking it up while the visitor types
 * would turn the endpoint into a free "which workspaces exist" API.
 */
export default function WorkspaceFinder({
    tone = 'light',
    suffix = 'sasapp',
    autoFocus = false,
    className = '',
}) {
    const dark = tone === 'dark';
    const { data, setData, post, processing, errors } = useForm({ workspace: '' });

    const submit = (event) => {
        event.preventDefault();

        post(route('workspace.lookup'));
    };

    const error = errors.workspace;

    return (
        <form onSubmit={submit} className={className} noValidate>
            <label
                htmlFor="workspace"
                className={`block text-sm font-medium ${dark ? 'text-white/80' : 'text-gray-700'}`}
            >
                Workspace address
            </label>

            <div className="mt-2 flex flex-col gap-2 sm:flex-row">
                <TextInput
                    id="workspace"
                    name="workspace"
                    value={data.workspace}
                    onChange={(event) => setData('workspace', event.target.value)}
                    tone={tone}
                    invalid={Boolean(error)}
                    isFocused={autoFocus}
                    placeholder={`your-shop or your-shop.${suffix}`}
                    autoComplete="off"
                    autoCapitalize="none"
                    spellCheck={false}
                    inputMode="url"
                    aria-invalid={error ? 'true' : 'false'}
                    aria-describedby={error ? 'workspace-error' : 'workspace-hint'}
                    className="block w-full sm:flex-1"
                />
                <PrimaryButton
                    type="submit"
                    variant={dark ? 'ledger' : 'primary'}
                    loading={processing}
                    loadingText="Opening…"
                    className="shrink-0"
                >
                    Go to workspace
                </PrimaryButton>
            </div>

            {error ? (
                <InputError
                    id="workspace-error"
                    role="alert"
                    message={error}
                    className={`mt-2 ${dark ? 'text-red-300' : ''}`}
                />
            ) : (
                <p
                    id="workspace-hint"
                    className={`mt-2 text-xs ${dark ? 'text-white/50' : 'text-gray-500'}`}
                >
                    Enter a workspace name, or paste the address from your welcome email.
                </p>
            )}
        </form>
    );
}
