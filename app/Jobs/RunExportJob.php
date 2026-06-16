<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\ExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RunExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    private string $entity;

    private string $format;

    private array $columns;

    private array $filters;

    private string $jobId;

    private ?string $tenantId;

    public function __construct(string $entity, string $format, array $columns, array $filters, ?string $tenantId = null)
    {
        $this->entity = $entity;
        $this->format = $format;
        $this->columns = $columns;
        $this->filters = $filters;
        $this->jobId = (string) Str::uuid();
        $this->tenantId = $tenantId;
    }

    public function handle(ExportService $exportService): void
    {
        $prefix = $this->tenantId ? "tenant:{$this->tenantId}:export" : 'export';
        Cache::put("{$prefix}:{$this->jobId}:status", 'processing', 3600);

        try {
            if ($this->tenantId) {
                tenancy()->initialize($this->tenantId);
            }

            $result = $exportService->export($this->entity, $this->format, $this->columns, $this->filters);

            Cache::put("{$prefix}:{$this->jobId}:result", $result, 3600);
            Cache::put("{$prefix}:{$this->jobId}:status", 'completed', 3600);

            Log::info('Export completed via job', [
                'job_id' => $this->jobId,
                'entity' => $this->entity,
                'format' => $this->format,
                'record_count' => $result['record_count'],
            ]);
        } catch (\Exception $e) {
            Cache::put("{$prefix}:{$this->jobId}:status", 'failed', 3600);
            Cache::put("{$prefix}:{$this->jobId}:error", $e->getMessage(), 3600);

            Log::error('Export job failed', [
                'job_id' => $this->jobId,
                'entity' => $this->entity,
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
            ]);
        } finally {
            if ($this->tenantId) {
                tenancy()->end();
            }
        }
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }
}
