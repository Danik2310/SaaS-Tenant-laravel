<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExportRequest;
use App\Jobs\RunExportJob;
use App\Services\ExportService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class ExportController extends Controller
{
    public function __construct(
        private ExportService $exportService,
    ) {}

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

    public function status(string $jobId)
    {
        $status = Cache::get("export:{$jobId}:status", 'unknown');
        $result = Cache::get("export:{$jobId}:result");
        $error = Cache::get("export:{$jobId}:error");

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
