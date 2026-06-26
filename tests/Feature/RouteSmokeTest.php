<?php

namespace Tests\Feature;

use App\Http\Controllers\Backend\MatchResultController;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Guards against route regressions from this session's changes: the unified
 * match-management page, PDF import, AJAX award endpoints, and the public
 * match pages. Pure route-table assertions — no database.
 */
class RouteSmokeTest extends TestCase
{
    #[Test]
    public function changed_admin_routes_are_registered(): void
    {
        $names = [
            'admin.matches.summary.edit',
            'admin.matches.result.edit',
            'admin.matches.result.scorecard-pdf',
            'admin.matches.summary.assign-award',
            'admin.matches.summary.remove-award',
            'admin.matches.summary.auto-assign-awards',
            'admin.tournaments.create',
            'admin.tournaments.store',
        ];

        foreach ($names as $name) {
            $this->assertTrue(Route::has($name), "Route [{$name}] is missing.");
        }
    }

    #[Test]
    public function public_match_routes_are_registered(): void
    {
        foreach (['public.match.show', 'public.match.scorecard', 'public.match.summary'] as $name) {
            $this->assertTrue(Route::has($name), "Route [{$name}] is missing.");
        }
    }

    #[Test]
    public function result_edit_route_points_at_the_redirecting_action(): void
    {
        $action = Route::getRoutes()->getByName('admin.matches.result.edit')->getActionName();

        $this->assertSame(MatchResultController::class . '@edit', $action);
    }
}
