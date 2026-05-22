<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePaymentMethodRequest;
use App\Http\Requests\Admin\TogglePaymentMethodRequest;
use App\Http\Requests\Admin\UpdatePaymentMethodRequest;
use App\Http\Resources\PaymentMethodResource;
use App\Models\PaymentMethod;
use App\Traits\AuditablePaymentMethods;
use Illuminate\Support\Facades\Cache;

class PaymentMethodController extends Controller
{
    use AuditablePaymentMethods;

    public function index()
    {
        if (ob_get_level()) {
            ob_end_clean();
        }

        $methods = Cache::remember('payment_methods_all', 3600, fn () =>
            PaymentMethodResource::collection(PaymentMethod::all())
        );

        try {
            $this->logPaymentMethodAccessed(null, 'list');
        } catch (\Exception $e) {
            \Log::error('Failed to log payment method access: '.$e->getMessage());
        }

        return response()->json(['methods' => $methods]);
    }

    public function store(StorePaymentMethodRequest $request)
    {
        $method = PaymentMethod::create($request->validated());

        Cache::forget('payment_methods_all');
        $this->logPaymentMethodCreated($method);

        return response()->json(['method' => new PaymentMethodResource($method)], 201);
    }

    public function show(string $id)
    {
        $method = PaymentMethod::findOrFail($id);

        $this->logPaymentMethodAccessed($method, 'view');

        return response()->json(['method' => new PaymentMethodResource($method)]);
    }

    public function update(UpdatePaymentMethodRequest $request, string $id)
    {
        $method = PaymentMethod::findOrFail($id);

        $oldData = [
            'name' => $method->name,
            'provider' => $method->provider,
            'mode' => $method->mode,
            'active' => $method->active,
            'api_key' => $method->getAttributes()['api_key'] ?? null,
            'secret_key' => $method->getAttributes()['secret_key'] ?? null,
        ];

        $method->update($request->validated());

        Cache::forget('payment_methods_all');
        $this->logPaymentMethodUpdated($method, $oldData);

        return response()->json(['method' => new PaymentMethodResource($method)]);
    }

    public function toggleActive(TogglePaymentMethodRequest $request, string $id)
    {
        $method = PaymentMethod::findOrFail($id);
        $oldActive = $method->active;

        $method->update(['active' => ! $method->active]);

        Cache::forget('payment_methods_all');
        $this->logPaymentMethodToggled($method, $oldActive);

        return response()->json(['method' => new PaymentMethodResource($method)]);
    }

    public function destroy(string $id)
    {
        $method = PaymentMethod::findOrFail($id);

        Cache::forget('payment_methods_all');
        $this->logPaymentMethodDeleted($method);
        $method->delete();

        return response()->noContent();
    }
}
