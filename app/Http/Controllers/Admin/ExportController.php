<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ExportService;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function __construct(
        private ExportService $exportService,
    ) {}

    public function export(Request $request, string $entity)
    {
        $validEntities = ['tenants', 'subscriptions', 'staff', 'plans', 'activity-logs'];

        if (! in_array($entity, $validEntities, true)) {
            return response()->json([
                'message' => 'Invalid entity. Supported: '.implode(', ', $validEntities),
            ], 422);
        }

        $validated = $request->validate([
            'format' => ['sometimes', 'string', 'in:csv,xlsx'],
            'columns' => ['sometimes', 'array'],
            'columns.*' => ['string'],
            'filters' => ['sometimes', 'array'],
            'filters.status' => ['sometimes', 'string'],
            'filters.plan_id' => ['sometimes', 'integer'],
            'filters.date_from' => ['sometimes', 'date'],
            'filters.date_to' => ['sometimes', 'date'],
            'filters.search' => ['sometimes', 'string'],
            'filters.log_name' => ['sometimes', 'string'],
            'filters.causer_id' => ['sometimes', 'integer'],
            'filters.is_active' => ['sometimes', 'boolean'],
        ]);

        $format = $validated['format'] ?? 'csv';
        $columns = $validated['columns'] ?? [];
        $filters = $validated['filters'] ?? [];

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
        $path = storage_path('app/exports/'.$safeFilename);

        if (! file_exists($path)) {
            return response()->json(['message' => 'Export file not found or expired.'], 404);
        }

        $mime = $extension === 'csv' ? 'text/csv' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        activity('export')
            ->causedBy(auth('admin')->user())
            ->withProperties(['filename' => $safeFilename])
            ->log("Downloaded export {$safeFilename}");

        return response()->download($path, $safeFilename, [
            'Content-Type' => $mime,
        ]);
    }

    public function status(string $jobId)
    {
        return response()->json([
            'data' => [
                'job_id' => $jobId,
                'status' => 'completed',
            ],
        ]);
    }
}
