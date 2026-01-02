<?php

namespace App\Services\MenuService;

use App\Services\Content\ContentService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class AdminMenuService
{
    /**
     * @var AdminMenuItem[][]
     */
    protected array $groups = [];

    /**
     * Add a menu item to the admin sidebar.
     *
     * @param  AdminMenuItem|array  $item  The menu item or configuration array
     * @param  string|null  $group  The group to add the item to
     *
     * @throws \InvalidArgumentException
     */
    public function addMenuItem(AdminMenuItem|array $item, ?string $group = null): void
    {
        $group = $group ?: __('Main');
        $menuItem = $this->createAdminMenuItem($item);
        if (! isset($this->groups[$group])) {
            $this->groups[$group] = [];
        }

        if ($menuItem->userHasPermission()) {
            $this->groups[$group][] = $menuItem;
        }
    }

    protected function createAdminMenuItem(AdminMenuItem|array $data): AdminMenuItem
    {
        if ($data instanceof AdminMenuItem) {
            return $data;
        }

        $menuItem = new AdminMenuItem();

        if (isset($data['children']) && is_array($data['children'])) {
            $data['children'] = array_map(
                function ($child) {
                    // Check if user is authenticated
                    $user = auth()->user();
                    if (! $user) {
                        return null;
                    }

                    // Handle permissions.
                    if (isset($child['permission'])) {
                        $child['permissions'] = $child['permission'];
                        unset($child['permission']);
                    }

                    $permissions = $child['permissions'] ?? [];
                    if (empty($permissions) || $user->hasAnyPermission((array) $permissions)) {
                        return $this->createAdminMenuItem($child);
                    }

                    return null;
                },
                $data['children']
            );

            // Filter out null values (items without permission).
            $data['children'] = array_filter($data['children']);
        }

        // Convert 'permission' to 'permissions' for consistency
        if (isset($data['permission'])) {
            $data['permissions'] = $data['permission'];
            unset($data['permission']);
        }

        // Handle route with params
        if (isset($data['route']) && isset($data['params'])) {
            $routeName = $data['route'];
            $params = $data['params'];

            if (is_array($params)) {
                $data['route'] = route($routeName, $params);
            } else {
                $data['route'] = route($routeName, [$params]);
            }
        }

        return $menuItem->setAttributes($data);
    }

    public function getMenu()
    {
        $this->addMenuItem([
            'label' => __('Dashboard'),
            'icon' => 'lucide:layout-dashboard',
            'route' => route('admin.dashboard'),
            'active' => Route::is('admin.dashboard'),
            'id' => 'dashboard',
            'priority' => 1,
            'permissions' => 'dashboard.view',
        ]);

        // Content Management Menu from registered post types
        try {
            $this->registerPostTypesInMenu();
        } catch (\Exception $e) {
            // Skip if there's an error
        }

        $this->addMenuItem([
            'label' => __('Roles & Permissions'),
            'icon' => 'lucide:key',
            'id' => 'roles-submenu',
            'active' => Route::is('admin.roles.*') || Route::is('admin.permissions.*'),
            'priority' => 20,
            'permissions' => ['role.create', 'role.view', 'role.edit', 'role.delete'],
            'children' => [
                [
                    'label' => __('Roles'),
                    'route' => route('admin.roles.index'),
                    'active' => Route::is('admin.roles.index') || Route::is('admin.roles.edit'),
                    'priority' => 10,
                    'permissions' => 'role.view',
                ],
                [
                    'label' => __('New Role'),
                    'route' => route('admin.roles.create'),
                    'active' => Route::is('admin.roles.create'),
                    'priority' => 20,
                    'permissions' => 'role.create',
                ],
                [
                    'label' => __('Permissions'),
                    'route' => route('admin.permissions.index'),
                    'active' => Route::is('admin.permissions.index') || Route::is('admin.permissions.show'),
                    'priority' => 30,
                    'permissions' => 'role.view',
                ],
            ],
        ]);

        $this->addMenuItem([
            'label' => __('Users'),
            'icon' => 'feather:users',
            'id' => 'users-submenu',
            'active' => Route::is('admin.users.*'),
            'priority' => 20,
            'permissions' => ['user.create', 'user.view', 'user.edit', 'user.delete'],
            'children' => [
                [
                    'label' => __('Users'),
                    'route' => route('admin.users.index'),
                    'active' => Route::is('admin.users.index') || Route::is('admin.users.edit'),
                    'priority' => 20,
                    'permissions' => 'user.view',
                ],
                [
                    'label' => __('New User'),
                    'route' => route('admin.users.create'),
                    'active' => Route::is('admin.users.create'),
                    'priority' => 10,
                    'permissions' => 'user.create',
                ],
            ],
        ]);





        $this->addMenuItem([
            'label' => __('Players'),
            'icon' => 'feather:user',
            'id' => 'players-submenu',
            'active' => Route::is('admin.players.*'),
            'priority' => 20,
            'permissions' => ['player.create', 'player.view', 'player.edit', 'player.delete'],
            'children' => [
                [
                    'label' => __('All Players'),
                    'route' => route('admin.players.index'),
                    'active' => Route::is('admin.players.index') || Route::is('admin.players.edit') || Route::is('admin.players.show'),
                    'priority' => 10,
                    'permissions' => 'player.view',
                ],
                [
                    'label' => __('New Player'),
                    'route' => route('admin.players.create'),
                    'active' => Route::is('admin.players.create'),
                    'priority' => 20,
                    'permissions' => 'player.create',
                ],
            ],
        ]);




        $this->addMenuItem([
            'label' => __('Matches'),
            'icon' => 'feather:calendar',
            'id' => 'matches-submenu',
            'active' => Route::is('admin.matches.*'),
            'priority' => 25,
            'permissions' => ['match.create', 'match.view', 'match.edit', 'match.delete', 'match.result', 'match.awards', 'match.scorecard'],
            'children' => [
                [
                    'label' => __('All Matches'),
                    'route' => route('admin.matches.index'),
                    'active' => Route::is('admin.matches.index') || Route::is('admin.matches.edit') || Route::is('admin.matches.show'),
                    'priority' => 10,
                    'permissions' => 'match.view',
                ],
                [
                    'label' => __('New Match'),
                    'route' => route('admin.matches.create'),
                    'active' => Route::is('admin.matches.create'),
                    'priority' => 20,
                    'permissions' => 'match.create',
                ],
            ],
        ]);

        $this->addMenuItem([
            'label' => __('Appreciations'),
            'icon' => 'feather:award',
            'id' => 'appreciations-submenu',
            'active' => Route::is('admin.appreciations.*') || Route::is('admin.matches.appreciations.*'),
            'priority' => 26,
            'permissions' => ['match_appreciation.create', 'match_appreciation.view', 'match_appreciation.edit', 'match_appreciation.delete'],
            'children' => [
                [
                    'label' => __('All Appreciations'),
                    'route' => route('admin.appreciations.index'),
                    'active' => Route::is('admin.appreciations.index') || Route::is('admin.appreciations.edit'),
                    'priority' => 10,
                    'permissions' => 'match_appreciation.view',
                ],
            ],
        ]);

        $this->addMenuItem([
            'label' => __('Auction Teams'),
            'icon' => 'feather:users',
            'id' => 'teams-submenu',
            'active' => Route::is('admin.teams.*'),
            'priority' => 25,
            'permissions' => ['team.create', 'team.view', 'team.edit', 'team.delete'],
            'children' => [
                [
                    'label' => __('All Teams'),
                    'route' => route('admin.teams.index'),
                    'active' => Route::is('admin.teams.index') || Route::is('admin.teams.edit') || Route::is('admin.teams.show'),
                    'priority' => 10,
                    'permissions' => 'team.view',
                ],
                [
                    'label' => __('New Team'),
                    'route' => route('admin.teams.create'),
                    'active' => Route::is('admin.teams.create'),
                    'priority' => 20,
                    'permissions' => 'team.create',
                ],
            ],
        ]);
        $this->addMenuItem([
            'label' => __('Actual Teams'),
            'icon' => 'feather:shield',
            'id' => 'actual-teams-submenu',
            'active' => Route::is('admin.actual-teams.*'),
            'priority' => 25,
            'permissions' => ['actual-team.create', 'actual-team.view', 'actual-team.edit', 'actual-team.delete'],
            'children' => [
                [
                    'label' => __('All Teams'),
                    'route' => route('admin.actual-teams.index'),
                    'active' => Route::is('admin.actual-teams.index') || Route::is('admin.actual-teams.edit') || Route::is('admin.actual-teams.show'),
                    'priority' => 10,
                    'permissions' => 'actual-team.view',
                ],
                [
                    'label' => __('New Team'),
                    'route' => route('admin.actual-teams.create'),
                    'active' => Route::is('admin.actual-teams.create'),
                    'priority' => 20,
                    'permissions' => 'actual-team.create',
                ],
            ],
        ]);
        $this->addMenuItem([
            'label' => __('Organizations'),
            'icon' => 'feather:briefcase',
            'id' => 'organizations-submenu',
            'active' => Route::is('admin.organizations.*'),
            'priority' => 24,
            'permissions' => ['organization.view', 'organization.create', 'organization.edit', 'organization.delete'],
            'children' => [
                [
                    'label' => __('All Organizations'),
                    'route' => route('admin.organizations.index'),
                    'active' => Route::is('admin.organizations.index') || Route::is('admin.organizations.edit') || Route::is('admin.organizations.show'),
                    'priority' => 10,
                    'permissions' => 'organization.view',
                ],
                [
                    'label' => __('New Organization'),
                    'route' => route('admin.organizations.create'),
                    'active' => Route::is('admin.organizations.create'),
                    'priority' => 20,
                    'permissions' => 'organization.create',
                ],
            ],
        ]);
        $this->addMenuItem([
            'label' => __('Auctions'),
            'icon' => 'feather:lock',
            'id' => 'auctions-submenu',
            'active' => Route::is('admin.auctions.*') || Route::is('admin.auction.organizer.*') || Route::is('team.auction.bidding.*'),
            'priority' => 30,
            'permissions' => ['auction.create', 'auction.view', 'auction.edit', 'auction.delete', 'auction.closed-bids'],
            'children' => [
                [
                    'label' => __('All Auctions'),
                    'route' => route('admin.auctions.index'),
                    'active' => Route::is('admin.auctions.index') || Route::is('admin.auctions.edit') || Route::is('admin.auctions.show') || Route::is('team.auction.bidding.*'),
                    'priority' => 10,
                    'permissions' => 'auction.view',
                ],
                [
                    'label' => __('New Auction'),
                    'route' => route('admin.auctions.create'),
                    'active' => Route::is('admin.auctions.create'),
                    'priority' => 20,
                    'permissions' => 'auction.create',
                ],
                [
                    'label' => __('Closed Bids'),
                    'route' => route('admin.auctions.closed-bids'),
                    'active' => Route::is('admin.auctions.closed-bids'),
                    'priority' => 30,
                    'permissions' => 'auction.closed-bids',
                ],
            ],
        ]);
        $this->addMenuItem([
            'label' => __('Tournaments'),
            'icon' => 'feather:flag',
            'id' => 'tournaments-submenu',
            'active' => Route::is('admin.tournaments.*'),
            'priority' => 24,
            'permissions' => [
                'tournament.create',
                'tournament.view',
                'tournament.edit',
                'tournament.delete',
                'tournament.settings',
                'tournament.registrations',
                'tournament.groups',
                'tournament.fixtures',
                'tournament.point-table',
                'tournament.statistics',
                'tournament.awards',
            ],
            'children' => [
                [
                    'label' => __('All Tournaments'),
                    'route' => route('admin.tournaments.index'),
                    'active' => Route::is('admin.tournaments.index') || Route::is('admin.tournaments.edit') || Route::is('admin.tournaments.show'),
                    'priority' => 10,
                    'permissions' => 'tournament.view',
                ],
                [
                    'label' => __('New Tournament'),
                    'route' => route('admin.tournaments.create'),
                    'active' => Route::is('admin.tournaments.create'),
                    'priority' => 20,
                    'permissions' => 'tournament.create',
                ],
            ],
        ]);

        $this->addMenuItem([
            'label' => __('Grounds'),
            'icon' => 'feather:map-pin',
            'id' => 'grounds-submenu',
            'active' => Route::is('admin.grounds.*'),
            'priority' => 26,
            'permissions' => ['ground.view', 'ground.create', 'ground.edit', 'ground.delete'],
            'children' => [
                [
                    'label' => __('All Grounds'),
                    'route' => route('admin.grounds.index'),
                    'active' => Route::is('admin.grounds.index') || Route::is('admin.grounds.edit') || Route::is('admin.grounds.show'),
                    'priority' => 10,
                    'permissions' => 'ground.view',
                ],
                [
                    'label' => __('New Ground'),
                    'route' => route('admin.grounds.create'),
                    'active' => Route::is('admin.grounds.create'),
                    'priority' => 20,
                    'permissions' => 'ground.create',
                ],
            ],
        ]);

        $this->addMenuItem([
            'label' => __('Modules'),
            'icon' => 'lucide:boxes',
            'route' => route('admin.modules.index'),
            'active' => Route::is('admin.modules.index'),
            'id' => 'modules',
            'priority' => 30,
            'permissions' => 'module.view',
        ]);

        $this->addMenuItem([
            'label' => __('Monitoring'),
            'icon' => 'lucide:monitor',
            'id' => 'monitoring-submenu',
            'active' => Route::is('admin.actionlog.*'),
            'priority' => 40,
            'permissions' => ['pulse.view', 'actionlog.view'],
            'children' => [
                [
                    'label' => __('Action Logs'),
                    'route' => route('admin.actionlog.index'),
                    'active' => Route::is('admin.actionlog.index'),
                    'priority' => 20,
                    'permissions' => 'actionlog.view',
                ],
                [
                    'label' => __('Laravel Pulse'),
                    'route' => route('pulse'),
                    'active' => false,
                    'target' => '_blank',
                    'priority' => 10,
                    'permissions' => 'pulse.view',
                ],
            ],
        ]);

        $this->addMenuItem([
            'label' => __('Settings'),
            'icon' => 'lucide:settings',
            'id' => 'settings-submenu',
            'active' => Route::is('admin.settings.*') || Route::is('admin.translations.*') || Route::is('admin.image-templates.*'),
            'priority' => 1,
            'permissions' => ['settings.edit', 'translations.view', 'image-templates.edit'],
            'children' => [
                [
                    'label' => __('General Settings'),
                    'route' => route('admin.settings.index'),
                    'active' => Route::is('admin.settings.index'),
                    'priority' => 20,
                    'permissions' => 'settings.edit',
                ],
                [
                    'label' => __('Templates'),
                    'route' => route('admin.image-templates.index'),
                    'active' => Route::is('admin.image-templates.*'),
                    'priority' => 21,
                    'permissions' => 'image-templates.edit',
                ],
                [
                    'label' => __('Translations'),
                    'route' => route('admin.translations.index'),
                    'active' => Route::is('admin.translations.*'),
                    'priority' => 10,
                    'permissions' => ['translations.view', 'translations.edit'],
                ],
                  [
                    'label' => __('Backup / restore'),
                    'route' => route('admin.backups.index'),
                    'active' => Route::is('admin.backups.*'),
                    'priority' => 10,
                    'permissions' => ['backups.view', 'backups.edit'],
                ],
            ],
        ], __('More'));


        $this->addMenuItem([
            'label' => __('Logout'),
            'icon' => 'lucide:log-out',
            'route' => route('admin.dashboard'),
            'active' => false,
            'id' => 'logout',
            'priority' => 1,
            'html' => '
                <li>
                    <form method="POST" action="' . route('logout') . '">
                        ' . csrf_field() . '
                        <button type="submit" class="menu-item group w-full text-left menu-item-inactive text-gray-700 dark:text-white hover:text-gray-700">
                            <iconify-icon icon="lucide:log-out" class="menu-item-icon " width="16" height="16"></iconify-icon>
                            <span class="menu-item-text">' . __('Logout') . '</span>
                        </button>
                    </form>
                </li>
            ',
        ], __('More'));

        $this->groups = ld_apply_filters('admin_menu_groups_before_sorting', $this->groups);

        $this->sortMenuItemsByPriority();

        return $this->applyFiltersToMenuItems();
    }

    /**
     * Register post types in the menu
     */
    protected function registerPostTypesInMenu(): void
    {
        $contentService = app(ContentService::class);
        $postTypes = $contentService->getPostTypes();

        if ($postTypes->isEmpty()) {
            return;
        }

        foreach ($postTypes as $typeName => $type) {
            // Skip if not showing in menu.
            if (isset($type->show_in_menu) && ! $type->show_in_menu) {
                continue;
            }

            // Create children menu items.
            $children = [
                [
                    'title' => __("All {$type->label}"),
                    'route' => 'admin.posts.index',
                    'params' => $typeName,
                    'active' => request()->is('admin/posts/' . $typeName) ||
                        (request()->is('admin/posts/' . $typeName . '/*') && ! request()->is('admin/posts/' . $typeName . '/create')),
                    'priority' => 10,
                    'permissions' => 'post.view',
                ],
                [
                    'title' => __('Add New'),
                    'route' => 'admin.posts.create',
                    'params' => $typeName,
                    'active' => request()->is('admin/posts/' . $typeName . '/create'),
                    'priority' => 20,
                    'permissions' => 'post.create',
                ],
            ];

            // Add taxonomies as children of this post type if this post type has them.
            if (! empty($type->taxonomies)) {
                $taxonomies = $contentService->getTaxonomies()
                    ->whereIn('name', $type->taxonomies);

                foreach ($taxonomies as $taxonomy) {
                    $children[] = [
                        'title' => __($taxonomy->label),
                        'route' => 'admin.terms.index',
                        'params' => $taxonomy->name,
                        'active' => request()->is('admin/terms/' . $taxonomy->name . '*'),
                        'priority' => 30 + $taxonomy->id, // Prioritize after standard items
                        'permissions' => 'term.view',
                    ];
                }
            }

            // Set up menu item with all children.
            $menuItem = [
                'title' => __($type->label),
                'icon' => get_post_type_icon($typeName),
                'id' => 'post-type-' . $typeName,
                'active' => request()->is('admin/posts/' . $typeName . '*') ||
                    (! empty($type->taxonomies) && $this->isCurrentTermBelongsToPostType($type->taxonomies)),
                'priority' => 10,
                'permissions' => 'post.view',
                'children' => $children,
            ];

            $this->addMenuItem($menuItem, 'Content');
        }
    }

    /**
     * Check if the current term route belongs to the given taxonomies
     */
    protected function isCurrentTermBelongsToPostType(array $taxonomies): bool
    {
        if (! request()->is('admin/terms/*')) {
            return false;
        }

        // Get the current taxonomy from the route
        $currentTaxonomy = request()->segment(3); // admin/terms/{taxonomy}

        return in_array($currentTaxonomy, $taxonomies);
    }

    protected function sortMenuItemsByPriority(): void
    {
        foreach ($this->groups as &$groupItems) {
            usort($groupItems, function ($a, $b) {
                return (int) $a->priority <=> (int) $b->priority;
            });
        }
    }

    protected function applyFiltersToMenuItems(): array
    {
        $result = [];
        foreach ($this->groups as $group => $items) {
            // Filter items by permission.
            $filteredItems = array_filter($items, function (AdminMenuItem $item) {
                return $item->userHasPermission();
            });

            // Apply filters that might add/modify menu items.
            $filteredItems = ld_apply_filters('sidebar_menu_' . strtolower($group), $filteredItems);

            // Only add the group if it has items after filtering.
            if (! empty($filteredItems)) {
                $result[$group] = $filteredItems;
            }
        }

        return $result;
    }

    public function shouldExpandSubmenu(AdminMenuItem $menuItem): bool
    {
        // If the parent menu item is active, expand the submenu.
        if ($menuItem->active) {
            return true;
        }

        // Check if any child menu item is active.
        foreach ($menuItem->children as $child) {
            if ($child->active) {
                return true;
            }
        }

        return false;
    }

    public function render(array $groupItems): string
    {
        $html = '';
        foreach ($groupItems as $menuItem) {
            $filterKey = $menuItem->id ?? Str::slug($menuItem->label) ?? '';
            $html .= ld_apply_filters('sidebar_menu_before_' . $filterKey, '');

            $html .= view('backend.layouts.partials.menu-item', [
                'item' => $menuItem,
            ])->render();

            $html .= ld_apply_filters('sidebar_menu_after_' . $filterKey, '');
        }

        return $html;
    }
}
