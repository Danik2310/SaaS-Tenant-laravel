<?php

namespace App\Plans\Support;

use Illuminate\Support\Facades\DB;

class PlanFeatureNormalizer
{
    /**
     * Normalize existing plan_features rows to the canonical feature flag registry.
     *
     * - Expands the legacy 'all' sentinel into every canonical feature key.
     * - Maps the legacy 'basic' flag to 'basic_reports'.
     * - Deletes any remaining rows whose key is not in the registry.
     */
    public static function normalize(): void
    {
        $known = array_keys(config('plan_features'));
        $db = DB::connection('mysql_central');

        $db->table('plan_features')->where('feature_key', 'all')->chunkById(200, function ($rows) use ($known, $db) {
            foreach ($rows as $row) {
                foreach ($known as $key) {
                    $db->table('plan_features')->updateOrInsert(
                        ['plan_id' => $row->plan_id, 'feature_key' => $key],
                        ['is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]
                    );
                }

                $db->table('plan_features')->where('id', $row->id)->delete();
            }
        });

        // Map legacy 'basic' rows to 'basic_reports', skipping plans that already
        // have a 'basic_reports' row (those simply drop the duplicate 'basic').
        $db->table('plan_features as legacy')
            ->join('plan_features as existing', function ($join) {
                $join->on('existing.plan_id', '=', 'legacy.plan_id')
                    ->where('existing.feature_key', '=', 'basic_reports');
            })
            ->where('legacy.feature_key', '=', 'basic')
            ->delete();

        $db->table('plan_features')->where('feature_key', 'basic')->update(['feature_key' => 'basic_reports']);

        $db->table('plan_features')->whereNotIn('feature_key', $known)->delete();
    }
}
