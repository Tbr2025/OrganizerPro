<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Turns a logo reference into a base64 data URI for a Browsershot-rendered PDF.
 *
 * Headless Chrome cannot reliably fetch remote or relative image URLs — it renders the page
 * before the request finishes, or without the session that would authorise it — which is why
 * the consent PDF's logo came out blank. Embedding the bytes removes the fetch entirely.
 *
 * Lives here rather than on a controller because two PDFs now need it, and the second copy is
 * where the two would start disagreeing about which of the four accepted input shapes work.
 */
class LogoDataUri
{
    /**
     * Accepts any of the four shapes a logo is stored or handed around as:
     * a full `/storage/…` URL, a full URL to a public asset, a public-disk relative path,
     * or a path under `public/`. Returns null when it resolves to no readable file, so a
     * missing logo degrades to no logo rather than a broken image.
     */
    public static function from(?string $src): ?string
    {
        if (! $src) {
            return null;
        }

        $path = self::resolve($src);

        if (! $path || ! is_file($path)) {
            return null;
        }

        $data = @file_get_contents($path);
        if ($data === false) {
            return null;
        }

        $mime = @mime_content_type($path) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }

    /** The first of the four shapes that resolves to a local file. */
    private static function resolve(string $src): ?string
    {
        if (str_starts_with($src, 'http')) {
            $urlPath = parse_url($src, PHP_URL_PATH) ?: '';

            if (str_contains($urlPath, '/storage/')) {
                $rel = ltrim(substr($urlPath, strpos($urlPath, '/storage/') + strlen('/storage/')), '/');

                return Storage::disk('public')->exists($rel)
                    ? Storage::disk('public')->path($rel)
                    : null;
            }

            $candidate = public_path(ltrim($urlPath, '/'));

            return is_file($candidate) ? $candidate : null;
        }

        if (Storage::disk('public')->exists($src)) {
            return Storage::disk('public')->path($src);
        }

        return is_file(public_path($src)) ? public_path($src) : null;
    }
}
