<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Local Dev Stack
|--------------------------------------------------------------------------
|
| When DEV_STACK=true and the application is running locally, `php artisan
| serve` also starts the Vite dev server and the Reverb WebSocket server in the
| same terminal. Keep this disabled everywhere else (CI, staging, production):
| `serve` must behave exactly as Laravel ships it.
|
| Ports mirror vite.config.js (locked by `strictPort`) and config/reverb.php so
| a service that is already listening is detected and never started twice.
|
*/

return [

    'stack' => (bool) env('DEV_STACK', false),

    'services' => [

        'vite' => [
            'port' => 5174,
            'command' => ['pnpm', 'run', 'dev'],
        ],

        'reverb' => [
            'port' => (int) env('REVERB_SERVER_PORT', 8080),
            'command' => ['artisan', 'reverb:start'],
        ],

    ],

];
