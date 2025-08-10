<div class="divide-y divide-gray-200">
    @forelse($notifications as $notification)
        <div class="flex items-center justify-between py-4 {{ $notification->read_at ? 'bg-gray-50' : 'bg-green-50' }} px-4 rounded">
            <div>
                <p class="text-gray-800">{{ $notification->data['message'] ?? 'No message' }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                @if(isset($notification->data['page']))
                    <a href="{{ $notification->data['page'] }}" class="text-blue-500 text-sm underline mt-2 block">View Details</a>
                @endif
            </div>
            <div class="flex space-x-2">
                @if(!$notification->read_at)
                    <button onclick="markAsRead('{{ $notification->id }}')" class="text-sm text-green-600 border border-green-600 px-3 py-1 rounded hover:bg-green-600 hover:text-white">
                        Mark as Read
                    </button>
                @else
                    <button onclick="markAsUnread('{{ $notification->id }}')" class="text-sm text-gray-600 border border-gray-600 px-3 py-1 rounded hover:bg-gray-600 hover:text-white">
                        Mark as Unread
                    </button>
                @endif
            </div>
        </div>
    @empty
        <p class="text-gray-500 text-center py-6">No notifications found.</p>
    @endforelse
</div>

<div class="mt-4">
    {{ $notifications->links() }}
</div>
