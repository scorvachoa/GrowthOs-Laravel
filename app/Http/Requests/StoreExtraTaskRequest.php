<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExtraTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->isMethod('POST')
            ? $this->user()->can('create planning')
            : $this->user()->can('edit planning');
    }

    public function rules(): array
    {
        return [
            'task_date' => ['required', 'date'],
            'time_range' => ['required', 'string', 'max:32'],
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:24'],
            'location' => ['required', Rule::in(['oficina', 'fuera'])],
        ];
    }
}
