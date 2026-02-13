@php
    $isEdit = isset($template);
    $positions = $isEdit ? ($template->element_positions ?? []) : $defaultPositions;
@endphp

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    {{-- Left: Form Fields --}}
    <div class="lg:col-span-1 space-y-6">

        {{-- Basic Info --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Basic Information</h3>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Template Name *</label>
                    <input type="text" name="name" value="{{ old('name', $template->name ?? '') }}"
                           class="form-control" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Template Type *</label>
                    <select name="type" class="form-control">
                        <option value="live_display" {{ old('type', $template->type ?? '') == 'live_display' ? 'selected' : '' }}>Live Display (LED Wall)</option>
                        <option value="sold_display" {{ old('type', $template->type ?? '') == 'sold_display' ? 'selected' : '' }}>Sold Display</option>
                        <option value="player_card" {{ old('type', $template->type ?? '') == 'player_card' ? 'selected' : '' }}>Player Card</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Specific Auction (Optional)</label>
                    <select name="auction_id" class="form-control">
                        <option value="">-- Global Template --</option>
                        @foreach($auctions as $id => $name)
                            <option value="{{ $id }}" {{ old('auction_id', $template->auction_id ?? '') == $id ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Leave empty to use as global default</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Canvas Width (px)</label>
                        <input type="number" name="canvas_width" value="{{ old('canvas_width', $template->canvas_width ?? 1601) }}"
                               class="form-control" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Canvas Height (px)</label>
                        <input type="number" name="canvas_height" value="{{ old('canvas_height', $template->canvas_height ?? 910) }}"
                               class="form-control" required>
                    </div>
                </div>

                <div class="flex items-center gap-6">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_default" value="1"
                               {{ old('is_default', $template->is_default ?? false) ? 'checked' : '' }}
                               class="form-checkbox rounded text-blue-600">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Set as Default</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1"
                               {{ old('is_active', $template->is_active ?? true) ? 'checked' : '' }}
                               class="form-checkbox rounded text-green-600">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Active</span>
                    </label>
                </div>
            </div>
        </div>

        {{-- Images --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Template Images</h3>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Background Image</label>
                    @if($isEdit && $template->background_image)
                        <div class="mb-2 p-2 bg-gray-100 dark:bg-gray-700 rounded">
                            <img src="{{ asset('storage/' . $template->background_image) }}" class="h-20 object-contain">
                        </div>
                    @endif
                    <input type="file" name="background_image" accept="image/*" class="form-control">
                    <p class="text-xs text-gray-500 mt-1">Recommended: 1601x910px (Max 10MB)</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sold Badge Image</label>
                    @if($isEdit && $template->sold_badge_image)
                        <div class="mb-2 p-2 bg-gray-100 dark:bg-gray-700 rounded">
                            <img src="{{ asset('storage/' . $template->sold_badge_image) }}" class="h-16 object-contain">
                        </div>
                    @endif
                    <input type="file" name="sold_badge_image" accept="image/*" class="form-control">
                    <p class="text-xs text-gray-500 mt-1">PNG with transparency recommended (Max 5MB)</p>
                </div>
            </div>
        </div>

        {{-- Submit --}}
        <div class="flex gap-4">
            <button type="submit" class="btn btn-primary flex-1">
                {{ $isEdit ? 'Update Template' : 'Create Template' }}
            </button>
            <a href="{{ route('admin.auction-templates.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </div>

    {{-- Right: Element Positions --}}
    <div class="lg:col-span-2">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Element Positions (in pixels)</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Configure the position of each element on the template. Use top/left for positioning from top-left corner, or bottom/right for positioning from bottom-right.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                {{-- Player Image --}}
                <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                    <h4 class="font-semibold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                        <span class="w-3 h-3 bg-blue-500 rounded-full"></span>
                        Player Image
                    </h4>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="text-xs text-gray-500">Bottom</label>
                            <input type="number" name="pos_player_image_bottom" value="{{ $positions['player_image']['bottom'] ?? 305 }}" class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Left</label>
                            <input type="number" name="pos_player_image_left" value="{{ $positions['player_image']['left'] ?? 114 }}" class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Width</label>
                            <input type="number" name="pos_player_image_width" value="{{ $positions['player_image']['width'] ?? 380 }}" class="form-control form-control-sm">
                        </div>
                    </div>
                </div>

                {{-- Player Name --}}
                <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                    <h4 class="font-semibold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                        <span class="w-3 h-3 bg-green-500 rounded-full"></span>
                        Player Name
                    </h4>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="text-xs text-gray-500">Top</label>
                            <input type="number" name="pos_player_name_top" value="{{ $positions['player_name']['top'] ?? 210 }}" class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Left</label>
                            <input type="number" name="pos_player_name_left" value="{{ $positions['player_name']['left'] ?? 545 }}" class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Font Size</label>
                            <input type="number" name="pos_player_name_fontSize" value="{{ $positions['player_name']['fontSize'] ?? 46 }}" class="form-control form-control-sm">
                        </div>
                    </div>
                </div>

                {{-- Player Role --}}
                <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                    <h4 class="font-semibold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                        <span class="w-3 h-3 bg-purple-500 rounded-full"></span>
                        Player Role
                    </h4>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="text-xs text-gray-500">Top</label>
                            <input type="number" name="pos_player_role_top" value="{{ $positions['player_role']['top'] ?? 275 }}" class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Left</label>
                            <input type="number" name="pos_player_role_left" value="{{ $positions['player_role']['left'] ?? 570 }}" class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Font Size</label>
                            <input type="number" name="pos_player_role_fontSize" value="{{ $positions['player_role']['fontSize'] ?? 24 }}" class="form-control form-control-sm">
                        </div>
                    </div>
                </div>

                {{-- Batting Style --}}
                <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                    <h4 class="font-semibold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                        <span class="w-3 h-3 bg-yellow-500 rounded-full"></span>
                        Batting Style
                    </h4>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="text-xs text-gray-500">Top</label>
                            <input type="number" name="pos_batting_style_top" value="{{ $positions['batting_style']['top'] ?? 334 }}" class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Left</label>
                            <input type="number" name="pos_batting_style_left" value="{{ $positions['batting_style']['left'] ?? 570 }}" class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Font Size</label>
                            <input type="number" name="pos_batting_style_fontSize" value="{{ $positions['batting_style']['fontSize'] ?? 34 }}" class="form-control form-control-sm">
                        </div>
                    </div>
                </div>

                {{-- Bowling Style --}}
                <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                    <h4 class="font-semibold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                        <span class="w-3 h-3 bg-orange-500 rounded-full"></span>
                        Bowling Style
                    </h4>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="text-xs text-gray-500">Top</label>
                            <input type="number" name="pos_bowling_style_top" value="{{ $positions['bowling_style']['top'] ?? 404 }}" class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Left</label>
                            <input type="number" name="pos_bowling_style_left" value="{{ $positions['bowling_style']['left'] ?? 570 }}" class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Font Size</label>
                            <input type="number" name="pos_bowling_style_fontSize" value="{{ $positions['bowling_style']['fontSize'] ?? 34 }}" class="form-control form-control-sm">
                        </div>
                    </div>
                </div>

                {{-- Current Bid --}}
                <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                    <h4 class="font-semibold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                        <span class="w-3 h-3 bg-red-500 rounded-full"></span>
                        Current Bid Amount
                    </h4>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="text-xs text-gray-500">Bottom</label>
                            <input type="number" name="pos_current_bid_bottom" value="{{ $positions['current_bid']['bottom'] ?? 197 }}" class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Left</label>
                            <input type="number" name="pos_current_bid_left" value="{{ $positions['current_bid']['left'] ?? 234 }}" class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Font Size</label>
                            <input type="number" name="pos_current_bid_fontSize" value="{{ $positions['current_bid']['fontSize'] ?? 32 }}" class="form-control form-control-sm">
                        </div>
                    </div>
                </div>

                {{-- Bid Label --}}
                <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                    <h4 class="font-semibold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                        <span class="w-3 h-3 bg-pink-500 rounded-full"></span>
                        Bid Label (SOLD PRICE / CURRENT BID)
                    </h4>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="text-xs text-gray-500">Bottom</label>
                            <input type="number" name="pos_bid_label_bottom" value="{{ $positions['bid_label']['bottom'] ?? 243 }}" class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Left</label>
                            <input type="number" name="pos_bid_label_left" value="{{ $positions['bid_label']['left'] ?? 186 }}" class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Font Size</label>
                            <input type="number" name="pos_bid_label_fontSize" value="{{ $positions['bid_label']['fontSize'] ?? 32 }}" class="form-control form-control-sm">
                        </div>
                    </div>
                </div>

                {{-- Sold Badge --}}
                <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                    <h4 class="font-semibold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                        <span class="w-3 h-3 bg-emerald-500 rounded-full"></span>
                        Sold Badge
                    </h4>
                    <div class="grid grid-cols-4 gap-2">
                        <div>
                            <label class="text-xs text-gray-500">Bottom</label>
                            <input type="number" name="pos_sold_badge_bottom" value="{{ $positions['sold_badge']['bottom'] ?? 27 }}" class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Left</label>
                            <input type="number" name="pos_sold_badge_left" value="{{ $positions['sold_badge']['left'] ?? 112 }}" class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Width</label>
                            <input type="number" name="pos_sold_badge_width" value="{{ $positions['sold_badge']['width'] ?? 150 }}" class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Height</label>
                            <input type="number" name="pos_sold_badge_height" value="{{ $positions['sold_badge']['height'] ?? 150 }}" class="form-control form-control-sm">
                        </div>
                    </div>
                </div>

                {{-- Team Logo --}}
                <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                    <h4 class="font-semibold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                        <span class="w-3 h-3 bg-cyan-500 rounded-full"></span>
                        Team Logo (Winning Team)
                    </h4>
                    <div class="grid grid-cols-4 gap-2">
                        <div>
                            <label class="text-xs text-gray-500">Bottom</label>
                            <input type="number" name="pos_team_logo_bottom" value="{{ $positions['team_logo']['bottom'] ?? 56 }}" class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Left</label>
                            <input type="number" name="pos_team_logo_left" value="{{ $positions['team_logo']['left'] ?? 316 }}" class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Width</label>
                            <input type="number" name="pos_team_logo_width" value="{{ $positions['team_logo']['width'] ?? 170 }}" class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Height</label>
                            <input type="number" name="pos_team_logo_height" value="{{ $positions['team_logo']['height'] ?? 100 }}" class="form-control form-control-sm">
                        </div>
                    </div>
                </div>

                {{-- Stats: Matches --}}
                <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                    <h4 class="font-semibold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                        <span class="w-3 h-3 bg-indigo-500 rounded-full"></span>
                        Stats: Matches
                    </h4>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="text-xs text-gray-500">Top</label>
                            <input type="number" name="pos_stats_matches_top" value="{{ $positions['stats_matches']['top'] ?? 550 }}" class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Left</label>
                            <input type="number" name="pos_stats_matches_left" value="{{ $positions['stats_matches']['left'] ?? 605 }}" class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Font Size</label>
                            <input type="number" name="pos_stats_matches_fontSize" value="{{ $positions['stats_matches']['fontSize'] ?? 33 }}" class="form-control form-control-sm">
                        </div>
                    </div>
                </div>

                {{-- Stats: Runs --}}
                <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                    <h4 class="font-semibold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                        <span class="w-3 h-3 bg-rose-500 rounded-full"></span>
                        Stats: Runs
                    </h4>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="text-xs text-gray-500">Top</label>
                            <input type="number" name="pos_stats_runs_top" value="{{ $positions['stats_runs']['top'] ?? 550 }}" class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Left</label>
                            <input type="number" name="pos_stats_runs_left" value="{{ $positions['stats_runs']['left'] ?? 1050 }}" class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Font Size</label>
                            <input type="number" name="pos_stats_runs_fontSize" value="{{ $positions['stats_runs']['fontSize'] ?? 33 }}" class="form-control form-control-sm">
                        </div>
                    </div>
                </div>

                {{-- Stats: Wickets --}}
                <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                    <h4 class="font-semibold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                        <span class="w-3 h-3 bg-amber-500 rounded-full"></span>
                        Stats: Wickets
                    </h4>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="text-xs text-gray-500">Top</label>
                            <input type="number" name="pos_stats_wickets_top" value="{{ $positions['stats_wickets']['top'] ?? 550 }}" class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Left</label>
                            <input type="number" name="pos_stats_wickets_left" value="{{ $positions['stats_wickets']['left'] ?? 825 }}" class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Font Size</label>
                            <input type="number" name="pos_stats_wickets_fontSize" value="{{ $positions['stats_wickets']['fontSize'] ?? 33 }}" class="form-control form-control-sm">
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
