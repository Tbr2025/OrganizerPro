<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Player;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Logins for players added directly (admin, team manager, scorer's quick add)
 * with a phone number and no email.
 *
 * Such a player's account gets a placeholder address — the phone's digits at
 * the site domain, e.g. 919876543210@sportzley.com — so the users table keeps
 * one unique login id per person. It is never written to: User mail
 * notifications and the registration mailer both skip placeholders. The
 * player signs in with the phone number (or that address) and a password an
 * admin sets for them. Public registration still requires a real email.
 */
class PlayerLogin
{
    public static function domain(): string
    {
        return (string) config('auth.phone_login_domain', 'sportzley.com');
    }

    public static function isPlaceholder(?string $email): bool
    {
        return $email !== null && str_ends_with(strtolower($email), '@' . strtolower(self::domain()));
    }

    /**
     * The placeholder for a phone, unique across users. A second account on
     * the same number (two people sharing a phone) gets ".2", ".3", …
     */
    public function placeholderFor(string $phoneDigits, ?int $exceptUserId = null): string
    {
        $digits = Phone::digits($phoneDigits);
        if ($digits === '') {
            $digits = 'player' . Str::lower(Str::random(8));
        }

        $base = $digits;
        $n = 1;
        do {
            $email = ($n === 1 ? $base : "{$base}.{$n}") . '@' . self::domain();
            $taken = User::where('email', $email)->when($exceptUserId, fn ($q) => $q->where('id', '!=', $exceptUserId))->exists();
            $n++;
        } while ($taken);

        return $email;
    }

    /** A new account for a directly-added player: their email, else the phone placeholder. */
    public function createUser(string $name, ?string $email, ?string $phoneDigits, ?int $organizationId): User
    {
        $email = $email ?: ($phoneDigits ? $this->placeholderFor($phoneDigits) : null);

        return User::create([
            'name' => $name,
            'email' => $email,
            'username' => Str::slug($name) . '-' . Str::lower(Str::random(5)),
            // Nobody knows this; an admin sets the real one (setPassword).
            'password' => Hash::make(Str::random(40)),
            'organization_id' => $organizationId,
        ]);
    }

    /** The player's account, created (and linked) if they have none yet. */
    public function ensureUser(Player $player): User
    {
        if ($player->user_id && ($user = User::find($player->user_id))) {
            if (! $user->email) {
                $user->update(['email' => $this->placeholderFor((string) $player->mobile_number_full, $user->id)]);
            }

            return $user;
        }

        return DB::transaction(function () use ($player) {
            $user = $this->createUser($player->name, $player->email, $player->mobile_number_full, $player->organization_id);
            $player->forceFill(['user_id' => $user->id])->save();

            return $user;
        });
    }

    /** Admin sets (or resets) the player's password. Returns the login id. */
    public function setPassword(Player $player, string $password): User
    {
        $user = $this->ensureUser($player);
        $user->forceFill(['password' => Hash::make($password)])->save();

        return $user;
    }

    /**
     * The account a phone number signs in to, or null when none — or more than
     * one, which must not be guessed between.
     *
     * Matches the placeholder address, then any player with that phone. A number
     * typed without its country code (9876543210) matches a stored one with it
     * (919876543210), so long as only one account fits.
     */
    public function resolve(string $input): ?User
    {
        $digits = ltrim(Phone::digits($input), '0');
        if (strlen($digits) < 7 || strlen($digits) > 15) {
            return null;
        }

        $domain = '@' . self::domain();
        $ids = User::where('email', $digits . $domain)->orWhere('email', 'like', $digits . '.%' . $domain)->pluck('id');

        $playerUserIds = Player::withoutGlobalScopes()->whereNotNull('user_id')
            ->where(fn ($q) => $q->where('mobile_number_full', $digits)
                ->orWhere(fn ($q) => $q->where('mobile_number_full', 'like', '%' . $digits)->whereRaw('CHAR_LENGTH(mobile_number_full) <= ?', [strlen($digits) + 4])))
            ->pluck('user_id');

        $all = $ids->merge($playerUserIds)->unique()->values();

        return $all->count() === 1 ? User::find($all->first()) : null;
    }

    /** True when the input looks like a phone number rather than an email or username. */
    public static function looksLikePhone(string $input): bool
    {
        return (bool) preg_match('/^\+?[\d\s\-()]{7,20}$/', trim($input));
    }
}
