<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Traits\AuditablePaymentMethods;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentMethodController extends Controller
{
    use AuditablePaymentMethods;

    protected $fillable = [
        'name',
        'provider',
        'api_key',
        'secret_key',
        'mode',
        'active',
    ];

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Discard any stray output (e.g., from log handlers writing to stderr)
        if (ob_get_level()) {
            ob_end_clean();
        }

        $methods = PaymentMethod::all();

        // Log access to payment methods list
        try {
            $this->logPaymentMethodAccessed(null, 'list');
        } catch (\Exception $e) {
            \Log::error('Failed to log payment method access: ' . $e->getMessage());
        }

        return response()->json(['methods' => $methods]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:payment_methods,name',
            'provider' => 'required|in:stripe,paypal,other',
            'api_key' => 'nullable|string|min:10',
            'secret_key' => 'nullable|string|min:10',
            'mode' => 'required|in:test,live',
            'active' => 'boolean',
        ]);

        try {
            $method = PaymentMethod::create($validated);

            // Log the creation
            $this->logPaymentMethodCreated($method);

            return response()->json(['method' => $method], 201);
        } catch (\Exception $e) {
            // Log the error
            \Log::error('Payment method creation failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $method = PaymentMethod::findOrFail($id);

        // Log access to specific payment method
        $this->logPaymentMethodAccessed($method, 'view');

        // Return method without exposing api_key and secret_key
        return response()->json(['method' => $method]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:payment_methods,name,' . $id,
            'provider' => 'required|in:stripe,paypal,other',
            'api_key' => 'nullable|string|min:10',
            'secret_key' => 'nullable|string|min:10',
            'mode' => 'required|in:test,live',
            'active' => 'boolean',
        ]);

        $method = PaymentMethod::findOrFail($id);

        try {
            // Store old data for audit logging
            $oldData = [
                'name' => $method->name,
                'provider' => $method->provider,
                'mode' => $method->mode,
                'active' => $method->active,
                'api_key' => $method->getAttributes()['api_key'] ?? null,
                'secret_key' => $method->getAttributes()['secret_key'] ?? null,
            ];

            $method->update($validated);

            // Log the update with changes
            $this->logPaymentMethodUpdated($method, $oldData);

            return response()->json(['method' => $method]);
        } catch (\Exception $e) {
            // Log the error
            \Log::error('Payment method update failed', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Toggle the active status of a payment method.
     */
    public function toggleActive(string $id)
    {
        $method = PaymentMethod::findOrFail($id);

        // Store old active status for audit logging
        $oldActive = $method->active;

        $method->update(['active' => !$method->active]);

        // Log the toggle action
        $this->logPaymentMethodToggled($method, $oldActive);

        return response()->json(['method' => $method]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $method = PaymentMethod::findOrFail($id);

        // Log the deletion before deleting
        $this->logPaymentMethodDeleted($method);

        $method->delete();

        return response()->json(['message' => 'Payment method deleted successfully']);
    }
}
