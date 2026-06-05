<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class PlanLimitExceededException extends Exception
{
    public function __construct(string $resource, int $limit)
    {
        parent::__construct("You have reached the {$resource} limit of {$limit} on your current plan.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], 403);
    }
}
