<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdminUser;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Spatie\Activitylog\Models\Activity;

class ExportService
{
    private const EXPIRY_HOURS = 24;

    public function export(string $entity, string $format, array $columns, array $filters = []): array
    {
        $data = match ($entity) {
            'tenants' => $this->getTenantsData($filters, $columns),
            'subscriptions' => $this->getSubscriptionsData($filters, $columns),
            'staff' => $this->getStaffData($filters, $columns),
            'plans' => $this->getPlansData($filters, $columns),
            'activity-logs' => $this->getActivityLogsData($filters, $columns),
            default => throw new \InvalidArgumentException("Unknown entity: {$entity}"),
        };

        $filename = sprintf('%s_%s.%s', $entity, now()->format('Ymd_His'), $format);
        $path = 'exports/'.$filename;

        if ($format === 'csv') {
            $this->writeCsv($path, $data['headers'], $data['rows']);
        } else {
            $this->writeXlsx($path, $data['headers'], $data['rows']);
        }

        return [
            'filename' => $filename,
            'path' => $path,
            'record_count' => count($data['rows']),
            'expires_at' => now()->addHours(self::EXPIRY_HOURS),
        ];
    }

    public function getDownloadUrl(string $path): ?string
    {
        if (! Storage::exists($path)) {
            return null;
        }

        return Storage::url($path);
    }

    private function writeCsv(string $path, array $headers, array $rows): void
    {
        $writer = new CsvWriter;
        $writer->openToFile(storage_path('app/'.$path));
        $writer->addRow($headers);
        foreach ($rows as $row) {
            $writer->addRow($row);
        }
        $writer->close();
    }

    private function writeXlsx(string $path, array $headers, array $rows): void
    {
        $writer = new XlsxWriter;
        $writer->openToFile(storage_path('app/'.$path));
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Export');
        $writer->addRow($headers);
        foreach ($rows as $row) {
            $writer->addRow($row);
        }
        $writer->close();
    }

    private function getTenantsData(array $filters, array $columns): array
    {
        $query = Tenant::with('domains', 'plan');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['plan_id'])) {
            $query->where('plan_id', $filters['plan_id']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $tenants = $query->get();
        $headers = $this->resolveHeaders($columns, ['ID', 'Name', 'Email', 'Domain', 'Status', 'Plan', 'Created']);
        $allColumns = ['id', 'name', 'email', 'domain', 'status', 'plan_name', 'created_at'];

        $rows = $tenants->map(function ($tenant) use ($columns, $allColumns) {
            $selected = $this->pickColumns($columns, $allColumns, [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'email' => $tenant->email,
                'domain' => $tenant->domains->first()?->domain ?? '',
                'status' => $tenant->status,
                'plan_name' => $tenant->plan?->name ?? '',
                'created_at' => $tenant->created_at->format('Y-m-d H:i:s'),
            ]);

            return array_values($selected);
        })->toArray();

        return ['headers' => $headers, 'rows' => $rows];
    }

    private function getSubscriptionsData(array $filters, array $columns): array
    {
        $query = Subscription::with('tenant', 'plan');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['plan_id'])) {
            $query->where('plan_id', $filters['plan_id']);
        }
        if (! empty($filters['search'])) {
            $query->whereHas('tenant', fn ($q) => $q->where('name', 'like', "%{$filters['search']}%")
                ->orWhere('email', 'like', "%{$filters['search']}%"));
        }

        $subscriptions = $query->get();
        $headers = $this->resolveHeaders($columns, ['ID', 'Tenant', 'Plan', 'Status', 'Start', 'End', 'Created']);
        $allColumns = ['id', 'tenant_name', 'plan_name', 'status', 'starts_at', 'ends_at', 'created_at'];

        $rows = $subscriptions->map(function ($sub) use ($columns, $allColumns) {
            $selected = $this->pickColumns($columns, $allColumns, [
                'id' => $sub->id,
                'tenant_name' => $sub->tenant?->name ?? '',
                'plan_name' => $sub->plan?->name ?? '',
                'status' => $sub->status,
                'starts_at' => $sub->starts_at?->format('Y-m-d') ?? '',
                'ends_at' => $sub->ends_at?->format('Y-m-d') ?? '',
                'created_at' => $sub->created_at->format('Y-m-d H:i:s'),
            ]);

            return array_values($selected);
        })->toArray();

        return ['headers' => $headers, 'rows' => $rows];
    }

    private function getStaffData(array $filters, array $columns): array
    {
        $query = AdminUser::query();

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $staff = $query->get();
        $headers = $this->resolveHeaders($columns, ['ID', 'Name', 'Email', 'Active', 'Created']);
        $allColumns = ['id', 'name', 'email', 'is_active', 'created_at'];

        $rows = $staff->map(function ($user) use ($columns, $allColumns) {
            $selected = $this->pickColumns($columns, $allColumns, [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active ? 'Yes' : 'No',
                'created_at' => $user->created_at->format('Y-m-d H:i:s'),
            ]);

            return array_values($selected);
        })->toArray();

        return ['headers' => $headers, 'rows' => $rows];
    }

    private function getPlansData(array $filters, array $columns): array
    {
        $query = Plan::query();

        $plans = $query->get();
        $headers = $this->resolveHeaders($columns, ['ID', 'Name', 'Slug', 'Price', 'Max Users', 'Created']);
        $allColumns = ['id', 'name', 'slug', 'price', 'max_users', 'created_at'];

        $rows = $plans->map(function ($plan) use ($columns, $allColumns) {
            $selected = $this->pickColumns($columns, $allColumns, [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'price' => $plan->price,
                'max_users' => $plan->max_users ?? 'Unlimited',
                'created_at' => $plan->created_at->format('Y-m-d H:i:s'),
            ]);

            return array_values($selected);
        })->toArray();

        return ['headers' => $headers, 'rows' => $rows];
    }

    private function getActivityLogsData(array $filters, array $columns): array
    {
        $query = Activity::query();

        if (! empty($filters['log_name'])) {
            $query->where('log_name', $filters['log_name']);
        }
        if (! empty($filters['causer_id'])) {
            $query->where('causer_id', $filters['causer_id']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
        if (! empty($filters['search'])) {
            $query->where('description', 'like', "%{$filters['search']}%");
        }

        $activities = $query->latest()->limit(5000)->get();
        $headers = $this->resolveHeaders($columns, ['ID', 'Description', 'Log', 'Causer', 'Created']);
        $allColumns = ['id', 'description', 'log_name', 'causer', 'created_at'];

        $rows = $activities->map(function ($log) use ($columns, $allColumns) {
            $selected = $this->pickColumns($columns, $allColumns, [
                'id' => $log->id,
                'description' => $log->description,
                'log_name' => $log->log_name,
                'causer' => $log->causer?->name ?? 'System',
                'created_at' => $log->created_at->format('Y-m-d H:i:s'),
            ]);

            return array_values($selected);
        })->toArray();

        return ['headers' => $headers, 'rows' => $rows];
    }

    private function resolveHeaders(array $requested, array $defaults): array
    {
        if (empty($requested)) {
            return $defaults;
        }

        return $requested;
    }

    private function pickColumns(array $requested, array $allColumns, array $values): array
    {
        if (empty($requested)) {
            return $values;
        }
        $result = [];
        foreach ($requested as $col) {
            $index = array_search($col, $allColumns);
            if ($index !== false) {
                $key = $allColumns[$index];
                $result[$key] = $values[$key] ?? '';
            }
        }

        return $result;
    }

    public function cleanupExpired(): void
    {
        $files = Storage::files('exports');
        $now = now();
        foreach ($files as $file) {
            $lastModified = Storage::lastModified($file);
            if ($now->timestamp - $lastModified > self::EXPIRY_HOURS * 3600) {
                Storage::delete($file);
            }
        }
    }
}
