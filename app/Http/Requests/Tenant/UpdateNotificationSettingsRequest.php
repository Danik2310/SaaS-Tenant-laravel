<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Shared\Constants\PermissionNames;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->user() === null || tenant() === null) {
            return false;
        }

        return $this->user()->can(PermissionNames::MANAGE_SETTINGS);
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'low_stock_threshold' => 'required|integer|min:0|max:100000',
            'large_order_threshold' => 'required|numeric|min:0|max:100000000',
            'spike_multiplier' => 'required|numeric|min:1|max:100',
        ];
    }
}
