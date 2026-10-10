<?php

use App\Services\Auth\PlayerLogin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Accounts created without an email (scorer quick add, phone-only direct adds
 * before phone login existed) get their phone login id, so the player can sign
 * in once an admin sets a password. Only fills empty emails; touches nothing else.
 */
return new class extends Migration
{
    public function up(): void
    {
        $login = app(PlayerLogin::class);

        $rows = DB::table('users')
            ->join('players', 'players.user_id', '=', 'users.id')
            ->whereNull('users.email')
            ->whereNotNull('players.mobile_number_full')
            ->where('players.mobile_number_full', '!=', '')
            ->select('users.id', 'players.mobile_number_full')
            ->get()
            ->unique('id');

        foreach ($rows as $row) {
            DB::table('users')->where('id', $row->id)->whereNull('email')
                ->update(['email' => $login->placeholderFor($row->mobile_number_full, $row->id)]);
        }
    }

    public function down(): void
    {
        // Leaves the ids in place: removing them would lock those players out.
    }
};
