import { useEffect, useMemo, useState } from 'react';
import GuestLayout from '@/Layouts/GuestLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';

function toSlug(value) {
    return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

const LIMIT_ROWS = [
    { key: 'users', label: 'Users' },
    { key: 'warehouses', label: 'Warehouses' },
    { key: 'products', label: 'Products' },
    { key: 'categories', label: 'Categories' },
    { key: 'storage', label: 'Storage (MB)' },
];

function formatPrice(plan) {
    const amount = Number(plan.price);
    const formatted = new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: plan.currency || 'USD',
    }).format(amount);

    if (amount === 0) {
        return 'Free';
    }

    if (!plan.duration_months) {
        return formatted;
    }

    if (plan.duration_months === 1) {
        return `${formatted}/mo`;
    }

    if (plan.duration_months === 12) {
        return `${formatted}/yr`;
    }

    return `${formatted}/${plan.duration_months} mo`;
}

function limitValue(value) {
    return value === null || value === undefined ? 'Unlimited' : String(value);
}

function PlansTable({ plans, featureDefinitions, onSelect, processing }) {
    return (
        <div>
            <h2 className="text-xl font-semibold text-gray-900">Choose your plan</h2>
            <p className="mt-1 text-sm text-gray-600">
                Pick a plan to start your workspace. No credit card required.
            </p>

            <div className="mt-4 overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr className="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                            <th scope="col" className="py-2 pr-3">Plan</th>
                            <th scope="col" className="py-2 pr-3">Price</th>
                            <th scope="col" className="py-2 pr-3">Features</th>
                            <th scope="col" className="py-2 pr-3">Limits</th>
                            <th scope="col" className="py-2 text-right">
                                <span className="sr-only">Action</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {plans.map((plan) => (
                            <tr key={plan.slug} data-testid={`plan-row-${plan.slug}`}>
                                <td className="py-3 pr-3 align-top">
                                    <div className="font-medium text-gray-900">{plan.name}</div>
                                    {plan.slug === 'trial' && (
                                        <span className="mt-1 inline-block rounded bg-indigo-50 px-1.5 py-0.5 text-xs font-medium text-indigo-700">
                                            14-day free trial
                                        </span>
                                    )}
                                </td>
                                <td className="py-3 pr-3 align-top whitespace-nowrap font-medium text-gray-900">
                                    {formatPrice(plan)}
                                </td>
                                <td className="py-3 pr-3 align-top">
                                    {plan.features.length === 0 ? (
                                        <span className="text-gray-400">—</span>
                                    ) : (
                                        <ul className="space-y-0.5">
                                            {plan.features.map((key) => (
                                                <li key={key} className="text-gray-600">
                                                    {featureDefinitions?.[key]?.label ?? key}
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </td>
                                <td className="py-3 pr-3 align-top">
                                    <dl className="space-y-0.5">
                                        {LIMIT_ROWS.map((row) => (
                                            <div key={row.key} className="flex gap-2">
                                                <dt className="text-gray-500">{row.label}:</dt>
                                                <dd className="text-gray-700">
                                                    {limitValue(plan.limits?.[row.key])}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                </td>
                                <td className="py-3 align-top text-right">
                                    <button
                                        type="button"
                                        onClick={() => onSelect(plan.slug)}
                                        disabled={processing}
                                        className="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50"
                                    >
                                        {plan.can_signup ? 'Sign Up' : 'Buy'}
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <p className="mt-4 text-xs text-gray-500">
                Paid plans are billed separately. Signing up starts a free trial today.
            </p>
        </div>
    );
}

export default function TenantRegister({ plans = [], selected_plan = null, tenant_domain_suffix, feature_definitions = {} }) {
    const [showForm, setShowForm] = useState(Boolean(selected_plan));

    const { data, setData, post, processing, errors, reset } = useForm({
        company_name: '',
        name: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
        terms: false,
        website: '',
        plan: selected_plan ?? '',
    });

    useEffect(() => {
        return () => {
            reset('password', 'password_confirmation');
        };
    }, []);

    const slug = useMemo(() => toSlug(data.company_name), [data.company_name]);

    const selectedPlan = useMemo(
        () => plans.find((plan) => plan.slug === data.plan) ?? null,
        [plans, data.plan]
    );

    const submit = (e) => {
        e.preventDefault();

        post(route('register.tenant'));
    };

    const handleSelect = (planSlug) => {
        setData('plan', planSlug);
        setShowForm(true);
    };

    return (
        <GuestLayout>
            <Head title={showForm ? 'Create your workspace' : 'Choose your plan'} />

            {!showForm ? (
                <PlansTable
                    plans={plans}
                    featureDefinitions={feature_definitions}
                    onSelect={handleSelect}
                    processing={processing}
                />
            ) : (
                <form onSubmit={submit}>
                    <div className="hidden" aria-hidden="true">
                        <input
                            tabIndex="-1"
                            autoComplete="off"
                            type="text"
                            name="website"
                            value={data.website}
                            onChange={(e) => setData('website', e.target.value)}
                        />
                    </div>

                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <h2 className="text-xl font-semibold text-gray-900">Create your workspace</h2>
                            <p className="mt-1 text-sm text-gray-600">
                                {selectedPlan
                                    ? `Plan: ${selectedPlan.name} — ${
                                          selectedPlan.can_signup
                                              ? selectedPlan.slug === 'trial'
                                                  ? '14-day free trial, no credit card required.'
                                                  : 'free to start.'
                                          : 'free trial first, billed later.'
                                      }`
                                    : 'Start your 14-day free trial. No credit card required.'}
                            </p>
                        </div>
                        {plans.length > 0 && (
                            <button
                                type="button"
                                onClick={() => setShowForm(false)}
                                className="shrink-0 text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 rounded-md"
                            >
                                ← Plans
                            </button>
                        )}
                    </div>

                    <div className="mt-4">
                        <InputLabel htmlFor="company_name" value="Company name" />

                        <TextInput
                            id="company_name"
                            name="company_name"
                            value={data.company_name}
                            className="mt-1 block w-full"
                            autoComplete="organization"
                            isFocused={true}
                            onChange={(e) => setData('company_name', e.target.value)}
                            required
                        />

                        <InputError message={errors.company_name} className="mt-2" />
                    </div>

                    {slug && (
                        <p className="mt-1 text-xs text-gray-500">
                            Your workspace address will be{' '}
                            <span className="font-medium text-gray-700">
                                {slug}.{tenant_domain_suffix}
                            </span>
                        </p>
                    )}

                    <div className="mt-4">
                        <InputLabel htmlFor="name" value="Full name" />

                        <TextInput
                            id="name"
                            name="name"
                            value={data.name}
                            className="mt-1 block w-full"
                            autoComplete="name"
                            onChange={(e) => setData('name', e.target.value)}
                            required
                        />

                        <InputError message={errors.name} className="mt-2" />
                    </div>

                    <div className="mt-4">
                        <InputLabel htmlFor="email" value="Work email" />

                        <TextInput
                            id="email"
                            type="email"
                            name="email"
                            value={data.email}
                            className="mt-1 block w-full"
                            autoComplete="username"
                            onChange={(e) => setData('email', e.target.value)}
                            required
                        />

                        <InputError message={errors.email} className="mt-2" />
                    </div>

                    <div className="mt-4">
                        <InputLabel htmlFor="phone" value="Phone (optional)" />

                        <TextInput
                            id="phone"
                            type="tel"
                            name="phone"
                            value={data.phone}
                            className="mt-1 block w-full"
                            autoComplete="tel"
                            onChange={(e) => setData('phone', e.target.value)}
                        />

                        <InputError message={errors.phone} className="mt-2" />
                    </div>

                    <div className="mt-4">
                        <InputLabel htmlFor="password" value="Password" />

                        <TextInput
                            id="password"
                            type="password"
                            name="password"
                            value={data.password}
                            className="mt-1 block w-full"
                            autoComplete="new-password"
                            onChange={(e) => setData('password', e.target.value)}
                            required
                        />

                        <InputError message={errors.password} className="mt-2" />
                    </div>

                    <div className="mt-4">
                        <InputLabel htmlFor="password_confirmation" value="Confirm Password" />

                        <TextInput
                            id="password_confirmation"
                            type="password"
                            name="password_confirmation"
                            value={data.password_confirmation}
                            className="mt-1 block w-full"
                            autoComplete="new-password"
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            required
                        />

                        <InputError message={errors.password_confirmation} className="mt-2" />
                    </div>

                    <div className="mt-4">
                        <label htmlFor="terms" className="flex items-start text-sm text-gray-600">
                            <input
                                id="terms"
                                type="checkbox"
                                name="terms"
                                checked={data.terms}
                                onChange={(e) => setData('terms', e.target.checked)}
                                className="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                required
                            />
                            <span className="ms-2">
                                I agree to the terms of service and privacy policy.
                            </span>
                        </label>

                        <InputError message={errors.terms} className="mt-2" />
                    </div>

                    <InputError message={errors.plan} className="mt-2" />
                    <InputError message={errors.provisioning} className="mt-4" />

                    <div className="flex items-center justify-end mt-4">
                        <Link
                            href={route('central.login')}
                            className="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        >
                            Admin? Log in
                        </Link>

                        <PrimaryButton className="ms-4" disabled={processing}>
                            Create workspace
                        </PrimaryButton>
                    </div>
                </form>
            )}
        </GuestLayout>
    );
}
