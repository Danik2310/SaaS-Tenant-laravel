import { useEffect, useMemo, useRef, useState } from 'react';
import LedgerLayout from '@/Layouts/LedgerLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import usePrefersReducedMotion from '@/hooks/usePrefersReducedMotion';
import ErrorSummary from './parts/ErrorSummary';
import FormField from './parts/FormField';
import OptionalDetails from './parts/OptionalDetails';
import PlanGrid from './parts/PlanGrid';
import PlanSummary from './parts/PlanSummary';
import StepIndicator from './parts/StepIndicator';
import { Head, useForm } from '@inertiajs/react';

function toSlug(value) {
    return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

const OPTIONAL_FIELDS = [
    'first_name',
    'last_name',
    'phone',
    'address_line1',
    'address_line2',
    'city',
    'state',
    'postal_code',
    'country',
];

const FIELD_HINTS = {
    company_name: 'Legal or trading name of the company',
    subdomain: 'Your subdomain plus the shared domain.',
    phone: 'Primary contact phone number',
    first_name: "Primary contact's first name",
    last_name: "Primary contact's last name",
    address_line1: 'Street address (e.g., 123 Main St)',
    address_line2: 'Apartment, suite, unit, etc.',
    city: 'City or locality',
    state: 'State, province, or region',
    postal_code: 'ZIP / postal code',
    country: 'Country of the registered address',
};

function Legend({ children }) {
    return (
        <legend className="text-xs font-bold uppercase tracking-[0.2em] text-brand-400">
            {children}
        </legend>
    );
}

function Fieldset({ children, className = '' }) {
    return <fieldset className={`space-y-5 ${className}`}>{children}</fieldset>;
}

export default function TenantRegister({
    plans = [],
    selected_plan = null,
    tenant_domain_suffix,
    feature_definitions = {},
    trial_days = 14,
}) {
    const [showForm, setShowForm] = useState(Boolean(selected_plan));
    const [optionalOpen, setOptionalOpen] = useState(false);
    const [showPassword, setShowPassword] = useState(false);
    const [focusPlansHeading, setFocusPlansHeading] = useState(false);
    const subdomainTouched = useRef(false);
    const planHeadingRef = useRef(null);
    const companyRef = useRef(null);
    const pendingFocus = useRef(null);
    const didInitialFocus = useRef(false);
    const prefersReducedMotion = usePrefersReducedMotion();

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

    const focusField = (key) => {
        if (!key) return;

        if (OPTIONAL_FIELDS.includes(key)) {
            pendingFocus.current = key;
            setOptionalOpen(true);
            return;
        }

        document.getElementById(key)?.focus();
    };

    useEffect(() => {
        const key = pendingFocus.current;

        if (!key) return;

        const element = document.getElementById(key);

        if (element && !element.closest('[hidden]')) {
            element.focus();
            pendingFocus.current = null;
        }
    }, [optionalOpen, errors]);

    const firstErrorKey = useMemo(() => {
        const keys = Object.keys(errors ?? {}).filter((key) => key !== 'website');

        return keys.length > 0 ? keys[0] : null;
    }, [errors]);

    useEffect(() => {
        if (firstErrorKey) {
            focusField(firstErrorKey);
        }
    }, [firstErrorKey]);

    useEffect(() => {
        if (showForm && !didInitialFocus.current) {
            didInitialFocus.current = true;

            // Only claim focus when there is no validation error waiting for it.
            if (!firstErrorKey) {
                companyRef.current?.focus();
            }
        }
    }, [showForm, firstErrorKey]);

    useEffect(() => {
        if (!showForm && focusPlansHeading) {
            planHeadingRef.current?.focus();
            setFocusPlansHeading(false);
        }
    }, [showForm, focusPlansHeading]);

    const submit = (e) => {
        e.preventDefault();

        post(route('register.tenant'));
    };

    const handleSelect = (planSlug) => {
        setData('plan', planSlug);
        setShowForm(true);
    };

    const handleBackToPlans = () => {
        setShowForm(false);
        setOptionalOpen(false);
        setFocusPlansHeading(true);
        didInitialFocus.current = false;
    };

    const stepAnimation = prefersReducedMotion ? undefined : 'animate-sli-step-in';

    return (
        <LedgerLayout>
            <Head title={showForm ? 'Create your workspace' : 'Choose your plan'} />

            <p aria-live="polite" role="status" className="sr-only">
                {showForm ? 'Step 2 of 2: workspace details' : 'Step 1 of 2: choose your plan'}
            </p>

            <div className={showForm ? stepAnimation : undefined}>
                <StepIndicator current={showForm ? 'workspace' : 'plan'} />

                {!showForm ? (
                    <>
                        <div className="mt-8">
                            <p className="text-xs font-bold uppercase tracking-[0.2em] text-brand-400">
                                Step 1
                            </p>
                            <h1
                                id="plan-step-heading"
                                ref={planHeadingRef}
                                tabIndex={-1}
                                className="mt-3 text-[clamp(1.75rem,4vw,2.25rem)] font-bold leading-tight tracking-tight text-balance text-white focus:outline-none"
                            >
                                Choose the plan your shop will grow into
                            </h1>
                            <p className="mt-3 max-w-prose text-sm leading-relaxed text-white/60">
                                Every plan includes a 14-day trial of the paid features. Pick the one that fits
                                how many people and locations you need to run.
                            </p>
                        </div>

                        <div className="mt-8">
                            <PlanGrid
                                plans={plans}
                                featureDefinitions={feature_definitions}
                                trialDays={trial_days}
                                onSelect={handleSelect}
                                processing={processing}
                            />
                        </div>
                    </>
                ) : (
                    <>
                        <div className="mt-8 flex items-start justify-between gap-4">
                            <div>
                                <p className="text-xs font-bold uppercase tracking-[0.2em] text-brand-400">
                                    Step 2
                                </p>
                                <h1 className="mt-3 text-[clamp(1.75rem,4vw,2.25rem)] font-bold leading-tight tracking-tight text-balance text-white">
                                    Create your workspace
                                </h1>
                                <p className="mt-3 max-w-prose text-sm leading-relaxed text-white/60">
                                    Fields marked <span className="font-semibold">*</span> are required. Everything
                                    else is optional and can be filled in later.
                                </p>
                            </div>

                            {plans.length > 0 && (
                                <button
                                    type="button"
                                    onClick={handleBackToPlans}
                                    data-testid="back-to-plans"
                                    className="shrink-0 rounded-sm text-sm font-medium text-white/60 underline underline-offset-4 transition-colors duration-150 ease-out hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 focus-visible:ring-offset-ink"
                                >
                                    ← Plans
                                </button>
                            )}
                        </div>

                        <PlanSummary plan={selectedPlan} variant="bar" />

                        <div className="lg:grid lg:grid-cols-[minmax(0,1fr)_20rem] lg:gap-10">
                            <form onSubmit={submit} className="mt-8">
                                <div className="sl-honey" aria-hidden="true">
                                    <label htmlFor="website">Website</label>
                                    <input
                                        id="website"
                                        data-testid="honeypot-website"
                                        tabIndex={-1}
                                        autoComplete="off"
                                        type="text"
                                        name="website"
                                        value={data.website}
                                        onChange={(e) => setData('website', e.target.value)}
                                    />
                                </div>

                                <ErrorSummary errors={errors} onFocusField={focusField} />

                                <Fieldset className="mt-8">
                                    <Legend>Your workspace</Legend>

                                    <FormField
                                            row
                                        label="Company Name"
                                        htmlFor="company_name"
                                        hint={FIELD_HINTS.company_name}
                                        error={errors.company_name}
                                        required
                                    >
                                        <TextInput
                                                tone="dark"
                                            id="company_name"
                                            ref={companyRef}
                                            name="company_name"
                                            value={data.company_name}
                                            className="block w-full"
                                            invalid={Boolean(errors.company_name)}
                                            autoComplete="organization"
                                            onChange={(e) => setData('company_name', e.target.value)}
                                            placeholder="e.g., Acme Corp LLC"
                                            required
                                        />
                                    </FormField>

                                    <FormField
                                            row
                                        label="Workspace Address"
                                        htmlFor="subdomain"
                                        hint={FIELD_HINTS.subdomain}
                                        error={errors.subdomain}
                                    >
                                        <TextInput
                                                tone="dark"
                                            id="subdomain"
                                            name="subdomain"
                                            value={data.subdomain}
                                            className="block w-full"
                                            invalid={Boolean(errors.subdomain)}
                                            autoComplete="off"
                                            spellCheck={false}
                                            onChange={(e) => {
                                                subdomainTouched.current = true;
                                                setData('subdomain', e.target.value);
                                            }}
                                            placeholder="your-workspace"
                                        />

                                        <p aria-live="polite" data-testid="subdomain-preview" className="text-xs text-gray-500">
                                            Your workspace address will be{' '}
                                            <span className="font-medium text-gray-700">
                                                {data.subdomain || slug || 'your-workspace'}.{tenant_domain_suffix}
                                            </span>
                                        </p>
                                    </FormField>

                                    <FormField
                                            row
                                        label="Workspace name"
                                        htmlFor="name"
                                        hint="Shown in the sidebar and on invoices"
                                        error={errors.name}
                                        required
                                    >
                                        <TextInput
                                                tone="dark"
                                            id="name"
                                            name="name"
                                            value={data.name}
                                            className="block w-full"
                                            invalid={Boolean(errors.name)}
                                            autoComplete="name"
                                            onChange={(e) => setData('name', e.target.value)}
                                            placeholder="e.g., Acme Corp"
                                            required
                                        />
                                    </FormField>

                                    <FormField
                                            row
                                        label="Email"
                                        htmlFor="email"
                                        hint="Primary contact email for this tenant"
                                        error={errors.email}
                                        required
                                    >
                                        <TextInput
                                                tone="dark"
                                            id="email"
                                            type="email"
                                            name="email"
                                            value={data.email}
                                            className="block w-full"
                                            invalid={Boolean(errors.email)}
                                            autoComplete="username"
                                            onChange={(e) => setData('email', e.target.value)}
                                            placeholder="admin@tenant.com"
                                            required
                                        />
                                    </FormField>
                                </Fieldset>

                                <Fieldset className="mt-8">
                                    <Legend>Secure your account</Legend>

                                    <div>
                                        <div className="flex items-center justify-between gap-3">
                                            <div className="flex items-baseline gap-1">
                                                <InputLabel htmlFor="password" value="Password" />
                                                <span aria-hidden="true" className="text-brand-700">
                                                    *
                                                </span>
                                            </div>

                                            <button
                                                type="button"
                                                onClick={() => setShowPassword((v) => !v)}
                                                aria-pressed={showPassword}
                                                data-testid="password-visibility-toggle"
                                                className="shrink-0 rounded-md text-xs font-medium text-brand-700 underline underline-offset-2 hover:text-brand-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-ink focus-visible:ring-offset-2"
                                            >
                                                {showPassword ? 'Hide password' : 'Show password'}
                                            </button>
                                        </div>

                                        <TextInput
                                                tone="dark"
                                            id="password"
                                            type={showPassword ? 'text' : 'password'}
                                            name="password"
                                            value={data.password}
                                            className="mt-1.5 block w-full"
                                            invalid={Boolean(errors.password)}
                                            aria-describedby="password-hint"
                                            aria-invalid={errors.password ? 'true' : undefined}
                                            autoComplete="new-password"
                                            onChange={(e) => setData('password', e.target.value)}
                                            required
                                        />

                                        <p id="password-hint" className="mt-1 text-xs text-gray-500">
                                            At least 8 characters.
                                        </p>

                                        <InputError
                                            id="password-error"
                                            message={errors.password}
                                            className="mt-1"
                                        />
                                    </div>

                                    <FormField
                                            row
                                        label="Confirm Password"
                                        htmlFor="password_confirmation"
                                        error={errors.password_confirmation}
                                        required
                                    >
                                        <TextInput
                                                tone="dark"
                                            id="password_confirmation"
                                            type={showPassword ? 'text' : 'password'}
                                            name="password_confirmation"
                                            value={data.password_confirmation}
                                            className="block w-full"
                                            invalid={Boolean(errors.password_confirmation)}
                                            autoComplete="new-password"
                                            onChange={(e) => setData('password_confirmation', e.target.value)}
                                            required
                                        />
                                    </FormField>

                                    <div>
                                        <label htmlFor="terms" className="flex items-start text-sm text-gray-700">
                                            <input
                                                id="terms"
                                                type="checkbox"
                                                name="terms"
                                                checked={data.terms}
                                                onChange={(e) => setData('terms', e.target.checked)}
                                                className="mt-0.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                                                required
                                            />
                                            <span className="ms-2">
                                                I agree to the terms of service and privacy policy.
                                            </span>
                                        </label>

                                        <InputError
                                            id="terms-error"
                                            message={errors.terms}
                                            className="mt-2"
                                        />
                                    </div>
                                </Fieldset>

                                <OptionalDetails
                                    open={optionalOpen}
                                    onToggle={() => setOptionalOpen((v) => !v)}
                                    label="Contact & billing"
                                    hint="Optional — billing address and a named contact for invoices"
                                >
                                    <div className="grid gap-x-4 sm:grid-cols-2">
                                        <FormField
                                                row
                                            label="First Name"
                                            htmlFor="first_name"
                                            hint={FIELD_HINTS.first_name}
                                            error={errors.first_name}
                                        >
                                            <TextInput
                                                    tone="dark"
                                                id="first_name"
                                                name="first_name"
                                                value={data.first_name}
                                                className="block w-full"
                                                invalid={Boolean(errors.first_name)}
                                                autoComplete="section-contact given-name"
                                                onChange={(e) => setData('first_name', e.target.value)}
                                                placeholder="Jane"
                                            />
                                        </FormField>

                                        <FormField
                                                row
                                            label="Last Name"
                                            htmlFor="last_name"
                                            hint={FIELD_HINTS.last_name}
                                            error={errors.last_name}
                                        >
                                            <TextInput
                                                    tone="dark"
                                                id="last_name"
                                                name="last_name"
                                                value={data.last_name}
                                                className="block w-full"
                                                invalid={Boolean(errors.last_name)}
                                                autoComplete="section-contact family-name"
                                                onChange={(e) => setData('last_name', e.target.value)}
                                                placeholder="Doe"
                                            />
                                        </FormField>
                                    </div>

                                    <FormField
                                            row
                                        label="Phone (optional)"
                                        htmlFor="phone"
                                        hint={FIELD_HINTS.phone}
                                        error={errors.phone}
                                    >
                                        <TextInput
                                                tone="dark"
                                            id="phone"
                                            type="tel"
                                            name="phone"
                                            value={data.phone}
                                            className="block w-full"
                                            invalid={Boolean(errors.phone)}
                                            autoComplete="section-contact tel"
                                            onChange={(e) => setData('phone', e.target.value)}
                                            placeholder="+1 555 123 4567"
                                        />
                                    </FormField>

                                    <FormField
                                            row
                                        label="Address Line 1"
                                        htmlFor="address_line1"
                                        hint={FIELD_HINTS.address_line1}
                                        error={errors.address_line1}
                                    >
                                        <TextInput
                                                tone="dark"
                                            id="address_line1"
                                            name="address_line1"
                                            value={data.address_line1}
                                            className="block w-full"
                                            invalid={Boolean(errors.address_line1)}
                                            autoComplete="section-billing address-line1"
                                            onChange={(e) => setData('address_line1', e.target.value)}
                                            placeholder="123 Main St"
                                        />
                                    </FormField>

                                    <FormField
                                            row
                                        label="Address Line 2"
                                        htmlFor="address_line2"
                                        hint={FIELD_HINTS.address_line2}
                                        error={errors.address_line2}
                                    >
                                        <TextInput
                                                tone="dark"
                                            id="address_line2"
                                            name="address_line2"
                                            value={data.address_line2}
                                            className="block w-full"
                                            invalid={Boolean(errors.address_line2)}
                                            autoComplete="section-billing address-line2"
                                            onChange={(e) => setData('address_line2', e.target.value)}
                                            placeholder="Suite 400"
                                        />
                                    </FormField>

                                    <div className="grid gap-x-4 sm:grid-cols-3">
                                        <FormField row label="City" htmlFor="city" hint={FIELD_HINTS.city} error={errors.city}>
                                            <TextInput
                                                    tone="dark"
                                                id="city"
                                                name="city"
                                                value={data.city}
                                                className="block w-full"
                                                invalid={Boolean(errors.city)}
                                                autoComplete="section-billing address-level2"
                                                onChange={(e) => setData('city', e.target.value)}
                                                placeholder="Springfield"
                                            />
                                        </FormField>

                                        <FormField
                                                row
                                            label="State / Province"
                                            htmlFor="state"
                                            hint={FIELD_HINTS.state}
                                            error={errors.state}
                                        >
                                            <TextInput
                                                    tone="dark"
                                                id="state"
                                                name="state"
                                                value={data.state}
                                                className="block w-full"
                                                invalid={Boolean(errors.state)}
                                                autoComplete="section-billing address-level1"
                                                onChange={(e) => setData('state', e.target.value)}
                                                placeholder="IL"
                                            />
                                        </FormField>

                                        <FormField
                                                row
                                            label="Postal Code"
                                            htmlFor="postal_code"
                                            hint={FIELD_HINTS.postal_code}
                                            error={errors.postal_code}
                                        >
                                            <TextInput
                                                    tone="dark"
                                                id="postal_code"
                                                name="postal_code"
                                                value={data.postal_code}
                                                className="block w-full"
                                                invalid={Boolean(errors.postal_code)}
                                                autoComplete="section-billing postal-code"
                                                onChange={(e) => setData('postal_code', e.target.value)}
                                                placeholder="62701"
                                            />
                                        </FormField>
                                    </div>

                                    <FormField
                                            row
                                        label="Country"
                                        htmlFor="country"
                                        hint={FIELD_HINTS.country}
                                        error={errors.country}
                                    >
                                        <TextInput
                                                tone="dark"
                                            id="country"
                                            name="country"
                                            value={data.country}
                                            className="block w-full"
                                            invalid={Boolean(errors.country)}
                                            autoComplete="section-billing country-name"
                                            onChange={(e) => setData('country', e.target.value)}
                                            placeholder="United States"
                                        />
                                    </FormField>
                                </OptionalDetails>

                                <div className="mt-8">
                                    <PrimaryButton
                                        type="submit"
                                        className="w-full"
                                        disabled={processing}
                                        loading={processing}
                                        loadingText="Creating your workspace…"
                                        data-testid="submit-button"
                                    >
                                        Create workspace
                                    </PrimaryButton>
                                </div>
                            </form>

                            <PlanSummary plan={selectedPlan} variant="rail" />
                        </div>
                    </>
                )}
            </div>
        </LedgerLayout>
    );
}
