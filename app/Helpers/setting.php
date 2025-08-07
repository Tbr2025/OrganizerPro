<?php

use Illuminate\Support\Facades\DB;

/**
 * Get a setting from the global or organization settings table.
 *
 * @param string $key
 * @param int|null $organizationId (optional)
 * @return string|null
 */
function getSetting(string $key, ?int $organizationId = null): ?string
{
    if ($organizationId) {
        $orgSetting = DB::table('organization_settings')
            ->where('organization_id', $organizationId)
            ->where('option_name', $key)
            ->value('option_value');

        if ($orgSetting !== null) {
            return $orgSetting;
        }
    }

    return DB::table('settings')
        ->where('option_name', $key)
        ->value('option_value');
}
