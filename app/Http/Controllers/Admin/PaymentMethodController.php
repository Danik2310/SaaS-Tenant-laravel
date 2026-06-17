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

/**
 * @group Payment Method Management
 *
 * APIs for managing payment methods in the admin panel.
 */
class PaymentMethodController extends Controller
{
    use AuditablePaymentMethods;

    /**
     * List all payment methods.
     *
     * @authenticated
     */
    public function index()
    {
        $perPage = min((int) request('per_page', 50), 100);
        $paymentMethods = PaymentMethod::paginate($perPage);

        try {
            $this->logPaymentMethodAccessed(null, 'list');
        } catch (\Exception $e) {
            \Log::error('Failed to log payment method access: '.$e->getMessage());
        }

        return response()->json([
            'data' => PaymentMethodResource::collection($paymentMethods->items()),
            'meta' => [
                'current_page' => $paymentMethods->currentPage(),
                'last_page' => $paymentMethods->lastPage(),
                'per_page' => $paymentMethods->perPage(),
                'total' => $paymentMethods->total(),
            ],
        ]);
    }

    /**
     * Create a payment method.
     *
     * @authenticated
     *
     * @bodyParam name string required Payment method name.
     * @bodyParam provider string required Provider identifier.
     * @bodyParam mode string required Mode (test, live).
     *
     * @apiResource App\Http\Resources\PaymentMethodResource
     *
     * @apiResourceModel App\Models\PaymentMethod
     *
     * @response 201 {"message":"Payment method created","data":{...}}
     */
    public function store(StorePaymentMethodRequest $request)
    {
        $method = PaymentMethod::create($request->validated());

        Cache::forget('payment_methods_all');
        $this->logPaymentMethodCreated($method);

        return response()->json(['data' => new PaymentMethodResource($method)], 201);
    }

    /**
     * Get a single payment method.
     *
     * @authenticated
     *
     * @urlParam id integer required The payment method ID.
     */
    public function show(string $id)
    {
        $method = PaymentMethod::findOrFail($id);

        $this->logPaymentMethodAccessed($method, 'view');

        return response()->json(['data' => new PaymentMethodResource($method)]);
    }

    /**
     * Update a payment method.
     *
     * @authenticated
     *
     * @urlParam id integer required The payment method ID.
     */
    public function update(UpdatePaymentMethodRequest $request, string $id)
    {
        $method = PaymentMethod::findOrFail($id);

        $oldData = [
            'name' => $method->name,
            'provider' => $method->provider,
            'mode' => $method->mode,
            'active' => $method->active,
        ];

        $method->update($request->validated());

        Cache::forget('payment_methods_all');
        $this->logPaymentMethodUpdated($method, $oldData);

        return response()->json(['data' => new PaymentMethodResource($method)]);
    }

    public function toggleActive(TogglePaymentMethodRequest $request, string $id)
    {
        $method = PaymentMethod::findOrFail($id);
        $oldActive = $method->active;

        $method->update(['active' => ! $method->active]);

        Cache::forget('payment_methods_all');
        $this->logPaymentMethodToggled($method, $oldActive);

        return response()->json(['data' => new PaymentMethodResource($method)]);
    }

    /**
     * Delete a payment method.
     *
     * @authenticated
     *
     * @urlParam id integer required The payment method ID.
     *
     * @response 204 No content.
     */
    public function destroy(string $id)
    {
        $method = PaymentMethod::findOrFail($id);

        Cache::forget('payment_methods_all');
        $this->logPaymentMethodDeleted($method);
        $method->delete();

        return response()->noContent();
    }
}
