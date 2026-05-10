<?php

declare(strict_types=1);

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;

class ActivityLogObserver
{
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
            $properties['old'] = $model->getOriginal();
            $properties['new'] = $model->getChanges();
        }

        $log = activity($logName)
            ->performedOn($model)
            ->withProperties($properties);

        $causer = auth('admin')->user() ?? auth()->user();

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
