<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Give auction templates an owning organization.
 *
 * Until now `auction_templates` had no organization at all and the admin routes
 * carried no permission gate, so any authenticated account could read, edit and
 * delete every organization's templates. That was survivable while a template was
 * just positioned-element JSON. It stops being survivable the moment a template can
 * hold raw HTML that is served on a public URL.
 *
 * Deliberately NOT wired to the `BelongsToOrganization` global scope:
 * `OrganizationScope` filters on strict equality, so a global template
 * (organization_id IS NULL — the fallback every auction relies on through
 * `AuctionTemplate::getDefault()`) would become invisible to every non-Superadmin
 * and the existing LED wall would silently lose its template. Isolation is enforced
 * explicitly in the controller and in EnsureOrganizerCanAccess instead, both of
 * which can express "mine, or global".
 */
return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('auction_templates', 'organization_id')) {
            return;
        }

        Schema::table('auction_templates', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable()->after('id')->index();
            $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
        });

        // Templates bound to an auction inherit that auction's organization. Templates
        // with no auction stay NULL: those are the global fallbacks, and claiming them
        // for one organization would take them away from everyone else.
        DB::statement('
            UPDATE auction_templates AS t
            INNER JOIN auctions AS a ON a.id = t.auction_id
            SET t.organization_id = a.organization_id
            WHERE t.auction_id IS NOT NULL AND t.organization_id IS NULL
        ');
    }

    public function down(): void
    {
        if (! Schema::hasColumn('auction_templates', 'organization_id')) {
            return;
        }

        Schema::table('auction_templates', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });
    }
};
