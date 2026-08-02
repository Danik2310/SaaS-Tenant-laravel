<?php

namespace App\Plans\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFeatureFlagRequest;
use App\Http\Requests\Admin\UpdateFeatureFlagRequest;
use App\Models\FeatureFlag;
use App\Models\PlanFeature;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @group Feature Flag Management
 *
 * APIs for managing the feature flag catalog that plans reference.
 */
class FeatureFlagController extends Controller
{
    /**
     * List all feature flags.
     *
     * Paginated list of feature flags with their labels and settings.
     *
     * @authenticated
     */
    public function index()
    {
        $perPage = min((int) request('per_page', 10), 100);

        $query = FeatureFlag::query();

        $search = trim((string) request('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('key', 'like', "%{$search}%")
                    ->orWhere('label', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $sortField = in_array(request('sort'), ['key', 'label', 'sort_order', 'is_active', 'is_locked'], true)
            ? request('sort')
            : 'sort_order';
        $order = request('order') === 'desc' ? 'desc' : 'asc';

        $flags = $query->orderBy($sortField, $order)->paginate($perPage);

        return response()->json([
            'flags' => $flags->items(),
            'meta' => [
                'current_page' => $flags->currentPage(),
                'last_page' => $flags->lastPage(),
                'per_page' => $flags->perPage(),
                'total' => $flags->total(),
            ],
        ]);
    }

    /**
     * Create a feature flag.
     *
     * @authenticated
     *
     * @bodyParam key string required Unique flag key (lowercase letters, numbers, underscores).
     * @bodyParam label string required Display label.
     * @bodyParam description string optional Short description shown in the picker.
     * @bodyParam is_active boolean optional Whether the flag is available for new selections. Defaults to true.
     * @bodyParam sort_order integer optional Ordering in the picker. Defaults to 0.
     *
     * @response 201 {"message":"Feature flag created","flag":{"id":9,"key":"cargo_tracking","label":"Cargo Tracking"}}
     */
    public function store(StoreFeatureFlagRequest $request)
    {
        $data = $request->validated();

        if (! isset($data['sort_order'])) {
            $data['sort_order'] = (int) FeatureFlag::max('sort_order') + 1;
        }

        $flag = FeatureFlag::create($data);

        Log::info('Feature flag created', ['flag_id' => $flag->id, 'flag_key' => $flag->key]);

        return response()->json(['message' => 'Feature flag created', 'flag' => $flag], 201);
    }

    /**
     * Get a single feature flag.
     *
     * @authenticated
     *
     * @urlParam id integer required The feature flag ID.
     */
    public function show(string $id)
    {
        return response()->json(['flag' => FeatureFlag::findOrFail($id)]);
    }

    /**
     * Update a feature flag.
     *
     * @authenticated
     *
     * @urlParam id integer required The feature flag ID.
     *
     * Renaming a flag cascades to the feature gates of every plan using it.
     * Locked (system) flags cannot be renamed.
     */
    public function update(UpdateFeatureFlagRequest $request, string $id)
    {
        $flag = FeatureFlag::findOrFail($id);
        $data = $request->validated();

        if ($flag->is_locked && $data['key'] !== $flag->key) {
            return response()->json([
                'message' => 'This flag is locked and its key cannot be changed.',
            ], 422);
        }

        $oldKey = $flag->key;

        $flag->update($data);

        if ($oldKey !== $data['key']) {
            DB::connection('mysql_central')->table('plan_features')
                ->where('feature_key', $oldKey)
                ->update(['feature_key' => $data['key']]);
        }

        Log::info('Feature flag updated', ['flag_id' => $flag->id, 'flag_key' => $flag->key]);

        return response()->json(['message' => 'Feature flag updated', 'flag' => $flag]);
    }

    /**
     * Delete a feature flag.
     *
     * @authenticated
     *
     * @urlParam id integer required The feature flag ID.
     *
     * Locked (system) flags and flags referenced by plans cannot be deleted.
     *
     * @response 204 No content.
     */
    public function destroy(string $id)
    {
        $flag = FeatureFlag::findOrFail($id);

        if ($flag->is_locked) {
            return response()->json([
                'message' => 'This flag is locked and cannot be deleted.',
            ], 422);
        }

        $usageCount = PlanFeature::where('feature_key', $flag->key)->count();

        if ($usageCount > 0) {
            return response()->json([
                'message' => "This flag is assigned to {$usageCount} plan(s). Remove it from those plans first.",
            ], 422);
        }

        $flag->delete();

        Log::info('Feature flag deleted', ['flag_id' => $flag->id, 'flag_key' => $flag->key]);

        return response()->noContent();
    }
}
