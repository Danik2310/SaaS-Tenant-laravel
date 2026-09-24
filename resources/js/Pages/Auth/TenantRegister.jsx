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

const FIELD_HINTS = {
    company_name: 'Legal or trading name of the company',
    phone: 'Primary contact phone number',
    first_name: "Primary contact's first name",
    last_name: "Primary contact's last name",
    address_line1: 'Street address (e.g., 123 Main St)',
    address_line2: 'Apartment, suite, unit, etc. (optional)',
    city: 'City or locality',
    state: 'State, province, or region',
    postal_code: 'ZIP / postal code',
    country: 'Country of the registered address',
};

function Field({ label, htmlFor, hint, children }) {
    return (
        <div className="mt-4">
            <InputLabel htmlFor={htmlFor} value={label} />

            {children}

            {hint && <p className="mt-1 text-xs text-gray-500">{hint}</p>}
        </div>
    );
}

function SectionHeading() {
    return (
        <div className="mt-6 rounded-md bg-gray-50 px-4 py-3 border border-gray-200">
            <p className="text-sm font-semibold text-gray-700">Business & Contact Information</p>
            <p className="text-xs text-gray-500">
                Optional details about the tenant company and primary contact.
            </p>
        </div>
    );
}

export default function TenantRegister({ selected_plan = null, tenant_domain_suffix }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        company_name: '',
        name: '',
        email: '',
        phone: '',
        first_name: '',
        last_name: '',
        address_line1: '',
        address_line2: '',
        city: '',
        state: '',
        postal_code: '',
        country: '',
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

    const submit = (e) => {
        e.preventDefault();

        post(route('register.tenant'));
    };

    return (
        <GuestLayout wide>
            <Head title="Create your workspace" />

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

                <div>
                    <h2 className="text-xl font-semibold text-gray-900">Create New Tenant</h2>
                    <p className="mt-1 text-sm text-gray-600">
                        Add a new tenant to the platform. Start your 14-day free trial. No credit
                        card required.
                    </p>
                </div>

                <Field label="Tenant Name" htmlFor="name">
                    <TextInput
                        id="name"
                        name="name"
                        value={data.name}
                        className="mt-1 block w-full"
                        autoComplete="organization"
                        isFocused={true}
                        onChange={(e) => setData('name', e.target.value)}
                        placeholder="e.g., Acme Corp"
                        required
                    />
                    <InputError message={errors.name} className="mt-2" />
                </Field>

                <Field label="Email" htmlFor="email" hint="Primary contact email for this tenant">
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block w-full"
                        autoComplete="username"
                        onChange={(e) => setData('email', e.target.value)}
                        placeholder="admin@tenant.com"
                        required
                    />
                    <InputError message={errors.email} className="mt-2" />
                </Field>

                <SectionHeading />

                <Field label="Company Name" htmlFor="company_name" hint={FIELD_HINTS.company_name}>
                    <TextInput
                        id="company_name"
                        name="company_name"
                        value={data.company_name}
                        className="mt-1 block w-full"
                        autoComplete="organization"
                        onChange={(e) => setData('company_name', e.target.value)}
                        placeholder="e.g., Acme Corp LLC"
                        required
                    />
                    <InputError message={errors.company_name} className="mt-2" />

                    {slug && (
                        <p className="mt-1 text-xs text-gray-500">
                            Your workspace address will be{' '}
                            <span className="font-medium text-gray-700">
                                {slug}.{tenant_domain_suffix}
                            </span>
                        </p>
                    )}
                </Field>

                <div className="grid gap-x-4 sm:grid-cols-2">
                    <Field label="First Name" htmlFor="first_name" hint={FIELD_HINTS.first_name}>
                        <TextInput
                            id="first_name"
                            name="first_name"
                            value={data.first_name}
                            className="mt-1 block w-full"
                            autoComplete="given-name"
                            onChange={(e) => setData('first_name', e.target.value)}
                            placeholder="Jane"
                        />
                        <InputError message={errors.first_name} className="mt-2" />
                    </Field>

                    <Field label="Last Name" htmlFor="last_name" hint={FIELD_HINTS.last_name}>
                        <TextInput
                            id="last_name"
                            name="last_name"
                            value={data.last_name}
                            className="mt-1 block w-full"
                            autoComplete="family-name"
                            onChange={(e) => setData('last_name', e.target.value)}
                            placeholder="Doe"
                        />
                        <InputError message={errors.last_name} className="mt-2" />
                    </Field>
                </div>

                <Field label="Phone (optional)" htmlFor="phone" hint={FIELD_HINTS.phone}>
                    <TextInput
                        id="phone"
                        type="tel"
                        name="phone"
                        value={data.phone}
                        className="mt-1 block w-full"
                        autoComplete="tel"
                        onChange={(e) => setData('phone', e.target.value)}
                        placeholder="+1 555 123 4567"
                    />
                    <InputError message={errors.phone} className="mt-2" />
                </Field>

                <Field label="Address Line 1" htmlFor="address_line1" hint={FIELD_HINTS.address_line1}>
                    <TextInput
                        id="address_line1"
                        name="address_line1"
                        value={data.address_line1}
                        className="mt-1 block w-full"
                        autoComplete="address-line1"
                        onChange={(e) => setData('address_line1', e.target.value)}
                        placeholder="123 Main St"
                    />
                    <InputError message={errors.address_line1} className="mt-2" />
                </Field>

                <Field label="Address Line 2" htmlFor="address_line2" hint={FIELD_HINTS.address_line2}>
                    <TextInput
                        id="address_line2"
                        name="address_line2"
                        value={data.address_line2}
                        className="mt-1 block w-full"
                        autoComplete="address-line2"
                        onChange={(e) => setData('address_line2', e.target.value)}
                        placeholder="Suite 400"
                    />
                    <InputError message={errors.address_line2} className="mt-2" />
                </Field>

                <div className="grid gap-x-4 sm:grid-cols-3">
                    <Field label="City" htmlFor="city" hint={FIELD_HINTS.city}>
                        <TextInput
                            id="city"
                            name="city"
                            value={data.city}
                            className="mt-1 block w-full"
                            autoComplete="address-level2"
                            onChange={(e) => setData('city', e.target.value)}
                            placeholder="Springfield"
                        />
                        <InputError message={errors.city} className="mt-2" />
                    </Field>

                    <Field label="State / Province" htmlFor="state" hint={FIELD_HINTS.state}>
                        <TextInput
                            id="state"
                            name="state"
                            value={data.state}
                            className="mt-1 block w-full"
                            autoComplete="address-level1"
                            onChange={(e) => setData('state', e.target.value)}
                            placeholder="IL"
                        />
                        <InputError message={errors.state} className="mt-2" />
                    </Field>

                    <Field label="Postal Code" htmlFor="postal_code" hint={FIELD_HINTS.postal_code}>
                        <TextInput
                            id="postal_code"
                            name="postal_code"
                            value={data.postal_code}
                            className="mt-1 block w-full"
                            autoComplete="postal-code"
                            onChange={(e) => setData('postal_code', e.target.value)}
                            placeholder="62701"
                        />
                        <InputError message={errors.postal_code} className="mt-2" />
                    </Field>
                </div>

                <Field label="Country" htmlFor="country" hint={FIELD_HINTS.country}>
                    <TextInput
                        id="country"
                        name="country"
                        value={data.country}
                        className="mt-1 block w-full"
                        autoComplete="country-name"
                        onChange={(e) => setData('country', e.target.value)}
                        placeholder="United States"
                    />
                    <InputError message={errors.country} className="mt-2" />
                </Field>

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
        </GuestLayout>
    );
}