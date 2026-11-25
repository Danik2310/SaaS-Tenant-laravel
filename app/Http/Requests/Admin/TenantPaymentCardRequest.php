<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Shared\Constants\PermissionNames;
use Illuminate\Foundation\Http\FormRequest;

class TenantPaymentCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check()
            && auth('admin')->user()->can(PermissionNames::EDIT_PAYMENT_METHODS);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
