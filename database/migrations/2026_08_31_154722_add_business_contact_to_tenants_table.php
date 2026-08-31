<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Business & contact details for the tenant.account owner.
     *
     * NOTE: These are stored as plaintext PII (as with the existing `email`
     * column). If GDPR compliance or data minimization is required, consider
     * encrypting these columns or moving them to a separate encrypted store.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $columns = [
                'company_name' => 255,
                'first_name' => 255,
                'last_name' => 255,
                'phone' => 50,
                'address_line1' => 255,
                'address_line2' => 255,
                'city' => 100,
                'state' => 100,
                'postal_code' => 20,
                'country' => 100,
            ];

            foreach ($columns as $column => $length) {
                if (! Schema::hasColumn('tenants', $column)) {
                    $table->string($column, $length)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $columns = [
                'company_name', 'first_name', 'last_name', 'phone',
                'address_line1', 'address_line2', 'city', 'state',
                'postal_code', 'country',
            ];

            $existing = collect(Schema::getColumnListing('tenants'))
                ->intersect($columns)
                ->values()
                ->all();

            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }
};
