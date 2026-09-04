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
     * OpenAI, for turning a CricHeroes match-report PDF into a blog post.
     *
     * The key lives in the server's .env and nowhere else — this repository is PUBLIC, so a key
     * committed here is scraped within minutes. `key` being empty is the supported "not
     * configured" state: the generate button reports it rather than failing at the API.
     */
    'openai' => [
        'key' => env('OPENAI_API_KEY'),

        // The fallback when no model has been chosen in the dashboard.
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 120),

        /*
         * What the dashboard offers, and what each one costs per 1M tokens.
         *
         * Prices are OpenAI's published list rates and are used only to ESTIMATE spend and to
         * price the usage OpenAI reports back — they are not a bill. OpenAI changes them, so
         * this table is config rather than code, and the panel says the figure is an estimate.
         */
        'models' => [
            'gpt-4o-mini' => ['label' => 'GPT-4o mini', 'note' => 'Cheapest — fine for testing', 'input' => 0.15, 'output' => 0.60],
            'gpt-5.6-luna' => ['label' => 'GPT-5.6 Luna', 'note' => 'Fast and affordable, newer', 'input' => 0.20, 'output' => 1.20],
            'gpt-4o' => ['label' => 'GPT-4o', 'note' => 'Stronger prose', 'input' => 2.50, 'output' => 10.00],
            'gpt-5.6-terra' => ['label' => 'GPT-5.6 Terra', 'note' => 'Balanced, high volume', 'input' => 2.00, 'output' => 12.00],
            'gpt-5.6-sol' => ['label' => 'GPT-5.6 Sol', 'note' => 'Flagship — most expensive', 'input' => 4.00, 'output' => 20.00],

            /*
             * Free, OpenAI-compatible alternatives.
             *
             * The whole client is Http::withToken() against {base_url}/chat/completions, so any
             * provider speaking that dialect works with no code change — set OPENAI_BASE_URL and
             * OPENAI_API_KEY to theirs. These only work once the base URL points at Groq, which
             * is why the panel shows which endpoint is actually being called.
             */
            'llama-3.3-70b-versatile' => ['label' => 'Llama 3.3 70B (Groq — free)', 'note' => 'Needs OPENAI_BASE_URL set to Groq', 'input' => 0.0, 'output' => 0.0],
            'llama-3.1-8b-instant' => ['label' => 'Llama 3.1 8B (Groq — free)', 'note' => 'Needs OPENAI_BASE_URL set to Groq. Fastest, weakest prose', 'input' => 0.0, 'output' => 0.0],
        ],
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
