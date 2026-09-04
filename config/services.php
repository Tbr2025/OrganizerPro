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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
     * The AI provider that drafts a blog post from a CricHeroes match-report PDF.
     *
     * Any provider speaking the OpenAI chat-completions dialect works — the client is just
     * Http::withToken() against {base_url}/chat/completions. Keys, base URL and model are all
     * managed in Settings → AI & Blog and stored per provider, so switching between them does
     * not throw away the credentials for the other.
     *
     * Model IDs are SUGGESTIONS, not a whitelist. They change constantly and differ by what a
     * particular key is entitled to, so the settings page lets you type any id and offers a
     * "Load available models" button that asks the provider what your key can actually use.
     */
    'ai' => [
        'timeout' => (int) env('OPENAI_TIMEOUT', 120),

        'providers' => [
            'openai' => [
                'label' => 'OpenAI',
                'base_url' => 'https://api.openai.com/v1',
                'keys_url' => 'https://platform.openai.com/api-keys',
                'note' => 'Paid. Best prose. ~$0.0008 per post on gpt-4o-mini.',
                'models' => ['gpt-4o-mini', 'gpt-4o', 'gpt-5.6-luna', 'gpt-5.6-terra', 'gpt-5.6-sol'],
            ],
            'groq' => [
                'label' => 'Groq',
                'base_url' => 'https://api.groq.com/openai/v1',
                'keys_url' => 'https://console.groq.com/keys',
                'note' => 'Free, no card, roughly 1,000 requests a day. Fastest.',
                'models' => ['llama-3.3-70b-versatile', 'llama-3.1-8b-instant'],
            ],
            'gemini' => [
                'label' => 'Google Gemini',
                'base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai/',
                'keys_url' => 'https://aistudio.google.com/apikey',
                'note' => 'Free tier is capped at roughly 20 requests a day and bills nothing. Older models are listed but refused to new keys, so prefer the -latest aliases; avoid Pro.',
                'models' => ['gemini-flash-latest', 'gemini-3.6-flash', 'gemini-flash-lite-latest'],
            ],
            'custom' => [
                'label' => 'Custom (OpenAI-compatible)',
                'base_url' => '',
                'keys_url' => '',
                'note' => 'Anything speaking the OpenAI chat-completions dialect.',
                'models' => [],
            ],
        ],

        /*
         * List prices per 1M tokens, used only to ESTIMATE spend and to price the usage a
         * provider reports back. A model that is not listed simply has no cost shown — that is
         * a missing price, not an error, and must never stop a post being generated.
         */
        'pricing' => [
            'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
            'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
            'gpt-5.6-luna' => ['input' => 0.20, 'output' => 1.20],
            'gpt-5.6-terra' => ['input' => 2.00, 'output' => 12.00],
            'gpt-5.6-sol' => ['input' => 4.00, 'output' => 20.00],
            'llama-3.3-70b-versatile' => ['input' => 0.0, 'output' => 0.0],
            'llama-3.1-8b-instant' => ['input' => 0.0, 'output' => 0.0],
            /*
             * Gemini's PAID list prices, not zero.
             *
             * Its free tier bills nothing, but showing 0.00 here would quietly become a lie the
             * moment billing is enabled on the project — and the difference between Flash-Lite
             * and 3.5 Flash is roughly twentyfold, which is exactly the sort of thing an
             * estimate exists to warn you about.
             */
            'gemini-flash-latest' => ['input' => 0.75, 'output' => 3.75],
            'gemini-flash-lite-latest' => ['input' => 0.10, 'output' => 0.40],
            'gemini-2.5-flash-lite' => ['input' => 0.10, 'output' => 0.40],
            'gemini-3.5-flash-lite' => ['input' => 0.30, 'output' => 2.50],
            'gemini-2.5-flash' => ['input' => 0.30, 'output' => 2.50],
            'gemini-3.6-flash' => ['input' => 0.75, 'output' => 3.75],
            'gemini-3.7-flash' => ['input' => 0.75, 'output' => 3.75],
            'gemini-3.8-flash' => ['input' => 0.75, 'output' => 3.75],
            'gemini-3.5-flash' => ['input' => 1.50, 'output' => 9.00],
            'gemini-2.5-pro' => ['input' => 1.25, 'output' => 10.00],
        ],
    ],

    // Legacy .env fallbacks. Settings → AI & Blog is the supported place now; these still work
    // so a server configured before that page existed keeps generating.
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 120),
    ],

    // pdftotext (poppler-utils) — already present on the server at /usr/bin/pdftotext.
    // Deliberately a binary rather than a composer package: /vendor is gitignored and the
    // deploy runs no `composer install`, so a new PHP dependency would never reach production.
    'pdftotext' => [
        'path' => env('PDFTOTEXT_PATH'),
    ],

    // Chrome/Chromium binary for Browsershot (PDF/screenshot generation).
    // Leave null locally (Browsershot auto-resolves); set on servers where
    // puppeteer's path resolution fails under PHP-FPM.
    'chrome' => [
        'path' => env('BROWSERSHOT_CHROME_PATH'),
    ],

];
