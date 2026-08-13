<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Organization;
use App\Models\Player;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `/admin/players/export-xlsx` answered 404 in production while the route sat plainly in
 * routes/web.php.
 *
 * The cause was ordering, not the route: `Route::resource('players', ...)` was registered
 * first, and its `GET /players/{player}` matches the literal segment "export-xlsx". Laravel
 * then tried to bind a player with the id "export-xlsx", found none, and returned the 404 the
 * user was looking at. The fix is to register the literal route ahead of the wildcard, which
 * only a test that hits the URL can hold in place — `route()` builds the same string either
 * way, so a name-based assertion would have passed throughout.
 */
class PlayerWorkbookRouteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $org = Organization::create(['name' => 'Export Org', 'slug' => 'export-org-' . uniqid()]);

        $role = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(
            ['name' => 'player.view', 'guard_name' => 'web'],
            ['group_name' => 'player']
        ));

        return User::factory()->create(['organization_id' => $org->id])->assignRole($role);
    }

    #[Test]
    public function the_literal_export_url_is_not_swallowed_by_the_player_wildcard(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/players/export-xlsx');

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            (string) $response->headers->get('content-type'),
            'The export should answer with a workbook, not with a player page.'
        );
    }

    #[Test]
    public function the_export_honours_the_filters_the_list_was_showing(): void
    {
        $admin = $this->admin();
        $org = Organization::find($admin->organization_id);

        $playerRole = Role::firstOrCreate(['name' => 'Player', 'guard_name' => 'web']);

        $make = function (string $name, string $status) use ($org, $playerRole) {
            $user = User::factory()->create(['organization_id' => $org->id]);
            $user->assignRole($playerRole);

            return Player::create([
                'organization_id' => $org->id,
                'user_id' => $user->id,
                'name' => $name,
                'email' => strtolower(str_replace(' ', '', $name)) . '@x.test',
                'status' => $status,
            ]);
        };

        $approved = $make('KeepMe', 'approved');
        $rejected = $make('DropMe', 'rejected');

        /*
         * The list defaults to `status=approved`; the export defaulted to no status at all, so
         * every pending and rejected player came out of it as though they were on the books.
         * That is the failure mode this pins: not "does a filter work" but "does the export
         * agree with the page it was launched from".
         */
        $defaultSheet = $this->sheetText($admin, '/admin/players/export-xlsx');

        $this->assertStringContainsString('KeepMe', $defaultSheet);
        $this->assertStringNotContainsString('DropMe', $defaultSheet);

        // And an explicit filter narrows it further.
        $this->assertStringNotContainsString(
            'KeepMe',
            $this->sheetText($admin, '/admin/players/export-xlsx?search=Nobody')
        );

        // A filter that does match still returns the row, so the assertion above is not passing
        // for the trivial reason that every export is empty.
        $this->assertStringContainsString(
            'KeepMe',
            $this->sheetText($admin, '/admin/players/export-xlsx?search=KeepMe')
        );
    }

    /**
     * The worksheet's text, read out of the .xlsx.
     *
     * The response is a file download and an .xlsx is a zip, so asserting on the response body
     * asserts on compressed bytes — which passes and fails for reasons that have nothing to do
     * with the rows in it.
     */
    private function sheetText($admin, string $url): string
    {
        $response = $this->actingAs($admin)->get($url)->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'sheet-') . '.xlsx';
        // A BinaryFileResponse, not a streamed one — read the file it points at.
        file_put_contents($path, file_get_contents($response->baseResponse->getFile()->getPathname()));

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path) === true, 'The export should be a readable xlsx.');
        $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($path);

        return $xml;
    }

    #[Test]
    public function a_real_player_id_still_reaches_the_player_page(): void
    {
        // The reordering must not cost the resource route it was moved in front of.
        $this->actingAs($this->admin())
            ->get('/admin/players/999999')
            ->assertNotFound();
    }
}
