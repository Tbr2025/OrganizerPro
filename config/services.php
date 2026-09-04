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
