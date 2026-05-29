<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('payment_methods')
            ->whereNotNull('api_key')
            ->orWhereNotNull('secret_key')
            ->orderBy('id')
            ->each(function (object $method) {
                $updates = [];

                if ($method->api_key !== null && ! Str::startsWith($method->api_key, 'eyJpdiI6')) {
                    $updates['api_key'] = Crypt::encryptString($method->api_key);
                }

                if ($method->secret_key !== null && ! Str::startsWith($method->secret_key, 'eyJpdiI6')) {
                    $updates['secret_key'] = Crypt::encryptString($method->secret_key);
                }

                if ($updates !== []) {
                    DB::table('payment_methods')
                        ->where('id', $method->id)
                        ->update($updates);
                }
            });
    }

    public function down(): void {}
};
