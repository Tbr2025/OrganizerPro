<?php

declare(strict_types=1);

namespace App\Services\Blog;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * Provider credentials for the blog writer, held per provider.
 *
 * Each provider keeps its OWN key, base URL and model, so switching from OpenAI to Groq and back
 * does not throw away either set — which is what happens with a single shared field, and it is
 * the sort of thing you only discover after re-pasting a key for the third time.
 *
 * Keys are stored in the settings table ENCRYPTED rather than written to .env: php-fpm runs as
 * www-data, .env on the server is owned by ubuntu and is not group-writable, and the settings
 * page's env-writing path is wrapped in a try/catch — so a field that wrote there would appear
 * to save and silently do nothing. Encrypted because the settings table is in every database
 * backup. A saved key is never rendered back, only masked.
 */
class AiSettings
{
    public const PROVIDER = 'ai_provider';

    /** Posted field names that must never be stored in plain text or written to a log. */
    public const SECRET_FIELD_PREFIX = 'ai_key_';

    /** Where the pre-provider settings lived. Read as a fallback so nothing already set is lost. */
    private const LEGACY_KEY = 'openai_api_key_encrypted';

    private const LEGACY_BASE_URL = 'openai_base_url';

    private const LEGACY_MODEL = 'openai_blog_model';

    public function providers(): array
    {
        return (array) config('services.ai.providers', []);
    }

    public function provider(): string
    {
        $chosen = (string) (get_setting(self::PROVIDER) ?: '');

        return array_key_exists($chosen, $this->providers()) ? $chosen : 'openai';
    }

    public function providerConfig(?string $provider = null): array
    {
        $provider ??= $this->provider();

        return $this->providers()[$provider] ?? [];
    }

    // -----------------------------------------------------------------------
    // Credentials
    // -----------------------------------------------------------------------

    public function apiKey(?string $provider = null): ?string
    {
        $provider ??= $this->provider();
        $stored = (string) (get_setting(self::SECRET_FIELD_PREFIX . $provider) ?: '');

        if ($stored === '' && $provider === 'openai') {
            $stored = (string) (get_setting(self::LEGACY_KEY) ?: '');
        }

        if ($stored !== '') {
            try {
                return Crypt::decryptString($stored);
            } catch (DecryptException) {
                // Encrypted under a previous APP_KEY and unrecoverable. Fall through rather than
                // handing the provider a string of ciphertext.
            }
        }

        return $provider === 'openai' ? $this->envKey() : null;
    }

    /** Blank input leaves the saved key alone — the field always renders empty. */
    public function storeApiKey(string $provider, ?string $plain): void
    {
        $plain = trim((string) $plain);

        // A posted mask means "unchanged", not "set my key to dots".
        if ($plain === '' || str_contains($plain, '•')) {
            return;
        }

        add_setting(self::SECRET_FIELD_PREFIX . $provider, Crypt::encryptString($plain));
    }

    public function hasKey(?string $provider = null): bool
    {
        return (string) $this->apiKey($provider) !== '';
    }

    public function keySource(?string $provider = null): ?string
    {
        $provider ??= $this->provider();

        if ((string) (get_setting(self::SECRET_FIELD_PREFIX . $provider) ?: '') !== '') {
            return 'dashboard';
        }
        if ($provider === 'openai' && (string) (get_setting(self::LEGACY_KEY) ?: '') !== '') {
            return 'dashboard';
        }

        return ($provider === 'openai' && $this->envKey()) ? 'env' : null;
    }

    /** Enough to recognise a key, not enough to use one. */
    public function maskedKey(?string $provider = null): ?string
    {
        $key = $this->apiKey($provider);

        if (! $key) {
            return null;
        }

        return mb_substr($key, 0, 3) . str_repeat('•', 8) . mb_substr($key, -4);
    }

    // -----------------------------------------------------------------------
    // Endpoint and model
    // -----------------------------------------------------------------------

    public function baseUrl(?string $provider = null): string
    {
        $provider ??= $this->provider();
        $stored = trim((string) (get_setting('ai_base_url_' . $provider) ?: ''));

        if ($stored === '' && $provider === 'openai') {
            $stored = trim((string) (get_setting(self::LEGACY_BASE_URL) ?: ''));
        }

        if ($stored !== '') {
            return $stored;
        }

        return (string) ($this->providerConfig($provider)['base_url'] ?? config('services.openai.base_url'));
    }

    /**
     * The model id, as typed.
     *
     * Deliberately NOT validated against a list. Model ids change constantly and differ by what
     * a given key is entitled to — `gemini-3.8-flash` exists but a free-tier key is refused it —
     * so a whitelist here would block working configurations for the sake of tidiness.
     */
    public function model(?string $provider = null): string
    {
        $provider ??= $this->provider();
        $stored = trim((string) (get_setting('ai_model_' . $provider) ?: ''));

        if ($stored === '' && $provider === 'openai') {
            $stored = trim((string) (get_setting(self::LEGACY_MODEL) ?: ''));
        }

        if ($stored !== '') {
            return $stored;
        }

        $suggested = $this->providerConfig($provider)['models'] ?? [];

        return $suggested[0] ?? (string) config('services.openai.model', 'gpt-4o-mini');
    }

    public function storeEndpoint(string $provider, ?string $baseUrl, ?string $model): void
    {
        add_setting('ai_base_url_' . $provider, trim((string) $baseUrl));
        add_setting('ai_model_' . $provider, trim((string) $model));
    }

    // -----------------------------------------------------------------------
    // Live model list
    // -----------------------------------------------------------------------

    /**
     * Ask the provider what this key may actually use.
     *
     * Every OpenAI-dialect provider exposes GET /models, and it is the only reliable answer to
     * "does this model exist for me" — a hardcoded list cannot know what a particular key is
     * entitled to, which is exactly how `gemini-3.8-flash` got rejected as non-existent.
     *
     * @return array<int, string>
     */
    public function availableModels(?string $provider = null): array
    {
        $provider ??= $this->provider();
        $key = $this->apiKey($provider);

        if (! $key) {
            throw new \RuntimeException('Save an API key for this provider first.');
        }

        $response = Http::withToken($key)
            ->timeout(30)
            ->acceptJson()
            ->get(rtrim($this->baseUrl($provider), '/') . '/models');

        if ($response->failed()) {
            throw new \RuntimeException(
                $response->json('error.message') ?? ('The provider returned HTTP ' . $response->status() . '.')
            );
        }

        $ids = collect($response->json('data') ?? [])
            ->pluck('id')
            ->filter()
            // Gemini returns "models/gemini-2.5-flash"; chat completions wants the bare id.
            ->map(fn ($id) => (string) preg_replace('#^models/#', '', (string) $id))
            ->unique()
            ->sort()
            ->values()
            ->all();

        if (empty($ids)) {
            throw new \RuntimeException('The provider returned no models.');
        }

        return $ids;
    }

    private function envKey(): ?string
    {
        $key = (string) config('services.openai.key');

        return $key !== '' ? $key : null;
    }
}
