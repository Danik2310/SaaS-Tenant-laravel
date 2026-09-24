<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Domain;
use App\Shared\Constants\PermissionNames;
use Illuminate\Foundation\Http\FormRequest;

class StoreTenantDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->can(PermissionNames::EDIT_TENANTS);
    }

    public function rules(): array
    {
        return [
            'domain' => 'required|string|max:255',
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $domain = $validator->validated()['domain'] ?? null;

                if ($domain) {
                    $this->checkDomain($domain, $validator);
                }
            },
        ];
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
