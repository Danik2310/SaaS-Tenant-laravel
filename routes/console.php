<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| Closure-based console commands. Loaded by App\Console\Kernel::commands().
|
| Application commands live in `app/Console/Commands` (auto-loaded) and in each
| module's service provider; only throwaway, closure-based commands belong here.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
