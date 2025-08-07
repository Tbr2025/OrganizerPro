<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;

class SettingService
{
    public array $excluded_settings = [];

    public function __construct()
    {
        $this->excluded_settings = ld_apply_filters('excluded_setting_keys', [
            '_token',
        ]);
    }

    public function addSetting(string $optionName, mixed $optionValue, bool $autoload = false): ?Setting
    {
        if (in_array($optionName, $this->excluded_settings)) {
            return null;
        }

        return Setting::updateOrCreate(
            ['option_name' => $optionName],
            ['option_value' => $optionValue ?? '', 'autoload' => $autoload]
        );
    }

    public function updateSetting(string $optionName, mixed $optionValue, ?bool $autoload = null): bool
    {
        if (in_array($optionName, $this->excluded_settings)) {
            return false;
        }

        $setting = Setting::where('option_name', $optionName)->first();

        if ($setting) {
            $setting->update([
                'option_value' => $optionValue,
                'autoload' => $autoload ?? $setting->autoload,
            ]);

            return true;
        }

        return false;
    }

    public function deleteSetting(string $optionName): bool
    {
        return Setting::where('option_name', $optionName)->delete() > 0;
    }

    public function getSetting(string $optionName): mixed
    {
        return Setting::where('option_name', $optionName)->value('option_value');
    }

    public function getSettings(int|bool|null $autoload = true): array
    {
        if ($autoload === -1) {
            return Setting::all()->toArray();
        }

        return Setting::where('autoload', (bool) $autoload)->get()->toArray();
    }

    /**
     * Get all settings with optional group filter
     */
    public function getAllSettings(?string $search = null, $autoload = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = Setting::query();

        if ($search) {
            $query->where('option_name', 'like', "%{$search}%");
        }

        if ($autoload !== null) {
            $query->where('autoload', (bool) $autoload);
        }

        return $query->get();
    }

    /**
     * Update or create a setting
     */
    public function updateOrCreateSetting(string $key, mixed $value): Setting
    {
        return Setting::updateOrCreate(
            ['option_name' => $key],
            ['option_value' => $value ?? '']
        );
    }

    /**
     * Get setting by key
     */
    public function getSettingByKey(string $key): ?Setting
    {
        return Setting::where('option_name', $key)->first();
    }
}
