<?php

declare(strict_types=1);

namespace App\Support;

/**
 * One spelling of a phone number for matching.
 *
 * `mobile_number_full` is stored as country code + national number with the
 * '+' stripped ("919876543210"). People type the national part with spaces,
 * dashes or a trunk '0' ("098765 43210"), so two registrations by the same
 * person can store different strings. Normalise before comparing.
 */
final class Phone
{
    /** Digits only: country code followed by the national number without a trunk 0. */
    public static function full(?string $countryCode, ?string $national): string
    {
        $code = preg_replace('/\D/', '', (string) $countryCode);
        $nat = ltrim(preg_replace('/\D/', '', (string) $national), '0');

        return $nat === '' ? '' : $code . $nat;
    }

    /** Normalise an already-combined number (digits only, no leading '+'). */
    public static function digits(?string $full): string
    {
        return preg_replace('/\D/', '', (string) $full);
    }
}
