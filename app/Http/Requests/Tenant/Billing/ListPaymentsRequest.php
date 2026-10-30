<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Billing;

use App\Models\SubscriptionPayment;
use App\Shared\Constants\PermissionNames;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

class ListPaymentsRequest extends FormRequest
{
    /**
     * Payment history is financial data: only tenant users holding the
     * `manage payments` permission may read it. Enforced again at the route
     * level (`permission:manage payments,web`) as defence in depth.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && tenant() && $user->can(PermissionNames::MANAGE_PAYMENTS);
    }

    /**
     * @return array<string, array<int, string|In>>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
            'status' => ['nullable', Rule::in(['completed', 'pending', 'failed', 'refunded'])],
        ];
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 15);
    }

    /**
     * Apply the validated filters and stable sort to a payment query.
     *
     * @param  Builder<SubscriptionPayment>  $query
     * @return Builder<SubscriptionPayment>
     */
    public function applyFilters($query)
    {
        return $query
            ->when(
                $this->validated('status'),
                fn ($query, string $status) => $query->where('status', $status)
            )
            ->orderByDesc('paid_at')
            ->orderByDesc('id');
    }
}
