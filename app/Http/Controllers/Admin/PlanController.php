<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PlanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $plans = Plan::all();
        return response()->json(['plans' => $plans]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:plans,slug',
            'price' => 'required|numeric|min:0',
            'max_users' => 'nullable|integer|min:1',
            'features' => 'nullable|string', // Frontend sends comma-separated
        ]);

        // Convert features to array if provided
        if ($validated['features']) {
            $validated['features'] = array_map('trim', explode(',', $validated['features']));
        }

        $plan = Plan::create($validated);

        Log::info("Plan created by user: " . auth('admin')->id());

        return response()->json(['plan' => $plan], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $plan = Plan::findOrFail($id);
        return response()->json(['plan' => $plan]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:plans,slug,' . $id,
            'price' => 'required|numeric|min:0',
            'max_users' => 'nullable|integer|min:1',
            'features' => 'nullable|string',
        ]);

        if ($validated['features']) {
            $validated['features'] = array_map('trim', explode(',', $validated['features']));
        }

        $plan = Plan::findOrFail($id);
        $plan->update($validated);

        Log::info("Plan updated by user: " . auth('admin')->id());

        return response()->json(['plan' => $plan]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $plan = Plan::findOrFail($id);
        $plan->delete();

        Log::info("Plan deleted by user: " . auth('admin')->id());

        return response()->json(['message' => 'Deleted successfully']);
    }
}
