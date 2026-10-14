<?php

declare(strict_types=1);

namespace App\Shared\Observers;

use Illuminate\Database\Eloquent\Model;

/**
 * Records create/update/delete/restore on every registered model to the Spatie
 * activity log.
 *
 * Flow:
 *  - Maps the model to a log channel (`resolveLogName()`).
 *  - Skips noisy updates (Category/Warehouse) and Order status-only no-ops.
 *  - Strips sensitive fields (passwords, keys) from the diff.
 *  - Resolves the causer from the `admin` guard only outside tenant context —
 *    the `admin_users` table does not exist on a tenant connection.
 */
class ActivityLogObserver
{
    private const array SENSITIVE_FIELDS = [
        'password',
        'remember_token',
        'api_key',
        'secret_key',
    ];

    private const array SKIP_UPDATE_MODELS = [
        'Category',
        'Warehouse',
    ];

    public function created(Model $model): void
    {
        $this->log('created', $model);
    }

    public function updated(Model $model): void
    {
        $label = class_basename($model);

        if (in_array($label, self::SKIP_UPDATE_MODELS, true)) {
            return;
        }

        if ($label === 'Order' && ! $model->wasChanged('status')) {
            return;
        }

        if ($model->wasChanged()) {
            $this->log('updated', $model);
        }
    }

    public function deleted(Model $model): void
    {
        if (method_exists($model, 'isForceDeleting') && $model->isForceDeleting()) {
            $this->log('force_deleted', $model);
        } else {
            $this->log('deleted', $model);
        }
    }

    public function restored(Model $model): void
    {
        $this->log('restored', $model);
    }

    private function log(string $event, Model $model): void
    {
        $logName = $this->resolveLogName($model);
        $label = class_basename($model);
        $identifier = $model->name ?? $model->id;

        $properties = [
            'event' => $event,
            'model' => $label,
            'model_id' => $model->id,
        ];

        if ($event === 'updated') {
            $properties['old'] = collect($model->getOriginal())->except(self::SENSITIVE_FIELDS)->toArray();
            $properties['new'] = collect($model->getChanges())->except(self::SENSITIVE_FIELDS)->toArray();
        }

        $log = activity($logName)
            ->performedOn($model)
            ->withProperties($properties);

        // Only resolve the central admin guard outside tenant context — inside a
        // tenant request `admin_users` does not exist on the tenant connection.
        $causer = (! tenancy()->initialized ? auth('admin')->user() : null) ?? auth()->user();

        if ($causer) {
            $log->causedBy($causer);
        }

        $log->log("{$label} {$event}: {$identifier}");
    }

    private function resolveLogName(Model $model): string
    {
        return match (class_basename($model)) {
            'Tenant' => 'tenant',
            'Plan' => 'plan',
            'AdminUser' => 'staff',
            'PaymentMethod' => 'payment_method',
            'Subscription' => 'subscription',
            'Role' => 'role',
            'Permission' => 'permission',
            'Domain' => 'domain',
            'GlobalSetting' => 'global_setting',
            'Setting' => 'setting',
            'User' => 'user',
            'Customer' => 'customer',
            'Product' => 'product',
            'Order' => 'order',
            'Payment' => 'payment',
            'Category' => 'category',
            'Warehouse' => 'warehouse',
            default => 'system',
        };
    }
}
