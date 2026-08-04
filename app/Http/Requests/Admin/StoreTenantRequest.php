<?php

namespace App\Http\Requests\Admin;

use App\Models\Domain;
use App\Models\Tenant;
use App\Shared\Constants\PermissionNames;
use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->can(PermissionNames::CREATE_TENANTS);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'domain' => 'required|string',
            'plan' => 'nullable|string|exists:mysql_central.plans,slug',
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $data = $validator->validated();

                if (! empty($data['email'])) {
                    $this->checkEmail($data['email'], $validator);
                }

                if (! empty($data['domain'])) {
                    $this->checkDomain($data['domain'], $validator);
                }
            },
        ];
    }

    private function checkEmail(string $email, $validator): void
    {
        $existing = Tenant::withTrashed()->where('email', $email)->first();

        if (! $existing) {
            return;
        }

        if ($existing->trashed()) {
            $validator->errors()->add('email',
                "A tenant with the email '{$email}' was deleted on "
                .$existing->deleted_at->format('Y-m-d').'. '
                .'Restore it instead of creating a duplicate: '
                ."PATCH /admin/api/tenants/{$existing->id}/restore"
            );
        } else {
            $validator->errors()->add('email',
                "A tenant with the email '{$email}' already exists (ID: {$existing->id})."
            );
        }
    }

    private function checkDomain(string $domain, $validator): void
    {
        $existing = Domain::where('domain', $domain)->with('tenant')->first();

        if (! $existing) {
            return;
        }

        $tenant = $existing->tenant;
        $name = $tenant ? "'{$tenant->name}'" : 'another tenant';
        $suffix = '';

        if ($tenant && $tenant->trashed()) {
            $suffix = ' (this tenant was deleted)';
        }

        $validator->errors()->add('domain',
            "The domain '{$domain}' is already in use by {$name}{$suffix}."
        );
    }
}
