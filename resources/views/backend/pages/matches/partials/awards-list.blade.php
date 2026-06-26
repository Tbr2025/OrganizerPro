{{-- Awards list — re-rendered standalone after AJAX add/edit/delete --}}
@if($awards->count() > 0)
    <div class="space-y-3 mb-6">
        @foreach($awards as $award)
            <div class="relative flex items-center gap-4 bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-700 rounded-xl p-4 border border-gray-200 dark:border-gray-600 hover:shadow-lg transition">
                {{-- Player Image (clickable to upload) --}}
                <div class="flex-shrink-0 relative group cursor-pointer" onclick="document.getElementById('award-img-input-{{ $award->id }}').click()">
                    @if($award->display_image)
                        <img src="{{ Storage::url($award->display_image) }}" alt="{{ $award->display_name }}"
                             id="award-img-preview-{{ $award->id }}"
                             class="w-14 h-14 rounded-full object-cover border-2 border-yellow-400 shadow-md">
                    @else
                        <div id="award-img-placeholder-{{ $award->id }}" class="w-14 h-14 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center text-lg font-bold text-gray-600 dark:text-gray-300">
                            {{ substr($award->display_name, 0, 1) }}
                        </div>
                    @endif
                    <div class="absolute inset-0 rounded-full bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <input type="file" id="award-img-input-{{ $award->id }}" accept="image/*" class="hidden"
                           onchange="uploadAwardImage({{ $award->id }}, this)">
                </div>

                {{-- Award Info --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-0.5">
                        <span class="text-lg">{{ $award->tournamentAward?->icon ?? '🏆' }}</span>
                        <span class="text-sm font-semibold text-yellow-600 dark:text-yellow-400">{{ $award->tournamentAward?->name ?? 'Award' }}</span>
                    </div>
                    <div class="font-medium text-gray-800 dark:text-gray-200">
                        {{ $award->display_name }}
                        @if(!$award->player_id)
                            <span class="text-xs bg-orange-100 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400 px-1.5 py-0.5 rounded-full ml-1">custom</span>
                        @endif
                    </div>
                    @if($award->remarks)
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $award->remarks }}</p>
                    @endif
                </div>

                {{-- Actions --}}
                <div class="flex items-center gap-1 flex-shrink-0">
                    <button type="button" class="edit-award-btn p-2 text-blue-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition" title="Edit Award"
                            data-award-id="{{ $award->tournament_award_id }}"
                            data-player-id="{{ $award->player_id }}"
                            data-custom-name="{{ $award->custom_player_name }}"
                            data-remarks="{{ $award->remarks }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </button>
                    <a href="{{ route('admin.tournaments.templates.generate', $tournament) }}?type=award_poster&match_id={{ $match->id }}@if($award->player_id)&player_id={{ $award->player_id }}@endif&award_name={{ urlencode($award->tournamentAward?->name ?? 'Award') }}@if(!$award->player_id && $award->custom_player_name)&custom_player_name={{ urlencode($award->custom_player_name) }}@endif"
                       class="p-2 text-purple-400 hover:text-purple-600 hover:bg-purple-50 dark:hover:bg-purple-900/20 rounded-lg transition" title="Generate Award Poster">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </a>
                    <form action="{{ route('admin.matches.summary.remove-award', [$match, $award]) }}" method="POST" class="award-delete-form">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition" title="Remove Award">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
@else
    <div class="text-center py-6 text-gray-400 mb-4">
        <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
        </svg>
        <p>No awards assigned yet</p>
    </div>
@endif
