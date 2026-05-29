<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlanRequest;
use App\Http\Requests\Admin\UpdatePlanRequest;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Support\Facades\Log;

class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::with('featureGates')->paginate(25);

        return response()->json([
            'plans' => PlanResource::collection($plans->items()),
            'total' => $plans->total(),
            'meta' => [
                'current_page' => $plans->currentPage(),
                'last_page' => $plans->lastPage(),
                'per_page' => $plans->perPage(),
                'total' => $plans->total(),
            ],
        ]);
    }

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

        Log::info('Plan created by user: '.auth('admin')->id());

        return response()->json(['plan' => new PlanResource($plan)], 201);
    }

    public function show(string $id)
    {
        $plan = Plan::with('featureGates')->findOrFail($id);

        return response()->json(['plan' => new PlanResource($plan)]);
    }

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

        Log::info('Plan updated by user: '.auth('admin')->id());

        return response()->json(['plan' => new PlanResource($plan)]);
    }

    public function destroy(string $id)
    {
        $plan = Plan::findOrFail($id);
        $plan->delete();

        Log::info('Plan deleted by user: '.auth('admin')->id());

        return response()->noContent();
    }
}
