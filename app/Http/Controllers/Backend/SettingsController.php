<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend;

use App\Enums\ActionType;
use App\Http\Controllers\Controller;
use App\Services\CacheService;
use App\Services\EnvWriter;
use App\Services\SettingService;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingService $settingService,
        private readonly EnvWriter $envWriter,
        private readonly CacheService $cacheService
    ) {
        // The cacheService is used in the EnvWriter for cache clearing operations
    }

    public function index($tab = null): Renderable
    {
        $this->checkAuthorization(Auth::user(), ['settings.edit']);

        $tab = $tab ?? request()->input('tab', 'general');

        return view('backend.pages.settings.index', compact('tab'))
            ->with([
                'breadcrumbs' => [
                    'title' => __('Settings'),
                ],
            ]);
    }

    public function store(Request $request)
    {
        // Restrict specific fields in demo mode.
        if (config('app.demo_mode', false)) {
            $restrictedFields = ld_apply_filters('settings_restricted_fields', ['app_name', 'google_analytics_script']);
            $fields = $request->except($restrictedFields);
        } else {
            $fields = $request->all();
        }

        $this->checkAuthorization(Auth::user(), ['settings.edit']);

        /*
         * Secrets never reach the generic loop below.
         *
         * That loop writes every posted field into the settings table as plain text and then
         * hands the whole array to the action log. An API key must do neither, so it is pulled
         * out here, stored encrypted by its own service, and removed from $fields — which also
         * keeps it out of the log entry.
         */
        if (Auth::user()?->hasRole('Superadmin') && $request->filled('ai_provider')) {
            $ai = app(\App\Services\Blog\AiSettings::class);
            $provider = (string) $request->input('ai_provider');

            $ai->storeApiKey($provider, $request->input('ai_key_' . $provider));
            $ai->storeEndpoint(
                $provider,
                $request->input('ai_base_url_' . $provider),
                $request->input('ai_model_' . $provider)
            );
        }

        // Every posted key field goes, whichever provider it belongs to.
        foreach (array_keys($fields) as $field) {
            if (str_starts_with((string) $field, \App\Services\Blog\AiSettings::SECRET_FIELD_PREFIX)) {
                unset($fields[$field]);
            }
        }

        $uploadPath = 'uploads/settings';

        foreach ($fields as $fieldName => $fieldValue) {
            if ($request->hasFile($fieldName)) {
                deleteImageFromPublic((string) config($fieldName));
                $fileUrl = storeImageAndGetUrl($request, $fieldName, $uploadPath);
                $this->settingService->addSetting($fieldName, $fileUrl);
            } else {
                $this->settingService->addSetting($fieldName, $fieldValue);
            }
        }

        $this->envWriter->batchWriteKeysToEnvFile($fields);

        $this->storeActionLog(ActionType::UPDATED, [
            'settings' => $fields,
        ]);

        return redirect()->back()->with('success', 'Settings saved successfully.');
    }
}
