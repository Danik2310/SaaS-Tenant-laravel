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
        ]);

        $names = $this->locations->cityNamesForScope(
            $validated['country'],
            $validated['state'] ?? null,
        );

        return response()->json(['data' => $names]);
    }
}
