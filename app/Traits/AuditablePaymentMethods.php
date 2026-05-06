<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait AuditablePaymentMethods
{
    /**
     * Log payment method creation
     */
    protected function logPaymentMethodCreated($method, $userId = null)
    {
        $userId = $userId ?? auth('admin')->id();

        $logData = [
            'method_id' => $method->id,
            'method_name' => $method->name,
            'provider' => $method->provider,
            'mode' => $method->mode,
            'user_id' => $userId,
            'user_type' => 'admin',
            'action' => 'create',
            'timestamp' => now()->toISOString(),
        ];

        // Add request-specific data if available
        if (function_exists('request')) {
            $logData['ip_address'] = request()->ip();
            $logData['user_agent'] = request()->userAgent();
        }

        Log::channel('payment_methods')->info('Payment method created', $logData);
    }

    /**
     * Log payment method update
     */
    protected function logPaymentMethodUpdated($method, $oldData, $userId = null)
    {
        $userId = $userId ?? auth('admin')->id();

        $changes = $this->getChangesArray($method, $oldData);

        $logData = [
            'method_id' => $method->id,
            'method_name' => $method->name,
            'provider' => $method->provider,
            'changes' => $changes,
            'user_id' => $userId,
            'user_type' => 'admin',
            'action' => 'update',
            'timestamp' => now()->toISOString(),
        ];

        // Add request-specific data if available
        if (function_exists('request')) {
            $logData['ip_address'] = request()->ip();
            $logData['user_agent'] = request()->userAgent();
        }

        Log::channel('payment_methods')->info('Payment method updated', $logData);
    }

    /**
     * Log payment method deletion
     */
    protected function logPaymentMethodDeleted($method, $userId = null)
    {
        $userId = $userId ?? auth('admin')->id();

        $logData = [
            'method_id' => $method->id,
            'method_name' => $method->name,
            'provider' => $method->provider,
            'mode' => $method->mode,
            'user_id' => $userId,
            'user_type' => 'admin',
            'action' => 'delete',
            'timestamp' => now()->toISOString(),
        ];

        // Add request-specific data if available
        if (function_exists('request')) {
            $logData['ip_address'] = request()->ip();
            $logData['user_agent'] = request()->userAgent();
        }

        Log::channel('payment_methods')->warning('Payment method deleted', $logData);
    }

    /**
     * Log payment method access/view
     */
    protected function logPaymentMethodAccessed($method, $action = 'view', $userId = null)
    {
        $userId = $userId ?? auth('admin')->id();

        $logData = [
            'action' => $action, // 'view', 'list', etc.
            'user_id' => $userId,
            'user_type' => 'admin',
            'timestamp' => now()->toISOString(),
        ];

        // Add method-specific data if method is provided
        if ($method) {
            $logData['method_id'] = $method->id;
            $logData['method_name'] = $method->name;
            $logData['provider'] = $method->provider;
        }

        // Add request-specific data if available
        if (function_exists('request')) {
            $logData['ip_address'] = request()->ip();
            $logData['user_agent'] = request()->userAgent();
        }

        // Use a dedicated channel that writes only to file, never to stderr
        Log::channel('payment_methods')->info('Payment method accessed', $logData);
    }

    /**
     * Log payment method toggle active status
     */
    protected function logPaymentMethodToggled($method, $oldActive, $userId = null)
    {
        $userId = $userId ?? auth('admin')->id();

        $logData = [
            'method_id' => $method->id,
            'method_name' => $method->name,
            'provider' => $method->provider,
            'old_active' => $oldActive,
            'new_active' => $method->active,
            'user_id' => $userId,
            'user_type' => 'admin',
            'action' => 'toggle_active',
            'timestamp' => now()->toISOString(),
        ];

        // Add request-specific data if available
        if (function_exists('request')) {
            $logData['ip_address'] = request()->ip();
            $logData['user_agent'] = request()->userAgent();
        }

        Log::channel('payment_methods')->info('Payment method active status toggled', $logData);
    }

    /**
     * Get array of changes between old and new data
     */
    private function getChangesArray($method, $oldData)
    {
        $changes = [];

        foreach (['name', 'provider', 'mode', 'active'] as $field) {
            if (isset($oldData[$field]) && $oldData[$field] != $method->$field) {
                $changes[$field] = [
                    'from' => $oldData[$field],
                    'to' => $method->$field
                ];
            }
        }

        // Special handling for API keys (don't log actual values)
        if (isset($oldData['api_key']) && $oldData['api_key'] !== $method->getAttributes()['api_key']) {
            $changes['api_key'] = [
                'from' => '[ENCRYPTED]',
                'to' => '[ENCRYPTED]'
            ];
        }

        if (isset($oldData['secret_key']) && $oldData['secret_key'] !== $method->getAttributes()['secret_key']) {
            $changes['secret_key'] = [
                'from' => '[ENCRYPTED]',
                'to' => '[ENCRYPTED]'
            ];
        }

        return $changes;
    }
}