<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Quota mode
    |--------------------------------------------------------------------------
    | 'observe'  → registra el uso y solo aplica el techo de seguridad.
    | 'enforce'  → además bloquea al superar los límites configurados.
    */
    'mode' => env('QUOTA_MODE', 'observe'),

    /*
    |--------------------------------------------------------------------------
    | Safety ceiling (techo duro)
    |--------------------------------------------------------------------------
    | Máximo de eventos por organización+evento+mes. Se aplica SIEMPRE,
    | en cualquier modo, como protección contra abusos o fugas.
    */
    'safety_ceiling' => (int) env('QUOTA_SAFETY_CEILING', 10000),

    /*
    |--------------------------------------------------------------------------
    | Per-event monthly limits
    |--------------------------------------------------------------------------
    | null = sin límite. Se rellenan tras el período de medición (2 semanas).
    */
    'limits' => [
        'ai.generate' => env('QUOTA_AI_GENERATE'),
        'ai.audio' => env('QUOTA_AI_AUDIO'),
        'ai.copy' => env('QUOTA_AI_COPY'),
        'ai.phrases' => env('QUOTA_AI_PHRASES'),
        'ai.copy-phrases' => env('QUOTA_AI_COPY_PHRASES'),
        'ai.create-task' => env('QUOTA_AI_CREATE_TASK'),
        'tasks.generate-copy' => env('QUOTA_TASKS_GENERATE_COPY'),
        'tasks.generate-title-thumbnails' => env('QUOTA_TASKS_GENERATE_TITLES'),
        'tasks.translate' => env('QUOTA_TASKS_TRANSLATE'),
        'tasks.translate-task' => env('QUOTA_TASKS_TRANSLATE_TASK'),
        'tasks.translate-content' => env('QUOTA_TASKS_TRANSLATE_CONTENT'),
        'ideas.generate' => env('QUOTA_IDEAS_GENERATE'),
    ],

];
