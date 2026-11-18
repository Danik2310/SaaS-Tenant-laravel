<?php

declare(strict_types=1);

namespace App\Shared\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateTenantCurrencyRequest;
use App\Models\Setting;
use App\Shared\Contracts\CurrencySettingsInterface;
use App\Shared\Contracts\TenantCurrencyResolverInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class TenantCurrencyController extends Controller
{
    public function edit(
        CurrencySettingsInterface $settings,
        TenantCurrencyResolverInterface $resolver,
    ): Response {
        $allowed = array_values(array_filter(
            $settings->tenantCurrencies(),
            fn (string $code) => $code !== $settings->base()
        ));

        return Inertia::render('Tenant/CurrencySettings', [
            'currency' => $resolver->currentCode() ?? $settings->base(),
            'allowed_currencies' => $allowed,
            'base_currency' => $settings->base(),
        ]);
    }

    public function update(UpdateTenantCurrencyRequest $request): RedirectResponse
    {
        Setting::updateOrCreate(
            ['key' => 'currency'],
            ['value' => strtoupper((string) $request->validated('currency'))],
        );

        try {
            Cache::tags(['tenant_'.tenant('id')])->flush();
        } catch (Throwable) {
            // Untaggable cache stores need no per-tenant invalidation.
        }

        return back()->with('success', 'Dashboard currency saved.');
    }
}
