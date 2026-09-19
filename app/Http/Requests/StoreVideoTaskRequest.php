<?php

namespace App\Http\Requests;

use App\Enums\VideoTaskStatus;
use App\Services\PlanningValidator;
use App\Support\WorkBlocks;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVideoTaskRequest extends FormRequest
{
    public function __construct(
        protected PlanningValidator $planningValidator,
    ) {
        parent::__construct();
    }

    public function authorize(): bool
    {
        return $this->isMethod('POST')
            ? $this->user()->can('create planning')
            : $this->user()->can('edit planning');
    }

    public function rules(): array
    {
        $settings = $this->user()->merged_settings;
        $blockHours = $settings['block_hours'] ?? 2;
        $startHour = WorkBlocks::parseHour($settings['default_work_start'] ?? '09:00');
        $endHour = WorkBlocks::parseHour($settings['default_work_end'] ?? '18:00');
        $useBlocks = $settings['use_blocks'] ?? true;

        $rules = [
            'task_date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'script' => ['nullable', 'string'],
            'copy' => ['nullable', 'string'],
            'translations' => ['nullable', 'array'],
            'youtube_url' => ['nullable', 'url'],
            'channel_id' => ['nullable', Rule::exists('channels', 'id')->where(function ($q) {
                $q->where('organization_id', $this->user()->activeOrganizationId());
            })],
            'status' => ['required', Rule::in(VideoTaskStatus::values())],
            'shared_user_ids' => ['nullable', 'array'],
            'shared_user_ids.*' => ['integer', 'exists:users,id'],
            'shared_roles' => ['nullable', 'array'],
            'shared_roles.*' => ['string', 'in:reader,editor'],
        ];

        if ($useBlocks) {
            $blocks = WorkBlocks::generate($blockHours, $startHour, $endHour);
            $rules['time_range'] = ['required', Rule::in($blocks)];
        } else {
            $rules['time_range'] = ['required', 'string', 'max:30'];
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $settings = $this->user()->merged_settings;
        $workingDays = $settings['working_days'] ?? [1, 2, 3, 4, 5];
        $useBlocks = $settings['use_blocks'] ?? true;

        $validator->after(function ($validator) use ($workingDays, $useBlocks) {
            $taskDate = $this->input('task_date');
            $timeRange = $this->input('time_range');
            $taskId = $this->route('video_task')?->id ?? $this->route('task')?->id;

            if ($taskDate) {
                $this->planningValidator->assertWorkingDay($taskDate, $workingDays);
            }

            if ($taskDate && $timeRange) {
                $this->planningValidator->assertSlotAvailable($taskDate, $timeRange, $taskId);
            }

            if (! $useBlocks && $timeRange && str_contains($timeRange, '-')) {
                [$start, $end] = explode('-', $timeRange, 2);
                if (strlen($start) === 5 && strlen($end) === 5 && $end <= $start) {
                    $validator->errors()->add('time_range', 'La hora fin debe ser mayor a la hora de inicio');
                }
            }
        });
    }
}
