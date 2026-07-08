<?php

namespace App\Support;

use Spatie\Browsershot\Browsershot;

/**
 * Builds Browsershot instances configured to run headless on our servers.
 *
 * Puppeteer's automatic Chrome path resolution fails under PHP-FPM (it can't
 * locate the browser in the www-data cache), so we resolve the binary
 * explicitly: an env override first, then the puppeteer cache. Locally both
 * are empty and Browsershot falls back to its own resolution, so this is a
 * no-op in development.
 */
class PdfBrowser
{
    /** Resolve the Chrome binary path (env override, else puppeteer cache). */
    public static function chromePath(): ?string
    {
        $configured = config('services.chrome.path');
        if ($configured && is_file($configured)) {
            return $configured;
        }

        $found = glob('/var/www/.cache/puppeteer/chrome/*/chrome-linux64/chrome');

        return $found[0] ?? null;
    }

    /** A Browsershot instance from raw HTML, configured for headless server use. */
    public static function html(string $html): Browsershot
    {
        $browsershot = Browsershot::html($html)->noSandbox();

        if ($path = self::chromePath()) {
            $browsershot->setChromePath($path);
        }

        return $browsershot;
    }
}
