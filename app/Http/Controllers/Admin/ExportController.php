<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Contracts\ExportServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExportRequest;
use App\Jobs\RunExportJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * @group Data Export
 *
 * APIs for exporting data entities (sync and async).
 */
class ExportController extends Controller
{
    public function __construct(
        private ExportServiceInterface $exportService,
    ) {}

    /**
     * Export data.
     *
     * Supports sync and async export of various data entities.
     *
     * @authenticated
     *
     * @urlParam entity string required The entity type (tenants, staff, etc).
     *
     * @bodyParam format string Export format (csv, xlsx). Example: csv
     * @bodyParam async boolean Whether to queue the export. Example: false
     *
     * @response 202 scenario="Async" {"data":{"job_id":"...","status":"queued"}}
     */
    public function export(ExportRequest $request, string $entity)
    {
        $validated = $request->validated();

        $format = $validated['format'] ?? 'csv';
        $columns = $validated['columns'] ?? [];
        $filters = $validated['filters'] ?? [];

        $async = $validated['async'] ?? false;

        if ($async) {
            $job = new RunExportJob($entity, $format, $columns, $filters);
            dispatch($job);

            activity('export')
                ->causedBy(auth('admin')->user())
                ->withProperties([
                    'entity' => $entity,
                    'format' => $format,
                    'job_id' => $job->getJobId(),
                ])
                ->log("Queued export of {$entity} as {$format}");

            return response()->json([
                'data' => [
                    'job_id' => $job->getJobId(),
                    'entity' => $entity,
                    'format' => $format,
                    'status' => 'queued',
                ],
                'message' => 'Export queued successfully. Check status endpoint for completion.',
            ]);
        }

        try {
            $result = $this->exportService->export($entity, $format, $columns, $filters);

            $recordCount = $result['record_count'];

            activity('export')
                ->causedBy(auth('admin')->user())
                ->withProperties([
                    'entity' => $entity,
                    'format' => $format,
                    'record_count' => $recordCount,
                ])
                ->log("Exported {$entity} as {$format} ({$recordCount} records)");

            return response()->json([
                'data' => [
                    'filename' => $result['filename'],
                    'entity' => $entity,
                    'format' => $format,
                    'record_count' => $recordCount,
                    'expires_at' => $result['expires_at'],
                ],
                'message' => "Export generated successfully with {$recordCount} records.",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Export failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download an export file.
     *
     * @authenticated
     *
     * @urlParam filename string required The export filename.
     */
    public function download(string $filename)
    {
        $allowedExtensions = ['csv', 'xlsx'];
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        if (! in_array($extension, $allowedExtensions, true)) {
            return response()->json(['message' => 'Invalid file type.'], 422);
        }

        $safeFilename = basename($filename);
        $path = 'exports/'.$safeFilename;

        if (! Storage::disk('local')->exists($path)) {
            return response()->json(['message' => 'Export file not found or expired.'], 404);
        }

        $mime = $extension === 'csv' ? 'text/csv' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        activity('export')
            ->causedBy(auth('admin')->user())
            ->withProperties(['filename' => $safeFilename])
            ->log("Downloaded export {$safeFilename}");

        return response()->download(Storage::disk('local')->path($path), $safeFilename, [
            'Content-Type' => $mime,
        ]);
    }

    /**
     * Check export job status.
     *
     * @authenticated
     *
     * @urlParam jobId string required The export job ID.
     */
    public function status(string $jobId, Request $request)
    {
        $tenantId = $request->query('tenant_id');
        $prefix = $tenantId ? "tenant:{$tenantId}:export" : 'export';
        $status = Cache::store('global')->get("{$prefix}:{$jobId}:status", 'unknown');
        $result = Cache::store('global')->get("{$prefix}:{$jobId}:result");
        $error = Cache::store('global')->get("{$prefix}:{$jobId}:error");

        $response = [
            'data' => [
                'job_id' => $jobId,
                'status' => $status,
            ],
        ];

        if ($result) {
            $response['data']['filename'] = $result['filename'];
            $response['data']['record_count'] = $result['record_count'];
        }

        if ($error) {
            $response['data']['error'] = $error;
        }

        return response()->json($response);
    }
}
