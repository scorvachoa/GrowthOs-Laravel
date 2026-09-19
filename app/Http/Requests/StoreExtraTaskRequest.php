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
            'description' => ['nullable', 'string', 'max:65535'],
            'status' => ['required', 'string', 'max:24'],
            'location' => ['required', Rule::in(['oficina', 'fuera'])],
            'shared_user_ids' => ['nullable', 'array'],
            'shared_user_ids.*' => ['integer', 'exists:users,id'],
            'shared_roles' => ['nullable', 'array'],
            'shared_roles.*' => ['string', 'in:reader,editor'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $timeRange = $this->input('time_range');
            if ($timeRange && str_contains($timeRange, '-')) {
                [$start, $end] = explode('-', $timeRange, 2);
                if (strlen($start) === 5 && strlen($end) === 5 && $end <= $start) {
                    $validator->errors()->add('time_range', 'La hora fin debe ser mayor a la hora de inicio');
                }
            }
        });
    }
}
