<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use Exception;

class PlanLimitExceededException extends Exception
{
    public function __construct(string $resource, int $limit)
    {
        parent::__construct("You have reached the {$resource} limit of {$limit} on your current plan.");
    }
}
