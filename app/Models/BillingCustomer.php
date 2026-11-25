<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Cashier\Billable;

/**
 * Cashier's billable view of a tenant.
 *
 * The app already has a `Tenant::subscriptions()` relation for its local plan
 * projection, which collides with Cashier's Billable relation. A dedicated
 * model mapped to the same central `tenants` row keeps both systems intact.
 */
class BillingCustomer extends Model
{
    use Billable;
    use SoftDeletes;

    protected $connection = 'mysql_central';

    protected $table = 'tenants';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'email',
        'billing_email',
        'company_name',
        'tax_id',
        'stripe_customer_id',
        'stripe_id',
        'pm_type',
        'pm_last_four',
        'pm_exp_month',
        'pm_exp_year',
        'pm_card_count',
        'pm_synced_at',
        'trial_ends_at',
        'registration_trial_ends_at',
        'phone',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'registration_trial_ends_at' => 'datetime',
        'deleted_at' => 'datetime',
        'pm_exp_month' => 'integer',
        'pm_exp_year' => 'integer',
        'pm_card_count' => 'integer',
        'pm_synced_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $customer) {
            if ($customer->isDirty('stripe_id') && ! $customer->isDirty('stripe_customer_id')) {
                $customer->stripe_customer_id = $customer->stripe_id;
            } elseif ($customer->isDirty('stripe_customer_id') && ! $customer->isDirty('stripe_id')) {
                $customer->stripe_id = $customer->stripe_customer_id;
            }
        });
    }

    public function stripeName(): ?string
    {
        $name = $this->company_name ?: $this->name;

        return is_string($name) && $name !== '' ? $name : null;
    }

    public function stripeEmail(): ?string
    {
        $email = $this->billing_email ?: $this->email;

        return is_string($email) && $email !== '' ? $email : null;
    }

    public function stripePhone(): ?string
    {
        $phone = $this->phone;

        return is_string($phone) && $phone !== '' ? $phone : null;
    }

    /**
     * @return array<string, string>
     */
    public function stripeAddress(): array
    {
        $country = (string) $this->country;

        // Stripe expects a two-letter country code. Until the billing form
        // resolves country names to ISO2 codes, omit a partially mapped address
        // rather than sending an invalid value.
        if (strlen($country) !== 2) {
            return [];
        }

        return array_filter([
            'line1' => $this->address_line1,
            'line2' => $this->address_line2,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country' => strtoupper($country),
        ], fn ($value): bool => is_string($value) && $value !== '');
    }

    /**
     * @return array<string, string>
     */
    public function stripeMetadata(): ?array
    {
        return [
            'tenant_id' => (string) $this->id,
            'tenant_reference' => (string) $this->reference_id,
        ];
    }
}
