<?php

declare(strict_types=1);

namespace App\Tenants\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Shared\Contracts\LocationCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegistrationLocationController extends Controller
{
    public function __construct(
        private LocationCatalog $locations,
    ) {}

    public function cities(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country' => 'required|string|max:2',
            'state' => 'nullable|string|max:100',
            'term' => 'nullable|string|max:100',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $names = $this->locations->cityNames(
            $validated['country'],
            $validated['state'] ?? null,
            $validated['term'] ?? '',
            (int) ($validated['limit'] ?? 20),
        );

        return response()->json(['data' => $names]);
    }
}
