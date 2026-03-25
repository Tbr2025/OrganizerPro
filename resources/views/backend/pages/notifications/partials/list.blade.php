<div class="divide-y divide-gray-200 dark:divide-gray-700">
    @forelse($notifications as $notification)
        @php
            $iconMap = [
                'team' => 'mdi:account-group',
                'team-approved' => 'mdi:check-circle',
                'player' => 'mdi:account',
                'player-added' => 'mdi:account-plus',
                'captain' => 'mdi:shield-account',
                'info' => 'mdi:information',
            ];
            $icon = $iconMap[$notification->data['icon'] ?? 'info'] ?? 'mdi:information';
        @endphp
        <div class="flex items-center justify-between py-4 {{ $notification->read_at ? 'bg-gray-50 dark:bg-gray-800' : 'bg-green-50 dark:bg-green-900/20' }} px-4 rounded">
            <div class="flex items-start gap-3">
                <iconify-icon icon="{{ $icon }}" width="22" height="22" class="mt-0.5 text-gray-500 dark:text-gray-400 flex-shrink-0"></iconify-icon>
                <div>
                    <p class="text-gray-800 dark:text-gray-200">{{ $notification->data['message'] ?? 'No message' }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                    @if(isset($notification->data['page']))
                        <a href="{{ $notification->data['page'] }}" class="text-blue-500 dark:text-blue-400 text-sm underline mt-2 block">View Details</a>
                    @endif
                </div>
            </div>
            <div class="flex space-x-2 flex-shrink-0">
                @if(!$notification->read_at)
                    <button onclick="markAsRead('{{ $notification->id }}')" class="text-sm text-green-600 dark:text-green-400 border border-green-600 dark:border-green-400 px-3 py-1 rounded hover:bg-green-600 hover:text-white dark:hover:bg-green-600">
                        Mark as Read
                    </button>
                @else
                    <button onclick="markAsUnread('{{ $notification->id }}')" class="text-sm text-gray-600 dark:text-gray-400 border border-gray-600 dark:border-gray-500 px-3 py-1 rounded hover:bg-gray-600 hover:text-white dark:hover:bg-gray-600">
                        Mark as Unread
                    </button>
                @endif
            </div>
        </div>
    @empty
        <p class="text-gray-500 dark:text-gray-400 text-center py-6">No notifications found.</p>
    @endforelse
</div>

<div class="mt-4">
    {{ $notifications->links() }}
</div>
