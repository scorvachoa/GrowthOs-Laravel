<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('edit empresa');
    }

    public function rules(): array
    {
        $org = $this->route('company');

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('organizations', 'name')->ignore($org)],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ];
    }
}
