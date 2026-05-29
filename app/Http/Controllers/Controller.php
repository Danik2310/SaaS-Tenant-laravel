<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Cache;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    protected function flushTenantCache(): void
    {
        try {
            Cache::tags(['tenant_'.tenant('id')])->flush();
        } catch (\BadMethodCallException) {
            // Cache driver doesn't support tags
        }
    }
}
