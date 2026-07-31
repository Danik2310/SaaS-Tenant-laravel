<?php

namespace App\Plans\Support;

use App\Models\FeatureFlag;

class FeatureFlagCatalog
{
    /**
     * Return the feature flag catalog keyed by flag key.
     *
     * The catalog is tiny (a handful of rows) and is read directly from the
     * database so that admin edits are reflected immediately on every request,
     * including tenant-scoped Inertia pages.
     *
     * @return array<string, array{label: string, description: string|null, is_locked: bool, is_active: bool, sort_order: int}>
     */
    public static function definitions(): array
    {
        return FeatureFlag::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (FeatureFlag $flag) => [
                $flag->key => [
                    'label' => $flag->label,
                    'description' => $flag->description,
                    'is_locked' => $flag->is_locked,
                    'is_active' => $flag->is_active,
                    'sort_order' => $flag->sort_order,
                ],
            ])
            ->toArray();
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * Keys of flags that are currently active (available for new selections).
     *
     * @return array<int, string>
     */
    public static function activeKeys(): array
    {
        return array_keys(array_filter(
            self::definitions(),
            fn (array $definition) => $definition['is_active']
        ));
    }
}
