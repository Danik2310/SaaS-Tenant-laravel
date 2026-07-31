<?php

namespace App\Plans\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlanRequest;
use App\Http\Requests\Admin\UpdatePlanRequest;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

/**
 * @group Plan Management
 *
 * APIs for managing subscription plans and their feature gates.
 */
class PlanController extends Controller
{
    /**
     * List all plans.
     *
     * Paginated list of plans with their feature gates.
     *
     * @authenticated
     */
    public function index()
    {
        $plans = Plan::with('featureGates')->paginate(5);

        return response()->json([
            'plans' => PlanResource::collection($plans->items()),
            'feature_definitions' => config('plan_features'),
            'meta' => [
                'current_page' => $plans->currentPage(),
                'last_page' => $plans->lastPage(),
                'per_page' => $plans->perPage(),
                'total' => $plans->total(),
            ],
        ]);
    }

    /**
     * Create a plan.
     *
     * @authenticated
     *
     * @bodyParam name string required Plan name.
     * @bodyParam slug string required Unique plan slug.
     * @bodyParam price number required Plan price.
     * @bodyParam features array optional Feature flag keys enabled for this plan.
     *
     * @response 201 {"success":true,"message":"Plan created successfully","plan":{"id":1,"name":"Pro"}}
     */
    public function store(StorePlanRequest $request)
    {
        $data = $request->validated();
        $features = $data['features'] ?? [];
        unset($data['features']);

        $plan = Plan::create($data);

        $this->syncFeatures($plan, $features);

        $plan->load('featureGates');

        Log::info('Plan created', ['plan_id' => $plan->id, 'plan_name' => $plan->name]);

        return response()->json(['message' => 'Plan created successfully', 'plan' => new PlanResource($plan)], 201);
    }

    /**
     * Get a single plan.
     *
     * @authenticated
     *
     * @urlParam id integer required The plan ID.
     */
    public function show(string $id)
    {
        $plan = Plan::with('featureGates')->findOrFail($id);

        return response()->json(['plan' => new PlanResource($plan)]);
    }

    /**
     * Update a plan.
     *
     * @authenticated
     *
     * @urlParam id integer required The plan ID.
     *
     * @response {"success":true,"message":"Plan updated successfully","plan":{"id":1,"name":"Pro"}}
     */
    public function update(UpdatePlanRequest $request, string $id)
    {
        $data = $request->validated();
        $features = $data['features'] ?? [];
        unset($data['features']);

        $plan = Plan::findOrFail($id);
        $plan->update($data);

        $this->syncFeatures($plan, $features);

        $plan->load('featureGates');

        Log::info('Plan updated', ['plan_id' => $plan->id, 'plan_name' => $plan->name]);

        return response()->json(['message' => 'Plan updated successfully', 'plan' => new PlanResource($plan)]);
    }

    /**
     * Replace the enabled feature gates of a plan with the given keys.
     *
     * Accepts an array of keys or a comma-separated string. Unknown keys are
     * silently dropped (the request validation already rejects them).
     */
    private function syncFeatures(Plan $plan, array|string|null $features): void
    {
        $keys = is_array($features) ? $features : array_filter(array_map('trim', explode(',', (string) $features)));
        $keys = array_values(array_unique(array_filter($keys)));
        $known = array_keys(config('plan_features'));

        $plan->featureGates()->where('is_enabled', true)->delete();

        foreach (array_intersect($keys, $known) as $featureKey) {
            PlanFeature::create([
                'plan_id' => $plan->id,
                'feature_key' => $featureKey,
                'is_enabled' => true,
            ]);
        }
    }

    /**
     * Delete a plan.
     *
     * @authenticated
     *
     * @urlParam id integer required The plan ID.
     *
     * @response 204 No content.
     */
    public function destroy(string $id)
    {
        $plan = Plan::findOrFail($id);

        $tenantCount = Tenant::where('plan_id', $plan->id)->count();
        if ($tenantCount > 0) {
            return response()->json([
                'message' => 'Cannot delete a plan assigned to '.$tenantCount.' tenant(s). Reassign tenants first.',
            ], 422);
        }

        $plan->featureGates()->delete();
        $plan->delete();

        Log::info('Plan deleted', ['plan_id' => $plan->id, 'plan_name' => $plan->name]);

        return response()->noContent();
    }
}
