<?php

declare(strict_types=1);

namespace App\Services\Blog;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Where the AI credentials live, and how they are kept.
 *
 * The key is stored in the settings table, ENCRYPTED, rather than written to .env. That is not a
 * preference: php-fpm runs as www-data and .env on the server is owned by ubuntu and not
 * group-writable, so the settings page's env-writing path fails silently there. A key that
 * appears to save and then does nothing is worse than no field at all.
 *
 * Encrypted because the settings table ends up in every database backup, and a plaintext API key
 * with a card behind it should not travel in a dump. It is never rendered back to the page —
 * only a masked hint — so a saved key cannot be read out of the HTML.
 *
 * .env still works and still wins nothing: an explicit dashboard value takes precedence, because
 * otherwise typing one in while OPENAI_API_KEY was set would silently change nothing.
 */
class AiSettings
{
    public const KEY = 'openai_api_key_encrypted';

    public const BASE_URL = 'openai_base_url';

    /** Never store these in the settings table as plain text, and never write them to a log. */
    public const SECRET_FIELDS = ['openai_api_key'];

    public function apiKey(): ?string
    {
        $stored = (string) (get_setting(self::KEY) ?: '');

        if ($stored !== '') {
            try {
                return Crypt::decryptString($stored);
            } catch (DecryptException) {
                // A key encrypted under a previous APP_KEY cannot be recovered. Fall through to
                // .env rather than handing the API a string of ciphertext.
                return $this->envKey();
            }
        }

        return $this->envKey();
    }

    /** Blank input leaves the saved key alone — the field is always rendered empty. */
    public function storeApiKey(?string $plain): void
    {
        $plain = trim((string) $plain);

        if ($plain === '') {
            return;
        }

        // A literal mask posted back means "unchanged", not "set my key to dots".
        if (str_contains($plain, '•')) {
            return;
        }

        add_setting(self::KEY, Crypt::encryptString($plain));
    }

    public function forgetApiKey(): void
    {
        delete_setting(self::KEY);
    }

    public function hasKey(): bool
    {
        return $this->apiKey() !== null && $this->apiKey() !== '';
    }

    /** Where the key in use came from, so the page can say so. */
    public function keySource(): ?string
    {
        if ((string) (get_setting(self::KEY) ?: '') !== '') {
            return 'dashboard';
        }

        return $this->envKey() ? 'env' : null;
    }

    /** Enough to recognise a key, not enough to use one. */
    public function maskedKey(): ?string
    {
        $key = $this->apiKey();

        if (! $key) {
            return null;
        }

        return mb_substr($key, 0, 3) . str_repeat('•', 8) . mb_substr($key, -4);
    }

    public function baseUrl(): string
    {
        $stored = trim((string) (get_setting(self::BASE_URL) ?: ''));

        return $stored !== '' ? $stored : (string) config('services.openai.base_url');
    }

    private function envKey(): ?string
    {
        $key = (string) config('services.openai.key');

        return $key !== '' ? $key : null;
    }
}
