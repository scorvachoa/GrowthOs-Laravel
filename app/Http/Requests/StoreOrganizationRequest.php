<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create empresa');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', 'unique:organizations,name'],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
        ];
    }
}
