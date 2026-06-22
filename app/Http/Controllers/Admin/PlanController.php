<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlanRequest;
use App\Http\Requests\Admin\UpdatePlanRequest;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Support\Facades\Cache;
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
        $plans = Plan::with('featureGates')->paginate(25);

        return response()->json([
            'plans' => PlanResource::collection($plans->items()),
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
     * @bodyParam features string optional Comma-separated feature keys.
     *
     * @apiResource App\Http\Resources\PlanResource
     *
     * @apiResourceModel App\Models\Plan
     *
     * @response 201 {"message":"Plan created successfully","data":{...}}
     */
    public function store(StorePlanRequest $request)
    {
        $data = $request->validated();
        $features = isset($data['features']) ? array_map('trim', explode(',', $data['features'])) : [];
        unset($data['features']);

        $plan = Plan::create($data);

        foreach ($features as $featureKey) {
            if (! empty($featureKey)) {
                PlanFeature::create([
                    'plan_id' => $plan->id,
                    'feature_key' => $featureKey,
                    'is_enabled' => true,
                ]);
            }
        }

        $plan->load('featureGates');

        Cache::forget('admin_plans_list');
        Log::info('Plan created by user: '.auth('admin')->id());

        return response()->json(['plan' => new PlanResource($plan)], 201);
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
     */
    public function update(UpdatePlanRequest $request, string $id)
    {
        $data = $request->validated();
        $features = isset($data['features']) ? array_map('trim', explode(',', $data['features'])) : [];
        unset($data['features']);

        $plan = Plan::findOrFail($id);
        $plan->update($data);

        $plan->featureGates()->where('is_enabled', true)->delete();
        foreach ($features as $featureKey) {
            if (! empty($featureKey)) {
                PlanFeature::create([
                    'plan_id' => $plan->id,
                    'feature_key' => $featureKey,
                    'is_enabled' => true,
                ]);
            }
        }

        $plan->load('featureGates');

        Cache::forget('admin_plans_list');
        Log::info('Plan updated by user: '.auth('admin')->id());

        return response()->json(['plan' => new PlanResource($plan)]);
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
        $plan->delete();

        Cache::forget('admin_plans_list');
        Log::info('Plan deleted by user: '.auth('admin')->id());

        return response()->noContent();
    }
}
