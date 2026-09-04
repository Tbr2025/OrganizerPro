<?php

declare(strict_types=1);

namespace App\Services\Blog;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Reading a CricHeroes match-report PDF.
 *
 * Extraction shells out to `pdftotext` (poppler-utils), which is already installed on the
 * server, rather than pulling in a PHP parser. That is not a preference: `/vendor` is gitignored
 * and the deploy chain runs no `composer install`, so a new composer package would be missing in
 * production and the feature would fail there and nowhere else.
 *
 * The upload is stored on the PRIVATE disk. A match report is somebody's unpublished draft until
 * they publish the post, and `public/` is served directly by nginx.
 */
class MatchReportPdfService
{
    /** Where uploads live on the private disk. */
    public const DIRECTORY = 'match_reports';

    /**
     * Beyond this the text is almost certainly repeated boilerplate, and it is the model's bill.
     * A full CricHeroes report is a few thousand characters.
     */
    public const MAX_TEXT_LENGTH = 24000;

    public function store(UploadedFile $file): array
    {
        $path = $file->store(self::DIRECTORY);

        return [
            'pdf_path' => $path,
            'pdf_name' => $file->getClientOriginalName(),
            'pdf_size' => $file->getSize(),
        ];
    }

    /**
     * Pull the text out of a stored PDF.
     *
     * Returns '' rather than throwing when the PDF has no text layer — a scanned or
     * image-only report is a normal thing for someone to upload, and the caller says so in the
     * UI instead of showing a stack trace.
     */
    public function extractText(string $storagePath): string
    {
        if (! Storage::exists($storagePath)) {
            return '';
        }

        $binary = $this->binary();
        if ($binary === null) {
            throw new \RuntimeException(
                'pdftotext is not available. Install poppler-utils, or set PDFTOTEXT_PATH to its absolute path.'
            );
        }

        $absolute = Storage::path($storagePath);

        // -layout keeps the scorecard columns readable as columns; without it the batting table
        // collapses into one run of numbers that no model can attribute to a player.
        $process = new Process([$binary, '-layout', '-nopgbrk', '-enc', 'UTF-8', $absolute, '-']);
        $process->setTimeout(60);

        try {
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            throw new \RuntimeException('Could not read the PDF: ' . trim($process->getErrorOutput() ?: $e->getMessage()));
        }

        return $this->tidy($process->getOutput());
    }

    /** Collapse the whitespace pdftotext leaves behind, and cap the length. */
    private function tidy(string $text): string
    {
        $text = str_replace("\r\n", "\n", $text);
        // -layout pads with long runs of spaces; two is enough to keep columns apart.
        $text = preg_replace('/[ \t]{3,}/', '  ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = trim($text);

        if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            $text = mb_substr($text, 0, self::MAX_TEXT_LENGTH) . "\n\n[report truncated]";
        }

        return $text;
    }

    /** The configured binary, else whatever is on PATH, else null. */
    private function binary(): ?string
    {
        $configured = config('services.pdftotext.path');
        if (! empty($configured) && is_executable($configured)) {
            return $configured;
        }

        foreach (['/usr/bin/pdftotext', '/usr/local/bin/pdftotext', '/opt/homebrew/bin/pdftotext'] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    public function isAvailable(): bool
    {
        return $this->binary() !== null;
    }

    public function delete(?string $storagePath): void
    {
        if ($storagePath && Storage::exists($storagePath)) {
            Storage::delete($storagePath);
        }
    }
}
