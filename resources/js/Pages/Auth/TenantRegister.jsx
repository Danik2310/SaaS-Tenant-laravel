import { useEffect, useMemo, useRef, useState } from 'react';
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

function PlanCarousel({ plans, featureDefinitions, onSelect, processing }) {
    const viewportRef = useRef(null);
    const [activeIndex, setActiveIndex] = useState(0);

    useEffect(() => {
        const viewport = viewportRef.current;

        if (!viewport) {
            return undefined;
        }

        const recompute = () => {
            const card = viewport.querySelector('[data-testid^="plan-card-"]');

            if (card) {
                const index = Math.round(viewport.scrollLeft / card.offsetWidth);

                setActiveIndex(Math.max(0, Math.min(index, plans.length - 1)));
            }
        };

        viewport.addEventListener('scroll', recompute, { passive: true });
        window.addEventListener('resize', recompute);

        return () => {
            viewport.removeEventListener('scroll', recompute);
            window.removeEventListener('resize', recompute);
        };
    }, [plans.length]);

    const scrollToIndex = (index) => {
        const viewport = viewportRef.current;

        if (!viewport) {
            return;
        }

        const card = viewport.querySelector(`[data-testid="plan-card-${plans[index].slug}"]`);

        if (card) {
            viewport.scrollTo({ left: card.offsetLeft - viewport.offsetLeft, behavior: 'smooth' });
        }
    };

    const handlePrev = () => scrollToIndex(Math.max(0, activeIndex - 1));

    const handleNext = () => scrollToIndex(Math.min(plans.length - 1, activeIndex + 1));

    const handleKeyDown = (e) => {
        if (e.key === 'ArrowLeft') {
            e.preventDefault();
            handlePrev();
        }

        if (e.key === 'ArrowRight') {
            e.preventDefault();
            handleNext();
        }
    };

    return (
        <section aria-label="Choose your plan">
            <div className="flex items-end justify-between gap-4">
                <div>
                    <h2 className="text-xl font-semibold text-gray-900">Choose your plan</h2>
                    <p className="mt-1 text-sm text-gray-600">
                        Pick a plan to start your workspace. No credit card required until checkout.
                    </p>
                </div>

                <div className="flex shrink-0 gap-2">
                    <button
                        type="button"
                        onClick={handlePrev}
                        data-testid="carousel-prev"
                        aria-label="Previous plans"
                        disabled={activeIndex === 0 || processing}
                        className="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-40"
                    >
                        ←
                    </button>

                    <button
                        type="button"
                        onClick={handleNext}
                        data-testid="carousel-next"
                        aria-label="Next plans"
                        disabled={activeIndex === plans.length - 1 || processing}
                        className="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-40"
                    >
                        →
                    </button>
                </div>
            </div>

            <div
                ref={viewportRef}
                data-testid="plan-carousel"
                role="region"
                aria-roledescription="carousel"
                aria-label="Plans"
                tabIndex={0}
                onKeyDown={handleKeyDown}
                className="mt-6 flex snap-x snap-mandatory gap-4 overflow-x-auto scroll-smooth pb-4"
            >
                {plans.map((plan, index) => (
                    <article
                        key={plan.slug}
                        data-testid={`plan-card-${plan.slug}`}
                        aria-roledescription="slide"
                        aria-label={`${plan.name}, slide ${index + 1} of ${plans.length}`}
                        className="w-[85%] shrink-0 snap-start flex flex-col rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:w-[calc(50%-0.5rem)] lg:w-[calc(33.333%-0.6667rem)]"
                    >
                        {plan.slug === 'trial' && (
                            <span className="inline-block self-start rounded bg-indigo-50 px-1.5 py-0.5 text-xs font-medium text-indigo-700">
                                14-day free trial
                            </span>
                        )}

                        <h3 className="mt-2 text-lg font-semibold text-gray-900">{plan.name}</h3>

                        <p className="mt-1 font-bold text-gray-900">{formatPrice(plan)}</p>

                        <ul className="mt-3 space-y-0.5 text-sm text-gray-600">
                            {plan.features.length === 0 ? (
                                <li className="text-gray-400">—</li>
                            ) : (
                                plan.features.map((key) => (
                                    <li key={key}>{featureDefinitions?.[key]?.label ?? key}</li>
                                ))
                            )}
                        </ul>

                        <dl className="mt-3 space-y-0.5 border-t border-gray-100 pt-3 text-sm">
                            {LIMIT_ROWS.map((row) => (
                                <div key={row.key} className="flex justify-between gap-2">
                                    <dt className="text-gray-500">{row.label}</dt>
                                    <dd className="font-medium text-gray-700">
                                        {limitValue(plan.limits?.[row.key])}
                                    </dd>
                                </div>
                            ))}
                        </dl>

                        <button
                            type="button"
                            onClick={() => onSelect(plan.slug)}
                            disabled={processing}
                            className="mt-auto pt-4 w-full inline-flex items-center justify-center rounded-md border border-transparent bg-gray-800 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50"
                        >
                            Sign Up
                        </button>
                    </article>
                ))}
            </div>

            <div className="mt-4 flex justify-center gap-2">
                {plans.map((plan, index) => (
                    <button
                        key={plan.slug}
                        type="button"
                        onClick={() => scrollToIndex(index)}
                        data-testid={`carousel-dot-${plan.slug}`}
                        aria-label={`Go to ${plan.name}`}
                        aria-current={index === activeIndex ? 'true' : undefined}
                        className={`h-2 w-2 rounded-full transition-colors ${
                            index === activeIndex ? 'bg-gray-800' : 'bg-gray-300 hover:bg-gray-400'
                        } focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2`}
                    />
                ))}
            </div>

            <p className="mt-6 text-center text-xs text-gray-500">
                Paid plans are billed securely at checkout. Trials and free plans have no charge.
            </p>
        </section>
    );
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

export default function TenantRegister({ plans = [], selected_plan = null, tenant_domain_suffix, feature_definitions = {} }) {
    const [showForm, setShowForm] = useState(Boolean(selected_plan));
    const subdomainTouched = useRef(false);

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
        subdomain: '',
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

    useEffect(() => {
        if (!subdomainTouched.current && slug !== '') {
            setData('subdomain', slug);
        }
    }, [slug]);

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
        <GuestLayout wide>
            <Head title={showForm ? 'Create your workspace' : 'Choose your plan'} />

            {!showForm ? (
                <PlanCarousel
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
                            <h2 className="text-xl font-semibold text-gray-900">Create New Tenant</h2>
                            <p className="mt-1 text-sm text-gray-600">
                                {selectedPlan
                                    ? `Plan: ${selectedPlan.name} · ${
                                          selectedPlan.slug === 'trial'
                                              ? '14-day free trial, no credit card required.'
                                              : Number(selectedPlan.price) === 0
                                                  ? 'free to start.'
                                                  : 'secure payment at checkout.'
                                      }`
                                    : 'Add a new tenant to the platform.'}
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
                    </Field>

                    <Field
                        label="Workspace Address"
                        htmlFor="subdomain"
                        hint="Your subdomain plus the shared domain."
                    >
                        <TextInput
                            id="subdomain"
                            name="subdomain"
                            value={data.subdomain}
                            className="mt-1 block w-full"
                            autoComplete="off"
                            spellCheck={false}
                            onChange={(e) => {
                                subdomainTouched.current = true;
                                setData('subdomain', e.target.value);
                            }}
                            placeholder="your-workspace"
                        />
                        <InputError message={errors.subdomain} className="mt-2" />

                        <p className="mt-1 text-xs text-gray-500">
                            Your workspace address will be{' '}
                            <span className="font-medium text-gray-700">
                                {data.subdomain || slug || 'your-workspace'}.{tenant_domain_suffix}
                            </span>
                        </p>
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
            )}
        </GuestLayout>
    );
}