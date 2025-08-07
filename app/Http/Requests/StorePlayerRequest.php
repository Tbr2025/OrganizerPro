<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePlayerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'team_name' => 'nullable|string',
            'jersey_name' => 'nullable|string',
            'kit_size' => 'nullable|string',
            'cricheroes_no' => 'nullable|string',
            'mobile_no' => 'nullable|string',
            'batting_profile' => 'nullable|string',
            'bowling_profile' => 'nullable|string',
            'wicket_keeper' => 'nullable|boolean',
            'transportation_required' => 'nullable|boolean',
        ];
    }
}
