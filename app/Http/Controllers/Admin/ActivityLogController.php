<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListActivityLogsRequest;
use App\Models\AdminUser;
use Spatie\Activitylog\Models\Activity;

/**
 * @group Activity Logs
 *
 * APIs for viewing and filtering activity logs.
 */
class ActivityLogController extends Controller
{
    /**
     * List activity logs.
     *
     * Paginated list with optional filtering by log name, causer, search, or date range.
     *
     * @authenticated
     *
     * @queryParam log_name string Filter by log name. Example: staff
     * @queryParam causer_id integer Filter by causer user ID.
     * @queryParam search string Search in description, subject type, or causer type.
     * @queryParam date_from string Filter by start date (Y-m-d).
     * @queryParam date_to string Filter by end date (Y-m-d).
     */
    public function index(ListActivityLogsRequest $request)
    {
        $query = Activity::query();

        if ($logName = $request->query('log_name')) {
            $query->where('log_name', $logName);
        }

        if ($causerId = $request->query('causer_id')) {
            $query->where('causer_id', $causerId)
                ->where('causer_type', AdminUser::class);
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
            'data' => $items,
            'meta' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
            ],
        ]);
    }

    /**
     * Get a single activity log entry.
     *
     * @authenticated
     *
     * @urlParam id integer required The activity log ID.
     */
    public function show(string $id)
    {
        $activity = Activity::findOrFail($id);

        return response()->json([
            'data' => [
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

    /**
     * Get distinct log names.
     *
     * @authenticated
     */
    public function logNames()
    {
        $names = Activity::select('log_name')
            ->distinct()
            ->orderBy('log_name')
            ->pluck('log_name')
            ->filter()
            ->values();

        return response()->json(['data' => $names]);
    }

    /**
     * Get causer users.
     *
     * Paginated list of admin users who have performed activities.
     *
     * @authenticated
     */
    public function causers()
    {
        $users = AdminUser::select('id', 'name', 'email')
            ->orderBy('name')
            ->paginate(50);

        return response()->json([
            'data' => $users->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email]),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'total' => $users->total(),
            ],
        ]);
    }
}
