import GuestLayout from '@/Layouts/GuestLayout';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title="Forgot Password" />

            <div className="flex items-start justify-between gap-4">
                <div>
                    <h1 className="font-display text-2xl leading-tight tracking-tight text-ink">
                        Forgot your password?
                    </h1>
                    <p className="mt-1 text-sm text-gray-600">
                        No problem. Just let us know your email address and we will email you a
                        password reset link that will allow you to choose a new one.
                    </p>
                </div>

                <Link
                    href={route('login')}
                    className="mt-1 shrink-0 rounded-md text-sm font-medium text-brand-600 underline underline-offset-4 hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500"
                >
                    Back to log in
                </Link>
            </div>

            {status && (
                <div
                    role="status"
                    data-testid="flash-status"
                    className="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700"
                >
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="mt-6">
                <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-1">
                    Email
                </label>
                <TextInput
                    id="email"
                    type="email"
                    name="email"
                    value={data.email}
                    className="mt-1 block w-full"
                    isFocused={true}
                    onChange={(e) => setData('email', e.target.value)}
                />

                <InputError message={errors.email} className="mt-2" />

                <div className="mt-6">
                    <PrimaryButton className="w-full" disabled={processing}>
                        Email Password Reset Link
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
