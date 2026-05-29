<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * WARNING: This migration provides a proper down() for the encryption
     * operation in 2026_05_26_230926_encrypt_existing_payment_method_keys.
     *
     * Rolling back encryption is a coordinated operation:
     * 1. Ensure all code that reads payment method keys supports plaintext
     * 2. Run the down() of this migration to decrypt the keys
     * 3. Roll back the code from before 2026_05_26_230926 if needed
     *
     * Running up() re-encrypts any plaintext keys (idempotent safety net).
     */
    public function up(): void
    {
        DB::table('payment_methods')
            ->whereNotNull('api_key')
            ->orWhereNotNull('secret_key')
            ->orderBy('id')
            ->each(function (object $method) {
                $updates = [];

                if ($method->api_key !== null && ! str_starts_with($method->api_key, 'eyJpdiI6')) {
                    $updates['api_key'] = Crypt::encryptString($method->api_key);
                }

                if ($method->secret_key !== null && ! str_starts_with($method->secret_key, 'eyJpdiI6')) {
                    $updates['secret_key'] = Crypt::encryptString($method->secret_key);
                }

                if ($updates !== []) {
                    DB::table('payment_methods')
                        ->where('id', $method->id)
                        ->update($updates);
                }
            });
    }

    public function down(): void
    {
        DB::table('payment_methods')
            ->whereNotNull('api_key')
            ->orWhereNotNull('secret_key')
            ->orderBy('id')
            ->each(function (object $method) {
                $updates = [];

                try {
                    if ($method->api_key !== null && str_starts_with($method->api_key, 'eyJpdiI6')) {
                        $updates['api_key'] = Crypt::decryptString($method->api_key);
                    }

                    if ($method->secret_key !== null && str_starts_with($method->secret_key, 'eyJpdiI6')) {
                        $updates['secret_key'] = Crypt::decryptString($method->secret_key);
                    }

                    if ($updates !== []) {
                        DB::table('payment_methods')
                            ->where('id', $method->id)
                            ->update($updates);
                    }
                } catch (Exception $e) {
                    // Skip rows that aren't encrypted or use a different cipher
                }
            });
    }
};
