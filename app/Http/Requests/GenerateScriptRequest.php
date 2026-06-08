<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateScriptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('generate ai');
    }

    public function rules(): array
    {
        return [
            'idea' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
