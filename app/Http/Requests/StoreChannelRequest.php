<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->isMethod('POST')
            ? $this->user()->can('create empresa')
            : $this->user()->can('edit empresa');
    }

    public function rules(): array
    {
        $channel = $this->route('channel');
        $orgId = $this->user()->activeOrganizationId();

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('channels', 'name')
                    ->where(fn ($q) => $q->where('organization_id', $orgId))
                    ->ignore($channel?->id),
            ],
            'color' => ['required', 'string', 'max:20'],
            'youtube_channel_id' => ['nullable', 'string', 'max:120'],
            'channel_url' => ['nullable', 'url', 'max:500'],
        ];
    }
}
