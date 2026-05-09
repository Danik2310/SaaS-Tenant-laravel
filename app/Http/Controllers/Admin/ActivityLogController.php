<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = Activity::query();

        if ($logName = $request->query('log_name')) {
            $query->where('log_name', $logName);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('subject_type', 'like', "%{$search}%")
                  ->orWhere('causer_type', 'like', "%{$search}%");
            });
        }

        if ($dateFrom = $request->query('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->query('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $activities = $query->orderBy('created_at', 'desc')->paginate(50);

        $items = $activities->getCollection()->map(function ($activity) {
            return [
                'id' => $activity->id,
                'log_name' => $activity->log_name,
                'description' => $activity->description,
                'subject_type' => $activity->subject_type ? class_basename($activity->subject_type) : null,
                'subject_id' => $activity->subject_id,
                'causer_type' => $activity->causer_type ? class_basename($activity->causer_type) : null,
                'causer_id' => $activity->causer_id,
                'properties' => $activity->properties,
                'created_at' => $activity->created_at?->format('Y-m-d H:i:s'),
                'created_at_diff' => $activity->created_at?->diffForHumans(),
            ];
        });

        return response()->json([
            'activities' => $items,
            'total' => $activities->total(),
            'meta' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
            ],
        ]);
    }

    public function show(string $id)
    {
        $activity = Activity::findOrFail($id);

        return response()->json([
            'activity' => [
                'id' => $activity->id,
                'log_name' => $activity->log_name,
                'description' => $activity->description,
                'subject_type' => $activity->subject_type,
                'subject_id' => $activity->subject_id,
                'causer_type' => $activity->causer_type,
                'causer_id' => $activity->causer_id,
                'properties' => $activity->properties,
                'created_at' => $activity->created_at?->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    public function logNames()
    {
        $names = Activity::select('log_name')
            ->distinct()
            ->orderBy('log_name')
            ->pluck('log_name')
            ->filter()
            ->values();

        return response()->json(['log_names' => $names]);
    }
}
