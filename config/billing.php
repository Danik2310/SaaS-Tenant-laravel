<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Billing Configuration
    |--------------------------------------------------------------------------
    |
    | Default payment gateway used when a tenant has no explicit gateway
    | configured. Override with the BILLING_DEFAULT_GATEWAY env variable.
    |
    */

    'default_gateway' => env('BILLING_DEFAULT_GATEWAY', 'stripe'),

];
