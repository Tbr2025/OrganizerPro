<?php

declare(strict_types=1);

namespace App\Http\Requests\Setting;

use App\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->checkAuthorization(Auth::user(), ['settings.edit']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ld_apply_filters('settings.update.validation.rules', [
            /** @example {"app_name": "Lara Dashboard", "theme_primary_color": "#635bff", "theme_secondary_color": "#1f2937", "sidebar_bg_lite": "#FFFFFF", "sidebar_bg_dark": "#171f2e", "default_pagination": "10", "site_logo_lite": "/images/logo/lara-dashboard.png", "site_logo_dark": "/images/logo/lara-dashboard-dark.png", "global_custom_css": "", "global_custom_js": ""} */
            'settings' => 'required|array',
        ]);
    }
}
