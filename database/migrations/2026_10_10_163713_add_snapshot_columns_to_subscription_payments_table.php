<?php

use App\Models\GlobalSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot the plan billed for each payment so the tenant-facing history
     * can render plan / price / currency without joins, and those values stay
     * frozen even when the tenant changes plan afterwards.
     */
    public function up(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->string('plan_name')->nullable()->after('status');
            $table->decimal('plan_price', 12, 2)->nullable()->after('plan_name');
            $table->char('currency', 3)->nullable()->after('plan_price');
        });

        $this->backfillPlanSnapshots();
    }

    public function down(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropColumn(['plan_name', 'plan_price', 'currency']);
        });
    }

    /**
     * Best-effort backfill for rows recorded before the snapshot columns
     * existed. Uses the plan the subscription was linked to at deploy time;
     * any rows created from now on are snapshotted by the model itself.
     */
    private function backfillPlanSnapshots(): void
    {
        if (! Schema::hasTable('subscriptions') || ! Schema::hasTable('plans')) {
            return;
        }

        $payments = DB::table('subscription_payments as p')
            ->join('subscriptions as s', 's.id', '=', 'p.subscription_id')
            ->join('plans as pl', 'pl.id', '=', 's.plan_id')
            ->whereNull('p.plan_name')
            ->select(['p.id', 'pl.name as plan_name', 'pl.price as plan_price'])
            ->get();

        if ($payments->isEmpty()) {
            return;
        }

        $currency = strtoupper((string) GlobalSetting::get('currency', 'USD'));

        foreach ($payments as $payment) {
            DB::table('subscription_payments')
                ->where('id', $payment->id)
                ->update([
                    'plan_name' => $payment->plan_name,
                    'plan_price' => $payment->plan_price,
                    'currency' => $currency,
                ]);
        }
    }
};
