<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Impersonation Session Lifetime
    |--------------------------------------------------------------------------
    |
    | How long (in minutes) an active impersonation (god mode) session may
    | last before it is automatically torn down.
    |
    */

    'ttl' => env('IMPERSONATION_TTL', 60),

    /*
    |--------------------------------------------------------------------------
    | Handoff Token Lifetime
    |--------------------------------------------------------------------------
    |
    | How long (in seconds) the signed, single-use token handed from the
    | central admin app to the tenant entry route is considered valid.
    |
    */

    'token_ttl' => env('IMPERSONATION_TOKEN_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Read-Only Mode
    |--------------------------------------------------------------------------
    |
    | Whether an impersonating administrator is restricted to read-only
    | access inside the tenant. When true, any mutating request
    | (POST/PUT/PATCH/DELETE) initiated while impersonating is rejected.
    |
    */

    'read_only' => env('IMPERSONATION_READ_ONLY', true),

];
