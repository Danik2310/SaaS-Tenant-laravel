import { vi, describe, test, beforeEach, expect } from 'vitest';
import React from 'react';
import { render, screen, fireEvent, within } from '../test-utils';
import TenantRegister from '@/Pages/Auth/TenantRegister';
import TenantRegisterSuccess from '@/Pages/Auth/TenantRegisterSuccess';

const { useFormMock, lastForm } = vi.hoisted(() => {
    const useFormMock = vi.fn();
    const lastForm = () => {
        const results = useFormMock.mock.results;
        return results[results.length - 1]?.value ?? null;
    };
    return { useFormMock, lastForm };
});

vi.mock('@inertiajs/react', async () => {
    const ReactActual = await import('react');

    return {
        Head: ({ children }) => ReactActual.createElement(ReactActual.Fragment, null, children),
        Link: ({ href, children, ...rest }) =>
            ReactActual.createElement('a', { href, ...rest }, children),
        useForm: useFormMock,
    };
});

/** Installs the Inertia useForm stub. Overridden per-test to assert error states. */
const mockForm = ({ errors = {}, processing = false } = {}) => {
    useFormMock.mockImplementation((initial) => {
        const [data, setData] = React.useState(initial);

        return {
            data,
            setData: (key, value) => setData((prev) => ({ ...prev, [key]: value })),
            post: vi.fn(),
            processing,
            errors,
            reset: vi.fn(),
        };
    });
};

const plans = [
    { slug: 'trial', name: 'Trial', price: 0, currency: 'USD', duration_months: 1, can_signup: true, features: [], limits: {} },
    { slug: 'free', name: 'Free', price: 0, currency: 'USD', duration_months: null, can_signup: true, features: [], limits: {} },
    { slug: 'growth', name: 'Growth', price: 15, currency: 'USD', duration_months: 1, can_signup: true, features: [], limits: {} },
];

const featureDefinitions = {
    inventory: { label: 'Inventory management' },
    reports: { label: 'Advanced reports' },
    api: { label: 'API access' },
    webhooks: { label: 'Webhooks' },
    sso: { label: 'Single sign-on' },
};

describe('TenantRegister', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockForm();
        global.route = vi.fn((name) => (name === 'register.tenant' ? '/register' : `/${name}`));
        global.route.mockImplementation((name) => (name === 'register.tenant' ? '/register' : `/${name}`));
    });

    test('drops the step transition when the visitor prefers reduced motion', () => {
        // The only motion assertion that can mean anything here: jsdom does not
        // evaluate CSS animations, so the classes themselves are the contract.
        vi.spyOn(window, 'matchMedia').mockImplementation((query) => ({
            matches: query.includes('prefers-reduced-motion'),
            media: query,
            onchange: null,
            addEventListener() {},
            removeEventListener() {},
            addListener() {},
            removeListener() {},
            dispatchEvent: () => false,
        }));

        render(<TenantRegister plans={plans} tenant_domain_suffix="sasapp" />);

        fireEvent.click(within(screen.getByTestId('plan-card-trial')).getByRole('button', { name: /sign up/i }));

        expect(screen.queryByTestId('plan-grid')).not.toBeInTheDocument();
        expect(document.querySelector('.animate-sli-step-in')).toBeNull();
    });

    test('renders the plan grid before the form', () => {
        render(<TenantRegister plans={plans} tenant_domain_suffix="sasapp" />);

        expect(screen.getByTestId('plan-grid')).toBeInTheDocument();
        expect(screen.getByTestId('plan-card-trial')).toBeInTheDocument();
        expect(screen.getByTestId('plan-card-free')).toBeInTheDocument();
        expect(screen.getByTestId('plan-card-growth')).toBeInTheDocument();
        expect(screen.queryByLabelText('Workspace name')).not.toBeInTheDocument();
    });

    test('shows the full form after a plan is selected', () => {
        render(<TenantRegister plans={plans} tenant_domain_suffix="sasapp" />);

        fireEvent.click(within(screen.getByTestId('plan-card-trial')).getByRole('button', { name: /sign up/i }));

        expect(screen.queryByTestId('plan-grid')).not.toBeInTheDocument();
        expect(lastForm().data.plan).toBe('trial');
        expect(screen.getByLabelText('Workspace name')).toBeInTheDocument();
        expect(screen.getByLabelText('Email')).toBeInTheDocument();
        expect(screen.getByLabelText('Company Name')).toBeInTheDocument();
        expect(screen.getByLabelText('Workspace Address')).toBeInTheDocument();
        expect(screen.getByText('Contact & billing')).toBeInTheDocument();
        expect(screen.getByLabelText('First Name')).toBeInTheDocument();
        expect(screen.getByLabelText('Last Name')).toBeInTheDocument();
        expect(screen.getByLabelText('Phone (optional)')).toBeInTheDocument();
        expect(screen.getByLabelText('Address Line 1')).toBeInTheDocument();
        expect(screen.getByLabelText('Address Line 2')).toBeInTheDocument();
        expect(screen.getByLabelText('City')).toBeInTheDocument();
        expect(screen.getByLabelText('State / Province')).toBeInTheDocument();
        expect(screen.getByLabelText('Postal Code')).toBeInTheDocument();
        expect(screen.getByLabelText('Country')).toBeInTheDocument();
        expect(screen.getByLabelText('Password')).toBeInTheDocument();
        expect(screen.getByLabelText('Confirm Password')).toBeInTheDocument();
        expect(screen.getByRole('checkbox', { name: /terms of service/i })).toBeInTheDocument();
    });

    test('back button returns to the plan grid', () => {
        render(<TenantRegister plans={plans} tenant_domain_suffix="sasapp" />);

        fireEvent.click(within(screen.getByTestId('plan-card-trial')).getByRole('button', { name: /sign up/i }));
        fireEvent.click(screen.getByRole('button', { name: /← plans/i }));

        expect(screen.getByTestId('plan-grid')).toBeInTheDocument();
        expect(screen.queryByLabelText('Workspace name')).not.toBeInTheDocument();
    });

    test('shows the form immediately when a plan is preselected', () => {
        render(<TenantRegister plans={plans} selected_plan="trial" tenant_domain_suffix="sasapp" />);

        expect(screen.queryByTestId('plan-grid')).not.toBeInTheDocument();
        expect(screen.getByLabelText('Workspace name')).toBeInTheDocument();
        expect(lastForm().data.plan).toBe('trial');
    });

    test('prefills the workspace address from the company name', () => {
        render(<TenantRegister plans={plans} selected_plan="trial" tenant_domain_suffix="sasapp" />);

        fireEvent.change(screen.getByLabelText('Company Name'), {
            target: { value: 'Acme Corp' },
        });

        expect(screen.getByLabelText('Workspace Address')).toHaveValue('acme-corp');
        expect(screen.getByText('.sasapp')).toBeInTheDocument();
    });

    test('respects a manually chosen workspace address', () => {
        render(<TenantRegister plans={plans} selected_plan="trial" tenant_domain_suffix="sasapp" />);

        fireEvent.change(screen.getByLabelText('Company Name'), {
            target: { value: 'Acme Corp' },
        });

        fireEvent.change(screen.getByLabelText('Workspace Address'), {
            target: { value: 'acme-hq' },
        });

        expect(screen.getByLabelText('Workspace Address')).toHaveValue('acme-hq');
        expect(screen.getByText('.sasapp')).toBeInTheDocument();

        fireEvent.change(screen.getByLabelText('Company Name'), {
            target: { value: 'Acme Corp LLC' },
        });

        expect(screen.getByLabelText('Workspace Address')).toHaveValue('acme-hq');
    });

    test('submits the chosen subdomain along the preserve preselected plan', () => {
        const { container } = render(
            <TenantRegister plans={plans} selected_plan="trial" tenant_domain_suffix="sasapp" />
        );

        fireEvent.change(screen.getByLabelText('Company Name'), {
            target: { value: 'Acme Corp' },
        });

        fireEvent.submit(container.querySelector('form'));

        const form = lastForm();
        expect(form).not.toBeNull();
        expect(form.post).toHaveBeenCalledWith('/register');
        expect(form.data.plan).toBe('trial');
        expect(form.data.subdomain).toBe('acme-corp');
    });

    test('submits to the register.tenant route and preserves the preselected plan', () => {
        const { container } = render(
            <TenantRegister plans={plans} selected_plan="trial" tenant_domain_suffix="sasapp" />
        );

        fireEvent.submit(container.querySelector('form'));

        const form = lastForm();
        expect(form).not.toBeNull();
        expect(form.post).toHaveBeenCalledWith('/register');
        expect(form.data.plan).toBe('trial');
    });

    test('success page shows the workspace domain and login link', () => {
        render(
            <TenantRegisterSuccess
                domain="acme-corp.sasapp"
                loginUrl="https://acme-corp.sasapp/login"
                plan="Trial"
                trialDays={14}
                email="jane@acme.test"
            />
        );

        expect(screen.getByText('acme-corp.sasapp')).toBeInTheDocument();
        expect(screen.getByText('jane@acme.test')).toBeInTheDocument();

        expect(screen.getByRole('link', { name: /go to your workspace/i })).toHaveAttribute(
            'href',
            'https://acme-corp.sasapp/login'
        );
    });

    test('renders a grid with no carousel controls', () => {
        render(<TenantRegister plans={plans} tenant_domain_suffix="sasapp" trial_days={21} />);

        expect(screen.queryByRole('button', { name: /next plans/i })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /previous plans/i })).not.toBeInTheDocument();
        expect(screen.queryAllByTestId(/carousel-/)).toHaveLength(0);
        expect(screen.getByTestId('plan-grid')).toBeInTheDocument();
    });

    test('forwards the trial length to the plan cards', () => {
        render(<TenantRegister plans={plans} tenant_domain_suffix="sasapp" trial_days={21} />);

        expect(screen.getByTestId('plan-card-trial')).toHaveTextContent('for 21 days');
    });

    test('marks the growth plan as most popular', () => {
        render(<TenantRegister plans={plans} tenant_domain_suffix="sasapp" />);

        expect(screen.getByTestId('plan-card-growth')).toHaveAttribute('data-recommended', 'true');
        expect(screen.getByTestId('plan-card-growth')).toHaveTextContent('Most popular');
        expect(screen.getByTestId('plan-card-trial')).toHaveAttribute('data-recommended', 'false');
    });

    test('resolves feature labels from the feature definitions with a raw key fallback', () => {
        const withFeatures = [
            {
                ...plans[2],
                features: ['inventory', 'unknown_key', 'api', 'webhooks', 'sso'],
            },
        ];

        render(
            <TenantRegister
                plans={withFeatures}
                feature_definitions={featureDefinitions}
                tenant_domain_suffix="sasapp"
            />
        );

        const card = screen.getByTestId('plan-card-growth');

        expect(card).toHaveTextContent('Inventory management');
        expect(card).toHaveTextContent('API access');

        // Unknown feature keys fall back to the raw key instead of rendering blank.
        expect(card).toHaveTextContent('unknown_key');

        // Features past the fourth are summarised rather than listed in full.
        expect(card).not.toHaveTextContent('Single sign-on');
        expect(card).toHaveTextContent('+1 more');
    });

    test('moves focus to the first required field after selecting a plan', () => {
        render(<TenantRegister plans={plans} tenant_domain_suffix="sasapp" />);

        fireEvent.click(within(screen.getByTestId('plan-card-trial')).getByRole('button', { name: /sign up/i }));

        expect(screen.getByLabelText('Company Name')).toHaveFocus();
    });

    test('moves focus back to the plan heading when returning to the plans', () => {
        render(<TenantRegister plans={plans} tenant_domain_suffix="sasapp" />);

        fireEvent.click(within(screen.getByTestId('plan-card-trial')).getByRole('button', { name: /sign up/i }));
        fireEvent.click(screen.getByRole('button', { name: /← plans/i }));

        expect(
            screen.getByRole('heading', { level: 1, name: /choose the plan your shop will grow into/i })
        ).toHaveFocus();
    });

    test('announces the current signup step', () => {
        render(<TenantRegister plans={plans} tenant_domain_suffix="sasapp" />);

        expect(screen.getByTestId('step-indicator')).toBeInTheDocument();
        expect(screen.getByTestId('step-1')).toHaveAttribute('aria-current', 'step');
        expect(screen.getByTestId('step-2')).not.toHaveAttribute('aria-current');

        fireEvent.click(within(screen.getByTestId('plan-card-trial')).getByRole('button', { name: /sign up/i }));

        // Exactly one step is current at a time: finishing step 1 has to hand
        // aria-current over to step 2 rather than leave both flagged.
        expect(screen.getByTestId('step-1')).not.toHaveAttribute('aria-current');
        expect(screen.getByTestId('step-2')).toHaveAttribute('aria-current', 'step');
        expect(screen.getByText(/step 2 of 3: workspace details/i)).toBeInTheDocument();
    });

    test('keeps the optional details collapsed but mounted', () => {
        render(<TenantRegister plans={plans} selected_plan="trial" tenant_domain_suffix="sasapp" />);

        const toggle = screen.getByTestId('optional-details-toggle');

        expect(toggle).toHaveAttribute('aria-expanded', 'false');
        expect(screen.getByLabelText('First Name')).toBeInTheDocument();
        expect(screen.queryByRole('textbox', { name: 'First Name' })).not.toBeInTheDocument();

        fireEvent.click(toggle);

        expect(toggle).toHaveAttribute('aria-expanded', 'true');
        expect(screen.getByRole('textbox', { name: 'First Name' })).toBeInTheDocument();
    });

    test('links the password hint to the password input', () => {
        render(<TenantRegister plans={plans} selected_plan="trial" tenant_domain_suffix="sasapp" />);

        expect(screen.getByLabelText('Password')).toHaveAttribute('aria-describedby', 'password-hint');
        expect(screen.getByText('At least 8 characters.')).toBeInTheDocument();
    });

    test('toggles password visibility', () => {
        render(<TenantRegister plans={plans} selected_plan="trial" tenant_domain_suffix="sasapp" />);

        const toggle = screen.getByTestId('password-visibility-toggle');

        expect(toggle).toHaveAttribute('aria-pressed', 'false');
        expect(screen.getByLabelText('Password')).toHaveAttribute('type', 'password');

        fireEvent.click(toggle);

        expect(toggle).toHaveAttribute('aria-pressed', 'true');
        expect(screen.getByLabelText('Password')).toHaveAttribute('type', 'text');
    });

    test('announces the generated workspace address', () => {
        render(<TenantRegister plans={plans} selected_plan="trial" tenant_domain_suffix="sasapp" />);

        expect(screen.getByText('.sasapp')).toBeInTheDocument();
        expect(screen.queryByTestId('subdomain-preview')).not.toBeInTheDocument();
    });

    test('keeps the honeypot out of the tab order', () => {
        render(<TenantRegister plans={plans} selected_plan="trial" tenant_domain_suffix="sasapp" />);

        const honeypot = screen.getByTestId('honeypot-website');

        expect(honeypot).toHaveAttribute('tabindex', '-1');
        expect(honeypot.closest('[aria-hidden="true"]')).not.toBeNull();
        expect(honeypot).toHaveAttribute('autocomplete', 'off');
    });

    test('keeps the selected plan visible while the form is filled in', () => {
        render(<TenantRegister plans={plans} selected_plan="growth" tenant_domain_suffix="sasapp" />);

        expect(screen.getByTestId('plan-summary')).toBeInTheDocument();
        expect(screen.getByTestId('plan-summary-name')).toHaveTextContent('Growth');
    });

    // Growth is RECOMMENDED_SLUG, so it used to take the `featured` tone here -
    // near-black text tokens that are only legible on featured's white card.
    // The summary sits on the ink field and paints no surface of its own, so
    // that rendered as black on black. No assertion in this file reads colour,
    // which is why the whole suite stayed green while the panel was unreadable.
    test('keeps the summary on the ink palette even for the recommended plan', () => {
        render(<TenantRegister plans={plans} selected_plan="growth" tenant_domain_suffix="sasapp" />);

        const name = screen.getByTestId('plan-summary-name');

        expect(name).toHaveClass('text-brand-400');
        expect(name).not.toHaveClass('text-ink');

        // Nothing here may claim to be the light featured surface.
        expect(screen.getByTestId('plan-summary')).not.toHaveClass('bg-white');

        // Scoped to the summary: the grid also renders a PlanLimits for Growth,
        // and both share the plan-limits-<slug> testid.
        const limits = within(screen.getByTestId('plan-summary')).getByTestId('plan-limits-growth');

        expect(limits).toHaveClass('border-white/20');
        expect(limits).not.toHaveClass('border-ink/10');
    });

    test('marks the invalid field, links its error and focuses it', () => {
        mockForm({ errors: { email: 'This email address is already registered.' } });

        render(<TenantRegister plans={plans} selected_plan="trial" tenant_domain_suffix="sasapp" />);

        const email = screen.getByLabelText('Email');

        expect(email).toHaveAttribute('aria-invalid', 'true');
        expect(email).toHaveAttribute('aria-describedby', 'email-hint email-error');
        expect(email).toHaveFocus();

        // Reported twice on purpose: inline on the field, and in the summary banner.
        expect(screen.getAllByText('This email address is already registered.')).toHaveLength(2);
        expect(screen.getByTestId('form-error-banner')).toHaveTextContent(
            'This email address is already registered.'
        );
    });

    test('summarises every server error and offers a jump link per field', () => {
        mockForm({
            errors: {
                provisioning: 'We could not provision your workspace.',
                plan: 'Please choose a plan.',
                website: 'Invalid submission.',
                email: 'This email address is already registered.',
            },
        });

        render(<TenantRegister plans={plans} selected_plan="trial" tenant_domain_suffix="sasapp" />);

        const banner = screen.getByTestId('form-error-banner');

        expect(banner).toHaveAttribute('role', 'alert');
        expect(banner).toHaveTextContent('We could not provision your workspace.');
        expect(banner).toHaveTextContent('Please choose a plan.');
        expect(banner).toHaveTextContent('Invalid submission.');
        expect(screen.getByRole('button', { name: /go to email/i })).toBeInTheDocument();
    });

    test('expands the optional details when a hidden field fails validation', () => {
        mockForm({ errors: { first_name: 'The first name must be at least 2 characters.' } });

        render(<TenantRegister plans={plans} selected_plan="trial" tenant_domain_suffix="sasapp" />);

        expect(screen.getByTestId('optional-details-toggle')).toHaveAttribute('aria-expanded', 'true');
        expect(screen.getByLabelText('First Name')).toHaveFocus();
    });

    test('shows a pending state on the submit button while posting', () => {
        mockForm({ processing: true });

        render(<TenantRegister plans={plans} selected_plan="trial" tenant_domain_suffix="sasapp" />);

        const submit = screen.getByTestId('submit-button');

        expect(submit).toBeDisabled();
        expect(submit).toHaveAttribute('aria-busy', 'true');
        expect(submit).toHaveTextContent('Creating your workspace');
    });
});
