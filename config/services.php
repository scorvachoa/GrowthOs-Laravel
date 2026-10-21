<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'youtube' => [
        'api_key' => env('YOUTUBE_API_KEY'),
    ],

    'facebook' => [
        'graph_version' => env('FACEBOOK_GRAPH_VERSION', 'v22.0'),
        'client_id' => env('FACEBOOK_APP_ID'),
        'client_secret' => env('FACEBOOK_APP_SECRET'),
    ],

    'gemini' => [
        // All API keys aggregated for multi-key rotation
        'api_keys' => array_values(array_filter([
            env('GEMINI_API_KEY'),
            env('GEMINI_KEY_1'),
            env('GEMINI_KEY_2'),
            env('GEMINI_KEY_3'),
            env('GEMINI_KEY_4'),
        ], fn ($v) => ! empty(trim($v ?? '')))),
    ],

    'elevenlabs' => [
        'api_key' => env('ELEVENLABS_API_KEY'),
        'voice_id' => env('ELEVENLABS_VOICE_ID'),
        'model_id' => env('ELEVENLABS_MODEL_ID', 'eleven_multilingual_v2'),
    ],

    'deepl' => [
        'api_key' => env('DEEPL_API_KEY'),
    ],

];
