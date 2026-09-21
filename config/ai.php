<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Prompts Configuration
    |--------------------------------------------------------------------------
    |
    | Configurable content for AI generation prompts. Customize these values
    | to adapt the prompts to your specific business or use case.
    |
    */

    'business_type' => env('AI_BUSINESS_TYPE', 'turismo en Cusco'),
    'business_name' => env('AI_BUSINESS_NAME', ''),
    'business_description' => env('AI_BUSINESS_DESCRIPTION', 'especializado en turismo en Cusco'),
    'target_audience' => env('AI_TARGET_AUDIENCE', 'viajeros jovenes, parejas y familias que desean conocer mas sobre cada destino antes de visitarlo'),
    'business_url' => env('AI_BUSINESS_URL', ''),
    'business_phone' => env('AI_BUSINESS_PHONE', ''),
    'business_country' => env('AI_BUSINESS_COUNTRY', 'Peru'),

];
