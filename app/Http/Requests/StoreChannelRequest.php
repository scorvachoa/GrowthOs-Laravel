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

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                $channel
                    ? Rule::unique('channels', 'name')->ignore($channel->id)
                    : 'unique:channels,name',
            ],
            'color' => ['required', 'string', 'max:20'],
            'youtube_channel_id' => ['nullable', 'string', 'max:120'],
            'channel_url' => ['nullable', 'url', 'max:500'],
        ];
    }
}
