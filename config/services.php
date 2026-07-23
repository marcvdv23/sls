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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
        'endpoint' => env('GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta'),
        'ca_bundle' => env('GEMINI_CA_BUNDLE', 'C:\laragon\etc\ssl\cacert.pem'),
        'verify_ssl' => env('GEMINI_VERIFY_SSL', true),
    ],

    'libretranslate' => [
        'enabled' => env('LIBRETRANSLATE_ENABLED', true),
        'endpoint' => env('LIBRETRANSLATE_ENDPOINT', ''),
        'api_key' => env('LIBRETRANSLATE_API_KEY'),
        'timeout' => env('LIBRETRANSLATE_TIMEOUT', 3),
    ],

    'translation' => [
        'provider_order' => array_filter(array_map('trim', explode(',', env(
            'TRANSLATION_PROVIDER_ORDER',
            'deepl,google,azure,mymemory,libretranslate,gemini'
        )))),
        'timeout' => env('TRANSLATION_TIMEOUT', 8),
        'ca_bundle' => env('TRANSLATION_CA_BUNDLE', env('GEMINI_CA_BUNDLE', 'C:\laragon\etc\ssl\cacert.pem')),
        'verify_ssl' => env('TRANSLATION_VERIFY_SSL', env('GEMINI_VERIFY_SSL', true)),
    ],

    'deepl' => [
        'api_key' => env('DEEPL_API_KEY'),
        'endpoint' => env('DEEPL_ENDPOINT', 'https://api-free.deepl.com'),
    ],

    'google_translate' => [
        'api_key' => env('GOOGLE_TRANSLATE_API_KEY'),
    ],

    'azure_translator' => [
        'key' => env('AZURE_TRANSLATOR_KEY'),
        'endpoint' => env('AZURE_TRANSLATOR_ENDPOINT', 'https://api.cognitive.microsofttranslator.com'),
        'region' => env('AZURE_TRANSLATOR_REGION'),
    ],

    'mymemory' => [
        'enabled' => env('MYMEMORY_ENABLED', true),
        'email' => env('MYMEMORY_EMAIL'),
        'key' => env('MYMEMORY_KEY'),
    ],

    'ai' => [
        'provider' => env('AI_PROVIDER', 'gemini'),
    ],

    'ollama' => [
        'endpoint' => env('OLLAMA_ENDPOINT', 'http://127.0.0.1:11434'),
        'model' => env('OLLAMA_MODEL', 'mistral'),
        'timeout' => env('OLLAMA_TIMEOUT', 120),
    ],

    'sls_worker' => [
        'token' => env('SLS_WORKER_TOKEN'),
    ],

];
