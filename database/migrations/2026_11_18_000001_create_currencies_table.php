<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('currencies')) {
            Schema::create('currencies', function (Blueprint $table) {
                $table->char('code', 3)->primary();
                $table->string('name');
                $table->string('symbol', 32);
                $table->unsignedTinyInteger('decimal_places')->default(2);
                $table->string('region', 32)->nullable()->index();
                $table->boolean('popular')->default(false)->index();
                $table->boolean('active')->default(true)->index();
                $table->boolean('allow_tenant')->default(true)->index();
                $table->boolean('allow_public')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0)->index();
                $table->timestamps();
            });

            return;
        }

        // Akaunting Money publishes its own `currencies` table. Reuse it instead
        // of replacing it, adding only the administrator-controlled columns.
        Schema::table('currencies', function (Blueprint $table) {
            if (Schema::hasColumn('currencies', 'country_id')) {
                $table->unsignedBigInteger('country_id')->nullable()->change();
            }

            if (Schema::hasColumn('currencies', 'symbol_native')) {
                $table->string('symbol_native')->nullable()->change();
            }

            if (! Schema::hasColumn('currencies', 'decimal_places')) {
                $table->unsignedTinyInteger('decimal_places')->default(2);
            }

            if (! Schema::hasColumn('currencies', 'region')) {
                $table->string('region', 32)->nullable()->index();
            }

            if (! Schema::hasColumn('currencies', 'popular')) {
                $table->boolean('popular')->default(false)->index();
            }

            if (! Schema::hasColumn('currencies', 'active')) {
                $table->boolean('active')->default(true)->index();
            }

            if (! Schema::hasColumn('currencies', 'allow_tenant')) {
                $table->boolean('allow_tenant')->default(true)->index();
            }

            if (! Schema::hasColumn('currencies', 'allow_public')) {
                $table->boolean('allow_public')->default(true)->index();
            }

            if (! Schema::hasColumn('currencies', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->index();
            }

            if (! Schema::hasColumn('currencies', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }

            if (! Schema::hasColumn('currencies', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }

            if (! $this->hasUniqueCodeIndex()) {
                $table->unique('code', 'currencies_code_unique');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('currencies')) {
            return;
        }

        if (! Schema::hasColumn('currencies', 'country_id')) {
            Schema::drop('currencies');

            return;
        }

        Schema::table('currencies', function (Blueprint $table) {
            if ($this->hasUniqueCodeIndex()) {
                $table->dropUnique('currencies_code_unique');
            }

            foreach (['decimal_places', 'region', 'popular', 'active', 'allow_tenant', 'allow_public', 'sort_order', 'created_at', 'updated_at'] as $column) {
                if (Schema::hasColumn('currencies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function hasUniqueCodeIndex(): bool
    {
        foreach (DB::select('SHOW INDEX FROM currencies') as $index) {
            if ($index->Key_name === 'currencies_code_unique' && (int) $index->Non_unique === 0) {
                return true;
            }
        }

        return false;
    }
};
