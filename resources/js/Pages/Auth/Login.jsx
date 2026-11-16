import { useEffect } from 'react';
import Checkbox from '@/Components/Checkbox';
import GoogleButton from '@/Components/Auth/GoogleButton';
import GuestLayout from '@/Layouts/GuestLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Login({ status, canResetPassword, providers = [], oauthError = null, signupUrl = null }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    useEffect(() => {
        return () => {
            reset('password');
        };
    }, []);

    const submit = (e) => {
        e.preventDefault();

        post(route('login'));
    };

    return (
        <GuestLayout>
            <Head title="Log in" />

            <div>
                <h1 className="font-display text-2xl leading-tight tracking-tight text-ink">Welcome back</h1>
                <p className="mt-1 text-sm text-gray-600">Log in to your ShoppingLi workspace.</p>
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

            {oauthError && (
                <div role="alert" className="mt-4 text-sm text-red-600">
                    {oauthError}
                </div>
            )}

            <form onSubmit={submit} className="mt-6">
                <div>
                    <InputLabel htmlFor="email" value="Email" />

                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block w-full"
                        autoComplete="username"
                        isFocused={true}
                        onChange={(e) => setData('email', e.target.value)}
                    />

                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="password" value="Password" />

                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full"
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="block mt-4">
                    <label className="flex items-center">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) => setData('remember', e.target.checked)}
                        />
                        <span className="ms-2 text-sm text-gray-600">Remember me</span>
                    </label>
                </div>

                <div className="flex items-center justify-end mt-4">
                    <PrimaryButton disabled={processing}>Log in</PrimaryButton>
                </div>
            </form>

            <GoogleButton providers={providers} label="Sign in with Google" divider="before" />

            {canResetPassword && (
                <p className="mt-6 text-center text-sm text-gray-600">
                    Don&apos;t remember your password?{' '}
                    <Link
                        href={route('password.request')}
                        className="font-medium text-brand-600 underline hover:text-brand-700 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500"
                    >
                        Forgot your password?
                    </Link>
                </p>
            )}

            {signupUrl && (
                <p className="mt-8 border-t border-gray-200 pt-6 text-center text-sm text-gray-600">
                    Don&apos;t have an account?{' '}
                    <a
                        href={signupUrl}
                        data-testid="signup-link"
                        className="font-medium text-brand-600 underline underline-offset-4 hover:text-brand-700 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500"
                    >
                        Sign up using your email
                        {providers.length > 0 ? ' or Google account' : ''}
                    </a>
                </p>
            )}
        </GuestLayout>
    );
}
